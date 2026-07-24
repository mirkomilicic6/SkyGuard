@extends('adminlte::page')

@section('title', __('ui.flights.flight_details'))

@section('content_header')
@endsection

@section('content')

{{-- ── PAGE HEADER ──────────────────────────────────────────── --}}
<div class="fs-header mb-4">
    <div class="fs-header-left">
        <a href="{{ route('flights.index') }}" class="fs-back-btn">
            <i class="fas fa-arrow-left"></i>
        </a>
        <div>
            <div class="fs-page-title">{{ __('ui.flights.flight_details') }}</div>
            <div class="fs-page-sub">
                {{ $flight->flight_date->translatedFormat('d F Y, H:i') }}
                &nbsp;·&nbsp; {{ $flight->drone->name }}
                &nbsp;·&nbsp; {{ $flight->pilot->name }}
            </div>
        </div>
    </div>
    <div class="d-flex align-items-center gap-2" style="gap:.6rem">
        <span class="fs-status-badge fs-status-{{ $flight->status }}">
            {{ __('ui.status.' . $flight->status, [], null) ?: ucfirst($flight->status) }}
        </span>
        @if(!auth()->user()->hasRole('viewer'))
        <a href="{{ route('flights.edit', $flight) }}" class="fs-action-btn">
            <i class="fas fa-edit mr-1"></i> {{ __('ui.edit') }}
        </a>
        @endif
    </div>
</div>

@if(session('success'))
    <div class="alert alert-success alert-dismissible mb-3">
        <button type="button" class="close" data-dismiss="alert">&times;</button>
        {{ session('success') }}
    </div>
@endif

{{-- ── MAIN GRID ────────────────────────────────────────────── --}}
<div class="row">

{{-- LEFT COLUMN ──────────────────────────────────────────────── --}}
<div class="col-lg-4">

    {{-- Flight Stats --}}
    <div class="fs-card mb-3">
        <div class="fs-card-header">
            <i class="fas fa-chart-bar mr-2" style="color:var(--gold)"></i>
            {{ __('ui.flights.flight_info') }}
        </div>
        <div class="fs-stat-grid">
            <div class="fs-stat">
                <div class="fs-stat-icon" style="background:rgba(60,141,188,0.12);color:#3c8dbc"><i class="fas fa-helicopter"></i></div>
                <div>
                    <div class="fs-stat-val"><a href="{{ route('drones.show', $flight->drone) }}" class="fs-link">{{ $flight->drone->name }}</a></div>
                    <div class="fs-stat-lbl">{{ __('ui.flights.drone') }}</div>
                </div>
            </div>
            <div class="fs-stat">
                <div class="fs-stat-icon" style="background:rgba(40,167,69,0.12);color:#28a745"><i class="fas fa-user"></i></div>
                <div>
                    <div class="fs-stat-val">{{ $flight->pilot->name }}</div>
                    <div class="fs-stat-lbl">{{ __('ui.flights.pilot') }}</div>
                </div>
            </div>
            <div class="fs-stat">
                <div class="fs-stat-icon" style="background:rgba(240,192,64,0.12);color:#f0c040"><i class="fas fa-clock"></i></div>
                <div>
                    <div class="fs-stat-val">{{ $flight->duration_minutes ? $flight->duration_minutes . ' min' : '—' }}</div>
                    <div class="fs-stat-lbl">{{ __('ui.flights.duration') }}</div>
                </div>
            </div>
            <div class="fs-stat">
                <div class="fs-stat-icon" style="background:rgba(23,162,184,0.12);color:#17a2b8"><i class="fas fa-route"></i></div>
                <div>
                    <div class="fs-stat-val">{{ $flight->distance_km ? $flight->distance_km . ' km' : '—' }}</div>
                    <div class="fs-stat-lbl">{{ __('ui.flights.distance') }}</div>
                </div>
            </div>
            <div class="fs-stat">
                <div class="fs-stat-icon" style="background:rgba(111,66,193,0.12);color:#9b6ff5"><i class="fas fa-mountain"></i></div>
                <div>
                    <div class="fs-stat-val">{{ $flight->max_altitude_m ? $flight->max_altitude_m . ' m' : '—' }}</div>
                    <div class="fs-stat-lbl">{{ __('ui.flights.max_altitude') }}</div>
                </div>
            </div>
            <div class="fs-stat">
                <div class="fs-stat-icon" style="background:rgba(253,126,20,0.12);color:#fd7e14"><i class="fas fa-tachometer-alt"></i></div>
                <div>
                    <div class="fs-stat-val">{{ $flight->avg_speed_kmh ? $flight->avg_speed_kmh . ' km/h' : '—' }}</div>
                    <div class="fs-stat-lbl">{{ __('ui.flights.avg_speed') }}</div>
                </div>
            </div>
        </div>
        @if($flight->location || $flight->purpose)
        <div class="fs-card-divider"></div>
        @if($flight->location)
        <div class="fs-meta-row"><i class="fas fa-map-marker-alt mr-2" style="color:var(--gold)"></i>{{ $flight->location }}</div>
        @endif
        @if($flight->purpose)
        <div class="fs-meta-row"><i class="fas fa-bullseye mr-2" style="color:rgba(255,255,255,.4)"></i>{{ $flight->purpose }}</div>
        @endif
        @endif
    </div>

    {{-- Detections Panel --}}
    <div class="fs-card">
        <div class="fs-card-header">
            <div class="d-flex align-items-center gap-2" style="gap:.6rem">
                <i class="fas fa-map-pin mr-1" style="color:#f46d43"></i>
                {{ __('ui.detections.title') }}
                <span class="fs-badge-count" id="detectionCount">{{ $flight->detections->count() }}</span>
            </div>
            @if($flight->gpxPoints->count() > 0 && !auth()->user()->hasRole('viewer'))
            <span class="fs-hint-label"><i class="fas fa-hand-pointer mr-1"></i>{{ __('ui.flights.click_to_add') }}</span>
            @endif
        </div>
        <div class="fs-detection-list" id="detectionList">
            @forelse($flight->detections as $det)
            <div class="fs-det-item" id="det-{{ $det->id }}">
                <div class="fs-det-type-dot" style="background:{{ $det->typeColor() }}"></div>
                <div class="fs-det-body">
                    <div class="fs-det-title">
                        <i class="fas {{ $det->typeIcon() }} mr-1" style="color:{{ $det->typeColor() }}"></i>
                        {{ __('ui.detections.types.' . $det->type, [], null) ?: ucfirst($det->type) }}
                        <span class="fs-det-count">×{{ $det->count }}</span>
                    </div>
                    <div class="fs-det-meta">{{ $det->detected_at->translatedFormat('d F H:i') }} &middot; {{ $det->user->name }}</div>
                    @if($det->notes)
                    <div class="fs-det-notes">{{ Str::limit($det->notes, 70) }}</div>
                    @endif
                </div>
                @if(auth()->user()->hasRole('admin') || $det->user_id === auth()->id())
                <button class="fs-det-delete btn-delete-detection"
                        data-id="{{ $det->id }}"
                        data-url="{{ route('detections.destroy', $det) }}"
                        title="{{ __('ui.delete') }}">
                    <i class="fas fa-times"></i>
                </button>
                @endif
            </div>
            @empty
            <div class="fs-det-empty" id="noDetections">
                <i class="fas fa-map-pin"></i>
                <span>{{ __('ui.detections.no_detections') }}</span>
            </div>
            @endforelse
        </div>
    </div>

</div>{{-- /left col --}}

{{-- RIGHT COLUMN ─────────────────────────────────────────────── --}}
<div class="col-lg-8">

    {{-- Map --}}
    <div class="fs-card mb-3">
        <div class="fs-card-header">
            <i class="fas fa-map-marked-alt mr-2" style="color:var(--gold)"></i>
            {{ __('ui.flights.flight_path') }}
            @if($flight->gpxPoints->count() > 0 && !auth()->user()->hasRole('viewer'))
            <span class="fs-hint-label ml-auto"><i class="fas fa-hand-pointer mr-1"></i>{{ __('ui.flights.click_map_hint') }}</span>
            @endif
        </div>
        @if($flight->gpxPoints->count() > 0)
            <div id="map" style="height:430px; border-radius:0 0 12px 12px;"></div>
        @else
            <div class="fs-empty-map">
                <i class="fas fa-map-marked-alt"></i>
                <div>{{ __('ui.flights.no_gpx') }}</div>
                @if(!auth()->user()->hasRole('viewer'))
                <a href="{{ route('flights.edit', $flight) }}" class="fs-action-btn mt-2">{{ __('ui.flights.upload_gpx') }}</a>
                @endif
            </div>
        @endif
    </div>

    {{-- Altitude chart --}}
    @if($flight->gpxPoints->count() > 0)
    <div class="fs-card">
        <div class="fs-card-header">
            <i class="fas fa-chart-area mr-2" style="color:var(--gold)"></i>
            {{ __('ui.flights.altitude_profile') }}
        </div>
        <div style="padding:1rem 1.2rem">
            <canvas id="altitudeChart" height="70"></canvas>
        </div>
    </div>
    @endif

</div>{{-- /right col --}}
</div>{{-- /row --}}

{{-- ================================================================
     DETECTION MODAL (dark themed)
     ================================================================ --}}
@if(!auth()->user()->hasRole('viewer'))
<div class="fs-modal-backdrop" id="detModal" style="display:none" onclick="if(event.target===this)closeDetModal()">
    <div class="fs-modal">
        <div class="fs-modal-header">
            <div class="fs-modal-title">
                <i class="fas fa-map-pin mr-2" style="color:#f46d43"></i>
                {{ __('ui.detections.log_detection') }}
            </div>
            <button class="fs-modal-close" onclick="closeDetModal()"><i class="fas fa-times"></i></button>
        </div>
        <div class="fs-modal-body">
            <input type="hidden" id="det_lat">
            <input type="hidden" id="det_lon">

            {{-- Coordinates --}}
            <div class="fs-modal-coords mb-3">
                <div class="fs-coord-box">
                    <div class="fs-coord-label">{{ __('ui.detections.latitude') }}</div>
                    <div class="fs-coord-val" id="det_lat_display">—</div>
                </div>
                <div class="fs-coord-sep"><i class="fas fa-crosshairs"></i></div>
                <div class="fs-coord-box">
                    <div class="fs-coord-label">{{ __('ui.detections.longitude') }}</div>
                    <div class="fs-coord-val" id="det_lon_display">—</div>
                </div>
            </div>

            {{-- Type --}}
            <div class="fs-form-group">
                <label class="fs-label">{{ __('ui.detections.type') }}</label>
                <div class="fs-type-grid" id="typeGrid">
                    @foreach([
                        ['value'=>'person',    'label'=>__('ui.detections.types.person'),    'icon'=>'fa-user',           'color'=>'#fd7e14'],
                        ['value'=>'group',     'label'=>__('ui.detections.types.group'),     'icon'=>'fa-users',          'color'=>'#dc3545'],
                        ['value'=>'vehicle',   'label'=>__('ui.detections.types.vehicle'),   'icon'=>'fa-car',            'color'=>'#0d6efd'],
                        ['value'=>'smuggling', 'label'=>__('ui.detections.types.smuggling'), 'icon'=>'fa-box',            'color'=>'#6f42c1'],
                        ['value'=>'other',     'label'=>__('ui.detections.types.other'),     'icon'=>'fa-question-circle','color'=>'#6c757d'],
                    ] as $t)
                    <button type="button" class="fs-type-btn" data-value="{{ $t['value'] }}"
                            style="--tc:{{ $t['color'] }}" onclick="selectType(this)">
                        <i class="fas {{ $t['icon'] }}"></i>
                        <span>{{ $t['label'] }}</span>
                    </button>
                    @endforeach
                </div>
                <input type="hidden" id="det_type" value="person">
            </div>

            {{-- Count --}}
            <div class="fs-form-group">
                <label class="fs-label">{{ __('ui.detections.count') }}</label>
                <div class="fs-count-row">
                    <button type="button" class="fs-count-btn" onclick="changeCount(-1)"><i class="fas fa-minus"></i></button>
                    <input type="number" id="det_count" class="fs-count-input" value="1" min="1" max="999">
                    <button type="button" class="fs-count-btn" onclick="changeCount(1)"><i class="fas fa-plus"></i></button>
                </div>
            </div>

            {{-- Notes --}}
            <div class="fs-form-group">
                <label class="fs-label">{{ __('ui.detections.notes') }} <span class="fs-optional">({{ __('ui.optional') }})</span></label>
                <textarea id="det_notes" class="fs-textarea" rows="2" maxlength="500"
                          placeholder="{{ __('ui.detections.notes_placeholder') ?? '' }}"></textarea>
            </div>

            {{-- Time --}}
            <div class="fs-form-group">
                <label class="fs-label">{{ __('ui.detections.detected_at') }} <span class="fs-optional">({{ __('ui.optional') }})</span></label>
                <input type="datetime-local" id="det_detected_at" class="fs-input">
            </div>
        </div>
        <div class="fs-modal-footer">
            <button type="button" class="fs-btn-cancel" onclick="closeDetModal()">{{ __('ui.cancel') }}</button>
            <button type="button" class="fs-btn-save" id="btnSaveDetection">
                <i class="fas fa-map-pin mr-1"></i> {{ __('ui.detections.save_detection') }}
            </button>
        </div>
    </div>
</div>
@endif

@endsection

@section('css')
<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css"/>
<style>
/* ── HIDE CONTENT HEADER ─────────────────────────────── */
.content-header { display: none; }

/* ── PAGE HEADER ─────────────────────────────────────── */
.fs-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    flex-wrap: wrap;
    gap: 1rem;
}
.fs-header-left { display: flex; align-items: center; gap: 1rem; }
.fs-back-btn {
    width: 38px; height: 38px;
    border-radius: 9px;
    background: rgba(255,255,255,0.07);
    border: 1px solid rgba(255,255,255,0.1);
    color: rgba(255,255,255,0.7);
    display: flex; align-items: center; justify-content: center;
    text-decoration: none; font-size: .9rem; flex-shrink: 0;
    transition: background .15s;
}
.fs-back-btn:hover { background: rgba(255,255,255,0.14); color: #fff; text-decoration: none; }
.fs-page-title { font-size: 1.2rem; font-weight: 700; color: #fff; }
.fs-page-sub   { font-size: 0.78rem; color: rgba(255,255,255,0.4); margin-top: 2px; }

.fs-status-badge {
    padding: 4px 12px;
    border-radius: 20px;
    font-size: .72rem;
    font-weight: 700;
    letter-spacing: .4px;
    text-transform: uppercase;
}
.fs-status-completed { background: rgba(40,167,69,.2);  color: #7ddc9f; }
.fs-status-aborted   { background: rgba(220,53,69,.2);  color: #f5a0a0; }

.fs-action-btn {
    padding: 6px 16px;
    border-radius: 9px;
    background: rgba(255,255,255,0.08);
    border: 1px solid rgba(255,255,255,0.15);
    color: rgba(255,255,255,0.8);
    font-size: .82rem;
    text-decoration: none;
    display: inline-flex; align-items: center;
    transition: background .15s;
    cursor: pointer;
}
.fs-action-btn:hover { background: rgba(255,255,255,0.15); color: #fff; text-decoration: none; }
.fs-link { color: var(--gold) !important; text-decoration: none; }
.fs-link:hover { text-decoration: underline; }

/* ── CARD ────────────────────────────────────────────── */
.fs-card {
    background: var(--navy-card);
    border: 1px solid rgba(255,255,255,.06);
    border-radius: 12px;
    overflow: hidden;
}
.fs-card-header {
    display: flex;
    align-items: center;
    padding: .85rem 1.2rem;
    border-bottom: 1px solid rgba(255,255,255,.06);
    font-size: .88rem;
    font-weight: 600;
    color: #fff;
}
.fs-card-divider { border-top: 1px solid rgba(255,255,255,.05); margin: 0; }
.fs-meta-row {
    padding: .55rem 1.2rem;
    font-size: .82rem;
    color: rgba(255,255,255,.6);
    border-bottom: 1px solid rgba(255,255,255,.04);
}
.fs-meta-row:last-child { border: none; }

.fs-hint-label { font-size: .7rem; color: rgba(255,255,255,.3); font-weight: 400; white-space: nowrap; }

/* ── STAT GRID ───────────────────────────────────────── */
.fs-stat-grid { display: grid; grid-template-columns: 1fr 1fr; }
.fs-stat {
    display: flex;
    align-items: center;
    gap: .75rem;
    padding: .75rem 1.2rem;
    border-bottom: 1px solid rgba(255,255,255,.04);
    border-right: 1px solid rgba(255,255,255,.04);
}
.fs-stat:nth-child(even) { border-right: none; }
.fs-stat:nth-last-child(-n+2) { border-bottom: none; }
.fs-stat-icon {
    width: 34px; height: 34px; border-radius: 8px;
    display: flex; align-items: center; justify-content: center;
    font-size: .85rem; flex-shrink: 0;
}
.fs-stat-val { font-size: .88rem; font-weight: 600; color: #fff; }
.fs-stat-lbl { font-size: .68rem; color: rgba(255,255,255,.35); margin-top: 1px; text-transform: uppercase; letter-spacing: .4px; }

/* ── DETECTION LIST ──────────────────────────────────── */
.fs-detection-list { padding: .4rem 0; max-height: 340px; overflow-y: auto; }
.fs-det-item {
    display: flex;
    align-items: flex-start;
    gap: .7rem;
    padding: .65rem 1.2rem;
    border-bottom: 1px solid rgba(255,255,255,.04);
    position: relative;
}
.fs-det-item:last-child { border: none; }
.fs-det-type-dot {
    width: 8px; height: 8px; border-radius: 50%;
    flex-shrink: 0; margin-top: 5px;
}
.fs-det-body { flex: 1; min-width: 0; }
.fs-det-title { font-size: .83rem; font-weight: 600; color: #fff; }
.fs-det-count {
    display: inline-block;
    background: rgba(255,255,255,.1);
    border-radius: 10px;
    padding: 0 6px;
    font-size: .7rem;
    font-weight: 400;
    margin-left: 4px;
}
.fs-det-meta  { font-size: .7rem; color: rgba(255,255,255,.35); margin-top: 2px; }
.fs-det-notes { font-size: .72rem; color: rgba(255,255,255,.45); margin-top: 2px; font-style: italic; }
.fs-det-delete {
    width: 26px; height: 26px; border-radius: 6px;
    background: rgba(220,53,69,.1);
    border: 1px solid rgba(220,53,69,.2);
    color: #f5a0a0;
    display: flex; align-items: center; justify-content: center;
    font-size: .72rem; cursor: pointer; flex-shrink: 0;
    transition: background .15s;
}
.fs-det-delete:hover { background: rgba(220,53,69,.25); color: #fff; }
.fs-badge-count {
    background: rgba(244,109,67,.25);
    color: #f46d43;
    border-radius: 20px;
    padding: 1px 8px;
    font-size: .72rem;
    font-weight: 700;
}
.fs-det-empty {
    display: flex; flex-direction: column;
    align-items: center; justify-content: center;
    gap: .5rem;
    padding: 2rem 1rem;
    color: rgba(255,255,255,.2);
    font-size: .82rem;
}
.fs-det-empty i { font-size: 1.6rem; }

/* ── EMPTY MAP ───────────────────────────────────────── */
.fs-empty-map {
    height: 220px;
    display: flex; flex-direction: column;
    align-items: center; justify-content: center;
    color: rgba(255,255,255,.2); font-size: 2.5rem; gap: .75rem;
}
.fs-empty-map div { font-size: .9rem; }

/* ── MODAL ───────────────────────────────────────────── */
.fs-modal-backdrop {
    position: fixed; inset: 0;
    background: rgba(0,0,0,.6);
    backdrop-filter: blur(4px);
    z-index: 9999;
    align-items: center;
    justify-content: center;
    padding: 1rem;
}
.fs-modal {
    background: #0d2460;
    border: 1px solid rgba(255,255,255,.1);
    border-radius: 16px;
    width: 100%;
    max-width: 460px;
    box-shadow: 0 24px 60px rgba(0,0,0,.5);
    overflow: hidden;
}
.fs-modal-header {
    display: flex; align-items: center; justify-content: space-between;
    padding: 1rem 1.4rem;
    border-bottom: 1px solid rgba(255,255,255,.08);
}
.fs-modal-title { font-size: .95rem; font-weight: 700; color: #fff; }
.fs-modal-close {
    width: 30px; height: 30px; border-radius: 8px;
    background: rgba(255,255,255,.07); border: none;
    color: rgba(255,255,255,.5); cursor: pointer; font-size: .85rem;
    display: flex; align-items: center; justify-content: center;
    transition: background .15s;
}
.fs-modal-close:hover { background: rgba(255,255,255,.14); color: #fff; }
.fs-modal-body { padding: 1.2rem 1.4rem; }
.fs-modal-footer {
    display: flex; gap: .6rem; justify-content: flex-end;
    padding: .9rem 1.4rem;
    border-top: 1px solid rgba(255,255,255,.08);
}

/* Coordinates */
.fs-modal-coords {
    display: flex; align-items: center; gap: .5rem;
    background: rgba(0,0,0,.25);
    border: 1px solid rgba(255,255,255,.07);
    border-radius: 10px;
    padding: .6rem 1rem;
}
.fs-coord-box { flex: 1; text-align: center; }
.fs-coord-label { font-size: .62rem; color: rgba(255,255,255,.35); text-transform: uppercase; letter-spacing: .5px; }
.fs-coord-val { font-size: .82rem; font-weight: 600; color: #fff; margin-top: 2px; font-family: monospace; }
.fs-coord-sep { color: rgba(255,255,255,.2); font-size: 1rem; }

/* Form elements */
.fs-form-group { margin-bottom: 1rem; }
.fs-label {
    display: block;
    font-size: .75rem; font-weight: 600;
    color: rgba(255,255,255,.5);
    text-transform: uppercase; letter-spacing: .5px;
    margin-bottom: .4rem;
}
.fs-optional { font-weight: 400; text-transform: none; letter-spacing: 0; color: rgba(255,255,255,.25); }
.fs-input, .fs-textarea {
    width: 100%;
    background: rgba(255,255,255,.07) !important;
    border: 1px solid rgba(255,255,255,.1) !important;
    border-radius: 9px;
    color: #fff !important;
    padding: .55rem .8rem;
    font-size: .85rem;
}
.fs-input:focus, .fs-textarea:focus {
    outline: none;
    border-color: rgba(240,192,64,.5) !important;
    box-shadow: 0 0 0 3px rgba(240,192,64,.1);
}
.fs-textarea { resize: none; }

/* Type selector */
.fs-type-grid { display: grid; grid-template-columns: repeat(5,1fr); gap: .4rem; }
.fs-type-btn {
    display: flex; flex-direction: column;
    align-items: center; justify-content: center;
    gap: .3rem;
    padding: .6rem .3rem;
    border-radius: 10px;
    background: rgba(255,255,255,.05);
    border: 2px solid rgba(255,255,255,.08);
    color: rgba(255,255,255,.5);
    cursor: pointer; font-size: .65rem;
    transition: all .15s;
}
.fs-type-btn i { font-size: 1.1rem; }
.fs-type-btn:hover {
    background: color-mix(in srgb, var(--tc) 15%, transparent);
    border-color: var(--tc);
    color: #fff;
}
.fs-type-btn.active {
    background: color-mix(in srgb, var(--tc) 22%, transparent);
    border-color: var(--tc);
    color: #fff;
}

/* Count row */
.fs-count-row { display: flex; align-items: center; gap: .5rem; width: 140px; }
.fs-count-btn {
    width: 34px; height: 34px; border-radius: 8px;
    background: rgba(255,255,255,.08);
    border: 1px solid rgba(255,255,255,.12);
    color: rgba(255,255,255,.7); cursor: pointer; font-size: .85rem;
    display: flex; align-items: center; justify-content: center;
    transition: background .15s;
}
.fs-count-btn:hover { background: rgba(255,255,255,.15); color: #fff; }
.fs-count-input {
    flex: 1; text-align: center;
    background: rgba(255,255,255,.07) !important;
    border: 1px solid rgba(255,255,255,.1) !important;
    border-radius: 8px; color: #fff !important;
    padding: 6px; font-size: .9rem; font-weight: 600;
}

/* Buttons */
.fs-btn-cancel {
    padding: 8px 18px; border-radius: 9px;
    background: rgba(255,255,255,.07);
    border: 1px solid rgba(255,255,255,.1);
    color: rgba(255,255,255,.6);
    cursor: pointer; font-size: .85rem;
    transition: background .15s;
}
.fs-btn-cancel:hover { background: rgba(255,255,255,.14); color: #fff; }
.fs-btn-save {
    padding: 8px 20px; border-radius: 9px;
    background: rgba(244,109,67,.2);
    border: 1px solid rgba(244,109,67,.45);
    color: #f9a87d;
    cursor: pointer; font-size: .85rem; font-weight: 600;
    transition: background .15s;
}
.fs-btn-save:hover { background: rgba(244,109,67,.35); color: #fff; }
.fs-btn-save:disabled { opacity: .5; cursor: not-allowed; }

/* Map cursor */
#map { cursor: crosshair; }

/* Leaflet popup dark */
.leaflet-popup-content-wrapper {
    background: #0d2460 !important;
    border: 1px solid rgba(255,255,255,.12) !important;
    color: rgba(255,255,255,.85) !important;
    border-radius: 10px !important;
    box-shadow: 0 8px 24px rgba(0,0,0,.4) !important;
}
.leaflet-popup-tip { background: #0d2460 !important; }
.leaflet-popup-content a { color: var(--gold); }
</style>
@endsection

@section('js')
@if($flight->gpxPoints->count() > 0)
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
<script src="https://cdn.jsdelivr.net/npm/chart.js@4/dist/chart.umd.min.js"></script>
@php
$gpxData = $flight->gpxPoints->map(fn($p) => [
    'lat'   => $p->latitude,
    'lon'   => $p->longitude,
    'alt'   => $p->altitude,
    'speed' => $p->speed,
    'time'  => $p->timestamp?->format('H:i:s'),
    'order' => $p->point_order,
]);
$existingDetections = $flight->detections->map(fn($d) => [
    'id'          => $d->id,
    'latitude'    => $d->latitude,
    'longitude'   => $d->longitude,
    'type'        => $d->type,
    'type_label'  => __('ui.detections.types.' . $d->type) ?: ucfirst($d->type),
    'count'       => $d->count,
    'notes'       => $d->notes,
    'detected_at' => $d->detected_at->translatedFormat('d F Y H:i'),
    'color'       => $d->typeColor(),
    'icon'        => $d->typeIcon(),
    'reported_by' => $d->user->name,
    'can_delete'  => auth()->user()->hasRole('admin') || $d->user_id === auth()->id(),
    'delete_url'  => route('detections.destroy', $d),
]);
@endphp
<script>
const gpxPoints          = @json($gpxData);
const existingDetections = @json($existingDetections);
const storeUrl           = "{{ route('detections.store', $flight) }}";
const csrfToken          = "{{ csrf_token() }}";
const i18n = {
    saveDetection: "{{ __('ui.detections.save_detection') }}",
    noDetections:  "{{ __('ui.detections.no_detections') }}",
    deleteConfirm: "{{ __('ui.detections.delete_confirm') }}",
    saveError:     "{{ __('ui.detections.save_error') }}",
    deleteError:   "{{ __('ui.detections.delete_error') }}",
    altLabel:      "{{ __('ui.flights.altitude_label') }}",
};

// ── MAP ──────────────────────────────────────────────────────
const latlngs = gpxPoints.map(p => [p.lat, p.lon]);
const map = L.map('map');

L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
    attribution: '© OpenStreetMap', maxZoom: 19
}).addTo(map);

// Flight path — dark casing underneath for contrast against the light basemap
L.polyline(latlngs, { color: '#0d2460', weight: 5, opacity: 0.9 }).addTo(map);
L.polyline(latlngs, { color: '#f0c040', weight: 2.5, opacity: 0.95 }).addTo(map);
const bounds = L.latLngBounds(latlngs);
map.fitBounds(bounds, { padding: [25, 25] });

// Start / End markers
const dotIcon = (color) => L.divIcon({
    className: '', iconSize: [14, 14], iconAnchor: [7, 7],
    html: `<div style="width:14px;height:14px;border-radius:50%;background:${color};border:2px solid #fff;box-shadow:0 0 6px rgba(0,0,0,.5)"></div>`
});
L.marker(latlngs[0], { icon: dotIcon('#28a745') }).addTo(map)
  .bindPopup('<strong style="color:#7ddc9f">Start</strong>' + (gpxPoints[0].time ? '<br><small>' + gpxPoints[0].time + '</small>' : ''));
const last = gpxPoints[gpxPoints.length - 1];
L.marker([last.lat, last.lon], { icon: dotIcon('#dc3545') }).addTo(map)
  .bindPopup('<strong style="color:#f5a0a0">End</strong>' + (last.time ? '<br><small>' + last.time + '</small>' : ''));

// Waypoints
const step = Math.max(1, Math.floor(gpxPoints.length / 8));
gpxPoints.forEach((p, i) => {
    if (i === 0 || i === gpxPoints.length - 1 || i % step !== 0) return;
    L.circleMarker([p.lat, p.lon], { radius: 4, color: '#f0c040', weight: 1.5, fillColor: '#0d2460', fillOpacity: 1 })
     .addTo(map)
     .bindPopup([p.time ? '<small>'+p.time+'</small>' : '', p.alt != null ? 'Alt: <strong>'+p.alt+' m</strong>' : '', p.speed != null ? 'Speed: <strong>'+p.speed+' km/h</strong>' : ''].filter(Boolean).join('<br>'));
});

// ── DETECTION PINS ───────────────────────────────────────────
const detectionMarkers = {};

function makePin(color) {
    return L.divIcon({
        className: '', iconSize: [26, 32], iconAnchor: [13, 32], popupAnchor: [0, -32],
        html: `<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 26 32" width="26" height="32">
            <path fill="${color}" stroke="rgba(13,36,96,0.85)" stroke-width="1.5"
                  d="M13 1C7.48 1 3 5.48 3 11c0 7.5 10 20 10 20S23 18.5 23 11c0-5.52-4.48-10-10-10z"/>
            <circle cx="13" cy="11" r="4" fill="rgba(255,255,255,0.9)"/>
        </svg>`
    });
}

function addDetectionMarker(d) {
    const label = d.type_label || (d.type.charAt(0).toUpperCase() + d.type.slice(1));
    const popup = `
        <div style="min-width:150px">
            <strong>${label}</strong> <span style="color:rgba(255,255,255,.5)">×${d.count}</span><br>
            <small style="color:rgba(255,255,255,.45)">${d.detected_at} · ${d.reported_by}</small>
            ${d.notes ? `<br><small style="color:rgba(255,255,255,.55);font-style:italic">${d.notes}</small>` : ''}
            ${d.can_delete ? `<br><button onclick="deleteDetection(${d.id},'${d.delete_url}')" style="margin-top:6px;padding:3px 10px;border-radius:6px;background:rgba(220,53,69,.2);border:1px solid rgba(220,53,69,.4);color:#f5a0a0;cursor:pointer;font-size:.72rem">Obriši</button>` : ''}
        </div>`;
    const marker = L.marker([d.latitude, d.longitude], { icon: makePin(d.color) })
        .addTo(map).bindPopup(popup, { className: 'fs-leaflet-popup' });
    detectionMarkers[d.id] = marker;
}

existingDetections.forEach(addDetectionMarker);

// ── MODAL LOGIC ───────────────────────────────────────────────
@if(!auth()->user()->hasRole('viewer'))
// Select first type button by default
document.querySelector('.fs-type-btn[data-value="person"]')?.classList.add('active');

function openDetModal(lat, lng) {
    document.getElementById('det_lat').value         = lat;
    document.getElementById('det_lon').value         = lng;
    document.getElementById('det_lat_display').textContent = parseFloat(lat).toFixed(5);
    document.getElementById('det_lon_display').textContent = parseFloat(lng).toFixed(5);
    document.getElementById('det_notes').value       = '';
    document.getElementById('det_count').value       = 1;
    document.getElementById('det_detected_at').value = '';
    document.getElementById('det_type').value        = 'person';
    document.querySelectorAll('.fs-type-btn').forEach(b => b.classList.remove('active'));
    document.querySelector('.fs-type-btn[data-value="person"]')?.classList.add('active');
    document.getElementById('detModal').style.display = 'flex';
    document.body.style.overflow = 'hidden';
}
function closeDetModal() {
    document.getElementById('detModal').style.display = 'none';
    document.body.style.overflow = '';
}
function selectType(btn) {
    document.querySelectorAll('.fs-type-btn').forEach(b => b.classList.remove('active'));
    btn.classList.add('active');
    document.getElementById('det_type').value = btn.dataset.value;
}
function changeCount(delta) {
    const el  = document.getElementById('det_count');
    const val = Math.min(999, Math.max(1, parseInt(el.value || 1) + delta));
    el.value  = val;
}

map.on('click', e => openDetModal(e.latlng.lat.toFixed(7), e.latlng.lng.toFixed(7)));

document.getElementById('btnSaveDetection').addEventListener('click', function() {
    const btn = this;
    btn.disabled = true;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin mr-1"></i>';

    fetch(storeUrl, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrfToken, 'Accept': 'application/json' },
        body: JSON.stringify({
            latitude:    document.getElementById('det_lat').value,
            longitude:   document.getElementById('det_lon').value,
            type:        document.getElementById('det_type').value,
            count:       document.getElementById('det_count').value,
            notes:       document.getElementById('det_notes').value || null,
            detected_at: document.getElementById('det_detected_at').value || null,
        }),
    })
    .then(r => r.json())
    .then(d => {
        if (d.id) {
            addDetectionMarker({ ...d, can_delete: true });
            addDetectionToList(d);
            updateCount(1);
            closeDetModal();
        }
    })
    .catch(() => alert(i18n.saveError))
    .finally(() => {
        btn.disabled = false;
        btn.innerHTML = '<i class="fas fa-map-pin mr-1"></i> ' + i18n.saveDetection;
    });
});

// Delete from list
document.addEventListener('click', function(e) {
    const btn = e.target.closest('.fs-det-delete');
    if (!btn) return;
    if (!confirm(i18n.deleteConfirm)) return;
    deleteDetection(btn.dataset.id, btn.dataset.url);
});

function deleteDetection(id, url) {
    fetch(url, { method: 'DELETE', headers: { 'X-CSRF-TOKEN': csrfToken, 'Accept': 'application/json' } })
    .then(r => r.json())
    .then(res => {
        if (!res.ok) return;
        const item = document.getElementById('det-' + id);
        if (item) item.remove();
        if (detectionMarkers[id]) { map.removeLayer(detectionMarkers[id]); delete detectionMarkers[id]; }
        updateCount(-1);
        if (!document.querySelector('#detectionList .fs-det-item')) {
            document.getElementById('detectionList').innerHTML =
                `<div class="fs-det-empty" id="noDetections"><i class="fas fa-map-pin"></i><span>${i18n.noDetections}</span></div>`;
        }
    })
    .catch(() => alert(i18n.deleteError));
}

function addDetectionToList(d) {
    const noItem = document.getElementById('noDetections');
    if (noItem) noItem.remove();
    const label = d.type_label || (d.type.charAt(0).toUpperCase() + d.type.slice(1));
    const el = document.createElement('div');
    el.className = 'fs-det-item';
    el.id = 'det-' + d.id;
    el.innerHTML = `
        <div class="fs-det-type-dot" style="background:${d.color}"></div>
        <div class="fs-det-body">
            <div class="fs-det-title">
                <i class="fas ${d.icon} mr-1" style="color:${d.color}"></i>
                ${label} <span class="fs-det-count">×${d.count}</span>
            </div>
            <div class="fs-det-meta">${d.detected_at} · ${d.reported_by}</div>
            ${d.notes ? `<div class="fs-det-notes">${d.notes.substring(0,70)}</div>` : ''}
        </div>
        <button class="fs-det-delete btn-delete-detection" data-id="${d.id}" data-url="${d.delete_url}">
            <i class="fas fa-times"></i>
        </button>`;
    document.getElementById('detectionList').appendChild(el);
}

function updateCount(delta) {
    const el = document.getElementById('detectionCount');
    el.textContent = parseInt(el.textContent) + delta;
}
@endif

// ── ALTITUDE CHART ───────────────────────────────────────────
const altCtx  = document.getElementById('altitudeChart').getContext('2d');
const altGrad = altCtx.createLinearGradient(0, 0, 0, 180);
altGrad.addColorStop(0, 'rgba(111,66,193,0.4)');
altGrad.addColorStop(1, 'rgba(111,66,193,0.02)');

new Chart(altCtx, {
    type: 'line',
    data: {
        labels:   gpxPoints.map(p => p.time ?? '#' + p.order),
        datasets: [{ label: i18n.altLabel, data: gpxPoints.map(p => p.alt),
            borderColor: '#9b6ff5', borderWidth: 2,
            backgroundColor: altGrad, pointRadius: 0, fill: true, tension: 0.4 }]
    },
    options: {
        responsive: true,
        plugins: { legend: { display: false },
            tooltip: { backgroundColor: '#0d2460', borderColor: 'rgba(255,255,255,.1)', borderWidth: 1,
                       titleColor: '#9b6ff5', bodyColor: 'rgba(255,255,255,.8)', padding: 8 }
        },
        scales: {
            x: { grid: { color: 'rgba(255,255,255,.04)' }, ticks: { color: 'rgba(255,255,255,.3)', maxTicksLimit: 10, font: { size: 10 } } },
            y: { grid: { color: 'rgba(255,255,255,.04)' }, ticks: { color: 'rgba(255,255,255,.3)', font: { size: 10 } },
                 title: { display: true, text: 'm', color: 'rgba(255,255,255,.3)', font: { size: 10 } } }
        }
    }
});
</script>
@endif
@endsection
