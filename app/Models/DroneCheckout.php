<?php

namespace App\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;

class DroneCheckout extends Model
{
    protected $fillable = ['drone_id', 'user_id', 'checked_out_at', 'checked_in_at', 'notes'];

    protected $casts = [
        'checked_out_at' => 'datetime',
        'checked_in_at'  => 'datetime',
    ];

    public function drone()
    {
        return $this->belongsTo(Drone::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function isActive(): bool
    {
        return $this->checked_in_at === null;
    }

    public function durationMinutes(): ?int
    {
        if (!$this->checked_in_at) return null;
        return (int) $this->checked_out_at->diffInMinutes($this->checked_in_at);
    }

    /**
     * Za buduću USB integraciju: pronađi korisnika koji je zadužio dron
     * u trenutku kada je let obavljen, radi automatskog upisa leta.
     */
    public static function findPilotForFlight(int $droneId, Carbon $flightDate): ?int
    {
        $checkout = static::where('drone_id', $droneId)
            ->where('checked_out_at', '<=', $flightDate)
            ->where(function ($q) use ($flightDate) {
                $q->whereNull('checked_in_at')
                  ->orWhere('checked_in_at', '>=', $flightDate);
            })
            ->orderBy('checked_out_at', 'desc')
            ->first();

        return $checkout?->user_id;
    }
}
