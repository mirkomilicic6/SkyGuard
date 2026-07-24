<?php

namespace App\Http\Controllers;

use App\Models\Detection;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class AnalyticsController extends Controller
{
    public function index()
    {
        $user      = auth()->user();
        $stationId = $user->station_id;

        $userId = $user->id;

        $base = Detection::query()
            ->when($stationId, function ($q) use ($stationId, $userId) {
                $q->where(function ($inner) use ($stationId, $userId) {
                    $inner->whereHas('flight', fn($fq) => $fq->where('station_id', $stationId))
                          ->orWhereHas('camera', fn($cq) => $cq->where('station_id', $stationId))
                          ->orWhere(fn($mq) => $mq->whereNull('flight_id')
                                                   ->whereNull('camera_id')
                                                   ->where('user_id', $userId));
                });
            });

        // Summary stats
        $total     = (clone $base)->count();
        $confirmed = (clone $base)->where('confirmed', true)->count();

        // Heatmap points: [lat, lng, weight]
        $heatmap = (clone $base)
            ->whereNotNull('latitude')
            ->whereNotNull('longitude')
            ->select('latitude', 'longitude', 'count', 'type')
            ->get()
            ->map(fn($d) => [(float)$d->latitude, (float)$d->longitude, min((int)$d->count / 5 + 0.2, 1.0)])
            ->values();

        // By type
        $byType = (clone $base)
            ->select('type', DB::raw('SUM(`count`) as total'))
            ->groupBy('type')
            ->pluck('total', 'type');

        $typeLabels = ['person' => 'Osoba', 'group' => 'Grupa', 'vehicle' => 'Vozilo', 'smuggling' => 'Krijumčarenje', 'other' => 'Ostalo'];
        $typeColors = ['person' => '#fd7e14', 'group' => '#dc3545', 'vehicle' => '#0d6efd', 'smuggling' => '#6f42c1', 'other' => '#6c757d'];

        $chartType = [
            'labels' => collect($typeLabels)->values(),
            'data'   => collect($typeLabels)->keys()->map(fn($k) => (int)($byType[$k] ?? 0)),
            'colors' => collect($typeColors)->values(),
        ];

        // By hour of day
        $byHour = (clone $base)
            ->select(DB::raw('HOUR(detected_at) as hr'), DB::raw('COUNT(*) as cnt'))
            ->groupBy('hr')
            ->pluck('cnt', 'hr');

        $chartHour = [
            'labels' => collect(range(0, 23))->map(fn($h) => str_pad($h, 2, '0', STR_PAD_LEFT) . ':00'),
            'data'   => collect(range(0, 23))->map(fn($h) => (int)($byHour[$h] ?? 0)),
        ];

        // By day of week (1=Monday … 7=Sunday for MySQL DAYOFWEEK: 1=Sun,2=Mon…)
        $byDow = (clone $base)
            ->select(DB::raw('DAYOFWEEK(detected_at) as dow'), DB::raw('COUNT(*) as cnt'))
            ->groupBy('dow')
            ->pluck('cnt', 'dow');

        $dowLabels = [2 => 'Pon', 3 => 'Uto', 4 => 'Sri', 5 => 'Čet', 6 => 'Pet', 7 => 'Sub', 1 => 'Ned'];
        $chartDow = [
            'labels' => collect($dowLabels)->values(),
            'data'   => collect($dowLabels)->keys()->map(fn($k) => (int)($byDow[$k] ?? 0)),
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
            'data'   => $months->map(fn($ym) => (int)($byMonth[$ym] ?? 0)),
        ];

        // Escalation breakdown
        $byEscalation = (clone $base)
            ->select('escalation_level', DB::raw('COUNT(*) as cnt'))
            ->groupBy('escalation_level')
            ->pluck('cnt', 'escalation_level');

        $escalationLabels = [0 => 'Informativno', 1 => 'Upozorenje', 2 => 'Intervencija', 3 => 'Kritično'];
        $escalationColors = [0 => '#6c757d', 1 => '#f0c040', 2 => '#fd7e14', 3 => '#dc3545'];
        $chartEscalation = [
            'labels' => collect($escalationLabels)->values(),
            'data'   => collect($escalationLabels)->keys()->map(fn($k) => (int)($byEscalation[$k] ?? 0)),
            'colors' => collect($escalationColors)->values(),
        ];

        return view('analytics.index', compact(
            'total', 'confirmed',
            'heatmap', 'chartType', 'chartHour', 'chartDow', 'chartMonth', 'chartEscalation'
        ));
    }
}
