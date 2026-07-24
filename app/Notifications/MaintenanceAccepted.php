<?php

namespace App\Notifications;

use App\Models\MaintenanceLog;
use Illuminate\Notifications\Notification;

class MaintenanceAccepted extends Notification
{
    public function __construct(public readonly MaintenanceLog $log) {}

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
            'type'           => 'fault_accepted',
            'maintenance_id' => $this->log->id,
            'drone_name'     => $this->log->drone->name,
            'fault_type'     => $typeLabels[$this->log->type] ?? $this->log->type,
        ];
    }
}
