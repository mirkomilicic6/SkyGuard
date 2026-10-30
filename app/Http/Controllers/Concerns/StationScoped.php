<?php

namespace App\Http\Controllers\Concerns;

use App\Models\BorderPoliceStation;

trait StationScoped
{
    // Ograničava upit na postaje koje korisnik smije vidjeti.
    // Administrator vidi sve postaje svoje uprave, analitičar samo svoju.
    // Za pilote se to rješava u pojedinom kontroleru.
    protected function applyStationScope($query, string $stationColumn = 'station_id')
    {
        $stationIds = $this->scopedStationIds();

        if ($stationIds !== null) {
            $query->whereIn($stationColumn, $stationIds);
        }

        return $query;
    }

    // Zabrana za analitičara (može samo pregledavati)
    protected function denyViewer(): void
    {
        if (auth()->user()->hasRole('viewer')) {
            abort(403, 'Nemate ovlasti za izmjenu podataka.');
        }
    }

    // Dopušteno samo glavnom administratoru, npr. dodavanje novih postaja
    protected function denyNonSuperAdmin(): void
    {
        if (auth()->user()->station_id !== null) {
            abort(403, 'Samo super admin može upravljati ustrojstvom postaja.');
        }
    }

    // Pripada li postaja zapisa opsegu korisnika
    protected function inUserStation(?int $recordStationId): bool
    {
        $stationIds = $this->scopedStationIds();

        // glavni administrator nema ograničenja
        if ($stationIds === null) {
            return true;
        }

        return $recordStationId !== null && in_array($recordStationId, $stationIds, true);
    }

    // Pripada li uprava opsegu korisnika (glavni administrator vidi sve)
    protected function inUserAdministration(?int $administrationId): bool
    {
        $user = auth()->user();

        if ($user->station_id === null) {
            return true; // glavni administrator
        }

        return $administrationId !== null && $administrationId === $user->station?->police_administration_id;
    }

    // Vidi li korisnik više postaja, pa tablice trebaju stupac "Postaja"
    protected function showStationColumn(): bool
    {
        $stationIds = $this->scopedStationIds();

        return $stationIds === null || count($stationIds) > 1;
    }

    // Postaje koje korisnik smije vidjeti, ili null za glavnog administratora.
    // Administrator dobiva sve postaje svoje uprave, ostali samo svoju.
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
