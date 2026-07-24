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

    <div class="db-kpi-card db-kpi-green">
        <div class="db-kpi-icon"><i class="fas fa-check-circle"></i></div>
        <div class="db-kpi-body">
            <div class="db-kpi-value">{{ $confirmed }}</div>
            <div class="db-kpi-label">Potvrđeno</div>
            <div class="db-kpi-sub">
                {{ $total > 0 ? round($confirmed / $total * 100) : 0 }}% od ukupnih
            </div>
        </div>
    </div>

    <div class="db-kpi-card db-kpi-red">
        <div class="db-kpi-icon"><i class="fas fa-exclamation-triangle"></i></div>
        <div class="db-kpi-body">
            @php $highRisk = ($chartEscalation['data'][3] ?? 0) + ($chartEscalation['data'][2] ?? 0); @endphp
            <div class="db-kpi-value">{{ $highRisk }}</div>
            <div class="db-kpi-label">Visoki rizik</div>
            <div class="db-kpi-sub">kritično + intervencija</div>
        </div>
    </div>

    <div class="db-kpi-card db-kpi-purple">
        <div class="db-kpi-icon"><i class="fas fa-users"></i></div>
        <div class="db-kpi-body">
            @php $people = ($chartType['data'][0] ?? 0) + ($chartType['data'][1] ?? 0); @endphp
            <div class="db-kpi-value">{{ $people }}</div>
            <div class="db-kpi-label">Osobe / Grupe</div>
            <div class="db-kpi-sub">zabilježenih osoba</div>
        </div>
    </div>

</div>

{{-- ── Heatmap ───────────────────────────────────────────────────────────── --}}
<div class="db-card mb-4">
    <div class="db-card-header">
        <div>
            <div class="db-card-title">
                <i class="fas fa-fire mr-2" style="color:#f46d43"></i>Heatmapa detekcija
            </div>
            <div class="db-card-subtitle">{{ count($heatmap) }} GPS točaka</div>
        </div>
    </div>
    <div style="position:relative">
        @if(count($heatmap) > 0)
            <div id="heatmap" style="height:420px; border-radius:0 0 12px 12px;"></div>
        @else
            <div class="an-empty-map">
                <i class="fas fa-map-marked-alt"></i>
                <div>Nema GPS podataka za prikaz</div>
            </div>
        @endif
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

{{-- ── Escalation ────────────────────────────────────────────────────────── --}}
<div class="row mb-4">
    <div class="col-md-5">
        <div class="db-card">
            <div class="db-card-header">
                <div class="db-card-title"><i class="fas fa-exclamation-circle mr-2" style="color:#fd7e14"></i>Razine eskalacije</div>
            </div>
            <div class="db-card-body">
                <canvas id="chartEscalation" height="240"></canvas>
            </div>
        </div>
    </div>
    <div class="col-md-7">
        <div class="db-card">
            <div class="db-card-header">
                <div class="db-card-title"><i class="fas fa-list mr-2" style="color:var(--gold)"></i>Pregled po eskalaciji</div>
            </div>
            <div class="db-card-body p-0">
                @php
                    $escLabels = [0 => 'Informativno', 1 => 'Upozorenje', 2 => 'Intervencija', 3 => 'Kritično'];
                    $escColors = [0 => '#6c757d',      1 => '#f0c040',    2 => '#fd7e14',      3 => '#dc3545'];
                @endphp
                @foreach($escLabels as $level => $label)
                <div class="db-stat-row" style="{{ $level === 3 ? 'border:none' : '' }}">
                    <div class="db-stat-icon" style="background:{{ $escColors[$level] }}22;color:{{ $escColors[$level] }}">
                        <i class="fas fa-exclamation-circle"></i>
                    </div>
                    <span>{{ $label }}</span>
                    <strong class="ml-auto" style="color:{{ $escColors[$level] }}">
                        {{ $chartEscalation['data'][$level] ?? 0 }}
                    </strong>
                </div>
                @endforeach
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
</style>
@endsection

@section('js')
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
<script src="https://unpkg.com/leaflet.heat@0.2.0/dist/leaflet-heat.js"></script>
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.2/dist/chart.umd.min.js"></script>
<script>
// ── Heatmap ──────────────────────────────────────────────────────────────────
@if(count($heatmap) > 0)
const heatPoints = {!! json_encode($heatmap) !!};
const map = L.map('heatmap').setView([45.1, 18.5], 9);
L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
    attribution: '© OpenStreetMap', maxZoom: 19
}).addTo(map);
L.heatLayer(heatPoints, {
    radius: 22, blur: 18, maxZoom: 14, max: 1.0,
    gradient: { 0.2:'#4575b4', 0.45:'#74add1', 0.65:'#fdae61', 0.82:'#f46d43', 1.0:'#d73027' }
}).addTo(map);
const coords = heatPoints.map(p => [p[0], p[1]]);
map.fitBounds(L.latLngBounds(coords).pad(0.1));
@endif

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

// ── Escalation (doughnut) ────────────────────────────────────────────────────
new Chart(document.getElementById('chartEscalation'), {
    type: 'doughnut',
    data: {
        labels: {!! json_encode($chartEscalation['labels']) !!},
        datasets: [{
            data:            {!! json_encode($chartEscalation['data']) !!},
            backgroundColor: {!! json_encode($chartEscalation['colors']) !!},
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
</script>
@endsection
