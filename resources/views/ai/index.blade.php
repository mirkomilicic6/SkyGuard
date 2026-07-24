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
</script>
@endif
@endsection
