<?php

namespace Database\Seeders;

use App\Models\Detection;
use App\Models\Drone;
use App\Models\Flight;
use App\Models\GpxPoint;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Seeder;

class SavaFlightSeeder extends Seeder
{
    // Patrol areas along the Sava river (Croatian north bank), Slavonski Brod → Gunja
    private array $areas = [
        ['lat' => 45.1650, 'lon' => 17.9750, 'name' => 'Slavonski Brod — zapad'],
        ['lat' => 45.1580, 'lon' => 18.0480, 'name' => 'Slavonski Brod — istok'],
        ['lat' => 45.1520, 'lon' => 18.1350, 'name' => 'Oprisavci — Sava'],
        ['lat' => 45.1420, 'lon' => 18.2450, 'name' => 'Velika Kopanica — obala'],
        ['lat' => 45.1480, 'lon' => 18.3900, 'name' => 'Vrpolje — Sava krivina'],
        ['lat' => 45.0600, 'lon' => 18.4780, 'name' => 'Slavonski Šamac — prijelaz'],
        ['lat' => 45.0920, 'lon' => 18.5300, 'name' => 'Babina Greda — obala'],
        ['lat' => 45.0180, 'lon' => 18.6380, 'name' => 'Rajevo Selo — koridor'],
        ['lat' => 44.9700, 'lon' => 18.6980, 'name' => 'Županja — Sava'],
        ['lat' => 44.9120, 'lon' => 18.8200, 'name' => 'Drenovci — Gunja'],
    ];

    // Detection hotspots at known river-crossing points along the Sava
    private array $hotspots = [
        // Slavonski Brod area
        ['lat' => 45.1620, 'lon' => 17.9680, 'types' => ['person', 'group']],
        ['lat' => 45.1590, 'lon' => 17.9820, 'types' => ['vehicle']],
        ['lat' => 45.1555, 'lon' => 17.9950, 'types' => ['person']],
        ['lat' => 45.1530, 'lon' => 18.0120, 'types' => ['group', 'person']],
        ['lat' => 45.1570, 'lon' => 18.0340, 'types' => ['other']],
        ['lat' => 45.1540, 'lon' => 18.0610, 'types' => ['person', 'vehicle']],
        ['lat' => 45.1510, 'lon' => 18.0750, 'types' => ['group']],
        ['lat' => 45.1480, 'lon' => 18.0880, 'types' => ['person', 'other']],
        // Oprisavci / Brodska Varoš
        ['lat' => 45.1450, 'lon' => 18.1080, 'types' => ['person']],
        ['lat' => 45.1430, 'lon' => 18.1230, 'types' => ['vehicle']],
        ['lat' => 45.1400, 'lon' => 18.1380, 'types' => ['group', 'person']],
        ['lat' => 45.1370, 'lon' => 18.1520, 'types' => ['person']],
        ['lat' => 45.1345, 'lon' => 18.1680, 'types' => ['vehicle']],
        // Velika Kopanica — Donji Andrijevci
        ['lat' => 45.1420, 'lon' => 18.1880, 'types' => ['person', 'group']],
        ['lat' => 45.1390, 'lon' => 18.2050, 'types' => ['other']],
        ['lat' => 45.1360, 'lon' => 18.2220, 'types' => ['person']],
        ['lat' => 45.1330, 'lon' => 18.2400, 'types' => ['group', 'vehicle']],
        ['lat' => 45.1310, 'lon' => 18.2570, 'types' => ['person']],
        // Vrpolje — Đurići
        ['lat' => 45.1450, 'lon' => 18.3120, 'types' => ['person']],
        ['lat' => 45.1430, 'lon' => 18.3300, 'types' => ['group']],
        ['lat' => 45.1460, 'lon' => 18.3540, 'types' => ['vehicle']],
        ['lat' => 45.1470, 'lon' => 18.3760, 'types' => ['person', 'group']],
        ['lat' => 45.1450, 'lon' => 18.3990, 'types' => ['person']],
        ['lat' => 45.1430, 'lon' => 18.4180, 'types' => ['other']],
        // Slavonski Šamac prijelaz (most)
        ['lat' => 45.0720, 'lon' => 18.4480, 'types' => ['vehicle', 'person']],
        ['lat' => 45.0640, 'lon' => 18.4620, 'types' => ['group']],
        ['lat' => 45.0570, 'lon' => 18.4780, 'types' => ['person']],
        ['lat' => 45.0510, 'lon' => 18.4930, 'types' => ['vehicle']],
        // Babina Greda
        ['lat' => 45.0880, 'lon' => 18.5080, 'types' => ['person', 'group']],
        ['lat' => 45.0840, 'lon' => 18.5250, 'types' => ['other']],
        ['lat' => 45.0800, 'lon' => 18.5440, 'types' => ['person']],
        ['lat' => 45.0750, 'lon' => 18.5620, 'types' => ['group', 'vehicle']],
        // Rajevo Selo — Štitar
        ['lat' => 45.0350, 'lon' => 18.5920, 'types' => ['person']],
        ['lat' => 45.0280, 'lon' => 18.6120, 'types' => ['person']],
        ['lat' => 45.0200, 'lon' => 18.6290, 'types' => ['group']],
        ['lat' => 45.0130, 'lon' => 18.6480, 'types' => ['vehicle']],
        // Županja
        ['lat' => 44.9840, 'lon' => 18.6720, 'types' => ['person', 'group']],
        ['lat' => 44.9760, 'lon' => 18.6870, 'types' => ['person']],
        ['lat' => 44.9680, 'lon' => 18.7020, 'types' => ['other']],
        ['lat' => 44.9600, 'lon' => 18.7180, 'types' => ['vehicle', 'person']],
        // Strošinci — Drenovci
        ['lat' => 44.9530, 'lon' => 18.7480, 'types' => ['group', 'person']],
        ['lat' => 44.9450, 'lon' => 18.7670, 'types' => ['vehicle']],
        ['lat' => 44.9370, 'lon' => 18.7850, 'types' => ['person']],
        ['lat' => 44.9290, 'lon' => 18.8020, 'types' => ['group']],
        // Gunja
        ['lat' => 44.9180, 'lon' => 18.8220, 'types' => ['person', 'vehicle']],
        ['lat' => 44.9100, 'lon' => 18.8380, 'types' => ['group']],
        ['lat' => 44.9030, 'lon' => 18.8510, 'types' => ['person']],
        ['lat' => 44.8960, 'lon' => 18.8650, 'types' => ['vehicle', 'other']],
        ['lat' => 44.8890, 'lon' => 18.8780, 'types' => ['group']],
        ['lat' => 44.8820, 'lon' => 18.8900, 'types' => ['person']],
    ];

    private array $purposes = [
        'Nadzor rijeke Save',
        'Noćni patrolni let uz granicu',
        'Provjera sumnjive aktivnosti na obali',
        'Rutinsko praćenje graničnog pojasa',
        'Potraga uz riječno korito',
        'Praćenje vozila uz Savu',
        'Pregled šumskog pojasa uz granicu',
        'Nadzor riječnog prijelaza',
        'Jutarnji granični obilazak',
        'Hitni odgovor na dojavu',
        'Noćni pregled obale Save',
        'Izviđanje sektora uz Savu',
    ];

    public function run(): void
    {
        $this->command->info('Seeding 300 Sava river patrol flights (Slavonski Brod – Gunja)...');

        $drones = Drone::all();
        $pilots = User::role('pilot')->get();

        if ($drones->isEmpty() || $pilots->isEmpty()) {
            $this->command->error('No drones or pilots found. Run RolesAndPermissionsSeeder first.');
            return;
        }

        $start = Carbon::create(2026, 1, 1);
        $end   = Carbon::create(2026, 5, 31);
        $range = $end->diffInSeconds($start);

        for ($i = 0; $i < 300; $i++) {
            $drone  = $drones->random();
            $pilot  = $pilots->random();
            $area   = $this->areas[array_rand($this->areas)];

            $flightDate = $start->copy()->addSeconds(rand(0, $range));
            $points     = $this->generateRiverPatrol($area, $flightDate);
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

            $this->seedDetections($flight, $points, $pilot->id, $flightDate);

            $this->command->getOutput()->write('.');
        }

        $this->command->info('');
        $this->command->info('300 Sava river patrol flights seeded.');
        $this->command->info('Total detection count: ' . Detection::count());
    }

    // River patrols follow the Sava (roughly WSW→ESE, bearing ~100°), back-and-forth
    private function generateRiverPatrol(array $center, Carbon $startTime): array
    {
        $pointCount = rand(45, 75);
        $interval   = rand(8, 13);

        // Sava bearing: ~100° (ESE) or reverse ~280°
        $bearing = rand(0, 1) ? rand(95, 115) : rand(275, 295);
        $rad     = deg2rad($bearing);
        $dist    = 0.025 + lcg_value() * 0.035;
        $offset  = 0.0006; // slight offset for the return pass (north bank parallel)

        $endLat = $center['lat'] + $dist * cos($rad);
        $endLon = $center['lon'] + $dist * sin($rad);

        $waypoints = [
            [$center['lat'],            $center['lon']           ],
            [$endLat,                   $endLon                  ],
            [$endLat + $offset,         $endLon                  ],
            [$center['lat'] + $offset,  $center['lon']           ],
            [$center['lat'],            $center['lon']           ],
        ];

        return $this->buildPath($waypoints, $pointCount, $interval, $startTime, rand(70, 150));
    }

    private function seedDetections(Flight $flight, array $points, int $userId, Carbon $flightDate): void
    {
        $lats   = array_column($points, 'lat');
        $lons   = array_column($points, 'lon');
        $minLat = min($lats) - 0.006;
        $maxLat = max($lats) + 0.006;
        $minLon = min($lons) - 0.006;
        $maxLon = max($lons) + 0.006;

        $nearby = array_filter($this->hotspots, fn($h) =>
            $h['lat'] >= $minLat && $h['lat'] <= $maxLat &&
            $h['lon'] >= $minLon && $h['lon'] <= $maxLon
        );

        if (empty($nearby) || rand(1, 100) > 65) return;

        $count = rand(1, min(3, count($nearby)));
        $picks = array_rand(array_values($nearby), min($count, count($nearby)));
        if (!is_array($picks)) $picks = [$picks];

        $nearbyValues = array_values($nearby);
        foreach ($picks as $idx) {
            $hotspot = $nearbyValues[$idx];
            $type    = $hotspot['types'][array_rand($hotspot['types'])];

            $lat = $hotspot['lat'] + (rand(-50, 50) / 100000);
            $lon = $hotspot['lon'] + (rand(-50, 50) / 100000);

            Detection::create([
                'flight_id'      => $flight->id,
                'station_id'     => $flight->station_id,
                'created_by'     => $userId,
                'source'         => 'drone',
                'latitude'       => round($lat, 7),
                'longitude'      => round($lon, 7),
                'detection_type' => $type,
                'entity_count'   => $this->randomCount($type),
                'note'           => $this->randomNote($type),
                'detected_at'    => $flightDate->copy()->addMinutes(rand(5, 30)),
            ]);
        }
    }

    private function randomCount(string $type): int
    {
        return match($type) {
            'person'  => rand(1, 3),
            'group'   => rand(4, 12),
            'vehicle' => rand(1, 2),
            default   => 1,
        };
    }

    private function randomNote(string $type): ?string
    {
        $notes = match($type) {
            'person'  => ['Kretanje uz obalu Save', 'Prijelaz čamcem', 'Skriva se u trski', null, null],
            'group'   => ['Veća skupina uz riječnu obalu', 'Kretanje prema cesti', 'Tabor uz šumski pojas', null],
            'vehicle' => ['Tamni kombi bez tablica', 'Vozilo uz nasip Save', 'Noćno kretanje uz rijeku', null, null],
            default   => [null, null, 'Neidentificirana aktivnost uz Savu'],
        };
        return $notes[array_rand($notes)];
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

        $firstWp = $waypoints[0];
        for ($i = 0; $i < $climbPoints; $i++) {
            $frac   = $i / $climbPoints;
            $path[] = ['lat' => $firstWp[0], 'lon' => $firstWp[1], 'alt' => round($cruiseAlt * $frac, 1), 'time' => $time->copy(), 'speed' => null];
            $time->addSeconds($interval);
        }

        for ($w = 0; $w < count($waypoints) - 1; $w++) {
            $a = $waypoints[$w];
            $b = $waypoints[$w + 1];
            for ($s = 0; $s < $cruisePerSeg; $s++) {
                $frac  = ($cruisePerSeg > 1) ? $s / ($cruisePerSeg - 1) : 0;
                $lat   = $a[0] + ($b[0] - $a[0]) * $frac + (lcg_value() - 0.5) * 0.0003;
                $lon   = $a[1] + ($b[1] - $a[1]) * $frac + (lcg_value() - 0.5) * 0.0003;
                $alt   = round($cruiseAlt + (lcg_value() - 0.5) * 14, 1);
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
