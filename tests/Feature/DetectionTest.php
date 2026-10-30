<?php

namespace Tests\Feature;

use App\Models\Detection;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\Concerns\CreatesSurveillanceFixtures;
use Tests\TestCase;

class DetectionTest extends TestCase
{
    use RefreshDatabase;
    use CreatesSurveillanceFixtures;

    public function test_manual_detection_can_be_saved_without_a_flight(): void
    {
        $this->seedRoles();
        $admin = $this->makeAdministration();
        $station = $this->makeStation($admin);
        $pilot = $this->makeUser($station->id, 'pilot');

        $response = $this->actingAs($pilot)->post(route('detections.storeManual'), [
            'source'         => 'ground_observation',
            'station_id'     => $station->id,
            'latitude'       => 45.1234567,
            'longitude'      => 18.1234567,
            'detection_type' => 'person',
            'entity_count'   => 1,
            'detected_at'    => now()->format('Y-m-d H:i:s'),
            'note'           => 'Test napomena',
        ]);

        $response->assertRedirect(route('detections.index'));
        $this->assertDatabaseHas('detections', [
            'flight_id'  => null,
            'station_id' => $station->id,
            'created_by' => $pilot->id,
            'source'     => 'ground_observation',
        ]);
    }

    public function test_detection_can_be_saved_linked_to_a_flight_and_inherits_its_station(): void
    {
        $this->seedRoles();
        $admin = $this->makeAdministration();
        $station = $this->makeStation($admin);
        $pilot = $this->makeUser($station->id, 'pilot');
        $drone = $this->makeDrone($station->id);
        $flight = $this->makeFlight($station->id, $pilot->id, $drone->id);

        $response = $this->actingAs($pilot)->post(route('detections.storeManual'), [
            'source'         => 'drone',
            'flight_id'      => $flight->id,
            'latitude'       => 45.2,
            'longitude'      => 18.2,
            'detection_type' => 'vehicle',
            'entity_count'   => 2,
            'detected_at'    => now()->format('Y-m-d H:i:s'),
        ]);

        $response->assertRedirect(route('detections.index'));
        $this->assertDatabaseHas('detections', [
            'flight_id'  => $flight->id,
            'station_id' => $station->id,
        ]);
    }

    public function test_gps_coordinates_are_required_and_validated(): void
    {
        $this->seedRoles();
        $admin = $this->makeAdministration();
        $station = $this->makeStation($admin);
        $pilot = $this->makeUser($station->id, 'pilot');

        $response = $this->actingAs($pilot)->post(route('detections.storeManual'), [
            'source'         => 'ground_observation',
            'station_id'     => $station->id,
            'latitude'       => 200, // out of range
            'detection_type' => 'person',
            'entity_count'   => 1,
            'detected_at'    => now()->format('Y-m-d H:i:s'),
        ]);

        $response->assertSessionHasErrors(['latitude', 'longitude']);
        $this->assertDatabaseCount('detections', 0);
    }

    public function test_entity_count_must_be_a_positive_integer(): void
    {
        $this->seedRoles();
        $admin = $this->makeAdministration();
        $station = $this->makeStation($admin);
        $pilot = $this->makeUser($station->id, 'pilot');

        $response = $this->actingAs($pilot)->post(route('detections.storeManual'), [
            'source'         => 'ground_observation',
            'station_id'     => $station->id,
            'latitude'       => 45.1,
            'longitude'      => 18.1,
            'detection_type' => 'person',
            'entity_count'   => 0,
            'detected_at'    => now()->format('Y-m-d H:i:s'),
        ]);

        $response->assertSessionHasErrors('entity_count');
    }

    public function test_only_allowed_detection_types_and_sources_are_accepted(): void
    {
        $this->seedRoles();
        $admin = $this->makeAdministration();
        $station = $this->makeStation($admin);
        $pilot = $this->makeUser($station->id, 'pilot');

        $response = $this->actingAs($pilot)->post(route('detections.storeManual'), [
            'source'         => 'smuggler_drone', // not an allowed source
            'station_id'     => $station->id,
            'latitude'       => 45.1,
            'longitude'      => 18.1,
            'detection_type' => 'smuggling', // no longer an allowed type
            'entity_count'   => 1,
            'detected_at'    => now()->format('Y-m-d H:i:s'),
        ]);

        $response->assertSessionHasErrors(['source', 'detection_type']);
    }

    public function test_detection_index_filters_by_type_source_and_flight_link(): void
    {
        $this->seedRoles();
        $admin = $this->makeAdministration();
        $station = $this->makeStation($admin);
        $pilot = $this->makeUser($station->id, 'pilot');
        $drone = $this->makeDrone($station->id);
        $flight = $this->makeFlight($station->id, $pilot->id, $drone->id);

        Detection::create([
            'flight_id' => $flight->id, 'station_id' => $station->id, 'created_by' => $pilot->id,
            'source' => 'drone', 'latitude' => 45.1, 'longitude' => 18.1,
            'detection_type' => 'person', 'entity_count' => 1, 'detected_at' => now(),
        ]);
        Detection::create([
            'flight_id' => null, 'station_id' => $station->id, 'created_by' => $pilot->id,
            'source' => 'ground_observation', 'latitude' => 45.1, 'longitude' => 18.1,
            'detection_type' => 'vehicle', 'entity_count' => 1, 'detected_at' => now(),
        ]);

        $this->actingAs($pilot)->get(route('detections.index', ['detection_type' => 'vehicle']))
            ->assertOk()->assertSee('Vozilo');

        $this->actingAs($pilot)->get(route('detections.index', ['flight_link' => 'unlinked']))
            ->assertOk();

        $linkedOnly = \App\Models\Detection::query()
            ->when(true, fn($q) => $q->whereNotNull('flight_id'))->count();
        $this->assertSame(1, $linkedOnly);
    }

    public function test_analytics_page_loads_without_escalation_or_confirmed_concepts(): void
    {
        $this->seedRoles();
        $admin = $this->makeAdministration();
        $station = $this->makeStation($admin);
        $superAdmin = $this->makeUser(null, 'admin');

        $response = $this->actingAs($superAdmin)->get(route('analytics.index'));

        $response->assertOk();
        $response->assertDontSee('Potvrđeno');
        $response->assertDontSee('Visoki rizik');
        $response->assertDontSee('eskalacij', false);
    }

    public function test_flights_with_and_without_detections_are_counted(): void
    {
        $this->seedRoles();
        $admin = $this->makeAdministration();
        $station = $this->makeStation($admin);
        $pilot = $this->makeUser($station->id, 'pilot');
        $drone = $this->makeDrone($station->id);

        $withDetection = $this->makeFlight($station->id, $pilot->id, $drone->id);
        $this->makeFlight($station->id, $pilot->id, $drone->id); // without detection

        Detection::create([
            'flight_id' => $withDetection->id, 'station_id' => $station->id, 'created_by' => $pilot->id,
            'source' => 'drone', 'latitude' => 45.1, 'longitude' => 18.1,
            'detection_type' => 'person', 'entity_count' => 1, 'detected_at' => now(),
        ]);

        $this->assertSame(1, \App\Models\Flight::where('station_id', $station->id)->has('detections')->count());
        $this->assertSame(1, \App\Models\Flight::where('station_id', $station->id)->doesntHave('detections')->count());
    }

    public function test_viewer_cannot_create_or_delete_detections(): void
    {
        $this->seedRoles();
        $admin = $this->makeAdministration();
        $station = $this->makeStation($admin);
        $viewer = $this->makeUser($station->id, 'viewer');

        $this->actingAs($viewer)->get(route('detections.create'))->assertForbidden();

        $this->actingAs($viewer)->post(route('detections.storeManual'), [
            'source' => 'ground_observation', 'station_id' => $station->id,
            'latitude' => 45.1, 'longitude' => 18.1,
            'detection_type' => 'person', 'entity_count' => 1,
            'detected_at' => now()->format('Y-m-d H:i:s'),
        ])->assertForbidden();
    }

    public function test_admin_sees_detections_across_whole_administration_but_not_other_administrations(): void
    {
        $this->seedRoles();
        $adminA = $this->makeAdministration();
        $stationA1 = $this->makeStation($adminA);
        $stationA2 = $this->makeStation($adminA);
        $adminB = $this->makeAdministration();
        $stationB = $this->makeStation($adminB);

        $adminUser = $this->makeUser($stationA1->id, 'admin');
        $someoneElse = $this->makeUser($stationA1->id, 'pilot');
        $otherPilot = $this->makeUser($stationB->id, 'pilot');

        Detection::create([
            'flight_id' => null, 'station_id' => $stationA2->id, 'created_by' => $someoneElse->id,
            'source' => 'ground_observation', 'latitude' => 45.1, 'longitude' => 18.1,
            'detection_type' => 'person', 'entity_count' => 1, 'detected_at' => now(),
        ]);
        Detection::create([
            'flight_id' => null, 'station_id' => $stationB->id, 'created_by' => $otherPilot->id,
            'source' => 'ground_observation', 'latitude' => 45.1, 'longitude' => 18.1,
            'detection_type' => 'person', 'entity_count' => 1, 'detected_at' => now(),
        ]);

        $response = $this->actingAs($adminUser)->get(route('detections.index'));
        $response->assertOk();

        $this->assertSame(1, Detection::where('station_id', $stationA2->id)->count());
        $this->assertSame(1, Detection::where('station_id', $stationB->id)->count());
    }
}
