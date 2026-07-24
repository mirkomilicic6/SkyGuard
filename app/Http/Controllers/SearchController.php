<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\StationScoped;
use App\Models\BorderPoliceStation;
use App\Models\Drone;
use App\Models\PoliceAdministration;
use App\Models\User;
use Illuminate\Http\Request;

class SearchController extends Controller
{
    use StationScoped;

    /**
     * Live global search for admins — drones, pilots, stations, administrations.
     * Scoped to the admin's own administration; unrestricted for the super admin.
     */
    public function index(Request $request)
    {
        $q = trim((string) $request->input('q', ''));

        if (mb_strlen($q) < 2) {
            return response()->json(['drones' => [], 'pilots' => [], 'stations' => [], 'administrations' => []]);
        }

        $stationIds = $this->scopedStationIds();
        $administrationId = auth()->user()->station?->police_administration_id;

        $drones = Drone::query()
            ->when($stationIds !== null, fn ($query) => $query->whereIn('station_id', $stationIds))
            ->where(fn ($query) => $query->where('name', 'like', "%{$q}%")->orWhere('serial_number', 'like', "%{$q}%"))
            ->limit(6)
            ->get(['id', 'name', 'serial_number'])
            ->map(fn ($d) => [
                'label' => "{$d->name} ({$d->serial_number})",
                'url'   => route('drones.show', $d),
            ]);

        $pilots = User::role('pilot')
            ->when($stationIds !== null, fn ($query) => $query->whereIn('station_id', $stationIds))
            ->where(fn ($query) => $query->where('name', 'like', "%{$q}%")->orWhere('email', 'like', "%{$q}%"))
            ->limit(6)
            ->get(['id', 'name', 'email'])
            ->map(fn ($p) => [
                'label' => "{$p->name} — {$p->email}",
                'url'   => route('users.show', $p),
            ]);

        $stationsQuery = BorderPoliceStation::query();
        if ($stationIds !== null) {
            $stationsQuery->whereIn('id', $stationIds);
        }
        $stations = $stationsQuery->where('name', 'like', "%{$q}%")
            ->limit(6)
            ->get(['id', 'name'])
            ->map(fn ($s) => [
                'label' => $s->name,
                'url'   => route('cameras.index', ['station_id' => $s->id]),
            ]);

        $administrationsQuery = PoliceAdministration::query();
        if ($stationIds !== null) {
            $administrationsQuery->where('id', $administrationId);
        }
        $administrations = $administrationsQuery->where('name', 'like', "%{$q}%")
            ->limit(6)
            ->get(['id', 'name'])
            ->map(fn ($a) => [
                'label' => $a->name,
                'url'   => route('users.index', ['administration_id' => $a->id]),
            ]);

        return response()->json([
            'drones'          => $drones,
            'pilots'          => $pilots,
            'stations'        => $stations,
            'administrations' => $administrations,
        ]);
    }
}
