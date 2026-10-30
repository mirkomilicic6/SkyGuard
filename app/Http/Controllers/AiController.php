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

    private string $base;

    public function __construct()
    {
        $this->base = config('services.ml.url');
    }
    // Parametar postaje ili uprave za upit prema servisu, prema opsegu korisnika
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

    // Stranica Pregled: osnovni podaci (detekcije, letovi, razdoblje), a ne rezultati modela.
    // Čita iz baze aplikacije, pa radi i kad servis za strojno učenje ne radi.
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
            $clusters  = Http::timeout(15)->get("{$this->base}/clusters{$this->scopeParam()}")->json();
            $zoneCount = count($clusters['clusters'] ?? []);
            $mlOnline  = true;
        } catch (\Throwable) {
            $zoneCount = null;
            $mlOnline  = false;
        }

        // asistent radi samo ako je postavljen API ključ
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
        return $this->proxy('/risk-grid', 60);
    }

    public function clusters()
    {
        // prvi izračun žarišta nakon pokretanja servisa može trajati desetke sekundi
        return $this->proxy('/clusters', 120);
    }

    public function footageAnalysis()
    {
        return view('ai.footage');
    }

    // Šalje video servisu za strojno učenje na analizu (YOLOv8) i vraća rezultat.
    // thermal odabire model za termalne snimke. Analiza traje, pa je vrijeme čekanja dugo.
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
        return $this->proxy('/zones/flight-stats', 60);
    }

    public function zoneDatasetPreview()
    {
        return $this->proxy('/zones/dataset-preview', 60);
    }

    public function zoneModelMetrics()
    {
        // učenje sva četiri modela traje nekoliko minuta, pa je vrijeme čekanja najdulje
        return $this->proxy('/zone-model-metrics', 240);
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
            $result = Http::timeout(120)->get("{$this->base}/zone-predictions", $params)->json();
            return response()->json($result);
        } catch (\Throwable) {
            return response()->json(['message' => 'ML servis nedostupan'], 503);
        }
    }
}
