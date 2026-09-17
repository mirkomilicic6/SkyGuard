<?php

namespace App\Services;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Http;

class RecommendationService
{
    private const DOW_LABELS = [1 => 'nedjeljom', 2 => 'ponedjeljkom', 3 => 'utorkom', 4 => 'srijedom',
        5 => 'četvrtkom', 6 => 'petkom', 7 => 'subotom'];

    /**
     * Four-category surveillance recommendation for DBSCAN hotspot zones
     * (K1, K2...), driven by the trained model's predicted probability
     * — a deliberate, explicit exception to "RecommendationService doesn't
     * talk to ml-service": the model's probability IS the input here, per
     * the academic-pipeline requirement. A zone below the minimum-flights/
     * minutes guardrail is always "insufficient_data"; the model's
     * probability is never allowed to force a reduction on its own.
     */
    public function forDbscanZones(?int $administrationId = null, ?int $stationId = null): Collection
    {
        $base = 'http://127.0.0.1:8001';
        $param = array_filter([
            'administration_id' => $administrationId,
            'station_id'        => $administrationId ? null : $stationId,
        ]);

        try {
            $flightStats = Http::timeout(30)->get("{$base}/zones/flight-stats", $param)->json('zones') ?? [];
            $predictions = Http::timeout(60)->get("{$base}/zone-predictions", $param)->json('zones') ?? [];
        } catch (\Throwable) {
            return collect();
        }

        $statsById = collect($flightStats)->keyBy('id');
        $minFlights = config('surveillance.min_flights_for_zone');
        $minMinutes = config('surveillance.min_minutes_for_zone');

        return collect($predictions)->map(function (array $pred) use ($statsById, $minFlights, $minMinutes) {
            $stats = (array) $statsById->get($pred['id'], []);
            $flightsCount = $stats['flights_count'] ?? 0;
            $totalMinutes = ($stats['total_hours'] ?? 0) * 60;
            $sufficientData = $flightsCount >= $minFlights && $totalMinutes >= $minMinutes;

            if (!$sufficientData || $pred['probability'] === null) {
                $type = 'insufficient_data';
            } elseif ($pred['probability'] >= 70) {
                $type = 'increase';
            } elseif ($pred['probability'] <= 25) {
                // Guardrail already applied above — a low-probability zone
                // only ever reaches "consider_reduction" once it has proven
                // it has enough flights/minutes to trust that low reading.
                $type = 'consider_reduction';
            } else {
                $type = 'maintain';
            }

            if ($type === 'maintain' && ($stats['trend_pct'] ?? 0) >= config('surveillance.trend_escalation_pct')) {
                $type = 'increase';
            }

            $result = array_merge($pred, [
                'flights_count'     => $flightsCount,
                'total_hours'       => $stats['total_hours'] ?? 0,
                'detections_count'  => $stats['detections_count'] ?? 0,
                'rate_per_hour'     => $stats['rate_per_hour'] ?? 0,
                'most_active_dow'   => $stats['most_active_dow'] ?? null,
                'most_active_block' => $this->blockFromIndex($stats['most_active_block'] ?? null),
                'trend_pct'         => $stats['trend_pct'] ?? 0,
                'type'              => $type,
            ]);
            $result['explanation'] = $this->explanationForZone($result, $type);

            return $result;
        });
    }

    private function blockFromIndex(?int $index): ?array
    {
        if ($index === null) {
            return null;
        }
        return config('surveillance.time_blocks')[$index] ?? null;
    }

    private function explanationForZone(array $z, string $type): string
    {
        $id    = $z['id'];
        $day   = self::DOW_LABELS[$z['most_active_dow']] ?? null;
        $block = $z['most_active_block']['label'] ?? null;
        $when  = $day && $block ? "{$day} između {$block}" : 'u bilo koje doba';

        $trendWord = $z['trend_pct'] > 0 ? 'više' : ($z['trend_pct'] < 0 ? 'manje' : 'jednako');
        $trendPart = "U posljednjih " . config('surveillance.recent_window_days') . " dana zabilježeno je "
            . "{$z['detections_count']} detekcija, " . abs($z['trend_pct']) . "% {$trendWord} nego u prethodnom razdoblju.";

        $probPart = $z['probability'] !== null
            ? "Model procjenjuje {$z['probability']}% vjerojatnosti detekcije. "
            : '';

        return match ($type) {
            'insufficient_data'  => "Nedovoljno podataka za zonu {$id} — potrebno je minimalno "
                . config('surveillance.min_flights_for_zone') . ' letova i '
                . config('surveillance.min_minutes_for_zone') . ' minuta nadzora za pouzdanu preporuku.',
            'increase'           => "Preporučuje se pojačati nadzor zone {$id} {$when}. {$probPart}{$trendPart}",
            'consider_reduction' => "Zona {$id} pokazuje nisku procijenjenu vjerojatnost detekcije u odnosu na uloženo vrijeme nadzora — razmotriti smanjenje. {$probPart}{$trendPart}",
            default              => "Zadržati trenutnu razinu nadzora zone {$id}. {$probPart}{$trendPart}",
        };
    }
}
