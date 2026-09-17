<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\StationScoped;
use App\Models\BorderPoliceStation;
use App\Models\Detection;
use App\Models\Drone;
use App\Models\DroneCheckout;
use App\Models\Flight;
use App\Models\GpxPoint;
use App\Models\HuntingCamera;
use App\Models\MaintenanceLog;
use App\Models\PoliceAdministration;
use App\Models\User;
use App\Services\WeatherService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

class DashboardController extends Controller
{
    use StationScoped;

    public function index(Request $request, WeatherService $weather)
    {
        $user = auth()->user();

        if ($user->hasRole('pilot')) {
            return $this->pilotHome($user, $weather);
        }

        $isSupervisor = $user->hasRole(['admin', 'viewer']);
        $isSuperAdmin = $user->station_id === null;

        $administrations = $isSuperAdmin
            ? PoliceAdministration::with('stations')->orderBy('name')->get()
            : null;

        // Base query: supervisor sees all station flights, pilot sees own only
        $flightBase = $isSupervisor
            ? $this->applyStationScope(Flight::query())
            : Flight::where('user_id', $user->id);

        $droneBase = $isSupervisor
            ? $this->applyStationScope(Drone::query())
            : $user->drones();

        // --- Stats ---
        $stats = [
            'total_drones'       => (clone $droneBase)->count(),
            'active_drones'      => (clone $droneBase)->where('status', 'active')->count(),
            'total_flights'      => (clone $flightBase)->count(),
            'flights_today'      => (clone $flightBase)->whereDate('flight_date', today())->count(),
            'flights_this_month' => (clone $flightBase)->whereMonth('flight_date', now()->month)->count(),
            'total_flight_hours' => round((clone $flightBase)->sum('duration_minutes') / 60, 1),
            'open_maintenance'   => $isSupervisor
                ? $this->applyStationScope(MaintenanceLog::where('status', 'open'))->count()
                : MaintenanceLog::where('status', 'open')->count(),
            'total_pilots'       => $isSupervisor
                ? $this->applyStationScope(User::role('pilot'))->count()
                : User::role('pilot')->count(),
            'total_detections'   => Detection::query()
                ->when($user->station_id, fn($q) => $q->where('station_id', $user->station_id))
                ->when(!$isSupervisor && !$user->station_id, fn($q) =>
                    $q->where('created_by', $user->id)
                )
                ->count(),
        ];

        // --- Monthly chart ---
        $availableYears = (clone $flightBase)
            ->selectRaw('YEAR(flight_date) as year')
            ->groupBy('year')
            ->orderBy('year')
            ->pluck('year')
            ->toArray();

        $chartYear = (int) $request->input('chart_year', now()->year);
        if (!empty($availableYears) && !in_array($chartYear, $availableYears)) {
            $chartYear = max($availableYears);
        }

        $monthlyFlights = (clone $flightBase)
            ->selectRaw('MONTH(flight_date) as month, COUNT(*) as count')
            ->whereYear('flight_date', $chartYear)
            ->groupBy('month')
            ->pluck('count', 'month')
            ->toArray();

        // --- Date filter (supervisors only) ---
        $dateFrom = $isSupervisor ? $request->input('date_from') : null;
        $dateTo   = $isSupervisor ? $request->input('date_to')   : null;

        // --- Recent flights ---
        $recentFlights = (clone $flightBase)
            ->with(['drone', 'pilot', 'station.administration'])
            ->when($dateFrom, fn($q) => $q->whereDate('flight_date', '>=', $dateFrom))
            ->when($dateTo,   fn($q) => $q->whereDate('flight_date', '<=', $dateTo))
            ->latest('flight_date')
            ->paginate(15)
            ->withQueryString();

        // --- Heatmap ---
        $heatmapPoints = GpxPoint::select('gpx_points.latitude', 'gpx_points.longitude')
            ->join('flights', 'flights.id', '=', 'gpx_points.flight_id')
            ->whereNull('flights.deleted_at')
            ->when(!$isSupervisor, fn($q) => $q->where('flights.user_id', $user->id))
            ->when($isSupervisor && $user->station_id, fn($q) => $q->where('flights.station_id', $user->station_id))
            ->when($dateFrom, fn($q) => $q->whereDate('flights.flight_date', '>=', $dateFrom))
            ->when($dateTo,   fn($q) => $q->whereDate('flights.flight_date', '<=', $dateTo))
            ->whereNotNull('gpx_points.latitude')
            ->whereNotNull('gpx_points.longitude')
            ->latest('flights.flight_date')
            ->limit(8000)
            ->get()
            ->map(fn($p) => [$p->latitude, $p->longitude])
            ->values();

        // --- Action queue (admin only) — faults awaiting review/accept ---
        $pendingReview = $user->hasRole('admin')
            ? $this->applyStationScope(MaintenanceLog::where('status', MaintenanceLog::STATUS_PENDING))
                ->with(['drone', 'reportedBy'])
                ->oldest()
                ->take(8)
                ->get()
            : collect();

        // --- Checkout widget (pilots only) ---
        $activeCheckout = null;
        $assignedDrones = collect();

        if ($user->hasRole('pilot')) {
            $activeCheckout = DroneCheckout::where('user_id', $user->id)
                ->whereNull('checked_in_at')
                ->with('drone')
                ->first();

            if (!$activeCheckout) {
                $assignedDrones = $user->drones()
                    ->where('drones.status', 'active')
                    ->whereNotIn('drones.id', function ($q) {
                        $q->select('drone_id')
                          ->from('drone_checkouts')
                          ->whereNull('checked_in_at');
                    })
                    ->get();
            }
        }

        return view('dashboard', compact(
            'stats', 'recentFlights', 'monthlyFlights', 'heatmapPoints',
            'dateFrom', 'dateTo', 'chartYear', 'availableYears',
            'activeCheckout', 'assignedDrones', 'pendingReview', 'administrations',
        ) + ['isAdmin' => $isSupervisor, 'isSuperAdmin' => $isSuperAdmin]);
    }

    /**
     * Network-wide overview map, super admin only: detections, flight
     * routes, hunting camera locations, and station/administration
     * boundaries as independently toggleable layers (see resources/views/
     * dashboard.blade.php). Deliberately its own endpoint rather than
     * reusing AnalyticsController::spatialData() — that one is scoped to
     * the caller's own station/administration and doesn't carry cameras or
     * administration boundaries, both needed here.
     */
    public function overviewMapData(Request $request)
    {
        $user = auth()->user();
        abort_unless($user->station_id === null, 403);

        $stationId = $request->filled('station_id') ? $request->integer('station_id') : null;
        $administrationId = $request->filled('administration_id') ? $request->integer('administration_id') : null;

        // A station implies its own administration, so the administration-
        // level boundary layer stays consistent even when only a station
        // was picked in the cascading filter.
        if ($stationId && !$administrationId) {
            $administrationId = BorderPoliceStation::find($stationId)?->police_administration_id;
        }

        $detections = Detection::query()
            ->when($stationId, fn($q) => $q->where('station_id', $stationId))
            ->when(!$stationId && $administrationId, fn($q) =>
                $q->whereHas('station', fn($s) => $s->where('police_administration_id', $administrationId))
            )
            ->when($request->filled('date_from'), fn($q) => $q->whereDate('detected_at', '>=', $request->date('date_from')))
            ->when($request->filled('date_to'), fn($q) => $q->whereDate('detected_at', '<=', $request->date('date_to')))
            ->when($request->filled('detection_type'), fn($q) => $q->where('detection_type', $request->string('detection_type')))
            ->when($request->filled('source'), fn($q) => $q->where('source', $request->string('source')))
            ->whereNotNull('latitude')->whereNotNull('longitude')
            ->get(['latitude', 'longitude', 'entity_count', 'detection_type', 'source', 'detected_at']);

        $points = $detections->map(fn($d) => [
            'lat'            => (float) $d->latitude,
            'lon'            => (float) $d->longitude,
            'entity_count'   => (int) $d->entity_count,
            'detection_type' => $d->detection_type,
            'source'         => $d->source,
            'detected_at'    => $d->detected_at->translatedFormat('d.m.Y H:i'),
        ])->values();

        // Routes: date/station-scoped only — type/source are detection-level
        // attributes and don't apply to a whole flight.
        $routes = Flight::query()
            ->when($stationId, fn($q) => $q->where('station_id', $stationId))
            ->when(!$stationId && $administrationId, fn($q) =>
                $q->whereHas('station', fn($s) => $s->where('police_administration_id', $administrationId))
            )
            ->when($request->filled('date_from'), fn($q) => $q->whereDate('flight_date', '>=', $request->date('date_from')))
            ->when($request->filled('date_to'), fn($q) => $q->whereDate('flight_date', '<=', $request->date('date_to')))
            ->with(['gpxPoints' => fn($q) => $q->orderBy('point_order')->select('id', 'flight_id', 'latitude', 'longitude')])
            ->get()
            ->map(fn($f) => $f->gpxPoints->map(fn($p) => [(float) $p->latitude, (float) $p->longitude])->values())
            ->filter(fn($pts) => $pts->count() > 1)
            ->values();

        $cameras = HuntingCamera::query()
            ->when($stationId, fn($q) => $q->where('station_id', $stationId))
            ->when(!$stationId && $administrationId, fn($q) =>
                $q->whereHas('station', fn($s) => $s->where('police_administration_id', $administrationId))
            )
            ->with('station')
            ->whereNotNull('latitude')->whereNotNull('longitude')
            ->get()
            ->map(fn($c) => [
                'lat'           => (float) $c->latitude,
                'lon'           => (float) $c->longitude,
                'name'          => $c->name,
                'location_name' => $c->location_name,
                'is_active'     => (bool) $c->is_active,
                'station_name'  => $c->station->name ?? null,
            ])->values();

        $stations = BorderPoliceStation::query()
            ->when($stationId, fn($q) => $q->where('id', $stationId))
            ->when(!$stationId && $administrationId, fn($q) => $q->where('police_administration_id', $administrationId))
            ->with('administration')
            ->get()
            ->map(fn($s) => [
                'id'                  => $s->id,
                'name'                => $s->name,
                'lat'                 => (float) $s->latitude,
                'lon'                 => (float) $s->longitude,
                'administration_name' => $s->administration->name ?? null,
                'boundary'            => $s->hasBoundary() ? $s->boundary : null,
                'radius_km'           => BorderPoliceStation::TERRITORY_RADIUS_KM,
            ])->values();

        $administrationAreas = PoliceAdministration::query()
            ->when($administrationId, fn($q) => $q->where('id', $administrationId))
            ->get()
            ->map(fn($a) => [
                'id'       => $a->id,
                'name'     => $a->name,
                'boundary' => $a->hasBoundary() ? $a->boundary : null,
            ])->values();

        return response()->json([
            'points'          => $points,
            'routes'          => $routes,
            'cameras'         => $cameras,
            'stations'        => $stations,
            'administrations' => $administrationAreas,
        ]);
    }

    private function pilotHome(User $user, WeatherService $weather)
    {
        $station = $user->station;

        $activeCheckout = DroneCheckout::where('user_id', $user->id)
            ->whereNull('checked_in_at')
            ->with('drone')
            ->first();

        $assignedDrones = collect();
        if (!$activeCheckout) {
            $assignedDrones = $user->drones()
                ->where('drones.status', 'active')
                ->whereNotIn('drones.id', function ($q) {
                    $q->select('drone_id')
                      ->from('drone_checkouts')
                      ->whereNull('checked_in_at');
                })
                ->get();
        }

        $stationDroneIds = $station
            ? Drone::where('station_id', $station->id)->pluck('id')
            : collect();

        $maintenanceAlerts = MaintenanceLog::whereIn('drone_id', $stationDroneIds)
            ->whereIn('status', [MaintenanceLog::STATUS_PENDING, MaintenanceLog::STATUS_OPEN, MaintenanceLog::STATUS_IN_PROGRESS])
            ->with('drone')
            ->latest()
            ->get();

        $monthStats = [
            'flights' => Flight::where('user_id', $user->id)
                ->whereMonth('flight_date', now()->month)
                ->whereYear('flight_date', now()->year)
                ->count(),
            'hours' => round(Flight::where('user_id', $user->id)
                ->whereMonth('flight_date', now()->month)
                ->whereYear('flight_date', now()->year)
                ->sum('duration_minutes') / 60, 1),
            'cameras' => $station
                ? HuntingCamera::where('station_id', $station->id)->where('is_active', true)->count()
                : 0,
        ];

        $recentFlights = Flight::where('user_id', $user->id)
            ->with('drone')
            ->latest('flight_date')
            ->take(5)
            ->get();

        $cameras = $station
            ? HuntingCamera::where('station_id', $station->id)->orderBy('name')->get()
            : collect();

        return view('dashboard-pilot', [
            'user'              => $user,
            'station'           => $station,
            'weather'           => $weather->forStation($station),
            'activeCheckout'    => $activeCheckout,
            'assignedDrones'    => $assignedDrones,
            'maintenanceAlerts' => $maintenanceAlerts,
            'monthStats'        => $monthStats,
            'recentFlights'     => $recentFlights,
            'cameras'           => $cameras,
            'aiSummary'         => $this->fetchAiRiskSummary($station),
        ]);
    }

    /**
     * Lightweight preview of the risk-grid recommendations for the pilot's
     * station — pure ML data, no LLM call, so it's cheap enough to load on
     * every dashboard visit. The full natural-language version lives in the
     * AI chat assistant.
     */
    private function fetchAiRiskSummary(?BorderPoliceStation $station): ?array
    {
        if (!$station) {
            return null;
        }

        $adminId = $station->police_administration_id;
        $params  = $adminId ? ['administration_id' => $adminId] : ['station_id' => $station->id];

        try {
            $response = Http::timeout(6)->get('http://127.0.0.1:8001/risk-grid', $params);
            if (!$response->successful()) {
                return null;
            }
            $data = $response->json();
        } catch (\Throwable $e) {
            return null;
        }

        if (!empty($data['message'])) {
            return ['message' => $data['message']];
        }

        return [
            'trained_on' => $data['trained_on'] ?? 0,
            'increase'   => array_slice($data['recommend_increase'] ?? [], 0, 5),
            'decrease'   => array_slice($data['recommend_decrease'] ?? [], 0, 3),
        ];
    }
}
