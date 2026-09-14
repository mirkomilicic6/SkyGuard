<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\StationScoped;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

class AiController extends Controller
{
    use StationScoped;

    private string $base = 'http://127.0.0.1:8001';

    public function index()
    {
        $user      = auth()->user();
        $stationId = $user->station_id;

        // For pilots: scope to their entire administration
        $adminId = null;
        if ($stationId && $user->station) {
            $adminId = $user->station->administration?->id;
        }

        $param = $adminId
            ? "?administration_id={$adminId}"
            : ($stationId ? "?station_id={$stationId}" : '');

        try {
            $clusters   = Http::timeout(8)->get("{$this->base}/clusters{$param}")->json();
            $riskMatrix = Http::timeout(8)->get("{$this->base}/risk-matrix{$param}")->json();
            $insights   = Http::timeout(8)->get("{$this->base}/insights{$param}")->json();
            $mlOnline   = true;
        } catch (\Throwable) {
            $clusters = $riskMatrix = $insights = [];
            $mlOnline = false;
        }

        $isSuperAdmin = $stationId === null;

        return view('ai.index', compact('clusters', 'riskMatrix', 'insights', 'mlOnline', 'isSuperAdmin'));
    }

    /**
     * Dev-mode model diagnostics: held-out accuracy/precision/recall/ROC-AUC
     * for the risk-grid Random Forest, feature importances, and DBSCAN
     * cluster-quality (silhouette, noise ratio). Super admin only — this is
     * a development/build-time gauge, not a production feature.
     */
    public function devMetrics()
    {
        $this->denyNonSuperAdmin();

        try {
            $result = Http::timeout(20)->get("{$this->base}/model-metrics")->json();
            return response()->json($result);
        } catch (\Throwable) {
            return response()->json(['message' => 'ML servis nedostupan'], 503);
        }
    }

    public function predict(Request $request)
    {
        $request->validate([
            'lat'  => 'required|numeric',
            'lon'  => 'required|numeric',
            'hour' => 'required|integer|between:0,23',
            'dow'  => 'required|integer|between:0,6',
        ]);

        $user    = auth()->user();
        $stationId = $user->station_id;
        $adminId   = $stationId ? $user->station?->administration?->id : null;

        $extra = $adminId
            ? "&administration_id={$adminId}"
            : ($stationId ? "&station_id={$stationId}" : '');

        try {
            $result = Http::timeout(6)->get(
                "{$this->base}/predict?lat={$request->lat}&lon={$request->lon}&hour={$request->hour}&dow={$request->dow}{$extra}"
            )->json();
            return response()->json($result);
        } catch (\Throwable) {
            return response()->json(['error' => 'ML servis nedostupan'], 503);
        }
    }

    public function riskGrid()
    {
        $user      = auth()->user();
        $stationId = $user->station_id;
        $adminId   = $stationId ? $user->station?->administration?->id : null;

        $param = $adminId
            ? "?administration_id={$adminId}"
            : ($stationId ? "?station_id={$stationId}" : '');

        try {
            $result = Http::timeout(15)->get("{$this->base}/risk-grid{$param}")->json();
            return response()->json($result);
        } catch (\Throwable) {
            return response()->json(['message' => 'ML servis nedostupan'], 503);
        }
    }
}
