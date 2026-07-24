@extends('adminlte::page')

@section('title', $station->name . ' — ' . __('ui.boundary.edit_title'))

@section('content_header')
    <h1>{{ $station->name }}
        <a href="{{ route('station-boundary.index') }}" class="btn btn-secondary btn-sm float-right">
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
                <span class="card-title mb-0"><i class="fas fa-draw-polygon mr-1" style="color:var(--gold)"></i>{{ __('ui.boundary.edit_title') }}</span>
                <div class="ml-auto">
                    <button type="button" id="clear-boundary-btn" class="btn btn-sm btn-outline-danger">
                        <i class="fas fa-trash mr-1"></i>{{ __('ui.boundary.clear_all') }}
                    </button>
                </div>
            </div>
            <div id="boundary-map" style="height:560px"></div>
        </div>
    </div>
    <div class="col-lg-3">
        <div class="card">
            <div class="card-body">
                <p style="font-size:.85rem">{{ __('ui.boundary.draw_hint') }}</p>
                <p class="text-muted" style="font-size:.78rem">
                    <i class="fas fa-keyboard mr-1"></i>{{ __('ui.boundary.undo_last') }}: <kbd>Backspace</kbd>
                </p>
                @if(!$station->hasBoundary())
                <div class="alert alert-warning py-2" style="font-size:.8rem">
                    <i class="fas fa-info-circle mr-1"></i>{{ __('ui.boundary.no_boundary_yet') }}
                </div>
                @endif
                @if($neighborStations->isNotEmpty())
                <p class="text-muted mt-3 mb-1" style="font-size:.75rem;text-transform:uppercase;letter-spacing:.05em">
                    {{ __('ui.boundary.neighbor_legend') }}
                </p>
                <ul class="list-unstyled" style="font-size:.8rem">
                    @foreach($neighborStations as $n)
                    <li><span style="display:inline-block;width:10px;height:10px;background:#6c757d;border-radius:2px;margin-right:6px"></span>{{ $n->name }}</li>
                    @endforeach
                </ul>
                @endif

                <form id="boundary-form" method="POST" action="{{ route('station-boundary.update', $station) }}" class="mt-3">
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
.boundary-neighbor-label { background: rgba(13,36,96,0.85); border: none; color: #fff; font-size: .72rem; }
</style>
@endsection

@section('js')
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
<script src="https://unpkg.com/leaflet-draw@1.0.4/dist/leaflet.draw.js"></script>
<script>
const stationLat = {{ $station->latitude ?? 45.1 }};
const stationLon = {{ $station->longitude ?? 17.0 }};
const TERRITORY_RADIUS_M = {{ \App\Models\BorderPoliceStation::TERRITORY_RADIUS_KM * 1000 }};

const map = L.map('boundary-map').setView([stationLat, stationLon], 12);
L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
    attribution: '© OpenStreetMap', maxZoom: 19
}).addTo(map);

// Station reference marker + 10km fallback circle — only shown until a real
// boundary is drawn; once one exists, the circle is no longer relevant.
L.marker([stationLat, stationLon]).addTo(map).bindPopup('{{ $station->name }}');
let fallbackCircle = null;
@if(!$station->hasBoundary())
fallbackCircle = L.circle([stationLat, stationLon], {
    radius: TERRITORY_RADIUS_M,
    color: '#17a2b8', weight: 1.5, dashArray: '6,6', opacity: 0.5, fillOpacity: 0.02,
}).addTo(map);
@endif

// Neighbouring stations' boundaries — read-only reference
const neighborBoundaries = @json($neighborStations->map(fn($n) => ['name' => $n->name, 'boundary' => $n->boundary]));
neighborBoundaries.forEach(n => {
    L.polygon(n.boundary, { color: '#6c757d', weight: 2, dashArray: '4,4', fillOpacity: 0.04, interactive: false })
        .bindTooltip(n.name, { permanent: true, direction: 'center', className: 'boundary-neighbor-label' })
        .addTo(map);
});

// Editable layer — this station's own boundary (at most one polygon)
const drawnItems = new L.FeatureGroup();
map.addLayer(drawnItems);

const existingBoundary = @json($station->boundary ?? null);
if (existingBoundary && existingBoundary.length >= 3) {
    const existing = L.polygon(existingBoundary, { color: '#f0c040', weight: 3, fillOpacity: 0.12 });
    drawnItems.addLayer(existing);
    map.fitBounds(existing.getBounds().pad(0.2));
}

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
    if (fallbackCircle) {
        map.removeLayer(fallbackCircle);
        fallbackCircle = null;
    }
});

document.getElementById('clear-boundary-btn').addEventListener('click', function () {
    drawnItems.clearLayers();
    if (!fallbackCircle) {
        fallbackCircle = L.circle([stationLat, stationLon], {
            radius: TERRITORY_RADIUS_M,
            color: '#17a2b8', weight: 1.5, dashArray: '6,6', opacity: 0.5, fillOpacity: 0.02,
        }).addTo(map);
    }
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
