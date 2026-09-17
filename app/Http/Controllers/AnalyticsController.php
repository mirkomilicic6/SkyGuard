<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\StationScoped;
use App\Models\Detection;
use App\Models\Flight;
use App\Models\BorderPoliceStation;
use App\Models\PoliceAdministration;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class AnalyticsController extends Controller
{
    use StationScoped;

    public function index()
    {
        $scopedIds = $this->scopedStationIds();

        $base = Detection::query()
            ->when($scopedIds !== null, fn($q) => $q->whereIn('station_id', $scopedIds));

        $flightBase = Flight::query()
            ->when($scopedIds !== null, fn($q) => $q->whereIn('station_id', $scopedIds));

        // Summary stats
        $total          = (clone $base)->count();
        $totalEntities  = (int) (clone $base)->sum('entity_count');
        $totalFlights   = (clone $flightBase)->count();
        $totalMinutes   = (int) (clone $flightBase)->sum('duration_minutes');
        $totalHours     = round($totalMinutes / 60, 1);

        // By type
        $byType = (clone $base)
            ->select('detection_type', DB::raw('SUM(entity_count) as total'))
            ->groupBy('detection_type')
            ->pluck('total', 'detection_type');

        $typeLabels = ['person' => 'Osoba', 'group' => 'Grupa', 'vehicle' => 'Vozilo', 'other' => 'Ostalo'];
        $typeColors = ['person' => '#fd7e14', 'group' => '#dc3545', 'vehicle' => '#0d6efd', 'other' => '#6c757d'];

        $chartType = [
            'labels' => collect($typeLabels)->values(),
            'data'   => collect($typeLabels)->keys()->map(fn($k) => (int) ($byType[$k] ?? 0)),
            'colors' => collect($typeColors)->values(),
        ];

        // By source
        $bySource = (clone $base)
            ->select('source', DB::raw('COUNT(*) as cnt'))
            ->groupBy('source')
            ->pluck('cnt', 'source');

        $sourceLabels = ['drone' => 'Dron', 'trail_camera' => 'Kamera', 'ground_observation' => 'Ručno (teren)', 'other' => 'Ostalo'];
        $sourceColors = ['drone' => '#0d6efd', 'trail_camera' => '#20c997', 'ground_observation' => '#f0c040', 'other' => '#6c757d'];

        $chartSource = [
            'labels' => collect($sourceLabels)->values(),
            'data'   => collect($sourceLabels)->keys()->map(fn($k) => (int) ($bySource[$k] ?? 0)),
            'colors' => collect($sourceColors)->values(),
        ];

        // By hour of day
        $byHour = (clone $base)
            ->select(DB::raw('HOUR(detected_at) as hr'), DB::raw('COUNT(*) as cnt'))
            ->groupBy('hr')
            ->pluck('cnt', 'hr');

        $chartHour = [
            'labels' => collect(range(0, 23))->map(fn($h) => str_pad($h, 2, '0', STR_PAD_LEFT) . ':00'),
            'data'   => collect(range(0, 23))->map(fn($h) => (int) ($byHour[$h] ?? 0)),
        ];

        // By day of week (MySQL DAYOFWEEK: 1=Sun,2=Mon…)
        $byDow = (clone $base)
            ->select(DB::raw('DAYOFWEEK(detected_at) as dow'), DB::raw('COUNT(*) as cnt'))
            ->groupBy('dow')
            ->pluck('cnt', 'dow');

        $dowLabels = [2 => 'Pon', 3 => 'Uto', 4 => 'Sri', 5 => 'Čet', 6 => 'Pet', 7 => 'Sub', 1 => 'Ned'];
        $chartDow = [
            'labels' => collect($dowLabels)->values(),
            'data'   => collect($dowLabels)->keys()->map(fn($k) => (int) ($byDow[$k] ?? 0)),
        ];

        // Monthly trend — last 12 months
        $since = Carbon::now()->subMonths(11)->startOfMonth();
        $byMonth = (clone $base)
            ->where('detected_at', '>=', $since)
            ->select(DB::raw("DATE_FORMAT(detected_at,'%Y-%m') as ym"), DB::raw('COUNT(*) as cnt'))
            ->groupBy('ym')
            ->pluck('cnt', 'ym');

        $months = collect();
        for ($i = 11; $i >= 0; $i--) {
            $months->push(Carbon::now()->subMonths($i)->format('Y-m'));
        }
        $chartMonth = [
            'labels' => $months->map(fn($ym) => Carbon::createFromFormat('Y-m', $ym)->translatedFormat('M Y')),
            'data'   => $months->map(fn($ym) => (int) ($byMonth[$ym] ?? 0)),
        ];

        // By station
        $byStation = (clone $base)
            ->join('border_police_stations', 'border_police_stations.id', '=', 'detections.station_id')
            ->select('border_police_stations.name', DB::raw('COUNT(*) as cnt'))
            ->groupBy('border_police_stations.name')
            ->orderByDesc('cnt')
            ->pluck('cnt', 'name');

        $chartStation = [
            'labels' => $byStation->keys(),
            'data'   => $byStation->values(),
        ];

        // Flights with vs. without detections
        $flightsWithDetections    = (clone $flightBase)->has('detections')->count();
        $flightsWithoutDetections = $totalFlights - $flightsWithDetections;

        // Average detections per flight-hour
        $avgDetectionsPerFlightHour = $totalHours > 0 ? round($total / $totalHours, 2) : 0;

        // ── Filter option lists for the spatial-density section ────────────────
        $user         = auth()->user();
        $isSuperAdmin = $user->station_id === null;

        $administrations = $isSuperAdmin
            ? PoliceAdministration::with('stations')->orderBy('name')->get()
            : null;

        $stations = (!$isSuperAdmin && $scopedIds !== null)
            ? BorderPoliceStation::whereIn('id', $scopedIds)->orderBy('name')->get()
            : null;

        $timeBlocks = config('surveillance.time_blocks');

        return view('analytics.index', compact(
            'total', 'totalEntities', 'totalFlights', 'totalHours',
            'chartType', 'chartSource', 'chartHour', 'chartDow', 'chartMonth', 'chartStation',
            'flightsWithDetections', 'flightsWithoutDetections', 'avgDetectionsPerFlightHour',
            'administrations', 'stations', 'timeBlocks'
        ));
    }

    /**
     * JSON data for the spatial-density map: filtered detections (for the
     * density heatmap and the individual-detection layer), GPX routes for
     * flights in the same station/period scope, and station boundaries.
     * Historical density only — no risk scoring of any kind lives here.
     */
    public function spatialData(Request $request)
    {
        $scopedIds  = $this->scopedStationIds();
        $timeBlocks = config('surveillance.time_blocks');

        $detections = Detection::query()
            ->when($scopedIds !== null, fn($q) => $q->whereIn('station_id', $scopedIds))
            ->when($request->filled('station_id'), fn($q) => $q->where('station_id', $request->integer('station_id')))
            ->when($request->filled('date_from'), fn($q) => $q->whereDate('detected_at', '>=', $request->date('date_from')))
            ->when($request->filled('date_to'), fn($q) => $q->whereDate('detected_at', '<=', $request->date('date_to')))
            ->when($request->filled('detection_type'), fn($q) => $q->where('detection_type', $request->string('detection_type')))
            ->when($request->filled('source'), fn($q) => $q->where('source', $request->string('source')))
            ->when($request->filled('time_block') && isset($timeBlocks[$request->integer('time_block')]), function ($q) use ($request, $timeBlocks) {
                $block = $timeBlocks[$request->integer('time_block')];
                $q->whereRaw('HOUR(detected_at) >= ? AND HOUR(detected_at) < ?', [$block['start'], $block['end']]);
            })
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

        // Routes: GPX for flights in the same station+period scope. Type/
        // source/time-block are detection-level attributes and don't apply
        // to flights, so only station+period narrow this layer.
        $routes = Flight::query()
            ->when($scopedIds !== null, fn($q) => $q->whereIn('station_id', $scopedIds))
            ->when($request->filled('station_id'), fn($q) => $q->where('station_id', $request->integer('station_id')))
            ->when($request->filled('date_from'), fn($q) => $q->whereDate('flight_date', '>=', $request->date('date_from')))
            ->when($request->filled('date_to'), fn($q) => $q->whereDate('flight_date', '<=', $request->date('date_to')))
            ->with(['gpxPoints' => fn($q) => $q->orderBy('point_order')->select('id', 'flight_id', 'latitude', 'longitude')])
            ->get()
            ->map(fn($f) => $f->gpxPoints->map(fn($p) => [(float) $p->latitude, (float) $p->longitude])->values())
            ->filter(fn($pts) => $pts->count() > 1)
            ->values();

        // Zones: station boundaries in scope (or just the one selected).
        $zones = BorderPoliceStation::query()
            ->when($scopedIds !== null, fn($q) => $q->whereIn('id', $scopedIds))
            ->when($request->filled('station_id'), fn($q) => $q->where('id', $request->integer('station_id')))
            ->get()
            ->map(fn($s) => [
                'id'        => $s->id,
                'name'      => $s->name,
                'lat'       => (float) $s->latitude,
                'lon'       => (float) $s->longitude,
                'boundary'  => $s->hasBoundary() ? $s->boundary : null,
                'radius_km' => BorderPoliceStation::TERRITORY_RADIUS_KM,
            ])->values();

        return response()->json(['points' => $points, 'routes' => $routes, 'zones' => $zones]);
    }
}
