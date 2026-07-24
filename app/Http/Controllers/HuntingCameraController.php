<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\StationScoped;
use App\Models\BorderPoliceStation;
use App\Models\CameraChangeLog;
use App\Models\HuntingCamera;
use App\Models\PoliceAdministration;
use Illuminate\Http\Request;

class HuntingCameraController extends Controller
{
    use StationScoped;

    public function index(Request $request)
    {
        $user = auth()->user();
        $isSuperAdmin = $user->station_id === null;
        $scopedIds    = $this->scopedStationIds();

        $query = $this->cameraScope()->with(['station.administration', 'lastUpdatedBy']);

        if ($isSuperAdmin) {
            if ($request->filled('station_id')) {
                $query->where('station_id', $request->station_id);
            } elseif ($request->filled('administration_id')) {
                $stationIds = BorderPoliceStation::where('police_administration_id', $request->administration_id)
                    ->pluck('id');
                $query->whereIn('station_id', $stationIds);
            }
        } elseif ($request->filled('station_id') && $scopedIds && in_array((int) $request->station_id, $scopedIds, true)) {
            $query->where('station_id', $request->station_id);
        }

        $cameras = $query->orderBy('name')->get();

        $administrations = $isSuperAdmin
            ? PoliceAdministration::with('stations')->orderBy('name')->get()
            : null;

        // Admin manages a whole administration — offer a station filter scoped to it.
        $stations = (!$isSuperAdmin && $user->hasRole('admin') && $user->station?->police_administration_id)
            ? BorderPoliceStation::where('police_administration_id', $user->station->police_administration_id)
                ->orderBy('name')->get()
            : null;

        $showStationColumn = $this->showStationColumn();

        return view('cameras.index', compact('cameras', 'administrations', 'stations', 'showStationColumn'));
    }

    public function create()
    {
        $this->denyViewer();
        $user = auth()->user();
        $stations = $user->station_id ? null
            : \App\Models\BorderPoliceStation::orderBy('name')->get();
        $ownStation = $user->station_id ? $user->station : null;
        return view('cameras.create', compact('stations', 'ownStation'));
    }

    public function store(Request $request)
    {
        $this->denyViewer();
        $user = auth()->user();

        $rules = [
            'name'          => 'required|string|max:20',
            'location_name' => 'required|string|max:255',
            'latitude'      => 'nullable|numeric|between:-90,90',
            'longitude'     => 'nullable|numeric|between:-180,180',
            'notes'         => 'nullable|string|max:1000',
        ];
        if (!$user->station_id) {
            $rules['station_id'] = 'required|exists:border_police_stations,id';
        }

        $validated = $request->validate($rules);

        $validated['station_id']      = $user->station_id ?? $request->station_id;
        $validated['last_updated_by'] = $user->id;

        if (isset($validated['latitude'], $validated['longitude'])) {
            $station = BorderPoliceStation::find($validated['station_id']);
            if ($station && !$station->containsPoint((float) $validated['latitude'], (float) $validated['longitude'])) {
                return back()->withErrors(['latitude' => __('ui.cameras.outside_boundary')])->withInput();
            }
        }

        $camera = HuntingCamera::create($validated);

        CameraChangeLog::create([
            'camera_id'  => $camera->id,
            'user_id'    => $user->id,
            'action'     => 'created',
            'changes'    => 'Kamera dodana: ' . $camera->name . ' — ' . $camera->location_name,
            'created_at' => now(),
        ]);

        return redirect()->route('cameras.index')->with('success', __('ui.cameras.created'));
    }

    public function show(HuntingCamera $camera)
    {
        $this->authorizeCamera($camera);
        $camera->load(['station', 'lastUpdatedBy', 'changeLogs.user']);
        return view('cameras.show', compact('camera'));
    }

    public function edit(HuntingCamera $camera)
    {
        $this->denyViewer();
        $this->authorizeCamera($camera);
        return view('cameras.edit', compact('camera'));
    }

    public function update(Request $request, HuntingCamera $camera)
    {
        $this->denyViewer();
        $this->authorizeCamera($camera);
        $user = auth()->user();

        $validated = $request->validate([
            'name'          => 'required|string|max:20',
            'location_name' => 'required|string|max:255',
            'latitude'      => 'nullable|numeric|between:-90,90',
            'longitude'     => 'nullable|numeric|between:-180,180',
            'notes'         => 'nullable|string|max:1000',
        ]);

        if (isset($validated['latitude'], $validated['longitude']) && $camera->station
            && !$camera->station->containsPoint((float) $validated['latitude'], (float) $validated['longitude'])) {
            return back()->withErrors(['latitude' => __('ui.cameras.outside_boundary')])->withInput();
        }

        $action = 'updated';
        $changeDesc = [];

        if ($camera->location_name !== $validated['location_name'] ||
            (string)$camera->latitude !== (string)$validated['latitude'] ||
            (string)$camera->longitude !== (string)$validated['longitude']) {
            $action = 'relocated';
            $changeDesc[] = 'Lokacija: ' . $camera->location_name . ' → ' . $validated['location_name'];
            if ($validated['latitude']) {
                $changeDesc[] = 'GPS: ' . $camera->latitude . ',' . $camera->longitude
                              . ' → ' . $validated['latitude'] . ',' . $validated['longitude'];
            }
        }

        $validated['last_updated_by'] = $user->id;
        $camera->update($validated);

        CameraChangeLog::create([
            'camera_id'  => $camera->id,
            'user_id'    => $user->id,
            'action'     => $action,
            'changes'    => implode('; ', $changeDesc) ?: 'Ažurirani podaci.',
            'created_at' => now(),
        ]);

        return redirect()->route('cameras.index')->with('success', __('ui.cameras.updated'));
    }

    public function destroy(HuntingCamera $camera)
    {
        $this->denyViewer();
        $this->authorizeCamera($camera);
        $camera->delete();
        return redirect()->route('cameras.index')->with('success', __('ui.cameras.deleted'));
    }

    public function exportWord(Request $request)
    {
        $cameras = $this->cameraScope()
            ->with('station')
            ->where('is_active', true)
            ->orderBy('name')
            ->get();

        $stationName = auth()->user()->station?->name ?? 'Sve postaje';
        $date = now()->format('d.m.Y');

        $html = view('cameras.export_word', compact('cameras', 'stationName', 'date'))->render();

        return response($html, 200, [
            'Content-Type'        => 'application/vnd.ms-word',
            'Content-Disposition' => 'attachment; filename="kamere_' . now()->format('d-m-Y') . '.doc"',
        ]);
    }

    private function cameraScope()
    {
        return $this->applyStationScope(HuntingCamera::query());
    }

    private function authorizeCamera(HuntingCamera $camera): void
    {
        if (!$this->inUserStation($camera->station_id)) {
            abort(403);
        }
    }
}
