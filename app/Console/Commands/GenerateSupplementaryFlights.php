<?php

namespace App\Console\Commands;

use App\Models\BorderPoliceStation;
use App\Models\Detection;
use App\Models\Drone;
use App\Models\Flight;
use App\Models\GpxPoint;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Random\Engine\Mt19937;
use Random\Randomizer;

class GenerateSupplementaryFlights extends Command
{
    protected $signature = 'flights:generate-supplementary
        {--per-station=14 : New flights to generate per active station}
        {--seed=424242 : Fixed RNG seed for reproducibility}
        {--dry-run : Report what would be generated without writing anything}';

    protected $description = 'Fill the Jul-Sep 2026 flight gap for already-staffed stations, with GPX routes and a realistic minority of linked detections';

    /** Marks rows created by this command, so re-running it is a no-op per station (idempotent). */
    private const MARKER = 'Dopuna sintetičkih letova — faza 2';

    private const WINDOW_START = '2026-07-16 00:00:00';
    private const WINDOW_END   = '2026-09-16 23:59:59';

    /** type => [min entities, max entities, relative weight] — same weights as GenerateFlightDetections. */
    private const DETECTION_TYPES = [
        'person'  => ['min' => 1, 'max' => 1, 'weight' => 40],
        'group'   => ['min' => 2, 'max' => 10, 'weight' => 28],
        'vehicle' => ['min' => 1, 'max' => 3, 'weight' => 25],
        'other'   => ['min' => 1, 'max' => 3, 'weight' => 7],
    ];

    private Randomizer $rng;

    public function handle(): int
    {
        $this->rng = new Randomizer(new Mt19937((int) $this->option('seed')));
        $perStation = (int) $this->option('per-station');
        $dryRun = (bool) $this->option('dry-run');

        $stations = BorderPoliceStation::where('name', 'like', 'PGP %')
            ->whereHas('users', fn ($q) => $q->role('pilot'))
            ->with(['users' => fn ($q) => $q->role('pilot'), 'drones'])
            ->get();

        if ($stations->isEmpty()) {
            $this->error('Nema postaja s dodijeljenim pilotom — ništa za generirati.');
            return self::FAILURE;
        }

        $this->info("Ciljne postaje: {$stations->count()} · po {$perStation} letova · razdoblje " . self::WINDOW_START . ' – ' . self::WINDOW_END);

        $createdFlights = 0;
        $createdDetections = 0;
        $skippedStations = 0;

        foreach ($stations as $station) {
            $already = Flight::where('station_id', $station->id)
                ->where('purpose', self::MARKER)
                ->count();

            if ($already >= $perStation) {
                $skippedStations++;
                $this->line("  {$station->name}: već ima {$already} — preskačem (idempotentno).");
                continue;
            }

            $toCreate = $perStation - $already;
            $pilot = $station->users->first();
            $drones = $station->drones;

            if (!$pilot || $drones->isEmpty()) {
                $this->warn("  {$station->name}: nema pilota/dronova — preskačem.");
                continue;
            }

            for ($i = 0; $i < $toCreate; $i++) {
                $drone = $drones[$this->rng->getInt(0, $drones->count() - 1)];
                [$flight, $points, $detectionsForFlight] = $this->buildFlight($station, $pilot, $drone);

                if ($dryRun) {
                    $createdFlights++;
                    $createdDetections += count($detectionsForFlight);
                    continue;
                }

                $flightModel = Flight::create($flight);

                $rows = [];
                foreach ($points as $idx => $p) {
                    $rows[] = [
                        'flight_id'   => $flightModel->id,
                        'latitude'    => $p['lat'],
                        'longitude'   => $p['lon'],
                        'altitude'    => $p['alt'],
                        'speed'       => $p['speed'],
                        'timestamp'   => $p['time']->format('Y-m-d H:i:s'),
                        'point_order' => $idx + 1,
                        'created_at'  => now(),
                        'updated_at'  => now(),
                    ];
                }
                GpxPoint::insert($rows);

                foreach ($detectionsForFlight as $det) {
                    Detection::create([
                        'flight_id'      => $flightModel->id,
                        'station_id'     => $station->id,
                        'created_by'     => $pilot->id,
                        'source'         => 'drone',
                        'latitude'       => $det['lat'],
                        'longitude'      => $det['lon'],
                        'detection_type' => $det['type'],
                        'entity_count'   => $det['count'],
                        'detected_at'    => $det['time'],
                    ]);
                    $createdDetections++;
                }

                $createdFlights++;
            }

            $this->line("  {$station->name}: +{$toCreate} leta.");
        }

        $this->info(($dryRun ? '[DRY-RUN] ' : '') . "Gotovo. Novih letova: {$createdFlights}, novih detekcija: {$createdDetections}, preskočenih postaja (već generirano): {$skippedStations}.");

        if (!$dryRun && $createdFlights > 0) {
            $this->verifyConsistency();
        }

        return self::SUCCESS;
    }

    /** @return array{0: array, 1: array, 2: array} [flight attributes, gpx points, detections] */
    private function buildFlight(BorderPoliceStation $station, User $pilot, Drone $drone): array
    {
        $windowStart = Carbon::parse(self::WINDOW_START);
        $windowEnd   = Carbon::parse(self::WINDOW_END);
        $totalSeconds = $windowEnd->getTimestamp() - $windowStart->getTimestamp();

        $isNight = $this->rng->getInt(1, 100) <= 36; // matches the existing ~64/36 day/night split
        $flightDate = $this->randomDateTimeInWindow($windowStart, $totalSeconds, $isNight);

        // 60% of flights orbit close to the station's own registered point (its
        // known crossing/hotspot); 40% range further into the wider territory —
        // "kroz žarišta i izvan žarišta" without inventing fake named hotspots.
        $nearHotspot = $this->rng->getInt(1, 100) <= 60;
        $center = $nearHotspot
            ? $this->stationPoint($station, 2.0)
            : $this->stationPoint($station, self::effectiveRadius($station) * 0.85);

        $durationMinutes = $this->realisticDuration();
        $points = $this->generateRoute($station, $center, $flightDate, $durationMinutes);

        $last = $points[count($points) - 1];
        $stats = $this->routeStats($points);

        $flight = [
            'drone_id'         => $drone->id,
            'user_id'          => $pilot->id,
            'station_id'       => $station->id,
            'flight_date'      => $points[0]['time'],
            'duration_minutes' => round($points[0]['time']->diffInSeconds($last['time']) / 60, 1),
            'distance_km'      => $stats['distance_km'],
            'max_altitude_m'   => $stats['max_altitude_m'],
            'avg_speed_kmh'    => $stats['avg_speed_kmh'],
            'location'         => $station->name,
            'purpose'          => self::MARKER,
            'status'           => 'completed',
        ];

        $detections = $this->maybeGenerateDetections($points, $flightDate);

        return [$flight, $points, $detections];
    }

    private function randomDateTimeInWindow(Carbon $windowStart, int $totalSeconds, bool $night): Carbon
    {
        for ($attempt = 0; $attempt < 20; $attempt++) {
            $dt = $windowStart->copy()->addSeconds($this->rng->getInt(0, $totalSeconds));
            $hour = (int) $dt->format('H');
            $isNightHour = $hour >= 20 || $hour < 6;
            if ($isNightHour === $night) {
                return $dt;
            }
        }
        return $windowStart->copy()->addSeconds($this->rng->getInt(0, $totalSeconds));
    }

    private function realisticDuration(): int
    {
        // Sample around the existing dataset's mean (~25 min), clipped to its
        // observed range (6-55 min) — rule 11.
        $val = (int) round($this->normalSample(24.9, 9));
        return max(6, min(55, $val));
    }

    private function normalSample(float $mean, float $stdDev): float
    {
        $u1 = max(1e-9, $this->rng->getInt(1, 1000000) / 1000000);
        $u2 = $this->rng->getInt(1, 1000000) / 1000000;
        $z  = sqrt(-2 * log($u1)) * cos(2 * M_PI * $u2);
        return $mean + $z * $stdDev;
    }

    private static function effectiveRadius(BorderPoliceStation $station): float
    {
        return $station->hasBoundary() ? 6.0 : BorderPoliceStation::TERRITORY_RADIUS_KM;
    }

    /** A point guaranteed to satisfy $station->containsPoint() (rule 4). */
    private function stationPoint(BorderPoliceStation $station, float $radiusKm): array
    {
        for ($attempt = 0; $attempt < 30; $attempt++) {
            [$lat, $lon] = $station->randomPointWithinTerritory(max(0.3, $radiusKm));
            if ($station->containsPoint($lat, $lon)) {
                return [$lat, $lon];
            }
        }
        // Fallback: the station's own coordinate always satisfies containsPoint().
        return [(float) $station->latitude, (float) $station->longitude];
    }

    /**
     * Back-and-forth patrol path around $center, entirely re-validated against
     * the station's territory point by point (rule 4) — climb/cruise/descent
     * phases, same shape as the existing border/river seeders.
     */
    private function generateRoute(BorderPoliceStation $station, array $center, Carbon $start, int $durationMinutes): array
    {
        $pointCount = max(8, (int) round($durationMinutes * 60 / 10)); // ~1 point per 10s
        $interval   = 10;
        $legKm      = 0.8 + $this->rng->getInt(0, 100) / 100 * 1.6;
        $bearing    = $this->rng->getInt(0, 359) * M_PI / 180;

        $endLat = $center[0] + ($legKm / 111.0) * cos($bearing);
        $endLon = $center[1] + ($legKm / (111.0 * max(cos(deg2rad($center[0])), 0.2))) * sin($bearing);

        // Clamp the far end back into the territory if the leg overshot it.
        if (!$station->containsPoint($endLat, $endLon)) {
            $endLat = $center[0] + ($legKm * 0.3 / 111.0) * cos($bearing);
            $endLon = $center[1] + ($legKm * 0.3 / (111.0 * max(cos(deg2rad($center[0])), 0.2))) * sin($bearing);
        }

        $cruiseAlt = 80 + $this->rng->getInt(0, 80);
        $waypoints = [$center, [$endLat, $endLon], $center];

        $climb = 4;
        $descent = 4;
        $cruise = max(1, $pointCount - $climb - $descent);
        $perLeg = max(1, intdiv($cruise, count($waypoints) - 1));

        $path = [];
        $time = $start->copy();
        $prev = null;

        for ($i = 0; $i < $climb; $i++) {
            $frac = $i / $climb;
            $path[] = ['lat' => $waypoints[0][0], 'lon' => $waypoints[0][1], 'alt' => round($cruiseAlt * $frac, 1), 'time' => $time->copy(), 'speed' => null];
            $time->addSeconds($interval);
        }

        for ($w = 0; $w < count($waypoints) - 1; $w++) {
            $a = $waypoints[$w];
            $b = $waypoints[$w + 1];
            for ($s = 0; $s < $perLeg; $s++) {
                $frac = $perLeg > 1 ? $s / ($perLeg - 1) : 0;
                $lat = $a[0] + ($b[0] - $a[0]) * $frac;
                $lon = $a[1] + ($b[1] - $a[1]) * $frac;
                // Small jitter, re-clamped to stay inside the territory.
                $jLat = $lat + ($this->rng->getInt(-100, 100) / 100) * 0.0003;
                $jLon = $lon + ($this->rng->getInt(-100, 100) / 100) * 0.0003;
                if (!$station->containsPoint($jLat, $jLon)) {
                    $jLat = $lat;
                    $jLon = $lon;
                }
                $alt = round($cruiseAlt + ($this->rng->getInt(-60, 60) / 10), 1);
                $point = ['lat' => $jLat, 'lon' => $jLon, 'alt' => $alt, 'time' => $time->copy(), 'speed' => null];
                if ($prev) {
                    $dist = $this->haversineKm($prev['lat'], $prev['lon'], $jLat, $jLon);
                    $point['speed'] = round($dist / ($interval / 3600), 1);
                }
                $path[] = $point;
                $prev = $point;
                $time->addSeconds($interval);
            }
        }

        for ($i = $descent; $i >= 0; $i--) {
            $frac = $i / $descent;
            $last = $waypoints[count($waypoints) - 1];
            $path[] = ['lat' => $last[0], 'lon' => $last[1], 'alt' => round($cruiseAlt * $frac, 1), 'time' => $time->copy(), 'speed' => null];
            $time->addSeconds($interval);
        }

        if (count($path) > 1 && $path[0]['speed'] === null) {
            $path[0]['speed'] = $path[1]['speed'] ?? 0;
        }

        return $path;
    }

    private function routeStats(array $points): array
    {
        $totalDist = 0;
        for ($i = 1; $i < count($points); $i++) {
            $totalDist += $this->haversineKm($points[$i - 1]['lat'], $points[$i - 1]['lon'], $points[$i]['lat'], $points[$i]['lon']);
        }
        $alts = array_filter(array_column($points, 'alt'));
        $speeds = array_filter(array_column($points, 'speed'), fn ($s) => $s > 0);

        return [
            'distance_km'    => round($totalDist, 3),
            'max_altitude_m' => !empty($alts) ? round(max($alts), 1) : null,
            'avg_speed_kmh'  => !empty($speeds) ? round(array_sum($speeds) / count($speeds), 1) : null,
        ];
    }

    /**
     * Roughly 66% none / 25% one / 9% multiple, matching the existing
     * dataset's ratio exactly (rules 7-9). Every detection is placed AT an
     * actual GPX point already on this route (rule 12: temporally within the
     * flight, spatially on the route, same station) — never at an
     * independent random background location (rule 14).
     */
    private function maybeGenerateDetections(array $points, Carbon $flightStart): array
    {
        $roll = $this->rng->getInt(1, 1000) / 10; // 0.0-100.0
        if ($roll > 34.0) {
            return []; // ~66% of flights: no detection (rule 13)
        }

        $countRoll = $this->rng->getInt(1, 1000) / 10;
        $n = $countRoll <= 73.8 ? 1 : ($countRoll <= 93.7 ? 2 : 3);

        $detections = [];
        $usedIdx = [];
        for ($i = 0; $i < $n; $i++) {
            $idx = $this->rng->getInt(1, count($points) - 2); // avoid the climb/descent endpoints
            if (in_array($idx, $usedIdx, true)) {
                continue;
            }
            $usedIdx[] = $idx;
            $point = $points[$idx];

            $type = $this->weightedDetectionType();
            $range = self::DETECTION_TYPES[$type];

            $detections[] = [
                'lat'   => $point['lat'],
                'lon'   => $point['lon'],
                'type'  => $type,
                'count' => min(10, $this->rng->getInt($range['min'], $range['max'])),
                'time'  => $point['time'],
            ];
        }

        return $detections;
    }

    private function weightedDetectionType(): string
    {
        $total = array_sum(array_column(self::DETECTION_TYPES, 'weight'));
        $roll = $this->rng->getInt(1, $total);
        $cumulative = 0;
        foreach (self::DETECTION_TYPES as $type => $cfg) {
            $cumulative += $cfg['weight'];
            if ($roll <= $cumulative) {
                return $type;
            }
        }
        return 'person';
    }

    private function haversineKm(float $lat1, float $lon1, float $lat2, float $lon2): float
    {
        $r = 6371;
        $dLat = deg2rad($lat2 - $lat1);
        $dLon = deg2rad($lon2 - $lon1);
        $a = sin($dLat / 2) ** 2 + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLon / 2) ** 2;
        return $r * 2 * atan2(sqrt($a), sqrt(1 - $a));
    }

    /**
     * Rule 20: verify spatial/temporal consistency of everything just created.
     */
    private function verifyConsistency(): void
    {
        $this->info('Provjera konzistentnosti...');
        $flights = Flight::where('purpose', self::MARKER)->with(['gpxPoints', 'detections', 'station'])->get();

        $badGeo = 0;
        $badTime = 0;
        $badStation = 0;

        foreach ($flights as $flight) {
            $station = $flight->station;
            foreach ($flight->gpxPoints as $p) {
                if (!$station->containsPoint((float) $p->latitude, (float) $p->longitude)) {
                    $badGeo++;
                }
            }
            $end = $flight->flight_date->copy()->addMinutes((float) $flight->duration_minutes);
            foreach ($flight->detections as $d) {
                if ($d->detected_at->lt($flight->flight_date) || $d->detected_at->gt($end)) {
                    $badTime++;
                }
                if ((int) $d->station_id !== (int) $flight->station_id) {
                    $badStation++;
                }
            }
        }

        $this->line("  Letova provjereno: {$flights->count()}");
        $this->line('  GPX točaka izvan teritorija postaje: ' . ($badGeo ?: 'nema'));
        $this->line('  Detekcija izvan vremena leta: ' . ($badTime ?: 'nema'));
        $this->line('  Detekcija s pogrešnim station_id: ' . ($badStation ?: 'nema'));
    }
}
