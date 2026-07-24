<?php

namespace App\Http\Controllers\Concerns;

use App\Models\BorderPoliceStation;

trait StationScoped
{
    /**
     * Apply station filter to a query based on the current user's scope.
     * Admins manage their whole administration (every station under it);
     * viewers are scoped to just their own station.
     * Pilots see only their own records (handled per-controller).
     */
    protected function applyStationScope($query, string $stationColumn = 'station_id')
    {
        $stationIds = $this->scopedStationIds();

        if ($stationIds !== null) {
            $query->whereIn($stationColumn, $stationIds);
        }

        return $query;
    }

    /**
     * Abort if the authenticated user is a viewer (read-only role).
     */
    protected function denyViewer(): void
    {
        if (auth()->user()->hasRole('viewer')) {
            abort(403, 'Nemate ovlasti za izmjenu podataka.');
        }
    }

    /**
     * Check whether a record's station falls within the current user's scope.
     */
    protected function inUserStation(?int $recordStationId): bool
    {
        $stationIds = $this->scopedStationIds();

        // No restriction (super admin)
        if ($stationIds === null) {
            return true;
        }

        return $recordStationId !== null && in_array($recordStationId, $stationIds, true);
    }

    /**
     * Whether the current user's scope spans more than one station, so views
     * know to render a "Postaja" column (super admin, or an admin whose
     * administration has multiple stations).
     */
    protected function showStationColumn(): bool
    {
        $stationIds = $this->scopedStationIds();

        return $stationIds === null || count($stationIds) > 1;
    }

    /**
     * Station IDs the current user is allowed to see/manage, or null for no
     * restriction (super admin). Admins get every station in their own
     * administration; everyone else is limited to their own station.
     */
    protected function scopedStationIds(): ?array
    {
        $user = auth()->user();

        if ($user->station_id === null) {
            return null;
        }

        if ($user->hasRole('admin')) {
            $administrationId = $user->station?->police_administration_id;

            if ($administrationId) {
                return BorderPoliceStation::where('police_administration_id', $administrationId)
                    ->pluck('id')
                    ->all();
            }
        }

        return [$user->station_id];
    }
}
