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

        // pilot vidi samo svoje letove, ostali sve letove svog opsega
        $flightBase = $isSupervisor
            ? $this->applyStationScope(Flight::query())
            : Flight::where('user_id', $user->id);

        $droneBase = $isSupervisor
            ? $this->applyStationScope(Drone::query())
            : $user->drones();

        // statistika
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

        // graf po mjesecima
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

        // filter po datumu (ne za pilote)
        $dateFrom = $isSupervisor ? $request->input('date_from') : null;
        $dateTo   = $isSupervisor ? $request->input('date_to')   : null;

        // zadnji letovi
        $recentFlights = (clone $flightBase)
            ->with(['drone', 'pilot', 'station.administration'])
            ->when($dateFrom, fn($q) => $q->whereDate('flight_date', '>=', $dateFrom))
            ->when($dateTo,   fn($q) => $q->whereDate('flight_date', '<=', $dateTo))
            ->latest('flight_date')
            ->paginate(15)
            ->withQueryString();

        // toplinska karta
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

        // kvarovi koji čekaju obradu (samo administrator)
        $pendingReview = $user->hasRole('admin')
            ? $this->applyStationScope(MaintenanceLog::where('status', MaintenanceLog::STATUS_PENDING))
                ->with(['drone', 'reportedBy'])
                ->oldest()
                ->take(8)
                ->get()
            : collect();

        // zaduženi dron (samo pilot)
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

    // Karta cijele mreže, samo za glavnog administratora: detekcije, putanje letova,
    // lovačke kamere te granice postaja i uprava kao zasebni slojevi.
    public function overviewMapData(Request $request)
    {
        $user = auth()->user();
        abort_unless($user->station_id === null, 403);

        $stationId = $request->filled('station_id') ? $request->integer('station_id') : null;
        $administrationId = $request->filled('administration_id') ? $request->integer('administration_id') : null;

        // ako je odabrana postaja, prikazuje se i granica njezine uprave
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

        // putanje letova filtriraju se samo po datumu i postaji,
        // a prikazuje se ograničen broj najnovijih letova zbog memorije i preglednosti karte
        $routesQuery = Flight::query()
            ->when($stationId, fn($q) => $q->where('station_id', $stationId))
            ->when(!$stationId && $administrationId, fn($q) =>
                $q->whereHas('station', fn($s) => $s->where('police_administration_id', $administrationId))
            )
            ->when($request->filled('date_from'), fn($q) => $q->whereDate('flight_date', '>=', $request->date('date_from')))
            ->when($request->filled('date_to'), fn($q) => $q->whereDate('flight_date', '<=', $request->date('date_to')));

        $routesTotal = (clone $routesQuery)->count();
        $routesLimit = 500;

        $routes = $routesQuery
            ->orderByDesc('flight_date')
            ->limit($routesLimit)
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
            'routes_total'    => $routesTotal,
            'routes_shown'    => $routes->count(),
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

    // Kratki pregled preporuka iz mreže rizika za postaju pilota, bez jezičnog modela
    private function fetchAiRiskSummary(?BorderPoliceStation $station): ?array
    {
        if (!$station) {
            return null;
        }

        $adminId = $station->police_administration_id;
        $params  = $adminId ? ['administration_id' => $adminId] : ['station_id' => $station->id];

        try {
            $response = Http::timeout(20)->get(config('services.ml.url') . '/risk-grid', $params);
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
