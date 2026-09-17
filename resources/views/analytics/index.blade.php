@extends('adminlte::page')

@section('title', 'Analitika')

@section('content_header')
@endsection

@section('content')

{{-- ── KPI cards ─────────────────────────────────────────────────────────── --}}
<div class="an-kpi-grid mb-4">

    <div class="db-kpi-card db-kpi-gold">
        <div class="db-kpi-icon"><i class="fas fa-crosshairs"></i></div>
        <div class="db-kpi-body">
            <div class="db-kpi-value">{{ $total }}</div>
            <div class="db-kpi-label">Ukupno detekcija</div>
            <div class="db-kpi-sub">sve zabilježene</div>
        </div>
    </div>

    <div class="db-kpi-card db-kpi-purple">
        <div class="db-kpi-icon"><i class="fas fa-users"></i></div>
        <div class="db-kpi-body">
            <div class="db-kpi-value">{{ $totalEntities }}</div>
            <div class="db-kpi-label">Ukupno entiteta</div>
            <div class="db-kpi-sub">osoba, vozila, skupina</div>
        </div>
    </div>

    <div class="db-kpi-card db-kpi-green">
        <div class="db-kpi-icon"><i class="fas fa-helicopter"></i></div>
        <div class="db-kpi-body">
            <div class="db-kpi-value">{{ $totalFlights }}</div>
            <div class="db-kpi-label">Ukupno letova</div>
            <div class="db-kpi-sub">
                {{ $flightsWithDetections }} s detekcijom · {{ $flightsWithoutDetections }} bez
            </div>
        </div>
    </div>

    <div class="db-kpi-card db-kpi-red">
        <div class="db-kpi-icon"><i class="fas fa-clock"></i></div>
        <div class="db-kpi-body">
            <div class="db-kpi-value">{{ $totalHours }}</div>
            <div class="db-kpi-label">Sati nadzora</div>
            <div class="db-kpi-sub">{{ $avgDetectionsPerFlightHour }} detekcija/h leta</div>
        </div>
    </div>

</div>

{{-- ── Spatial density (heatmap + individual detections + routes + zones) ── --}}
<div class="db-card mb-4">
    <div class="db-card-header">
        <div>
            <div class="db-card-title">
                <i class="fas fa-fire mr-2" style="color:#f46d43"></i>Prostorna gustoća detekcija
            </div>
            <div class="db-card-subtitle" id="spFilteredCount">Učitavanje...</div>
        </div>
    </div>

    {{-- Filters ------------------------------------------------------------ --}}
    <div class="sp-filters">
        @if($administrations)
        <select id="spAdministration" class="form-control form-control-sm">
            <option value="">— Sva uprava —</option>
            @foreach($administrations as $adm)
                <option value="{{ $adm->id }}"
                    data-stations="{{ $adm->stations->map(fn($s)=>['id'=>$s->id,'name'=>$s->name])->toJson() }}">
                    {{ $adm->name }}
                </option>
            @endforeach
        </select>
        <select id="spStation" class="form-control form-control-sm">
            <option value="">— Sve postaje —</option>
            @foreach($administrations->flatMap->stations as $st)
                <option value="{{ $st->id }}" data-admin="{{ $st->police_administration_id }}">{{ $st->name }}</option>
            @endforeach
        </select>
        @elseif($stations)
        <select id="spStation" class="form-control form-control-sm">
            <option value="">— Sve postaje —</option>
            @foreach($stations as $st)
                <option value="{{ $st->id }}">{{ $st->name }}</option>
            @endforeach
        </select>
        @endif

        <input type="date" id="spDateFrom" class="form-control form-control-sm" title="Datum od">
        <input type="date" id="spDateTo" class="form-control form-control-sm" title="Datum do">

        <select id="spType" class="form-control form-control-sm">
            <option value="">— Sve vrste —</option>
            <option value="person">Osoba</option>
            <option value="group">Grupa</option>
            <option value="vehicle">Vozilo</option>
            <option value="other">Ostalo</option>
        </select>

        <select id="spSource" class="form-control form-control-sm">
            <option value="">— Svi izvori —</option>
            <option value="drone">Dron</option>
            <option value="trail_camera">Kamera</option>
            <option value="ground_observation">Ručno (teren)</option>
            <option value="other">Ostalo</option>
        </select>

        <select id="spTimeBlock" class="form-control form-control-sm">
            <option value="">— Cijeli dan —</option>
            @foreach($timeBlocks as $i => $block)
                <option value="{{ $i }}">{{ $block['label'] }}</option>
            @endforeach
        </select>

        <button id="spApply" class="btn btn-sm btn-primary"><i class="fas fa-filter mr-1"></i>Primijeni</button>
        <button id="spReset" class="btn btn-sm btn-secondary"><i class="fas fa-times"></i></button>
    </div>

    {{-- Layer toggles + heat params ----------------------------------------- --}}
    <div class="sp-controls">
        <div class="sp-layers">
            <label><input type="checkbox" id="layerHeat" checked> Heatmapa</label>
            <label><input type="checkbox" id="layerPoints"> Pojedinačne detekcije</label>
            <label><input type="checkbox" id="layerRoutes"> GPX rute</label>
            <label><input type="checkbox" id="layerZones"> Granice postaja</label>
        </div>
        <div class="sp-params">
            <label>Radius <input type="number" id="paramRadius" value="35" min="5" max="100" class="form-control form-control-sm"></label>
            <label>Blur <input type="number" id="paramBlur" value="25" min="0" max="100" class="form-control form-control-sm"></label>
            <label>Maks. intenzitet <input type="number" id="paramMax" value="1" min="1" step="0.5" class="form-control form-control-sm"></label>
            <label class="sp-check"><input type="checkbox" id="paramWeightByEntities"> Ponderiraj prema broju entiteta</label>
        </div>
    </div>

    <div style="position:relative">
        <div id="spatialMap" style="height:480px; border-radius:0 0 12px 12px;"></div>
    </div>

    {{-- Legend ---------------------------------------------------------------- --}}
    <div class="sp-legend">
        <span>Niska gustoća</span>
        <div class="sp-legend-bar"></div>
        <span>Visoka gustoća</span>
    </div>
</div>

{{-- ── Charts row 1: type + hour ────────────────────────────────────────── --}}
<div class="row mb-4">
    <div class="col-md-5">
        <div class="db-card h-100">
            <div class="db-card-header">
                <div class="db-card-title"><i class="fas fa-tag mr-2" style="color:var(--gold)"></i>Po vrsti</div>
            </div>
            <div class="db-card-body">
                <canvas id="chartType" height="240"></canvas>
            </div>
        </div>
    </div>
    <div class="col-md-7">
        <div class="db-card h-100">
            <div class="db-card-header">
                <div class="db-card-title"><i class="fas fa-clock mr-2" style="color:var(--gold)"></i>Detekcije po satu u danu</div>
            </div>
            <div class="db-card-body">
                <canvas id="chartHour" height="120"></canvas>
            </div>
        </div>
    </div>
</div>

{{-- ── Charts row 2: weekday + monthly trend ───────────────────────────── --}}
<div class="row mb-4">
    <div class="col-md-5">
        <div class="db-card h-100">
            <div class="db-card-header">
                <div class="db-card-title"><i class="fas fa-calendar-week mr-2" style="color:var(--gold)"></i>Po danu u tjednu</div>
            </div>
            <div class="db-card-body">
                <canvas id="chartDow" height="200"></canvas>
            </div>
        </div>
    </div>
    <div class="col-md-7">
        <div class="db-card h-100">
            <div class="db-card-header">
                <div class="db-card-title"><i class="fas fa-calendar-alt mr-2" style="color:var(--gold)"></i>Trend — zadnjih 12 mjeseci</div>
            </div>
            <div class="db-card-body">
                <canvas id="chartMonth" height="120"></canvas>
            </div>
        </div>
    </div>
</div>

{{-- ── Charts row 3: source + station ──────────────────────────────────── --}}
<div class="row mb-4">
    <div class="col-md-5">
        <div class="db-card">
            <div class="db-card-header">
                <div class="db-card-title"><i class="fas fa-satellite-dish mr-2" style="color:var(--gold)"></i>Po izvoru</div>
            </div>
            <div class="db-card-body">
                <canvas id="chartSource" height="240"></canvas>
            </div>
        </div>
    </div>
    <div class="col-md-7">
        <div class="db-card">
            <div class="db-card-header">
                <div class="db-card-title"><i class="fas fa-building-shield mr-2" style="color:var(--gold)"></i>Po policijskoj postaji</div>
            </div>
            <div class="db-card-body">
                <canvas id="chartStation" height="200"></canvas>
            </div>
        </div>
    </div>
</div>

@endsection

@section('css')
<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css"/>
<style>
.content-header { display: none; }
.content-wrapper > .content { padding-top: 1.5rem; }

.an-kpi-grid {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    gap: 1rem;
}
@media(max-width:992px){ .an-kpi-grid { grid-template-columns: repeat(2,1fr); } }
@media(max-width:480px){ .an-kpi-grid { grid-template-columns: 1fr; } }

.an-empty-map {
    height: 200px;
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    color: rgba(255,255,255,0.25);
    font-size: 2.5rem;
    gap: 0.75rem;
}
.an-empty-map div { font-size: 0.9rem; }

.sp-filters, .sp-controls {
    display: flex;
    flex-wrap: wrap;
    align-items: center;
    gap: .5rem;
    padding: .6rem 1.2rem;
    border-top: 1px solid rgba(255,255,255,0.06);
}
.sp-filters select, .sp-filters input { width: auto; }
.sp-controls { justify-content: space-between; background: rgba(255,255,255,0.02); }
.sp-layers, .sp-params { display: flex; flex-wrap: wrap; align-items: center; gap: 1rem; }
.sp-layers label, .sp-params label {
    display: flex; align-items: center; gap: .4rem;
    font-size: .8rem; color: rgba(255,255,255,0.6); margin: 0;
}
.sp-params input[type="number"] { width: 70px; display: inline-block; }
.sp-check { white-space: nowrap; }

.sp-legend {
    display: flex; align-items: center; gap: .6rem;
    padding: .6rem 1.2rem;
    font-size: .75rem; color: rgba(255,255,255,0.5);
    border-top: 1px solid rgba(255,255,255,0.06);
}
.sp-legend-bar {
    flex: 1; max-width: 260px; height: 10px; border-radius: 5px;
    background: linear-gradient(to right, #4575b4, #74add1, #fdae61, #f46d43, #d73027);
}
</style>
@endsection

@section('js')
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
<script src="https://unpkg.com/leaflet.heat@0.2.0/dist/leaflet-heat.js"></script>
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.2/dist/chart.umd.min.js"></script>
<script>
// ── Spatial density map ────────────────────────────────────────────────────
const spatialMap = L.map('spatialMap').setView([45.1, 18.0], 8);
L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
    attribution: '© OpenStreetMap', maxZoom: 19
}).addTo(spatialMap);

let spData = { points: [], routes: [], zones: [] };
let heatLayer = null;
const layerPointsGroup = L.layerGroup();
const layerRoutesGroup = L.layerGroup();
const layerZonesGroup  = L.layerGroup();

const spTypeColors = { person: '#fd7e14', group: '#dc3545', vehicle: '#0d6efd', other: '#6c757d' };

function spWeight(p) {
    return document.getElementById('paramWeightByEntities').checked ? Math.max(1, p.entity_count) : 1;
}

// Estimate the peak local density in the *currently loaded* set by binning
// points into a pixel-space grid (sized to the heat radius, at the map's
// current projection/zoom) and taking the busiest cell's summed weight.
// This is what "isolated = low, overlap = red, normalised to the active
// set's own peak" (points 1-3 of the spec) actually means numerically —
// without it, `max` is just a guess and single points can hit full red.
function computeAutoMax(points) {
    const radius = parseFloat(document.getElementById('paramRadius').value) || 35;
    const cell = Math.max(radius, 10);
    const grid = new Map();
    points.forEach(p => {
        const pt = spatialMap.project([p.lat, p.lon], spatialMap.getZoom());
        const key = Math.floor(pt.x / cell) + '_' + Math.floor(pt.y / cell);
        grid.set(key, (grid.get(key) || 0) + spWeight(p));
    });
    let max = 0;
    grid.forEach(v => { if (v > max) max = v; });
    return Math.max(1, Math.round(max * 10) / 10);
}

function renderHeat() {
    if (heatLayer) { spatialMap.removeLayer(heatLayer); heatLayer = null; }
    if (!document.getElementById('layerHeat').checked) return;
    const radius = parseFloat(document.getElementById('paramRadius').value) || 35;
    const blur   = parseFloat(document.getElementById('paramBlur').value) || 25;
    const max    = parseFloat(document.getElementById('paramMax').value) || 1;
    const pts = spData.points.map(p => [p.lat, p.lon, spWeight(p)]);
    // maxZoom must track the map's actual zoom or points render almost
    // invisibly faint at a wide view (same fix as the KDE/Analytics bug).
    heatLayer = L.heatLayer(pts, {
        radius, blur, max, maxZoom: spatialMap.getZoom(),
        gradient: { 0.2: '#4575b4', 0.45: '#74add1', 0.65: '#fdae61', 0.82: '#f46d43', 1.0: '#d73027' }
    }).addTo(spatialMap);
}

function renderPoints() {
    layerPointsGroup.clearLayers();
    spData.points.forEach(p => {
        const color = spTypeColors[p.detection_type] || '#6c757d';
        L.circleMarker([p.lat, p.lon], { radius: 4, color, fillColor: color, fillOpacity: .8, weight: 1 })
            .bindPopup(`<b>${p.detection_type}</b><br><small>${p.source} · ${p.entity_count} ent. · ${p.detected_at}</small>`)
            .addTo(layerPointsGroup);
    });
}

function renderRoutes() {
    layerRoutesGroup.clearLayers();
    spData.routes.forEach(pts => {
        L.polyline(pts, { color: '#f0c040', weight: 1.5, opacity: .35 }).addTo(layerRoutesGroup);
    });
}

function renderZones() {
    layerZonesGroup.clearLayers();
    spData.zones.forEach(z => {
        const layer = (z.boundary && z.boundary.length >= 3)
            ? L.polygon(z.boundary, { color: '#9aa5b1', weight: 1.5, fillOpacity: .03 })
            : L.circle([z.lat, z.lon], { radius: z.radius_km * 1000, color: '#9aa5b1', weight: 1.5, fillOpacity: .03 });
        layer.bindPopup(z.name).addTo(layerZonesGroup);
    });
}

function syncLayerVisibility() {
    document.getElementById('layerPoints').checked ? layerPointsGroup.addTo(spatialMap) : spatialMap.removeLayer(layerPointsGroup);
    document.getElementById('layerRoutes').checked ? layerRoutesGroup.addTo(spatialMap) : spatialMap.removeLayer(layerRoutesGroup);
    document.getElementById('layerZones').checked  ? layerZonesGroup.addTo(spatialMap)  : spatialMap.removeLayer(layerZonesGroup);
    renderHeat();
}

function renderAllLayers() {
    renderPoints();
    renderRoutes();
    renderZones();
    syncLayerVisibility();
    document.getElementById('spFilteredCount').textContent =
        `${spData.points.length} detekcija · ${spData.routes.length} ruta leta`;
}

function spBuildQuery() {
    const params = new URLSearchParams();
    const station = document.getElementById('spStation')?.value;
    if (station) params.set('station_id', station);
    const from = document.getElementById('spDateFrom').value;
    if (from) params.set('date_from', from);
    const to = document.getElementById('spDateTo').value;
    if (to) params.set('date_to', to);
    const type = document.getElementById('spType').value;
    if (type) params.set('detection_type', type);
    const source = document.getElementById('spSource').value;
    if (source) params.set('source', source);
    const block = document.getElementById('spTimeBlock').value;
    if (block !== '') params.set('time_block', block);
    return params.toString();
}

function loadSpatialData() {
    document.getElementById('spFilteredCount').textContent = 'Učitavanje...';
    fetch('{{ route('analytics.spatialData') }}?' + spBuildQuery())
        .then(r => r.json())
        .then(d => {
            spData = d;
            if (spData.points.length) {
                spatialMap.fitBounds(L.latLngBounds(spData.points.map(p => [p.lat, p.lon])).pad(0.1));
            }
            document.getElementById('paramMax').value = computeAutoMax(spData.points);
            renderAllLayers();
        })
        .catch(() => { document.getElementById('spFilteredCount').textContent = 'Greška pri učitavanju'; });
}

document.getElementById('spApply').addEventListener('click', loadSpatialData);
document.getElementById('spReset').addEventListener('click', () => {
    ['spStation', 'spDateFrom', 'spDateTo', 'spType', 'spSource', 'spTimeBlock'].forEach(id => {
        const el = document.getElementById(id);
        if (el) el.value = '';
    });
    if (document.getElementById('spAdministration')) document.getElementById('spAdministration').value = '';
    loadSpatialData();
});
['layerHeat', 'layerPoints', 'layerRoutes', 'layerZones'].forEach(id => {
    document.getElementById(id).addEventListener('change', syncLayerVisibility);
});
document.getElementById('paramRadius').addEventListener('change', () => {
    document.getElementById('paramMax').value = computeAutoMax(spData.points);
    renderHeat();
});
['paramBlur', 'paramMax', 'paramWeightByEntities'].forEach(id => {
    document.getElementById(id).addEventListener('change', renderHeat);
});

// Administration → station cascading filter (super admin only)
const spAdminSelect = document.getElementById('spAdministration');
if (spAdminSelect) {
    const spStationSelect = document.getElementById('spStation');
    spAdminSelect.addEventListener('change', () => {
        const adminId = spAdminSelect.value;
        Array.from(spStationSelect.options).forEach(opt => {
            if (!opt.value) return;
            opt.hidden = adminId !== '' && opt.dataset.admin != adminId;
        });
        if (adminId !== '' && spStationSelect.value &&
            spStationSelect.options[spStationSelect.selectedIndex]?.dataset.admin != adminId) {
            spStationSelect.value = '';
        }
    });
}

loadSpatialData();

// ── Chart defaults ───────────────────────────────────────────────────────────
const tooltip = {
    backgroundColor: '#0d2460',
    borderColor: 'rgba(240,192,64,0.4)',
    borderWidth: 1,
    titleColor: '#f0c040',
    bodyColor: 'rgba(255,255,255,0.8)',
    padding: 10,
};
const gridColor = 'rgba(255,255,255,0.05)';
const tickColor = 'rgba(255,255,255,0.4)';

function barScales(yLabel) {
    return {
        x: { grid: { color: gridColor }, ticks: { color: tickColor, font: { size: 11 } } },
        y: { beginAtZero: true, grid: { color: gridColor },
             ticks: { color: tickColor, font: { size: 11 }, stepSize: 1 },
             title: { display: !!yLabel, text: yLabel, color: tickColor, font: { size: 11 } } }
    };
}

// ── By type (doughnut) ───────────────────────────────────────────────────────
new Chart(document.getElementById('chartType'), {
    type: 'doughnut',
    data: {
        labels: {!! json_encode($chartType['labels']) !!},
        datasets: [{
            data:            {!! json_encode($chartType['data']) !!},
            backgroundColor: {!! json_encode($chartType['colors']) !!},
            borderWidth: 2,
            borderColor: '#112247',
        }]
    },
    options: {
        responsive: true,
        plugins: {
            legend: { position: 'bottom', labels: { color: 'rgba(255,255,255,0.6)', padding: 14, font: { size: 12 } } },
            tooltip,
        }
    }
});

// ── By hour ──────────────────────────────────────────────────────────────────
new Chart(document.getElementById('chartHour'), {
    type: 'bar',
    data: {
        labels: {!! json_encode($chartHour['labels']) !!},
        datasets: [{
            data: {!! json_encode($chartHour['data']) !!},
            backgroundColor: 'rgba(240,192,64,0.55)',
            borderColor: '#f0c040',
            borderWidth: 1,
            borderRadius: 3,
        }]
    },
    options: {
        responsive: true,
        plugins: { legend: { display: false }, tooltip },
        scales: barScales('Detekcija'),
    }
});

// ── By weekday ───────────────────────────────────────────────────────────────
new Chart(document.getElementById('chartDow'), {
    type: 'bar',
    data: {
        labels: {!! json_encode($chartDow['labels']) !!},
        datasets: [{
            data: {!! json_encode($chartDow['data']) !!},
            backgroundColor: 'rgba(240,192,64,0.55)',
            borderColor: '#f0c040',
            borderWidth: 1,
            borderRadius: 4,
        }]
    },
    options: {
        responsive: true,
        plugins: { legend: { display: false }, tooltip },
        scales: barScales('Detekcija'),
    }
});

// ── Monthly trend (line) ─────────────────────────────────────────────────────
const monthCtx  = document.getElementById('chartMonth').getContext('2d');
const monthGrad = monthCtx.createLinearGradient(0, 0, 0, 260);
monthGrad.addColorStop(0,   'rgba(240,192,64,0.35)');
monthGrad.addColorStop(1,   'rgba(240,192,64,0.02)');

new Chart(monthCtx, {
    type: 'line',
    data: {
        labels: {!! json_encode($chartMonth['labels']) !!},
        datasets: [{
            data: {!! json_encode($chartMonth['data']) !!},
            borderColor: '#f0c040',
            borderWidth: 2.5,
            backgroundColor: monthGrad,
            fill: true,
            tension: 0.4,
            pointBackgroundColor: '#f0c040',
            pointRadius: 4,
            pointHoverRadius: 6,
        }]
    },
    options: {
        responsive: true,
        plugins: { legend: { display: false }, tooltip },
        scales: barScales('Detekcija'),
    }
});

// ── By source (doughnut) ─────────────────────────────────────────────────────
new Chart(document.getElementById('chartSource'), {
    type: 'doughnut',
    data: {
        labels: {!! json_encode($chartSource['labels']) !!},
        datasets: [{
            data:            {!! json_encode($chartSource['data']) !!},
            backgroundColor: {!! json_encode($chartSource['colors']) !!},
            borderWidth: 2,
            borderColor: '#112247',
        }]
    },
    options: {
        responsive: true,
        plugins: {
            legend: { position: 'bottom', labels: { color: 'rgba(255,255,255,0.6)', padding: 14, font: { size: 12 } } },
            tooltip,
        }
    }
});

// ── By station (bar) ─────────────────────────────────────────────────────────
new Chart(document.getElementById('chartStation'), {
    type: 'bar',
    data: {
        labels: {!! json_encode($chartStation['labels']) !!},
        datasets: [{
            data: {!! json_encode($chartStation['data']) !!},
            backgroundColor: 'rgba(13,110,253,0.55)',
            borderColor: '#0d6efd',
            borderWidth: 1,
            borderRadius: 3,
        }]
    },
    options: {
        indexAxis: 'y',
        responsive: true,
        plugins: { legend: { display: false }, tooltip },
        scales: barScales('Detekcija'),
    }
});
</script>
@endsection
