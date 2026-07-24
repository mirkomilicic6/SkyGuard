<?php

namespace App\Http\Controllers;

use Anthropic\Client;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class ChatController extends Controller
{
    private string $mlBase = 'http://127.0.0.1:8001';

    public function respond(Request $request)
    {
        $request->validate([
            'message' => 'required|string|max:2000',
            'history' => 'nullable|array',
        ]);

        $user = auth()->user();
        $stationId = $user->station_id;
        $adminId = $stationId ? $user->station?->administration?->id : null;

        $messages = $request->input('history', []);
        $messages[] = ['role' => 'user', 'content' => $request->input('message')];

        try {
            $client = new Client();
        } catch (\Throwable $e) {
            return response()->json(['error' => 'AI asistent nije konfiguriran (nedostaje API ključ).'], 503);
        }

        $tools = $this->toolDefinitions();
        $mapPoints = [];

        for ($i = 0; $i < 5; $i++) {
            try {
                $response = $client->messages->create(
                    model: 'claude-opus-4-8',
                    maxTokens: 1536,
                    system: $this->systemPrompt(),
                    messages: $messages,
                    tools: $tools,
                );
            } catch (\Throwable $e) {
                Log::warning('AI chat request failed', ['error' => $e->getMessage()]);
                return response()->json(['error' => 'Greška pri komunikaciji s AI servisom.'], 502);
            }

            $toolUses = array_values(array_filter(
                $response->content,
                fn ($b) => $b->type === 'tool_use'
            ));

            if (empty($toolUses)) {
                $text = collect($response->content)
                    ->filter(fn ($b) => $b->type === 'text')
                    ->map(fn ($b) => $b->text)
                    ->implode("\n");

                $messages[] = ['role' => 'assistant', 'content' => $text];

                return response()->json([
                    'reply' => $text,
                    'history' => $messages,
                    'mapPoints' => $mapPoints,
                ]);
            }

            $messages[] = ['role' => 'assistant', 'content' => $response->content];

            $toolResults = [];
            foreach ($toolUses as $block) {
                $result = $this->executeTool($block->name, $block->input, $stationId, $adminId);
                $this->collectMapPoints($block->name, $block->input, $result, $mapPoints);
                $toolResults[] = [
                    'type' => 'tool_result',
                    'tool_use_id' => $block->id,
                    'content' => json_encode($result),
                ];
            }

            $messages[] = ['role' => 'user', 'content' => $toolResults];
        }

        return response()->json([
            'reply' => 'Nisam uspio doći do odgovora u razumnom broju koraka. Pokušajte preformulirati pitanje.',
            'history' => $messages,
            'mapPoints' => $mapPoints,
        ]);
    }

    /**
     * Pull lat/lon-bearing recommendations out of a tool result so the widget
     * can plot them on a mini map instead of the pilot having to eyeball
     * coordinates in the chat text.
     */
    private function collectMapPoints(string $name, array $input, mixed $result, array &$points): void
    {
        if (!is_array($result)) {
            return;
        }

        match ($name) {
            'get_risk_grid' => $this->addGridPoints($result, $points),
            'get_clusters' => $this->addClusterPoints($result, $points),
            'predict_location_risk' => $this->addPredictionPoint($input, $result, $points),
            default => null,
        };
    }

    private function addGridPoints(array $data, array &$points): void
    {
        foreach ($data['recommend_increase'] ?? [] as $c) {
            if (isset($c['lat'], $c['lon'])) {
                $points[] = ['lat' => (float) $c['lat'], 'lon' => (float) $c['lon'], 'kind' => 'increase', 'value' => $c['risk'] ?? null];
            }
        }
        foreach ($data['recommend_decrease'] ?? [] as $c) {
            if (isset($c['lat'], $c['lon'])) {
                $points[] = ['lat' => (float) $c['lat'], 'lon' => (float) $c['lon'], 'kind' => 'decrease', 'value' => $c['risk'] ?? null];
            }
        }
    }

    private function addClusterPoints(array $data, array &$points): void
    {
        foreach ($data['clusters'] ?? [] as $cl) {
            if (isset($cl['lat'], $cl['lon'])) {
                $points[] = ['lat' => (float) $cl['lat'], 'lon' => (float) $cl['lon'], 'kind' => 'cluster', 'value' => $cl['risk'] ?? null];
            }
        }
    }

    private function addPredictionPoint(array $input, mixed $result, array &$points): void
    {
        if (isset($input['lat'], $input['lon'])) {
            $points[] = [
                'lat' => (float) $input['lat'],
                'lon' => (float) $input['lon'],
                'kind' => 'prediction',
                'value' => is_array($result) ? ($result['risk'] ?? null) : null,
            ];
        }
    }

    private function systemPrompt(): string
    {
        return <<<PROMPT
Ti si AI asistent ugrađen u SkyGuard, sustav za upravljanje dronovima granične policije.
Korisnici su policijski službenici koji nadziru granicu prema Bosni i Hercegovini i Srbiji dronovima i lovnim kamerama.
Odgovaraj isključivo na hrvatskom jeziku, kratko i konkretno, bez nepotrebnog uvoda.
Kad korisnik pita o rizičnim zonama, gdje pojačati ili smanjiti nadzor/letove, ili o statistici detekcija, MORAŠ prvo pozvati odgovarajući alat da dohvatiš stvarne podatke - nikad ne izmišljaj brojke ili koordinate.
Koordinate zaokruži na 4 decimale. Ako korisnik ne navede lokaciju/vrijeme za predict_location_risk, koristi trenutni datum/vrijeme kao razumnu pretpostavku.
PROMPT;
    }

    private function toolDefinitions(): array
    {
        $emptyObject = new \stdClass();

        return [
            [
                'name' => 'get_risk_grid',
                'description' => 'Prediktivna mreža rizika uz granicu (model treniran na povijesnim detekcijama), uspoređena s dosadašnjom pokrivenošću letova. Vraća popis preporuka gdje pojačati i gdje smanjiti nadzor. Koristi ovo za pitanja o tome gdje treba više/manje nadzora ili letova.',
                'input_schema' => ['type' => 'object', 'properties' => $emptyObject],
            ],
            [
                'name' => 'get_clusters',
                'description' => 'DBSCAN klasteri (vruće točke) povijesnih detekcija s procjenom rizika po zoni.',
                'input_schema' => ['type' => 'object', 'properties' => $emptyObject],
            ],
            [
                'name' => 'get_insights',
                'description' => 'Opće statistike: ukupan broj detekcija, raspodjela po tipu, trend zadnjih 30 dana, postotak potvrđenih, broj kritičnih detekcija.',
                'input_schema' => ['type' => 'object', 'properties' => $emptyObject],
            ],
            [
                'name' => 'predict_location_risk',
                'description' => 'Procjena rizika za konkretnu lokaciju, sat i dan u tjednu.',
                'input_schema' => [
                    'type' => 'object',
                    'properties' => [
                        'lat' => ['type' => 'number', 'description' => 'Zemljopisna širina'],
                        'lon' => ['type' => 'number', 'description' => 'Zemljopisna dužina'],
                        'hour' => ['type' => 'integer', 'description' => 'Sat, 0-23'],
                        'dow' => ['type' => 'integer', 'description' => 'Dan u tjednu, 0=ponedjeljak .. 6=nedjelja'],
                    ],
                    'required' => ['lat', 'lon', 'hour', 'dow'],
                ],
            ],
        ];
    }

    private function executeTool(string $name, array $input, ?int $stationId, ?int $adminId): mixed
    {
        $query = array_filter([
            'station_id' => $stationId,
            'administration_id' => $adminId,
        ]);

        try {
            return match ($name) {
                'get_risk_grid' => $this->trimRiskGrid(
                    Http::timeout(15)->get("{$this->mlBase}/risk-grid", $query)->json()
                ),
                'get_clusters' => $this->trimClusters(
                    Http::timeout(8)->get("{$this->mlBase}/clusters", $query)->json()
                ),
                'get_insights' => Http::timeout(8)->get("{$this->mlBase}/insights", $query)->json(),
                'predict_location_risk' => Http::timeout(6)
                    ->get("{$this->mlBase}/predict", array_merge($query, $input))
                    ->json(),
                default => ['error' => 'Nepoznat alat'],
            };
        } catch (\Throwable $e) {
            return ['error' => 'ML servis nedostupan'];
        }
    }

    /** Drop the full per-cell grid — the chat only needs the summary + top recommendations. */
    private function trimRiskGrid(?array $data): array
    {
        if (!$data) {
            return ['error' => 'Nema odgovora od ML servisa'];
        }

        return [
            'message' => $data['message'] ?? null,
            'cell_km' => $data['cell_km'] ?? null,
            'trained_on' => $data['trained_on'] ?? null,
            'recommend_increase' => $data['recommend_increase'] ?? [],
            'recommend_decrease' => $data['recommend_decrease'] ?? [],
        ];
    }

    /** Drop the raw noise points — keep only the identified clusters. */
    private function trimClusters(?array $data): array
    {
        if (!$data) {
            return ['error' => 'Nema odgovora od ML servisa'];
        }

        return [
            'clusters' => $data['clusters'] ?? [],
            'total_detections' => $data['total_detections'] ?? 0,
        ];
    }
}
