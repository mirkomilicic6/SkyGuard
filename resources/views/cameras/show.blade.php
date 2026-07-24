@extends('adminlte::page')

@section('title', $camera->name)

@section('content_header')
    <h1>{{ $camera->name }}
        <a href="{{ route('cameras.index') }}" class="btn btn-secondary btn-sm float-right">
            <i class="fas fa-arrow-left"></i> {{ __('ui.back') }}
        </a>
        @if(!auth()->user()->hasRole('viewer'))
        <a href="{{ route('cameras.edit', $camera) }}" class="btn btn-warning btn-sm float-right mr-2">
            <i class="fas fa-edit"></i> {{ __('ui.edit') }}
        </a>
        @endif
    </h1>
@endsection

@section('content')
<div class="row">
    <div class="col-md-4">
        <div class="card mb-3">
            <div class="card-body">
                <table class="table table-sm">
                    <tr><th>{{ __('ui.cameras.name') }}</th><td><strong>{{ $camera->name }}</strong></td></tr>
                    <tr><th>{{ __('ui.cameras.station') }}</th><td>{{ $camera->station->name }}</td></tr>
                    <tr><th>{{ __('ui.cameras.location') }}</th><td>{{ $camera->location_name }}</td></tr>
                    <tr>
                        <th>{{ __('ui.cameras.coords_n') }}</th>
                        <td class="font-monospace">{{ $camera->latitude ? number_format((float)$camera->latitude, 5) : '—' }}</td>
                    </tr>
                    <tr>
                        <th>{{ __('ui.cameras.coords_e') }}</th>
                        <td class="font-monospace">{{ $camera->longitude ? number_format((float)$camera->longitude, 5) : '—' }}</td>
                    </tr>
                    <tr><th>{{ __('ui.cameras.last_update') }}</th><td>{{ $camera->updated_at->format('d.m.Y H:i') }}</td></tr>
                    <tr><th>{{ __('ui.cameras.updated_by') }}</th><td>{{ $camera->lastUpdatedBy?->name ?? '—' }}</td></tr>
                </table>
                @if($camera->notes)
                <hr>
                <p class="text-muted small mb-0">{{ $camera->notes }}</p>
                @endif
            </div>
        </div>
        <div class="card">
            <div class="card-header"><span class="card-title">{{ __('ui.cameras.change_log') }}</span></div>
            <div class="card-body p-0" style="max-height:320px;overflow-y:auto">
                <table class="table table-sm mb-0">
                    <tbody>
                        @forelse($camera->changeLogs as $log)
                        <tr>
                            <td>
                                <small class="text-muted">{{ $log->created_at->format('d.m.Y H:i') }}</small><br>
                                <strong style="font-size:.82rem">{{ $log->user->name }}</strong>
                                <span class="badge badge-secondary ml-1" style="font-size:.65rem">{{ $log->action }}</span><br>
                                <span class="text-muted" style="font-size:.78rem">{{ $log->changes }}</span>
                            </td>
                        </tr>
                        @empty
                        <tr><td class="text-center text-muted py-2">—</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
    <div class="col-md-8">
        <div class="card">
            <div class="card-header"><span class="card-title"><i class="fas fa-map-marked-alt mr-1" style="color:var(--gold)"></i>{{ __('ui.cameras.location') }}</span></div>
            @if($camera->latitude)
            <div id="cam-map" style="height:450px;"></div>
            @else
            <div class="card-body text-center text-muted py-5">
                <i class="fas fa-map-marked-alt fa-3x mb-2"></i><br>
                {{ __('ui.cameras.no_coords') }}
            </div>
            @endif
        </div>
    </div>
</div>
@endsection

@section('css')
@if($camera->latitude)
<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css"/>
<style>.leaflet-popup-content-wrapper{background:#0d2460!important;color:#fff!important;border-radius:10px!important}.leaflet-popup-tip{background:#0d2460!important}</style>
@endif
@endsection

@section('js')
@if($camera->latitude)
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
<script>
const map = L.map('cam-map').setView([{{ $camera->latitude }}, {{ $camera->longitude }}], 14);
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
L.marker([{{ $camera->latitude }}, {{ $camera->longitude }}])
    .addTo(map)
    .bindPopup('<strong style="color:#f0c040">{{ $camera->name }}</strong><br>{{ $camera->location_name }}')
    .openPopup();
</script>
@endif
@endsection
