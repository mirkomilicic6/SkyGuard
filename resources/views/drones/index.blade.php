@extends('adminlte::page')

@section('title', __('ui.drones.title'))

@section('content_header')
    <h1>{{ __('ui.drones.title') }}
        @can('manage drones')
        <a href="{{ route('drones.create') }}" class="btn btn-primary btn-sm float-right">
            <i class="fas fa-plus"></i> {{ __('ui.drones.add_drone') }}
        </a>
        @endcan
    </h1>
@endsection

@section('content')
@if(session('success'))
    <div class="alert alert-success">{{ session('success') }}</div>
@endif

<div class="card">
    <div class="card-body p-0">
        <table class="table table-striped">
            <thead>
                <tr>
                    <th>{{ __('ui.drones.photo') }}</th>
                    <th>{{ __('ui.drones.name') }}</th>
                    <th>{{ __('ui.drones.serial_number') }}</th>
                    <th>{{ __('ui.drones.model') }}</th>
                    @if($showStationColumn)<th>{{ __('ui.users.station') }}</th>@endif
                    <th>{{ __('ui.flights.status') }}</th>
                    <th>{{ __('ui.drones.flights') }}</th>
                    <th>{{ __('ui.flights.actions') }}</th>
                </tr>
            </thead>
            <tbody>
                @forelse($drones as $drone)
                <tr>
                    <td>
                        @if($drone->photo)
                            <img src="{{ asset('storage/' . $drone->photo) }}" width="50" height="50" style="object-fit:cover; border-radius:5px;">
                        @else
                            <i class="fas fa-helicopter fa-2x text-muted"></i>
                        @endif
                    </td>
                    <td><strong>{{ $drone->name }}</strong></td>
                    <td>{{ $drone->serial_number }}</td>
                    <td>{{ $drone->model }}</td>
                    @if($showStationColumn)
                    <td>
                        <small class="d-block text-muted">{{ $drone->station->administration->name ?? '' }}</small>
                        {{ $drone->station->name ?? '—' }}
                    </td>
                    @endif
                    <td>
                        <span class="badge badge-{{ $drone->status === 'active' ? 'success' : ($drone->status === 'in_maintenance' ? 'warning' : 'danger') }}">
                            {{ __('ui.status.' . $drone->status, [], null) ?: ucfirst(str_replace('_', ' ', $drone->status)) }}
                        </span>
                    </td>
                    <td>{{ $drone->flights_count }}</td>
                    <td>
                        <a href="{{ route('drones.show', $drone) }}" class="btn btn-xs btn-info">
                            <i class="fas fa-eye"></i>
                        </a>
                        @can('manage drones')
                        <a href="{{ route('drones.edit', $drone) }}" class="btn btn-xs btn-warning">
                            <i class="fas fa-edit"></i>
                        </a>
                        <form action="{{ route('drones.destroy', $drone) }}" method="POST" style="display:inline"
                            onsubmit="return confirm('{{ __('ui.are_you_sure') }}')">
                            @csrf @method('DELETE')
                            <button class="btn btn-xs btn-danger"><i class="fas fa-trash"></i></button>
                        </form>
                        @endcan
                    </td>
                </tr>
                @empty
                <tr><td colspan="{{ $showStationColumn ? 8 : 7 }}" class="text-center">{{ __('ui.drones.no_drones') }}</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    <div class="card-footer">
        {{ $drones->links() }}
    </div>
</div>
@endsection
