<?php

namespace Tests\Feature\Concerns;

use App\Models\BorderPoliceStation;
use App\Models\Drone;
use App\Models\Flight;
use App\Models\PoliceAdministration;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Support\Facades\Hash;

trait CreatesSurveillanceFixtures
{
    protected function seedRoles(): void
    {
        $this->seed(RolesAndPermissionsSeeder::class);
    }

    protected function makeAdministration(): PoliceAdministration
    {
        return PoliceAdministration::create(['name' => 'Test uprava ' . uniqid()]);
    }

    protected function makeStation(PoliceAdministration $administration, array $overrides = []): BorderPoliceStation
    {
        return BorderPoliceStation::create(array_merge([
            'police_administration_id' => $administration->id,
            'name'                     => 'Test postaja ' . uniqid(),
            'latitude'                 => 45.1,
            'longitude'                => 18.0,
        ], $overrides));
    }

    protected function makeUser(?int $stationId, string $role, array $overrides = []): User
    {
        $user = User::create(array_merge([
            'name'     => 'Test ' . $role . ' ' . uniqid(),
            'email'    => uniqid($role . '_') . '@example.test',
            'password' => Hash::make('password'),
            'station_id' => $stationId,
        ], $overrides));
        $user->assignRole($role);

        return $user;
    }

    protected function makeDrone(?int $stationId = null): Drone
    {
        return Drone::create([
            'name'          => 'Test dron ' . uniqid(),
            'serial_number' => 'SN-' . uniqid(),
            'model'         => 'Test model',
            'station_id'    => $stationId,
        ]);
    }

    protected function makeFlight(int $stationId, int $userId, int $droneId, array $overrides = []): Flight
    {
        return Flight::create(array_merge([
            'drone_id'         => $droneId,
            'user_id'          => $userId,
            'station_id'       => $stationId,
            'flight_date'      => now()->subDays(2),
            'duration_minutes' => 30,
            'status'           => 'completed',
        ], $overrides));
    }
}
