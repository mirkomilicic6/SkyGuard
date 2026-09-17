<?php

namespace App\Console\Commands;

use App\Models\Detection;
use App\Models\Flight;
use Illuminate\Console\Command;

class GenerateFlightDetections extends Command
{
    protected $signature = 'detections:generate-for-border-flights
        {--from-id=537 : Lowest flight id to consider}
        {--probability=30 : Chance (%) that a given flight has a detection}';

    protected $description = 'Add random detections to a subset of the generated border patrol flights';

    /** type => [min count, max count, relative weight] */
    private const TYPES = [
        'person'  => ['min' => 1, 'max' => 1, 'weight' => 40],
        'group'   => ['min' => 2, 'max' => 10, 'weight' => 28],
        'vehicle' => ['min' => 1, 'max' => 3, 'weight' => 25],
        'other'   => ['min' => 1, 'max' => 3, 'weight' => 7],
    ];

    public function handle(): int
    {
        $fromId = (int) $this->option('from-id');
        $probability = (int) $this->option('probability');

        $flights = Flight::where('id', '>=', $fromId)
            ->with('gpxPoints')
            ->get();

        $created = 0;

        foreach ($flights as $flight) {
            if (mt_rand(1, 100) > $probability) {
                continue;
            }

            $point = $flight->gpxPoints->isNotEmpty()
                ? $flight->gpxPoints->random()
                : null;

            if (!$point) {
                continue;
            }

            $type = $this->weightedType();
            $range = self::TYPES[$type];
            $count = min(10, mt_rand($range['min'], $range['max']));

            $detectedAt = (clone $flight->flight_date)->addMinutes(mt_rand(0, max(1, $flight->duration_minutes - 1)));

            Detection::create([
                'flight_id'      => $flight->id,
                'station_id'     => $flight->station_id,
                'created_by'     => $flight->user_id,
                'source'         => 'drone',
                'latitude'       => $point->latitude,
                'longitude'      => $point->longitude,
                'detection_type' => $type,
                'entity_count'   => $count,
                'detected_at'    => $detectedAt,
            ]);

            $created++;
        }

        $this->info("Gotovo. Dodano {$created} detekcija na {$flights->count()} letova (vjerojatnost {$probability}%).");

        return self::SUCCESS;
    }

    private function weightedType(): string
    {
        $totalWeight = array_sum(array_column(self::TYPES, 'weight'));
        $roll = mt_rand(1, $totalWeight);
        $cumulative = 0;

        foreach (self::TYPES as $type => $config) {
            $cumulative += $config['weight'];
            if ($roll <= $cumulative) {
                return $type;
            }
        }

        return 'person';
    }
}
