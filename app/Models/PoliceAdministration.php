<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PoliceAdministration extends Model
{
    protected $fillable = ['name'];

    public function stations(): HasMany
    {
        return $this->hasMany(BorderPoliceStation::class, 'police_administration_id');
    }
}
