@extends('adminlte::page')

@section('title', __('ui.detections.title'))

@section('content_header')
    <div class="d-flex align-items-center justify-content-between">
        <h1>{{ __('ui.detections.title') }}</h1>
        @unless(auth()->user()->hasRole('viewer'))
        <a href="{{ route('detections.create') }}" class="btn btn-warning btn-sm">
            <i class="fas fa-plus mr-1"></i> Nova detekcija
        </a>
        @endunless
    </div>
@endsection

@section('content')

@if(session('success'))
<div class="alert alert-success alert-dismissible fade show">
    <i class="fas fa-check-circle mr-2"></i>{{ session('success') }}
    <button type="button" class="close" data-dismiss="alert"><span>&times;</span></button>
</div>
@endif

{{-- ── Filters ──────────────────────────────────────────────────────────── --}}
<div class="card card-outline card-primary mb-3">
    <div class="card-body py-2">
        <form method="GET" action="{{ route('detections.index') }}" class="form-inline flex-wrap" id="filter-form" style="gap:.5rem">

            @if($administrations)
            <select name="administration_id" id="filter-admin" class="form-control form-control-sm">
                <option value="">— Sva uprava —</option>
                @foreach($administrations as $adm)
                    <option value="{{ $adm->id }}" {{ request('administration_id') == $adm->id ? 'selected' : '' }}
                        data-stations="{{ $adm->stations->map(fn($s)=>['id'=>$s->id,'name'=>$s->name])->toJson() }}">
                        {{ $adm->name }}
                    </option>
                @endforeach
            </select>
            <select name="station_id" id="filter-station" class="form-control form-control-sm">
                <option value="">— Sve postaje —</option>
                @foreach($administrations->flatMap->stations as $st)
                    <option value="{{ $st->id }}" {{ request('station_id') == $st->id ? 'selected' : '' }}
                        data-admin="{{ $st->police_administration_id }}">
                        {{ $st->name }}
                    </option>
                @endforeach
            </select>
            @endif

            {{-- Postaja filter za admina cijele uprave --}}
            @if($stations)
            <select name="station_id" class="form-control form-control-sm">
                <option value="">— Sve postaje —</option>
                @foreach($stations as $st)
                    <option value="{{ $st->id }}" {{ request('station_id') == $st->id ? 'selected' : '' }}>{{ $st->name }}</option>
                @endforeach
            </select>
            @endif

            <select name="type" class="form-control form-control-sm">
                <option value="">— Sve vrste —</option>
                @foreach(['person','group','vehicle','smuggling','other'] as $t)
                    <option value="{{ $t }}" {{ request('type') === $t ? 'selected' : '' }}>
                        {{ __('ui.detections.types.' . $t) }}
                    </option>
                @endforeach
            </select>

            <select name="source" class="form-control form-control-sm">
                <option value="">— Svi izvori —</option>
                <option value="drone"  {{ request('source') === 'drone'  ? 'selected' : '' }}>Dron</option>
                <option value="camera" {{ request('source') === 'camera' ? 'selected' : '' }}>Kamera</option>
                <option value="manual" {{ request('source') === 'manual' ? 'selected' : '' }}>Ručno</option>
            </select>

            <div class="input-group input-group-sm" style="width:auto">
                <div class="input-group-prepend"><span class="input-group-text">{{ __('ui.from') }}</span></div>
                <input type="date" name="date_from" class="form-control" value="{{ request('date_from') }}">
            </div>
            <div class="input-group input-group-sm" style="width:auto">
                <div class="input-group-prepend"><span class="input-group-text">{{ __('ui.to') }}</span></div>
                <input type="date" name="date_to" class="form-control" value="{{ request('date_to') }}">
            </div>

            <button type="submit" class="btn btn-sm btn-primary">
                <i class="fas fa-filter mr-1"></i> Filter
            </button>
            <a href="{{ route('detections.index') }}" class="btn btn-sm btn-secondary">
                <i class="fas fa-times"></i>
            </a>

            @if($detections->total())
            <span class="badge badge-secondary ml-1" style="font-size:.8rem;padding:5px 10px">
                {{ $detections->total() }} {{ __('ui.results') }}
            </span>
            @endif
        </form>
    </div>
</div>

{{-- ── Table ─────────────────────────────────────────────────────────────── --}}
<div class="card">
    <div class="card-body p-0">
        <table class="table table-striped mb-0">
            <thead>
                <tr>
                    <th>{{ __('ui.detections.detected_at') }}</th>
                    <th>{{ __('ui.detections.type') }}</th>
                    <th style="width:36px" title="Izvor"><i class="fas fa-tag"></i></th>
                    <th>{{ __('ui.detections.count') }}</th>
                    @if($showStationColumn)<th>Postaja</th>@endif
                    <th>Let / Izvor</th>
                    <th>{{ __('ui.detections.notes') }}</th>
                    <th>Prijavio</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                @forelse($detections as $det)
                @php
                    $colors = [
                        'person'    => ['bg'=>'#fd7e14','icon'=>'fa-user'],
                        'group'     => ['bg'=>'#dc3545','icon'=>'fa-users'],
                        'vehicle'   => ['bg'=>'#0d6efd','icon'=>'fa-car'],
                        'smuggling' => ['bg'=>'#6f42c1','icon'=>'fa-box'],
                        'other'     => ['bg'=>'#6c757d','icon'=>'fa-question-circle'],
                    ];
                    $style = $colors[$det->type] ?? $colors['other'];
                    $src   = $det->source ?? 'drone';
                    $srcIcon  = ['drone'=>'fa-helicopter','camera'=>'fa-camera','manual'=>'fa-walking'][$src] ?? 'fa-helicopter';
                    $srcTitle = ['drone'=>'Dron let','camera'=>'Lovačka kamera','manual'=>'Ručno'][$src] ?? 'Dron';
                @endphp
                <tr>
                    <td>
                        <strong>{{ $det->detected_at->format('d.m.Y') }}</strong>
                        <small class="d-block text-muted">{{ $det->detected_at->format('H:i') }}</small>
                        @if($det->escalation_level > 0)
                            @php $escColors = [1=>'#f0c040',2=>'#fd7e14',3=>'#dc3545']; @endphp
                            <span style="font-size:.7rem;color:{{ $escColors[$det->escalation_level] ?? '' }}">
                                <i class="fas fa-exclamation-triangle"></i>
                                {{ ['1'=>'Upozorenje','2'=>'Intervencija','3'=>'Kritično'][$det->escalation_level] ?? '' }}
                            </span>
                        @endif
                    </td>
                    <td>
                        <span class="badge" style="background:{{ $style['bg'] }};color:#fff;font-size:.78rem;padding:4px 9px">
                            <i class="fas {{ $style['icon'] }} mr-1"></i>
                            {{ __('ui.detections.types.' . $det->type) }}
                        </span>
                        @if($det->confirmed)
                            <br><small class="text-success"><i class="fas fa-check-circle"></i> Potvrđeno</small>
                        @endif
                    </td>
                    <td>
                        <span title="{{ $srcTitle }}" style="color:rgba(255,255,255,.45);font-size:.85rem">
                            <i class="fas {{ $srcIcon }}"></i>
                        </span>
                    </td>
                    <td><strong>{{ $det->count }}</strong></td>
                    @if($showStationColumn)
                    <td>
                        @if($src === 'drone' && $det->flight)
                            <small class="text-muted d-block">{{ $det->flight->station->administration->name ?? '' }}</small>
                            {{ $det->flight->station->name ?? '—' }}
                        @elseif($src === 'camera' && $det->camera)
                            <small class="text-muted d-block">{{ $det->camera->station?->administration?->name ?? '' }}</small>
                            {{ $det->camera->station?->name ?? '—' }}
                        @else
                            <span class="text-muted">—</span>
                        @endif
                    </td>
                    @endif
                    <td>
                        @if($src === 'drone' && $det->flight)
                            <a href="{{ route('flights.show', $det->flight_id) }}" class="text-info">
                                {{ $det->flight->location ?? 'Let #' . $det->flight_id }}
                            </a>
                            @if($det->flight->drone)
                                <small class="d-block text-muted">{{ $det->flight->drone->name }}</small>
                            @endif
                        @elseif($src === 'camera' && $det->camera)
                            <span class="text-warning">
                                <i class="fas fa-camera mr-1"></i>{{ $det->camera->name }}
                            </span>
                            @if($det->camera->location_name)
                                <small class="d-block text-muted">{{ $det->camera->location_name }}</small>
                            @endif
                        @else
                            <span class="text-muted"><i class="fas fa-walking mr-1"></i>Ručna prijava</span>
                        @endif
                    </td>
                    <td>
                        @if($det->notes)
                            <small>{{ Str::limit($det->notes, 60) }}</small>
                        @else
                            <span class="text-muted">—</span>
                        @endif
                    </td>
                    <td><small>{{ $det->user?->name ?? '—' }}</small></td>
                    <td class="text-nowrap">
                        @if($src === 'drone' && $det->flight_id)
                        <a href="{{ route('flights.show', $det->flight_id) }}" class="btn btn-xs btn-info" title="Otvori let">
                            <i class="fas fa-eye"></i>
                        </a>
                        @endif
                        @if(auth()->user()->hasRole('admin') || $det->user_id === auth()->id())
                        <form action="{{ route('detections.destroy', $det) }}" method="POST" style="display:inline"
                              onsubmit="return confirm('{{ __('ui.are_you_sure') }}')">
                            @csrf @method('DELETE')
                            <button class="btn btn-xs btn-danger"><i class="fas fa-trash"></i></button>
                        </form>
                        @endif
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="{{ $showStationColumn ? 9 : 8 }}" class="text-center py-4 text-muted">
                        <i class="fas fa-crosshairs mr-2"></i>{{ __('ui.detections.no_detections') }}
                    </td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>
    @if($detections->hasPages())
    <div class="card-footer">{{ $detections->links() }}</div>
    @endif
</div>

@endsection

@if($administrations)
@section('js')
<script>
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
</script>
@endsection
@endif
