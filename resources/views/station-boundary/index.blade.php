@extends('adminlte::page')

@section('title', __('ui.boundary.index_title'))

@section('content_header')
    <h1>{{ __('ui.boundary.index_title') }}</h1>
@endsection

@section('content')

@if(session('success'))
    <div class="alert alert-success">{{ session('success') }}</div>
@endif

<p class="text-muted mb-3">{{ __('ui.boundary.index_intro') }}</p>

<div class="card mb-3">
    <div class="card-header">
        <span class="card-title"><i class="fas fa-map-marked-alt mr-1" style="color:var(--gold)"></i>{{ __('ui.boundary.overview_map') }}</span>
    </div>
    <div id="boundary-overview-map" style="height:320px"></div>
</div>

<div class="card">
    <div class="card-body p-0">
        <table class="table table-striped mb-0">
            <thead>
                <tr>
                    <th>{{ __('ui.boundary.station') }}</th>
                    <th>{{ __('ui.boundary.administration') }}</th>
                    <th>{{ __('ui.boundary.status') }}</th>
                    <th>{{ __('ui.flights.actions') }}</th>
                </tr>
            </thead>
            <tbody>
                @forelse($stations as $station)
                <tr>
                    <td><strong>{{ $station->name }}</strong></td>
                    <td>{{ $station->administration->name ?? '—' }}</td>
                    <td>
                        @if($station->hasBoundary())
                            <span class="badge badge-success"><i class="fas fa-draw-polygon mr-1"></i>{{ __('ui.boundary.status_drawn') }}</span>
                        @else
                            <span class="badge badge-secondary">{{ __('ui.boundary.status_not_drawn') }}</span>
                        @endif
                    </td>
                    <td>
                        <a href="{{ route('station-boundary.edit', $station) }}" class="btn btn-xs btn-warning">
                            <i class="fas fa-draw-polygon mr-1"></i>{{ __('ui.boundary.edit_boundary') }}
                        </a>
                    </td>
                </tr>
                @empty
                <tr><td colspan="4" class="text-center text-muted py-4">{{ __('ui.no_data') }}</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>

@endsection

@section('css')
<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css"/>
<style>
.leaflet-popup-content-wrapper { background: #0d2460 !important; color: #fff !important; border-radius: 10px !important; }
.leaflet-popup-tip { background: #0d2460 !important; }
</style>
@endsection

@php
    $overviewStations = $stations->filter(fn($s) => $s->latitude)->map(fn($s) => [
        'lat' => (float) $s->latitude, 'lon' => (float) $s->longitude,
        'name' => $s->name, 'boundary' => $s->boundary,
    ])->values();
@endphp

@section('js')
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
<script>
const overviewStations = @json($overviewStations);

const overviewMap = L.map('boundary-overview-map', { zoomControl: true, scrollWheelZoom: false }).setView([45.1, 17.0], 8);
L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
    attribution: '© OpenStreetMap', maxZoom: 19
}).addTo(overviewMap);

const TERRITORY_RADIUS_M = {{ \App\Models\BorderPoliceStation::TERRITORY_RADIUS_KM * 1000 }};
const overviewLayers = [];

overviewStations.forEach(st => {
    const layer = (st.boundary && st.boundary.length >= 3)
        ? L.polygon(st.boundary, { color: '#f0c040', weight: 2, fillColor: '#f0c040', fillOpacity: 0.1 })
        : L.circle([st.lat, st.lon], {
            radius: TERRITORY_RADIUS_M,
            color: '#17a2b8', weight: 1.5, dashArray: '5,5', opacity: 0.7, fillColor: '#17a2b8', fillOpacity: 0.05,
        });
    layer.bindTooltip(st.name, { direction: 'center' }).addTo(overviewMap);
    overviewLayers.push(layer);
});

if (overviewLayers.length) {
    overviewMap.fitBounds(L.featureGroup(overviewLayers).getBounds().pad(0.15));
}
</script>
@endsection
