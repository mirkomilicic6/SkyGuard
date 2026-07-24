@extends('adminlte::page')

@section('title', __('ui.flights.log_flight'))

@section('content_header')
    <h1>{{ __('ui.flights.log_new_flight') }}</h1>
@endsection

@section('content')
<div class="card">
    <div class="card-body">
        <form action="{{ route('flights.store') }}" method="POST" enctype="multipart/form-data">
            @csrf
            <div class="row">
                <div class="col-md-6">
                    <div class="form-group">
                        <label>{{ __('ui.flights.drone_required') }}</label>
                        <select name="drone_id" class="form-control @error('drone_id') is-invalid @enderror" required>
                            <option value="">{{ __('ui.flights.select_drone') }}</option>
                            @foreach($drones as $drone)
                                <option value="{{ $drone->id }}" {{ old('drone_id') == $drone->id ? 'selected' : '' }}>
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
                        <input type="file" name="gpx_file" class="form-control-file @error('gpx_file') is-invalid @enderror" accept=".gpx,.xml" required>
                        <small class="text-muted">{{ __('ui.flights.gpx_hint') }}</small>
                        @error('gpx_file')<span class="invalid-feedback d-block">{{ $message }}</span>@enderror
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="form-group">
                        <label>{{ __('ui.flights.location') }}</label>
                        <input type="text" name="location" class="form-control"
                            value="{{ old('location') }}" placeholder="{{ __('ui.flights.location_placeholder') }}">
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="form-group">
                        <label>{{ __('ui.flights.notes') }}</label>
                        <input type="text" name="purpose" class="form-control"
                            value="{{ old('purpose') }}" placeholder="{{ __('ui.flights.notes_placeholder') }}">
                    </div>
                </div>
            </div>
            <button type="submit" class="btn btn-primary">
                <i class="fas fa-upload"></i> {{ __('ui.flights.upload_flight') }}
            </button>
            <a href="{{ route('flights.index') }}" class="btn btn-secondary">{{ __('ui.cancel') }}</a>
        </form>
    </div>
</div>
@endsection
