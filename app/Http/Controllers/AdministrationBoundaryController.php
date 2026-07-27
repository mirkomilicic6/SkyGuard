<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\StationScoped;
use App\Models\PoliceAdministration;
use Illuminate\Http\Request;

class AdministrationBoundaryController extends Controller
{
    use StationScoped;

    public function edit(PoliceAdministration $administration)
    {
        $this->authorizeAdministration($administration);

        $administration->load(['stations' => function ($q) {
            $q->select('id', 'police_administration_id', 'name', 'latitude', 'longitude', 'boundary');
        }]);

        // Every other administration's boundary, shown as a read-only reference
        // layer so the admin can draw their own line up to the neighbour's.
        $neighborAdministrations = PoliceAdministration::where('id', '!=', $administration->id)
            ->whereNotNull('boundary')
            ->get(['id', 'name', 'boundary']);

        return view('police-administrations.boundary', compact('administration', 'neighborAdministrations'));
    }

    public function update(Request $request, PoliceAdministration $administration)
    {
        $this->authorizeAdministration($administration);

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

        $administration->update(['boundary' => $validated['boundary'] ?? null]);

        return redirect()->route('administration-boundary.edit', $administration)
            ->with('success', __('ui.boundary.saved'));
    }

    private function authorizeAdministration(PoliceAdministration $administration): void
    {
        if (!$this->inUserAdministration($administration->id)) {
            abort(403);
        }
    }
}
