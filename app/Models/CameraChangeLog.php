<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CameraChangeLog extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'camera_id', 'user_id', 'action', 'changes', 'created_at',
    ];

    protected $casts = [
        'created_at' => 'datetime',
    ];

    public function camera()
    {
        return $this->belongsTo(HuntingCamera::class, 'camera_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
