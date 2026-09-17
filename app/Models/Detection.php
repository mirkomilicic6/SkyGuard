<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Detection extends Model
{
    protected $fillable = [
        'flight_id', 'station_id', 'created_by',
        'source', 'latitude', 'longitude',
        'detection_type', 'entity_count', 'note', 'detected_at',
    ];

    protected $casts = [
        'detected_at' => 'datetime',
        'latitude'    => 'decimal:7',
        'longitude'   => 'decimal:7',
    ];

    public function flight()
    {
        return $this->belongsTo(Flight::class);
    }

    public function station()
    {
        return $this->belongsTo(BorderPoliceStation::class, 'station_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function typeColor(): string
    {
        return match($this->detection_type) {
            'person'    => '#fd7e14',
            'group'     => '#dc3545',
            'vehicle'   => '#0d6efd',
            default     => '#6c757d',
        };
    }

    public function typeIcon(): string
    {
        return match($this->detection_type) {
            'person'    => 'fa-user',
            'group'     => 'fa-users',
            'vehicle'   => 'fa-car',
            default     => 'fa-question-circle',
        };
    }

    public function sourceIcon(): string
    {
        return match($this->source ?? 'drone') {
            'trail_camera'      => 'fa-camera',
            'ground_observation' => 'fa-walking',
            default             => 'fa-helicopter',
        };
    }

    public function sourceLabel(): string
    {
        return match($this->source ?? 'drone') {
            'trail_camera'       => 'Kamera',
            'ground_observation' => 'Ručno (teren)',
            'other'              => 'Ostalo',
            default              => 'Dron',
        };
    }
}
