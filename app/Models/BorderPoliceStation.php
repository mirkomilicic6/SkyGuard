<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class BorderPoliceStation extends Model
{
    /**
     * Radius (km) treated as this station's territory for now — just a
     * simple circle around its coordinate, not a real administrative
     * boundary. Used both for the map overlay and for placing new records
     * (e.g. cameras) within a plausible area of the station.
     */
    public const TERRITORY_RADIUS_KM = 10.0;

    protected $fillable = ['police_administration_id', 'name', 'latitude', 'longitude', 'boundary', 'landmark'];

    protected $casts = [
        'latitude'  => 'decimal:6',
        'longitude' => 'decimal:6',
        'boundary'  => 'array',
    ];

    public function administration(): BelongsTo
    {
        return $this->belongsTo(PoliceAdministration::class, 'police_administration_id');
    }

    /**
     * A uniformly-random [lat, lon] point within this station's territory
     * radius (sqrt of a uniform random fraction is used for the distance,
     * otherwise points would cluster unrealistically near the centre).
     *
     * @return array{0: float, 1: float}
     */
    public function randomPointWithinTerritory(?float $radiusKm = null): array
    {
        $radiusKm ??= self::TERRITORY_RADIUS_KM;
        $lat = (float) $this->latitude;
        $lon = (float) $this->longitude;

        $distanceKm = $radiusKm * sqrt(mt_rand() / mt_getrandmax());
        $bearing    = mt_rand() / mt_getrandmax() * 2 * M_PI;

        $dLat = ($distanceKm * cos($bearing)) / 111.0;
        $dLon = ($distanceKm * sin($bearing)) / (111.0 * max(cos(deg2rad($lat)), 0.2));

        return [round($lat + $dLat, 6), round($lon + $dLon, 6)];
    }

    public function hasBoundary(): bool
    {
        return !empty($this->boundary) && count($this->boundary) >= 3;
    }

    /**
     * Whether (lat, lon) falls within this station's territory. Uses the
     * hand-drawn boundary polygon if one has been drawn; otherwise falls
     * back to the simple 10km radius circle.
     */
    public function containsPoint(float $lat, float $lon): bool
    {
        if ($this->hasBoundary()) {
            return self::pointInPolygon($lat, $lon, $this->boundary);
        }

        if ($this->latitude === null) {
            return true; // no reference point to check against — allow
        }

        return $this->distanceKm($lat, $lon) <= self::TERRITORY_RADIUS_KM;
    }

    public function distanceKm(float $lat, float $lon): float
    {
        $lat0 = (float) $this->latitude;
        $lon0 = (float) $this->longitude;

        $dLat = ($lat - $lat0) * 111.0;
        $dLon = ($lon - $lon0) * 111.0 * cos(deg2rad($lat0));

        return sqrt($dLat ** 2 + $dLon ** 2);
    }

    /**
     * Standard ray-casting point-in-polygon test.
     *
     * @param  array<int, array{0: float, 1: float}>  $polygon  [lat, lon] pairs
     */
    private static function pointInPolygon(float $lat, float $lon, array $polygon): bool
    {
        $inside = false;
        $n = count($polygon);

        for ($i = 0, $j = $n - 1; $i < $n; $j = $i++) {
            $latI = (float) $polygon[$i][0];
            $lonI = (float) $polygon[$i][1];
            $latJ = (float) $polygon[$j][0];
            $lonJ = (float) $polygon[$j][1];

            $intersects = (($lonI > $lon) !== ($lonJ > $lon))
                && ($lat < ($latJ - $latI) * ($lon - $lonI) / ($lonJ - $lonI) + $latI);

            if ($intersects) {
                $inside = !$inside;
            }
        }

        return $inside;
    }

    public function users(): HasMany
    {
        return $this->hasMany(User::class, 'station_id');
    }

    public function drones(): HasMany
    {
        return $this->hasMany(Drone::class, 'station_id');
    }
}
