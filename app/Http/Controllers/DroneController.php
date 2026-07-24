<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\StationScoped;
use App\Models\Drone;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class DroneController extends Controller
{
    use StationScoped;

    public function index()
    {
        $drones = $this->droneScope()
            ->with('station.administration')
            ->withCount('flights')
            ->latest()
            ->paginate(10);

        $showStationColumn = $this->showStationColumn();

        return view('drones.index', compact('drones', 'showStationColumn'));
    }

    public function create()
    {
        $this->denyViewer();
        $pilots = $this->stationPilots();
        [$pilotAdministrations, $pilotStations] = $this->pilotFilterData($pilots);
        return view('drones.create', compact('pilots', 'pilotAdministrations', 'pilotStations'));
    }

    public function store(Request $request)
    {
        $this->denyViewer();

        $validated = $request->validate([
            'name'          => 'required|string|max:255',
            'serial_number' => 'required|string|unique:drones',
            'model'         => 'required|string|max:255',
            'manufacturer'  => 'nullable|string|max:255',
            'purchase_date' => 'nullable|date',
            'status'        => 'required|in:active,in_maintenance,retired',
            'photo'         => 'nullable|image|max:2048',
            'notes'         => 'nullable|string',
            'pilots'        => 'required|array|min:1',
            'pilots.*'      => 'exists:users,id',
        ]);

        if ($request->hasFile('photo')) {
            $validated['photo'] = $request->file('photo')->store('drones', 'public');
        }

        $validated['station_id'] = auth()->user()->station_id;

        $drone = Drone::create($validated);
        $drone->pilots()->sync($request->pilots);

        return redirect()->route('drones.index')->with('success', 'Drone created successfully.');
    }

    public function show(Drone $drone)
    {
        $this->authorizeAccess($drone);

        $drone->load(['flights.pilot', 'maintenanceLogs', 'pilots']);
        $recentFlights = $drone->flights()->latest('flight_date')->take(5)->get();

        return view('drones.show', compact('drone', 'recentFlights'));
    }

    public function edit(Drone $drone)
    {
        $this->denyViewer();
        $this->authorizeAccess($drone);

        $pilots = $this->stationPilots();
        $assignedPilots = $drone->pilots->pluck('id')->toArray();
        [$pilotAdministrations, $pilotStations] = $this->pilotFilterData($pilots);
        return view('drones.edit', compact('drone', 'pilots', 'assignedPilots', 'pilotAdministrations', 'pilotStations'));
    }

    public function update(Request $request, Drone $drone)
    {
        $this->denyViewer();
        $this->authorizeAccess($drone);

        $validated = $request->validate([
            'name'          => 'required|string|max:255',
            'serial_number' => 'required|string|unique:drones,serial_number,' . $drone->id,
            'model'         => 'required|string|max:255',
            'manufacturer'  => 'nullable|string|max:255',
            'purchase_date' => 'nullable|date',
            'status'        => 'required|in:active,in_maintenance,retired',
            'photo'         => 'nullable|image|max:2048',
            'notes'         => 'nullable|string',
            'pilots'        => 'required|array|min:1',
            'pilots.*'      => 'exists:users,id',
        ]);

        if ($request->hasFile('photo')) {
            if ($drone->photo) Storage::disk('public')->delete($drone->photo);
            $validated['photo'] = $request->file('photo')->store('drones', 'public');
        }

        $drone->update($validated);
        $drone->pilots()->sync($request->pilots);

        return redirect()->route('drones.show', $drone)->with('success', 'Drone updated successfully.');
    }

    public function destroy(Drone $drone)
    {
        $this->denyViewer();
        $this->authorizeAccess($drone);

        if ($drone->photo) Storage::disk('public')->delete($drone->photo);
        $drone->delete();
        return redirect()->route('drones.index')->with('success', 'Drone deleted.');
    }

    private function droneScope()
    {
        $user = auth()->user();

        if ($user->hasRole(['admin', 'viewer'])) {
            return $this->applyStationScope(Drone::query());
        }

        return $user->drones();
    }

    private function authorizeAccess(Drone $drone): void
    {
        $user = auth()->user();

        if ($user->hasRole(['admin', 'viewer'])) {
            if (!$this->inUserStation($drone->station_id)) {
                abort(403);
            }
            return;
        }

        if (!$user->drones->contains($drone->id)) {
            abort(403, 'You are not assigned to this drone.');
        }
    }

    private function stationPilots()
    {
        return $this->applyStationScope(User::role('pilot'))
            ->with('station.administration')
            ->orderBy('name')
            ->get();
    }

    /**
     * Build the (administration, station) option lists used to filter the
     * pilot-assignment select on the drone create/edit forms.
     */
    private function pilotFilterData($pilots)
    {
        $stations = $pilots->pluck('station')->filter()->unique('id')->sortBy('name')->values();
        $administrations = $stations->pluck('administration')->filter()->unique('id')->sortBy('name')->values();

        return [$administrations, $stations];
    }
}
