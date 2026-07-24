<?php

namespace Database\Seeders;

use App\Models\Drone;
use App\Models\Flight;
use App\Models\GpxPoint;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Seeder;

class FlightSeeder extends Seeder
{
    // Centres around which flights are generated (varied areas for a rich heatmap)
    private array $areas = [
        ['lat' => 45.8000, 'lon' => 15.9000],
        ['lat' => 45.8100, 'lon' => 15.8850],
        ['lat' => 45.7900, 'lon' => 15.9200],
        ['lat' => 45.7850, 'lon' => 15.8950],
        ['lat' => 45.8150, 'lon' => 15.9150],
    ];

    private array $locations = [
        'Zagreb North', 'Zagreb East', 'Zagreb West',
        'Sesvete', 'Jankomir', 'Dubrava',
        'Črnomerec', 'Trešnjevka', 'Maksimir',
    ];

    private array $purposes = [
        'Infrastructure inspection',
        'Agricultural survey',
        'Real estate photography',
        'Security patrol',
        'Traffic monitoring',
        'Pipeline inspection',
        'Sports event coverage',
        'Construction site monitoring',
        'Mapping mission',
        'Search and rescue training',
    ];

    public function runWithRange(int $count, string $from, string $to): void
    {
        $drones = Drone::all();
        $pilots = User::role('pilot')->get();

        if ($drones->isEmpty() || $pilots->isEmpty()) {
            echo "No drones or pilots found.\n";
            return;
        }

        echo "Seeding {$count} flights from {$from} to {$to}...\n";

        $start = Carbon::parse($from);
        $range = Carbon::parse($to)->diffInSeconds($start);

        for ($i = 0; $i < $count; $i++) {
            $drone = $drones->random();
            $pilot = $pilots->random();
            $area  = $this->areas[array_rand($this->areas)];

            $flightDate = $start->copy()->addSeconds(rand(0, $range));
            $points     = $this->generatePoints($area, $flightDate);
            $stats      = $this->calculateStats($points);

            $flight = Flight::create([
                'drone_id'         => $drone->id,
                'user_id'          => $pilot->id,
                'flight_date'      => $points[0]['time'],
                'duration_minutes' => $stats['duration_minutes'],
                'distance_km'      => $stats['distance_km'],
                'max_altitude_m'   => $stats['max_altitude_m'],
                'avg_speed_kmh'    => $stats['avg_speed_kmh'],
                'location'         => $this->locations[array_rand($this->locations)],
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
            echo '.';
        }

        echo "\n{$count} flights seeded.\n";
    }

    public function run(): void
    {
        $drones = Drone::all();
        $pilots = User::role('pilot')->get();

        if ($drones->isEmpty() || $pilots->isEmpty()) {
            $this->command->warn('No drones or pilots found. Run RolesAndPermissionsSeeder first and create at least one drone and pilot.');
            return;
        }

        $count = (int) ($this->command->option('count') ?? 50);
        $from  = $this->command->option('from')  ?? now()->subDays(90)->toDateString();
        $to    = $this->command->option('to')    ?? now()->toDateString();

        $this->command->info("Seeding {$count} fake flights from {$from} to {$to}...");

        $start = Carbon::parse($from);
        $end   = Carbon::parse($to);
        $range = $end->diffInSeconds($start);

        for ($i = 0; $i < $count; $i++) {
            $drone  = $drones->random();
            $pilot  = $pilots->random();
            $area   = $this->areas[array_rand($this->areas)];

            $flightDate = $start->copy()->addSeconds(rand(0, $range));

            // Generate GPX points
            $points   = $this->generatePoints($area, $flightDate);
            $stats    = $this->calculateStats($points);

            $flight = Flight::create([
                'drone_id'         => $drone->id,
                'user_id'          => $pilot->id,
                'flight_date'      => $points[0]['time'],
                'duration_minutes' => $stats['duration_minutes'],
                'distance_km'      => $stats['distance_km'],
                'max_altitude_m'   => $stats['max_altitude_m'],
                'avg_speed_kmh'    => $stats['avg_speed_kmh'],
                'location'         => $this->locations[array_rand($this->locations)],
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

            $this->command->getOutput()->write('.');
        }

        $this->command->info('');
        $this->command->info("{$count} flights seeded successfully.");
    }

    private function generatePoints(array $center, Carbon $startTime): array
    {
        $pattern = rand(0, 2); // 0 = rectangular, 1 = circular, 2 = corridor
        $pointCount = rand(30, 60);
        $interval   = rand(8, 15); // seconds between points

        return match ($pattern) {
            0 => $this->rectangularPath($center, $startTime, $pointCount, $interval),
            1 => $this->circularPath($center, $startTime, $pointCount, $interval),
            2 => $this->corridorPath($center, $startTime, $pointCount, $interval),
        };
    }

    private function rectangularPath(array $c, Carbon $start, int $n, int $interval): array
    {
        $latSpan = 0.005 + lcg_value() * 0.010;
        $lonSpan = 0.007 + lcg_value() * 0.012;
        $cruiseAlt = rand(60, 140);

        $corners = [
            [$c['lat'],            $c['lon']           ],
            [$c['lat'] + $latSpan, $c['lon']           ],
            [$c['lat'] + $latSpan, $c['lon'] + $lonSpan],
            [$c['lat'],            $c['lon'] + $lonSpan],
            [$c['lat'],            $c['lon']           ],
        ];

        return $this->buildPath($corners, $n, $interval, $start, $cruiseAlt);
    }

    private function circularPath(array $c, Carbon $start, int $n, int $interval): array
    {
        $cruiseAlt = rand(60, 140);
        $latR = 0.003 + lcg_value() * 0.006;
        $lonR = $latR * 1.3;
        $loops = rand(1, 3);
        $totalDeg = 360 * $loops;

        $waypoints = [];
        $steps = max(12, $n - 10);
        for ($i = 0; $i <= $steps; $i++) {
            $deg = ($totalDeg / $steps) * $i;
            $rad = deg2rad($deg);
            $waypoints[] = [
                $c['lat'] + $latR * cos($rad),
                $c['lon'] + $lonR * sin($rad),
            ];
        }

        return $this->buildPath($waypoints, $n, $interval, $start, $cruiseAlt);
    }

    private function corridorPath(array $c, Carbon $start, int $n, int $interval): array
    {
        $cruiseAlt = rand(80, 150);
        $bearing   = lcg_value() * 360;
        $rad       = deg2rad($bearing);
        $dist      = 0.015 + lcg_value() * 0.015;
        $offset    = 0.001;

        $endLat = $c['lat'] + $dist * cos($rad);
        $endLon = $c['lon'] + $dist * sin($rad);

        $waypoints = [
            [$c['lat'],           $c['lon']          ],
            [$endLat,             $endLon            ],
            [$endLat + $offset,   $endLon + $offset  ],
            [$c['lat'] + $offset, $c['lon'] + $offset],
            [$c['lat'],           $c['lon']          ],
        ];

        return $this->buildPath($waypoints, $n, $interval, $start, $cruiseAlt);
    }

    private function buildPath(array $waypoints, int $n, int $interval, Carbon $start, int $cruiseAlt): array
    {
        $climbPoints   = 5;
        $descentPoints = 5;
        $cruisePoints  = $n - $climbPoints - $descentPoints;

        // Distribute cruise points evenly across waypoint segments
        $cruisePerSeg = max(1, intdiv($cruisePoints, max(1, count($waypoints) - 1)));

        $path = [];
        $time = $start->copy();
        $prev = null;

        // Takeoff climb
        $firstWp = $waypoints[0];
        for ($i = 0; $i < $climbPoints; $i++) {
            $frac = $i / $climbPoints;
            $alt  = round($cruiseAlt * $frac, 1);
            $path[] = ['lat' => $firstWp[0], 'lon' => $firstWp[1], 'alt' => $alt, 'time' => $time->copy(), 'speed' => null];
            $time->addSeconds($interval);
        }

        // Cruise between waypoints
        for ($w = 0; $w < count($waypoints) - 1; $w++) {
            $a = $waypoints[$w];
            $b = $waypoints[$w + 1];
            for ($s = 0; $s < $cruisePerSeg; $s++) {
                $frac = ($cruisePerSeg > 1) ? $s / ($cruisePerSeg - 1) : 0;
                $lat  = $a[0] + ($b[0] - $a[0]) * $frac;
                $lon  = $a[1] + ($b[1] - $a[1]) * $frac;
                // Small natural wobble
                $lat += (lcg_value() - 0.5) * 0.0002;
                $lon += (lcg_value() - 0.5) * 0.0002;
                $alt  = round($cruiseAlt + (lcg_value() - 0.5) * 10, 1);
                $point = ['lat' => $lat, 'lon' => $lon, 'alt' => $alt, 'time' => $time->copy(), 'speed' => null];
                if ($prev) {
                    $dist = $this->haversineKm($prev['lat'], $prev['lon'], $lat, $lon);
                    $point['speed'] = round($dist / ($interval / 3600), 1);
                }
                $path[] = $point;
                $prev = $point;
                $time->addSeconds($interval);
            }
        }

        // Descent and landing
        $lastWp = $waypoints[count($waypoints) - 1];
        for ($i = $descentPoints; $i >= 0; $i--) {
            $frac = $i / $descentPoints;
            $alt  = round($cruiseAlt * $frac, 1);
            $path[] = ['lat' => $lastWp[0], 'lon' => $lastWp[1], 'alt' => $alt, 'time' => $time->copy(), 'speed' => null];
            $time->addSeconds($interval);
        }

        // Fill first point speed
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
