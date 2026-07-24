<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\StationScoped;
use App\Models\BorderPoliceStation;
use Illuminate\Http\Request;

class StationBoundaryController extends Controller
{
    use StationScoped;

    public function index()
    {
        $stationIds = $this->scopedStationIds();

        $stations = ($stationIds === null
            ? BorderPoliceStation::query()
            : BorderPoliceStation::whereIn('id', $stationIds)
        )->with('administration')->orderBy('name')->get();

        return view('station-boundary.index', compact('stations'));
    }

    public function edit(BorderPoliceStation $station)
    {
        $this->authorizeStation($station);

        // Every other station's boundary, shown as a read-only reference layer
        // so the admin/viewer can draw their own line up to the neighbour's.
        $neighborStations = BorderPoliceStation::where('id', '!=', $station->id)
            ->whereNotNull('boundary')
            ->get(['id', 'name', 'boundary']);

        return view('station-boundary.edit', compact('station', 'neighborStations'));
    }

    public function update(Request $request, BorderPoliceStation $station)
    {
        $this->authorizeStation($station);

        $raw = $request->input('boundary_raw', '');
        $boundary = $raw !== '' ? json_decode($raw, true) : null;

        if ($boundary !== null && (!is_array($boundary) || count($boundary) < 3)) {
            return back()->withErrors(['boundary_raw' => __('ui.boundary.need_three_points')]);
        }

        $request->merge(['boundary' => $boundary]);

        $validated = $request->validate([
            'boundary'     => 'nullable|array|min:3',
            'boundary.*'   => 'array',
            'boundary.*.0' => 'required|numeric|between:-90,90',
            'boundary.*.1' => 'required|numeric|between:-180,180',
        ]);

        $station->update(['boundary' => $validated['boundary'] ?? null]);

        return redirect()->route('station-boundary.edit', $station)
            ->with('success', __('ui.boundary.saved'));
    }

    private function authorizeStation(BorderPoliceStation $station): void
    {
        if (!$this->inUserStation($station->id)) {
            abort(403);
        }
    }
}
