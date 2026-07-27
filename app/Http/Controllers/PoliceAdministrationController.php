<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\StationScoped;
use App\Models\PoliceAdministration;
use Illuminate\Http\Request;

class PoliceAdministrationController extends Controller
{
    use StationScoped;

    public function index()
    {
        $administrations = PoliceAdministration::withCount('stations')
            ->with('stations:id,police_administration_id,name,latitude,longitude,boundary')
            ->orderBy('name')
            ->get();

        return view('police-administrations.index', compact('administrations'));
    }

    public function create()
    {
        $this->denyNonSuperAdmin();

        return view('police-administrations.create');
    }

    public function store(Request $request)
    {
        $this->denyNonSuperAdmin();

        $validated = $request->validate([
            'name' => 'required|string|max:255|unique:police_administrations,name',
        ]);

        $administration = PoliceAdministration::create($validated);

        return redirect()->route('police-administrations.index')
            ->with('success', __('ui.police_administrations.created', ['name' => $administration->name]));
    }

    public function edit(PoliceAdministration $administration)
    {
        $this->denyNonSuperAdmin();

        return view('police-administrations.edit', compact('administration'));
    }

    public function update(Request $request, PoliceAdministration $administration)
    {
        $this->denyNonSuperAdmin();

        $validated = $request->validate([
            'name' => 'required|string|max:255|unique:police_administrations,name,' . $administration->id,
        ]);

        $administration->update($validated);

        return redirect()->route('police-administrations.index')
            ->with('success', __('ui.police_administrations.updated', ['name' => $administration->name]));
    }

    public function destroy(PoliceAdministration $administration)
    {
        $this->denyNonSuperAdmin();

        if ($administration->stations()->exists()) {
            return back()->with('error', __('ui.police_administrations.delete_blocked', ['name' => $administration->name]));
        }

        $administration->delete();

        return redirect()->route('police-administrations.index')
            ->with('success', __('ui.police_administrations.deleted', ['name' => $administration->name]));
    }
}
