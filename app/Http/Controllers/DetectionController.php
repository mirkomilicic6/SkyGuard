<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\StationScoped;
use App\Models\BorderPoliceStation;
use App\Models\Detection;
use App\Models\Flight;
use App\Models\HuntingCamera;
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

        $query = Detection::with([
                'flight.drone', 'flight.station.administration',
                'camera.station', 'user',
            ])
            ->when($scopedIds !== null, function ($q) use ($scopedIds, $user) {
                $q->where(function ($inner) use ($scopedIds, $user) {
                    $inner->whereHas('flight', fn($fq) => $fq->whereIn('station_id', $scopedIds))
                          ->orWhereHas('camera', fn($cq) => $cq->whereIn('station_id', $scopedIds))
                          ->orWhere(fn($mq) => $mq->whereNull('flight_id')
                                                   ->whereNull('camera_id')
                                                   ->where('user_id', $user->id));
                });
            });

        if ($isSuperAdmin) {
            if ($request->filled('station_id')) {
                $query->where(function ($q) use ($request) {
                    $q->whereHas('flight', fn($fq) => $fq->where('station_id', $request->station_id))
                      ->orWhereHas('camera', fn($cq) => $cq->where('station_id', $request->station_id));
                });
            } elseif ($request->filled('administration_id')) {
                $query->where(function ($q) use ($request) {
                    $q->whereHas('flight.station', fn($fq) =>
                        $fq->where('police_administration_id', $request->administration_id))
                      ->orWhereHas('camera.station', fn($cq) =>
                        $cq->where('police_administration_id', $request->administration_id));
                });
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
            $stationId = (int) $request->station_id;
            $query->where(function ($q) use ($stationId) {
                $q->whereHas('flight', fn($fq) => $fq->where('station_id', $stationId))
                  ->orWhereHas('camera', fn($cq) => $cq->where('station_id', $stationId));
            });
        }

        $showStationColumn = $isSuperAdmin || ($scopedIds !== null && count($scopedIds) > 1);

        if ($request->filled('type')) {
            $query->where('type', $request->type);
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

        $cameras = HuntingCamera::where('is_active', true)
            ->when($user->station_id, fn($q) => $q->where('station_id', $user->station_id))
            ->with('station')
            ->orderBy('name')
            ->get();

        return view('detections.create', compact('flights', 'cameras'));
    }

    public function storeManual(Request $request)
    {
        $this->denyViewer();

        $user = auth()->user();

        $validated = $request->validate([
            'source'            => 'required|in:drone,camera,manual',
            'flight_id'         => 'nullable|exists:flights,id',
            'camera_id'         => 'nullable|exists:hunting_cameras,id',
            'latitude'          => 'required|numeric|between:-90,90',
            'longitude'         => 'required|numeric|between:-180,180',
            'type'              => 'required|in:person,group,vehicle,smuggling,other',
            'count'             => 'required|integer|min:1|max:999',
            'detected_at'       => 'required|date',
            'notes'             => 'nullable|string|max:1000',
            'confirmed'         => 'nullable|boolean',
            'action_taken'      => 'nullable|string|max:30',
            'heading_deg'       => 'nullable|integer|between:0,359',
            'weather_condition' => 'nullable|string|max:20',
            'escalation_level'  => 'nullable|integer|between:0,3',
        ]);

        // Verify station ownership for non-super-admins
        if ($user->station_id) {
            if (!empty($validated['flight_id'])) {
                $flight = Flight::findOrFail($validated['flight_id']);
                if ($flight->station_id !== $user->station_id) {
                    abort(403);
                }
            }
            if (!empty($validated['camera_id'])) {
                $camera = HuntingCamera::findOrFail($validated['camera_id']);
                if ($camera->station_id !== $user->station_id) {
                    abort(403);
                }
            }
        }

        Detection::create([
            'flight_id'         => $validated['source'] === 'drone' ? ($validated['flight_id'] ?? null) : null,
            'camera_id'         => $validated['source'] === 'camera' ? ($validated['camera_id'] ?? null) : null,
            'user_id'           => $user->id,
            'source'            => $validated['source'],
            'latitude'          => $validated['latitude'],
            'longitude'         => $validated['longitude'],
            'type'              => $validated['type'],
            'count'             => $validated['count'],
            'detected_at'       => $validated['detected_at'],
            'notes'             => $validated['notes'] ?? null,
            'confirmed'         => $request->boolean('confirmed'),
            'action_taken'      => $validated['action_taken'] ?? null,
            'heading_deg'       => $validated['heading_deg'] ?? null,
            'weather_condition' => $validated['weather_condition'] ?? null,
            'escalation_level'  => $validated['escalation_level'] ?? 0,
        ]);

        return redirect()->route('detections.index')
            ->with('success', 'Detekcija uspješno dodana.');
    }

    public function store(Request $request, Flight $flight)
    {
        $this->denyViewer();

        $validated = $request->validate([
            'latitude'    => 'required|numeric|between:-90,90',
            'longitude'   => 'required|numeric|between:-180,180',
            'type'        => 'required|in:person,group,vehicle,smuggling,other',
            'count'       => 'required|integer|min:1|max:999',
            'notes'       => 'nullable|string|max:500',
            'detected_at' => 'nullable|date',
        ]);

        $detection = $flight->detections()->create([
            ...$validated,
            'user_id'     => auth()->id(),
            'source'      => 'drone',
            'detected_at' => $validated['detected_at'] ?? now(),
        ]);

        return response()->json([
            'id'          => $detection->id,
            'latitude'    => $detection->latitude,
            'longitude'   => $detection->longitude,
            'type'        => $detection->type,
            'count'       => $detection->count,
            'notes'       => $detection->notes,
            'detected_at' => $detection->detected_at->translatedFormat('d F Y H:i'),
            'color'       => $detection->typeColor(),
            'icon'        => $detection->typeIcon(),
            'reported_by' => auth()->user()->name,
            'delete_url'  => route('detections.destroy', $detection),
        ]);
    }

    public function destroy(Detection $detection)
    {
        if (!auth()->user()->hasRole('admin') && $detection->user_id !== auth()->id()) {
            abort(403);
        }
        $detection->delete();
        return response()->json(['ok' => true]);
    }
}
