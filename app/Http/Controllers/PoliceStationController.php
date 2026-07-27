<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\StationScoped;
use App\Models\BorderPoliceStation;
use App\Models\PoliceAdministration;
use Illuminate\Http\Request;

class PoliceStationController extends Controller
{
    use StationScoped;

    public function index()
    {
        $administrations = PoliceAdministration::with(['stations' => function ($q) {
            $q->withCount(['users', 'drones'])->orderBy('name');
        }])->orderBy('name')->get();

        return view('police-stations.index', compact('administrations'));
    }

    public function create()
    {
        $this->denyNonSuperAdmin();

        $administrations = PoliceAdministration::orderBy('name')->get();

        return view('police-stations.create', compact('administrations'));
    }

    public function store(Request $request)
    {
        $this->denyNonSuperAdmin();

        $raw = $request->input('boundary_raw', '');
        $boundary = $raw !== '' ? json_decode($raw, true) : null;

        if ($boundary !== null && (!is_array($boundary) || count($boundary) < 3)) {
            return back()->withErrors(['boundary_raw' => __('ui.boundary.need_three_points')])->withInput();
        }

        $request->merge(['boundary' => $boundary]);

        $validated = $request->validate([
            'name'                     => 'required|string|max:255',
            'police_administration_id' => 'required|exists:police_administrations,id',
            'latitude'                 => 'required|numeric|between:-90,90',
            'longitude'                => 'required|numeric|between:-180,180',
            'boundary'                 => 'nullable|array|min:3',
            'boundary.*'               => 'array',
            'boundary.*.0'             => 'required|numeric|between:-90,90',
            'boundary.*.1'             => 'required|numeric|between:-180,180',
        ]);

        $station = BorderPoliceStation::create([
            'name'                     => $validated['name'],
            'police_administration_id' => $validated['police_administration_id'],
            'latitude'                 => $validated['latitude'],
            'longitude'                => $validated['longitude'],
            'boundary'                 => $validated['boundary'] ?? null,
        ]);

        return redirect()->route('police-stations.index')
            ->with('success', __('ui.police_stations.created', ['name' => $station->name]));
    }

    public function edit(BorderPoliceStation $station)
    {
        $this->denyNonSuperAdmin();

        $administrations = PoliceAdministration::orderBy('name')->get();

        return view('police-stations.edit', compact('station', 'administrations'));
    }

    public function update(Request $request, BorderPoliceStation $station)
    {
        $this->denyNonSuperAdmin();

        $validated = $request->validate([
            'name'                     => 'required|string|max:255',
            'police_administration_id' => 'required|exists:police_administrations,id',
        ]);

        $station->update($validated);

        return redirect()->route('police-stations.index')
            ->with('success', __('ui.police_stations.updated', ['name' => $station->name]));
    }

    public function destroy(BorderPoliceStation $station)
    {
        $this->denyNonSuperAdmin();

        if ($station->users()->exists() || $station->drones()->exists()) {
            return back()->with('error', __('ui.police_stations.delete_blocked', ['name' => $station->name]));
        }

        $station->delete();

        return redirect()->route('police-stations.index')
            ->with('success', __('ui.police_stations.deleted', ['name' => $station->name]));
    }
}
