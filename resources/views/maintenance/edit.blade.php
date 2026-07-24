@extends('adminlte::page')

@section('title', __('ui.maint_form.edit_title'))

@section('content_header')
    <h1>{{ __('ui.maint_form.edit_title') }}</h1>
@endsection

@section('content')
@php $isPilot = auth()->user()->hasRole('pilot'); @endphp

<div class="card">
    <div class="card-body">
        <form action="{{ route('maintenance.update', $maintenance) }}" method="POST">
            @csrf @method('PUT')
            <div class="row">
                @if(!$isPilot)
                <div class="col-md-6">
                    <div class="form-group">
                        <label>{{ __('ui.maint_form.drone_required') }}</label>
                        <select name="drone_id" class="form-control" required>
                            @foreach($drones as $drone)
                                <option value="{{ $drone->id }}" {{ $maintenance->drone_id == $drone->id ? 'selected' : '' }}>
                                    {{ $drone->serial_number }} — {{ $drone->name }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                </div>
                @else
                <div class="col-md-6">
                    <div class="form-group">
                        <label>{{ __('ui.maint_form.drone_required') }}</label>
                        <input type="text" class="form-control" value="{{ $maintenance->drone->name }}" readonly>
                    </div>
                </div>
                @endif
                <div class="col-md-6">
                    <div class="form-group">
                        <label>{{ __('ui.maint_form.type_required') }}</label>
                        <select name="type" class="form-control" required>
                            <option value="repair"          {{ $maintenance->type === 'repair'          ? 'selected' : '' }}>{{ __('ui.maint_form.type_repair') }}</option>
                            <option value="routine"         {{ $maintenance->type === 'routine'         ? 'selected' : '' }}>{{ __('ui.maint_form.type_routine') }}</option>
                            <option value="inspection"      {{ $maintenance->type === 'inspection'      ? 'selected' : '' }}>{{ __('ui.maint_form.type_inspect') }}</option>
                            <option value="part_replacement"{{ $maintenance->type === 'part_replacement'? 'selected' : '' }}>{{ __('ui.maint_form.type_part') }}</option>
                        </select>
                    </div>
                </div>
                <div class="col-12">
                    <div class="form-group">
                        <label>{{ __('ui.maint_form.desc_required') }}</label>
                        <textarea name="description" class="form-control" rows="4" required>{{ $maintenance->description }}</textarea>
                    </div>
                </div>
                @if(!$isPilot)
                <div class="col-md-6">
                    <div class="form-group">
                        <label>{{ __('ui.maint_form.cost') }}</label>
                        <input type="number" name="cost" class="form-control"
                            value="{{ $maintenance->cost }}" step="0.01" min="0">
                    </div>
                </div>
                <div class="col-12">
                    <div class="form-group">
                        <label>{{ __('ui.maint_form.parts') }}</label>
                        <textarea name="parts_replaced" class="form-control" rows="2">{{ $maintenance->parts_replaced }}</textarea>
                    </div>
                </div>
                @endif
            </div>
            <button type="submit" class="btn btn-primary">
                <i class="fas fa-save"></i> {{ __('ui.save') }}
            </button>
            <a href="{{ route('maintenance.show', $maintenance) }}" class="btn btn-secondary">{{ __('ui.cancel') }}</a>
        </form>
    </div>
</div>
@endsection
