<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class GpxPoint extends Model
{
    protected $fillable = [
        'flight_id', 'latitude', 'longitude',
        'altitude', 'speed', 'timestamp', 'point_order',
    ];

    protected $casts = [
        'timestamp' => 'datetime',
    ];

    public function flight()
    {
        return $this->belongsTo(Flight::class);
    }
}
