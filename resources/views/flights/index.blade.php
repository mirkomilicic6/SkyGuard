@extends('adminlte::page')

@section('title', __('ui.flights.title'))

@section('content_header')
    <h1>{{ __('ui.flights.flight_logs') }}
        @if(!auth()->user()->hasRole('viewer'))
        <a href="{{ route('flights.create') }}" class="btn btn-primary btn-sm float-right">
            <i class="fas fa-plus"></i> {{ __('ui.flights.log_flight') }}
        </a>
        @endif
    </h1>
@endsection

@section('content')
@if(session('success'))
    <div class="alert alert-success">{{ session('success') }}</div>
@endif

{{-- Date filter --}}
<div class="card card-outline card-primary">
    <div class="card-body py-2">
        <form method="GET" action="{{ route('flights.index') }}" class="form-inline flex-wrap" style="gap:0.5rem;">
            <label class="mr-2 mb-0 font-weight-bold">{{ __('ui.filter_by_date') }}</label>
            <div class="input-group input-group-sm mr-2">
                <div class="input-group-prepend"><span class="input-group-text">{{ __('ui.from') }}</span></div>
                <input type="date" name="date_from" class="form-control form-control-sm"
                    value="{{ $dateFrom }}" max="{{ $dateTo ?: date('Y-m-d') }}">
            </div>
            <div class="input-group input-group-sm mr-2">
                <div class="input-group-prepend"><span class="input-group-text">{{ __('ui.to') }}</span></div>
                <input type="date" name="date_to" class="form-control form-control-sm"
                    value="{{ $dateTo }}" min="{{ $dateFrom }}" max="{{ date('Y-m-d') }}">
            </div>
            <button type="submit" class="btn btn-primary btn-sm mr-1">
                <i class="fas fa-filter"></i> {{ __('ui.apply') }}
            </button>
            @if($dateFrom || $dateTo)
            <a href="{{ route('flights.index') }}" class="btn btn-secondary btn-sm">
                <i class="fas fa-times"></i> {{ __('ui.clear') }}
            </a>
            <span class="badge badge-info ml-2 align-self-center">
                {{ $flights->total() }} {{ __('ui.results') }}
                {{ $dateFrom ? __('ui.from') . ' ' . \Carbon\Carbon::parse($dateFrom)->translatedFormat('d F Y') : '' }}
                {{ $dateTo   ? __('ui.to') . ' ' . \Carbon\Carbon::parse($dateTo)->translatedFormat('d F Y') : '' }}
            </span>
            @endif
        </form>
    </div>
</div>

<div class="card">
    <div class="card-body p-0">
        <table class="table table-striped">
            <thead>
                <tr>
                    <th>{{ __('ui.flights.drone') }}</th>
                    <th>{{ __('ui.flights.pilot') }}</th>
                    @if($showStationColumn)<th>{{ __('ui.users.station') }}</th>@endif
                    <th>{{ __('ui.flights.date') }}</th>
                    <th>{{ __('ui.flights.duration') }}</th>
                    <th>{{ __('ui.flights.location') }}</th>
                    <th>{{ __('ui.flights.status') }}</th>
                    <th>{{ __('ui.flights.actions') }}</th>
                </tr>
            </thead>
            <tbody>
                @forelse($flights as $flight)
                <tr>
                    <td>{{ $flight->drone->name }}</td>
                    <td>{{ $flight->pilot->name }}</td>
                    @if($showStationColumn)
                    <td>
                        <small class="d-block text-muted">{{ $flight->station->administration->name ?? '' }}</small>
                        {{ $flight->station->name ?? '—' }}
                    </td>
                    @endif
                    <td>{{ $flight->flight_date->translatedFormat('d F Y H:i') }}</td>
                    <td>{{ $flight->duration_minutes ? $flight->duration_minutes . ' min' : '-' }}</td>
                    <td>{{ $flight->location ?? '-' }}</td>
                    <td>
                        <span class="badge badge-{{ $flight->status === 'completed' ? 'success' : ($flight->status === 'aborted' ? 'danger' : 'warning') }}">
                            {{ __('ui.status.' . $flight->status, [], null) ?: ucfirst(str_replace('_', ' ', $flight->status)) }}
                        </span>
                    </td>
                    <td>
                        <a href="{{ route('flights.show', $flight) }}" class="btn btn-xs btn-info">
                            <i class="fas fa-eye"></i>
                        </a>
                        @if(!auth()->user()->hasRole('viewer'))
                        <a href="{{ route('flights.edit', $flight) }}" class="btn btn-xs btn-warning">
                            <i class="fas fa-edit"></i>
                        </a>
                        @endif
                        @if(auth()->user()->hasRole('admin'))
                        <form action="{{ route('flights.destroy', $flight) }}" method="POST" style="display:inline"
                            onsubmit="return confirm('{{ __('ui.are_you_sure') }}')">
                            @csrf @method('DELETE')
                            <button class="btn btn-xs btn-danger"><i class="fas fa-trash"></i></button>
                        </form>
                        @endif
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="{{ $showStationColumn ? 8 : 7 }}" class="text-center text-muted py-3">
                        @if($dateFrom || $dateTo)
                            {{ __('ui.flights.no_flights_range') }}
                        @else
                            {{ __('ui.flights.no_flights') }}
                        @endif
                    </td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <div class="card-footer">
        {{ $flights->links() }}
    </div>
</div>
@endsection
