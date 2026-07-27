@extends('adminlte::page')

@section('title', __('ui.police_stations.add_station'))

@section('content_header')
    <h1>{{ __('ui.police_stations.add_station') }}
        <a href="{{ route('police-stations.index') }}" class="btn btn-secondary btn-sm float-right">
            <i class="fas fa-arrow-left"></i> {{ __('ui.back') }}
        </a>
    </h1>
@endsection

@section('content')

@error('boundary_raw')
    <div class="alert alert-danger">{{ $message }}</div>
@enderror
@error('latitude')
    <div class="alert alert-danger">{{ __('ui.police_stations.center_required') }}</div>
@enderror

<form id="station-form" method="POST" action="{{ route('police-stations.store') }}">
@csrf
<div class="row">
    <div class="col-lg-9">
        <div class="card">
            <div class="card-header d-flex align-items-center">
                <span class="card-title mb-0"><i class="fas fa-map-marked-alt mr-1" style="color:var(--gold)"></i>{{ __('ui.police_stations.set_center_hint') }}</span>
            </div>
            <div id="station-map" style="height:520px"></div>
        </div>
    </div>
    <div class="col-lg-3">
        <div class="card">
            <div class="card-body">
                <div class="form-group">
                    <label>{{ __('ui.police_stations.name') }} *</label>
                    <input type="text" name="name" class="form-control @error('name') is-invalid @enderror"
                        value="{{ old('name') }}" required>
                    @error('name')<span class="invalid-feedback">{{ $message }}</span>@enderror
                </div>
                <div class="form-group">
                    <label>{{ __('ui.police_stations.administration') }} *</label>
                    <select name="police_administration_id" class="form-control @error('police_administration_id') is-invalid @enderror" required>
                        <option value="">—</option>
                        @foreach($administrations as $adm)
                            <option value="{{ $adm->id }}" {{ old('police_administration_id') == $adm->id ? 'selected' : '' }}>
                                {{ $adm->name }}
                            </option>
                        @endforeach
                    </select>
                    @error('police_administration_id')<span class="invalid-feedback d-block">{{ $message }}</span>@enderror
                </div>
                <p class="text-muted" style="font-size:.8rem">{{ __('ui.police_stations.draw_boundary_hint') }}</p>
                <input type="hidden" name="latitude" id="station-lat" value="{{ old('latitude') }}">
                <input type="hidden" name="longitude" id="station-lon" value="{{ old('longitude') }}">
                <input type="hidden" name="boundary_raw" id="boundary-input">
                <button type="submit" class="btn btn-primary btn-block">
                    <i class="fas fa-save mr-1"></i>{{ __('ui.save') }}
                </button>
            </div>
        </div>
    </div>
</div>
</form>

@endsection

@section('css')
<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css"/>
<link rel="stylesheet" href="https://unpkg.com/leaflet-draw@1.0.4/dist/leaflet.draw.css"/>
@endsection

@section('js')
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
<script src="https://unpkg.com/leaflet-draw@1.0.4/dist/leaflet.draw.js"></script>
<script>
const map = L.map('station-map').setView([45.1, 17.0], 8);
L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
    attribution: '© OpenStreetMap', maxZoom: 19
}).addTo(map);

let centerMarker = null;
@if(old('latitude') && old('longitude'))
centerMarker = L.marker([{{ old('latitude') }}, {{ old('longitude') }}]).addTo(map);
map.setView([{{ old('latitude') }}, {{ old('longitude') }}], 12);
@endif

map.on('click', function (e) {
    if (centerMarker) {
        centerMarker.setLatLng(e.latlng);
    } else {
        centerMarker = L.marker(e.latlng).addTo(map);
    }
    document.getElementById('station-lat').value = e.latlng.lat.toFixed(6);
    document.getElementById('station-lon').value = e.latlng.lng.toFixed(6);
});

const drawnItems = new L.FeatureGroup();
map.addLayer(drawnItems);

const drawControl = new L.Control.Draw({
    position: 'topright',
    draw: {
        polygon: { allowIntersection: false, showArea: true, shapeOptions: { color: '#f0c040', weight: 3 } },
        polyline: false, rectangle: false, circle: false, circlemarker: false, marker: false,
    },
    edit: { featureGroup: drawnItems, remove: true },
});
map.addControl(drawControl);

map.on(L.Draw.Event.CREATED, function (e) {
    drawnItems.clearLayers();
    drawnItems.addLayer(e.layer);
});

document.getElementById('station-form').addEventListener('submit', function (ev) {
    if (!document.getElementById('station-lat').value) {
        ev.preventDefault();
        alert('{{ __('ui.police_stations.center_required') }}');
        return;
    }
    const layers = drawnItems.getLayers();
    if (layers.length) {
        const latlngs = layers[0].getLatLngs()[0].map(p => [Math.round(p.lat * 1e6) / 1e6, Math.round(p.lng * 1e6) / 1e6]);
        document.getElementById('boundary-input').value = JSON.stringify(latlngs);
    }
});
</script>
@endsection
