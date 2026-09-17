<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\StationScoped;
use App\Models\Detection;
use App\Models\Flight;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

class AiController extends Controller
{
    use StationScoped;

    private string $base = 'http://127.0.0.1:8001';

    /** `?station_id=`/`?administration_id=` query fragment scoped to the current user. */
    private function scopeParam(string $prefix = '?'): string
    {
        $user      = auth()->user();
        $stationId = $user->station_id;
        $adminId   = $stationId ? $user->station?->administration?->id : null;

        return $adminId
            ? "{$prefix}administration_id={$adminId}"
            : ($stationId ? "{$prefix}station_id={$stationId}" : '');
    }

    private function proxy(string $path, int $timeout = 10)
    {
        try {
            $result = Http::timeout($timeout)->get("{$this->base}{$path}{$this->scopeParam()}")->json();
            return response()->json($result);
        } catch (\Throwable) {
            return response()->json(['message' => 'ML servis nedostupan'], 503);
        }
    }

    /**
     * "Pregled" — the foundational-data landing page: what we actually have
     * (detections, flights, history length), not model output. Deliberately
     * queries the app's own database rather than ml-service, so it stays
     * useful even when the ML microservice is down; detailed breakdowns live
     * on Analitika, DBSCAN/model/prediction output lives on ML analiza.
     */
    public function index()
    {
        $scopedIds = $this->scopedStationIds();

        $detectionsQuery = Detection::query()->when($scopedIds !== null, fn ($q) => $q->whereIn('station_id', $scopedIds));
        $flightsQuery    = Flight::query()->when($scopedIds !== null, fn ($q) => $q->whereIn('station_id', $scopedIds));

        $totalDetections = (clone $detectionsQuery)->count();
        $totalEntities   = (int) (clone $detectionsQuery)->sum('entity_count');
        $totalFlights    = (clone $flightsQuery)->count();
        $totalHours      = round((int) (clone $flightsQuery)->sum('duration_minutes') / 60, 1);

        $firstDetection = (clone $detectionsQuery)->min('detected_at');
        $lastDetection  = (clone $detectionsQuery)->max('detected_at');
        $historyDays    = ($firstDetection && $lastDetection)
            ? (int) Carbon::parse($firstDetection)->diffInDays(Carbon::parse($lastDetection)) + 1
            : 0;

        try {
            $clusters  = Http::timeout(6)->get("{$this->base}/clusters{$this->scopeParam()}")->json();
            $zoneCount = count($clusters['clusters'] ?? []);
            $mlOnline  = true;
        } catch (\Throwable) {
            $zoneCount = null;
            $mlOnline  = false;
        }

        // Same check ChatController::respond() relies on — a missing API key
        // is the only thing we can know without actually spending a request.
        try {
            new \Anthropic\Client();
            $assistantOnline = true;
        } catch (\Throwable) {
            $assistantOnline = false;
        }

        return view('ai.index', compact(
            'totalDetections', 'totalEntities', 'totalFlights', 'totalHours',
            'firstDetection', 'lastDetection', 'historyDays', 'zoneCount', 'mlOnline', 'assistantOnline'
        ));
    }

    public function predict(Request $request)
    {
        $request->validate([
            'lat'  => 'required|numeric',
            'lon'  => 'required|numeric',
            'hour' => 'required|integer|between:0,23',
            'dow'  => 'required|integer|between:0,6',
        ]);

        try {
            $result = Http::timeout(6)->get(
                "{$this->base}/predict?lat={$request->lat}&lon={$request->lon}&hour={$request->hour}&dow={$request->dow}" . $this->scopeParam('&')
            )->json();
            return response()->json($result);
        } catch (\Throwable) {
            return response()->json(['error' => 'ML servis nedostupan'], 503);
        }
    }

    public function riskGrid()
    {
        return $this->proxy('/risk-grid', 15);
    }

    public function clusters()
    {
        return $this->proxy('/clusters', 8);
    }

    public function footageAnalysis()
    {
        return view('ai.footage');
    }

    /**
     * Forwards an uploaded video to ml-service's YOLOv8 detection endpoint
     * and returns its per-sampled-frame box data as-is. `thermal` switches
     * to the model fine-tuned on thermal/infrared footage (person-only) —
     * the regular COCO model generalises poorly to that (very different
     * visual appearance from ordinary colour video). Long timeout — CPU
     * inference on even a short clip takes real time (see vision.py for the
     * sampling strategy that keeps it from taking much longer than that).
     */
    public function analyzeFootage(Request $request)
    {
        $this->denyViewer();

        $request->validate([
            'video'   => 'required|file|mimetypes:video/mp4,video/quicktime,video/x-msvideo,video/webm|max:102400',
            'thermal' => 'nullable|boolean',
        ]);

        try {
            $file = $request->file('video');
            $result = Http::timeout(300)
                ->attach('video', file_get_contents($file->getRealPath()), $file->getClientOriginalName())
                ->post("{$this->base}/footage/analyze", [
                    'thermal' => $request->boolean('thermal') ? 'true' : 'false',
                ])
                ->json();

            return response()->json($result);
        } catch (\Throwable) {
            return response()->json(['error' => 'ML servis nedostupan ili je obrada predugo trajala.'], 503);
        }
    }

    public function academic()
    {
        return view('academic.index');
    }

    public function zoneFlightStats()
    {
        return $this->proxy('/zones/flight-stats', 30);
    }

    public function zoneDatasetPreview()
    {
        return $this->proxy('/zones/dataset-preview', 20);
    }

    public function zoneModelMetrics()
    {
        return $this->proxy('/zone-model-metrics', 90);
    }

    public function zonePredictions(Request $request)
    {
        $user      = auth()->user();
        $stationId = $user->station_id;
        $adminId   = $stationId ? $user->station?->administration?->id : null;

        $params = array_filter([
            'administration_id' => $adminId,
            'station_id'        => $adminId ? null : $stationId,
            'dow'               => $request->filled('dow') ? $request->integer('dow') : null,
            'block'             => $request->filled('block') ? $request->integer('block') : null,
            'model'             => $request->filled('model') ? $request->string('model') : null,
        ], fn ($v) => $v !== null);

        try {
            $result = Http::timeout(60)->get("{$this->base}/zone-predictions", $params)->json();
            return response()->json($result);
        } catch (\Throwable) {
            return response()->json(['message' => 'ML servis nedostupan'], 503);
        }
    }
}
