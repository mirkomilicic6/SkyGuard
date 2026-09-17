<?php

namespace App\Http\Controllers;

use App\Services\RecommendationService;
use Illuminate\Http\Request;

class RecommendationController extends Controller
{
    /**
     * JSON variant of the DBSCAN-hotspot recommendations (model-probability
     * driven), for the academic pipeline page's fifth card.
     */
    public function dbscanZones(Request $request, RecommendationService $service)
    {
        $user         = auth()->user();
        $isSuperAdmin = $user->station_id === null;

        $administrationId = $isSuperAdmin
            ? $request->integer('administration_id') ?: null
            : $user->station?->police_administration_id;

        $zones = $service->forDbscanZones($administrationId)->values();

        return response()->json(['zones' => $zones]);
    }
}
