@extends('adminlte::page')

@section('title', __('ui.cameras.edit_camera'))

@section('content_header')
    <h1>{{ __('ui.cameras.edit_camera') }}: {{ $camera->name }}</h1>
@endsection

@section('content')
<div class="row">
    <div class="col-md-5">
        <div class="card">
            <div class="card-body">
                <form action="{{ route('cameras.update', $camera) }}" method="POST">
                    @csrf @method('PUT')
                    <div class="form-group">
                        <label>{{ __('ui.cameras.name') }} *</label>
                        <input type="text" name="name" class="form-control @error('name') is-invalid @enderror"
                            value="{{ old('name', $camera->name) }}" required maxlength="20">
                        @error('name')<span class="invalid-feedback">{{ $message }}</span>@enderror
                    </div>
                    <div class="form-group">
                        <label>{{ __('ui.cameras.location') }} *</label>
                        <input type="text" name="location_name" class="form-control @error('location_name') is-invalid @enderror"
                            value="{{ old('location_name', $camera->location_name) }}" required>
                        @error('location_name')<span class="invalid-feedback">{{ $message }}</span>@enderror
                    </div>
                    <div class="form-group">
                        <label>{{ __('ui.cameras.coords') }} <small class="text-muted">({{ __('ui.cameras.click_map') }})</small></label>
                        <div class="input-group mb-2">
                            <div class="input-group-prepend"><span class="input-group-text">N</span></div>
                            <input type="text" id="lat_display" class="form-control" readonly
                                value="{{ $camera->latitude ? number_format((float)$camera->latitude, 5) : '' }}">
                            <input type="hidden" name="latitude" id="lat_input" value="{{ old('latitude', $camera->latitude) }}">
                        </div>
                        <div class="input-group">
                            <div class="input-group-prepend"><span class="input-group-text">E</span></div>
                            <input type="text" id="lon_display" class="form-control" readonly
                                value="{{ $camera->longitude ? number_format((float)$camera->longitude, 5) : '' }}">
                            <input type="hidden" name="longitude" id="lon_input" value="{{ old('longitude', $camera->longitude) }}">
                        </div>
                        <small class="form-text text-muted">{{ __('ui.cameras.click_map') }}</small>
                    </div>
                    <div class="form-group">
                        <label>{{ __('ui.cameras.notes') }}</label>
                        <textarea name="notes" class="form-control" rows="2">{{ old('notes', $camera->notes) }}</textarea>
                    </div>
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save mr-1"></i> {{ __('ui.save') }}
                    </button>
                    <a href="{{ route('cameras.show', $camera) }}" class="btn btn-secondary">{{ __('ui.cancel') }}</a>
                </form>
            </div>
        </div>
    </div>
    <div class="col-md-7">
        <div class="card">
            <div class="card-header">
                <span class="card-title">
                    <i class="fas fa-map-marked-alt mr-1" style="color:var(--gold)"></i>
                    {{ __('ui.cameras.pick_location') }}
                </span>
            </div>
            <div id="cam-map" style="height:380px; cursor:crosshair;"></div>
        </div>
        <div class="card mt-3">
            <div class="card-header"><span class="card-title">{{ __('ui.cameras.change_log') }}</span></div>
            <div class="card-body p-0">
                <table class="table table-sm mb-0">
                    <thead><tr><th>{{ __('ui.cameras.when') }}</th><th>{{ __('ui.cameras.who') }}</th><th>{{ __('ui.cameras.what') }}</th></tr></thead>
                    <tbody>
                        @forelse($camera->changeLogs as $log)
                        <tr>
                            <td><small>{{ $log->created_at->format('d.m.Y H:i') }}</small></td>
                            <td><small>{{ $log->user->name }}</small></td>
                            <td><small>{{ $log->changes }}</small></td>
                        </tr>
                        @empty
                        <tr><td colspan="3" class="text-center text-muted py-2">—</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</div>
@endsection

@section('css')
<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css"/>
<style>.leaflet-popup-content-wrapper{background:#0d2460!important;color:#fff!important;border-radius:10px!important}.leaflet-popup-tip{background:#0d2460!important}</style>
@endsection

@section('js')
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
<script>
const initLat = {{ $camera->latitude ?? 44.8 }};
const initLon = {{ $camera->longitude ?? 16.5 }};
const zoom    = {{ $camera->latitude ? 13 : 9 }};

const map = L.map('cam-map').setView([initLat, initLon], zoom);
L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
    attribution: '© OpenStreetMap', maxZoom: 19
}).addTo(map);

@if($camera->station && $camera->station->latitude)
@if($camera->station->hasBoundary())
L.polygon(@json($camera->station->boundary), {
    color: '#f0c040', weight: 2, fillColor: '#f0c040', fillOpacity: 0.08,
}).addTo(map);
@else
L.circle([{{ $camera->station->latitude }}, {{ $camera->station->longitude }}], {
    radius: {{ \App\Models\BorderPoliceStation::TERRITORY_RADIUS_KM * 1000 }},
    color: '#17a2b8', weight: 2, dashArray: '6,6', opacity: 0.8,
    fillColor: '#17a2b8', fillOpacity: 0.06,
}).addTo(map);
@endif
@endif

let marker = @if($camera->latitude) L.marker([{{ $camera->latitude }}, {{ $camera->longitude }}]).addTo(map) @else null @endif;

map.on('click', function(e) {
    const lat = e.latlng.lat.toFixed(7);
    const lon = e.latlng.lng.toFixed(7);

    document.getElementById('lat_input').value = lat;
    document.getElementById('lon_input').value = lon;
    document.getElementById('lat_display').value = parseFloat(lat).toFixed(5);
    document.getElementById('lon_display').value = parseFloat(lon).toFixed(5);

    if (marker) map.removeLayer(marker);
    marker = L.marker([lat, lon]).addTo(map)
        .bindPopup('<strong style="color:#f0c040">Nova lokacija</strong><br>' + parseFloat(lat).toFixed(5) + ', ' + parseFloat(lon).toFixed(5))
        .openPopup();
});
</script>
@endsection
