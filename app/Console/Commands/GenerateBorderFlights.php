<?php

namespace App\Console\Commands;

use App\Models\BorderPoliceStation;
use App\Models\Drone;
use App\Models\Flight;
use App\Models\GpxPoint;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

class GenerateBorderFlights extends Command
{
    protected $signature = 'flights:generate-border {--per-station=15 : Number of flights to generate per station}';

    protected $description = 'Generate realistic patrol flights (with GPX tracks) anchored to the Croatian side of the real HR-BiH/HR-Srbija border, per station';

    /** Approximate real-world coordinates for each border police station town. */
    private const STATION_COORDS = [
        'PGP Slunj' => [45.1122, 15.5828],
        'PGP Cetingrad' => [45.0333, 15.7500],
        'PGP Vojnić' => [45.3086, 15.7167],
        'PGP Maljevac' => [44.9800, 15.7500],
        'PGP Rakovica' => [44.9667, 15.6833],
        'PGP Krnjak' => [45.2333, 15.7667],

        'PGP Hrvatska Kostajnica' => [45.1897, 16.5589],
        'PGP Dvor na Uni' => [45.0667, 16.3667],
        'PGP Donji Kukuruzari' => [45.1500, 16.4500],
        'PGP Jasenovac' => [45.2764, 16.9235],
        'PGP Sunja' => [45.3833, 16.5833],
        'PGP Hrvatska Dubica' => [45.1833, 16.8000],

        'PGP Ilok' => [45.2167, 19.3667],
        'PGP Tovarnik' => [45.1667, 19.1333],
        'PGP Lipovac' => [45.0333, 19.1167],
        'PGP Babska' => [45.1167, 19.2167],
        'PGP Nijemci' => [45.0167, 18.9667],
        'PGP Strošinci' => [44.9500, 19.0000],

        'PGP Sinj' => [43.7047, 16.6397],
        'PGP Trilj' => [43.6167, 16.7167],
        'PGP Imotski' => [43.4458, 17.2192],
        'PGP Vrgorac' => [43.2028, 17.3958],
        'PGP Vinjani Gornji' => [43.4500, 17.3500],
        'PGP Kamensko' => [43.6667, 16.9000],

        'PGP Metković' => [43.0553, 17.6483],
        'PGP Ploče' => [43.0567, 17.4319],
        'PGP Osojnik' => [42.7167, 18.1167],
        'PGP Zaton Doli' => [42.7500, 17.9667],
        'PGP Ivanica' => [42.6833, 18.3667],
        'PGP Orebić' => [42.9758, 17.1747],
    ];

    private array $segments = [];

    public function handle(): int
    {
        $this->loadBorderSegments();

        $perStation = (int) $this->option('per-station');
        $totalFlights = 0;
        $totalPoints = 0;

        $stations = BorderPoliceStation::with(['users' => fn ($q) => $q->role('pilot'), 'drones'])->get();

        foreach ($stations as $station) {
            $pilot = $station->users->first();
            $drones = $station->drones;
            $coord = self::STATION_COORDS[$station->name] ?? null;

            if (!$pilot || $drones->isEmpty() || !$coord) {
                $this->line("Preskačem {$station->name} (nema pilota/drona/koordinata).");
                continue;
            }

            [$borderLat, $borderLon, $tangent] = $this->nearestBorderPoint($coord[0], $coord[1]);
            $normal = $this->inwardNormal($borderLat, $borderLon, $tangent, $coord[0], $coord[1]);

            for ($i = 0; $i < $perStation; $i++) {
                $drone = $drones->random();
                [$flight, $points] = $this->buildFlight($station, $pilot->id, $drone->id, $borderLat, $borderLon, $tangent, $normal);
                $flight->save();

                foreach ($points as &$p) {
                    $p['flight_id'] = $flight->id;
                }
                GpxPoint::insert($points);

                $totalFlights++;
                $totalPoints += count($points);
            }

            $this->line("{$station->name}: +{$perStation} letova");
        }

        $this->info("Gotovo. Generirano {$totalFlights} letova, {$totalPoints} GPX točaka.");

        return self::SUCCESS;
    }

    private function loadBorderSegments(): void
    {
        $data = json_decode(File::get(storage_path('app/border_line.json')), true);
        $this->segments = [$data['hr_bih_main'], $data['hr_bih_south'], $data['hr_srb']];
    }

    /**
     * Find the nearest point across all border segments to the given coordinate,
     * plus a local tangent direction (unit vector [dLat, dLon]) at that point.
     */
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
                    $best = [
                        'dist' => $dist,
                        'lat' => $point[0],
                        'lon' => $point[1],
                        'tangent' => [$tLat / $len, $tLon / $len],
                    ];
                }
            }
        }

        return [$best['lat'], $best['lon'], $best['tangent']];
    }

    /** Unit normal to the tangent, pointing toward the station coordinate (i.e. into Croatia). */
    private function inwardNormal(float $borderLat, float $borderLon, array $tangent, float $stationLat, float $stationLon): array
    {
        $n1 = [-$tangent[1], $tangent[0]];
        $n2 = [$tangent[1], -$tangent[0]];

        $probe1 = $this->planarDistanceKm($stationLat, $stationLon, $borderLat + $n1[0] * 0.01, $borderLon + $n1[1] * 0.01);
        $probe2 = $this->planarDistanceKm($stationLat, $stationLon, $borderLat + $n2[0] * 0.01, $borderLon + $n2[1] * 0.01);

        return $probe1 < $probe2 ? $n1 : $n2;
    }

    private function planarDistanceKm(float $lat1, float $lon1, float $lat2, float $lon2): float
    {
        $midLat = deg2rad(($lat1 + $lat2) / 2);
        $dx = ($lon2 - $lon1) * 111.0 * cos($midLat);
        $dy = ($lat2 - $lat1) * 111.0;

        return sqrt($dx ** 2 + $dy ** 2);
    }

    /** @return array{0: Flight, 1: array<int, array>} */
    private function buildFlight(BorderPoliceStation $station, int $pilotId, int $droneId, float $borderLat, float $borderLon, array $tangent, array $normal): array
    {
        $offsetKm = mt_rand(150, 350) / 100;      // 1.5–3.5 km inland from the border line
        $jitterKm = 0.15;
        $jitterCycles = mt_rand(3, 6);
        $jitterPhase = mt_rand(0, 628) / 100;

        $centerLat = $borderLat + $normal[0] * $offsetKm / 111.0;
        $centerLon = $borderLon + $normal[1] * $offsetKm / (111.0 * cos(deg2rad($borderLat)));

        $numPoints = mt_rand(40, 90);
        $durationMinutes = mt_rand(15, 45);
        $flightDate = now()->subDays(mt_rand(0, 89))->subMinutes(mt_rand(0, 1439));

        // Pick a target distance from a realistic patrol speed, then size the
        // back-and-forth sweep so the generated path actually covers it
        // (accounting for the extra arc length the smooth jitter adds).
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
            // back-and-forth patrol sweep along the tangent, smooth perpendicular jitter, always offset inward
            $sweep = sin($t * M_PI * $cycles) * ($lengthKm / 2);
            $jitter = sin($t * 2 * M_PI * $jitterCycles + $jitterPhase) * $jitterKm;

            $km_lat = $sweep * $tangent[0] + $jitter * $normal[0];
            $km_lon = $sweep * $tangent[1] + $jitter * $normal[1];

            $lat = $centerLat + $km_lat / 111.0;
            $lon = $centerLon + $km_lon / (111.0 * cos(deg2rad($centerLat)));

            if ($prevLat !== null) {
                $totalDistanceKm += $this->planarDistanceKm($prevLat, $prevLon, $lat, $lon);
            }
            $prevLat = $lat;
            $prevLon = $lon;

            $points[] = [
                'latitude' => round($lat, 7),
                'longitude' => round($lon, 7),
                'altitude' => round($baseAlt + mt_rand(-15, 15), 2),
                'speed' => round(mt_rand(15, 40) + mt_rand(-5, 5), 2),
                'timestamp' => (clone $flightDate)->addSeconds((int) ($t * $durationMinutes * 60)),
                'point_order' => $i,
                'created_at' => now(),
                'updated_at' => now(),
            ];
        }

        $avgSpeed = $totalDistanceKm > 0 ? round($totalDistanceKm / ($durationMinutes / 60), 2) : 0;
        $maxAltitude = max(array_column($points, 'altitude'));

        $purposes = [
            'Redovna granična patrola',
            'Nadzor graničnog pojasa',
            'Preventivna kontrola prijelaza',
            'Praćenje kretanja uz granicu',
        ];

        $flight = new Flight([
            'drone_id' => $droneId,
            'user_id' => $pilotId,
            'station_id' => $station->id,
            'flight_date' => $flightDate,
            'duration_minutes' => $durationMinutes,
            'distance_km' => round($totalDistanceKm, 2),
            'max_altitude_m' => $maxAltitude,
            'avg_speed_kmh' => $avgSpeed,
            'location' => str_replace('PGP ', '', $station->name),
            'purpose' => $purposes[array_rand($purposes)],
            'status' => 'completed',
        ]);

        return [$flight, $points];
    }
}
