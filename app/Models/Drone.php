<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Drone extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'name', 'serial_number', 'model', 'manufacturer',
        'purchase_date', 'status', 'photo', 'notes', 'station_id',
    ];

    protected $casts = [
        'purchase_date' => 'date',
    ];

    public function station()
    {
        return $this->belongsTo(BorderPoliceStation::class, 'station_id');
    }

    public function pilots()
    {
        return $this->belongsToMany(User::class);
    }

    public function flights()
    {
        return $this->hasMany(Flight::class);
    }

    public function maintenanceLogs()
    {
        return $this->hasMany(MaintenanceLog::class);
    }

    public function totalFlightMinutes()
    {
        return $this->flights()->sum('duration_minutes');
    }

    public function totalFlightMinutesThisMonth()
    {
        return $this->flights()
            ->whereMonth('flight_date', now()->month)
            ->whereYear('flight_date', now()->year)
            ->sum('duration_minutes');
    }

    public function totalFlightMinutesToday()
    {
        return $this->flights()
            ->whereDate('flight_date', today())
            ->sum('duration_minutes');
    }
}
