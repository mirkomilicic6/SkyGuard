<?php

namespace App\Services;

class GpxParser
{
    public function parse(string $absolutePath): array
    {
        $xml = simplexml_load_file($absolutePath);

        // Works with or without XML namespace
        $trackPoints = $xml->xpath('//*[local-name()="trkpt"]');

        if (empty($trackPoints)) {
            return ['points' => [], 'stats' => []];
        }

        $points = [];
        foreach ($trackPoints as $i => $pt) {
            $lat  = (float) $pt['lat'];
            $lon  = (float) $pt['lon'];
            $alt  = isset($pt->ele) ? round((float) $pt->ele, 2) : null;
            $time = null;

            foreach ($pt->children() as $child) {
                if (strtolower($child->getName()) === 'time') {
                    try { $time = new \DateTime((string) $child); } catch (\Exception $e) {}
                }
            }

            // Try to get speed from extensions
            $speed = null;
            $extensions = $pt->xpath('*[local-name()="extensions"]');
            if (!empty($extensions)) {
                foreach ($extensions[0]->children() as $ext) {
                    if (strtolower($ext->getName()) === 'speed') {
                        $speed = round((float) $ext * 3.6, 2); // m/s → km/h
                    }
                }
            }

            $points[] = [
                'lat'   => $lat,
                'lon'   => $lon,
                'alt'   => $alt,
                'time'  => $time,
                'speed' => $speed,
                'order' => $i + 1,
            ];
        }

        // Calculate speed from position+time where not provided
        for ($i = 1; $i < count($points); $i++) {
            if ($points[$i]['speed'] === null
                && $points[$i]['time'] !== null
                && $points[$i - 1]['time'] !== null) {

                $dist    = $this->haversineKm(
                    $points[$i - 1]['lat'], $points[$i - 1]['lon'],
                    $points[$i]['lat'],     $points[$i]['lon']
                );
                $seconds = $points[$i]['time']->getTimestamp() - $points[$i - 1]['time']->getTimestamp();
                $points[$i]['speed'] = $seconds > 0 ? round($dist / ($seconds / 3600), 2) : 0;
            }
        }
        // First point gets same speed as second
        if (count($points) > 1 && $points[0]['speed'] === null) {
            $points[0]['speed'] = $points[1]['speed'];
        }

        return [
            'points' => $points,
            'stats'  => $this->calculateStats($points),
        ];
    }

    private function calculateStats(array $points): array
    {
        if (count($points) < 2) {
            return [];
        }

        $totalDistanceKm = 0;
        for ($i = 1; $i < count($points); $i++) {
            $totalDistanceKm += $this->haversineKm(
                $points[$i - 1]['lat'], $points[$i - 1]['lon'],
                $points[$i]['lat'],     $points[$i]['lon']
            );
        }

        $first = $points[0];
        $last  = $points[count($points) - 1];

        $durationMinutes = null;
        if ($first['time'] && $last['time']) {
            $durationMinutes = round(
                ($last['time']->getTimestamp() - $first['time']->getTimestamp()) / 60,
                1
            );
        }

        $altitudes = array_filter(array_column($points, 'alt'), fn($a) => $a !== null);
        $maxAlt    = !empty($altitudes) ? round(max($altitudes), 1) : null;

        $speeds   = array_filter(array_column($points, 'speed'), fn($s) => $s !== null && $s > 0);
        $avgSpeed = !empty($speeds) ? round(array_sum($speeds) / count($speeds), 1) : null;

        return [
            'duration_minutes' => $durationMinutes,
            'distance_km'      => round($totalDistanceKm, 3),
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
