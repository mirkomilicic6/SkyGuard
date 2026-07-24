<?php

namespace App\Notifications;

use App\Models\Flight;
use App\Models\User;
use Illuminate\Notifications\Notification;

class FlightUploaded extends Notification
{
    public function __construct(
        public readonly Flight $flight,
        public readonly User $pilot
    ) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toDatabase(object $notifiable): array
    {
        return [
            'type'        => 'flight_uploaded',
            'flight_id'   => $this->flight->id,
            'pilot_name'  => $this->pilot->name,
            'drone_name'  => $this->flight->drone->name,
            'flight_date' => $this->flight->flight_date->translatedFormat('d F Y'),
            'location'    => $this->flight->location,
        ];
    }
}
