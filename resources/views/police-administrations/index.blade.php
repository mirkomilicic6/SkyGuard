@extends('adminlte::page')

@section('title', __('ui.police_administrations.index_title'))

@section('content_header')
    <h1>
        {{ __('ui.police_administrations.index_title') }}
        @if(auth()->user()->station_id === null)
        <a href="{{ route('police-administrations.create') }}" class="btn btn-primary btn-sm float-right">
            <i class="fas fa-plus"></i> {{ __('ui.police_administrations.add_administration') }}
        </a>
        @endif
    </h1>
@endsection

@section('content')
@if(session('success'))
    <div class="alert alert-success">{{ session('success') }}</div>
@endif
@if(session('error'))
    <div class="alert alert-danger">{{ session('error') }}</div>
@endif

<p class="text-muted mb-3">{{ __('ui.police_administrations.index_intro') }}</p>

<div class="card mb-3">
    <div class="card-header d-flex align-items-center justify-content-between">
        <span class="card-title"><i class="fas fa-map-marked-alt mr-1" style="color:var(--gold)"></i>{{ __('ui.boundary.overview_map') }}</span>
        <div class="btn-group btn-group-sm" role="group">
            <button type="button" class="btn btn-outline-secondary map-filter-btn active" data-filter="both">{{ __('ui.map_filter.both') }}</button>
            <button type="button" class="btn btn-outline-secondary map-filter-btn" data-filter="stations">{{ __('ui.map_filter.stations') }}</button>
            <button type="button" class="btn btn-outline-secondary map-filter-btn" data-filter="administrations">{{ __('ui.map_filter.administrations') }}</button>
        </div>
    </div>
    <div id="admin-overview-map" style="height:340px"></div>
</div>

<div class="card">
    <div class="card-body p-0">
        <table class="table table-striped mb-0">
            <thead>
                <tr>
                    <th>{{ __('ui.police_administrations.name') }}</th>
                    <th>{{ __('ui.police_administrations.stations_count') }}</th>
                    <th>{{ __('ui.boundary.status') }}</th>
                    <th>{{ __('ui.flights.actions') }}</th>
                </tr>
            </thead>
            <tbody>
                @forelse($administrations as $administration)
                <tr>
                    <td><strong>{{ $administration->name }}</strong></td>
                    <td>{{ $administration->stations_count }}</td>
                    <td>
                        @if($administration->hasBoundary())
                            <span class="badge badge-success"><i class="fas fa-draw-polygon mr-1"></i>{{ __('ui.boundary.status_drawn') }}</span>
                        @else
                            <span class="badge badge-secondary">{{ __('ui.admin_boundary.status_not_drawn') }}</span>
                        @endif
                    </td>
                    <td>
                        <a href="{{ route('police-stations.index') }}" class="btn btn-xs btn-secondary">
                            <i class="fas fa-building mr-1"></i>{{ __('ui.police_administrations.stations_count') }}
                        </a>
                        @if(auth()->user()->station_id === null || auth()->user()->station?->police_administration_id === $administration->id)
                        <a href="{{ route('administration-boundary.edit', $administration) }}" class="btn btn-xs btn-warning">
                            <i class="fas fa-draw-polygon mr-1"></i>{{ __('ui.boundary.edit_boundary') }}
                        </a>
                        @endif
                        @if(auth()->user()->station_id === null)
                        <a href="{{ route('police-administrations.edit', $administration) }}" class="btn btn-xs btn-info">
                            <i class="fas fa-edit"></i>
                        </a>
                        <form action="{{ route('police-administrations.destroy', $administration) }}" method="POST" style="display:inline"
                            onsubmit="return confirm('{{ __('ui.are_you_sure') }}')">
                            @csrf @method('DELETE')
                            <button class="btn btn-xs btn-danger"><i class="fas fa-trash"></i></button>
                        </form>
                        @endif
                    </td>
                </tr>
                @empty
                <tr><td colspan="4" class="text-center text-muted py-3">{{ __('ui.police_administrations.no_administrations') }}</td></tr>
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
    $palette = ['#f0c040', '#17a2b8', '#e0668c', '#8fce6a', '#7b8ff0', '#f0954a', '#a67bf0', '#5ac8c8'];
    $mapStations = collect();
    foreach ($administrations as $ai => $administration) {
        $color = $palette[$ai % count($palette)];
        foreach ($administration->stations as $station) {
            if ($station->latitude === null) continue;
            $mapStations->push([
                'lat' => (float) $station->latitude,
                'lon' => (float) $station->longitude,
                'name' => $station->name,
                'administration' => $administration->name,
                'boundary' => $station->boundary,
                'color' => $color,
            ]);
        }
    }
    $mapStations = $mapStations->values();
    $adminBoundaries = $administrations->filter(fn($a) => $a->hasBoundary())
        ->map(fn($a) => ['name' => $a->name, 'boundary' => $a->boundary])->values();
@endphp

@section('js')
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
<script>
const mapStations = @json($mapStations);
const adminBoundaries = @json($adminBoundaries);

const overviewMap = L.map('admin-overview-map', { zoomControl: true, scrollWheelZoom: false }).setView([45.1, 17.0], 8);
L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
    attribution: '© OpenStreetMap', maxZoom: 19
}).addTo(overviewMap);

const TERRITORY_RADIUS_M = {{ \App\Models\BorderPoliceStation::TERRITORY_RADIUS_KM * 1000 }};
const stationsGroup = L.layerGroup().addTo(overviewMap);
const adminGroup = L.layerGroup().addTo(overviewMap);
const overviewLayers = [];

mapStations.forEach(st => {
    const layer = (st.boundary && st.boundary.length >= 3)
        ? L.polygon(st.boundary, { color: st.color, weight: 2, fillColor: st.color, fillOpacity: 0.15 })
        : L.circle([st.lat, st.lon], {
            radius: TERRITORY_RADIUS_M,
            color: st.color, weight: 1.5, dashArray: '5,5', opacity: 0.8, fillColor: st.color, fillOpacity: 0.08,
        });
    layer.bindPopup(`<strong>${st.name}</strong><br>${st.administration}`).addTo(stationsGroup);
    overviewLayers.push(layer);
});

// Administration (uprava) boundaries — drawn distinctly (thick magenta dashed
// line, no per-station colour) so they're never confused with station territories.
adminBoundaries.forEach(a => {
    const layer = L.polygon(a.boundary, { color: '#c026d3', weight: 4, dashArray: '10,6', fillOpacity: 0.02 })
        .bindTooltip(a.name, { direction: 'center' })
        .addTo(adminGroup);
    overviewLayers.push(layer);
});

if (overviewLayers.length) {
    overviewMap.fitBounds(L.featureGroup(overviewLayers).getBounds().pad(0.15));
}

document.querySelectorAll('.map-filter-btn').forEach(btn => {
    btn.addEventListener('click', function () {
        document.querySelectorAll('.map-filter-btn').forEach(b => b.classList.remove('active'));
        this.classList.add('active');
        const filter = this.dataset.filter;
        if (filter === 'stations' || filter === 'both') overviewMap.addLayer(stationsGroup); else overviewMap.removeLayer(stationsGroup);
        if (filter === 'administrations' || filter === 'both') overviewMap.addLayer(adminGroup); else overviewMap.removeLayer(adminGroup);
    });
});
</script>
@endsection
