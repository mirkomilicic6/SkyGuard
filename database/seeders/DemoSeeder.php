<?php

namespace Database\Seeders;

use App\Models\BorderPoliceStation;
use App\Models\Drone;
use App\Models\Flight;
use App\Models\GpxPoint;
use App\Models\MaintenanceLog;
use App\Models\PoliceAdministration;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class DemoSeeder extends Seeder
{
    // Center coordinates for each station (lat, lng) — Croatian side of the BiH border
    private array $stationCoords = [
        'PGP Slunj'              => [45.130, 15.585],
        'PGP Cetingrad'          => [45.125, 15.760],
        'PGP Vojnić'             => [45.325, 15.720],
        'PGP Maljevac'           => [45.175, 15.725],
        'PGP Rakovica'           => [44.970, 15.680],
        'PGP Krnjak'             => [45.338, 15.882],
        'PGP Hrvatska Kostajnica'=> [45.230, 16.540],
        'PGP Dvor na Uni'        => [45.082, 16.385],
        'PGP Donji Kukuruzari'   => [45.218, 16.268],
        'PGP Jasenovac'          => [45.262, 17.002],
        'PGP Sunja'              => [45.360, 16.550],
        'PGP Hrvatska Dubica'    => [45.182, 16.780],
        'PGP Ilok'               => [45.222, 19.372],
        'PGP Tovarnik'           => [45.150, 19.148],
        'PGP Lipovac'            => [45.072, 19.018],
        'PGP Babska'             => [45.032, 18.832],
        'PGP Nijemci'            => [45.008, 18.908],
        'PGP Strošinci'          => [45.122, 18.968],
        'PGP Sinj'               => [43.702, 16.638],
        'PGP Trilj'              => [43.618, 16.728],
        'PGP Imotski'            => [43.448, 17.218],
        'PGP Vrgorac'            => [43.198, 17.372],
        'PGP Vinjani Gornji'     => [43.502, 17.178],
        'PGP Kamensko'           => [43.638, 16.750],
        'PGP Metković'           => [43.058, 17.648],
        'PGP Ploče'              => [43.055, 17.432],
        'PGP Osojnik'            => [42.728, 18.098],
        'PGP Zaton Doli'         => [42.722, 17.872],
        'PGP Ivanica'            => [42.548, 18.112],
        'PGP Orebić'             => [42.978, 17.172],
    ];

    // Terrain altitude (m) per station for realistic GPX
    private array $stationAltitude = [
        'PU Karlovačka'             => [200, 380],
        'PU Sisačko-moslavačka'     => [90,  200],
        'PU Vukovarsko-srijemska'   => [80,  120],
        'PU Splitsko-dalmatinska'   => [250, 750],
        'PU Dubrovačko-neretvanska' => [80,  480],
    ];

    private array $maleNames   = ['Ante', 'Ivan', 'Marko', 'Luka', 'Josip', 'Nikola', 'Mario', 'Tomislav', 'Stjepan', 'Petar', 'Hrvoje', 'Damir', 'Goran', 'Krunoslav', 'Dario', 'Bruno', 'Matej', 'Filip', 'Domagoj', 'Krešimir'];
    private array $femaleNames = ['Ana', 'Maja', 'Ivana', 'Petra', 'Marija', 'Martina', 'Jelena', 'Sandra'];
    private array $lastNames   = ['Horvat', 'Kovačević', 'Babić', 'Marković', 'Perić', 'Tomić', 'Pavić', 'Matić', 'Novak', 'Jurić', 'Knežević', 'Vuković', 'Galić', 'Vidović', 'Barić', 'Filipović', 'Blažević', 'Mikulić', 'Radić', 'Stanić', 'Šarić', 'Lončar', 'Puljić', 'Mandić', 'Zečević', 'Bošnjak', 'Franić', 'Đukić', 'Mišković', 'Kelemen'];

    private array $flightPurposes = [
        'Patroliranje državne granice',
        'Nadzor zelene granice',
        'Potraga za neregularnim migrantima',
        'Izviđanje terena',
        'Nadzor sumnjive aktivnosti',
        'Rutinski obilazak sektora',
        'Podrška terenskoj jedinici',
        'Nadzor šumskog područja',
        'Praćenje vektora kretanja',
        'Noćni nadzor pojasa',
    ];

    private array $droneModels = [
        ['model' => 'DJI Mavic 3 Enterprise', 'manufacturer' => 'DJI'],
        ['model' => 'DJI Matrice 300 RTK',    'manufacturer' => 'DJI'],
        ['model' => 'Autel EVO II Pro',        'manufacturer' => 'Autel Robotics'],
        ['model' => 'Parrot ANAFI USA',        'manufacturer' => 'Parrot'],
        ['model' => 'Skydio 2+',              'manufacturer' => 'Skydio'],
    ];

    public function run(): void
    {
        $this->command->info('Čišćenje baze podataka...');
        $this->clearDatabase();

        $this->command->info('Kreiranje strukture (uprave, postaje, uloge)...');
        $this->call(PoliceStructureSeeder::class);

        $this->command->info('Kreiranje 30 korisnika...');
        $users = $this->createUsers();

        $this->command->info('Kreiranje dronova po postajama...');
        $drones = $this->createDrones($users);

        $this->command->info('Kreiranje letova...');
        $this->createFlights($users, $drones);

        $this->command->info('Kreiranje zapisa o održavanju...');
        $this->createMaintenance($drones, $users);

        $flightCount = Flight::count();
        $this->command->info("Gotovo! Kreirano: 30 korisnika, {$drones->count()} dronova, {$flightCount} letova.");
    }

    // -------------------------------------------------------------------------

    private function clearDatabase(): void
    {
        DB::statement('SET FOREIGN_KEY_CHECKS=0');
        DB::table('gpx_points')->truncate();
        DB::table('detections')->truncate();
        DB::table('drone_checkouts')->truncate();
        DB::table('flights')->truncate();
        DB::table('maintenance_logs')->truncate();
        DB::table('drone_user')->truncate();
        DB::table('drones')->truncate();
        DB::table('model_has_roles')->truncate();
        DB::table('model_has_permissions')->truncate();
        DB::table('users')->truncate();
        DB::statement('SET FOREIGN_KEY_CHECKS=1');
        app()[\Spatie\Permission\PermissionRegistrar::class]->forgetCachedPermissions();
    }

    // -------------------------------------------------------------------------

    private function createUsers(): \Illuminate\Support\Collection
    {
        $stations = BorderPoliceStation::with('administration')->get()->keyBy('name');
        $allUsers = collect();

        // Predefined 30 users — station, role, name
        $userDefs = [
            // PU Karlovačka (6 korisnika)
            ['station' => 'PGP Slunj',               'role' => 'admin',  'fn' => 'Ivan',      'ln' => 'Horvat'],
            ['station' => 'PGP Slunj',               'role' => 'pilot',  'fn' => 'Ante',      'ln' => 'Babić'],
            ['station' => 'PGP Cetingrad',            'role' => 'pilot',  'fn' => 'Marko',     'ln' => 'Perić'],
            ['station' => 'PGP Vojnić',               'role' => 'pilot',  'fn' => 'Luka',      'ln' => 'Tomić'],
            ['station' => 'PGP Maljevac',             'role' => 'viewer', 'fn' => 'Stjepan',   'ln' => 'Novak'],
            ['station' => 'PGP Rakovica',             'role' => 'pilot',  'fn' => 'Josip',     'ln' => 'Jurić'],
            // PU Sisačko-moslavačka (6 korisnika)
            ['station' => 'PGP Hrvatska Kostajnica',  'role' => 'admin',  'fn' => 'Mario',     'ln' => 'Kovačević'],
            ['station' => 'PGP Hrvatska Kostajnica',  'role' => 'pilot',  'fn' => 'Nikola',    'ln' => 'Marković'],
            ['station' => 'PGP Dvor na Uni',          'role' => 'pilot',  'fn' => 'Tomislav',  'ln' => 'Galić'],
            ['station' => 'PGP Donji Kukuruzari',     'role' => 'pilot',  'fn' => 'Petar',     'ln' => 'Pavić'],
            ['station' => 'PGP Sunja',                'role' => 'viewer', 'fn' => 'Maja',      'ln' => 'Knežević'],
            ['station' => 'PGP Hrvatska Dubica',      'role' => 'pilot',  'fn' => 'Hrvoje',    'ln' => 'Matić'],
            // PU Vukovarsko-srijemska (6 korisnika)
            ['station' => 'PGP Ilok',                 'role' => 'admin',  'fn' => 'Damir',     'ln' => 'Vuković'],
            ['station' => 'PGP Ilok',                 'role' => 'pilot',  'fn' => 'Goran',     'ln' => 'Vidović'],
            ['station' => 'PGP Tovarnik',             'role' => 'pilot',  'fn' => 'Krunoslav', 'ln' => 'Barić'],
            ['station' => 'PGP Lipovac',              'role' => 'pilot',  'fn' => 'Dario',     'ln' => 'Filipović'],
            ['station' => 'PGP Babska',               'role' => 'viewer', 'fn' => 'Ana',       'ln' => 'Blažević'],
            ['station' => 'PGP Nijemci',              'role' => 'pilot',  'fn' => 'Bruno',     'ln' => 'Mikulić'],
            // PU Splitsko-dalmatinska (6 korisnika)
            ['station' => 'PGP Sinj',                 'role' => 'admin',  'fn' => 'Matej',     'ln' => 'Radić'],
            ['station' => 'PGP Sinj',                 'role' => 'pilot',  'fn' => 'Filip',     'ln' => 'Stanić'],
            ['station' => 'PGP Trilj',                'role' => 'pilot',  'fn' => 'Domagoj',   'ln' => 'Šarić'],
            ['station' => 'PGP Imotski',              'role' => 'pilot',  'fn' => 'Krešimir',  'ln' => 'Lončar'],
            ['station' => 'PGP Vrgorac',              'role' => 'viewer', 'fn' => 'Petra',     'ln' => 'Puljić'],
            ['station' => 'PGP Vinjani Gornji',       'role' => 'pilot',  'fn' => 'Ivana',     'ln' => 'Mandić'],
            // PU Dubrovačko-neretvanska (6 korisnika)
            ['station' => 'PGP Metković',             'role' => 'admin',  'fn' => 'Krešimir',  'ln' => 'Zečević'],
            ['station' => 'PGP Metković',             'role' => 'pilot',  'fn' => 'Sandra',    'ln' => 'Bošnjak'],
            ['station' => 'PGP Ploče',                'role' => 'pilot',  'fn' => 'Marija',    'ln' => 'Franić'],
            ['station' => 'PGP Osojnik',              'role' => 'pilot',  'fn' => 'Martina',   'ln' => 'Đukić'],
            ['station' => 'PGP Zaton Doli',           'role' => 'viewer', 'fn' => 'Jelena',    'ln' => 'Mišković'],
            ['station' => 'PGP Ivanica',              'role' => 'pilot',  'fn' => 'Nikola',    'ln' => 'Kelemen'],
        ];

        foreach ($userDefs as $def) {
            $station = $stations->get($def['station']);
            if (!$station) continue;

            $slug  = $this->slugify($def['fn']) . '.' . $this->slugify($def['ln']);
            $email = $slug . '@mup.hr';

            $user = User::create([
                'name'       => $def['fn'] . ' ' . $def['ln'],
                'email'      => $email,
                'password'   => Hash::make('password'),
                'station_id' => $station->id,
                'email_verified_at' => now(),
            ]);
            $user->assignRole($def['role']);
            $allUsers->push($user);
        }

        return $allUsers;
    }

    // -------------------------------------------------------------------------

    private function createDrones(\Illuminate\Support\Collection $users): \Illuminate\Support\Collection
    {
        $allDrones  = collect();
        $stations   = BorderPoliceStation::all();
        $pilotsByStation = $users->where(fn($u) => $u->hasRole('pilot'))
            ->groupBy('station_id');

        $serialBase = 1001;

        foreach ($stations as $station) {
            $coords  = $this->stationCoords[$station->name] ?? [44.5, 16.5];
            $count   = 2; // 2 drones per station

            for ($i = 0; $i < $count; $i++) {
                $model = $this->droneModels[array_rand($this->droneModels)];

                $drone = Drone::create([
                    'name'          => 'UAV-' . $station->id . '-' . ($i + 1),
                    'serial_number' => 'HR-GP-' . str_pad($serialBase++, 4, '0', STR_PAD_LEFT),
                    'model'         => $model['model'],
                    'manufacturer'  => $model['manufacturer'],
                    'purchase_date' => Carbon::now()->subMonths(rand(6, 36))->format('Y-m-d'),
                    'status'        => 'active',
                    'station_id'    => $station->id,
                    'notes'         => 'Operativna letjelica, sektor ' . $station->name,
                ]);

                // Assign all pilots from this station to all drones of this station
                $stationPilots = $pilotsByStation->get($station->id, collect());
                if ($stationPilots->isNotEmpty()) {
                    $drone->pilots()->sync($stationPilots->pluck('id'));
                }

                $allDrones->push($drone);
            }
        }

        return $allDrones;
    }

    // -------------------------------------------------------------------------

    private function createFlights(\Illuminate\Support\Collection $users, \Illuminate\Support\Collection $drones): void
    {
        $pilots = $users->filter(fn($u) => $u->hasRole('pilot'));
        $dronesByStation = $drones->groupBy('station_id');

        // Distribute flights: some pilots get 8-12 flights, some 4-7, totalling 200+
        $totalFlights = 0;
        $gpxBatch     = [];
        $flightId     = 0;

        // Assign varying flight counts to make it look realistic
        $flightCounts = [];
        foreach ($pilots as $pilot) {
            $flightCounts[$pilot->id] = rand(8, 14);
        }

        // Ensure at least 200 total
        $sum = array_sum($flightCounts);
        if ($sum < 200) {
            $toAdd = 200 - $sum;
            foreach ($pilots->shuffle()->take(ceil($toAdd / 3)) as $pilot) {
                $flightCounts[$pilot->id] += 3;
            }
        }

        foreach ($pilots as $pilot) {
            $stationDrones = $dronesByStation->get($pilot->station_id, collect());
            if ($stationDrones->isEmpty()) continue;

            $stationName  = $stationDrones->first()?->station?->name ?? '';
            $coords       = $this->stationCoords[$stationName] ?? [44.5, 16.5];
            $adminName    = $pilot->station?->administration?->name ?? 'PU Karlovačka';
            $altRange     = $this->stationAltitude[$adminName] ?? [150, 300];

            $count = $flightCounts[$pilot->id] ?? 8;

            for ($f = 0; $f < $count; $f++) {
                $drone       = $stationDrones->random();
                $flightDate  = Carbon::now()
                    ->subDays(rand(0, 180))
                    ->setTime(rand(5, 20), rand(0, 59));

                $duration    = rand(18, 55);
                $pointCount  = rand(50, 80);
                $heading     = rand(0, 360); // patrol direction

                [$points, $stats] = $this->generateGpxPath(
                    $coords[0], $coords[1],
                    $pointCount, $duration,
                    $altRange, $heading
                );

                $flight = Flight::create([
                    'drone_id'         => $drone->id,
                    'user_id'          => $pilot->id,
                    'station_id'       => $pilot->station_id,
                    'flight_date'      => $flightDate,
                    'duration_minutes' => $duration,
                    'distance_km'      => $stats['distance_km'],
                    'max_altitude_m'   => $stats['max_alt'],
                    'avg_speed_kmh'    => $stats['avg_speed'],
                    'gpx_file_path'    => null,
                    'location'         => $stationName . ' — sektor ' . chr(65 + ($f % 6)),
                    'purpose'          => $this->flightPurposes[array_rand($this->flightPurposes)],
                    'status'           => $this->randomStatus(),
                ]);

                $totalFlights++;

                foreach ($points as $order => $pt) {
                    $gpxBatch[] = [
                        'flight_id'   => $flight->id,
                        'latitude'    => $pt['lat'],
                        'longitude'   => $pt['lng'],
                        'altitude'    => $pt['alt'],
                        'speed'       => $pt['speed'],
                        'timestamp'   => $flightDate->copy()->addSeconds($order * intval($duration * 60 / $pointCount))->format('Y-m-d H:i:s'),
                        'point_order' => $order,
                        'created_at'  => now(),
                        'updated_at'  => now(),
                    ];
                }

                // Bulk insert every 100 flights worth of points to avoid memory issues
                if (count($gpxBatch) >= 5000) {
                    GpxPoint::insert($gpxBatch);
                    $gpxBatch = [];
                }
            }
        }

        if (!empty($gpxBatch)) {
            GpxPoint::insert($gpxBatch);
        }
    }

    // -------------------------------------------------------------------------

    private function createMaintenance(\Illuminate\Support\Collection $drones, \Illuminate\Support\Collection $users): void
    {
        $types      = ['routine', 'inspection', 'repair', 'part_replacement'];
        $adminUsers = $users->filter(fn($u) => $u->hasRole('admin'));

        $descriptions = [
            'routine'          => 'Redovni tehnički pregled letjelice i baterija.',
            'inspection'       => 'Vizualni pregled trupa i propelera. Svi sustavi uredni.',
            'repair'           => 'Zamjena oštećene propelerske ploče, kalibracija kompasa.',
            'part_replacement' => 'Zamjena baterije, servisiranje gimbal mehanizma.',
        ];

        foreach ($drones as $drone) {
            $reporter = $adminUsers->where('station_id', $drone->station_id)->first()
                ?? $users->where('station_id', $drone->station_id)->first();
            if (!$reporter) continue;

            $logCount = rand(1, 4);
            for ($i = 0; $i < $logCount; $i++) {
                $type   = $types[array_rand($types)];
                $status = $i === 0 ? 'resolved' : ($i === 1 && rand(0, 1) ? 'in_progress' : 'resolved');

                $log = MaintenanceLog::create([
                    'drone_id'    => $drone->id,
                    'reported_by' => $reporter->id,
                    'station_id'  => $drone->station_id,
                    'type'        => $type,
                    'description' => $descriptions[$type],
                    'cost'        => $type !== 'routine' ? rand(200, 2500) : rand(50, 300),
                    'status'      => $status,
                ]);

                if ($status === 'resolved') {
                    $log->update([
                        'resolved_by' => $reporter->id,
                        'resolved_at' => now()->subDays(rand(1, 60)),
                    ]);
                }
            }
        }
    }

    // -------------------------------------------------------------------------
    // GPX path generation — realistic patrol path along border
    // -------------------------------------------------------------------------

    private function generateGpxPath(
        float $lat, float $lng,
        int   $pointCount,
        int   $durationMin,
        array $altRange,
        int   $heading
    ): array {
        // 1 degree lat ≈ 111 km, 1 degree lng ≈ 111 * cos(lat) km
        $latPerM = 1 / 111000;
        $lngPerM = 1 / (111000 * cos(deg2rad($lat)));

        // Speed: 30-60 km/h = 8-17 m/s
        $speedMs     = rand(80, 160) / 10.0; // m/s
        $intervalSec = $durationMin * 60 / $pointCount;
        $stepM       = $speedMs * $intervalSec;

        $baseAlt = rand($altRange[0], $altRange[1]);
        $aglBase = rand(60, 130); // altitude above ground (AGL)

        // Flight goes out ~60% of duration, returns ~40%
        $outPoints = intval($pointCount * 0.55);

        $headRad = deg2rad($heading);
        $perpRad = deg2rad($heading + 90);

        $points    = [];
        $curLat    = $lat   + (rand(-50, 50) * $latPerM);
        $curLng    = $lng   + (rand(-50, 50) * $lngPerM);
        $totalDist = 0.0;
        $maxAlt    = 0.0;

        for ($i = 0; $i < $pointCount; $i++) {
            // Altitude with gradual change + small noise
            $altProgress = $i < 5
                ? ($i / 5)                         // takeoff
                : ($i > $pointCount - 5 ? (($pointCount - $i) / 5) : 1.0); // landing
            $alt = ($baseAlt + $aglBase) * $altProgress + rand(-8, 8);
            $alt = max(0, $alt);

            $speed = $speedMs * (0.85 + rand(0, 30) / 100.0);

            $points[] = [
                'lat'   => round($curLat, 7),
                'lng'   => round($curLng, 7),
                'alt'   => round($alt, 1),
                'speed' => round($speed * 3.6, 1), // convert to km/h
            ];

            $maxAlt = max($maxAlt, $alt);

            // Movement: follow heading for outward leg, reverse for return
            $dir = $i < $outPoints ? 1 : -1;

            $drift   = sin($i * 0.4) * $stepM * 0.15; // gentle lateral drift
            $jitter  = rand(-5, 5);                    // small random jitter (metres)

            $curLat += $dir * cos($headRad) * ($stepM + $jitter) * $latPerM
                     + $drift * sin($perpRad) * $latPerM;
            $curLng += $dir * sin($headRad) * ($stepM + $jitter) * $lngPerM
                     + $drift * cos($perpRad) * $lngPerM;

            if ($i > 0) {
                $dlat = ($points[$i]['lat'] - $points[$i - 1]['lat']) / $latPerM;
                $dlng = ($points[$i]['lng'] - $points[$i - 1]['lng']) / $lngPerM;
                $totalDist += sqrt($dlat ** 2 + $dlng ** 2);
            }
        }

        $stats = [
            'distance_km' => round($totalDist / 1000, 2),
            'max_alt'     => round($maxAlt, 1),
            'avg_speed'   => round($speedMs * 3.6, 1),
        ];

        return [$points, $stats];
    }

    // -------------------------------------------------------------------------

    private function randomStatus(): string
    {
        return rand(1, 100) <= 90 ? 'completed' : 'aborted';
    }

    private function slugify(string $name): string
    {
        $name = mb_strtolower($name);
        $map  = ['č' => 'c', 'ć' => 'c', 'š' => 's', 'ž' => 'z', 'đ' => 'd', 'Č' => 'c', 'Ć' => 'c', 'Š' => 's', 'Ž' => 'z', 'Đ' => 'd'];
        return strtr($name, $map);
    }
}
