<?php

namespace App\Console\Commands;

use App\Models\BorderPoliceStation;
use App\Models\Detection;
use App\Models\Flight;
use App\Models\GpxPoint;
use App\Models\HuntingCamera;
use App\Models\PoliceAdministration;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

/**
 * Builds a synthetic flights+detections dataset for the thesis ML pipeline,
 * replacing whatever is currently in `flights`/`gpx_points`/`detections`.
 *
 * Design goals agreed with the thesis advisor scenario (see conversation):
 *  - Realistic source mix: 40% drone, 35% trail camera, 25% ground/manual.
 *  - ~Half of all flights carry zero detections ("not seen" ≠ "nothing
 *    happened") — the rest carry a variable, not fixed, count.
 *  - Spatial pattern per station: 60% in a couple of persistent hotspots,
 *    25% scattered, 15% in a temporary hotspot that is only "active" for a
 *    contiguous window within the year (appears/disappears). Trail cameras
 *    instead use their own already-fixed real coordinates.
 *  - Temporal pattern: all months/hours represented, but uneven — quiet
 *    baseline days plus a handful of pronounced night- or weekend-skewed
 *    "episodes" per station.
 *  - Referential integrity: drone detections always sit on their flight's
 *    actual path and inside its time window; every detection's station
 *    matches its flight/camera's station; created_by always resolves to a
 *    real user via a station → administration → super-admin fallback chain
 *    (never invented).
 */
class GenerateSyntheticDataset extends Command
{
    protected $signature = 'dataset:generate-synthetic
        {--flights=250 : Target number of flights to generate}
        {--detections=1500 : Target number of detections to generate}
        {--months=12 : Length of the generation window, in months, ending now}
        {--active-pct=50 : % chance a given flight carries any detection at all}
        {--no-wipe : Do not truncate existing flights/gpx_points/detections first}';

    protected $description = 'Replace flights/gpx_points/detections with a designed synthetic dataset (border-anchored flights, 3 detection sources, hotspot + episodic patterns)';

    /** Real-world station-town coordinates, same list as flights:generate-border. */
    private const STATION_COORDS = [
        'PGP Slunj' => [45.1122, 15.5828], 'PGP Cetingrad' => [45.0333, 15.7500],
        'PGP Vojnić' => [45.3086, 15.7167], 'PGP Maljevac' => [44.9800, 15.7500],
        'PGP Rakovica' => [44.9667, 15.6833], 'PGP Krnjak' => [45.2333, 15.7667],
        'PGP Hrvatska Kostajnica' => [45.1897, 16.5589], 'PGP Dvor na Uni' => [45.0667, 16.3667],
        'PGP Donji Kukuruzari' => [45.1500, 16.4500], 'PGP Jasenovac' => [45.2764, 16.9235],
        'PGP Sunja' => [45.3833, 16.5833], 'PGP Hrvatska Dubica' => [45.1833, 16.8000],
        'PGP Ilok' => [45.2167, 19.3667], 'PGP Tovarnik' => [45.1667, 19.1333],
        'PGP Lipovac' => [45.0333, 19.1167], 'PGP Babska' => [45.1167, 19.2167],
        'PGP Nijemci' => [45.0167, 18.9667], 'PGP Strošinci' => [44.9500, 19.0000],
        'PGP Sinj' => [43.7047, 16.6397], 'PGP Trilj' => [43.6167, 16.7167],
        'PGP Imotski' => [43.4458, 17.2192], 'PGP Vrgorac' => [43.2028, 17.3958],
        'PGP Vinjani Gornji' => [43.4500, 17.3500], 'PGP Kamensko' => [43.6667, 16.9000],
        'PGP Metković' => [43.0553, 17.6483], 'PGP Ploče' => [43.0567, 17.4319],
        'PGP Osojnik' => [42.7167, 18.1167], 'PGP Zaton Doli' => [42.7500, 17.9667],
        'PGP Ivanica' => [42.6833, 18.3667], 'PGP Orebić' => [42.9758, 17.1747],
    ];

    private array $segments = [];
    private array $stationCtx = []; // station_id => context array (anchors, calendar, etc.)
    private \DateTimeImmutable $from;
    private \DateTimeImmutable $to;
    private ?User $superAdmin = null;
    private array $administrationAdmins = []; // administration_id => User|null

    public function handle(): int
    {
        $this->to = new \DateTimeImmutable('now');
        $this->from = $this->to->modify('-' . (int) $this->option('months') . ' months');

        $this->loadBorderSegments();
        $this->superAdmin = User::role('admin')->whereNull('station_id')->first();

        $stations = BorderPoliceStation::where('name', 'like', 'PGP%')
            ->with(['users.roles', 'drones'])
            ->get();

        if (!$this->option('no-wipe')) {
            $this->wipe();
        }

        foreach (PoliceAdministration::all() as $admin) {
            $stationIds = BorderPoliceStation::where('police_administration_id', $admin->id)->pluck('id');
            $this->administrationAdmins[$admin->id] = User::role('admin')->whereIn('station_id', $stationIds)->first();
        }

        $cameras = HuntingCamera::whereNotNull('latitude')->whereNotNull('longitude')->get()->groupBy('station_id');

        foreach ($stations as $station) {
            $this->buildStationContext($station, $cameras->get($station->id, collect()));
        }

        $targetFlights = (int) $this->option('flights');
        $targetDetections = (int) $this->option('detections');

        $droneTarget = (int) round($targetDetections * 0.40);
        $cameraTarget = (int) round($targetDetections * 0.35);
        $groundTarget = $targetDetections - $droneTarget - $cameraTarget;

        $activePct = (int) $this->option('active-pct');
        [$flightsGenerated, $droneCount, $flightsWithDetections] = $this->generateFlightsAndDrone($targetFlights, $droneTarget, $activePct);
        $this->info('Letovi generirani: ' . $flightsGenerated);
        $this->info("Dron-detekcije: {$droneCount} (cilj {$droneTarget})");

        $cameraCount = $this->generateCameraDetections($cameraTarget);
        $this->info("Kamera-detekcije: {$cameraCount} (cilj {$cameraTarget})");

        $groundCount = $this->generateGroundDetections($groundTarget);
        $this->info("Ophodnja/ručni unos: {$groundCount} (cilj {$groundTarget})");

        $this->info('');
        $this->info('--- Sažetak ---');
        $this->info('Ukupno letova: ' . Flight::count() . " (od čega s detekcijama: {$flightsWithDetections}, " .
            round(100 * $flightsWithDetections / max(1, $flightsGenerated)) . '%)');
        $this->info('Ukupno detekcija: ' . Detection::count());
        $this->info('Period: ' . $this->from->format('Y-m-d') . ' – ' . $this->to->format('Y-m-d'));

        return self::SUCCESS;
    }

    private function wipe(): void
    {
        $this->line('Brišem postojeće detekcije, GPX točke i letove...');
        Detection::query()->delete();
        GpxPoint::query()->delete();
        Flight::withTrashed()->forceDelete();
    }

    private function loadBorderSegments(): void
    {
        $data = json_decode(File::get(storage_path('app/border_line.json')), true);
        $this->segments = [$data['hr_bih_main'], $data['hr_bih_south'], $data['hr_srb']];
    }

    private function planarDistanceKm(float $lat1, float $lon1, float $lat2, float $lon2): float
    {
        $midLat = deg2rad(($lat1 + $lat2) / 2);
        $dx = ($lon2 - $lon1) * 111.0 * cos($midLat);
        $dy = ($lat2 - $lat1) * 111.0;
        return sqrt($dx ** 2 + $dy ** 2);
    }

    private function nearestBorderPoint(float $lat, float $lon): array
    {
        $best = null;
        foreach ($this->segments as $segment) {
            foreach ($segment as $i => $point) {
                $dist = $this->planarDistanceKm($lat, $lon, $point[0], $point[1]);
                if ($best === null || $dist < $best['dist']) {
                    $prev = $segment[max(0, $i - 1)];
                    $next = $segment[min(count($segment) - 1, $i + 1)];
                    $tLat = $next[0] - $prev[0];
                    $tLon = $next[1] - $prev[1];
                    $len = sqrt($tLat ** 2 + $tLon ** 2) ?: 1;
                    $best = ['dist' => $dist, 'lat' => $point[0], 'lon' => $point[1], 'tangent' => [$tLat / $len, $tLon / $len]];
                }
            }
        }
        return [$best['lat'], $best['lon'], $best['tangent']];
    }

    private function inwardNormal(float $borderLat, float $borderLon, array $tangent, float $stationLat, float $stationLon): array
    {
        $n1 = [-$tangent[1], $tangent[0]];
        $n2 = [$tangent[1], -$tangent[0]];
        $p1 = $this->planarDistanceKm($stationLat, $stationLon, $borderLat + $n1[0] * 0.01, $borderLon + $n1[1] * 0.01);
        $p2 = $this->planarDistanceKm($stationLat, $stationLon, $borderLat + $n2[0] * 0.01, $borderLon + $n2[1] * 0.01);
        return $p1 < $p2 ? $n1 : $n2;
    }

    /** Builds anchors, intensity calendar and episode windows for one station. */
    private function buildStationContext(BorderPoliceStation $station, $stationCameras): void
    {
        $coord = self::STATION_COORDS[$station->name] ?? [(float) $station->latitude, (float) $station->longitude];
        [$borderLat, $borderLon, $tangent] = $this->nearestBorderPoint($coord[0], $coord[1]);
        $normal = $this->inwardNormal($borderLat, $borderLon, $tangent, $coord[0], $coord[1]);

        $activityWeight = mt_rand(60, 220) / 100; // 0.6 .. 2.2, station "busyness"

        // Two persistent hotspots + one temporary (appears/disappears) hotspot,
        // each expressed as [sweepKm along tangent, offsetKm inland].
        $persistent = [
            [mt_rand(-200, 200) / 100, mt_rand(150, 350) / 100],
            [mt_rand(-200, 200) / 100, mt_rand(150, 350) / 100],
        ];
        $tempStartDay = mt_rand(0, 300);
        $tempLenDays = mt_rand(21, 56);
        $temporary = [
            'anchor' => [mt_rand(-200, 200) / 100, mt_rand(150, 350) / 100],
            'start' => $tempStartDay,
            'end' => $tempStartDay + $tempLenDays,
        ];

        // 2-4 episode windows across the year: short bursts of elevated,
        // night- or weekend-skewed activity, on top of a quiet baseline.
        $episodes = [];
        $numEpisodes = mt_rand(2, 4);
        for ($i = 0; $i < $numEpisodes; $i++) {
            $start = mt_rand(0, 350);
            $episodes[] = [
                'start' => $start,
                'end' => $start + mt_rand(3, 7),
                'type' => mt_rand(0, 1) === 0 ? 'night' : 'weekend',
                'multiplier' => mt_rand(300, 600) / 100,
            ];
        }

        $totalDays = max(1, $this->to->diff($this->from)->days);
        $dayWeights = [];
        for ($d = 0; $d <= $totalDays; $d++) {
            $date = $this->from->modify("+{$d} days");
            $isWeekend = in_array((int) $date->format('N'), [6, 7], true);
            $weight = $isWeekend ? 1.3 : 1.0;
            $episodeType = null;
            foreach ($episodes as $ep) {
                if ($d >= $ep['start'] && $d <= $ep['end']) {
                    $weight *= $ep['multiplier'];
                    $episodeType = $ep['type'];
                    break;
                }
            }
            $dayWeights[$d] = ['weight' => $weight, 'episode' => $episodeType];
        }

        $this->stationCtx[$station->id] = [
            'station' => $station,
            'borderLat' => $borderLat, 'borderLon' => $borderLon,
            'tangent' => $tangent, 'normal' => $normal,
            'activityWeight' => $activityWeight,
            'persistent' => $persistent,
            'temporary' => $temporary,
            'dayWeights' => $dayWeights,
            'totalDays' => $totalDays,
            'pilot' => $station->users->first(fn ($u) => $u->hasRole('pilot')),
            'viewer' => $station->users->first(fn ($u) => $u->hasRole('viewer')),
            'localAdmin' => $station->users->first(fn ($u) => $u->hasRole('admin')),
            'drones' => $station->drones,
            'cameras' => $stationCameras,
        ];
    }

    /** Resolves a real-world lat/lon for a given anchor mode on a given day offset. */
    private function anchorPoint(array $ctx, int $dayOffset, ?string &$modeOut = null): array
    {
        $roll = mt_rand(1, 100);
        $temp = $ctx['temporary'];
        $tempActive = $dayOffset >= $temp['start'] && $dayOffset <= $temp['end'];

        if ($tempActive && $roll <= 15) {
            $anchor = $temp['anchor'];
            $modeOut = 'temporary';
        } elseif ($roll <= 15 + 30) {
            $anchor = $ctx['persistent'][0];
            $modeOut = 'hotspot1';
        } elseif ($roll <= 15 + 30 + 30) {
            $anchor = $ctx['persistent'][1];
            $modeOut = 'hotspot2';
        } else {
            $anchor = [mt_rand(-250, 250) / 100, mt_rand(100, 400) / 100];
            $modeOut = 'scattered';
        }

        [$sweepKm, $offsetKm] = $anchor;
        $jitterKm = 0.12;
        $bearing = mt_rand() / mt_getrandmax() * 2 * M_PI;
        $jLat = sin($bearing) * $jitterKm;
        $jLon = cos($bearing) * $jitterKm;

        $centerLat = $ctx['borderLat'] + $ctx['normal'][0] * $offsetKm / 111.0 + $ctx['tangent'][0] * $sweepKm / 111.0;
        $centerLon = $ctx['borderLon'] + $ctx['normal'][1] * $offsetKm / (111.0 * cos(deg2rad($ctx['borderLat'])))
            + $ctx['tangent'][1] * $sweepKm / (111.0 * cos(deg2rad($ctx['borderLat'])));

        return [
            round($centerLat + $jLat / 111.0, 7),
            round($centerLon + $jLon / (111.0 * cos(deg2rad($centerLat))), 7),
        ];
    }

    /** Weighted-random day offset (0..totalDays) favouring higher-weight days. */
    private function sampleDayOffset(array $ctx): int
    {
        $weights = $ctx['dayWeights'];
        $totalWeight = array_sum(array_column($weights, 'weight'));
        $r = mt_rand() / mt_getrandmax() * $totalWeight;
        $acc = 0.0;
        foreach ($weights as $day => $w) {
            $acc += $w['weight'];
            if ($r <= $acc) {
                return $day;
            }
        }
        return $ctx['totalDays'];
    }

    /** Hour-of-day sample, night-biased; more so during a 'night' episode day. */
    private function sampleHour(?string $episodeType, float $nightBias = 0.55): int
    {
        $bias = $episodeType === 'night' ? 0.82 : ($episodeType === 'weekend' ? $nightBias * 0.85 : $nightBias);
        $night = range(20, 23) + range(0, 5);
        $day = range(6, 19);
        if ((mt_rand() / mt_getrandmax()) < $bias) {
            return $night[array_rand($night)];
        }
        return $day[array_rand($day)];
    }

    private function weightedPick(array $weights): string
    {
        $total = array_sum($weights);
        $r = mt_rand() / mt_getrandmax() * $total;
        $acc = 0.0;
        foreach ($weights as $key => $w) {
            $acc += $w;
            if ($r <= $acc) {
                return $key;
            }
        }
        return array_key_first($weights);
    }

    private function entityCountFor(string $type): int
    {
        return match ($type) {
            'group' => mt_rand(2, 6),
            'vehicle' => mt_rand(1, 100) <= 15 ? 2 : 1,
            default => 1,
        };
    }

    /** Resolves created_by: station viewer → local admin → administration admin → pilot → super admin. */
    private function resolveCreatedBy(array $ctx): ?int
    {
        $candidate = $ctx['viewer'] ?? $ctx['localAdmin']
            ?? $this->administrationAdmins[$ctx['station']->police_administration_id] ?? null
            ?? $ctx['pilot'] ?? $this->superAdmin;
        return $candidate?->id;
    }

    /**
     * Generates every flight AND its drone detections in one streaming pass:
     * a flight's GPX points are built, inserted, used to sample that same
     * flight's drone detections, then discarded before moving to the next
     * flight. At low hundreds of flights it was fine to hold the whole
     * dataset (points included) in memory for a second pass — at tens of
     * thousands of flights that blew PHP's memory limit with no useful
     * error (a hard OOM kill, not a catchable exception). Exact per-flight
     * drone-detection counts still hit the target precisely: a cheap first
     * pass decides station/time/anchor/wants-detections for every flight
     * (lightweight scalars only, no points), scales those into an exact
     * largest-remainder count per flight, and the second (streaming) pass
     * just realises that plan.
     *
     * @return array{0: int, 1: int, 2: int} [flightsGenerated, droneDetectionsGenerated, flightsWithDetections]
     */
    private function generateFlightsAndDrone(int $targetFlights, int $targetDroneDetections, int $activePct = 50): array
    {
        $piloted = array_filter($this->stationCtx, fn ($ctx) => $ctx['pilot'] !== null && $ctx['drones']->isNotEmpty());
        if (empty($piloted)) {
            $this->warn('Nema postaja s pilotom i dronom — nema letova.');
            return [0, 0, 0];
        }

        $floor = max(1, min(3, intdiv($targetFlights, max(1, count($piloted)))));
        $weights = array_map(fn ($ctx) => $ctx['activityWeight'] * (mt_rand(80, 130) / 100), $piloted);
        $totalWeight = array_sum($weights);
        $counts = [];
        $assigned = 0;
        foreach ($weights as $stationId => $w) {
            $counts[$stationId] = max($floor, (int) round($targetFlights * $w / $totalWeight));
            $assigned += $counts[$stationId];
        }
        // Largest-remainder-ish trim/pad to land close to target, bounded so a
        // target too small to honour $floor across every station can't spin forever.
        $stationIds = array_keys($counts);
        $guard = 0;
        $maxGuard = count($stationIds) * 1000 + 1000;
        while ($assigned > $targetFlights && $guard++ < $maxGuard) {
            $sid = $stationIds[array_rand($stationIds)];
            if ($counts[$sid] > $floor) { $counts[$sid]--; $assigned--; }
        }
        $guard = 0;
        while ($assigned < $targetFlights && $guard++ < $maxGuard) {
            $sid = $stationIds[array_rand($stationIds)];
            $counts[$sid]++; $assigned++;
        }

        // Pass 1 — lightweight plan per flight (scalars only, no GPX points).
        $plans = [];
        foreach ($counts as $stationId => $count) {
            $ctx = $this->stationCtx[$stationId];
            for ($i = 0; $i < $count; $i++) {
                $dayOffset = $this->sampleDayOffset($ctx);
                $episodeType = $ctx['dayWeights'][$dayOffset]['episode'];
                $hour = $this->sampleHour($episodeType, 0.35); // flights skew less to pure night than passive sensors
                $mode = null;
                [$anchorLat, $anchorLon] = $this->anchorPoint($ctx, $dayOffset, $mode);

                $plans[] = [
                    'stationId' => $stationId,
                    'dayOffset' => $dayOffset,
                    'hour' => $hour, 'minute' => mt_rand(0, 59), 'second' => mt_rand(0, 59),
                    'durationMinutes' => mt_rand(15, 45),
                    'anchorLat' => $anchorLat, 'anchorLon' => $anchorLon,
                    'episode' => $episodeType,
                    'wantsDetections' => mt_rand(1, 100) <= $activePct,
                ];
            }
        }

        // Exact per-flight drone-detection count via largest-remainder scaling
        // (cheap — just scalars — computed before any points exist).
        $eligiblePlanIdx = [];
        $raw = [];
        foreach ($plans as $idx => $p) {
            if ($p['wantsDetections']) {
                $base = mt_rand(1, 4);
                if ($p['episode'] !== null) { $base += mt_rand(1, 4); }
                $eligiblePlanIdx[] = $idx;
                $raw[] = $base;
            }
        }
        $scaled = $this->scaleToTarget($raw, $targetDroneDetections);
        $droneCountByPlan = array_fill(0, count($plans), 0);
        foreach ($eligiblePlanIdx as $j => $planIdx) {
            $droneCountByPlan[$planIdx] = $scaled[$j];
        }

        // Pass 2 — stream: build + insert each flight, its GPX points and its
        // drone detections, then discard the points before the next flight.
        $purposes = ['Redovna granična patrola', 'Nadzor graničnog pojasa', 'Preventivna kontrola prijelaza', 'Praćenje kretanja uz granicu'];
        $types = ['vehicle' => 35, 'group' => 30, 'person' => 25, 'other' => 10];

        $flightsGenerated = 0;
        $droneDetectionsGenerated = 0;
        $flightsWithDetections = 0;
        $gpxBuffer = [];
        $detBuffer = [];
        $sinceFlush = 0;
        $flushEveryFlights = 20;

        foreach ($plans as $idx => $p) {
            $ctx = $this->stationCtx[$p['stationId']];
            $flightDate = $this->from->modify("+{$p['dayOffset']} days")->setTime($p['hour'], $p['minute'], $p['second']);

            [$flight, $points] = $this->buildFlight(
                $ctx, $flightDate, $p['durationMinutes'], $p['anchorLat'], $p['anchorLon'], $purposes
            );
            $flight->save();
            $flightsGenerated++;

            foreach ($points as &$pt) { $pt['flight_id'] = $flight->id; }
            unset($pt);
            foreach ($points as $pt) { $gpxBuffer[] = $pt; }

            $n = $droneCountByPlan[$idx];
            if ($n > 0) {
                $flightsWithDetections++;
                for ($k = 0; $k < $n; $k++) {
                    $pp = $points[array_rand($points)];
                    $bearing = mt_rand() / mt_getrandmax() * 2 * M_PI;
                    $jitterKm = mt_rand(5, 20) / 100; // 50-200m off the flight path
                    $lat = (float) $pp['latitude'] + (sin($bearing) * $jitterKm) / 111.0;
                    $lon = (float) $pp['longitude'] + (cos($bearing) * $jitterKm) / (111.0 * cos(deg2rad((float) $pp['latitude'])));
                    $type = $this->weightedPick($types);

                    $detBuffer[] = [
                        'flight_id' => $flight->id,
                        'station_id' => $ctx['station']->id,
                        'created_by' => $flight->user_id,
                        'source' => 'drone',
                        'latitude' => round($lat, 7),
                        'longitude' => round($lon, 7),
                        'detection_type' => $type,
                        'entity_count' => $this->entityCountFor($type),
                        'note' => null,
                        'detected_at' => $pp['timestamp'],
                        'created_at' => now(), 'updated_at' => now(),
                    ];
                    $droneDetectionsGenerated++;
                }
            }
            unset($points, $pp);

            if (++$sinceFlush >= $flushEveryFlights) {
                foreach (array_chunk($gpxBuffer, 1000) as $chunk) { GpxPoint::insert($chunk); }
                foreach (array_chunk($detBuffer, 500) as $chunk) { Detection::insert($chunk); }
                $gpxBuffer = [];
                $detBuffer = [];
                $sinceFlush = 0;
            }
        }
        foreach (array_chunk($gpxBuffer, 1000) as $chunk) { GpxPoint::insert($chunk); }
        foreach (array_chunk($detBuffer, 500) as $chunk) { Detection::insert($chunk); }

        return [$flightsGenerated, $droneDetectionsGenerated, $flightsWithDetections];
    }

    private function buildFlight(array $ctx, \DateTimeImmutable $flightDate, int $durationMinutes, float $centerLat, float $centerLon, array $purposes): array
    {
        $tangent = $ctx['tangent'];
        $normal = $ctx['normal'];
        $jitterKm = 0.15;
        $jitterCycles = mt_rand(3, 6);
        $jitterPhase = mt_rand(0, 628) / 100;
        $numPoints = mt_rand(40, 90);

        $targetAvgSpeed = mt_rand(18, 35);
        $targetDistanceKm = $targetAvgSpeed * $durationMinutes / 60;
        $cycles = mt_rand(2, 4);
        $jitterArcKm = 2 * $jitterKm * $jitterCycles;
        $lengthKm = max(0.5, min(4.0, ($targetDistanceKm - $jitterArcKm) / (2 * $cycles)));

        $baseAlt = mt_rand(80, 150);
        $points = [];
        $totalDistanceKm = 0.0;
        $prevLat = $prevLon = null;

        for ($i = 0; $i < $numPoints; $i++) {
            $t = $i / max(1, $numPoints - 1);
            $sweep = sin($t * M_PI * $cycles) * ($lengthKm / 2);
            $jitter = sin($t * 2 * M_PI * $jitterCycles + $jitterPhase) * $jitterKm;

            $km_lat = $sweep * $tangent[0] + $jitter * $normal[0];
            $km_lon = $sweep * $tangent[1] + $jitter * $normal[1];

            $lat = $centerLat + $km_lat / 111.0;
            $lon = $centerLon + $km_lon / (111.0 * cos(deg2rad($centerLat)));

            if ($prevLat !== null) {
                $totalDistanceKm += $this->planarDistanceKm($prevLat, $prevLon, $lat, $lon);
            }
            $prevLat = $lat; $prevLon = $lon;

            $pointTime = $flightDate->modify('+' . (int) ($t * $durationMinutes * 60) . ' seconds');

            $points[] = [
                'latitude' => round($lat, 7),
                'longitude' => round($lon, 7),
                'altitude' => round($baseAlt + mt_rand(-15, 15), 2),
                'speed' => round(mt_rand(15, 40) + mt_rand(-5, 5), 2),
                'timestamp' => $pointTime->format('Y-m-d H:i:s'),
                'point_order' => $i,
                'created_at' => now(), 'updated_at' => now(),
            ];
        }

        $avgSpeed = $totalDistanceKm > 0 ? round($totalDistanceKm / ($durationMinutes / 60), 2) : 0;
        $maxAltitude = max(array_column($points, 'altitude'));

        $flight = new Flight([
            'drone_id' => $ctx['drones']->random()->id,
            'user_id' => $ctx['pilot']->id,
            'station_id' => $ctx['station']->id,
            'flight_date' => $flightDate->format('Y-m-d H:i:s'),
            'duration_minutes' => $durationMinutes,
            'distance_km' => round($totalDistanceKm, 2),
            'max_altitude_m' => $maxAltitude,
            'avg_speed_kmh' => $avgSpeed,
            'location' => str_replace('PGP ', '', $ctx['station']->name),
            'purpose' => $purposes[array_rand($purposes)],
            'status' => 'completed',
        ]);

        return [$flight, $points, $totalDistanceKm, $maxAltitude, $avgSpeed];
    }

    private function generateCameraDetections(int $target): int
    {
        $cameraList = [];
        foreach ($this->stationCtx as $stationId => $ctx) {
            foreach ($ctx['cameras'] as $camera) {
                $cameraList[] = ['camera' => $camera, 'stationId' => $stationId, 'weight' => $ctx['activityWeight'] * (mt_rand(60, 160) / 100)];
            }
        }
        if (empty($cameraList)) {
            return 0;
        }

        $types = ['person' => 45, 'group' => 25, 'vehicle' => 15, 'other' => 15];
        $totalWeight = array_sum(array_column($cameraList, 'weight'));
        $lastEventAt = []; // camera_id => DateTime, to avoid near-duplicate frame-spam

        $rows = [];
        $attempts = 0;
        while (count($rows) < $target && $attempts < $target * 4) {
            $attempts++;
            $r = mt_rand() / mt_getrandmax() * $totalWeight;
            $acc = 0.0;
            $pick = $cameraList[count($cameraList) - 1];
            foreach ($cameraList as $c) {
                $acc += $c['weight'];
                if ($r <= $acc) { $pick = $c; break; }
            }

            $camera = $pick['camera'];
            $ctx = $this->stationCtx[$pick['stationId']];
            $dayOffset = $this->sampleDayOffset($ctx);
            $episodeType = $ctx['dayWeights'][$dayOffset]['episode'];
            $hour = $this->sampleHour($episodeType, 0.65);
            $time = $this->from->modify("+{$dayOffset} days")->setTime($hour, mt_rand(0, 59), mt_rand(0, 59));

            // Guard against spamming the same camera within a few minutes
            // (e.g. one real sighting split across many video frames).
            if (isset($lastEventAt[$camera->id]) && abs($time->getTimestamp() - $lastEventAt[$camera->id]) < 600) {
                continue;
            }
            $lastEventAt[$camera->id] = $time->getTimestamp();

            $bearing = mt_rand() / mt_getrandmax() * 2 * M_PI;
            $jitterKm = mt_rand(1, 4) / 100; // 10-40m GPS imprecision around the fixed camera
            $lat = (float) $camera->latitude + (sin($bearing) * $jitterKm) / 111.0;
            $lon = (float) $camera->longitude + (cos($bearing) * $jitterKm) / (111.0 * cos(deg2rad((float) $camera->latitude)));
            $type = $this->weightedPick($types);

            $rows[] = [
                'flight_id' => null,
                'station_id' => $ctx['station']->id,
                'created_by' => $this->resolveCreatedBy($ctx),
                'source' => 'trail_camera',
                'latitude' => round($lat, 7),
                'longitude' => round($lon, 7),
                'detection_type' => $type,
                'entity_count' => $this->entityCountFor($type),
                'note' => null,
                'detected_at' => $time->format('Y-m-d H:i:s'),
                'created_at' => now(), 'updated_at' => now(),
            ];
        }

        foreach (array_chunk($rows, 500) as $chunk) {
            Detection::insert($chunk);
        }

        return count($rows);
    }

    private function generateGroundDetections(int $target): int
    {
        $weights = [];
        foreach ($this->stationCtx as $stationId => $ctx) {
            $weights[$stationId] = $ctx['activityWeight'] * (mt_rand(70, 140) / 100);
        }
        $types = ['person' => 35, 'vehicle' => 30, 'group' => 25, 'other' => 10];

        $rows = [];
        for ($i = 0; $i < $target; $i++) {
            $stationId = $this->weightedPickStation($weights);
            $ctx = $this->stationCtx[$stationId];
            $dayOffset = $this->sampleDayOffset($ctx);
            $episodeType = $ctx['dayWeights'][$dayOffset]['episode'];
            $hour = $this->sampleHour($episodeType, 0.50);
            $time = $this->from->modify("+{$dayOffset} days")->setTime($hour, mt_rand(0, 59), mt_rand(0, 59));

            $mode = null;
            [$lat, $lon] = $this->anchorPoint($ctx, $dayOffset, $mode);
            $type = $this->weightedPick($types);

            $rows[] = [
                'flight_id' => null,
                'station_id' => $ctx['station']->id,
                'created_by' => $this->resolveCreatedBy($ctx),
                'source' => 'ground_observation',
                'latitude' => $lat,
                'longitude' => $lon,
                'detection_type' => $type,
                'entity_count' => $this->entityCountFor($type),
                'note' => null,
                'detected_at' => $time->format('Y-m-d H:i:s'),
                'created_at' => now(), 'updated_at' => now(),
            ];
        }

        foreach (array_chunk($rows, 500) as $chunk) {
            Detection::insert($chunk);
        }

        return count($rows);
    }

    private function weightedPickStation(array $weights): int
    {
        $total = array_sum($weights);
        $r = mt_rand() / mt_getrandmax() * $total;
        $acc = 0.0;
        foreach ($weights as $stationId => $w) {
            $acc += $w;
            if ($r <= $acc) { return $stationId; }
        }
        return array_key_first($weights);
    }

    /** Scales a list of raw positive-int weights so they sum exactly to $target (largest-remainder method). */
    private function scaleToTarget(array $raw, int $target): array
    {
        $sum = array_sum($raw);
        if ($sum === 0) {
            return array_fill(0, count($raw), 0);
        }
        $scaled = [];
        $remainders = [];
        $runningTotal = 0;
        foreach ($raw as $i => $v) {
            $exact = $v * $target / $sum;
            $floor = (int) floor($exact);
            $scaled[$i] = $floor;
            $remainders[$i] = $exact - $floor;
            $runningTotal += $floor;
        }
        $remaining = $target - $runningTotal;
        arsort($remainders);
        foreach (array_keys($remainders) as $i) {
            if ($remaining <= 0) { break; }
            $scaled[$i]++;
            $remaining--;
        }
        return $scaled;
    }
}
