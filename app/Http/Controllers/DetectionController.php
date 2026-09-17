<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\StationScoped;
use App\Models\BorderPoliceStation;
use App\Models\Detection;
use App\Models\Flight;
use App\Models\PoliceAdministration;
use Illuminate\Http\Request;

class DetectionController extends Controller
{
    use StationScoped;

    public function index(Request $request)
    {
        $user         = auth()->user();
        $isSuperAdmin = $user->station_id === null;
        $scopedIds    = $this->scopedStationIds();

        $query = Detection::with(['flight.drone', 'station.administration', 'user'])
            ->when($scopedIds !== null, fn($q) => $q->whereIn('station_id', $scopedIds));

        if ($isSuperAdmin) {
            if ($request->filled('station_id')) {
                $query->where('station_id', $request->station_id);
            } elseif ($request->filled('administration_id')) {
                $query->whereHas('station', fn($sq) =>
                    $sq->where('police_administration_id', $request->administration_id));
            }
            $administrations = PoliceAdministration::with('stations')->orderBy('name')->get();
        } else {
            $administrations = null;
        }

        // Admin manages a whole administration — offer a station filter scoped to it.
        $stations = (!$isSuperAdmin && $user->hasRole('admin') && $user->station?->police_administration_id)
            ? BorderPoliceStation::where('police_administration_id', $user->station->police_administration_id)
                ->orderBy('name')->get()
            : null;

        if ($stations && $request->filled('station_id') && $scopedIds && in_array((int) $request->station_id, $scopedIds, true)) {
            $query->where('station_id', (int) $request->station_id);
        }

        $showStationColumn = $isSuperAdmin || ($scopedIds !== null && count($scopedIds) > 1);

        if ($request->filled('detection_type')) {
            $query->where('detection_type', $request->detection_type);
        }
        if ($request->filled('source')) {
            $query->where('source', $request->source);
        }
        if ($request->filled('date_from')) {
            $query->whereDate('detected_at', '>=', $request->date_from);
        }
        if ($request->filled('date_to')) {
            $query->whereDate('detected_at', '<=', $request->date_to);
        }
        if ($request->filled('flight_link')) {
            $request->flight_link === 'linked'
                ? $query->whereNotNull('flight_id')
                : $query->whereNull('flight_id');
        }

        $detections = $query->latest('detected_at')->paginate(30)->withQueryString();

        return view('detections.index', compact('detections', 'administrations', 'stations', 'showStationColumn'));
    }

    public function create()
    {
        $this->denyViewer();

        $user = auth()->user();

        $flights = Flight::with('drone')
            ->when($user->station_id, fn($q) => $q->where('station_id', $user->station_id))
            ->latest('flight_date')
            ->limit(150)
            ->get();

        // Stations the user is allowed to pick when the detection isn't tied to a flight.
        $stations = $user->station_id === null
            ? BorderPoliceStation::orderBy('name')->get()
            : ($user->hasRole('admin') && $user->station?->police_administration_id
                ? BorderPoliceStation::where('police_administration_id', $user->station->police_administration_id)->orderBy('name')->get()
                : BorderPoliceStation::where('id', $user->station_id)->get());

        return view('detections.create', compact('flights', 'stations'));
    }

    public function storeManual(Request $request)
    {
        $this->denyViewer();

        $user = auth()->user();

        $validated = $request->validate([
            'source'         => 'required|in:drone,trail_camera,ground_observation,other',
            'flight_id'      => 'nullable|exists:flights,id',
            'station_id'     => 'nullable|exists:border_police_stations,id',
            'latitude'       => 'required|numeric|between:-90,90',
            'longitude'      => 'required|numeric|between:-180,180',
            'detection_type' => 'required|in:person,group,vehicle,other',
            'entity_count'   => 'required|integer|min:1|max:999',
            'detected_at'    => 'required|date',
            'note'           => 'nullable|string|max:1000',
        ]);

        $stationId = $validated['station_id'] ?? null;

        // Verify station ownership for non-super-admins.
        if ($user->station_id) {
            if (!empty($validated['flight_id'])) {
                $flight = Flight::findOrFail($validated['flight_id']);
                if ($flight->station_id !== $user->station_id) {
                    abort(403);
                }
            }
            if ($stationId && $stationId !== $user->station_id && !$user->hasRole('admin')) {
                abort(403);
            }
        }

        // A flight-linked detection inherits the flight's station automatically.
        if (!empty($validated['flight_id'])) {
            $stationId = Flight::find($validated['flight_id'])->station_id ?? $stationId;
        }

        Detection::create([
            'flight_id'      => $validated['flight_id'] ?? null,
            'station_id'     => $stationId,
            'created_by'     => $user->id,
            'source'         => $validated['source'],
            'latitude'       => $validated['latitude'],
            'longitude'      => $validated['longitude'],
            'detection_type' => $validated['detection_type'],
            'entity_count'   => $validated['entity_count'],
            'detected_at'    => $validated['detected_at'],
            'note'           => $validated['note'] ?? null,
        ]);

        return redirect()->route('detections.index')
            ->with('success', 'Detekcija uspješno dodana.');
    }

    public function store(Request $request, Flight $flight)
    {
        $this->denyViewer();

        $validated = $request->validate([
            'latitude'       => 'required|numeric|between:-90,90',
            'longitude'      => 'required|numeric|between:-180,180',
            'detection_type' => 'required|in:person,group,vehicle,other',
            'entity_count'   => 'required|integer|min:1|max:999',
            'note'           => 'nullable|string|max:500',
            'detected_at'    => 'nullable|date',
        ]);

        $detection = $flight->detections()->create([
            ...$validated,
            'station_id'  => $flight->station_id,
            'created_by'  => auth()->id(),
            'source'      => 'drone',
            'detected_at' => $validated['detected_at'] ?? now(),
        ]);

        return response()->json([
            'id'             => $detection->id,
            'latitude'       => $detection->latitude,
            'longitude'      => $detection->longitude,
            'detection_type' => $detection->detection_type,
            'entity_count'   => $detection->entity_count,
            'note'           => $detection->note,
            'detected_at'    => $detection->detected_at->translatedFormat('d F Y H:i'),
            'color'          => $detection->typeColor(),
            'icon'           => $detection->typeIcon(),
            'reported_by'    => auth()->user()->name,
            'delete_url'     => route('detections.destroy', $detection),
        ]);
    }

    public function destroy(Detection $detection)
    {
        if (!auth()->user()->hasRole('admin') && $detection->created_by !== auth()->id()) {
            abort(403);
        }
        $detection->delete();
        return response()->json(['ok' => true]);
    }
}
