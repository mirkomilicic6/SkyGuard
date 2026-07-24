<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class HuntingCamera extends Model
{
    use HasFactory;

    protected $fillable = [
        'station_id', 'name', 'location_name', 'latitude', 'longitude',
        'notes', 'is_active', 'last_updated_by',
    ];

    protected $casts = [
        'latitude'  => 'decimal:7',
        'longitude' => 'decimal:7',
        'is_active' => 'boolean',
    ];

    public function station()
    {
        return $this->belongsTo(BorderPoliceStation::class, 'station_id');
    }

    public function lastUpdatedBy()
    {
        return $this->belongsTo(User::class, 'last_updated_by');
    }

    public function changeLogs()
    {
        return $this->hasMany(CameraChangeLog::class, 'camera_id')->latest();
    }
}
