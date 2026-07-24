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
     GREETING + WEATHER
     ================================================================ --}}
<div class="ph-header mb-4">
    <div class="ph-greeting">
        <div class="ph-greeting-title">{{ __('ui.home.welcome', ['name' => $user->name]) }}</div>
        @if($station)
            <div class="ph-greeting-sub">
                <i class="fas fa-shield-alt mr-1"></i>{{ $station->name }}
                <span class="ph-greeting-dot">•</span>
                {{ $station->administration->name ?? '' }}
            </div>
        @endif
    </div>

    <div class="ph-weather ph-weather-{{ $weather['level'] ?? 'muted' }}">
        @if($weather)
            <div class="ph-weather-icon"><i class="fas {{ $weather['icon'] }}"></i></div>
            <div class="ph-weather-body">
                <div class="ph-weather-top">
                    <span class="ph-weather-temp">{{ $weather['temperature'] }}°C</span>
                    <span class="ph-weather-level">{{ __('ui.home.level.' . $weather['level']) }}</span>
                </div>
                <div class="ph-weather-sub">
                    {{ $weather['description'] }} ·
                    {{ __('ui.home.weather_wind') }} {{ $weather['wind_speed'] }} km/h ·
                    {{ __('ui.home.weather_gusts') }} {{ $weather['wind_gusts'] }} km/h
                </div>
            </div>
        @else
            <div class="ph-weather-icon"><i class="fas fa-cloud-sun"></i></div>
            <div class="ph-weather-body">
                <div class="ph-weather-top"><span class="ph-weather-level">{{ __('ui.home.weather_title') }}</span></div>
                <div class="ph-weather-sub">{{ __('ui.home.weather_unavailable') }}</div>
            </div>
        @endif
    </div>

    <button type="button" id="phOpenAiChat" class="ph-ai-widget">
        <div class="ph-ai-widget-icon"><i class="fas fa-robot"></i></div>
        <div class="ph-ai-widget-body">
            <div class="ph-ai-widget-title">{{ __('ui.home.ai_assistant_title') }}</div>
            <div class="ph-ai-widget-sub">{{ __('ui.home.ai_assistant_desc') }}</div>
        </div>
    </button>
</div>

{{-- ================================================================
     CHECKOUT — active checkout bar, or check-out form
     ================================================================ --}}
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

{{-- ================================================================
     MAINTENANCE ALERTS
     ================================================================ --}}
<div class="db-card mb-4">
    <div class="db-card-header ph-collapsible">
        <div class="db-card-title">
            <i class="fas fa-wrench mr-2" style="color:#dc3545"></i>{{ __('ui.home.maintenance_alerts_title') }}
        </div>
        <a href="{{ route('maintenance.index') }}" class="db-view-all ml-auto">
            {{ __('ui.view_all') }} <i class="fas fa-arrow-right ml-1"></i>
        </a>
        <i class="fas fa-chevron-down ph-card-toggle ml-2"></i>
    </div>
    <div class="db-card-body p-0">
        @forelse($maintenanceAlerts as $log)
            <a href="{{ route('maintenance.show', $log) }}" class="ph-alert-row">
                <span class="badge badge-{{ $log->statusBadge() }} ph-alert-badge">
                    {{ __('ui.status.' . $log->status, [], null) ?: ucfirst(str_replace('_', ' ', $log->status)) }}
                </span>
                <span class="ph-alert-drone">{{ $log->drone->name }}</span>
                <span class="ph-alert-desc">{{ \Illuminate\Support\Str::limit($log->description, 70) }}</span>
                <i class="fas fa-chevron-right ml-auto" style="color:rgba(255,255,255,0.2)"></i>
            </a>
        @empty
            <div class="db-empty-row"><i class="fas fa-check-circle mr-2" style="color:#28a745"></i>{{ __('ui.home.maintenance_alerts_empty') }}</div>
        @endforelse
    </div>
</div>

{{-- ================================================================
     CAMERAS IN THE FIELD
     ================================================================ --}}
<div class="db-card mb-4">
    <div class="db-card-header ph-collapsible">
        <div class="db-card-title">
            <i class="fas fa-camera mr-2" style="color:#17a2b8"></i>{{ __('ui.home.cameras_title') }}
        </div>
        <a href="{{ route('cameras.index') }}" class="db-view-all ml-auto">
            {{ __('ui.view_all') }} <i class="fas fa-arrow-right ml-1"></i>
        </a>
        <i class="fas fa-chevron-down ph-card-toggle ml-2"></i>
    </div>
    @if($cameras->isEmpty())
        <div class="db-empty-row"><i class="fas fa-camera mr-2"></i>{{ __('ui.home.cameras_empty') }}</div>
    @else
        <div class="ph-camera-layout">
            <div class="ph-camera-stats">
                <div class="ph-camera-stat">
                    <span class="ph-camera-stat-value" style="color:#dc3545">{{ $cameras->where('is_active', true)->count() }}</span>
                    <span class="ph-camera-stat-label">{{ __('ui.home.cameras_active') }}</span>
                </div>
                <div class="ph-camera-stat">
                    <span class="ph-camera-stat-value" style="color:rgba(255,255,255,0.4)">{{ $cameras->where('is_active', false)->count() }}</span>
                    <span class="ph-camera-stat-label">{{ __('ui.home.cameras_inactive') }}</span>
                </div>
            </div>
            <div class="ph-camera-list">
                @foreach($cameras->take(6) as $cam)
                <a href="{{ route('cameras.show', $cam) }}" class="ph-camera-row">
                    <span class="ph-camera-dot" style="background:{{ $cam->is_active ? '#dc3545' : '#6c757d' }}"></span>
                    <span class="ph-camera-name">{{ $cam->name }}</span>
                    <span class="ph-camera-loc">{{ $cam->location_name }}</span>
                </a>
                @endforeach
            </div>
            <div id="camerasMiniMap" class="ph-mini-map"></div>
        </div>
    @endif
</div>

{{-- ================================================================
     AI SURVEILLANCE RECOMMENDATION
     ================================================================ --}}
<div class="db-card mb-4 ph-ai-card">
    <div class="db-card-header ph-collapsible">
        <div class="db-card-title">
            <i class="fas fa-robot mr-2" style="color:var(--gold)"></i>{{ __('ui.home.ai_risk_title') }}
        </div>
        @if($aiSummary && !isset($aiSummary['message']))
        <span class="ml-auto" style="font-size:.72rem;color:rgba(255,255,255,.4)">
            {{ __('ui.home.ai_risk_trained_on', ['count' => $aiSummary['trained_on']]) }}
        </span>
        <i class="fas fa-chevron-down ph-card-toggle ml-2"></i>
        @else
        <i class="fas fa-chevron-down ph-card-toggle ml-auto"></i>
        @endif
    </div>
    @if(!$aiSummary)
        <div class="db-empty-row"><i class="fas fa-robot mr-2"></i>{{ __('ui.home.ai_risk_unavailable') }}</div>
    @elseif(isset($aiSummary['message']))
        <div class="db-empty-row"><i class="fas fa-info-circle mr-2"></i>{{ $aiSummary['message'] }}</div>
    @else
        <div class="ph-camera-layout">
            <div class="ph-ai-lists">
                @if(count($aiSummary['increase']))
                <div class="ph-ai-list-title"><i class="fas fa-arrow-up mr-1" style="color:#f5a0a0"></i>{{ __('ui.home.ai_risk_increase') }}</div>
                @foreach($aiSummary['increase'] as $c)
                <div class="ph-ai-row">
                    <span class="ph-ai-dot" style="background:#dc3545"></span>
                    {{ number_format($c['lat'], 4) }}, {{ number_format($c['lon'], 4) }}
                    <span class="ph-ai-risk">{{ __('ui.home.risk_label') }} {{ $c['risk'] }}</span>
                </div>
                @endforeach
                @endif
            </div>
            <div id="aiMiniMap" class="ph-mini-map"></div>
        </div>
        <div class="ph-ai-footer">
            <button type="button" id="phOpenAiChatFromRisk" class="ph-ai-open-btn">
                <i class="fas fa-comment-dots mr-1"></i>{{ __('ui.home.ai_risk_open') }}
            </button>
        </div>
    @endif
</div>

{{-- ================================================================
     QUICK ACTIONS
     ================================================================ --}}
<div class="ph-section-title mb-2">{{ __('ui.home.quick_actions') }}</div>
<div class="ph-quick-grid mb-4">
    <a href="{{ route('maintenance.create') }}" class="ph-quick-tile">
        <div class="ph-quick-icon" style="background:rgba(220,53,69,0.15);color:#dc3545"><i class="fas fa-tools"></i></div>
        <div class="ph-quick-label">{{ __('ui.home.action_report_fault') }}</div>
    </a>
    <a href="{{ route('cameras.index') }}" class="ph-quick-tile">
        <div class="ph-quick-icon" style="background:rgba(23,162,184,0.15);color:#17a2b8"><i class="fas fa-camera"></i></div>
        <div class="ph-quick-label">{{ __('ui.home.action_cameras') }}</div>
    </a>
</div>

{{-- ================================================================
     MONTH STATS
     ================================================================ --}}
<div class="ph-section-title mb-2">{{ __('ui.home.month_stats') }}</div>
<div class="db-kpi-grid mb-4">
    <a href="{{ route('flights.index') }}" class="db-kpi-card db-kpi-blue">
        <div class="db-kpi-icon"><i class="fas fa-route"></i></div>
        <div class="db-kpi-body">
            <div class="db-kpi-value">{{ $monthStats['flights'] }}</div>
            <div class="db-kpi-label">{{ __('ui.home.month_flights') }}</div>
        </div>
    </a>
    <a href="{{ route('flights.index') }}" class="db-kpi-card db-kpi-gold">
        <div class="db-kpi-icon"><i class="fas fa-clock"></i></div>
        <div class="db-kpi-body">
            <div class="db-kpi-value">{{ $monthStats['hours'] }}<span class="db-kpi-unit">h</span></div>
            <div class="db-kpi-label">{{ __('ui.home.month_hours') }}</div>
        </div>
    </a>
    <a href="{{ route('cameras.index') }}" class="db-kpi-card db-kpi-green">
        <div class="db-kpi-icon"><i class="fas fa-camera"></i></div>
        <div class="db-kpi-body">
            <div class="db-kpi-value">{{ $monthStats['cameras'] }}</div>
            <div class="db-kpi-label">{{ __('ui.home.month_cameras') }}</div>
        </div>
    </a>
</div>

{{-- ================================================================
     RECENT FLIGHTS
     ================================================================ --}}
<div class="db-card">
    <div class="db-card-header ph-collapsible">
        <div class="db-card-title">{{ __('ui.home.recent_flights') }}</div>
        <a href="{{ route('flights.index') }}" class="db-view-all ml-auto">
            {{ __('ui.view_all') }} <i class="fas fa-arrow-right ml-1"></i>
        </a>
        <i class="fas fa-chevron-down ph-card-toggle ml-2"></i>
    </div>
    <div class="db-card-body p-0">
        <div class="db-flights-table-wrap">
            <table class="db-table">
                <thead>
                    <tr>
                        <th>{{ __('ui.flights.drone') }}</th>
                        <th>{{ __('ui.flights.date') }}</th>
                        <th>{{ __('ui.flights.duration') }}</th>
                        <th>{{ __('ui.flights.status') }}</th>
                        <th>{{ __('ui.home.view_route') }}</th>
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
                            <a href="{{ route('flights.show', $flight) }}" class="db-view-btn" title="{{ __('ui.home.view_route') }}">
                                <i class="fas fa-map-marked-alt"></i>
                            </a>
                        </td>
                    </tr>
                    @empty
                    <tr><td colspan="5" class="db-empty-row"><i class="fas fa-inbox mr-2"></i>{{ __('ui.home.no_recent_flights') }}</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
</div>

@include('partials.ai-chat-widget')

@endsection

@section('css')
<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css"/>
<style>
.content-wrapper > .content { padding-top: 1.5rem; }
.content-header { display: none; }
.leaflet-popup-content-wrapper { background: #0d2460 !important; color: #fff !important; border-radius: 10px !important; }
.leaflet-popup-tip { background: #0d2460 !important; }

/* ── Greeting + weather ──────────────────────────────────────── */
.ph-header {
    display: flex;
    align-items: stretch;
    justify-content: space-between;
    gap: 1rem;
    flex-wrap: wrap;
}
.ph-greeting { display: flex; flex-direction: column; justify-content: center; }
.ph-greeting-title { font-size: 1.5rem; font-weight: 700; color: #fff; }
.ph-greeting-sub { font-size: 0.85rem; color: rgba(255,255,255,0.5); margin-top: 4px; }
.ph-greeting-dot { margin: 0 6px; color: rgba(255,255,255,0.25); }

.ph-weather {
    display: flex;
    align-items: center;
    gap: 0.9rem;
    background: var(--navy-card);
    border: 1px solid rgba(255,255,255,0.08);
    border-left: 4px solid rgba(255,255,255,0.2);
    border-radius: 12px;
    padding: 0.9rem 1.3rem;
    min-width: 300px;
}
.ph-weather-green  { border-left-color: #28a745; }
.ph-weather-yellow { border-left-color: #f0c040; }
.ph-weather-red    { border-left-color: #dc3545; }
.ph-weather-icon   { font-size: 1.8rem; color: rgba(255,255,255,0.7); flex-shrink: 0; }
.ph-weather-green  .ph-weather-icon { color: #7ddc9f; }
.ph-weather-yellow .ph-weather-icon { color: var(--gold); }
.ph-weather-red    .ph-weather-icon { color: #f5a0a0; }
.ph-weather-top { display: flex; align-items: baseline; gap: 0.6rem; }
.ph-weather-temp { font-size: 1.3rem; font-weight: 700; color: #fff; }
.ph-weather-level { font-size: 0.8rem; font-weight: 600; color: rgba(255,255,255,0.7); }
.ph-weather-green  .ph-weather-level { color: #7ddc9f; }
.ph-weather-yellow .ph-weather-level { color: var(--gold); }
.ph-weather-red    .ph-weather-level { color: #f5a0a0; }
.ph-weather-sub { font-size: 0.75rem; color: rgba(255,255,255,0.4); margin-top: 2px; }

/* ── Checkout widget (shared with admin dashboard) ──────────────── */
.db-checkout-active {
    display: flex; align-items: center; gap: 1.2rem;
    background: linear-gradient(135deg, rgba(40,167,69,0.12), rgba(40,167,69,0.04));
    border: 1px solid rgba(40,167,69,0.3);
    border-radius: 12px; padding: 1rem 1.4rem;
}
.db-checkout-icon {
    width: 48px; height: 48px; border-radius: 10px;
    background: rgba(40,167,69,0.2); color: #7ddc9f;
    display: flex; align-items: center; justify-content: center; font-size: 1.3rem; flex-shrink: 0;
}
.db-checkout-label { font-size: 0.72rem; color: rgba(255,255,255,0.45); text-transform: uppercase; letter-spacing: 0.5px; }
.db-checkout-drone { font-size: 1rem; font-weight: 600; color: #fff; }
.db-checkout-serial { font-size: 0.75rem; color: rgba(255,255,255,0.4); margin-left: 6px; }
.db-checkout-since { font-size: 0.75rem; color: rgba(255,255,255,0.4); margin-top: 2px; }
.db-checkin-btn {
    background: rgba(240,192,64,0.15); border: 1px solid rgba(240,192,64,0.4); color: var(--gold);
    border-radius: 9px; padding: 7px 18px; font-size: 0.82rem; font-weight: 600; cursor: pointer;
    white-space: nowrap; transition: background .15s;
}
.db-checkin-btn:hover { background: rgba(240,192,64,0.28); }
.db-checkout-card { background: var(--navy-card); border: 1px solid rgba(240,192,64,0.2); border-radius: 12px; padding: 1rem 1.4rem; }
.db-checkout-card-header { font-size: 0.9rem; font-weight: 600; color: rgba(255,255,255,0.85); }
.db-checkout-form { display: flex; gap: 0.6rem; flex-wrap: wrap; }
.db-select { background: rgba(255,255,255,0.07) !important; border-color: rgba(255,255,255,0.12) !important; color: #fff !important; border-radius: 9px !important; }
.db-checkout-btn {
    background: rgba(240,192,64,0.15); border: 1px solid rgba(240,192,64,0.4); color: var(--gold);
    border-radius: 9px; padding: 6px 18px; font-size: 0.85rem; font-weight: 600; cursor: pointer; white-space: nowrap;
}
.db-checkout-btn:hover { background: rgba(240,192,64,0.28); }

/* ── Maintenance alert rows ──────────────────────────────────── */
.ph-alert-row {
    display: flex; align-items: center; gap: 0.9rem;
    padding: 0.75rem 1.4rem;
    border-bottom: 1px solid rgba(255,255,255,0.05);
    color: rgba(255,255,255,0.8); text-decoration: none; font-size: 0.85rem;
    transition: background .15s;
}
.ph-alert-row:last-child { border-bottom: none; }
.ph-alert-row:hover { background: rgba(255,255,255,0.03); color: #fff; text-decoration: none; }
.ph-alert-badge { flex-shrink: 0; }
.ph-alert-drone { font-weight: 600; color: #fff; flex-shrink: 0; }
.ph-alert-desc { color: rgba(255,255,255,0.5); overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
.db-empty-row { text-align: center; color: rgba(255,255,255,0.3); padding: 1.4rem !important; }

/* ── Section title ───────────────────────────────────────────── */
.ph-section-title { font-size: 0.82rem; font-weight: 600; color: rgba(255,255,255,0.5); text-transform: uppercase; letter-spacing: 0.6px; }

/* ── Quick actions ───────────────────────────────────────────── */
.ph-quick-grid {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 1rem;
}
@media(max-width:576px) { .ph-quick-grid { grid-template-columns: 1fr; } }

.ph-quick-tile {
    display: flex; flex-direction: column; align-items: flex-start; gap: 0.6rem;
    background: var(--navy-card); border: 1px solid rgba(255,255,255,0.06); border-radius: 12px;
    padding: 1.2rem 1.3rem; text-decoration: none !important; cursor: pointer;
    transition: transform .2s, box-shadow .2s; text-align: left;
}
.ph-quick-tile:hover { transform: translateY(-3px); box-shadow: 0 8px 24px rgba(0,0,0,0.35); text-decoration: none !important; }
.ph-quick-icon {
    width: 44px; height: 44px; border-radius: 10px;
    display: flex; align-items: center; justify-content: center; font-size: 1.2rem;
}
.ph-quick-label { font-size: 0.9rem; font-weight: 600; color: #fff; }

/* ── AI assistant widget (top row, next to weather) ───────────── */
.ph-ai-widget {
    display: flex; align-items: center; gap: 0.9rem;
    background: linear-gradient(135deg, rgba(240,192,64,0.18), rgba(240,192,64,0.04));
    border: 1px solid rgba(240,192,64,0.45);
    border-radius: 12px;
    padding: 0.9rem 1.3rem;
    min-width: 300px;
    cursor: pointer;
    transition: transform .2s, box-shadow .2s;
}
.ph-ai-widget:hover { transform: translateY(-2px); box-shadow: 0 8px 22px rgba(240,192,64,0.18); }
.ph-ai-widget-icon { font-size: 1.9rem; color: var(--gold); flex-shrink: 0; }
.ph-ai-widget-body { text-align: left; }
.ph-ai-widget-title { font-size: 0.95rem; font-weight: 700; color: #fff; }
.ph-ai-widget-sub { font-size: 0.75rem; color: rgba(255,255,255,0.6); margin-top: 2px; line-height: 1.35; max-width: 260px; }

/* ── KPI grid (month stats) ──────────────────────────────────── */
.db-kpi-grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 1rem; }
@media(max-width:768px) { .db-kpi-grid { grid-template-columns: 1fr; } }

/* ── Flights table (shared with admin dashboard) ────────────── */
.db-flights-table-wrap { overflow-x: auto; }
.db-table { width: 100%; border-collapse: collapse; font-size: 0.85rem; }
.db-table thead th {
    padding: 0.6rem 1rem; color: rgba(255,255,255,0.35); font-size: 0.72rem; text-transform: uppercase;
    letter-spacing: 0.6px; font-weight: 600; border-bottom: 1px solid rgba(255,255,255,0.06); white-space: nowrap;
}
.db-table tbody tr { border-bottom: 1px solid rgba(255,255,255,0.04); transition: background .15s; }
.db-table tbody tr:hover { background: rgba(255,255,255,0.03); }
.db-table tbody td { padding: 0.65rem 1rem; color: rgba(255,255,255,0.8); vertical-align: middle; }
.db-drone-cell { display: flex; align-items: center; gap: 0.5rem; }
.db-drone-dot { width: 7px; height: 7px; border-radius: 50%; background: var(--gold); flex-shrink: 0; }
.db-date-cell small { display: block; color: rgba(255,255,255,0.35); font-size: 0.72rem; }
.db-status { display: inline-block; padding: 2px 10px; border-radius: 20px; font-size: 0.72rem; font-weight: 600; letter-spacing: 0.3px; }
.db-status-completed { background: rgba(40,167,69,0.2); color: #7ddc9f; }
.db-status-aborted   { background: rgba(220,53,69,0.2); color: #f5a0a0; }
.db-view-btn {
    display: inline-flex; align-items: center; justify-content: center; width: 28px; height: 28px; border-radius: 7px;
    background: rgba(23,162,184,0.15); color: #7dd3f5; text-decoration: none; font-size: 0.8rem; transition: background .15s;
}
.db-view-btn:hover { background: rgba(23,162,184,0.3); color: #fff; text-decoration: none; }

/* ── Cameras / AI cards shared layout ─────────────────────────── */
.ph-camera-layout {
    display: grid;
    grid-template-columns: 1fr 1.3fr;
    gap: 0;
}
@media(max-width:768px) { .ph-camera-layout { grid-template-columns: 1fr; } }

.ph-camera-stats {
    display: flex; gap: 1.6rem;
    padding: 1.1rem 1.4rem 0.6rem;
}
.ph-camera-stat { display: flex; flex-direction: column; }
.ph-camera-stat-value { font-size: 1.6rem; font-weight: 700; line-height: 1; }
.ph-camera-stat-label { font-size: 0.7rem; color: rgba(255,255,255,0.4); text-transform: uppercase; letter-spacing: 0.5px; margin-top: 3px; }

.ph-camera-list { padding: 0.4rem 0 0.8rem; }
.ph-camera-row {
    display: flex; align-items: center; gap: 0.6rem;
    padding: 0.45rem 1.4rem;
    color: rgba(255,255,255,0.8); text-decoration: none; font-size: 0.83rem;
    transition: background .15s;
}
.ph-camera-row:hover { background: rgba(255,255,255,0.03); color: #fff; text-decoration: none; }
.ph-camera-dot { width: 8px; height: 8px; border-radius: 50%; flex-shrink: 0; }
.ph-camera-name { font-weight: 600; color: #fff; flex-shrink: 0; }
.ph-camera-loc { color: rgba(255,255,255,0.4); font-size: 0.76rem; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }

.ph-mini-map {
    height: 240px;
    border-radius: 14px;
    overflow: hidden;
    border: 1px solid rgba(255,255,255,0.1);
    margin: 1.1rem 1.4rem 1.1rem 0;
}
@media(max-width:768px) { .ph-mini-map { margin: 0 1.4rem 1.1rem; } }

/* ── Leaflet zoom control — match theme ───────────────────────── */
.leaflet-control-zoom {
    border: 1px solid rgba(255,255,255,0.15) !important;
    border-radius: 8px !important;
    overflow: hidden;
    box-shadow: 0 2px 8px rgba(0,0,0,0.3) !important;
}
.leaflet-control-zoom a {
    background: #0d2460 !important;
    color: rgba(255,255,255,0.85) !important;
    border-color: rgba(255,255,255,0.12) !important;
}
.leaflet-control-zoom a:hover { background: #1a3060 !important; color: var(--gold) !important; }

/* ── Collapsible cards ─────────────────────────────────────────── */
.ph-collapsible { cursor: pointer; user-select: none; }
.ph-card-toggle { color: rgba(255,255,255,0.35); font-size: 0.8rem; transition: transform .25s; flex-shrink: 0; }
.db-card.ph-collapsed > *:not(.db-card-header) { display: none !important; }
.db-card.ph-collapsed .ph-card-toggle { transform: rotate(-90deg); }

/* ── AI card ───────────────────────────────────────────────────── */
.ph-ai-card { border: 1px solid rgba(240,192,64,0.2); }
.ph-ai-lists { padding: 0.6rem 0 0.8rem; }
.ph-ai-list-title { padding: 0.4rem 1.4rem 0.3rem; font-size: 0.72rem; color: rgba(255,255,255,0.5); text-transform: uppercase; letter-spacing: 0.5px; }
.ph-ai-row {
    display: flex; align-items: center; gap: 0.6rem;
    padding: 0.4rem 1.4rem;
    color: rgba(255,255,255,0.75); font-size: 0.8rem;
}
.ph-ai-dot { width: 8px; height: 8px; border-radius: 50%; flex-shrink: 0; }
.ph-ai-risk { margin-left: auto; font-weight: 600; color: #f5a0a0; font-size: 0.75rem; white-space: nowrap; }
.ph-ai-footer { padding: 0.8rem 1.4rem; border-top: 1px solid rgba(255,255,255,0.06); }
.ph-ai-open-btn {
    background: rgba(240,192,64,0.15); border: 1px solid rgba(240,192,64,0.4); color: var(--gold);
    border-radius: 9px; padding: 6px 16px; font-size: 0.82rem; font-weight: 600; cursor: pointer;
}
.ph-ai-open-btn:hover { background: rgba(240,192,64,0.28); }
</style>
@endsection

@php
    $camPoints = $cameras->filter(fn($c) => $c->latitude)->map(fn($c) => [
        'lat' => (float) $c->latitude, 'lon' => (float) $c->longitude,
        'name' => $c->name, 'location' => $c->location_name, 'active' => (bool) $c->is_active,
    ])->values();
@endphp

@section('js')
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
<script>
document.getElementById('phOpenAiChat')?.addEventListener('click', function () {
    document.getElementById('aiChatToggle')?.click();
});
document.getElementById('phOpenAiChatFromRisk')?.addEventListener('click', function () {
    document.getElementById('aiChatToggle')?.click();
});

// ── Collapsible cards ─────────────────────────────────────────────
const phMiniMaps = [];
document.querySelectorAll('.ph-collapsible').forEach(header => {
    header.addEventListener('click', function (e) {
        if (e.target.closest('a, button, form, input, select')) return;
        const card = header.closest('.db-card');
        card.classList.toggle('ph-collapsed');
        if (!card.classList.contains('ph-collapsed')) {
            setTimeout(() => phMiniMaps.forEach(m => m.invalidateSize()), 220);
        }
    });
});

@if($station && $station->latitude)
// ── Cameras mini map ─────────────────────────────────────────────
@if($cameras->isNotEmpty())
(function () {
    const map = L.map('camerasMiniMap', { zoomControl: true, attributionControl: false, scrollWheelZoom: false })
        .setView([{{ $station->latitude }}, {{ $station->longitude }}], 11);
    phMiniMaps.push(map);
    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', { maxZoom: 19 }).addTo(map);

@if($station->hasBoundary())
    L.polygon(@json($station->boundary), {
        color: '#f0c040', weight: 2, fillColor: '#f0c040', fillOpacity: 0.08,
    }).addTo(map);
@else
    L.circle([{{ $station->latitude }}, {{ $station->longitude }}], {
        radius: {{ \App\Models\BorderPoliceStation::TERRITORY_RADIUS_KM * 1000 }},
        color: '#17a2b8', weight: 2, dashArray: '6,6', opacity: 0.7,
        fillColor: '#17a2b8', fillOpacity: 0.05,
    }).addTo(map);
@endif

    const camPoints = @json($camPoints);

    const markers = camPoints.map(c => {
        const color = c.active ? '#dc3545' : '#6c757d';
        return L.circleMarker([c.lat, c.lon], { radius: 7, color, fillColor: color, fillOpacity: 0.85, weight: 2 })
            .bindPopup(`<strong>${c.name}</strong><br>${c.location}`)
            .addTo(map);
    });

    if (markers.length) {
        map.fitBounds(L.featureGroup(markers).getBounds().pad(0.3));
    }
})();
@endif

// ── AI risk mini map ─────────────────────────────────────────────
@if($aiSummary && !isset($aiSummary['message']) && (count($aiSummary['increase']) || count($aiSummary['decrease'])))
(function () {
    const map = L.map('aiMiniMap', { zoomControl: true, attributionControl: false, scrollWheelZoom: false })
        .setView([{{ $station->latitude }}, {{ $station->longitude }}], 10);
    phMiniMaps.push(map);
    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', { maxZoom: 19 }).addTo(map);

    const points = [
        ...(@json($aiSummary['increase'])).map(c => ({ ...c, kind: 'increase' })),
        ...(@json($aiSummary['decrease'])).map(c => ({ ...c, kind: 'decrease' })),
    ];

    const markers = points.map(p => {
        const color = p.kind === 'increase' ? '#dc3545' : '#28a745';
        const label = p.kind === 'increase' ? '{{ __('ui.home.ai_risk_increase') }}' : '{{ __('ui.home.ai_risk_decrease') }}';
        return L.circleMarker([p.lat, p.lon], { radius: 7, color, fillColor: color, fillOpacity: 0.85, weight: 2 })
            .bindPopup(`<strong>${label}</strong><br>{{ __('ui.home.risk_label') }}: ${p.risk}`)
            .addTo(map);
    });

    if (markers.length) {
        map.fitBounds(L.featureGroup(markers).getBounds().pad(0.3));
    }
})();
@endif
@endif
</script>
@endsection
