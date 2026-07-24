<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Flight extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'drone_id', 'user_id', 'station_id', 'flight_date', 'duration_minutes',
        'distance_km', 'max_altitude_m', 'avg_speed_kmh',
        'gpx_file_path', 'location', 'purpose', 'status',
    ];

    protected $casts = [
        'flight_date' => 'datetime',
    ];

    public function drone()
    {
        return $this->belongsTo(Drone::class);
    }

    public function pilot()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function station()
    {
        return $this->belongsTo(\App\Models\BorderPoliceStation::class, 'station_id');
    }

    public function gpxPoints()
    {
        return $this->hasMany(GpxPoint::class)->orderBy('point_order');
    }

    public function detections()
    {
        return $this->hasMany(Detection::class);
    }
}
