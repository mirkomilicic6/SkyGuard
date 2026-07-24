<?php

namespace App\Models;

use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Spatie\Permission\Traits\HasRoles;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class User extends Authenticatable
{
    use HasFactory, Notifiable, HasRoles;

    protected $fillable = ['name', 'email', 'password', 'station_id'];

    protected $hidden = ['password', 'remember_token'];

    protected $casts = [
        'email_verified_at' => 'datetime',
        'password' => 'hashed',
    ];

    public function station()
    {
        return $this->belongsTo(BorderPoliceStation::class, 'station_id');
    }

    public function drones()
    {
        return $this->belongsToMany(Drone::class);
    }

    public function flights()
    {
        return $this->hasMany(Flight::class);
    }

    public function maintenanceLogs()
    {
        return $this->hasMany(MaintenanceLog::class, 'reported_by');
    }

    public function totalFlightMinutes()
    {
        return $this->flights()->sum('duration_minutes');
    }
}
