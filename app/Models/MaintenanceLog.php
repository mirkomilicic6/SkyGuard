<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class MaintenanceLog extends Model
{
    use HasFactory;

    const STATUS_PENDING     = 'pending_review';
    const STATUS_OPEN        = 'open';
    const STATUS_IN_PROGRESS = 'in_progress';
    const STATUS_RESOLVED    = 'resolved';

    protected $fillable = [
        'drone_id', 'station_id', 'reported_by', 'type', 'description',
        'parts_replaced', 'cost', 'status', 'resolved_by', 'resolved_at',
    ];

    protected $casts = [
        'resolved_at' => 'datetime',
    ];

    public function drone()
    {
        return $this->belongsTo(Drone::class);
    }

    public function station()
    {
        return $this->belongsTo(BorderPoliceStation::class, 'station_id');
    }

    public function reportedBy()
    {
        return $this->belongsTo(User::class, 'reported_by');
    }

    public function resolvedBy()
    {
        return $this->belongsTo(User::class, 'resolved_by');
    }

    public function isPendingReview(): bool  { return $this->status === self::STATUS_PENDING; }
    public function isOpen(): bool           { return $this->status === self::STATUS_OPEN; }
    public function isInProgress(): bool     { return $this->status === self::STATUS_IN_PROGRESS; }
    public function isResolved(): bool       { return $this->status === self::STATUS_RESOLVED; }

    public function isBlocking(): bool
    {
        return in_array($this->status, [self::STATUS_PENDING, self::STATUS_OPEN, self::STATUS_IN_PROGRESS]);
    }

    public function statusBadge(): string
    {
        return match($this->status) {
            self::STATUS_PENDING     => 'secondary',
            self::STATUS_OPEN        => 'danger',
            self::STATUS_IN_PROGRESS => 'warning',
            self::STATUS_RESOLVED    => 'success',
            default                  => 'secondary',
        };
    }
}
