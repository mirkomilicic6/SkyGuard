<?php

namespace App\Http\Controllers;

use App\Models\BorderPoliceStation;
use Anthropic\Client;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class ChatController extends Controller
{
    private string $mlBase = 'http://127.0.0.1:8001';

    /** Full-page version of the assistant — same /chat endpoint, bigger UI. */
    public function page()
    {
        return view('ai.assistant');
    }

    public function respond(Request $request)
    {
        $request->validate([
            'message' => 'required|string|max:2000',
            'history' => 'nullable|array',
        ]);

        $user = auth()->user();

        // Same role-aware scope as everywhere else in the app (admin → whole
        // administration, viewer/pilot → own station only, super admin →
        // unrestricted) — previously this always resolved the administration
        // even for a plain viewer/pilot, so e.g. a PGP Strošinci user got
        // recommendations mixed in from every other station in their uprava.
        $stationId = null;
        $adminId   = null;

        if ($user->station_id !== null) {
            if ($user->hasRole('admin')) {
                $adminId = $user->station?->police_administration_id;
            } else {
                $stationId = $user->station_id;
            }
        }

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
                    system: $this->systemPrompt($user),
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

                $this->addOwnStationMarker($mapPoints, $user);

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

        $this->addOwnStationMarker($mapPoints, $user);

        return response()->json([
            'reply' => 'Nisam uspio doći do odgovora u razumnom broju koraka. Pokušajte preformulirati pitanje.',
            'history' => $messages,
            'mapPoints' => $mapPoints,
        ]);
    }

    /**
     * Whenever the reply carries any map points (risk-grid recommendations,
     * clusters, a prediction), add the asking user's own station as a
     * distinct reference marker — raw grid coordinates mean little to a
     * pilot/viewer on their own; seeing "your station is here, the
     * recommendation is there" makes the mini map actually readable.
     * Super admin (no station) and callers with nothing else to show get no
     * marker, so a plain text-only reply never grows an unrelated pin.
     */
    private function addOwnStationMarker(array &$points, $user): void
    {
        if (empty($points) || $user->station_id === null) {
            return;
        }

        $station = $user->station;
        if (!$station || $station->latitude === null || $station->longitude === null) {
            return;
        }

        array_unshift($points, [
            'lat'   => (float) $station->latitude,
            'lon'   => (float) $station->longitude,
            'kind'  => 'station',
            'value' => $station->name,
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
                $points[] = ['lat' => (float) $c['lat'], 'lon' => (float) $c['lon'], 'kind' => 'increase', 'value' => $c['risk'] ?? null, 'near' => $c['near'] ?? null];
            }
        }
        foreach ($data['recommend_decrease'] ?? [] as $c) {
            if (isset($c['lat'], $c['lon'])) {
                $points[] = ['lat' => (float) $c['lat'], 'lon' => (float) $c['lon'], 'kind' => 'decrease', 'value' => $c['risk'] ?? null, 'near' => $c['near'] ?? null];
            }
        }
    }

    private function addClusterPoints(array $data, array &$points): void
    {
        foreach ($data['clusters'] ?? [] as $cl) {
            if (isset($cl['lat'], $cl['lon'])) {
                $points[] = ['lat' => (float) $cl['lat'], 'lon' => (float) $cl['lon'], 'kind' => 'cluster', 'value' => $cl['risk'] ?? null, 'near' => $cl['near'] ?? null];
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

    private function systemPrompt($user): string
    {
        $stationContext = 'Korisnik je super admin i vidi podatke sa svih postaja.';
        if ($user->station_id !== null) {
            $station = $user->station;
            $place = $station ? preg_replace('/^PGP\s+/u', '', $station->name) : null;
            $stationContext = $station
                ? "Korisnik pripada postaji \"{$station->name}\" (mjesto {$place}), na koordinatama "
                    . round((float) $station->latitude, 4) . ', ' . round((float) $station->longitude, 4) . '.'
                : 'Korisnik pripada postaji, ali njena lokacija nije poznata.';
        }

        return <<<PROMPT
Ti si AI asistent ugrađen u SkyGuard, sustav za upravljanje dronovima granične policije.
Korisnici su policijski službenici koji nadziru granicu prema Bosni i Hercegovini i Srbiji dronovima i lovnim kamerama.
Odgovaraj isključivo na hrvatskom jeziku, kratko i konkretno, bez nepotrebnog uvoda.
{$stationContext}
Kad korisnik pita o rizičnim zonama, gdje pojačati ili smanjiti nadzor/letove, ili o statistici detekcija, MORAŠ prvo pozvati odgovarajući alat da dohvatiš stvarne podatke - nikad ne izmišljaj brojke, koordinate ni imena mjesta.
Rezultati get_risk_grid i get_clusters uz svaku točku uključuju polje "near" s ljudski čitljivim opisom lokacije (npr. "2 km sjeverno od mjesta Ilok, uz rijeku Dunav") - UVIJEK opisuj lokaciju korisniku pomoću tog polja, nikad samo sirovim koordinatama. Koordinate smiješ dodati u zagradi kao dodatnu, sporednu informaciju, zaokružene na 4 decimale, ali nikad kao jedini opis lokacije.
Ako korisnik ne navede lokaciju/vrijeme za predict_location_risk, koristi trenutni datum/vrijeme kao razumnu pretpostavku; za lokaciju koristi koordinate korisnikove postaje iz konteksta iznad ako ništa drugo nije navedeno.
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
                'get_risk_grid' => $this->enrichRiskGrid(
                    $this->trimRiskGrid(Http::timeout(15)->get("{$this->mlBase}/risk-grid", $query)->json()),
                    $stationId, $adminId
                ),
                'get_clusters' => $this->enrichClusters(
                    $this->trimClusters(Http::timeout(8)->get("{$this->mlBase}/clusters", $query)->json()),
                    $stationId, $adminId
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

    /**
     * Stations the current query is allowed to reference: just the user's
     * own station (viewer/pilot), every station in their administration
     * (admin), or all of them (super admin — used only for the "near"
     * description below, never to restrict).
     */
    private function referenceStations(?int $stationId, ?int $adminId): Collection
    {
        if ($stationId) {
            return BorderPoliceStation::where('id', $stationId)->get();
        }
        if ($adminId) {
            return BorderPoliceStation::where('police_administration_id', $adminId)->get();
        }

        return BorderPoliceStation::all();
    }

    /**
     * For each lat/lon point: find the nearest in-scope station, attach a
     * human-readable "near" description built from it, and — only when the
     * query is scoped to a single station (a viewer/pilot asking about
     * their own postaja) — drop any point that falls outside that station's
     * own territory. This is the actual fix for recommendations "leaking"
     * outside a station's own area: it's no longer enough for a point to
     * merely be built from that station's detections, it must now also
     * geographically fall within the station's territory (drawn boundary,
     * or the default 10km radius circle) to be shown at all.
     */
    private function enrichLocations(array $points, Collection $stations, bool $restrictToStations): array
    {
        if ($stations->isEmpty()) {
            return $restrictToStations ? [] : $points;
        }

        $kept = [];
        foreach ($points as $point) {
            if (!isset($point['lat'], $point['lon'])) {
                continue;
            }
            $lat = (float) $point['lat'];
            $lon = (float) $point['lon'];

            $nearest = null;
            $nearestDist = null;
            foreach ($stations as $station) {
                if ($station->latitude === null) {
                    continue;
                }
                $d = $station->distanceKm($lat, $lon);
                if ($nearestDist === null || $d < $nearestDist) {
                    $nearestDist = $d;
                    $nearest = $station;
                }
            }

            if ($restrictToStations && (!$nearest || !$nearest->containsPoint($lat, $lon))) {
                continue;
            }

            if ($nearest) {
                $point['near'] = $this->describeLocation($lat, $lon, $nearest, $nearestDist);
            }

            $kept[] = $point;
        }

        return $kept;
    }

    private function describeLocation(float $lat, float $lon, BorderPoliceStation $station, float $distanceKm): string
    {
        $place = preg_replace('/^PGP\s+/u', '', $station->name);

        $desc = $distanceKm < 1
            ? "u neposrednoj blizini mjesta {$place}"
            : round($distanceKm) . ' km ' . $this->compassBearing((float) $station->latitude, (float) $station->longitude, $lat, $lon) . " od mjesta {$place}";

        if (!empty($station->landmark)) {
            $desc .= ", {$station->landmark}";
        }

        return $desc;
    }

    private function compassBearing(float $lat0, float $lon0, float $lat1, float $lon1): string
    {
        $dLat = $lat1 - $lat0;
        $dLon = ($lon1 - $lon0) * cos(deg2rad($lat0));
        $angleDeg = rad2deg(atan2($dLon, $dLat));
        if ($angleDeg < 0) {
            $angleDeg += 360;
        }

        $labels = ['sjeverno', 'sjeveroistočno', 'istočno', 'jugoistočno', 'južno', 'jugozapadno', 'zapadno', 'sjeverozapadno'];

        return $labels[(int) round($angleDeg / 45) % 8];
    }

    private function enrichRiskGrid(array $data, ?int $stationId, ?int $adminId): array
    {
        $stations = $this->referenceStations($stationId, $adminId);
        $restrict = $stationId !== null;

        $data['recommend_increase'] = $this->enrichLocations($data['recommend_increase'] ?? [], $stations, $restrict);
        $data['recommend_decrease'] = $this->enrichLocations($data['recommend_decrease'] ?? [], $stations, $restrict);

        return $data;
    }

    private function enrichClusters(array $data, ?int $stationId, ?int $adminId): array
    {
        $stations = $this->referenceStations($stationId, $adminId);
        $restrict = $stationId !== null;

        $data['clusters'] = $this->enrichLocations($data['clusters'] ?? [], $stations, $restrict);

        return $data;
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
