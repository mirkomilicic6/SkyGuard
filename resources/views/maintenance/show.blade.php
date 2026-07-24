@extends('adminlte::page')

@section('title', __('ui.maintenance.details'))

@section('content_header')
    <h1>{{ __('ui.maintenance.details') }}
        <a href="{{ route('maintenance.index') }}" class="btn btn-secondary btn-sm float-right">
            <i class="fas fa-arrow-left"></i> {{ __('ui.back') }}
        </a>
    </h1>
@endsection

@section('content')
@if(session('success'))
    <div class="alert alert-success">{{ session('success') }}</div>
@endif
@if(session('error'))
    <div class="alert alert-danger">{{ session('error') }}</div>
@endif

@php
    $isAdmin   = auth()->user()->hasRole('admin');
    $isPilot   = auth()->user()->hasRole('pilot');
    $isOwner   = $maintenance->reported_by === auth()->id();
@endphp

{{-- Workflow action banner for admin --}}
@if($isAdmin && !$maintenance->isResolved())
<div class="card mb-3" style="border-left: 4px solid var(--gold)">
    <div class="card-body py-2 d-flex align-items-center gap-3" style="gap:.75rem">
        <span class="font-weight-bold mr-2" style="color:var(--gold)">
            <i class="fas fa-tasks mr-1"></i> {{ __('ui.maintenance.workflow') }}:
        </span>
        @if($maintenance->isPendingReview())
        <form action="{{ route('maintenance.accept', $maintenance) }}" method="POST" class="d-inline">
            @csrf
            <button class="btn btn-sm btn-success">
                <i class="fas fa-check mr-1"></i> {{ __('ui.maintenance.accept') }}
            </button>
        </form>
        @elseif($maintenance->isOpen())
        <form action="{{ route('maintenance.start', $maintenance) }}" method="POST" class="d-inline">
            @csrf
            <button class="btn btn-sm btn-warning">
                <i class="fas fa-play mr-1"></i> {{ __('ui.maintenance.start_work') }}
            </button>
        </form>
        @elseif($maintenance->isInProgress())
        <form action="{{ route('maintenance.resolve', $maintenance) }}" method="POST" class="d-inline">
            @csrf
            <button class="btn btn-sm btn-primary">
                <i class="fas fa-flag-checkered mr-1"></i> {{ __('ui.maintenance.mark_resolved') }}
            </button>
        </form>
        @endif
    </div>
</div>
@endif

<div class="card">
    <div class="card-body">
        <div class="row">
            <div class="col-md-6">
                <table class="table table-sm">
                    <tr>
                        <th>{{ __('ui.maintenance.drone') }}</th>
                        <td><a href="{{ route('drones.show', $maintenance->drone) }}">{{ $maintenance->drone->name }}</a></td>
                    </tr>
                    <tr>
                        <th>{{ __('ui.maintenance.type') }}</th>
                        <td>{{ ucfirst(str_replace('_', ' ', $maintenance->type)) }}</td>
                    </tr>
                    <tr>
                        <th>{{ __('ui.maintenance.reported_by') }}</th>
                        <td>{{ $maintenance->reportedBy->name }}</td>
                    </tr>
                    <tr>
                        <th>{{ __('ui.maintenance.date_reported') }}</th>
                        <td>{{ $maintenance->created_at->translatedFormat('d F Y H:i') }}</td>
                    </tr>
                    <tr>
                        <th>{{ __('ui.maintenance.cost') }}</th>
                        <td>{{ $maintenance->cost ? '€' . number_format($maintenance->cost, 2) : '-' }}</td>
                    </tr>
                    <tr>
                        <th>{{ __('ui.maintenance.status') }}</th>
                        <td>
                            <span class="badge badge-{{ $maintenance->statusBadge() }}">
                                {{ __('ui.status.' . $maintenance->status, [], null) ?: ucfirst(str_replace('_', ' ', $maintenance->status)) }}
                            </span>
                        </td>
                    </tr>
                    @if($maintenance->resolvedBy)
                    <tr>
                        <th>{{ __('ui.maintenance.resolved_by') }}</th>
                        <td>{{ $maintenance->resolvedBy->name }}</td>
                    </tr>
                    <tr>
                        <th>{{ __('ui.maintenance.resolved_at') }}</th>
                        <td>{{ $maintenance->resolved_at->translatedFormat('d F Y H:i') }}</td>
                    </tr>
                    @endif
                </table>
            </div>
            <div class="col-md-6">
                <h6>{{ __('ui.maintenance.description') }}</h6>
                <p>{{ $maintenance->description }}</p>
                @if($maintenance->parts_replaced)
                    <h6>{{ __('ui.maintenance.parts_replaced') }}</h6>
                    <p>{{ $maintenance->parts_replaced }}</p>
                @endif
            </div>
        </div>

        @php
            $canEdit = $isAdmin || ($isPilot && $isOwner && $maintenance->isPendingReview());
        @endphp
        @if($canEdit)
        <hr>
        <a href="{{ route('maintenance.edit', $maintenance) }}" class="btn btn-warning">
            <i class="fas fa-edit"></i> {{ __('ui.edit') }}
        </a>
        <form action="{{ route('maintenance.destroy', $maintenance) }}" method="POST" style="display:inline"
            onsubmit="return confirm('{{ __('ui.are_you_sure') }}')">
            @csrf @method('DELETE')
            <button class="btn btn-danger"><i class="fas fa-trash"></i> {{ __('ui.delete') }}</button>
        </form>
        @endif
    </div>
</div>
@endsection
