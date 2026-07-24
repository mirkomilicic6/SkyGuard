@extends('adminlte::page')

@section('title', __('ui.cameras.title'))

@section('content_header')
    <h1>{{ __('ui.cameras.title') }}
        <div class="float-right d-flex" style="gap:.5rem">
            <a href="{{ route('cameras.exportWord') }}" class="btn btn-secondary btn-sm">
                <i class="fas fa-file-word mr-1"></i> {{ __('ui.cameras.export_word') }}
            </a>
            @if(!auth()->user()->hasRole('viewer'))
            <a href="{{ route('cameras.create') }}" class="btn btn-primary btn-sm">
                <i class="fas fa-plus"></i> {{ __('ui.cameras.add_camera') }}
            </a>
            @endif
        </div>
    </h1>
@endsection

@section('content')
@if(session('success'))
    <div class="alert alert-success">{{ session('success') }}</div>
@endif

{{-- ── Filter (super admin only) ─────────────────────────────────────────── --}}
@if($administrations)
<div class="card card-outline card-primary mb-3">
    <div class="card-body py-2">
        <form method="GET" action="{{ route('cameras.index') }}" class="form-inline" id="filter-form">
            <div class="form-group mr-2">
                <select name="administration_id" id="filter-admin" class="form-control form-control-sm">
                    <option value="">— Sva uprava —</option>
                    @foreach($administrations as $adm)
                        <option value="{{ $adm->id }}" {{ request('administration_id') == $adm->id ? 'selected' : '' }}
                            data-stations="{{ $adm->stations->map(fn($s)=>['id'=>$s->id,'name'=>$s->name])->toJson() }}">
                            {{ $adm->name }}
                        </option>
                    @endforeach
                </select>
            </div>
            <div class="form-group mr-2">
                <select name="station_id" id="filter-station" class="form-control form-control-sm">
                    <option value="">— Sve postaje —</option>
                    @foreach($administrations->flatMap->stations as $st)
                        <option value="{{ $st->id }}" {{ request('station_id') == $st->id ? 'selected' : '' }}
                            data-admin="{{ $st->police_administration_id }}">
                            {{ $st->name }}
                        </option>
                    @endforeach
                </select>
            </div>
            <button type="submit" class="btn btn-sm btn-primary mr-1">
                <i class="fas fa-filter mr-1"></i> Filter
            </button>
            <a href="{{ route('cameras.index') }}" class="btn btn-sm btn-secondary">
                <i class="fas fa-times"></i>
            </a>
        </form>
    </div>
</div>
@endif

{{-- ── Filter (admin — own administration) ─────────────────────────────────── --}}
@if($stations)
<div class="card card-outline card-primary mb-3">
    <div class="card-body py-2">
        <form method="GET" action="{{ route('cameras.index') }}" class="form-inline">
            <div class="form-group mr-2">
                <select name="station_id" class="form-control form-control-sm">
                    <option value="">— Sve postaje —</option>
                    @foreach($stations as $st)
                        <option value="{{ $st->id }}" {{ request('station_id') == $st->id ? 'selected' : '' }}>{{ $st->name }}</option>
                    @endforeach
                </select>
            </div>
            <button type="submit" class="btn btn-sm btn-primary mr-1">
                <i class="fas fa-filter mr-1"></i> Filter
            </button>
            <a href="{{ route('cameras.index') }}" class="btn btn-sm btn-secondary">
                <i class="fas fa-times"></i>
            </a>
        </form>
    </div>
</div>
@endif

{{-- ── Map ───────────────────────────────────────────────────────────────── --}}
@php
    $mappedCameras = $cameras->filter(fn($c) => $c->latitude && $c->longitude);
    $mapData = $mappedCameras->map(fn($c) => [
        'lat'      => (float) $c->latitude,
        'lon'      => (float) $c->longitude,
        'name'     => $c->name,
        'location' => $c->location_name,
        'station'  => $c->station?->name ?? '',
        'active'   => (bool) $c->is_active,
        'url'      => route('cameras.show', $c),
    ])->values();

    // One territory outline per distinct station represented on this page —
    // the drawn boundary polygon if one exists, else the 10km fallback circle.
    $stationTerritories = $cameras->pluck('station')->filter()->unique('id')
        ->filter(fn($s) => $s->latitude)
        ->map(fn($s) => [
            'lat' => (float) $s->latitude, 'lon' => (float) $s->longitude,
            'name' => $s->name, 'boundary' => $s->boundary,
        ])
        ->values();
@endphp

<div class="card mb-3">
    <div class="card-header d-flex align-items-center" id="cam-map-toggle" style="cursor:pointer;user-select:none">
        <span class="card-title mb-0">
            <i class="fas fa-map-marked-alt mr-2" style="color:var(--gold)"></i>
            {{ __('ui.cameras.title') }} — {{ __('ui.cameras.location') }}
            <span class="badge badge-secondary ml-2">{{ $mappedCameras->count() }} GPS</span>
        </span>
        <div class="ml-auto d-flex align-items-center" style="gap:.75rem;font-size:.75rem;color:rgba(0,0,0,.45)">
            <span><span class="cam-legend-dot" style="background:#dc3545"></span> {{ __('ui.status.active') }}</span>
            <span><span class="cam-legend-dot" style="background:#6c757d"></span> {{ __('ui.cameras.inactive') }}</span>
            <i class="fas fa-chevron-up" id="cam-map-chevron"></i>
        </div>
    </div>
    <div id="cam-map-wrap">
        @if($mappedCameras->isNotEmpty())
            <div id="cam-map" style="height:420px;"></div>
        @else
            <div class="card-body text-center text-muted py-5">
                <i class="fas fa-map-marked-alt fa-3x mb-2 d-block"></i>
                {{ __('ui.cameras.no_coords') }}
            </div>
        @endif
    </div>
</div>

{{-- ── Table ─────────────────────────────────────────────────────────────── --}}
<div class="card">
    <div class="card-body p-0">
        <table class="table table-striped mb-0">
            <thead>
                <tr>
                    @if($showStationColumn)<th>{{ __('ui.cameras.station') }}</th>@endif
                    <th>{{ __('ui.cameras.name') }}</th>
                    <th>{{ __('ui.cameras.location') }}</th>
                    <th>{{ __('ui.cameras.coords_n') }}</th>
                    <th>{{ __('ui.cameras.coords_e') }}</th>
                    <th>{{ __('ui.cameras.last_update') }}</th>
                    <th>{{ __('ui.cameras.updated_by') }}</th>
                    <th>{{ __('ui.cameras.actions') }}</th>
                </tr>
            </thead>
            <tbody>
                @forelse($cameras as $cam)
                <tr class="{{ $cam->is_active ? '' : 'text-muted' }}">
                    @if($showStationColumn)
                    <td>
                        <small class="text-muted d-block">{{ $cam->station->administration->name ?? '' }}</small>
                        {{ $cam->station->name ?? '—' }}
                    </td>
                    @endif
                    <td>
                        <strong>{{ $cam->name }}</strong>
                        @if(!$cam->is_active)
                            <span class="badge badge-secondary ml-1">{{ __('ui.cameras.inactive') }}</span>
                        @endif
                    </td>
                    <td>{{ $cam->location_name }}</td>
                    <td>
                        @if($cam->latitude)
                            <span class="font-monospace" style="font-size:.82rem">{{ number_format((float)$cam->latitude, 5) }}</span>
                        @else —
                        @endif
                    </td>
                    <td>
                        @if($cam->longitude)
                            <span class="font-monospace" style="font-size:.82rem">{{ number_format((float)$cam->longitude, 5) }}</span>
                        @else —
                        @endif
                    </td>
                    <td><small>{{ $cam->updated_at->format('d.m.Y H:i') }}</small></td>
                    <td><small>{{ $cam->lastUpdatedBy?->name ?? '—' }}</small></td>
                    <td>
                        <a href="{{ route('cameras.show', $cam) }}" class="btn btn-xs btn-info">
                            <i class="fas fa-eye"></i>
                        </a>
                        @if(!auth()->user()->hasRole('viewer'))
                        <a href="{{ route('cameras.edit', $cam) }}" class="btn btn-xs btn-warning">
                            <i class="fas fa-edit"></i>
                        </a>
                        <form action="{{ route('cameras.destroy', $cam) }}" method="POST" style="display:inline"
                            onsubmit="return confirm('{{ __('ui.are_you_sure') }}')">
                            @csrf @method('DELETE')
                            <button class="btn btn-xs btn-danger"><i class="fas fa-trash"></i></button>
                        </form>
                        @endif
                    </td>
                </tr>
                @empty
                <tr><td colspan="{{ $showStationColumn ? 8 : 7 }}" class="text-center py-4">{{ __('ui.cameras.no_cameras') }}</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection

@section('css')
<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css"/>
<style>
.cam-legend-dot {
    display: inline-block;
    width: 10px; height: 10px;
    border-radius: 50%;
    margin-right: 3px;
    vertical-align: middle;
}
.leaflet-popup-content-wrapper {
    background: #0d2460 !important;
    color: #fff !important;
    border-radius: 10px !important;
    border: 1px solid rgba(240,192,64,0.3);
    box-shadow: 0 4px 20px rgba(0,0,0,.5) !important;
}
.leaflet-popup-tip { background: #0d2460 !important; }
.leaflet-popup-content { margin: 12px 14px !important; }
.cam-popup-link { color: #7dd3f5 !important; font-size: .78rem; text-decoration: none; }
.cam-popup-link:hover { color: #f0c040 !important; }
</style>
@endsection

@section('js')
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
<script>
{{-- ── Map ────────────────────────────────────────────────────── --}}
const mapData = @json($mapData);

@if($mappedCameras->isNotEmpty())
const map = L.map('cam-map', { zoomControl: true });
L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
    attribution: '© OpenStreetMap',
    maxZoom: 19
}).addTo(map);

// Station territory outlines — drawn boundary polygon if one exists, else the 10km circle
const stationTerritories = @json($stationTerritories);
const TERRITORY_RADIUS_M = {{ \App\Models\BorderPoliceStation::TERRITORY_RADIUS_KM * 1000 }};
stationTerritories.forEach(st => {
    const layer = (st.boundary && st.boundary.length >= 3)
        ? L.polygon(st.boundary, { color: '#f0c040', weight: 2, fillColor: '#f0c040', fillOpacity: 0.06 })
        : L.circle([st.lat, st.lon], {
            radius: TERRITORY_RADIUS_M,
            color: '#17a2b8', weight: 2, dashArray: '6,6', opacity: 0.8,
            fillColor: '#17a2b8', fillOpacity: 0.06,
        });
    layer.bindTooltip(st.name, { permanent: false, direction: 'center' }).addTo(map);
});

function cameraIcon(isActive) {
    const color  = isActive ? '#dc3545' : '#6c757d';
    const glow   = isActive ? 'rgba(220,53,69,0.25)' : 'rgba(108,117,125,0.15)';
    return L.divIcon({
        html: `<div style="
            width:34px;height:34px;border-radius:50%;
            background:${glow};
            border:2.5px solid ${color};
            display:flex;align-items:center;justify-content:center;
            color:${color};font-size:13px;
            box-shadow:0 0 0 5px ${glow};
        "><i class="fas fa-camera"></i></div>`,
        className: '',
        iconSize:   [34, 34],
        iconAnchor: [17, 17],
        popupAnchor:[0, -20],
    });
}

const bounds = [];
mapData.forEach(c => {
    const marker = L.marker([c.lat, c.lon], { icon: cameraIcon(c.active) });
    marker.bindPopup(`
        <div>
            <strong style="color:#f0c040;font-size:.95rem">${c.name}</strong>
            ${!c.active ? '<span style="background:#6c757d;color:#fff;border-radius:4px;padding:1px 6px;font-size:.65rem;margin-left:5px">{{ __('ui.cameras.inactive') }}</span>' : ''}
            <br>
            <span style="color:rgba(255,255,255,.75);font-size:.82rem">${c.location}</span><br>
            <span style="color:rgba(255,255,255,.45);font-size:.75rem">${c.station}</span><br>
            <div style="margin-top:6px">
                <a href="${c.url}" class="cam-popup-link"><i class="fas fa-eye mr-1"></i>{{ __('ui.more_info') }}</a>
            </div>
        </div>
    `);
    marker.addTo(map);
    bounds.push([c.lat, c.lon]);
});

if (bounds.length === 1) {
    map.setView(bounds[0], 14);
} else {
    map.fitBounds(bounds, { padding: [30, 30] });
}
@endif

{{-- ── Map toggle ──────────────────────────────────────────────── --}}
const mapWrap    = document.getElementById('cam-map-wrap');
const mapChevron = document.getElementById('cam-map-chevron');
document.getElementById('cam-map-toggle').addEventListener('click', function () {
    const hidden = mapWrap.style.display === 'none';
    mapWrap.style.display = hidden ? '' : 'none';
    mapChevron.classList.toggle('fa-chevron-up',   hidden);
    mapChevron.classList.toggle('fa-chevron-down', !hidden);
    @if($mappedCameras->isNotEmpty())
    if (hidden) map.invalidateSize();
    @endif
});

{{-- ── Filter cascade (super admin) ──────────────────────────────── --}}
@if($administrations)
const adminSelect   = document.getElementById('filter-admin');
const stationSelect = document.getElementById('filter-station');

function syncStations() {
    const adminId = adminSelect.value;
    Array.from(stationSelect.options).forEach(opt => {
        if (!opt.value) return;
        opt.hidden = adminId !== '' && opt.dataset.admin != adminId;
    });
    if (adminId !== '' && stationSelect.value &&
        stationSelect.options[stationSelect.selectedIndex]?.dataset.admin != adminId) {
        stationSelect.value = '';
    }
}
adminSelect.addEventListener('change', syncStations);
syncStations();
@endif
</script>
@endsection
