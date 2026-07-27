@extends('adminlte::page')

@section('title', __('ui.police_stations.edit_station'))

@section('content_header')
    <h1>{{ __('ui.police_stations.edit_station') }}: {{ $station->name }}
        <a href="{{ route('police-stations.index') }}" class="btn btn-secondary btn-sm float-right">
            <i class="fas fa-arrow-left"></i> {{ __('ui.back') }}
        </a>
    </h1>
@endsection

@section('content')
<div class="card">
    <div class="card-body">
        <form action="{{ route('police-stations.update', $station) }}" method="POST">
            @csrf @method('PUT')
            <div class="form-group">
                <label>{{ __('ui.police_stations.name') }} *</label>
                <input type="text" name="name" class="form-control @error('name') is-invalid @enderror"
                    value="{{ old('name', $station->name) }}" required>
                @error('name')<span class="invalid-feedback">{{ $message }}</span>@enderror
            </div>
            <div class="form-group">
                <label>{{ __('ui.police_stations.administration') }} *</label>
                <select name="police_administration_id" class="form-control @error('police_administration_id') is-invalid @enderror" required>
                    @foreach($administrations as $adm)
                        <option value="{{ $adm->id }}" {{ old('police_administration_id', $station->police_administration_id) == $adm->id ? 'selected' : '' }}>
                            {{ $adm->name }}
                        </option>
                    @endforeach
                </select>
                @error('police_administration_id')<span class="invalid-feedback d-block">{{ $message }}</span>@enderror
            </div>
            <p class="text-muted" style="font-size:.82rem">
                <i class="fas fa-info-circle mr-1"></i>
                <a href="{{ route('station-boundary.edit', $station) }}">{{ __('ui.boundary.edit_boundary') }}</a>
            </p>
            <button type="submit" class="btn btn-primary">
                <i class="fas fa-save mr-1"></i>{{ __('ui.save') }}
            </button>
            <a href="{{ route('police-stations.index') }}" class="btn btn-secondary">{{ __('ui.cancel') }}</a>
        </form>
    </div>
</div>
@endsection
