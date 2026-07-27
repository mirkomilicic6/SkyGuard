<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PoliceAdministration extends Model
{
    protected $fillable = ['name', 'boundary'];

    protected $casts = [
        'boundary' => 'array',
    ];

    public function stations(): HasMany
    {
        return $this->hasMany(BorderPoliceStation::class, 'police_administration_id');
    }

    public function hasBoundary(): bool
    {
        return !empty($this->boundary) && count($this->boundary) >= 3;
    }
}
