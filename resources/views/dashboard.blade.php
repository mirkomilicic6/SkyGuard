@extends('adminlte::page')

@section('title', __('ui.dashboard.title'))

@section('content_header')
@endsection

@section('content')

@if(session('success'))
    <div class="alert alert-success alert-dismissible">
        <button type="button" class="close" data-dismiss="alert">&times;</button>
        {{ session('success') }}
    </div>
@endif
@if(session('error'))
    <div class="alert alert-danger alert-dismissible">
        <button type="button" class="close" data-dismiss="alert">&times;</button>
        {{ session('error') }}
    </div>
@endif

{{-- ================================================================
     PILOT: Checkout widget
     ================================================================ --}}
@if(auth()->user()->hasRole('pilot'))
@if($activeCheckout)
<div class="db-checkout-active mb-4">
    <div class="db-checkout-icon">
        <i class="fas fa-helicopter"></i>
    </div>
    <div class="db-checkout-info">
        <div class="db-checkout-label">{{ __('ui.checkout.checked_out_drone') }}</div>
        <div class="db-checkout-drone">{{ $activeCheckout->drone->name }}
            <span class="db-checkout-serial">{{ $activeCheckout->drone->serial_number }}</span>
        </div>
        <div class="db-checkout-since">
            <i class="fas fa-clock mr-1"></i>{{ __('ui.checkout.since') }} {{ $activeCheckout->checked_out_at->format('d.m.Y H:i') }}
        </div>
    </div>
    <form method="POST" action="{{ route('drone_checkouts.checkIn', $activeCheckout) }}" class="ml-auto">
        @csrf @method('PATCH')
        <button class="db-checkin-btn" onclick="return confirm('{{ __('ui.checkout.return_confirm') }}')">
            <i class="fas fa-sign-out-alt mr-1"></i> {{ __('ui.checkout.return_drone') }}
        </button>
    </form>
</div>
@else
<div class="db-checkout-card mb-4">
    <div class="db-checkout-card-header">
        <i class="fas fa-exclamation-triangle mr-2 text-warning"></i>
        {{ __('ui.checkout.check_out_title') }}
    </div>
    @if($assignedDrones->isEmpty())
        <p class="text-muted mb-0 mt-2"><i class="fas fa-info-circle mr-1"></i>{{ __('ui.checkout.no_available') }}</p>
    @else
    <form method="POST" action="{{ route('drone_checkouts.store') }}" class="db-checkout-form mt-3">
        @csrf
        <select name="drone_id" class="form-control db-select" required>
            <option value="">{{ __('ui.checkout.select_drone') }}</option>
            @foreach($assignedDrones as $d)
                <option value="{{ $d->id }}">{{ $d->name }} ({{ $d->serial_number }})</option>
            @endforeach
        </select>
        <input type="text" name="notes" class="form-control db-select" placeholder="{{ __('ui.checkout.note_placeholder') }}" maxlength="500">
        <button type="submit" class="db-checkout-btn">
            <i class="fas fa-sign-in-alt mr-1"></i> {{ __('ui.checkout.check_out') }}
        </button>
    </form>
    @endif
</div>
@endif
@endif

{{-- ================================================================
     ADMIN: Action queue — faults awaiting review
     ================================================================ --}}
@if(auth()->user()->hasRole('admin') && $pendingReview->isNotEmpty())
<div class="db-card mb-4">
    <div class="db-card-header">
        <div class="db-card-title">
            <i class="fas fa-exclamation-circle mr-2" style="color:#f0c040"></i>{{ __('ui.dashboard.action_queue_title') }}
        </div>
        <a href="{{ route('maintenance.index') }}" class="db-view-all ml-auto">
            {{ __('ui.view_all') }} <i class="fas fa-arrow-right ml-1"></i>
        </a>
    </div>
    <div class="db-card-body p-0">
        @foreach($pendingReview as $log)
        <div class="db-action-row">
            <div class="db-action-info">
                <div class="db-action-drone">{{ $log->drone->name }}</div>
                <div class="db-action-desc">{{ \Illuminate\Support\Str::limit($log->description, 80) }}</div>
                <div class="db-action-meta">{{ __('ui.maintenance.reported_by') }}: {{ $log->reportedBy->name }} · {{ $log->created_at->diffForHumans() }}</div>
            </div>
            <form method="POST" action="{{ route('maintenance.accept', $log) }}" class="ml-auto">
                @csrf
                <button type="submit" class="db-checkin-btn">
                    <i class="fas fa-check mr-1"></i>{{ __('ui.maintenance.accept') }}
                </button>
            </form>
            <a href="{{ route('maintenance.show', $log) }}" class="db-view-btn ml-2">
                <i class="fas fa-eye"></i>
            </a>
        </div>
        @endforeach
    </div>
</div>
@endif

{{-- ================================================================
     KPI CARDS
     ================================================================ --}}
<div class="db-kpi-grid mb-4">

    <a href="{{ route('drones.index') }}" class="db-kpi-card db-kpi-blue">
        <div class="db-kpi-icon"><i class="fas fa-helicopter"></i></div>
        <div class="db-kpi-body">
            <div class="db-kpi-value">{{ $stats['active_drones'] }}</div>
            <div class="db-kpi-label">{{ __('ui.dashboard.active_drones') }}</div>
            <div class="db-kpi-sub">{{ __('ui.dashboard.total_drones') }}: {{ $stats['total_drones'] }}</div>
        </div>
    </a>

    <a href="{{ route('flights.index') }}" class="db-kpi-card db-kpi-green">
        <div class="db-kpi-icon"><i class="fas fa-route"></i></div>
        <div class="db-kpi-body">
            <div class="db-kpi-value">{{ $stats['flights_this_month'] }}</div>
            <div class="db-kpi-label">{{ __('ui.dashboard.flights_this_month') }}</div>
            <div class="db-kpi-sub">{{ __('ui.dashboard.flights_today') }}: {{ $stats['flights_today'] }}</div>
        </div>
    </a>

    <a href="{{ route('flights.index') }}" class="db-kpi-card db-kpi-gold">
        <div class="db-kpi-icon"><i class="fas fa-clock"></i></div>
        <div class="db-kpi-body">
            <div class="db-kpi-value">{{ $stats['total_flight_hours'] }}<span class="db-kpi-unit">h</span></div>
            <div class="db-kpi-label">{{ __('ui.dashboard.total_flight_hours') }}</div>
            <div class="db-kpi-sub">{{ __('ui.dashboard.total_flights') }}: {{ $stats['total_flights'] }}</div>
        </div>
    </a>

    <a href="{{ route('maintenance.index') }}" class="db-kpi-card db-kpi-red">
        <div class="db-kpi-icon"><i class="fas fa-wrench"></i></div>
        <div class="db-kpi-body">
            <div class="db-kpi-value">{{ $stats['open_maintenance'] }}</div>
            <div class="db-kpi-label">{{ __('ui.dashboard.open_maintenance') }}</div>
            <div class="db-kpi-sub">{{ __('ui.dashboard.total_pilots') }}: {{ $stats['total_pilots'] }}</div>
        </div>
    </a>

    <a href="{{ route('detections.index') }}" class="db-kpi-card db-kpi-purple">
        <div class="db-kpi-icon"><i class="fas fa-crosshairs"></i></div>
        <div class="db-kpi-body">
            <div class="db-kpi-value">{{ $stats['total_detections'] }}</div>
            <div class="db-kpi-label">{{ __('ui.dashboard.total_detections') }}</div>
            <div class="db-kpi-sub">{{ __('ui.dashboard.detections_sub') }}</div>
        </div>
    </a>

</div>

{{-- ================================================================
     CHART + SIDEBAR
     ================================================================ --}}
<div class="row mb-4">
    <div class="col-xl-8 col-lg-7">
        <div class="db-card">
            <div class="db-card-header">
                <div>
                    <div class="db-card-title">{{ __('ui.dashboard.flights_per_month') }}</div>
                    <div class="db-card-subtitle">{{ $chartYear }}</div>
                </div>
                <form method="GET" action="{{ route('dashboard') }}" class="mb-0 ml-auto">
                    @if($dateFrom)<input type="hidden" name="date_from" value="{{ $dateFrom }}">@endif
                    @if($dateTo)<input type="hidden" name="date_to" value="{{ $dateTo }}">@endif
                    <select name="chart_year" class="db-year-select" onchange="this.form.submit()">
                        @foreach($availableYears as $yr)
                            <option value="{{ $yr }}" @selected($yr == $chartYear)>{{ $yr }}</option>
                        @endforeach
                    </select>
                </form>
            </div>
            <div class="db-card-body">
                <canvas id="monthlyChart" height="85"></canvas>
            </div>
        </div>
    </div>

    <div class="col-xl-4 col-lg-5">
        <div class="db-card h-100">
            <div class="db-card-header">
                <div class="db-card-title">{{ __('ui.dashboard.quick_stats') }}</div>
            </div>
            <div class="db-card-body p-0">
                <div class="db-stat-row">
                    <div class="db-stat-icon" style="background:rgba(60,141,188,0.15);color:#3c8dbc"><i class="fas fa-helicopter"></i></div>
                    <span>{{ __('ui.dashboard.total_drones') }}</span>
                    <strong class="ml-auto">{{ $stats['total_drones'] }}</strong>
                </div>
                <div class="db-stat-row">
                    <div class="db-stat-icon" style="background:rgba(40,167,69,0.15);color:#28a745"><i class="fas fa-users"></i></div>
                    <span>{{ __('ui.dashboard.total_pilots') }}</span>
                    <strong class="ml-auto">{{ $stats['total_pilots'] }}</strong>
                </div>
                <div class="db-stat-row">
                    <div class="db-stat-icon" style="background:rgba(240,192,64,0.15);color:#f0c040"><i class="fas fa-calendar-day"></i></div>
                    <span>{{ __('ui.dashboard.flights_today') }}</span>
                    <strong class="ml-auto">{{ $stats['flights_today'] }}</strong>
                </div>
                <div class="db-stat-row">
                    <div class="db-stat-icon" style="background:rgba(23,162,184,0.15);color:#17a2b8"><i class="fas fa-route"></i></div>
                    <span>{{ __('ui.dashboard.total_flights') }}</span>
                    <strong class="ml-auto">{{ $stats['total_flights'] }}</strong>
                </div>
                <div class="db-stat-row">
                    <div class="db-stat-icon" style="background:rgba(240,192,64,0.15);color:#f0c040"><i class="fas fa-clock"></i></div>
                    <span>{{ __('ui.dashboard.total_flight_hours') }}</span>
                    <strong class="ml-auto">{{ $stats['total_flight_hours'] }}h</strong>
                </div>
                <div class="db-stat-row" style="border:none">
                    <div class="db-stat-icon" style="background:rgba(220,53,69,0.15);color:#dc3545"><i class="fas fa-wrench"></i></div>
                    <span>{{ __('ui.dashboard.open_maintenance') }}</span>
                    <strong class="ml-auto">{{ $stats['open_maintenance'] }}</strong>
                </div>
            </div>
        </div>
    </div>
</div>

{{-- ================================================================
     DATE FILTER (admin/viewer)
     ================================================================ --}}
@if($isAdmin)
<div class="db-filter-bar mb-4">
    <form method="GET" action="{{ route('dashboard') }}" class="db-filter-form">
        @if(isset($chartYear))<input type="hidden" name="chart_year" value="{{ $chartYear }}">@endif
        <span class="db-filter-label"><i class="fas fa-filter mr-1"></i>{{ __('ui.filter_by_date') }}</span>
        <div class="input-group input-group-sm" style="width:auto">
            <div class="input-group-prepend"><span class="input-group-text db-filter-addon">{{ __('ui.from') }}</span></div>
            <input type="date" name="date_from" class="form-control db-filter-input" value="{{ $dateFrom }}" max="{{ $dateTo ?: date('Y-m-d') }}">
        </div>
        <div class="input-group input-group-sm" style="width:auto">
            <div class="input-group-prepend"><span class="input-group-text db-filter-addon">{{ __('ui.to') }}</span></div>
            <input type="date" name="date_to" class="form-control db-filter-input" value="{{ $dateTo }}" min="{{ $dateFrom }}" max="{{ date('Y-m-d') }}">
        </div>
        <button type="submit" class="db-filter-btn"><i class="fas fa-search mr-1"></i>{{ __('ui.apply') }}</button>
        @if($dateFrom || $dateTo)
            <a href="{{ route('dashboard') }}" class="db-filter-clear"><i class="fas fa-times mr-1"></i>{{ __('ui.clear') }}</a>
            <span class="db-filter-badge">{{ $recentFlights->count() }} {{ __('ui.flights_label') }}</span>
        @endif
    </form>
</div>
@endif

{{-- ================================================================
     HEATMAP (admin/viewer/pilot) — super admin gets the fuller
     "Pregled cijele mreže" card below instead.
     ================================================================ --}}
@if(!$isSuperAdmin)
<div class="db-card mb-4">
    <div class="db-card-header">
        <div>
            <div class="db-card-title">
                <i class="fas fa-fire mr-2" style="color:#f46d43"></i>
                @if($isAdmin) {{ __('ui.dashboard.heatmap_title') }}
                @else {{ __('ui.dashboard.heatmap_own') }}
                @endif
            </div>
            <div class="db-card-subtitle">{{ __('ui.dashboard.gps_points') }}: {{ number_format(count($heatmapPoints)) }}</div>
        </div>
    </div>
    <div style="position:relative">
        @if(count($heatmapPoints) > 0)
            <div id="heatmap" style="height:420px; border-radius:0 0 12px 12px;"></div>
        @else
            <div class="db-empty-map">
                <i class="fas fa-map-marked-alt"></i>
                <div>{{ __('ui.dashboard.no_gps_data') }}</div>
            </div>
        @endif
    </div>
</div>
@else
{{-- ================================================================
     SUPER ADMIN: network-wide overview map
     Filters (uprava/postaja, datum, vrsta, izvor) + toggleable layers:
     heatmapa, detekcije, letovi, kamere, postaje, granice uprava.
     ================================================================ --}}
<div class="db-card mb-4">
    <div class="db-card-header">
        <div>
            <div class="db-card-title">
                <i class="fas fa-globe-europe mr-2" style="color:#f46d43"></i>Pregled cijele mreže
            </div>
            <div class="db-card-subtitle" id="ovmFilteredCount">Učitavanje...</div>
        </div>
    </div>

    {{-- Filters ------------------------------------------------------------ --}}
    <div class="ovm-filters">
        <select id="ovmAdministration" class="form-control form-control-sm">
            <option value="">— Sva uprava —</option>
            @foreach($administrations as $adm)
                <option value="{{ $adm->id }}"
                    data-stations="{{ $adm->stations->map(fn($s)=>['id'=>$s->id,'name'=>$s->name])->toJson() }}">
                    {{ $adm->name }}
                </option>
            @endforeach
        </select>
        <select id="ovmStation" class="form-control form-control-sm">
            <option value="">— Sve postaje —</option>
            @foreach($administrations->flatMap->stations as $st)
                <option value="{{ $st->id }}" data-admin="{{ $st->police_administration_id }}">{{ $st->name }}</option>
            @endforeach
        </select>

        <input type="date" id="ovmDateFrom" class="form-control form-control-sm" title="Datum od">
        <input type="date" id="ovmDateTo" class="form-control form-control-sm" title="Datum do">

        <select id="ovmType" class="form-control form-control-sm">
            <option value="">— Sve vrste —</option>
            <option value="person">Osoba</option>
            <option value="group">Grupa</option>
            <option value="vehicle">Vozilo</option>
            <option value="other">Ostalo</option>
        </select>

        <select id="ovmSource" class="form-control form-control-sm">
            <option value="">— Svi izvori —</option>
            <option value="drone">Dron</option>
            <option value="trail_camera">Kamera</option>
            <option value="ground_observation">Ručno (teren)</option>
            <option value="other">Ostalo</option>
        </select>

        <button id="ovmApply" class="btn btn-sm btn-primary"><i class="fas fa-filter mr-1"></i>Primijeni</button>
        <button id="ovmReset" class="btn btn-sm btn-secondary"><i class="fas fa-times"></i></button>
    </div>

    {{-- Layer toggles -------------------------------------------------------- --}}
    <div class="ovm-layers">
        <label><input type="checkbox" id="ovmLayerHeat" checked> Heatmapa letova</label>
        <label><input type="checkbox" id="ovmLayerPoints"> Detekcije</label>
        <label><input type="checkbox" id="ovmLayerRoutes"> Letovi (GPX)</label>
        <label><input type="checkbox" id="ovmLayerCameras"> Lovačke kamere</label>
        <label><input type="checkbox" id="ovmLayerStations"> Postaje</label>
        <label><input type="checkbox" id="ovmLayerAdmins"> Granice uprava</label>
    </div>

    <div style="position:relative">
        <div id="overviewMap" style="height:480px; border-radius:0 0 12px 12px;"></div>
    </div>

    {{-- Legend ---------------------------------------------------------------- --}}
    <div class="ovm-legend">
        <span><i class="ovm-legend-dot" style="background:#fd7e14"></i>Osoba</span>
        <span><i class="ovm-legend-dot" style="background:#dc3545"></i>Grupa</span>
        <span><i class="ovm-legend-dot" style="background:#0d6efd"></i>Vozilo</span>
        <span><i class="ovm-legend-dot" style="background:#6c757d"></i>Ostalo</span>
        <span><i class="ovm-legend-dot" style="background:#20c997"></i>Kamera</span>
        <span><i class="ovm-legend-dot" style="background:#f0c040"></i>Postaja</span>
    </div>
</div>
@endif

{{-- ================================================================
     RECENT FLIGHTS
     ================================================================ --}}
<div class="db-card">
    <div class="db-card-header">
        <div>
            <div class="db-card-title">{{ __('ui.dashboard.recent_flights') }}</div>
            @if($isAdmin && ($dateFrom || $dateTo))
            <div class="db-card-subtitle">{{ __('ui.filtered') }}</div>
            @endif
        </div>
        <a href="{{ route('flights.index') }}" class="db-view-all ml-auto">
            {{ __('ui.view_all') }} <i class="fas fa-arrow-right ml-1"></i>
        </a>
    </div>
    <div class="db-card-body p-0">
        <div class="db-flights-table-wrap">
            <table class="db-table">
                <thead>
                    <tr>
                        <th>{{ __('ui.flights.drone') }}</th>
                        <th>{{ __('ui.flights.pilot') }}</th>
                        @if($isAdmin)
                        <th>{{ __('ui.users.station') }}</th>
                        @endif
                        <th>{{ __('ui.flights.date') }}</th>
                        <th>{{ __('ui.flights.duration') }}</th>
                        <th>{{ __('ui.flights.status') }}</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($recentFlights as $flight)
                    <tr>
                        <td>
                            <div class="db-drone-cell">
                                <div class="db-drone-dot"></div>
                                <span>{{ $flight->drone->name }}</span>
                            </div>
                        </td>
                        <td>{{ $flight->pilot->name }}</td>
                        @if($isAdmin)
                        <td>
                            <small class="db-station-admin">{{ $flight->station->administration->name ?? '' }}</small>
                            <span class="db-station-name">{{ $flight->station->name ?? '—' }}</span>
                        </td>
                        @endif
                        <td class="db-date-cell">{{ $flight->flight_date->translatedFormat('d F Y') }}
                            <small>{{ $flight->flight_date->format('H:i') }}</small>
                        </td>
                        <td>{{ $flight->duration_minutes ?? '—' }} min</td>
                        <td>
                            <span class="db-status db-status-{{ $flight->status }}">
                                {{ __('ui.status.' . $flight->status, [], null) ?: ucfirst($flight->status) }}
                            </span>
                        </td>
                        <td>
                            <a href="{{ route('flights.show', $flight) }}" class="db-view-btn">
                                <i class="fas fa-eye"></i>
                            </a>
                        </td>
                    </tr>
                    @empty
                    <tr><td colspan="{{ $isAdmin ? 7 : 6 }}" class="db-empty-row"><i class="fas fa-inbox mr-2"></i>{{ __('ui.dashboard.no_flights_yet') }}</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
    @if($recentFlights->hasPages())
    <div class="db-pagination">
        {{ $recentFlights->links() }}
    </div>
    @endif
</div>

@include('partials.ai-chat-widget')

@endsection

@section('css')
<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css"/>
<style>
/* ── content padding (content-header is hidden) ──────────────── */
.content-wrapper > .content { padding-top: 1.5rem; }

/* ── KPI GRID ───────────────────────────────────────────────── */
.db-kpi-grid {
    display: grid;
    grid-template-columns: repeat(5, 1fr);
    gap: 1rem;
}
@media(max-width:1400px){ .db-kpi-grid { grid-template-columns: repeat(3,1fr); } }
@media(max-width:768px) { .db-kpi-grid { grid-template-columns: repeat(2,1fr); } }
@media(max-width:480px) { .db-kpi-grid { grid-template-columns: 1fr; } }

/* ── YEAR SELECT ─────────────────────────────────────────────── */
.db-year-select {
    background: rgba(255,255,255,0.07);
    border: 1px solid rgba(255,255,255,0.12);
    color: #fff;
    border-radius: 8px;
    padding: 4px 10px;
    font-size: 0.82rem;
}
.db-year-select option { background: #112247; }

/* ── FILTER BAR ──────────────────────────────────────────────── */
.db-filter-bar {
    background: var(--navy-card);
    border: 1px solid rgba(255,255,255,0.06);
    border-radius: 12px;
    padding: 0.75rem 1.2rem;
}
.db-filter-form {
    display: flex;
    align-items: center;
    flex-wrap: wrap;
    gap: 0.6rem;
}
.db-filter-label { font-size: 0.82rem; color: rgba(255,255,255,0.5); white-space: nowrap; }
.db-filter-input {
    background: rgba(255,255,255,0.07) !important;
    border-color: rgba(255,255,255,0.12) !important;
    color: #fff !important;
    font-size: 0.82rem;
}
.db-filter-addon {
    background: rgba(255,255,255,0.05) !important;
    border-color: rgba(255,255,255,0.12) !important;
    color: rgba(255,255,255,0.5) !important;
    font-size: 0.82rem;
}
.db-filter-btn {
    background: var(--navy-mid);
    border: 1px solid rgba(255,255,255,0.15);
    color: #fff;
    border-radius: 8px;
    padding: 4px 14px;
    font-size: 0.82rem;
    cursor: pointer;
}
.db-filter-btn:hover { background: var(--navy-light); border-color: var(--gold); }
.db-filter-clear {
    color: rgba(255,255,255,0.4);
    font-size: 0.82rem;
    text-decoration: none;
}
.db-filter-clear:hover { color: rgba(255,255,255,0.7); }
.db-filter-badge {
    background: rgba(23,162,184,0.2);
    color: #7dd3f5;
    border-radius: 20px;
    padding: 2px 10px;
    font-size: 0.75rem;
}

/* ── EMPTY MAP ───────────────────────────────────────────────── */
.db-empty-map {
    height: 200px;
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    color: rgba(255,255,255,0.25);
    font-size: 2.5rem;
    gap: 0.75rem;
}
.db-empty-map div { font-size: 0.9rem; }

/* ── FLIGHTS TABLE ───────────────────────────────────────────── */
.db-flights-table-wrap { overflow-x: auto; }
.db-table {
    width: 100%;
    border-collapse: collapse;
    font-size: 0.85rem;
}
.db-table thead th {
    padding: 0.6rem 1rem;
    color: rgba(255,255,255,0.35);
    font-size: 0.72rem;
    text-transform: uppercase;
    letter-spacing: 0.6px;
    font-weight: 600;
    border-bottom: 1px solid rgba(255,255,255,0.06);
    white-space: nowrap;
}
.db-table tbody tr {
    border-bottom: 1px solid rgba(255,255,255,0.04);
    transition: background .15s;
}
.db-table tbody tr:hover { background: rgba(255,255,255,0.03); }
.db-table tbody td { padding: 0.65rem 1rem; color: rgba(255,255,255,0.8); vertical-align: middle; }

.db-drone-cell { display: flex; align-items: center; gap: 0.5rem; }
.db-drone-dot  { width: 7px; height: 7px; border-radius: 50%; background: var(--gold); flex-shrink: 0; }
.db-date-cell small { display: block; color: rgba(255,255,255,0.35); font-size: 0.72rem; }

.db-status {
    display: inline-block;
    padding: 2px 10px;
    border-radius: 20px;
    font-size: 0.72rem;
    font-weight: 600;
    letter-spacing: 0.3px;
}
.db-status-completed { background: rgba(40,167,69,0.2);  color: #7ddc9f; }
.db-status-aborted   { background: rgba(220,53,69,0.2);  color: #f5a0a0; }

.db-empty-row { text-align: center; color: rgba(255,255,255,0.3); padding: 2rem !important; }

.db-station-admin { display: block; font-size: .7rem; color: rgba(255,255,255,.3); line-height: 1.2; }
.db-station-name  { font-size: .82rem; color: rgba(255,255,255,.7); }

.db-view-btn {
    display: inline-flex;
    align-items: center;
    justify-content: center;
    width: 28px; height: 28px;
    border-radius: 7px;
    background: rgba(23,162,184,0.15);
    color: #7dd3f5;
    text-decoration: none;
    font-size: 0.8rem;
    transition: background .15s;
}
.db-view-btn:hover { background: rgba(23,162,184,0.3); color: #fff; text-decoration: none; }

/* ── SUPER ADMIN OVERVIEW MAP ────────────────────────────────── */
.ovm-filters {
    display: flex; flex-wrap: wrap; align-items: center; gap: .5rem;
    padding: .6rem 1.2rem;
    border-top: 1px solid rgba(255,255,255,0.06);
}
.ovm-filters select, .ovm-filters input { width: auto; }
.ovm-layers {
    display: flex; flex-wrap: wrap; align-items: center; gap: 1rem;
    padding: .6rem 1.2rem;
    background: rgba(255,255,255,0.02);
}
.ovm-layers label {
    display: flex; align-items: center; gap: .4rem;
    font-size: .8rem; color: rgba(255,255,255,0.6); margin: 0;
}
.ovm-legend {
    display: flex; flex-wrap: wrap; align-items: center; gap: 1rem;
    padding: .6rem 1.2rem;
    font-size: .75rem; color: rgba(255,255,255,0.5);
    border-top: 1px solid rgba(255,255,255,0.06);
}
.ovm-legend span { display: flex; align-items: center; gap: .35rem; }
.ovm-legend-dot { display: inline-block; width: 9px; height: 9px; border-radius: 50%; }

/* ── CHECKOUT WIDGET ─────────────────────────────────────────── */
.db-checkout-active {
    display: flex;
    align-items: center;
    gap: 1.2rem;
    background: linear-gradient(135deg, rgba(40,167,69,0.12), rgba(40,167,69,0.04));
    border: 1px solid rgba(40,167,69,0.3);
    border-radius: 12px;
    padding: 1rem 1.4rem;
}
.db-checkout-icon {
    width: 48px; height: 48px;
    border-radius: 10px;
    background: rgba(40,167,69,0.2);
    color: #7ddc9f;
    display: flex; align-items: center; justify-content: center;
    font-size: 1.3rem;
    flex-shrink: 0;
}
.db-checkout-label { font-size: 0.72rem; color: rgba(255,255,255,0.45); text-transform: uppercase; letter-spacing: 0.5px; }
.db-checkout-drone { font-size: 1rem; font-weight: 600; color: #fff; }
.db-checkout-serial { font-size: 0.75rem; color: rgba(255,255,255,0.4); margin-left: 6px; }
.db-checkout-since { font-size: 0.75rem; color: rgba(255,255,255,0.4); margin-top: 2px; }
.db-checkin-btn {
    background: rgba(240,192,64,0.15);
    border: 1px solid rgba(240,192,64,0.4);
    color: var(--gold);
    border-radius: 9px;
    padding: 7px 18px;
    font-size: 0.82rem;
    font-weight: 600;
    cursor: pointer;
    white-space: nowrap;
    transition: background .15s;
}
.db-checkin-btn:hover { background: rgba(240,192,64,0.28); }

.db-checkout-card {
    background: var(--navy-card);
    border: 1px solid rgba(240,192,64,0.2);
    border-radius: 12px;
    padding: 1rem 1.4rem;
}
.db-checkout-card-header { font-size: 0.9rem; font-weight: 600; color: rgba(255,255,255,0.85); }
.db-checkout-form { display: flex; gap: 0.6rem; flex-wrap: wrap; }
.db-select {
    background: rgba(255,255,255,0.07) !important;
    border-color: rgba(255,255,255,0.12) !important;
    color: #fff !important;
    border-radius: 9px !important;
}
.db-checkout-btn {
    background: rgba(240,192,64,0.15);
    border: 1px solid rgba(240,192,64,0.4);
    color: var(--gold);
    border-radius: 9px;
    padding: 6px 18px;
    font-size: 0.85rem;
    font-weight: 600;
    cursor: pointer;
    white-space: nowrap;
}
.db-checkout-btn:hover { background: rgba(240,192,64,0.28); }

/* ── Admin action queue ──────────────────────────────────────── */
.db-action-row {
    display: flex; align-items: center; gap: 1rem;
    padding: 0.85rem 1.4rem;
    border-bottom: 1px solid rgba(255,255,255,0.05);
}
.db-action-row:last-child { border-bottom: none; }
.db-action-info { display: flex; flex-direction: column; gap: 2px; min-width: 0; flex: 1; }
.db-action-drone { font-weight: 600; color: #fff; font-size: 0.88rem; }
.db-action-desc { color: rgba(255,255,255,0.6); font-size: 0.8rem; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
.db-action-meta { color: rgba(255,255,255,0.35); font-size: 0.72rem; }

/* ── content header hidden ───────────────────────────────────── */
.content-header { display: none; }
</style>
@endsection

@section('js')
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
@if(!$isSuperAdmin && count($heatmapPoints) > 0)
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
<script src="https://unpkg.com/leaflet.heat@0.2.0/dist/leaflet-heat.js"></script>
@php
    $lats      = $heatmapPoints->pluck(0);
    $lons      = $heatmapPoints->pluck(1);
    $centerLat = $lats->avg();
    $centerLon = $lons->avg();
@endphp
<script>
const heatData = @json($heatmapPoints);
const map = L.map('heatmap').setView([{{ $centerLat }}, {{ $centerLon }}], 11);
L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
    attribution: '© OpenStreetMap',
    maxZoom: 19
}).addTo(map);
L.heatLayer(heatData, {
    radius: 20, blur: 25, maxZoom: 17,
    gradient: { 0.2:'#4575b4', 0.45:'#74add1', 0.65:'#fdae61', 0.82:'#f46d43', 1.0:'#d73027' }
}).addTo(map);
</script>
@endif
@if($isSuperAdmin)
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
<script src="https://unpkg.com/leaflet.heat@0.2.0/dist/leaflet-heat.js"></script>
<script>
// ── Super admin: network-wide overview map ─────────────────────────────────
const overviewMap = L.map('overviewMap').setView([45.1, 18.0], 8);
L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
    attribution: '© OpenStreetMap', maxZoom: 19
}).addTo(overviewMap);

let ovmData = { points: [], routes: [], cameras: [], stations: [], administrations: [] };
let ovmHeatLayer = null;
const ovmPointsGroup   = L.layerGroup();
const ovmRoutesGroup   = L.layerGroup();
const ovmCamerasGroup  = L.layerGroup();
const ovmStationsGroup = L.layerGroup();
const ovmAdminsGroup   = L.layerGroup();

const ovmTypeColors = { person: '#fd7e14', group: '#dc3545', vehicle: '#0d6efd', other: '#6c757d' };

function ovmRenderHeat() {
    if (ovmHeatLayer) { overviewMap.removeLayer(ovmHeatLayer); ovmHeatLayer = null; }
    if (!document.getElementById('ovmLayerHeat').checked) return;
    // Heat layer here represents flight-route density (GPX points), same
    // dataset/semantics as the admin/viewer "Heatmapa" card, not detections.
    const pts = ovmData.routes.flat();
    if (!pts.length) return;
    ovmHeatLayer = L.heatLayer(pts, {
        radius: 20, blur: 25, maxZoom: overviewMap.getZoom(),
        gradient: { 0.2: '#4575b4', 0.45: '#74add1', 0.65: '#fdae61', 0.82: '#f46d43', 1.0: '#d73027' }
    }).addTo(overviewMap);
}

function ovmRenderPoints() {
    ovmPointsGroup.clearLayers();
    ovmData.points.forEach(p => {
        const color = ovmTypeColors[p.detection_type] || '#6c757d';
        L.circleMarker([p.lat, p.lon], { radius: 4, color, fillColor: color, fillOpacity: .8, weight: 1 })
            .bindPopup(`<b>${p.detection_type}</b><br><small>${p.source} · ${p.entity_count} ent. · ${p.detected_at}</small>`)
            .addTo(ovmPointsGroup);
    });
}

function ovmRenderRoutes() {
    ovmRoutesGroup.clearLayers();
    ovmData.routes.forEach(pts => {
        L.polyline(pts, { color: '#f0c040', weight: 1.5, opacity: .35 }).addTo(ovmRoutesGroup);
    });
}

function ovmRenderCameras() {
    ovmCamerasGroup.clearLayers();
    ovmData.cameras.forEach(c => {
        L.circleMarker([c.lat, c.lon], { radius: 5, color: '#20c997', fillColor: '#20c997', fillOpacity: .9, weight: 1 })
            .bindPopup(`<b>${c.name}</b><br><small>${c.location_name ?? ''}<br>${c.station_name ?? ''} · ${c.is_active ? 'aktivna' : 'neaktivna'}</small>`)
            .addTo(ovmCamerasGroup);
    });
}

function ovmRenderStations() {
    ovmStationsGroup.clearLayers();
    ovmData.stations.forEach(s => {
        L.marker([s.lat, s.lon], { icon: L.divIcon({ className: '', html: `<div style="width:10px;height:10px;border-radius:50%;background:#f0c040;border:2px solid #fff"></div>` }) })
            .bindPopup(`<b>${s.name}</b><br><small>${s.administration_name ?? ''}</small>`)
            .addTo(ovmStationsGroup);
        if (s.boundary && s.boundary.length >= 3) {
            L.polygon(s.boundary, { color: '#f0c040', weight: 1.5, fillOpacity: .04 }).addTo(ovmStationsGroup);
        }
    });
}

function ovmRenderAdmins() {
    ovmAdminsGroup.clearLayers();
    ovmData.administrations.forEach(a => {
        if (a.boundary && a.boundary.length >= 3) {
            L.polygon(a.boundary, { color: '#9b59d0', weight: 2, fillOpacity: .03, dashArray: '6 4' })
                .bindPopup(a.name)
                .addTo(ovmAdminsGroup);
        }
    });
}

function ovmSyncVisibility() {
    document.getElementById('ovmLayerPoints').checked   ? ovmPointsGroup.addTo(overviewMap)   : overviewMap.removeLayer(ovmPointsGroup);
    document.getElementById('ovmLayerRoutes').checked   ? ovmRoutesGroup.addTo(overviewMap)   : overviewMap.removeLayer(ovmRoutesGroup);
    document.getElementById('ovmLayerCameras').checked  ? ovmCamerasGroup.addTo(overviewMap)  : overviewMap.removeLayer(ovmCamerasGroup);
    document.getElementById('ovmLayerStations').checked ? ovmStationsGroup.addTo(overviewMap) : overviewMap.removeLayer(ovmStationsGroup);
    document.getElementById('ovmLayerAdmins').checked   ? ovmAdminsGroup.addTo(overviewMap)   : overviewMap.removeLayer(ovmAdminsGroup);
    ovmRenderHeat();
}

function ovmRenderAll() {
    ovmRenderPoints();
    ovmRenderRoutes();
    ovmRenderCameras();
    ovmRenderStations();
    ovmRenderAdmins();
    ovmSyncVisibility();
    document.getElementById('ovmFilteredCount').textContent =
        `${ovmData.points.length} detekcija · ${ovmData.routes.length} letova · ${ovmData.cameras.length} kamera · ${ovmData.stations.length} postaja`;
}

function ovmBuildQuery() {
    const params = new URLSearchParams();
    const admin = document.getElementById('ovmAdministration').value;
    if (admin) params.set('administration_id', admin);
    const station = document.getElementById('ovmStation').value;
    if (station) params.set('station_id', station);
    const from = document.getElementById('ovmDateFrom').value;
    if (from) params.set('date_from', from);
    const to = document.getElementById('ovmDateTo').value;
    if (to) params.set('date_to', to);
    const type = document.getElementById('ovmType').value;
    if (type) params.set('detection_type', type);
    const source = document.getElementById('ovmSource').value;
    if (source) params.set('source', source);
    return params.toString();
}

function ovmLoadData() {
    document.getElementById('ovmFilteredCount').textContent = 'Učitavanje...';
    fetch('{{ route('dashboard.overviewMap') }}?' + ovmBuildQuery())
        .then(r => r.json())
        .then(d => {
            ovmData = d;
            const allPts = ovmData.routes.flat().concat(ovmData.points.map(p => [p.lat, p.lon]));
            if (allPts.length) overviewMap.fitBounds(L.latLngBounds(allPts).pad(0.1));
            ovmRenderAll();
        })
        .catch(() => { document.getElementById('ovmFilteredCount').textContent = 'Greška pri učitavanju'; });
}

document.getElementById('ovmApply').addEventListener('click', ovmLoadData);
document.getElementById('ovmReset').addEventListener('click', () => {
    ['ovmAdministration', 'ovmStation', 'ovmDateFrom', 'ovmDateTo', 'ovmType', 'ovmSource'].forEach(id => {
        document.getElementById(id).value = '';
    });
    ovmLoadData();
});
['ovmLayerHeat', 'ovmLayerPoints', 'ovmLayerRoutes', 'ovmLayerCameras', 'ovmLayerStations', 'ovmLayerAdmins'].forEach(id => {
    document.getElementById(id).addEventListener('change', ovmSyncVisibility);
});

// Administration → station cascading filter
const ovmAdminSelect = document.getElementById('ovmAdministration');
const ovmStationSelect = document.getElementById('ovmStation');
ovmAdminSelect.addEventListener('change', () => {
    const adminId = ovmAdminSelect.value;
    Array.from(ovmStationSelect.options).forEach(opt => {
        if (!opt.value) return;
        opt.hidden = adminId !== '' && opt.dataset.admin != adminId;
    });
    if (adminId !== '' && ovmStationSelect.value &&
        ovmStationSelect.options[ovmStationSelect.selectedIndex]?.dataset.admin != adminId) {
        ovmStationSelect.value = '';
    }
});

ovmLoadData();
</script>
@endif
<script>
const ctx         = document.getElementById('monthlyChart').getContext('2d');
const monthlyData = @json($monthlyFlights);
const labels      = @json(__('ui.dashboard.months'));
const data        = labels.map((_, i) => monthlyData[i + 1] || 0);

const grad = ctx.createLinearGradient(0, 0, 0, 260);
grad.addColorStop(0,   'rgba(240,192,64,0.35)');
grad.addColorStop(1,   'rgba(240,192,64,0.02)');

new Chart(ctx, {
    type: 'line',
    data: {
        labels,
        datasets: [{
            label: '{{ __('ui.dashboard.flights_chart') }}',
            data,
            borderColor: '#f0c040',
            borderWidth: 2.5,
            backgroundColor: grad,
            pointBackgroundColor: '#f0c040',
            pointRadius: 4,
            pointHoverRadius: 6,
            tension: 0.4,
            fill: true,
        }]
    },
    options: {
        responsive: true,
        plugins: {
            legend: { display: false },
            tooltip: {
                backgroundColor: '#0d2460',
                borderColor: 'rgba(240,192,64,0.4)',
                borderWidth: 1,
                titleColor: '#f0c040',
                bodyColor: 'rgba(255,255,255,0.8)',
                padding: 10,
            }
        },
        scales: {
            x: {
                grid: { color: 'rgba(255,255,255,0.05)' },
                ticks: { color: 'rgba(255,255,255,0.4)', font: { size: 11 } }
            },
            y: {
                beginAtZero: true,
                grid: { color: 'rgba(255,255,255,0.05)' },
                ticks: { color: 'rgba(255,255,255,0.4)', font: { size: 11 }, stepSize: 1 }
            }
        }
    }
});
</script>
@endsection
