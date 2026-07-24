@extends('adminlte::page')

@section('title', __('ui.edit') . ' — ' . __('ui.flights.title'))

@section('content_header')
    <h1>{{ __('ui.edit') }} — {{ __('ui.flights.title') }}</h1>
@endsection

@section('content')
<div class="card">
    <div class="card-body">
        <form action="{{ route('flights.update', $flight) }}" method="POST" enctype="multipart/form-data">
            @csrf @method('PUT')
            <div class="row">
                <div class="col-md-6">
                    <div class="form-group">
                        <label>{{ __('ui.flights.drone_required') }}</label>
                        <select name="drone_id" class="form-control @error('drone_id') is-invalid @enderror" required>
                            @foreach($drones as $drone)
                                <option value="{{ $drone->id }}" {{ $flight->drone_id == $drone->id ? 'selected' : '' }}>
                                    {{ $drone->name }} ({{ $drone->serial_number }})
                                </option>
                            @endforeach
                        </select>
                        @error('drone_id')<span class="invalid-feedback">{{ $message }}</span>@enderror
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="form-group">
                        <label>{{ __('ui.flights.gpx_file') }}</label>
                        @if($flight->gpx_file_path)
                            <p class="text-muted mb-1" style="font-size:0.85rem;">
                                <i class="fas fa-check-circle text-success mr-1"></i>
                                {{ $flight->gpxPoints->count() }} {{ __('ui.flights.gpx_points') }}
                            </p>
                        @endif
                        <input type="file" name="gpx_file" class="form-control-file @error('gpx_file') is-invalid @enderror" accept=".gpx,.xml">
                        <small class="text-muted">{{ __('ui.flights.gpx_hint') }}</small>
                        @error('gpx_file')<span class="invalid-feedback d-block">{{ $message }}</span>@enderror
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="form-group">
                        <label>{{ __('ui.flights.location') }}</label>
                        <input type="text" name="location" class="form-control"
                            value="{{ old('location', $flight->location) }}" placeholder="{{ __('ui.flights.location_placeholder') }}">
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="form-group">
                        <label>{{ __('ui.flights.notes') }}</label>
                        <input type="text" name="purpose" class="form-control"
                            value="{{ old('purpose', $flight->purpose) }}" placeholder="{{ __('ui.flights.notes_placeholder') }}">
                    </div>
                </div>
            </div>
            <button type="submit" class="btn btn-primary">
                <i class="fas fa-save"></i> {{ __('ui.save') }}
            </button>
            <a href="{{ route('flights.show', $flight) }}" class="btn btn-secondary">{{ __('ui.cancel') }}</a>
        </form>
    </div>
</div>
@endsection
