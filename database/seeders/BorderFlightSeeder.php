<?php

namespace Database\Seeders;

use App\Models\Detection;
use App\Models\Drone;
use App\Models\Flight;
use App\Models\GpxPoint;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Seeder;

class BorderFlightSeeder extends Seeder
{
    // Border patrol areas along Bosnia-Croatia border (Velika Kladuša / Bihać corridor)
    private array $areas = [
        ['lat' => 45.170, 'lon' => 15.785, 'name' => 'Maljevac – Velika Kladuša'],
        ['lat' => 45.140, 'lon' => 15.745, 'name' => 'Šturlić crossing'],
        ['lat' => 45.115, 'lon' => 15.700, 'name' => 'Cetingrad ridge'],
        ['lat' => 45.090, 'lon' => 15.720, 'name' => 'Tržac valley'],
        ['lat' => 45.060, 'lon' => 15.755, 'name' => 'Bugar–Rakovica'],
        ['lat' => 45.030, 'lon' => 15.780, 'name' => 'Glinska Poljana'],
        ['lat' => 44.995, 'lon' => 15.820, 'name' => 'Izačić crossing'],
        ['lat' => 44.960, 'lon' => 15.845, 'name' => 'North Bihać'],
        ['lat' => 44.920, 'lon' => 15.860, 'name' => 'Una river bend'],
        ['lat' => 44.875, 'lon' => 15.875, 'name' => 'South Bihać corridor'],
    ];

    // 50 pre-defined detection hotspots along the border
    private array $hotspots = [
        ['lat' => 45.1715, 'lon' => 15.7820, 'types' => ['person', 'group']],
        ['lat' => 45.1680, 'lon' => 15.7905, 'types' => ['person', 'smuggling']],
        ['lat' => 45.1650, 'lon' => 15.7760, 'types' => ['group', 'vehicle']],
        ['lat' => 45.1590, 'lon' => 15.7840, 'types' => ['person']],
        ['lat' => 45.1530, 'lon' => 15.7680, 'types' => ['vehicle', 'smuggling']],
        ['lat' => 45.1490, 'lon' => 15.7720, 'types' => ['person', 'group']],
        ['lat' => 45.1440, 'lon' => 15.7500, 'types' => ['person']],
        ['lat' => 45.1410, 'lon' => 15.7580, 'types' => ['smuggling', 'vehicle']],
        ['lat' => 45.1370, 'lon' => 15.7430, 'types' => ['group']],
        ['lat' => 45.1310, 'lon' => 15.7360, 'types' => ['person', 'other']],
        ['lat' => 45.1265, 'lon' => 15.7280, 'types' => ['vehicle']],
        ['lat' => 45.1220, 'lon' => 15.7150, 'types' => ['person', 'group']],
        ['lat' => 45.1190, 'lon' => 15.7060, 'types' => ['person']],
        ['lat' => 45.1140, 'lon' => 15.7090, 'types' => ['smuggling']],
        ['lat' => 45.1095, 'lon' => 15.7180, 'types' => ['group', 'person']],
        ['lat' => 45.1040, 'lon' => 15.7240, 'types' => ['vehicle', 'smuggling']],
        ['lat' => 45.0980, 'lon' => 15.7290, 'types' => ['person']],
        ['lat' => 45.0920, 'lon' => 15.7400, 'types' => ['group']],
        ['lat' => 45.0870, 'lon' => 15.7480, 'types' => ['person', 'vehicle']],
        ['lat' => 45.0820, 'lon' => 15.7560, 'types' => ['smuggling']],
        ['lat' => 45.0770, 'lon' => 15.7620, 'types' => ['person', 'group']],
        ['lat' => 45.0710, 'lon' => 15.7680, 'types' => ['person']],
        ['lat' => 45.0660, 'lon' => 15.7750, 'types' => ['vehicle']],
        ['lat' => 45.0600, 'lon' => 15.7790, 'types' => ['group', 'smuggling']],
        ['lat' => 45.0540, 'lon' => 15.7840, 'types' => ['person']],
        ['lat' => 45.0480, 'lon' => 15.7880, 'types' => ['person', 'other']],
        ['lat' => 45.0420, 'lon' => 15.7920, 'types' => ['group']],
        ['lat' => 45.0360, 'lon' => 15.7970, 'types' => ['vehicle', 'smuggling']],
        ['lat' => 45.0290, 'lon' => 15.8010, 'types' => ['person']],
        ['lat' => 45.0230, 'lon' => 15.8050, 'types' => ['group', 'person']],
        ['lat' => 45.0170, 'lon' => 15.8100, 'types' => ['smuggling']],
        ['lat' => 45.0110, 'lon' => 15.8150, 'types' => ['person', 'vehicle']],
        ['lat' => 45.0040, 'lon' => 15.8190, 'types' => ['group']],
        ['lat' => 44.9970, 'lon' => 15.8230, 'types' => ['person']],
        ['lat' => 44.9900, 'lon' => 15.8270, 'types' => ['smuggling', 'vehicle']],
        ['lat' => 44.9830, 'lon' => 15.8310, 'types' => ['person', 'group']],
        ['lat' => 44.9760, 'lon' => 15.8360, 'types' => ['person']],
        ['lat' => 44.9690, 'lon' => 15.8400, 'types' => ['other']],
        ['lat' => 44.9620, 'lon' => 15.8440, 'types' => ['vehicle', 'smuggling']],
        ['lat' => 44.9550, 'lon' => 15.8470, 'types' => ['person', 'group']],
        ['lat' => 44.9480, 'lon' => 15.8510, 'types' => ['person']],
        ['lat' => 44.9410, 'lon' => 15.8545, 'types' => ['group']],
        ['lat' => 44.9330, 'lon' => 15.8580, 'types' => ['smuggling']],
        ['lat' => 44.9250, 'lon' => 15.8610, 'types' => ['person', 'vehicle']],
        ['lat' => 44.9170, 'lon' => 15.8640, 'types' => ['group', 'person']],
        ['lat' => 44.9090, 'lon' => 15.8670, 'types' => ['person']],
        ['lat' => 44.9010, 'lon' => 15.8700, 'types' => ['smuggling', 'other']],
        ['lat' => 44.8930, 'lon' => 15.8730, 'types' => ['vehicle']],
        ['lat' => 44.8840, 'lon' => 15.8755, 'types' => ['person', 'group']],
        ['lat' => 44.8760, 'lon' => 15.8780, 'types' => ['person']],
    ];

    private array $purposes = [
        'Border surveillance patrol',
        'Night vision patrol run',
        'Suspicious activity follow-up',
        'Scheduled border monitoring',
        'Search and rescue support',
        'Vehicle tracking mission',
        'Forest corridor sweep',
        'River crossing surveillance',
        'Mountain pass observation',
        'Pre-dawn border sweep',
    ];

    public function run(): void
    {
        $this->command->info('Clearing existing flights, GPX points and detections...');
        Detection::truncate();
        GpxPoint::truncate();
        Flight::withTrashed()->forceDelete();
        $this->command->info('Done. Seeding 500 border patrol flights...');

        $drones = Drone::all();
        $pilots = User::role('pilot')->get();

        if ($drones->isEmpty() || $pilots->isEmpty()) {
            $this->command->error('No drones or pilots found. Run RolesAndPermissionsSeeder first.');
            return;
        }

        $start = Carbon::create(2026, 1, 1);
        $end   = Carbon::create(2026, 5, 31);
        $range = $end->diffInSeconds($start);

        for ($i = 0; $i < 500; $i++) {
            $drone  = $drones->random();
            $pilot  = $pilots->random();
            $area   = $this->areas[array_rand($this->areas)];

            $flightDate = $start->copy()->addSeconds(rand(0, $range));
            $points     = $this->generateBorderPatrol($area, $flightDate);
            $stats      = $this->calculateStats($points);

            $flight = Flight::create([
                'drone_id'         => $drone->id,
                'user_id'          => $pilot->id,
                'flight_date'      => $points[0]['time'],
                'duration_minutes' => $stats['duration_minutes'],
                'distance_km'      => $stats['distance_km'],
                'max_altitude_m'   => $stats['max_altitude_m'],
                'avg_speed_kmh'    => $stats['avg_speed_kmh'],
                'location'         => $area['name'],
                'purpose'          => $this->purposes[array_rand($this->purposes)],
                'status'           => 'completed',
            ]);

            $rows = array_map(fn($p, $idx) => [
                'flight_id'   => $flight->id,
                'latitude'    => $p['lat'],
                'longitude'   => $p['lon'],
                'altitude'    => $p['alt'],
                'speed'       => $p['speed'],
                'timestamp'   => $p['time']->format('Y-m-d H:i:s'),
                'point_order' => $idx + 1,
                'created_at'  => now(),
                'updated_at'  => now(),
            ], $points, array_keys($points));

            GpxPoint::insert($rows);

            // Add 0-3 detections near hotspots that fall within flight bounding box
            $this->seedDetections($flight, $points, $pilot->id, $flightDate);

            $this->command->getOutput()->write('.');
        }

        $this->command->info('');
        $this->command->info('500 border patrol flights seeded.');
        $this->command->info('Detection count: ' . Detection::count());
    }

    private function seedDetections(Flight $flight, array $points, int $userId, Carbon $flightDate): void
    {
        $lats  = array_column($points, 'lat');
        $lons  = array_column($points, 'lon');
        $minLat = min($lats) - 0.005;
        $maxLat = max($lats) + 0.005;
        $minLon = min($lons) - 0.005;
        $maxLon = max($lons) + 0.005;

        $nearby = array_filter($this->hotspots, fn($h) =>
            $h['lat'] >= $minLat && $h['lat'] <= $maxLat &&
            $h['lon'] >= $minLon && $h['lon'] <= $maxLon
        );

        if (empty($nearby)) return;

        // Pick 0–3 hotspots randomly (weighted: 60% chance of at least one)
        if (rand(1, 100) > 60) return;

        $count = rand(1, min(3, count($nearby)));
        $picks = array_rand(array_values($nearby), min($count, count($nearby)));
        if (!is_array($picks)) $picks = [$picks];

        $nearbyValues = array_values($nearby);
        foreach ($picks as $idx) {
            $hotspot = $nearbyValues[$idx];
            $type    = $hotspot['types'][array_rand($hotspot['types'])];

            // Small jitter so repeat sightings at same hotspot don't stack exactly
            $lat = $hotspot['lat'] + (rand(-50, 50) / 100000);
            $lon = $hotspot['lon'] + (rand(-50, 50) / 100000);

            Detection::create([
                'flight_id'   => $flight->id,
                'user_id'     => $userId,
                'latitude'    => round($lat, 7),
                'longitude'   => round($lon, 7),
                'type'        => $type,
                'count'       => $this->randomCount($type),
                'notes'       => $this->randomNote($type),
                'detected_at' => $flightDate->copy()->addMinutes(rand(5, 25)),
            ]);
        }
    }

    private function randomCount(string $type): int
    {
        return match($type) {
            'person'    => rand(1, 3),
            'group'     => rand(4, 15),
            'vehicle'   => rand(1, 3),
            'smuggling' => rand(1, 4),
            default     => 1,
        };
    }

    private function randomNote(string $type): ?string
    {
        $notes = match($type) {
            'person'    => ['Moving north on foot', 'Hiding in tree line', 'Crossed river', null, null],
            'group'     => ['Large group, mixed ages', 'Moving fast towards road', 'Camped near forest edge', null],
            'vehicle'   => ['Dark SUV without plates', 'Van parked off-road', 'Moving lights at night', null, null],
            'smuggling' => ['Backpacks visible', 'Drop point near fence', 'Suspected goods transfer', null],
            default     => [null, null, 'Unidentified activity'],
        };
        return $notes[array_rand($notes)];
    }

    private function generateBorderPatrol(array $center, Carbon $startTime): array
    {
        // Border patrols follow the border line (roughly NW-SE), so corridor pattern
        $pointCount = rand(40, 70);
        $interval   = rand(8, 14);

        // Random bearing along the border (roughly 135-165 degrees = SE, or reverse)
        $bearing  = rand(0, 1) ? rand(130, 165) : rand(310, 345);
        $rad      = deg2rad($bearing);
        $dist     = 0.020 + lcg_value() * 0.030; // longer corridor for border patrol
        $offset   = 0.0005;

        $endLat = $center['lat'] + $dist * cos($rad);
        $endLon = $center['lon'] + $dist * sin($rad);

        // Back-and-forth sweep
        $waypoints = [
            [$center['lat'],           $center['lon']          ],
            [$endLat,                  $endLon                 ],
            [$endLat + $offset,        $endLon - $offset       ],
            [$center['lat'] + $offset, $center['lon'] - $offset],
            [$center['lat'],           $center['lon']          ],
        ];

        return $this->buildPath($waypoints, $pointCount, $interval, $startTime, rand(80, 160));
    }

    private function buildPath(array $waypoints, int $n, int $interval, Carbon $start, int $cruiseAlt): array
    {
        $climbPoints   = 5;
        $descentPoints = 5;
        $cruisePoints  = $n - $climbPoints - $descentPoints;
        $cruisePerSeg  = max(1, intdiv($cruisePoints, max(1, count($waypoints) - 1)));

        $path = [];
        $time = $start->copy();
        $prev = null;

        // Takeoff climb
        $firstWp = $waypoints[0];
        for ($i = 0; $i < $climbPoints; $i++) {
            $frac   = $i / $climbPoints;
            $path[] = ['lat' => $firstWp[0], 'lon' => $firstWp[1], 'alt' => round($cruiseAlt * $frac, 1), 'time' => $time->copy(), 'speed' => null];
            $time->addSeconds($interval);
        }

        // Cruise
        for ($w = 0; $w < count($waypoints) - 1; $w++) {
            $a = $waypoints[$w];
            $b = $waypoints[$w + 1];
            for ($s = 0; $s < $cruisePerSeg; $s++) {
                $frac  = ($cruisePerSeg > 1) ? $s / ($cruisePerSeg - 1) : 0;
                $lat   = $a[0] + ($b[0] - $a[0]) * $frac + (lcg_value() - 0.5) * 0.0002;
                $lon   = $a[1] + ($b[1] - $a[1]) * $frac + (lcg_value() - 0.5) * 0.0002;
                $alt   = round($cruiseAlt + (lcg_value() - 0.5) * 12, 1);
                $point = ['lat' => $lat, 'lon' => $lon, 'alt' => $alt, 'time' => $time->copy(), 'speed' => null];
                if ($prev) {
                    $dist          = $this->haversineKm($prev['lat'], $prev['lon'], $lat, $lon);
                    $point['speed'] = round($dist / ($interval / 3600), 1);
                }
                $path[] = $point;
                $prev   = $point;
                $time->addSeconds($interval);
            }
        }

        // Descent
        $lastWp = $waypoints[count($waypoints) - 1];
        for ($i = $descentPoints; $i >= 0; $i--) {
            $frac   = $i / $descentPoints;
            $path[] = ['lat' => $lastWp[0], 'lon' => $lastWp[1], 'alt' => round($cruiseAlt * $frac, 1), 'time' => $time->copy(), 'speed' => null];
            $time->addSeconds($interval);
        }

        if (count($path) > 1 && $path[0]['speed'] === null) {
            $path[0]['speed'] = $path[1]['speed'] ?? 0;
        }

        return $path;
    }

    private function calculateStats(array $points): array
    {
        $totalDist = 0;
        for ($i = 1; $i < count($points); $i++) {
            $totalDist += $this->haversineKm(
                $points[$i - 1]['lat'], $points[$i - 1]['lon'],
                $points[$i]['lat'],     $points[$i]['lon']
            );
        }

        $first    = $points[0];
        $last     = $points[count($points) - 1];
        $duration = round(($last['time']->timestamp - $first['time']->timestamp) / 60, 1);

        $altitudes = array_filter(array_column($points, 'alt'));
        $maxAlt    = !empty($altitudes) ? round(max($altitudes), 1) : null;

        $speeds   = array_filter(array_column($points, 'speed'), fn($s) => $s > 0);
        $avgSpeed = !empty($speeds) ? round(array_sum($speeds) / count($speeds), 1) : null;

        return [
            'duration_minutes' => $duration,
            'distance_km'      => round($totalDist, 3),
            'max_altitude_m'   => $maxAlt,
            'avg_speed_kmh'    => $avgSpeed,
        ];
    }

    private function haversineKm(float $lat1, float $lon1, float $lat2, float $lon2): float
    {
        $R    = 6371;
        $dLat = deg2rad($lat2 - $lat1);
        $dLon = deg2rad($lon2 - $lon1);
        $a    = sin($dLat / 2) ** 2
              + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLon / 2) ** 2;
        return $R * 2 * atan2(sqrt($a), sqrt(1 - $a));
    }
}
