<?php

namespace App\Services;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Http;

class RecommendationService
{
    private const DOW_LABELS = [1 => 'nedjeljom', 2 => 'ponedjeljkom', 3 => 'utorkom', 4 => 'srijedom',
        5 => 'četvrtkom', 6 => 'petkom', 7 => 'subotom'];

    // Preporuka nadzora za DBSCAN žarišta (pojačati, zadržati, razmotriti smanjenje, premalo podataka).
    // Temelji se na indeksu rizika iz servisa za strojno učenje.
    // Žarište bez dovoljno letova i minuta nadzora uvijek dobiva "premalo podataka".
    public function forDbscanZones(?int $administrationId = null, ?int $stationId = null,
                                   ?int $dow = null, ?int $block = null): Collection
    {
        $base = config('services.ml.url');
        $param = array_filter([
            'administration_id' => $administrationId,
            'station_id'        => $administrationId ? null : $stationId,
        ]);
        // dan i blok mogu biti 0 (ponedjeljak, 00-04 h), pa se provjerava null, a ne prazna vrijednost
        $predictionParam = $param + array_filter(['dow' => $dow, 'block' => $block], fn ($v) => $v !== null);

        try {
            $flightStats = Http::timeout(60)->get("{$base}/zones/flight-stats", $param)->json('zones') ?? [];
            $predictions = Http::timeout(240)->get("{$base}/zone-predictions", $predictionParam)->json('zones') ?? [];
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
                // smanjenje je moguće samo ako žarište ima dovoljno letova i minuta nadzora (provjereno iznad)
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
                'recent_count'      => $stats['recent_count'] ?? 0,
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
        $trendPart = "U posljednjih " . config('surveillance.recent_window_days') . " dana "
            . $this->detectionsPhrase((int) $z['recent_count']) . ', '
            . abs($z['trend_pct']) . "% {$trendWord} nego u prethodnom razdoblju.";

        $probPart = $z['probability'] !== null
            ? "Indeks rizika prema modelu: {$z['probability']}/100. "
            : '';

        return match ($type) {
            'insufficient_data'  => "Nedovoljno podataka za zonu {$id} — potrebno je minimalno "
                . config('surveillance.min_flights_for_zone') . ' letova i '
                . config('surveillance.min_minutes_for_zone') . ' minuta nadzora za pouzdanu preporuku.',
            'increase'           => "Preporučuje se pojačati nadzor zone {$id} {$when}. {$probPart}{$trendPart}",
            'consider_reduction' => "Zona {$id} ima nizak indeks rizika u odnosu na uloženo vrijeme nadzora — razmotriti smanjenje. {$probPart}{$trendPart}",
            default              => "Zadržati trenutnu razinu nadzora zone {$id}. {$probPart}{$trendPart}",
        };
    }

    // Ispravan oblik riječi uz broj: 1 detekcija, 2-4 detekcije, 5 i više detekcija
    private function detectionsPhrase(int $n): string
    {
        $mod10 = $n % 10;
        $mod100 = $n % 100;

        if ($mod10 === 1 && $mod100 !== 11) {
            return "zabilježena je {$n} detekcija";
        }
        if ($mod10 >= 2 && $mod10 <= 4 && ($mod100 < 12 || $mod100 > 14)) {
            return "zabilježene su {$n} detekcije";
        }

        return "zabilježeno je {$n} detekcija";
    }
}
