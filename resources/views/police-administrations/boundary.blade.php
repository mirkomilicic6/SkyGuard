@extends('adminlte::page')

@section('title', $administration->name . ' — ' . __('ui.admin_boundary.edit_title'))

@section('content_header')
    <h1>{{ $administration->name }}
        <a href="{{ route('police-administrations.index') }}" class="btn btn-secondary btn-sm float-right">
            <i class="fas fa-arrow-left"></i> {{ __('ui.back') }}
        </a>
    </h1>
@endsection

@section('content')

@if(session('success'))
    <div class="alert alert-success">{{ session('success') }}</div>
@endif
@error('boundary_raw')
    <div class="alert alert-danger">{{ $message }}</div>
@enderror

<div class="row">
    <div class="col-lg-9">
        <div class="card">
            <div class="card-header d-flex align-items-center">
                <span class="card-title mb-0"><i class="fas fa-draw-polygon mr-1" style="color:#c026d3"></i>{{ __('ui.admin_boundary.edit_title') }}</span>
                <div class="ml-auto">
                    <button type="button" id="clear-boundary-btn" class="btn btn-sm btn-outline-danger">
                        <i class="fas fa-trash mr-1"></i>{{ __('ui.boundary.clear_all') }}
                    </button>
                </div>
            </div>
            <div id="admin-boundary-map" style="height:560px"></div>
        </div>
    </div>
    <div class="col-lg-3">
        <div class="card">
            <div class="card-body">
                <p style="font-size:.85rem">{{ __('ui.boundary.draw_hint') }}</p>
                <p class="text-muted" style="font-size:.78rem">
                    <i class="fas fa-keyboard mr-1"></i>{{ __('ui.boundary.undo_last') }}: <kbd>Backspace</kbd>
                </p>
                <p class="text-muted mb-1" style="font-size:.75rem;text-transform:uppercase;letter-spacing:.05em">
                    {{ __('ui.admin_boundary.legend') }}
                </p>
                <ul class="list-unstyled" style="font-size:.8rem">
                    <li><span style="display:inline-block;width:14px;height:3px;background:#c026d3;margin-right:6px"></span>{{ __('ui.admin_boundary.this_administration') }}</li>
                    <li><span style="display:inline-block;width:10px;height:10px;background:#6c757d;border-radius:2px;margin-right:6px"></span>{{ __('ui.boundary.station') }}</li>
                </ul>
                @if($neighborAdministrations->isNotEmpty())
                <p class="text-muted mt-3 mb-1" style="font-size:.75rem;text-transform:uppercase;letter-spacing:.05em">
                    {{ __('ui.admin_boundary.neighbor_legend') }}
                </p>
                <ul class="list-unstyled" style="font-size:.8rem">
                    @foreach($neighborAdministrations as $n)
                    <li><span style="display:inline-block;width:14px;height:3px;background:#9ca3af;margin-right:6px"></span>{{ $n->name }}</li>
                    @endforeach
                </ul>
                @endif

                <form id="boundary-form" method="POST" action="{{ route('administration-boundary.update', $administration) }}" class="mt-3">
                    @csrf
                    @method('PUT')
                    <input type="hidden" name="boundary_raw" id="boundary-input">
                    <button type="submit" class="btn btn-primary btn-block">
                        <i class="fas fa-save mr-1"></i>{{ __('ui.boundary.save_boundary') }}
                    </button>
                </form>
            </div>
        </div>
    </div>
</div>

@endsection

@section('css')
<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css"/>
<link rel="stylesheet" href="https://unpkg.com/leaflet-draw@1.0.4/dist/leaflet.draw.css"/>
<style>
.leaflet-popup-content-wrapper { background: #0d2460 !important; color: #fff !important; border-radius: 10px !important; }
.leaflet-popup-tip { background: #0d2460 !important; }
.admin-boundary-label { background: rgba(13,36,96,0.85); border: none; color: #fff; font-size: .72rem; }
</style>
@endsection

@php
    $stationPoints = $administration->stations->filter(fn($s) => $s->latitude)->map(fn($s) => [
        'lat' => (float) $s->latitude, 'lon' => (float) $s->longitude,
        'name' => $s->name, 'boundary' => $s->boundary,
    ])->values();
    $neighborBoundaries = $neighborAdministrations->map(fn($n) => ['name' => $n->name, 'boundary' => $n->boundary])->values();
@endphp

@section('js')
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
<script src="https://unpkg.com/leaflet-draw@1.0.4/dist/leaflet.draw.js"></script>
<script>
const stationPoints = @json($stationPoints);
const neighborBoundaries = @json($neighborBoundaries);

const map = L.map('admin-boundary-map').setView([45.1, 17.0], 8);
L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
    attribution: '© OpenStreetMap', maxZoom: 19
}).addTo(map);

// This administration's own stations — shown as grey reference context.
const TERRITORY_RADIUS_M = {{ \App\Models\BorderPoliceStation::TERRITORY_RADIUS_KM * 1000 }};
const stationLayers = [];
stationPoints.forEach(st => {
    const layer = (st.boundary && st.boundary.length >= 3)
        ? L.polygon(st.boundary, { color: '#6c757d', weight: 1.5, fillColor: '#6c757d', fillOpacity: 0.06 })
        : L.circle([st.lat, st.lon], { radius: TERRITORY_RADIUS_M, color: '#6c757d', weight: 1, dashArray: '4,4', fillOpacity: 0.03 });
    layer.bindTooltip(st.name, { direction: 'center' }).addTo(map);
    stationLayers.push(layer);
});

// Neighbouring administrations' boundaries — read-only reference
neighborBoundaries.forEach(n => {
    L.polygon(n.boundary, { color: '#9ca3af', weight: 2, dashArray: '10,6', fillOpacity: 0.02, interactive: false })
        .bindTooltip(n.name, { permanent: true, direction: 'center', className: 'admin-boundary-label' })
        .addTo(map);
});

// Editable layer — this administration's own boundary, drawn distinctly
// (magenta, thick dashed line) so it's visually not confused with a
// station's boundary (solid gold).
const drawnItems = new L.FeatureGroup();
map.addLayer(drawnItems);

const ADMIN_BOUNDARY_STYLE = { color: '#c026d3', weight: 4, dashArray: '10,6', fillColor: '#c026d3', fillOpacity: 0.05 };

const existingBoundary = @json($administration->boundary ?? null);
let fitLayers = stationLayers.slice();
if (existingBoundary && existingBoundary.length >= 3) {
    const existing = L.polygon(existingBoundary, ADMIN_BOUNDARY_STYLE);
    drawnItems.addLayer(existing);
    fitLayers.push(existing);
}

if (fitLayers.length) {
    map.fitBounds(L.featureGroup(fitLayers).getBounds().pad(0.2));
}

const drawControl = new L.Control.Draw({
    position: 'topright',
    draw: {
        polygon: { allowIntersection: false, showArea: true, shapeOptions: ADMIN_BOUNDARY_STYLE },
        polyline: false, rectangle: false, circle: false, circlemarker: false, marker: false,
    },
    edit: { featureGroup: drawnItems, remove: true },
});
map.addControl(drawControl);

map.on(L.Draw.Event.CREATED, function (e) {
    drawnItems.clearLayers();
    drawnItems.addLayer(e.layer);
});

document.getElementById('clear-boundary-btn').addEventListener('click', function () {
    drawnItems.clearLayers();
});

document.getElementById('boundary-form').addEventListener('submit', function () {
    const layers = drawnItems.getLayers();
    if (layers.length) {
        const latlngs = layers[0].getLatLngs()[0].map(p => [Math.round(p.lat * 1e6) / 1e6, Math.round(p.lng * 1e6) / 1e6]);
        document.getElementById('boundary-input').value = JSON.stringify(latlngs);
    } else {
        document.getElementById('boundary-input').value = '';
    }
});
</script>
@endsection
