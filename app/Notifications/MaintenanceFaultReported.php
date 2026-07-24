<?php

namespace App\Notifications;

use App\Models\MaintenanceLog;
use App\Models\User;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Str;

class MaintenanceFaultReported extends Notification
{
    public function __construct(
        public readonly MaintenanceLog $log,
        public readonly User $reporter
    ) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toDatabase(object $notifiable): array
    {
        $typeLabels = [
            'routine'          => 'Redovni servis',
            'repair'           => 'Popravak',
            'inspection'       => 'Inspekcija',
            'part_replacement' => 'Zamjena dijelova',
        ];

        return [
            'type'           => 'fault_reported',
            'maintenance_id' => $this->log->id,
            'reporter_name'  => $this->reporter->name,
            'drone_name'     => $this->log->drone->name,
            'fault_type'     => $typeLabels[$this->log->type] ?? $this->log->type,
            'description'    => Str::limit($this->log->description, 80),
        ];
    }
}
