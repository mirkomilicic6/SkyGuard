@extends('adminlte::page')

@section('title', __('ui.maint_form.add_title'))

@section('content_header')
    <h1>{{ __('ui.maint_form.add_title') }}</h1>
@endsection

@section('content')
@php $isPilot = auth()->user()->hasRole('pilot'); @endphp

@if($isPilot)
<div class="alert alert-info">
    <i class="fas fa-info-circle mr-1"></i>
    {{ __('ui.maintenance.pilot_create_info') }}
</div>
@endif

<div class="card">
    <div class="card-body">
        <form action="{{ route('maintenance.store') }}" method="POST">
            @csrf
            <div class="row">
                <div class="col-md-6">
                    <div class="form-group">
                        <label>{{ __('ui.maint_form.drone_required') }}</label>
                        <select name="drone_id" class="form-control @error('drone_id') is-invalid @enderror" required>
                            <option value="">{{ __('ui.maint_form.select_drone') }}</option>
                            @foreach($drones as $drone)
                                <option value="{{ $drone->id }}" {{ old('drone_id') == $drone->id ? 'selected' : '' }}>
                                    {{ $drone->serial_number }} — {{ $drone->name }}
                                    @if($drone->status === 'in_maintenance') ({{ __('ui.maint_form.in_maint') }}) @endif
                                </option>
                            @endforeach
                        </select>
                        @error('drone_id')<span class="invalid-feedback">{{ $message }}</span>@enderror
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="form-group">
                        <label>{{ __('ui.maint_form.type_required') }}</label>
                        <select name="type" class="form-control" required>
                            <option value="repair" {{ old('type') === 'repair' ? 'selected' : '' }}>{{ __('ui.maint_form.type_repair') }}</option>
                            <option value="routine" {{ old('type') === 'routine' ? 'selected' : '' }}>{{ __('ui.maint_form.type_routine') }}</option>
                            <option value="inspection" {{ old('type') === 'inspection' ? 'selected' : '' }}>{{ __('ui.maint_form.type_inspect') }}</option>
                            <option value="part_replacement" {{ old('type') === 'part_replacement' ? 'selected' : '' }}>{{ __('ui.maint_form.type_part') }}</option>
                        </select>
                    </div>
                </div>
                <div class="col-12">
                    <div class="form-group">
                        <label>{{ __('ui.maint_form.desc_required') }}</label>
                        <textarea name="description" class="form-control @error('description') is-invalid @enderror"
                            rows="4" required placeholder="{{ __('ui.maint_form.desc_placeholder') }}">{{ old('description') }}</textarea>
                        @error('description')<span class="invalid-feedback">{{ $message }}</span>@enderror
                    </div>
                </div>
                @if(!$isPilot)
                <div class="col-md-6">
                    <div class="form-group">
                        <label>{{ __('ui.maint_form.cost') }}</label>
                        <input type="number" name="cost" class="form-control"
                            value="{{ old('cost') }}" step="0.01" min="0">
                    </div>
                </div>
                <div class="col-12">
                    <div class="form-group">
                        <label>{{ __('ui.maint_form.parts') }}</label>
                        <textarea name="parts_replaced" class="form-control" rows="2">{{ old('parts_replaced') }}</textarea>
                    </div>
                </div>
                @endif
            </div>
            <button type="submit" class="btn btn-primary">
                <i class="fas fa-save"></i>
                {{ $isPilot ? __('ui.maintenance.report_fault') : __('ui.maint_form.save_log') }}
            </button>
            <a href="{{ route('maintenance.index') }}" class="btn btn-secondary">{{ __('ui.cancel') }}</a>
        </form>
    </div>
</div>
@endsection
