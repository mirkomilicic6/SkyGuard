@extends('adminlte::page')

@section('title', 'Nova detekcija')

@section('content_header')
    <div class="d-flex align-items-center justify-content-between">
        <h1><i class="fas fa-crosshairs mr-2 text-warning"></i>Nova detekcija</h1>
        <a href="{{ route('detections.index') }}" class="btn btn-secondary btn-sm">
            <i class="fas fa-arrow-left mr-1"></i> {{ __('ui.back') }}
        </a>
    </div>
@endsection

@section('css')
<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css"/>
<style>
/* ── Source selector ─────────────────────────────────────────────── */
.source-cards { display:flex; gap:12px; margin-bottom:1.5rem; flex-wrap:wrap; }
.source-card {
    flex:1; min-width:140px;
    border:2px solid rgba(255,255,255,0.1);
    border-radius:10px;
    padding:14px 16px;
    cursor:pointer;
    transition:.15s;
    text-align:center;
    background:transparent;
    color:rgba(255,255,255,0.55);
    user-select:none;
}
.source-card:hover { border-color:rgba(240,192,64,0.4); color:rgba(255,255,255,0.85); }
.source-card.active {
    border-color:#f0c040;
    background:rgba(240,192,64,0.1);
    color:#f0c040;
}
.source-card i { font-size:1.6rem; margin-bottom:6px; display:block; }
.source-card span { font-size:.82rem; font-weight:600; letter-spacing:.3px; }

/* ── Source sub-sections ─────────────────────────────────────────── */
.source-section { display:none; }
.source-section.visible { display:block; }

/* ── Map ─────────────────────────────────────────────────────────── */
#det-map {
    height:320px;
    border-radius:10px;
    border:1px solid rgba(255,255,255,0.1);
}
.map-hint {
    font-size:.78rem;
    color:rgba(255,255,255,0.4);
    margin-top:5px;
    text-align:center;
}

/* ── Escalation radio pills ──────────────────────────────────────── */
.esc-group { display:flex; gap:6px; flex-wrap:wrap; }
.esc-btn input { display:none; }
.esc-btn label {
    display:inline-block;
    padding:4px 14px;
    border-radius:20px;
    border:1px solid rgba(255,255,255,0.15);
    color:rgba(255,255,255,0.5);
    font-size:.8rem;
    cursor:pointer;
    transition:.15s;
}
.esc-btn input:checked + label { color:#fff; font-weight:600; }
.esc-0 input:checked + label { background:#495057; border-color:#6c757d; }
.esc-1 input:checked + label { background:rgba(240,192,64,.25); border-color:#f0c040; color:#f0c040; }
.esc-2 input:checked + label { background:rgba(253,126,20,.25); border-color:#fd7e14; color:#fd7e14; }
.esc-3 input:checked + label { background:rgba(220,53,69,.25); border-color:#dc3545; color:#dc3545; }

/* ── Section divider ─────────────────────────────────────────────── */
.form-section-label {
    font-size:.7rem;
    font-weight:700;
    letter-spacing:1.5px;
    color:rgba(255,255,255,0.3);
    text-transform:uppercase;
    margin:1.4rem 0 .6rem;
    padding-bottom:4px;
    border-bottom:1px solid rgba(255,255,255,0.07);
}

/* ── Confirmed toggle ────────────────────────────────────────────── */
.confirm-toggle { display:flex; align-items:center; gap:10px; }
.confirm-toggle .custom-control-label { cursor:pointer; font-size:.9rem; }
</style>
@endsection

@section('content')

@if($errors->any())
<div class="alert alert-danger">
    <ul class="mb-0">
        @foreach($errors->all() as $err)<li>{{ $err }}</li>@endforeach
    </ul>
</div>
@endif

<form method="POST" action="{{ route('detections.storeManual') }}" id="det-form">
@csrf

<div class="row">

{{-- ══ LEFT COLUMN ══════════════════════════════════════════════════════════ --}}
<div class="col-lg-7">

<div class="card">
<div class="card-body">

{{-- Source selector --}}
<p class="form-section-label" style="margin-top:0">Izvor detekcije</p>
<div class="source-cards">
    <div class="source-card active" data-source="drone">
        <i class="fas fa-helicopter"></i>
        <span>Dron let</span>
    </div>
    <div class="source-card" data-source="camera">
        <i class="fas fa-camera"></i>
        <span>Lovačka kamera</span>
    </div>
    <div class="source-card" data-source="manual">
        <i class="fas fa-walking"></i>
        <span>Ručno / Zemlja</span>
    </div>
</div>
<input type="hidden" name="source" id="source-input" value="drone">

{{-- Drone flight section --}}
<div class="source-section visible" id="sec-drone">
    <div class="form-group">
        <label>Let drona <span class="text-muted">({{ __('ui.optional') }})</span></label>
        <select name="flight_id" id="flight-select" class="form-control">
            <option value="">— Bez veze na let —</option>
            @foreach($flights as $fl)
            <option value="{{ $fl->id }}">
                {{ $fl->flight_date?->format('d.m.Y H:i') ?? '—' }}
                @if($fl->location) – {{ Str::limit($fl->location, 35) }}@endif
                @if($fl->drone) ({{ $fl->drone->name }})@endif
            </option>
            @endforeach
        </select>
        <small class="text-muted">Odaberite let ako je detekcija uočena za vrijeme patroliranja dronom.</small>
    </div>
</div>

{{-- Camera section --}}
<div class="source-section" id="sec-camera">
    <div class="form-group">
        <label>Lovačka kamera *</label>
        <select name="camera_id" id="camera-select" class="form-control">
            <option value="">— Odaberi kameru —</option>
            @foreach($cameras as $cam)
            <option value="{{ $cam->id }}"
                data-lat="{{ $cam->latitude }}"
                data-lon="{{ $cam->longitude }}"
                data-name="{{ $cam->name }}">
                {{ $cam->name }}
                @if($cam->location_name) – {{ $cam->location_name }}@endif
                @if($cam->station) ({{ $cam->station->name }})@endif
            </option>
            @endforeach
        </select>
        <small class="text-muted">Koordinate kamere bit će automatski upisane.</small>
    </div>
</div>

{{-- Manual section (no extra fields needed) --}}
<div class="source-section" id="sec-manual">
    <p class="text-muted" style="font-size:.85rem">
        <i class="fas fa-info-circle mr-1"></i>
        Ručna prijava — unesite koordinate mjesta detekcije u polja ispod
        ili kliknite na kartu desno.
    </p>
</div>

{{-- Coordinates --}}
<p class="form-section-label">Lokacija</p>
<div class="row">
    <div class="col-sm-6">
        <div class="form-group">
            <label>Geografska širina (lat) *</label>
            <input type="number" name="latitude" id="lat-input"
                   class="form-control @error('latitude') is-invalid @enderror"
                   step="0.0000001" placeholder="npr. 45.1234567"
                   value="{{ old('latitude') }}">
            @error('latitude')<div class="invalid-feedback">{{ $message }}</div>@enderror
        </div>
    </div>
    <div class="col-sm-6">
        <div class="form-group">
            <label>Geografska dužina (lon) *</label>
            <input type="number" name="longitude" id="lon-input"
                   class="form-control @error('longitude') is-invalid @enderror"
                   step="0.0000001" placeholder="npr. 18.6543210"
                   value="{{ old('longitude') }}">
            @error('longitude')<div class="invalid-feedback">{{ $message }}</div>@enderror
        </div>
    </div>
</div>

{{-- Detection details --}}
<p class="form-section-label">Detalji detekcije</p>
<div class="row">
    <div class="col-sm-4">
        <div class="form-group">
            <label>Vrsta *</label>
            <select name="type" class="form-control @error('type') is-invalid @enderror">
                @foreach(['person'=>'Osoba','group'=>'Skupina','vehicle'=>'Vozilo','smuggling'=>'Krijumčarenje','other'=>'Ostalo'] as $val=>$lbl)
                <option value="{{ $val }}" {{ old('type') === $val ? 'selected' : '' }}>{{ $lbl }}</option>
                @endforeach
            </select>
            @error('type')<div class="invalid-feedback">{{ $message }}</div>@enderror
        </div>
    </div>
    <div class="col-sm-3">
        <div class="form-group">
            <label>Broj entiteta *</label>
            <input type="number" name="count" class="form-control @error('count') is-invalid @enderror"
                   min="1" max="999" value="{{ old('count', 1) }}">
            @error('count')<div class="invalid-feedback">{{ $message }}</div>@enderror
        </div>
    </div>
    <div class="col-sm-5">
        <div class="form-group">
            <label>Datum i vrijeme *</label>
            <input type="datetime-local" name="detected_at"
                   class="form-control @error('detected_at') is-invalid @enderror"
                   value="{{ old('detected_at', now()->format('Y-m-d\TH:i')) }}">
            @error('detected_at')<div class="invalid-feedback">{{ $message }}</div>@enderror
        </div>
    </div>
</div>

<div class="form-group">
    <label>Napomena</label>
    <textarea name="notes" class="form-control" rows="3"
              placeholder="Opis situacije, ponašanje osoba, smjer kretanja...">{{ old('notes') }}</textarea>
</div>

</div>{{-- card-body --}}
</div>{{-- card --}}

</div>{{-- col-lg-7 --}}

{{-- ══ RIGHT COLUMN ═════════════════════════════════════════════════════════ --}}
<div class="col-lg-5">

{{-- Map card --}}
<div class="card mb-3">
    <div class="card-header py-2">
        <h6 class="mb-0"><i class="fas fa-map-marker-alt mr-2 text-warning"></i>Odabir lokacije</h6>
    </div>
    <div class="card-body p-2">
        <div id="det-map"></div>
        <p class="map-hint mt-2 mb-0">
            <i class="fas fa-hand-pointer mr-1"></i>
            Kliknite na kartu ili povucite marker za postavljanje koordinata
        </p>
    </div>
</div>

{{-- AI analysis card --}}
<div class="card">
    <div class="card-header py-2">
        <h6 class="mb-0">
            <i class="fas fa-brain mr-2 text-info"></i>
            Analitički podaci
            <small class="text-muted ml-1">({{ __('ui.optional') }})</small>
        </h6>
    </div>
    <div class="card-body">

        {{-- Confirmed + Escalation --}}
        <div class="row align-items-center mb-3">
            <div class="col-6">
                <div class="custom-control custom-switch confirm-toggle">
                    <input type="hidden" name="confirmed" value="0">
                    <input type="checkbox" class="custom-control-input" id="confirmed-chk"
                           name="confirmed" value="1" {{ old('confirmed') ? 'checked' : '' }}>
                    <label class="custom-control-label" for="confirmed-chk">
                        Potvrđeno
                    </label>
                </div>
            </div>
            <div class="col-6">
                <label class="mb-1" style="font-size:.8rem;color:rgba(255,255,255,.5)">Stupanj eskalacije</label>
                <div class="esc-group">
                    @foreach([0=>'Info',1=>'Upoz.',2=>'Interv.',3=>'Kritično'] as $lvl=>$lbl)
                    <div class="esc-btn esc-{{ $lvl }}">
                        <input type="radio" name="escalation_level" id="esc{{ $lvl }}"
                               value="{{ $lvl }}" {{ old('escalation_level', 0) == $lvl ? 'checked' : '' }}>
                        <label for="esc{{ $lvl }}">{{ $lbl }}</label>
                    </div>
                    @endforeach
                </div>
            </div>
        </div>

        {{-- Action taken --}}
        <div class="form-group">
            <label style="font-size:.85rem">Poduzeta mjera</label>
            <select name="action_taken" class="form-control form-control-sm">
                <option value="">— Nije odabrano —</option>
                @foreach([
                    'pending'      => 'U obradi',
                    'apprehended'  => 'Uhićen/a',
                    'turned_back'  => 'Vraćen/a',
                    'escaped'      => 'Pobjegao/la',
                    'false_alarm'  => 'Lažni alarm',
                    'investigating'=> 'U istrazi',
                ] as $val => $lbl)
                <option value="{{ $val }}" {{ old('action_taken') === $val ? 'selected' : '' }}>{{ $lbl }}</option>
                @endforeach
            </select>
        </div>

        {{-- Heading + Weather --}}
        <div class="row">
            <div class="col-6">
                <div class="form-group">
                    <label style="font-size:.85rem">Smjer kretanja (°)</label>
                    <div class="input-group input-group-sm">
                        <input type="number" name="heading_deg" class="form-control"
                               min="0" max="359" placeholder="0–359"
                               value="{{ old('heading_deg') }}">
                        <div class="input-group-append">
                            <span class="input-group-text">°</span>
                        </div>
                    </div>
                </div>
            </div>
            <div class="col-6">
                <div class="form-group">
                    <label style="font-size:.85rem">Vremenske prilike</label>
                    <select name="weather_condition" class="form-control form-control-sm">
                        <option value="">— —</option>
                        @foreach(['clear'=>'Vedro','cloudy'=>'Oblačno','rain'=>'Kiša','fog'=>'Magla','snow'=>'Snijeg','storm'=>'Oluja'] as $val=>$lbl)
                        <option value="{{ $val }}" {{ old('weather_condition') === $val ? 'selected' : '' }}>{{ $lbl }}</option>
                        @endforeach
                    </select>
                </div>
            </div>
        </div>

    </div>{{-- card-body --}}
</div>{{-- card --}}

{{-- Submit --}}
<button type="submit" class="btn btn-warning btn-block mt-2">
    <i class="fas fa-save mr-2"></i> Spremi detekciju
</button>

</div>{{-- col-lg-5 --}}

</div>{{-- row --}}
</form>

@endsection

@section('js')
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
@php
    $cameraJson = $cameras->keyBy('id')->map(fn($c) => [
        'lat'  => (float) $c->latitude,
        'lon'  => (float) $c->longitude,
        'name' => $c->name,
    ]);
@endphp
<script>
// ── Camera data for auto-fill ──────────────────────────────────────────────
const cameraData = {!! json_encode($cameraJson) !!};

// ── Leaflet map ────────────────────────────────────────────────────────────
const DEFAULT_CENTER = [45.15, 17.5]; // Slavonski Brod area
const DEFAULT_ZOOM   = 9;

const map = L.map('det-map', { zoomControl: true }).setView(DEFAULT_CENTER, DEFAULT_ZOOM);

L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
    attribution: '© OpenStreetMap',
    maxZoom: 19,
}).addTo(map);

const markerIcon = L.divIcon({
    className: '',
    html: `<div style="
        width:28px;height:28px;border-radius:50%;
        background:rgba(220,53,69,0.85);
        border:2px solid #fff;
        display:flex;align-items:center;justify-content:center;
        color:#fff;font-size:12px;
        box-shadow:0 0 8px rgba(220,53,69,0.6);
    "><i class="fas fa-crosshairs"></i></div>`,
    iconSize: [28, 28],
    iconAnchor: [14, 14],
});

let marker = null;

function setMarker(lat, lon, pan = true) {
    const latlng = L.latLng(lat, lon);
    if (marker) {
        marker.setLatLng(latlng);
    } else {
        marker = L.marker(latlng, { icon: markerIcon, draggable: true }).addTo(map);
        marker.on('dragend', function () {
            const p = marker.getLatLng();
            updateCoords(p.lat.toFixed(7), p.lng.toFixed(7), false);
        });
    }
    if (pan) map.setView(latlng, Math.max(map.getZoom(), 13));
}

function updateCoords(lat, lon, moveMarker = true) {
    document.getElementById('lat-input').value = lat;
    document.getElementById('lon-input').value = lon;
    if (moveMarker && lat && lon) setMarker(parseFloat(lat), parseFloat(lon));
}

// Click on map to place marker
map.on('click', function (e) {
    updateCoords(e.latlng.lat.toFixed(7), e.latlng.lng.toFixed(7), true);
});

// Sync lat/lon inputs → map
['lat-input', 'lon-input'].forEach(id => {
    document.getElementById(id).addEventListener('change', function () {
        const lat = parseFloat(document.getElementById('lat-input').value);
        const lon = parseFloat(document.getElementById('lon-input').value);
        if (!isNaN(lat) && !isNaN(lon)) setMarker(lat, lon);
    });
});

// Init marker if old values exist (validation failure)
const oldLat = parseFloat(document.getElementById('lat-input').value);
const oldLon = parseFloat(document.getElementById('lon-input').value);
if (!isNaN(oldLat) && !isNaN(oldLon)) setMarker(oldLat, oldLon);

// ── Source switcher ────────────────────────────────────────────────────────
const sourceCards    = document.querySelectorAll('.source-card');
const sourceInput    = document.getElementById('source-input');
const cameraSelect   = document.getElementById('camera-select');
const latInput       = document.getElementById('lat-input');
const lonInput       = document.getElementById('lon-input');

function activateSource(src) {
    sourceCards.forEach(c => c.classList.toggle('active', c.dataset.source === src));
    document.querySelectorAll('.source-section').forEach(s => s.classList.remove('visible'));
    document.getElementById('sec-' + src).classList.add('visible');
    sourceInput.value = src;

    // Re-enable coords for drone/manual, keep filled for camera
    if (src !== 'camera') {
        latInput.readOnly = false;
        lonInput.readOnly = false;
    }
}

sourceCards.forEach(card => {
    card.addEventListener('click', () => activateSource(card.dataset.source));
});

// Camera selected → auto-fill coordinates
cameraSelect.addEventListener('change', function () {
    const opt = this.options[this.selectedIndex];
    if (!opt.value) return;
    const lat = parseFloat(opt.dataset.lat);
    const lon = parseFloat(opt.dataset.lon);
    if (!isNaN(lat) && !isNaN(lon)) {
        updateCoords(lat.toFixed(7), lon.toFixed(7), true);
        latInput.readOnly = true;
        lonInput.readOnly = true;
    }
});

// Re-enable coords if camera is cleared
cameraSelect.addEventListener('change', function () {
    if (!this.value) {
        latInput.readOnly = false;
        lonInput.readOnly = false;
    }
});

// ── Form validation ────────────────────────────────────────────────────────
document.getElementById('det-form').addEventListener('submit', function (e) {
    const src = sourceInput.value;
    if (src === 'camera' && !cameraSelect.value) {
        e.preventDefault();
        alert('Odaberite lovačku kameru.');
        return;
    }
    const lat = parseFloat(latInput.value);
    const lon = parseFloat(lonInput.value);
    if (isNaN(lat) || isNaN(lon)) {
        e.preventDefault();
        alert('Unesite koordinate detekcije ili kliknite na kartu.');
        return;
    }
});
</script>
@endsection
