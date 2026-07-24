@extends('adminlte::page')

@section('title', __('ui.cameras.add_camera'))

@section('content_header')
    <h1>{{ __('ui.cameras.add_camera') }}</h1>
@endsection

@section('content')
<div class="row">
    <div class="col-md-5">
        <div class="card">
            <div class="card-body">
                <form action="{{ route('cameras.store') }}" method="POST">
                    @csrf
                    @if($stations)
                    <div class="form-group">
                        <label>{{ __('ui.cameras.station') }} *</label>
                        <select name="station_id" class="form-control @error('station_id') is-invalid @enderror" required>
                            <option value="">— Odaberi postaju —</option>
                            @foreach($stations as $st)
                                <option value="{{ $st->id }}" {{ old('station_id') == $st->id ? 'selected' : '' }}>
                                    {{ $st->name }}
                                </option>
                            @endforeach
                        </select>
                        @error('station_id')<span class="invalid-feedback">{{ $message }}</span>@enderror
                    </div>
                    @endif
                    <div class="form-group">
                        <label>{{ __('ui.cameras.name') }} *</label>
                        <input type="text" name="name" class="form-control @error('name') is-invalid @enderror"
                            value="{{ old('name') }}" placeholder="npr. IL1, SL3..." required maxlength="20">
                        <small class="form-text text-muted">{{ __('ui.cameras.name_hint') }}</small>
                        @error('name')<span class="invalid-feedback">{{ $message }}</span>@enderror
                    </div>
                    <div class="form-group">
                        <label>{{ __('ui.cameras.location') }} *</label>
                        <input type="text" name="location_name" class="form-control @error('location_name') is-invalid @enderror"
                            value="{{ old('location_name') }}" placeholder="{{ __('ui.cameras.location_placeholder') }}" required>
                        @error('location_name')<span class="invalid-feedback">{{ $message }}</span>@enderror
                    </div>
                    <div class="form-group">
                        <label>{{ __('ui.cameras.coords') }} <small class="text-muted">({{ __('ui.cameras.click_map') }})</small></label>
                        <div class="input-group mb-2">
                            <div class="input-group-prepend"><span class="input-group-text">N</span></div>
                            <input type="text" id="lat_display" class="form-control" readonly placeholder="—">
                            <input type="hidden" name="latitude" id="lat_input" value="{{ old('latitude') }}">
                        </div>
                        <div class="input-group">
                            <div class="input-group-prepend"><span class="input-group-text">E</span></div>
                            <input type="text" id="lon_display" class="form-control" readonly placeholder="—">
                            <input type="hidden" name="longitude" id="lon_input" value="{{ old('longitude') }}">
                        </div>
                        <small class="form-text text-muted">{{ __('ui.cameras.click_map') }}</small>
                    </div>
                    <div class="form-group">
                        <label>{{ __('ui.cameras.notes') }}</label>
                        <textarea name="notes" class="form-control" rows="2">{{ old('notes') }}</textarea>
                    </div>
                    <button type="submit" class="btn btn-primary">
                        <i class="fas fa-save mr-1"></i> {{ __('ui.save') }}
                    </button>
                    <a href="{{ route('cameras.index') }}" class="btn btn-secondary">{{ __('ui.cancel') }}</a>
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
            <div id="cam-map" style="height:450px; cursor:crosshair;"></div>
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
const initLat = {{ old('latitude', 44.8) }};
const initLon = {{ old('longitude', 16.5) }};

const map = L.map('cam-map').setView([initLat, initLon], 9);
L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
    attribution: '© OpenStreetMap', maxZoom: 19
}).addTo(map);

let marker = null;

// ── Station territory — drawn boundary polygon if one exists, else a 10km circle ──
const TERRITORY_RADIUS_M = {{ \App\Models\BorderPoliceStation::TERRITORY_RADIUS_KM * 1000 }};
let territoryLayer = null;

function showTerritory(lat, lon, boundary) {
    if (territoryLayer) map.removeLayer(territoryLayer);
    if (boundary && boundary.length >= 3) {
        territoryLayer = L.polygon(boundary, {
            color: '#f0c040', weight: 2, fillColor: '#f0c040', fillOpacity: 0.08,
        }).addTo(map);
    } else {
        territoryLayer = L.circle([lat, lon], {
            radius: TERRITORY_RADIUS_M,
            color: '#17a2b8', weight: 2, dashArray: '6,6', opacity: 0.8,
            fillColor: '#17a2b8', fillOpacity: 0.06,
        }).addTo(map);
    }
}

@if($ownStation && $ownStation->latitude)
showTerritory({{ $ownStation->latitude }}, {{ $ownStation->longitude }}, @json($ownStation->boundary ?? null));
map.setView([{{ $ownStation->latitude }}, {{ $ownStation->longitude }}], 11);
@endif

@if($stations)
const stationPoints = {!! $stations->filter(fn($s) => $s->latitude)->mapWithKeys(fn($s) => [$s->id => ['lat' => (float) $s->latitude, 'lon' => (float) $s->longitude, 'boundary' => $s->boundary]])->toJson() !!};
document.querySelector('select[name="station_id"]')?.addEventListener('change', function () {
    const st = stationPoints[this.value];
    if (st) {
        showTerritory(st.lat, st.lon, st.boundary);
        map.setView([st.lat, st.lon], 11);
    } else if (territoryLayer) {
        map.removeLayer(territoryLayer);
        territoryLayer = null;
    }
});
@endif

@if(old('latitude'))
marker = L.marker([{{ old('latitude') }}, {{ old('longitude') }}]).addTo(map);
document.getElementById('lat_display').value = parseFloat({{ old('latitude') }}).toFixed(5);
document.getElementById('lon_display').value = parseFloat({{ old('longitude') }}).toFixed(5);
@endif

map.on('click', function(e) {
    const lat = e.latlng.lat.toFixed(7);
    const lon = e.latlng.lng.toFixed(7);

    document.getElementById('lat_input').value = lat;
    document.getElementById('lon_input').value = lon;
    document.getElementById('lat_display').value = parseFloat(lat).toFixed(5);
    document.getElementById('lon_display').value = parseFloat(lon).toFixed(5);

    if (marker) map.removeLayer(marker);
    marker = L.marker([lat, lon]).addTo(map)
        .bindPopup('<strong style="color:#f0c040">Odabrana lokacija</strong><br>' + parseFloat(lat).toFixed(5) + ', ' + parseFloat(lon).toFixed(5))
        .openPopup();
});
</script>
@endsection
