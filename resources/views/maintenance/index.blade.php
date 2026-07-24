@extends('adminlte::page')

@section('title', __('ui.maintenance.title'))

@section('content_header')
    <h1>{{ __('ui.maintenance.logs_title') }}
        @if(!auth()->user()->hasRole('viewer'))
        <a href="{{ route('maintenance.create') }}" class="btn btn-primary btn-sm float-right">
            <i class="fas fa-plus"></i> {{ __('ui.maintenance.add_log') }}
        </a>
        @endif
    </h1>
@endsection

@section('content')
@if(session('success'))
    <div class="alert alert-success">{{ session('success') }}</div>
@endif
@if(session('error'))
    <div class="alert alert-danger">{{ session('error') }}</div>
@endif

<div class="card">
    <div class="card-body p-0">
        <table class="table table-striped">
            <thead>
                <tr>
                    <th>{{ __('ui.maintenance.drone') }}</th>
                    @if($showStationColumn)<th>{{ __('ui.users.station') }}</th>@endif
                    <th>{{ __('ui.maintenance.type') }}</th>
                    <th>{{ __('ui.maintenance.reported_by') }}</th>
                    <th>{{ __('ui.maintenance.date') }}</th>
                    <th>{{ __('ui.maintenance.status') }}</th>
                    <th>{{ __('ui.maintenance.actions') }}</th>
                </tr>
            </thead>
            <tbody>
                @forelse($logs as $log)
                <tr>
                    <td><strong>{{ $log->drone->name }}</strong></td>
                    @if($showStationColumn)
                    <td>
                        <small class="d-block text-muted">{{ $log->station->administration->name ?? '' }}</small>
                        {{ $log->station->name ?? '—' }}
                    </td>
                    @endif
                    <td>{{ ucfirst(str_replace('_', ' ', $log->type)) }}</td>
                    <td>{{ $log->reportedBy->name }}</td>
                    <td>{{ $log->created_at->translatedFormat('d F Y') }}</td>
                    <td>
                        <span class="badge badge-{{ $log->statusBadge() }}">
                            {{ __('ui.status.' . $log->status, [], null) ?: ucfirst(str_replace('_', ' ', $log->status)) }}
                        </span>
                    </td>
                    <td>
                        <a href="{{ route('maintenance.show', $log) }}" class="btn btn-xs btn-info">
                            <i class="fas fa-eye"></i>
                        </a>
                        @if(!auth()->user()->hasRole('viewer'))
                            @php
                                $canEdit = auth()->user()->hasRole('admin') ||
                                    (auth()->user()->hasRole('pilot') && $log->reported_by === auth()->id() && $log->isPendingReview());
                            @endphp
                            @if($canEdit)
                            <a href="{{ route('maintenance.edit', $log) }}" class="btn btn-xs btn-warning">
                                <i class="fas fa-edit"></i>
                            </a>
                            <form action="{{ route('maintenance.destroy', $log) }}" method="POST" style="display:inline"
                                onsubmit="return confirm('{{ __('ui.are_you_sure') }}')">
                                @csrf @method('DELETE')
                                <button class="btn btn-xs btn-danger"><i class="fas fa-trash"></i></button>
                            </form>
                            @endif
                        @endif
                    </td>
                </tr>
                @empty
                <tr><td colspan="{{ $showStationColumn ? 7 : 6 }}" class="text-center py-3">{{ __('ui.maintenance.no_logs') }}</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    @if(method_exists($logs, 'links'))
    <div class="card-footer">{{ $logs->links() }}</div>
    @endif
</div>
@endsection
