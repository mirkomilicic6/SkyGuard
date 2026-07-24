@extends('adminlte::page')

@section('title', $user->name)

@section('content_header')
    <h1>{{ $user->name }}
        @if(!auth()->user()->hasRole('viewer'))
        <a href="{{ route('users.edit', $user) }}" class="btn btn-warning btn-sm float-right ml-1">
            <i class="fas fa-edit"></i> {{ __('ui.edit') }}
        </a>
        @endif
        <a href="{{ route('users.index') }}" class="btn btn-secondary btn-sm float-right">
            <i class="fas fa-arrow-left"></i> {{ __('ui.back') }}
        </a>
    </h1>
@endsection

@section('content')
@if(session('success'))
    <div class="alert alert-success">{{ session('success') }}</div>
@endif

<div class="row">
    <div class="col-md-4">
        <div class="card card-primary card-outline">
            <div class="card-body text-center">
                <i class="fas fa-user-circle" style="font-size:80px; color:#ccc;"></i>
                <h4 class="mt-2">{{ $user->name }}</h4>
                <p class="text-muted">{{ $user->email }}</p>
                @foreach($user->roles as $role)
                    <span class="badge badge-{{ $role->name === 'admin' ? 'danger' : ($role->name === 'pilot' ? 'primary' : 'secondary') }} mb-2">
                        {{ ucfirst($role->name) }}
                    </span>
                @endforeach
                <table class="table table-sm text-left mt-3">
                    <tr><th>{{ __('ui.users.joined_label') }}</th><td>{{ $user->created_at->translatedFormat('d F Y') }}</td></tr>
                    <tr><th>{{ __('ui.users.email_verified') }}</th><td>{{ $user->email_verified_at ? $user->email_verified_at->translatedFormat('d F Y') : __('ui.users.not_verified') }}</td></tr>
                    @if($user->station)
                    <tr><th>{{ __('ui.users.administration') }}</th><td>{{ $user->station->administration->name ?? '—' }}</td></tr>
                    <tr><th>{{ __('ui.users.station') }}</th><td>{{ $user->station->name }}</td></tr>
                    @endif
                </table>
            </div>
        </div>

        <div class="card">
            <div class="card-header"><h3 class="card-title">{{ __('ui.users.flight_stats') }}</h3></div>
            <div class="card-body p-0">
                <ul class="list-group list-group-flush">
                    <li class="list-group-item d-flex justify-content-between">
                        <span>{{ __('ui.users.total_flights') }}</span>
                        <strong>{{ $user->flights->count() }}</strong>
                    </li>
                    <li class="list-group-item d-flex justify-content-between">
                        <span>{{ __('ui.users.total_time') }}</span>
                        <strong>{{ $user->totalFlightMinutes() }} min</strong>
                    </li>
                    <li class="list-group-item d-flex justify-content-between">
                        <span>{{ __('ui.users.maintenance_reported') }}</span>
                        <strong>{{ $user->maintenanceLogs->count() }}</strong>
                    </li>
                    <li class="list-group-item d-flex justify-content-between">
                        <span>{{ __('ui.users.assigned_drones') }}</span>
                        <strong>{{ $user->drones->count() }}</strong>
                    </li>
                </ul>
            </div>
        </div>
    </div>

    <div class="col-md-8">
        <div class="card">
            <div class="card-header"><h3 class="card-title">{{ __('ui.users.assigned_drones') }}</h3></div>
            <div class="card-body">
                @forelse($user->drones as $drone)
                    <a href="{{ route('drones.show', $drone) }}" class="badge badge-info mr-1" style="font-size:0.85rem;">
                        {{ $drone->name }}
                    </a>
                @empty
                    <span class="text-muted">{{ __('ui.users.no_drones') }}</span>
                @endforelse
            </div>
        </div>

        <div class="card">
            <div class="card-header">
                <h3 class="card-title">{{ __('ui.users.recent_flights') }}</h3>
                <div class="card-tools">
                    <a href="{{ route('flights.index') }}" class="btn btn-xs btn-info">{{ __('ui.view_all') }}</a>
                </div>
            </div>
            <div class="card-body p-0">
                <table class="table table-striped">
                    <thead>
                        <tr>
                            <th>{{ __('ui.users.date') }}</th>
                            <th>{{ __('ui.users.drone') }}</th>
                            <th>{{ __('ui.users.duration') }}</th>
                            <th>{{ __('ui.users.status') }}</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        @forelse($recentFlights as $flight)
                        <tr>
                            <td>{{ $flight->flight_date->translatedFormat('d F Y') }}</td>
                            <td>{{ $flight->drone->name }}</td>
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
    </div>
</div>
@endsection
