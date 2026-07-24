@extends('adminlte::page')

@section('title', $drone->name)

@section('content_header')
    <h1>{{ $drone->name }}
        @can('manage drones')
        <a href="{{ route('drones.edit', $drone) }}" class="btn btn-warning btn-sm float-right ml-1">
            <i class="fas fa-edit"></i> {{ __('ui.edit') }}
        </a>
        @endcan
        <a href="{{ route('drones.index') }}" class="btn btn-secondary btn-sm float-right">
            <i class="fas fa-arrow-left"></i> {{ __('ui.back') }}
        </a>
    </h1>
@endsection

@section('content')
<div class="row">
    <div class="col-md-4">
        <div class="card card-primary card-outline">
            <div class="card-body text-center">
                @if($drone->photo)
                    <img src="{{ asset('storage/' . $drone->photo) }}" class="img-fluid rounded mb-3" style="max-height:200px; object-fit:cover;">
                @else
                    <i class="fas fa-helicopter" style="font-size:80px; color:#ccc;"></i>
                @endif
                <h4>{{ $drone->name }}</h4>
                <span class="badge badge-{{ $drone->status === 'active' ? 'success' : ($drone->status === 'in_maintenance' ? 'warning' : 'danger') }} mb-2">
                    {{ __('ui.status.' . $drone->status, [], null) ?: ucfirst(str_replace('_', ' ', $drone->status)) }}
                </span>
                <table class="table table-sm text-left mt-3">
                    <tr><th>{{ __('ui.drones.serial_short') }}</th><td>{{ $drone->serial_number }}</td></tr>
                    <tr><th>{{ __('ui.drones.model_short') }}</th><td>{{ $drone->model }}</td></tr>
                    <tr><th>{{ __('ui.drones.manufacturer_short') }}</th><td>{{ $drone->manufacturer ?? '-' }}</td></tr>
                    <tr><th>{{ __('ui.drones.purchase_short') }}</th><td>{{ $drone->purchase_date?->translatedFormat('d F Y') ?? '-' }}</td></tr>
                </table>
            </div>
        </div>

        <div class="card">
            <div class="card-header"><h3 class="card-title">{{ __('ui.drones.flight_stats') }}</h3></div>
            <div class="card-body p-0">
                <ul class="list-group list-group-flush">
                    <li class="list-group-item d-flex justify-content-between">
                        <span>{{ __('ui.drones.today') }}</span>
                        <strong>{{ $drone->totalFlightMinutesToday() }} min</strong>
                    </li>
                    <li class="list-group-item d-flex justify-content-between">
                        <span>{{ __('ui.drones.this_month') }}</span>
                        <strong>{{ $drone->totalFlightMinutesThisMonth() }} min</strong>
                    </li>
                    <li class="list-group-item d-flex justify-content-between">
                        <span>{{ __('ui.drones.all_time') }}</span>
                        <strong>{{ $drone->totalFlightMinutes() }} min</strong>
                    </li>
                </ul>
            </div>
        </div>
    </div>

    <div class="col-md-8">
        <div class="card">
            <div class="card-header"><h3 class="card-title">{{ __('ui.drones.assigned_pilots') }}</h3></div>
            <div class="card-body">
                @forelse($drone->pilots as $pilot)
                    <span class="badge badge-info mr-1">{{ $pilot->name }}</span>
                @empty
                    <span class="text-muted">{{ __('ui.drones.no_pilots') }}</span>
                @endforelse
            </div>
        </div>

        <div class="card">
            <div class="card-header">
                <h3 class="card-title">{{ __('ui.dashboard.recent_flights') }}</h3>
                <div class="card-tools">
                    <a href="{{ route('flights.index') }}" class="btn btn-xs btn-info">{{ __('ui.view_all') }}</a>
                </div>
            </div>
            <div class="card-body p-0">
                <table class="table table-striped">
                    <thead>
                        <tr>
                            <th>{{ __('ui.flights.date') }}</th>
                            <th>{{ __('ui.flights.pilot') }}</th>
                            <th>{{ __('ui.flights.duration') }}</th>
                            <th>{{ __('ui.flights.status') }}</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($recentFlights as $flight)
                        <tr>
                            <td>{{ $flight->flight_date->translatedFormat('d F Y') }}</td>
                            <td>{{ $flight->pilot->name }}</td>
                            <td>{{ $flight->duration_minutes }} min</td>
                            <td>
                                <span class="badge badge-{{ $flight->status === 'completed' ? 'success' : ($flight->status === 'aborted' ? 'danger' : 'warning') }}">
                                    {{ __('ui.status.' . $flight->status, [], null) ?: ucfirst($flight->status) }}
                                </span>
                            </td>
                            <td><a href="{{ route('flights.show', $flight) }}" class="btn btn-xs btn-info">{{ __('ui.view') }}</a></td>
                        </tr>
                        @empty
                        <tr><td colspan="5" class="text-center">{{ __('ui.users.no_flights') }}</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        @if($drone->notes)
        <div class="card">
            <div class="card-header"><h3 class="card-title">{{ __('ui.drones.notes') }}</h3></div>
            <div class="card-body">{{ $drone->notes }}</div>
        </div>
        @endif
    </div>
</div>
@endsection
