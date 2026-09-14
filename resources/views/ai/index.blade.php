@extends('adminlte::page')

@section('title', 'AI Analiza')

@section('content_header')
@endsection

@section('content')

@unless($mlOnline)
<div class="alert" style="background:rgba(220,53,69,0.15);border:1px solid rgba(220,53,69,0.4);color:#f5a0a0;border-radius:10px;padding:1rem 1.4rem">
    <i class="fas fa-exclamation-triangle mr-2"></i>
    <strong>ML servis nije dostupan.</strong>
    Pokrenite ga u terminalu: <code style="background:rgba(0,0,0,0.3);padding:2px 8px;border-radius:4px">cd ml-service && python -m uvicorn main:app --port 8001</code>
</div>
@endunless

@if($mlOnline)

{{-- ── Insight cards ─────────────────────────────────────────────────────── --}}
@php
    $total    = $insights['total'] ?? 0;
    $byType   = $insights['by_type'] ?? [];
    $trend    = $insights['trend_30d'] ?? '—';
    $highRisk = $insights['high_risk_count'] ?? 0;
    $confPct  = $insights['confirmed_pct'] ?? 0;
    $trendIcon = match($trend) { 'rast'=>'fa-arrow-trend-up', 'pad'=>'fa-arrow-trend-down', default=>'fa-minus' };
    $trendColor = match($trend) { 'rast'=>'#dc3545', 'pad'=>'#28a745', default=>'#f0c040' };
    $clusterCount = count($clusters['clusters'] ?? []);
    $topCluster = ($clusters['clusters'] ?? [])[0] ?? null;
    $peakHour = $riskMatrix['peak_hour'] ?? null;
    $peakDay  = $riskMatrix['peak_day'] ?? '—';
@endphp

<div class="an-kpi-grid mb-4">
    <div class="db-kpi-card db-kpi-red">
        <div class="db-kpi-icon"><i class="fas fa-map-pin"></i></div>
        <div class="db-kpi-body">
            <div class="db-kpi-value">{{ $clusterCount }}</div>
            <div class="db-kpi-label">Zona visokog rizika</div>
            <div class="db-kpi-sub">DBSCAN clustering</div>
        </div>
    </div>
    <div class="db-kpi-card" style="--accent:#fd7e14">
        <div class="db-kpi-icon" style="background:rgba(253,126,20,0.15);color:#fd7e14"><i class="fas fa-clock"></i></div>
        <div class="db-kpi-body">
            <div class="db-kpi-value">{{ $peakHour !== null ? str_pad($peakHour,2,'0',STR_PAD_LEFT).':00' : '—' }}</div>
            <div class="db-kpi-label">Najrizičniji sat</div>
            <div class="db-kpi-sub">{{ $peakDay }}</div>
        </div>
    </div>
    <div class="db-kpi-card db-kpi-gold">
        <div class="db-kpi-icon"><i class="fas {{ $trendIcon }}"></i></div>
        <div class="db-kpi-body">
            <div class="db-kpi-value" style="color:{{ $trendColor }}">{{ ucfirst($trend) }}</div>
            <div class="db-kpi-label">Trend (30 dana)</div>
            <div class="db-kpi-sub">{{ $total }} detekcija ukupno</div>
        </div>
    </div>
    <div class="db-kpi-card db-kpi-purple">
        <div class="db-kpi-icon"><i class="fas fa-exclamation-circle"></i></div>
        <div class="db-kpi-body">
            <div class="db-kpi-value">{{ $highRisk }}</div>
            <div class="db-kpi-label">Kritične detekcije</div>
            <div class="db-kpi-sub">eskalacija razina ≥ 2</div>
        </div>
    </div>
</div>

{{-- ── Cluster map ───────────────────────────────────────────────────────── --}}
<div class="db-card mb-4">
    <div class="db-card-header">
        <div>
            <div class="db-card-title">
                <i class="fas fa-map-marked-alt mr-2" style="color:#f46d43"></i>Zona rizika — DBSCAN clustering
            </div>
            <div class="db-card-subtitle">
                {{ $clusterCount }} zona · {{ $clusters['total_detections'] ?? 0 }} detekcija analizirano
            </div>
        </div>
        <div class="ml-auto d-flex align-items-center" style="gap:.5rem;font-size:.78rem;color:rgba(255,255,255,.5)">
            <span class="ai-legend-dot" style="background:#dc3545"></span>Visok
            <span class="ai-legend-dot" style="background:#fd7e14;margin-left:6px"></span>Srednji
            <span class="ai-legend-dot" style="background:#f0c040;margin-left:6px"></span>Nizak
        </div>
    </div>
    <div id="clusterMap" style="height:460px;border-radius:0 0 12px 12px"></div>
</div>

{{-- ── Risk matrix + cluster table ─────────────────────────────────────── --}}
<div class="row mb-4">
    <div class="col-lg-7">
        <div class="db-card h-100">
            <div class="db-card-header">
                <div class="db-card-title"><i class="fas fa-th mr-2" style="color:var(--gold)"></i>Matrica rizika — sat × dan</div>
            </div>
            <div class="db-card-body">
                <canvas id="riskHeatmap" height="200"></canvas>
                <div class="mt-2 text-center" style="font-size:.75rem;color:rgba(255,255,255,.35)">
                    Intenzitet boje = relativni rizik (0–100) · Tamnija = veći rizik
                </div>
            </div>
        </div>
    </div>
    <div class="col-lg-5">
        <div class="db-card h-100">
            <div class="db-card-header">
                <div class="db-card-title"><i class="fas fa-list-ol mr-2" style="color:var(--gold)"></i>Top zone rizika</div>
            </div>
            <div class="db-card-body p-0">
                @forelse(array_slice($clusters['clusters'] ?? [], 0, 6) as $i => $cl)
                <div class="db-stat-row" style="{{ $i === 5 ? 'border:none' : '' }}">
                    <div class="db-stat-icon" style="background:{{ $cl['risk']>=70 ? 'rgba(220,53,69,0.2)' : ($cl['risk']>=40 ? 'rgba(253,126,20,0.2)' : 'rgba(240,192,64,0.2)') }};color:{{ $cl['risk']>=70 ? '#f5a0a0' : ($cl['risk']>=40 ? '#fdba74' : '#f0c040') }}">
                        <i class="fas fa-map-pin"></i>
                    </div>
                    <div style="flex:1;min-width:0">
                        <div style="font-size:.82rem;color:#fff;font-weight:600">
                            Zona {{ $i+1 }}
                            <span class="ml-1" style="font-size:.7rem;color:rgba(255,255,255,.4)">{{ number_format($cl['lat'],4) }}, {{ number_format($cl['lon'],4) }}</span>
                        </div>
                        <div style="font-size:.72rem;color:rgba(255,255,255,.4)">
                            {{ $cl['count'] }} detekcija · {{ $cl['total_individuals'] }} osoba/vozila · {{ ucfirst($cl['dominant_type']) }}
                        </div>
                    </div>
                    <div class="ai-risk-pill" style="background:{{ $cl['risk']>=70 ? 'rgba(220,53,69,0.25)' : ($cl['risk']>=40 ? 'rgba(253,126,20,0.25)' : 'rgba(240,192,64,0.25)') }};color:{{ $cl['risk']>=70 ? '#f5a0a0' : ($cl['risk']>=40 ? '#fdba74' : '#f0c040') }}">
                        {{ $cl['risk'] }}
                    </div>
                </div>
                @empty
                <p class="text-muted text-center py-4 mb-0">Nema dovoljno podataka za clustering</p>
                @endforelse
            </div>
        </div>
    </div>
</div>

{{-- ── Predict tool ──────────────────────────────────────────────────────── --}}
<div class="db-card mb-4">
    <div class="db-card-header">
        <div class="db-card-title"><i class="fas fa-robot mr-2" style="color:var(--gold)"></i>Procjena rizika lokacije</div>
        <div class="db-card-subtitle ml-2">Kliknite na kartu ili unesite koordinate</div>
    </div>
    <div class="row" style="margin:0">
        <div class="col-md-5" style="padding:1.2rem 1.4rem;border-right:1px solid rgba(255,255,255,.06)">
            <div class="mb-3">
                <label class="ai-label">Koordinate</label>
                <div class="d-flex" style="gap:.5rem">
                    <input id="pLat" type="number" step="0.0001" placeholder="Lat (45.15)" class="form-control form-control-sm">
                    <input id="pLon" type="number" step="0.0001" placeholder="Lon (18.09)" class="form-control form-control-sm">
                </div>
            </div>
            <div class="mb-3">
                <label class="ai-label">Sat (0–23)</label>
                <input id="pHour" type="number" min="0" max="23" value="{{ now()->hour }}" class="form-control form-control-sm">
            </div>
            <div class="mb-3">
                <label class="ai-label">Dan u tjednu</label>
                <select id="pDow" class="form-control form-control-sm">
                    @foreach(['Ponedjeljak','Utorak','Srijeda','Četvrtak','Petak','Subota','Nedjelja'] as $i=>$d)
                        <option value="{{ $i }}" {{ $i === now()->dayOfWeekIso-1 ? 'selected' : '' }}>{{ $d }}</option>
                    @endforeach
                </select>
            </div>
            <button id="predictBtn" class="btn btn-block" style="background:rgba(240,192,64,0.15);border:1px solid rgba(240,192,64,0.4);color:var(--gold);border-radius:9px;font-weight:600">
                <i class="fas fa-brain mr-2"></i>Procijeni rizik
            </button>
        </div>
        <div class="col-md-4" style="padding:1.2rem 1.4rem;border-right:1px solid rgba(255,255,255,.06)">
            <div id="predictMap" style="height:220px;border-radius:10px"></div>
        </div>
        <div class="col-md-3" style="padding:1.2rem 1.4rem;display:flex;flex-direction:column;justify-content:center;align-items:center">
            <div id="riskResult" class="text-center" style="display:none">
                <div id="riskScore" style="font-size:4rem;font-weight:700;line-height:1"></div>
                <div id="riskLevel" style="font-size:1rem;font-weight:600;margin-top:.3rem;text-transform:uppercase;letter-spacing:1px"></div>
                <div style="margin-top:1.2rem;width:100%">
                    <div class="ai-factor-row"><span>Prostorni</span><span id="fSpatial">—</span></div>
                    <div class="ai-factor-row"><span>Vremenski</span><span id="fTemporal">—</span></div>
                    <div class="ai-factor-row"><span>Eskalacijski</span><span id="fEsc">—</span></div>
                    <div class="ai-factor-row" style="border:none"><span>Obližnjih</span><span id="fNearby">—</span></div>
                </div>
            </div>
            <div id="riskEmpty" style="color:rgba(255,255,255,.3);text-align:center">
                <i class="fas fa-crosshairs" style="font-size:2.5rem"></i>
                <div style="font-size:.85rem;margin-top:.75rem">Odaberite lokaciju i kliknite Procijeni</div>
            </div>
        </div>
    </div>
</div>

{{-- ── Predictive risk grid ──────────────────────────────────────────────── --}}
<div class="db-card mb-4">
    <div class="db-card-header">
        <div>
            <div class="db-card-title"><i class="fas fa-satellite-dish mr-2" style="color:#f46d43"></i>Prediktivna mapa nadzora</div>
            <div class="db-card-subtitle">RandomForest model treniran na povijesnim detekcijama, uspoređen s dosadašnjim letnim pokrivanjem</div>
        </div>
        <div class="ml-auto" id="riskGridStatus" style="font-size:.78rem;color:rgba(255,255,255,.5)">Učitavanje...</div>
    </div>
    <div id="riskGridMap" style="height:460px;border-radius:0 0 12px 12px"></div>
</div>

<div class="row mb-4">
    <div class="col-lg-6">
        <div class="db-card h-100">
            <div class="db-card-header">
                <div class="db-card-title"><i class="fas fa-arrow-up mr-2" style="color:#dc3545"></i>Preporuka: pojačati nadzor</div>
            </div>
            <div class="db-card-body p-0" id="increaseList">
                <p class="text-muted text-center py-4 mb-0">Učitavanje...</p>
            </div>
        </div>
    </div>
    <div class="col-lg-6">
        <div class="db-card h-100">
            <div class="db-card-header">
                <div class="db-card-title"><i class="fas fa-arrow-down mr-2" style="color:#28a745"></i>Preporuka: smanjiti nadzor</div>
            </div>
            <div class="db-card-body p-0" id="decreaseList">
                <p class="text-muted text-center py-4 mb-0">Učitavanje...</p>
            </div>
        </div>
    </div>
</div>

@endif

@if($isSuperAdmin)
{{-- ── Dev mode: model diagnostics (super admin only) ───────────────────── --}}
<div class="db-card mb-4" style="border:1px dashed rgba(240,192,64,0.35)">
    <div class="db-card-header">
        <div>
            <div class="db-card-title">
                <i class="fas fa-flask mr-2" style="color:var(--gold)"></i>Developer mode — točnost modela
                <span class="ai-dev-badge">DEV</span>
            </div>
            <div class="db-card-subtitle">Interna dijagnostika dok je aplikacija u izradi — brzi pokazatelj, ne zamjenjuje formalnu evaluaciju</div>
        </div>
        <div class="ml-auto" id="devMetricsStatus" style="font-size:.78rem;color:rgba(255,255,255,.5)">Učitavanje...</div>
    </div>
    <div class="db-card-body" id="devMetricsBody">
        <p class="text-muted text-center py-4 mb-0">Učitavanje metrika...</p>
    </div>
</div>
@endif

@include('partials.ai-chat-widget')
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

.ai-legend-dot {
    display: inline-block;
    width: 10px; height: 10px;
    border-radius: 50%;
}
.ai-risk-pill {
    padding: 3px 10px;
    border-radius: 20px;
    font-size: .78rem;
    font-weight: 700;
    white-space: nowrap;
}
.ai-label {
    font-size: .78rem;
    color: rgba(255,255,255,.45);
    text-transform: uppercase;
    letter-spacing: .5px;
    display: block;
    margin-bottom: .35rem;
}
.ai-factor-row {
    display: flex;
    justify-content: space-between;
    padding: .3rem 0;
    border-bottom: 1px solid rgba(255,255,255,.05);
    font-size: .8rem;
    color: rgba(255,255,255,.6);
}
.leaflet-popup-content-wrapper { background: #0d2460 !important; color: #fff !important; border-radius: 10px !important; }
.leaflet-popup-tip { background: #0d2460 !important; }

.ai-dev-badge {
    font-size: .65rem;
    font-weight: 700;
    letter-spacing: .5px;
    color: #16305c;
    background: var(--gold);
    padding: 1px 7px;
    border-radius: 4px;
    margin-left: 8px;
    vertical-align: middle;
}
.dev-metric-grid {
    display: grid;
    grid-template-columns: repeat(5, 1fr);
    gap: .75rem;
    margin-bottom: 1.2rem;
}
@media(max-width:992px){ .dev-metric-grid { grid-template-columns: repeat(3,1fr); } }
@media(max-width:576px){ .dev-metric-grid { grid-template-columns: repeat(2,1fr); } }
.dev-metric-tile {
    background: rgba(255,255,255,.03);
    border: 1px solid rgba(255,255,255,.06);
    border-radius: 10px;
    padding: .85rem .5rem;
    text-align: center;
}
.dev-metric-value { font-size: 1.5rem; font-weight: 700; color: #fff; line-height: 1; }
.dev-metric-label { font-size: .68rem; color: rgba(255,255,255,.45); text-transform: uppercase; letter-spacing: .4px; margin-top: .35rem; }
.dev-importance-row { display:flex; align-items:center; gap:.6rem; margin-bottom:.45rem; }
.dev-importance-name { width: 78px; flex-shrink:0; font-size:.75rem; color:rgba(255,255,255,.6); font-family:monospace; }
.dev-importance-bar-bg { flex:1; height:10px; background:rgba(255,255,255,.06); border-radius:5px; overflow:hidden; }
.dev-importance-bar { height:100%; background:linear-gradient(90deg,#f0c040,#fd7e14); border-radius:5px; }
.dev-importance-val { width: 42px; text-align:right; font-size:.72rem; color:rgba(255,255,255,.5); }

.dev-cm-grid {
    display: grid;
    grid-template-columns: 110px 1fr 1fr;
    gap: 4px;
    align-items: stretch;
}
.dev-cm-axis {
    font-size: .65rem;
    color: rgba(255,255,255,.4);
    display: flex;
    align-items: center;
    justify-content: center;
    text-align: center;
    padding: .25rem;
}
.dev-cm-cell {
    border-radius: 8px;
    padding: .9rem .5rem;
    text-align: center;
    font-size: 1.6rem;
    font-weight: 700;
    color: #fff;
    position: relative;
}
.dev-cm-cell span {
    display: block;
    font-size: .62rem;
    font-weight: 600;
    letter-spacing: .5px;
    color: rgba(255,255,255,.55);
    margin-top: .2rem;
}
.dev-cm-good { background: rgba(40,167,69,0.18); border: 1px solid rgba(40,167,69,0.35); }
.dev-cm-bad  { background: rgba(220,53,69,0.18); border: 1px solid rgba(220,53,69,0.35); }

.dev-compare-table { width: 100%; border-collapse: collapse; font-size: .82rem; }
.dev-compare-table th {
    text-align: left;
    padding: .5rem .7rem;
    color: rgba(255,255,255,.45);
    font-size: .68rem;
    text-transform: uppercase;
    letter-spacing: .4px;
    border-bottom: 1px solid rgba(255,255,255,.1);
}
.dev-compare-table td {
    padding: .55rem .7rem;
    color: rgba(255,255,255,.75);
    border-bottom: 1px solid rgba(255,255,255,.05);
}
.dev-compare-table td.dev-best {
    color: var(--gold);
    font-weight: 700;
}
.dev-model-dot {
    display: inline-block;
    width: 9px; height: 9px;
    border-radius: 50%;
    margin-right: 8px;
}
</style>
@endsection

@section('js')
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.2/dist/chart.umd.min.js"></script>

@if($mlOnline)
<script>
// ── Cluster map ──────────────────────────────────────────────────────────────
const clusterMap = L.map('clusterMap').setView([45.15, 18.1], 9);
L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
    attribution: '© OpenStreetMap', maxZoom: 19
}).addTo(clusterMap);

const clusters = {!! json_encode($clusters['clusters'] ?? []) !!};
const noise    = {!! json_encode($clusters['noise'] ?? []) !!};

function riskColor(r) {
    if (r >= 70) return '#dc3545';
    if (r >= 40) return '#fd7e14';
    return '#f0c040';
}

clusters.forEach(cl => {
    const color = riskColor(cl.risk);
    const radiusM = Math.max(cl.radius_km * 1000, 500);
    L.circle([cl.lat, cl.lon], {
        radius: radiusM,
        color, fillColor: color,
        fillOpacity: 0.18, weight: 2, opacity: 0.7
    }).bindPopup(`
        <div style="font-family:sans-serif;min-width:160px">
            <b style="color:${color}">Rizik: ${cl.risk}/100</b><br>
            <small>${cl.count} detekcija · ${cl.total_individuals} osoba/vozila</small><br>
            <small>Dominant: ${cl.dominant_type}</small>
        </div>
    `).addTo(clusterMap);

    L.circleMarker([cl.lat, cl.lon], {
        radius: 5, color, fillColor: color, fillOpacity: 1, weight: 0
    }).addTo(clusterMap);
});

noise.forEach(n => {
    L.circleMarker([n[0], n[1]], {
        radius: 2, color:'rgba(73,80,87,0.5)', fillColor:'rgba(73,80,87,0.5)', fillOpacity:1, weight:0
    }).addTo(clusterMap);
});

if (clusters.length) {
    const bounds = L.latLngBounds(clusters.map(c => [c.lat, c.lon]));
    clusterMap.fitBounds(bounds.pad(0.2));
}

// ── Risk matrix heatmap (Chart.js matrix via custom rendering) ───────────────
const matrix   = {!! json_encode($riskMatrix['matrix'] ?? []) !!};
const days     = ['Pon','Uto','Sri','Čet','Pet','Sub','Ned'];
const hours    = Array.from({length:24}, (_,i) => String(i).padStart(2,'0')+':00');

// Build dataset as scatter with cell coloring
const matrixData = [];
for (let h = 0; h < 24; h++) {
    for (let d = 0; d < 7; d++) {
        matrixData.push({ x: d, y: h, v: matrix[h]?.[d] ?? 0 });
    }
}

new Chart(document.getElementById('riskHeatmap'), {
    type: 'scatter',
    data: {
        datasets: [{
            data: matrixData,
            pointRadius: 10,
            pointStyle: 'rect',
            backgroundColor: ctx => {
                const v = ctx.raw?.v ?? 0;
                const a = v / 100;
                return `rgba(240,192,64,${Math.max(0.05, a)})`;
            },
            borderWidth: 0,
        }]
    },
    options: {
        responsive: true,
        aspectRatio: 3.5,
        plugins: {
            legend: { display: false },
            tooltip: {
                backgroundColor: '#0d2460',
                borderColor: 'rgba(240,192,64,0.4)',
                borderWidth: 1,
                titleColor: '#f0c040',
                bodyColor: 'rgba(255,255,255,0.8)',
                callbacks: {
                    title: items => `${hours[items[0].raw.y]} · ${days[items[0].raw.x]}`,
                    label: item => `Rizik: ${item.raw.v.toFixed(0)}/100`,
                }
            }
        },
        scales: {
            x: {
                type: 'linear', min: -0.5, max: 6.5,
                ticks: { stepSize:1, color:'rgba(255,255,255,.4)', font:{size:10},
                         callback: v => days[v] ?? '' },
                grid: { color:'rgba(255,255,255,.04)' },
            },
            y: {
                type: 'linear', min: -0.5, max: 23.5,
                reverse: false,
                ticks: { stepSize:2, color:'rgba(255,255,255,.4)', font:{size:9},
                         callback: v => Number.isInteger(v) ? hours[v] : '' },
                grid: { color:'rgba(255,255,255,.04)' },
            }
        }
    }
});

// ── Predict tool ─────────────────────────────────────────────────────────────
const predictMap = L.map('predictMap').setView([45.15, 18.1], 9);
L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
    attribution: '© OpenStreetMap', maxZoom: 19
}).addTo(predictMap);

let predictMarker = null;

predictMap.on('click', e => {
    document.getElementById('pLat').value = e.latlng.lat.toFixed(5);
    document.getElementById('pLon').value = e.latlng.lng.toFixed(5);
    if (predictMarker) predictMarker.remove();
    predictMarker = L.circleMarker([e.latlng.lat, e.latlng.lng], {
        radius: 7, color:'#6f42c1', fillColor:'#6f42c1', fillOpacity:0.8, weight:2
    }).addTo(predictMap);
});

document.getElementById('predictBtn').addEventListener('click', async () => {
    const lat  = parseFloat(document.getElementById('pLat').value);
    const lon  = parseFloat(document.getElementById('pLon').value);
    const hour = parseInt(document.getElementById('pHour').value);
    const dow  = parseInt(document.getElementById('pDow').value);

    if (isNaN(lat) || isNaN(lon)) {
        alert('Unesite koordinate ili kliknite na kartu.');
        return;
    }

    const btn = document.getElementById('predictBtn');
    btn.disabled = true;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i>Računam...';

    try {
        const res = await fetch(`/ai/predict?lat=${lat}&lon=${lon}&hour=${hour}&dow=${dow}`);
        const d   = await res.json();

        const color = d.risk >= 70 ? '#dc3545' : (d.risk >= 40 ? '#fd7e14' : (d.risk >= 20 ? '#f0c040' : '#28a745'));
        document.getElementById('riskScore').style.color = color;
        document.getElementById('riskScore').textContent = d.risk;
        document.getElementById('riskLevel').style.color = color;
        document.getElementById('riskLevel').textContent = d.level;
        document.getElementById('fSpatial').textContent  = d.factors?.prostorni ?? 0;
        document.getElementById('fTemporal').textContent = d.factors?.vremenski ?? 0;
        document.getElementById('fEsc').textContent      = d.factors?.eskalacijski ?? 0;
        document.getElementById('fNearby').textContent   = (d.nearby_count ?? 0) + ' det.';

        document.getElementById('riskResult').style.display = 'block';
        document.getElementById('riskEmpty').style.display  = 'none';

        if (predictMarker) predictMarker.remove();
        predictMarker = L.circle([lat, lon], {
            radius: 3000, color, fillColor: color, fillOpacity: 0.2, weight: 2
        }).addTo(predictMap);
        predictMap.setView([lat, lon], 11);
    } catch(e) {
        alert('Greška pri spajanju na ML servis.');
    } finally {
        btn.disabled = false;
        btn.innerHTML = '<i class="fas fa-brain mr-2"></i>Procijeni rizik';
    }
});

// ── Predictive risk grid ─────────────────────────────────────────────────────
const riskGridMap = L.map('riskGridMap').setView([45.15, 18.1], 9);
L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
    attribution: '© OpenStreetMap', maxZoom: 19
}).addTo(riskGridMap);

function riskFillColor(r) {
    if (r >= 70) return '#dc3545';
    if (r >= 40) return '#fd7e14';
    if (r >= 20) return '#f0c040';
    return '#28a745';
}

function renderRecList(el, items, kind) {
    if (!items.length) {
        el.innerHTML = '<p class="text-muted text-center py-4 mb-0">Nema preporuka</p>';
        return;
    }
    el.innerHTML = items.map((c, i) => `
        <div class="db-stat-row" style="${i === items.length - 1 ? 'border:none' : ''}">
            <div class="db-stat-icon" style="background:${kind === 'up' ? 'rgba(220,53,69,0.2)' : 'rgba(40,167,69,0.2)'};color:${kind === 'up' ? '#f5a0a0' : '#7ddc9f'}">
                <i class="fas ${kind === 'up' ? 'fa-arrow-up' : 'fa-arrow-down'}"></i>
            </div>
            <div style="flex:1;min-width:0">
                <div style="font-size:.82rem;color:#fff;font-weight:600">${c.lat.toFixed(4)}, ${c.lon.toFixed(4)}</div>
                <div style="font-size:.72rem;color:rgba(255,255,255,.4)">Rizik ${c.risk} · Pokrivenost ${c.coverage}</div>
            </div>
        </div>
    `).join('');
}

fetch('{{ route('ai.riskGrid') }}')
    .then(r => r.json())
    .then(d => {
        const status = document.getElementById('riskGridStatus');
        if (d.message) {
            status.textContent = d.message;
            document.getElementById('increaseList').innerHTML = `<p class="text-muted text-center py-4 mb-0">${d.message}</p>`;
            document.getElementById('decreaseList').innerHTML = `<p class="text-muted text-center py-4 mb-0">${d.message}</p>`;
            return;
        }
        status.textContent = `Trenirano na ${d.trained_on} detekcija · ćelija ${d.cell_km} km`;

        if (d.border) {
            ['hr_bih', 'hr_srb'].forEach(key => {
                L.polyline(d.border[key], { color: '#0d2460', weight: 2.5, opacity: 0.8, dashArray: '4,5' }).addTo(riskGridMap);
            });
        }

        const CELL_SCALE = 0.7; // shrink rendered cells a bit so they read as distinct squares, not a solid blob
        const halfLat = (d.cell_km / 111) / 2 * CELL_SCALE;
        (d.cells || []).forEach(c => {
            if (c.risk < 8) return;
            const halfLon = halfLat / Math.max(Math.cos(c.lat * Math.PI / 180), 0.2);
            L.rectangle([[c.lat - halfLat, c.lon - halfLon], [c.lat + halfLat, c.lon + halfLon]], {
                color: 'transparent', fillColor: riskFillColor(c.risk), fillOpacity: Math.min(0.6, c.risk / 140),
                weight: 0
            }).bindPopup(`<b>Rizik: ${c.risk}/100</b><br><small>Pokrivenost: ${c.coverage}</small>`).addTo(riskGridMap);
        });

        (d.recommend_increase || []).forEach(c => {
            const bearing = c.bearing ?? 0;
            L.marker([c.lat, c.lon], {
                icon: L.divIcon({
                    className: '',
                    html: `<i class="fas fa-arrow-up" style="color:#dc3545;font-size:16px;text-shadow:0 0 4px #000;display:inline-block;transform:rotate(${bearing}deg)"></i>`
                })
            }).bindPopup(`<b>Pojačati nadzor</b><br><small>Rizik: ${c.risk}/100</small>`).addTo(riskGridMap);
        });

        if (d.cells && d.cells.length) {
            const bounds = L.latLngBounds(d.cells.map(c => [c.lat, c.lon]));
            riskGridMap.fitBounds(bounds.pad(0.05));
        }

        renderRecList(document.getElementById('increaseList'), d.recommend_increase || [], 'up');
        renderRecList(document.getElementById('decreaseList'), d.recommend_decrease || [], 'down');
    })
    .catch(() => {
        document.getElementById('riskGridStatus').textContent = 'Greška pri učitavanju';
    });

@if($isSuperAdmin)
// ── Dev mode: model comparison diagnostics ───────────────────────────────────
const featureLabels = { lat: 'lat', lon: 'lon', hour_sin: 'sat (sin)', hour_cos: 'sat (cos)', dow_sin: 'dan (sin)', dow_cos: 'dan (cos)' };
const modelColors = { random_forest: '#f0c040', gradient_boosting: '#fd7e14', logistic_regression: '#6f42c1', knn: '#0dcaf0' };

fetch('{{ route('ai.devMetrics') }}')
    .then(r => r.json())
    .then(d => {
        const status = document.getElementById('devMetricsStatus');
        const body   = document.getElementById('devMetricsBody');

        if (d.message) {
            status.textContent = '';
            body.innerHTML = `<p class="text-muted text-center py-4 mb-0">${d.message}</p>`;
            return;
        }

        status.textContent = `Trenirano na ${d.trained_on} detekcija`;

        const rm = d.risk_model || {};
        const cl = d.clustering || {};

        let html = '';

        if (!rm.trained) {
            html += `<p class="text-muted text-center py-2">${rm.message || 'Model nije treniran'}</p>`;
        } else {
            const models = rm.models || {};
            const entries = Object.entries(models);

            // Best value per metric → bolded/highlighted in the table.
            const bestOf = (metric) => Math.max(...entries.map(([,m]) => m[metric] ?? -1));

            html += `<div style="font-size:.72rem;color:rgba(255,255,255,.4);margin-bottom:.9rem">
                Usporedba 4 algoritma na istom izdvojenom test skupu · ${rm.n_positive} stvarnih detekcija + ${rm.n_background} nasumičnih (background) uzoraka · test skup: ${rm.test_size}
            </div>`;

            html += `<div class="table-responsive mb-4"><table class="dev-compare-table">
                <thead><tr>
                    <th>Model</th><th>Accuracy</th><th>Precision</th><th>Recall</th><th>F1</th><th>ROC-AUC</th>
                </tr></thead><tbody>`;
            entries.forEach(([key, m]) => {
                const fmt = (metric, isAuc=false) => {
                    const v = m[metric];
                    const txt = isAuc ? (v !== null ? v.toFixed(3) : '—') : (v*100).toFixed(1) + '%';
                    const isBest = v === bestOf(metric);
                    return `<td${isBest ? ' class="dev-best"' : ''}>${txt}</td>`;
                };
                html += `<tr>
                    <td><span class="dev-model-dot" style="background:${modelColors[key] || '#999'}"></span>${m.label}</td>
                    ${fmt('accuracy')}${fmt('precision')}${fmt('recall')}${fmt('f1')}${fmt('roc_auc', true)}
                </tr>`;
            });
            html += `</tbody></table></div>`;

            html += `<div class="ai-label" style="margin-bottom:.5rem">ROC krivulje — usporedba modela</div>
                <canvas id="rocChart" height="110" class="mb-4"></canvas>`;

            // Feature importance — only tree-based models expose this.
            const withFi = entries.filter(([,m]) => m.feature_importances);
            if (withFi.length) {
                html += `<div class="row mb-2">`;
                withFi.forEach(([key, m]) => {
                    const fi = Object.entries(m.feature_importances).sort((a,b) => b[1]-a[1]);
                    const max = Math.max(...fi.map(e => e[1]), 0.001);
                    html += `<div class="col-md-6 mb-3">
                        <div class="ai-label" style="margin-bottom:.5rem">${m.label} — važnost značajki</div>`;
                    fi.forEach(([k,v]) => {
                        html += `<div class="dev-importance-row">
                            <div class="dev-importance-name">${featureLabels[k] || k}</div>
                            <div class="dev-importance-bar-bg"><div class="dev-importance-bar" style="width:${(v/max*100).toFixed(0)}%;background:linear-gradient(90deg,${modelColors[key]},#fd7e14)"></div></div>
                            <div class="dev-importance-val">${(v*100).toFixed(0)}%</div>
                        </div>`;
                    });
                    html += `</div>`;
                });
                html += `</div>`;
            }

            // Confusion matrix — best model by ROC-AUC, to keep the panel readable.
            const bestKey = entries.reduce((a,b) => (b[1].roc_auc ?? -1) > (a[1].roc_auc ?? -1) ? b : a)[0];
            const bestModel = models[bestKey];
            if (bestModel?.confusion_matrix) {
                const cm = bestModel.confusion_matrix;
                html += `<div class="ai-label" style="margin:.5rem 0">Matrica konfuzije — najbolji model (${bestModel.label})</div>
                    <div class="dev-cm-grid" style="max-width:420px">
                        <div></div>
                        <div class="dev-cm-axis">Predviđeno: pozadina</div>
                        <div class="dev-cm-axis">Predviđeno: detekcija</div>
                        <div class="dev-cm-axis" style="text-align:right;padding-right:.5rem">Stvarno: pozadina</div>
                        <div class="dev-cm-cell dev-cm-good">${cm.tn}<span>TN</span></div>
                        <div class="dev-cm-cell dev-cm-bad">${cm.fp}<span>FP</span></div>
                        <div class="dev-cm-axis" style="text-align:right;padding-right:.5rem">Stvarno: detekcija</div>
                        <div class="dev-cm-cell dev-cm-bad">${cm.fn}<span>FN</span></div>
                        <div class="dev-cm-cell dev-cm-good">${cm.tp}<span>TP</span></div>
                    </div>`;
            }
        }

        html += `<hr style="border-color:rgba(255,255,255,.08);margin:1.2rem 0">
            <div class="ai-label" style="margin-bottom:.5rem">DBSCAN kvaliteta klastera</div>
            <div class="dev-metric-grid" style="grid-template-columns:repeat(3,1fr)">
                <div class="dev-metric-tile"><div class="dev-metric-value">${cl.n_clusters ?? '—'}</div><div class="dev-metric-label">Klastera</div></div>
                <div class="dev-metric-tile"><div class="dev-metric-value">${cl.noise_ratio !== null ? (cl.noise_ratio*100).toFixed(0)+'%' : '—'}</div><div class="dev-metric-label">Šum</div></div>
                <div class="dev-metric-tile"><div class="dev-metric-value">${cl.silhouette !== null ? cl.silhouette.toFixed(2) : '—'}</div><div class="dev-metric-label">Silhouette</div></div>
            </div>`;

        body.innerHTML = html;

        if (rm.trained && rm.models) {
            const datasets = Object.entries(rm.models)
                .filter(([,m]) => m.roc_curve)
                .map(([key, m]) => ({
                    label: `${m.label} (AUC ${m.roc_auc?.toFixed(2) ?? '—'})`,
                    data: m.roc_curve.fpr.map((f, i) => ({ x: f, y: m.roc_curve.tpr[i] })),
                    borderColor: modelColors[key] || '#999',
                    backgroundColor: 'transparent',
                    tension: 0.15,
                    pointRadius: 0,
                    borderWidth: 2,
                }));
            datasets.push({
                label: 'Slučajno pogađanje',
                data: [{x:0,y:0},{x:1,y:1}],
                borderColor: 'rgba(255,255,255,0.25)',
                borderDash: [4,4],
                pointRadius: 0,
                borderWidth: 1,
                fill: false,
            });

            new Chart(document.getElementById('rocChart'), {
                type: 'line',
                data: { datasets },
                options: {
                    responsive: true,
                    plugins: {
                        legend: { labels: { color: 'rgba(255,255,255,.6)', font:{size:10}, boxWidth: 14 } },
                        tooltip: {
                            backgroundColor: '#0d2460', borderColor: 'rgba(240,192,64,0.4)', borderWidth: 1,
                            titleColor: '#f0c040', bodyColor: 'rgba(255,255,255,0.8)',
                            callbacks: { label: item => `${item.dataset.label}: FPR ${item.raw.x.toFixed(2)} · TPR ${item.raw.y.toFixed(2)}` }
                        },
                    },
                    scales: {
                        x: { type:'linear', min:0, max:1, title:{display:true,text:'False Positive Rate',color:'rgba(255,255,255,.4)',font:{size:10}}, ticks:{color:'rgba(255,255,255,.4)',font:{size:9}}, grid:{color:'rgba(255,255,255,.04)'} },
                        y: { type:'linear', min:0, max:1, title:{display:true,text:'True Positive Rate',color:'rgba(255,255,255,.4)',font:{size:10}}, ticks:{color:'rgba(255,255,255,.4)',font:{size:9}}, grid:{color:'rgba(255,255,255,.04)'} },
                    },
                }
            });
        }
    })
    .catch(() => {
        document.getElementById('devMetricsStatus').textContent = '';
        document.getElementById('devMetricsBody').innerHTML = '<p class="text-muted text-center py-4 mb-0">Greška pri učitavanju metrika</p>';
    });
@endif
</script>
@endif
@endsection
