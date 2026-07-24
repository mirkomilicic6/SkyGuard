<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Detection extends Model
{
    protected $fillable = [
        'flight_id', 'camera_id', 'user_id',
        'source', 'latitude', 'longitude',
        'type', 'count', 'notes', 'detected_at',
        'confirmed', 'action_taken', 'heading_deg',
        'weather_condition', 'escalation_level',
    ];

    protected $casts = [
        'detected_at' => 'datetime',
        'confirmed'   => 'boolean',
        'latitude'    => 'decimal:7',
        'longitude'   => 'decimal:7',
    ];

    public function flight()
    {
        return $this->belongsTo(Flight::class);
    }

    public function camera()
    {
        return $this->belongsTo(HuntingCamera::class, 'camera_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function typeColor(): string
    {
        return match($this->type) {
            'person'    => '#fd7e14',
            'group'     => '#dc3545',
            'vehicle'   => '#0d6efd',
            'smuggling' => '#6f42c1',
            default     => '#6c757d',
        };
    }

    public function typeIcon(): string
    {
        return match($this->type) {
            'person'    => 'fa-user',
            'group'     => 'fa-users',
            'vehicle'   => 'fa-car',
            'smuggling' => 'fa-box',
            default     => 'fa-question-circle',
        };
    }

    public function sourceIcon(): string
    {
        return match($this->source ?? 'drone') {
            'camera' => 'fa-camera',
            'manual' => 'fa-walking',
            default  => 'fa-helicopter',
        };
    }

    public function sourceLabel(): string
    {
        return match($this->source ?? 'drone') {
            'camera' => 'Kamera',
            'manual' => 'Ručno',
            default  => 'Dron',
        };
    }

    public function escalationColor(): string
    {
        return match((int) ($this->escalation_level ?? 0)) {
            1 => '#f0c040',
            2 => '#fd7e14',
            3 => '#dc3545',
            default => '#6c757d',
        };
    }
}
