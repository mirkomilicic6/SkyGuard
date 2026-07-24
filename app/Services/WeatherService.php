<?php

namespace App\Services;

use App\Models\BorderPoliceStation;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class WeatherService
{
    /**
     * Current weather + flight-suitability rating for a station, or null if the
     * station has no coordinates or the weather API is unreachable.
     */
    public function forStation(?BorderPoliceStation $station): ?array
    {
        if (!$station || $station->latitude === null || $station->longitude === null) {
            return null;
        }

        return Cache::remember(
            "station-weather-{$station->id}",
            now()->addMinutes(20),
            fn () => $this->fetch((float) $station->latitude, (float) $station->longitude)
        );
    }

    private function fetch(float $lat, float $lon): ?array
    {
        try {
            $response = Http::timeout(6)->get('https://api.open-meteo.com/v1/forecast', [
                'latitude'  => $lat,
                'longitude' => $lon,
                'current'   => 'temperature_2m,precipitation,weather_code,wind_speed_10m,wind_gusts_10m',
                'timezone'  => 'auto',
            ]);

            if (!$response->successful()) {
                return null;
            }

            $current = $response->json('current');
            if (!$current) {
                return null;
            }

            $windSpeed = (float) ($current['wind_speed_10m'] ?? 0);
            $windGusts = (float) ($current['wind_gusts_10m'] ?? 0);
            $precip    = (float) ($current['precipitation'] ?? 0);
            $code      = (int) ($current['weather_code'] ?? 0);

            return [
                'temperature'   => round((float) ($current['temperature_2m'] ?? 0)),
                'wind_speed'    => round($windSpeed),
                'wind_gusts'    => round($windGusts),
                'precipitation' => $precip,
                'description'   => $this->describe($code),
                'icon'          => $this->icon($code),
                'level'         => $this->rate($windSpeed, $windGusts, $precip, $code),
            ];
        } catch (\Throwable $e) {
            Log::warning('Weather lookup failed', ['error' => $e->getMessage()]);
            return null;
        }
    }

    private function rate(float $windSpeed, float $windGusts, float $precip, int $code): string
    {
        $heavyRain = $precip >= 1.0 || in_array($code, [65, 67, 82, 95, 96, 99], true);

        if ($heavyRain || $windGusts >= 45) {
            return 'red';
        }

        $lightRain = $precip > 0 || in_array($code, [51, 53, 55, 61, 63, 71, 73, 75, 77, 80, 81], true);

        if ($lightRain || $windSpeed >= 20 || $windGusts >= 30) {
            return 'yellow';
        }

        return 'green';
    }

    private function describe(int $code): string
    {
        return match (true) {
            $code === 0 => 'Vedro',
            in_array($code, [1, 2], true) => 'Djelomično oblačno',
            $code === 3 => 'Oblačno',
            in_array($code, [45, 48], true) => 'Magla',
            in_array($code, [51, 53, 55], true) => 'Rosulja',
            in_array($code, [61, 63, 65], true) => 'Kiša',
            in_array($code, [66, 67], true) => 'Ledena kiša',
            in_array($code, [71, 73, 75, 77], true) => 'Snijeg',
            in_array($code, [80, 81, 82], true) => 'Pljuskovi',
            in_array($code, [95, 96, 99], true) => 'Grmljavinsko nevrijeme',
            default => 'Nepoznato',
        };
    }

    private function icon(int $code): string
    {
        return match (true) {
            $code === 0 => 'fa-sun',
            in_array($code, [1, 2], true) => 'fa-cloud-sun',
            $code === 3 => 'fa-cloud',
            in_array($code, [45, 48], true) => 'fa-smog',
            in_array($code, [51, 53, 55, 61, 63, 65, 66, 67, 80, 81, 82], true) => 'fa-cloud-showers-heavy',
            in_array($code, [71, 73, 75, 77], true) => 'fa-snowflake',
            in_array($code, [95, 96, 99], true) => 'fa-bolt',
            default => 'fa-cloud',
        };
    }
}
