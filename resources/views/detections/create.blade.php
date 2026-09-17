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
    <div class="source-card" data-source="trail_camera">
        <i class="fas fa-camera"></i>
        <span>Lovačka kamera</span>
    </div>
    <div class="source-card" data-source="ground_observation">
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
            <option value="{{ $fl->id }}" data-station="{{ $fl->station_id }}">
                {{ $fl->flight_date?->format('d.m.Y H:i') ?? '—' }}
                @if($fl->location) – {{ Str::limit($fl->location, 35) }}@endif
                @if($fl->drone) ({{ $fl->drone->name }})@endif
            </option>
            @endforeach
        </select>
        <small class="text-muted">Odaberite let ako je detekcija uočena za vrijeme patroliranja dronom — postaja se preuzima automatski.</small>
    </div>
</div>

{{-- Camera / manual sections (no flight linkage) --}}
<div class="source-section" id="sec-trail_camera">
    <p class="text-muted" style="font-size:.85rem">
        <i class="fas fa-info-circle mr-1"></i>
        Detekcija s lovačke kamere — unesite koordinate mjesta detekcije u polja ispod ili kliknite na kartu desno.
    </p>
</div>
<div class="source-section" id="sec-ground_observation">
    <p class="text-muted" style="font-size:.85rem">
        <i class="fas fa-info-circle mr-1"></i>
        Ručna prijava s terena — unesite koordinate mjesta detekcije u polja ispod ili kliknite na kartu desno.
    </p>
</div>

{{-- Station (required unless a flight was picked) --}}
<div class="form-group" id="station-group">
    <label>Policijska postaja *</label>
    <select name="station_id" id="station-select" class="form-control @error('station_id') is-invalid @enderror">
        <option value="">— Odaberi postaju —</option>
        @foreach($stations as $st)
        <option value="{{ $st->id }}" {{ old('station_id') == $st->id ? 'selected' : '' }}>{{ $st->name }}</option>
        @endforeach
    </select>
    @error('station_id')<div class="invalid-feedback">{{ $message }}</div>@enderror
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
            <select name="detection_type" class="form-control @error('detection_type') is-invalid @enderror">
                @foreach(['person'=>'Osoba','group'=>'Skupina','vehicle'=>'Vozilo','other'=>'Ostalo'] as $val=>$lbl)
                <option value="{{ $val }}" {{ old('detection_type') === $val ? 'selected' : '' }}>{{ $lbl }}</option>
                @endforeach
            </select>
            @error('detection_type')<div class="invalid-feedback">{{ $message }}</div>@enderror
        </div>
    </div>
    <div class="col-sm-3">
        <div class="form-group">
            <label>Broj entiteta *</label>
            <input type="number" name="entity_count" class="form-control @error('entity_count') is-invalid @enderror"
                   min="1" max="999" value="{{ old('entity_count', 1) }}">
            @error('entity_count')<div class="invalid-feedback">{{ $message }}</div>@enderror
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
    <textarea name="note" class="form-control" rows="3"
              placeholder="Opis situacije, ponašanje osoba, smjer kretanja...">{{ old('note') }}</textarea>
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
<script>
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
const sourceCards   = document.querySelectorAll('.source-card');
const sourceInput   = document.getElementById('source-input');
const flightSelect  = document.getElementById('flight-select');
const stationGroup  = document.getElementById('station-group');
const stationSelect = document.getElementById('station-select');

function activateSource(src) {
    sourceCards.forEach(c => c.classList.toggle('active', c.dataset.source === src));
    document.querySelectorAll('.source-section').forEach(s => s.classList.remove('visible'));
    document.getElementById('sec-' + src).classList.add('visible');
    sourceInput.value = src;
    syncStationRequirement();
}

sourceCards.forEach(card => {
    card.addEventListener('click', () => activateSource(card.dataset.source));
});

// A picked flight auto-supplies the station — hide the manual station picker.
function syncStationRequirement() {
    const flightChosen = sourceInput.value === 'drone' && flightSelect.value;
    stationGroup.style.display = flightChosen ? 'none' : '';
    stationSelect.required = !flightChosen;
}
flightSelect.addEventListener('change', syncStationRequirement);
syncStationRequirement();

// ── Form validation ────────────────────────────────────────────────────────
document.getElementById('det-form').addEventListener('submit', function (e) {
    const lat = parseFloat(document.getElementById('lat-input').value);
    const lon = parseFloat(document.getElementById('lon-input').value);
    if (isNaN(lat) || isNaN(lon)) {
        e.preventDefault();
        alert('Unesite koordinate detekcije ili kliknite na kartu.');
        return;
    }
    const flightChosen = sourceInput.value === 'drone' && flightSelect.value;
    if (!flightChosen && !stationSelect.value) {
        e.preventDefault();
        alert('Odaberite policijsku postaju.');
        return;
    }
});
</script>
@endsection
