<?php

namespace App\Http\Controllers;

use App\Models\BorderPoliceStation;
use App\Services\RecommendationService;
use Anthropic\Client;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class ChatController extends Controller
{
    private string $mlBase;

    public function __construct()
    {
        $this->mlBase = config('services.ml.url');
    }

    // Asistent na zasebnoj stranici (isti /chat, veći prikaz)
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

        // opseg podataka kao u ostatku aplikacije: administrator vidi upravu,
        // analitičar i pilot svoju postaju, glavni administrator sve
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

    // Ako odgovor ima točke za kartu, dodaje se i postaja korisnika kao orijentir.
    // Glavni administrator nema postaju, pa se tada ne dodaje.
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

    // Izdvaja žarišta s koordinatama iz rezultata alata za malu kartu u razgovoru
    private function collectMapPoints(string $name, array $input, mixed $result, array &$points): void
    {
        if (!is_array($result)) {
            return;
        }

        match ($name) {
            'get_zone_risk' => $this->addZoneRiskPoints($result, $points),
            'get_zones' => $this->addZonePoints($result, $points),
            default => null,
        };
    }

    // Vrsta preporuke i oznaka na karti
    private const ZONE_RISK_KIND = [
        'increase' => 'increase',
        'maintain' => 'maintain',
        'consider_reduction' => 'decrease',
        'insufficient_data' => 'insufficient',
    ];

    private function addZoneRiskPoints(array $data, array &$points): void
    {
        foreach ($data['zones'] ?? [] as $z) {
            $kind = self::ZONE_RISK_KIND[$z['recommendation'] ?? ''] ?? null;
            if ($kind && isset($z['lat'], $z['lon'])) {
                $points[] = ['lat' => (float) $z['lat'], 'lon' => (float) $z['lon'], 'kind' => $kind,
                    'value' => $z['id'] . ($z['risk_index'] !== null ? " · indeks rizika {$z['risk_index']}" : ''), 'near' => $z['near'] ?? null];
            }
        }
    }

    private function addZonePoints(array $data, array &$points): void
    {
        foreach ($data['zones'] ?? [] as $z) {
            if (isset($z['lat'], $z['lon'])) {
                $points[] = ['lat' => (float) $z['lat'], 'lon' => (float) $z['lon'], 'kind' => 'cluster',
                    'value' => $z['id'] . ' · ' . ($z['detections_count'] ?? 0) . ' det.', 'near' => $z['near'] ?? null];
            }
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

        $days = ['ponedjeljak', 'utorak', 'srijeda', 'četvrtak', 'petak', 'subota', 'nedjelja'];
        $now = now();
        $today = $days[$now->dayOfWeekIso - 1] . ', ' . $now->format('d.m.Y. H:i')
            . ' (dow=' . ($now->dayOfWeekIso - 1) . ', block=' . intdiv($now->hour, 4) . ')';

        return <<<PROMPT
Ti si AI asistent ugrađen u SkyGuard, sustav za upravljanje dronovima granične policije.
Korisnici su policijski službenici koji nadziru granicu prema Bosni i Hercegovini i Srbiji dronovima i lovnim kamerama.
Odgovaraj isključivo na hrvatskom jeziku, kratko i konkretno, bez nepotrebnog uvoda.
{$stationContext}
Danas je {$today}.
Rizik se procjenjuje po žarištima (zonama K1, K2, ...) koja je algoritam DBSCAN izdvojio iz povijesnih detekcija; K1 je zona s najviše detekcija. Dan je podijeljen na 6 vremenskih blokova od 4 sata: 0 = 00–04, 1 = 04–08, 2 = 08–12, 3 = 12–16, 4 = 16–20, 5 = 20–24 h.
Kad korisnik pita o rizičnim zonama, gdje pojačati ili smanjiti nadzor/letove, ili o statistici detekcija, MORAŠ prvo pozvati odgovarajući alat da dohvatiš stvarne podatke - nikad ne izmišljaj brojke, koordinate, zone ni imena mjesta.
Za pitanja "gdje je rizik / kamo poslati dron" koristi get_zone_risk; ako korisnik navede dan ili doba dana (npr. "večeras", "u subotu ujutro"), pretvori to u dow i block. Za opis samih žarišta i dosadašnjeg nadzora koristi get_zones. Za pitanja o pouzdanosti modela koristi get_model_quality.
Polje "risk_index" iz get_zone_risk je relativni indeks rizika (0-100) kojim model rangira zone; NIJE vjerojatnost - nikad ga ne prikazuj kao postotak ni kao "vjerojatnost detekcije", nego kao "indeks rizika X/100" i koristi ga za usporedbu zona. To je procjena modela strojnog učenja, ne sigurnost. Zone s preporukom "insufficient_data" nemaju dovoljno letova za pouzdanu procjenu; reci to umjesto da navodiš broj.
Rezultati uz svaku zonu uključuju polje "near" s ljudski čitljivim opisom lokacije (npr. "2 km sjeverno od mjesta Ilok, uz rijeku Dunav") - UVIJEK opisuj lokaciju korisniku pomoću tog polja i oznake zone, nikad samo sirovim koordinatama. Koordinate smiješ dodati u zagradi kao sporednu informaciju, zaokružene na 4 decimale.
PROMPT;
    }

    private function toolDefinitions(): array
    {
        $emptyObject = new \stdClass();

        return [
            [
                'name' => 'get_zone_risk',
                'description' => 'Procjena rizika po DBSCAN žarištima (K1, K2, ...) za zadani dan i vremenski blok: indeks rizika 0-100 (relativni, za rangiranje zona; nije vjerojatnost) prema najboljem modelu iz usporedbe modela, preporuka (increase = pojačati nadzor, maintain = zadržati, consider_reduction = razmotriti smanjenje, insufficient_data = premalo podataka) i kratko obrazloženje. Zone su poredane od najvećeg rizika. Bez dow/block vrijedi za trenutni dan i blok.',
                'input_schema' => [
                    'type' => 'object',
                    'properties' => [
                        'dow' => ['type' => 'integer', 'description' => 'Dan u tjednu, 0=ponedjeljak .. 6=nedjelja'],
                        'block' => ['type' => 'integer', 'description' => 'Vremenski blok 0-5 (0=00-04, 1=04-08, 2=08-12, 3=12-16, 4=16-20, 5=20-24)'],
                    ],
                ],
            ],
            [
                'name' => 'get_zones',
                'description' => 'Opis DBSCAN žarišta: broj detekcija, broj letova i sati nadzora kroz zonu, detekcije po satu leta, najaktivniji dan i vremenski blok, trend zadnjih 30 dana u odnosu na prethodnih 30.',
                'input_schema' => ['type' => 'object', 'properties' => $emptyObject],
            ],
            [
                'name' => 'get_model_quality',
                'description' => 'Rezultati usporedbe modela strojnog učenja koji procjenjuju rizik po zonama (kronološka evaluacija): koji je model najbolji i njegove mjere (F1, ROC-AUC, prosječna preciznost, pokrivenost 5/10 najrizičnijih zona). Koristi za pitanja o pouzdanosti procjena.',
                'input_schema' => ['type' => 'object', 'properties' => $emptyObject],
            ],
            [
                'name' => 'get_insights',
                'description' => 'Opće statistike detekcija: total = ukupan broj detekcija u CIJELOM razdoblju podataka (ne u zadnjih 30 dana), last_30d = broj detekcija u zadnjih 30 dana, by_type = broj osoba/entiteta po vrsti, by_source = broj detekcija po izvoru, trend_30d i trend_slope = smjer kretanja dnevnog broja detekcija u zadnjih 30 dana (rast/pad/stabilan).',
                'input_schema' => ['type' => 'object', 'properties' => $emptyObject],
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
                'get_zone_risk' => $this->zoneRisk($input, $stationId, $adminId),
                'get_zones' => $this->zones($query, $stationId, $adminId),
                'get_model_quality' => $this->modelQuality(
                    Http::timeout(240)->get("{$this->mlBase}/zone-model-metrics", $query)->json()
                ),
                'get_insights' => Http::timeout(8)->get("{$this->mlBase}/insights", $query)->json(),
                default => ['error' => 'Nepoznat alat'],
            };
        } catch (\Throwable $e) {
            return ['error' => 'ML servis nedostupan'];
        }
    }

    // Postaje koje se smiju koristiti za opis lokacije: vlastita postaja,
    // postaje uprave ili sve postaje za glavnog administratora
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

    // Svakoj točki dodaje opis lokacije prema najbližoj postaji.
    // Za korisnike jedne postaje izbacuje točke izvan područja postaje
    // (ucrtana granica ili krug od 10 km).
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

    // Dani u tjednu kako ih broji MySQL (1 = nedjelja)
    private const MYSQL_DOW_LABELS = [1 => 'nedjelja', 2 => 'ponedjeljak', 3 => 'utorak', 4 => 'srijeda',
        5 => 'četvrtak', 6 => 'petak', 7 => 'subota'];

    private function zoneRisk(array $input, ?int $stationId, ?int $adminId): array
    {
        $dow = isset($input['dow']) && $input['dow'] >= 0 && $input['dow'] <= 6 ? (int) $input['dow'] : null;
        $block = isset($input['block']) && $input['block'] >= 0 && $input['block'] <= 5 ? (int) $input['block'] : null;

        $zones = app(RecommendationService::class)->forDbscanZones($adminId, $stationId, $dow, $block);
        if ($zones->isEmpty()) {
            return ['error' => 'Nema procjene rizika — premalo detekcija za formiranje zona ili je ML servis nedostupan.'];
        }

        $rows = $zones
            ->sortByDesc(fn ($z) => $z['probability'] ?? -1)
            ->map(fn ($z) => [
                'id' => $z['id'],
                'lat' => $z['lat'],
                'lon' => $z['lon'],
                'risk_index' => $z['probability'],
                'recommendation' => $z['type'],
                'explanation' => $z['explanation'],
                'detections_count' => $z['detections_count'],
                'detections_last_30d' => $z['recent_count'],
                'flights_count' => $z['flights_count'],
                'total_hours' => $z['total_hours'],
                'trend_pct' => $z['trend_pct'],
            ])
            ->values()
            ->all();

        $kept = $this->enrichLocations($rows, $this->referenceStations($stationId, $adminId), $stationId !== null);

        return [
            'dow' => $dow ?? now()->dayOfWeekIso - 1,
            'block' => $block ?? intdiv(now()->hour, 4),
            'zones' => $kept,
        ] + $this->outsideTerritoryNote($rows, $kept);
    }

    // Ako žarišta postaje postoje, ali su izvan njezina područja, modelu se to objašnjava
    // napomenom, inače bi dobio prazan popis i morao bi nagađati razlog.
    private function outsideTerritoryNote(array $rows, array $kept): array
    {
        if (empty($rows) || !empty($kept)) {
            return [];
        }

        return ['napomena' => 'Iz detekcija postaje izdvojeno je ' . count($rows) . ' žarište/a, ali se nijedno ne nalazi unutar područja postaje '
            . '(ucrtana granica ili krug od 10 km oko postaje), pa se ne prikazuju. Procjena za područje postaje zato nije dostupna; to nije posljedica nedostatka podataka.'];
    }

    private function zones(array $query, ?int $stationId, ?int $adminId): array
    {
        $stats = Http::timeout(60)->get("{$this->mlBase}/zones/flight-stats", $query)->json('zones');
        if (empty($stats)) {
            return ['error' => 'Nema žarišta — premalo detekcija za formiranje zona.'];
        }

        $blocks = config('surveillance.time_blocks');
        $rows = array_map(fn ($z) => [
            'id' => $z['id'],
            'lat' => $z['lat'],
            'lon' => $z['lon'],
            'detections_count' => $z['detections_count'],
            'detections_last_30d' => $z['recent_count'] ?? null,
            'flights_count' => $z['flights_count'],
            'total_hours' => $z['total_hours'],
            'detections_per_flight_hour' => $z['rate_per_hour'],
            'most_active_day' => self::MYSQL_DOW_LABELS[$z['most_active_dow'] ?? 0] ?? null,
            'most_active_block' => $blocks[$z['most_active_block'] ?? -1]['label'] ?? null,
            'trend_pct' => $z['trend_pct'],
            'last_detection_at' => $z['last_detection_at'],
        ], $stats);

        $kept = $this->enrichLocations($rows, $this->referenceStations($stationId, $adminId), $stationId !== null);

        return ['zones' => $kept] + $this->outsideTerritoryNote($rows, $kept);
    }

    // Samo glavne mjere modela, bez krivulja i važnosti obilježja
    private function modelQuality(?array $data): array
    {
        if (empty($data['trained'])) {
            return ['error' => $data['message'] ?? 'Modeli nisu istrenirani — premalo podataka.'];
        }

        $nZones = $data['n_zones'] ?? null;
        $models = [];
        foreach ($data['models'] as $key => $m) {
            $models[$key] = [
                'naziv' => $m['label'],
                'f1' => $m['f1'],
                'precision' => $m['precision'],
                'recall' => $m['recall'],
                'roc_auc' => $m['roc_auc'],
                'average_precision' => $m['average_precision'],
                'pokrivenost_top5_zona' => $m['topk_coverage']['coverage_at_5'] ?? null,
                'pokrivenost_top10_zona' => $m['topk_coverage']['coverage_at_10'] ?? null,
            ];
        }

        return [
            'najbolji_model' => $data['best_model'],
            'kriterij_odabira' => 'najveći F1 na testnom skupu',
            'testno_razdoblje_od' => $data['split_date'],
            'broj_primjera' => $data['n_rows'],
            'udio_pozitivnih' => round($data['n_positive'] / max(1, $data['n_rows']), 3),
            'broj_zona' => $nZones,
            // pokrivenost top-K ima smisla samo uz usporedbu s nasumičnim odabirom
            'nasumicni_odabir_top5_zona' => $nZones ? round(min(1, 5 / $nZones), 3) : null,
            'nasumicni_odabir_top10_zona' => $nZones ? round(min(1, 10 / $nZones), 3) : null,
            'napomena' => 'Prosječna preciznost nasumičnog modela približno je jednaka udjelu pozitivnih primjera. Pokrivenost top-K zona usporedi s nasumičnim odabirom; ako zona ima K ili manje, pokrivenost je trivijalno 1 i ništa ne govori o modelu.',
            'modeli' => $models,
        ];
    }
}
