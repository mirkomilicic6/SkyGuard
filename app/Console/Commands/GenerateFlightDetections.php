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

    /** type => [min count, max count, relative weight, base escalation range] */
    private const TYPES = [
        'person'    => ['min' => 1, 'max' => 1, 'weight' => 35, 'escalation' => [0, 1]],
        'group'     => ['min' => 2, 'max' => 10, 'weight' => 25, 'escalation' => [1, 2]],
        'vehicle'   => ['min' => 1, 'max' => 3, 'weight' => 25, 'escalation' => [0, 2]],
        'smuggling' => ['min' => 1, 'max' => 6, 'weight' => 8, 'escalation' => [2, 3]],
        'other'     => ['min' => 1, 'max' => 3, 'weight' => 7, 'escalation' => [0, 1]],
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
                'flight_id' => $flight->id,
                'user_id' => $flight->user_id,
                'source' => 'drone',
                'latitude' => $point->latitude,
                'longitude' => $point->longitude,
                'type' => $type,
                'count' => $count,
                'detected_at' => $detectedAt,
                'confirmed' => mt_rand(1, 100) <= 70,
                'escalation_level' => mt_rand($range['escalation'][0], $range['escalation'][1]),
                'heading_deg' => mt_rand(0, 359),
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
