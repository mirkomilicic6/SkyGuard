@extends('adminlte::page')

@section('title', 'Pregled')

@section('content_header')
@endsection

@section('content')

<div class="an-kpi-grid mb-4">
    <div class="db-kpi-card" style="--accent:#0d6efd">
        <div class="db-kpi-icon" style="background:rgba(13,110,253,0.15);color:#9fc3ff"><i class="fas fa-crosshairs"></i></div>
        <div class="db-kpi-body">
            <div class="db-kpi-value">{{ $totalDetections }}</div>
            <div class="db-kpi-label">Detekcija</div>
            <div class="db-kpi-sub">{{ $totalEntities }} entiteta ukupno</div>
        </div>
    </div>
    <div class="db-kpi-card" style="--accent:#28a745">
        <div class="db-kpi-icon" style="background:rgba(40,167,69,0.15);color:#7ddc9f"><i class="fas fa-plane"></i></div>
        <div class="db-kpi-body">
            <div class="db-kpi-value">{{ $totalFlights }}</div>
            <div class="db-kpi-label">Letova</div>
            <div class="db-kpi-sub">{{ $totalHours }} sati nadzora</div>
        </div>
    </div>
    <div class="db-kpi-card db-kpi-gold">
        <div class="db-kpi-icon"><i class="fas fa-calendar-alt"></i></div>
        <div class="db-kpi-body">
            <div class="db-kpi-value">{{ $historyDays }}</div>
            <div class="db-kpi-label">Dana povijesti</div>
            <div class="db-kpi-sub">
                @if($firstDetection && $lastDetection)
                    {{ \Carbon\Carbon::parse($firstDetection)->format('d.m.Y') }} – {{ \Carbon\Carbon::parse($lastDetection)->format('d.m.Y') }}
                @else
                    nema detekcija
                @endif
            </div>
        </div>
    </div>
    <div class="db-kpi-card {{ $mlOnline ? '' : 'db-kpi-red' }}">
        <div class="db-kpi-icon" style="{{ $mlOnline ? 'background:rgba(40,167,69,0.15);color:#7ddc9f' : '' }}"><i class="fas fa-server"></i></div>
        <div class="db-kpi-body">
            <div class="db-kpi-value" style="{{ $mlOnline ? 'color:#28a745' : '' }}">{{ $mlOnline ? 'Online' : 'Offline' }}</div>
            <div class="db-kpi-label">ML servis</div>
            <div class="db-kpi-sub">{{ $zoneCount !== null ? $zoneCount.' DBSCAN zona formirano' : 'servis nedostupan' }}</div>
        </div>
    </div>
    <div class="db-kpi-card {{ $assistantOnline ? '' : 'db-kpi-red' }}">
        <div class="db-kpi-icon" style="{{ $assistantOnline ? 'background:rgba(40,167,69,0.15);color:#7ddc9f' : '' }}"><i class="fas fa-robot"></i></div>
        <div class="db-kpi-body">
            <div class="db-kpi-value" style="{{ $assistantOnline ? 'color:#28a745' : '' }}">{{ $assistantOnline ? 'Online' : 'Offline' }}</div>
            <div class="db-kpi-label">AI asistent</div>
            <div class="db-kpi-sub">{{ $assistantOnline ? 'spreman za razgovor' : 'API ključ nije konfiguriran' }}</div>
        </div>
    </div>
</div>

<div class="db-card mb-4">
    <div class="db-card-body" style="font-size:.88rem;color:rgba(255,255,255,.7);line-height:1.7">
        <i class="fas fa-info-circle mr-2" style="color:var(--gold)"></i>
        Ovo su temeljni podaci koji ulaze u AI/ML obradu — detekcije i letovi prikupljeni dronovima i kamerama. Za detaljnu
        raščlambu po vrsti, satu, danu i postaji, s kartom, pogledajte
        <a href="{{ route('analytics.index') }}" style="color:#f0c040">Analitiku</a>. Za DBSCAN žarišta, usporedbu ML modela,
        prediktivnu kartu i preporuke nadzora pogledajte <a href="{{ route('academic.index') }}" style="color:#f0c040">ML analizu</a>.
    </div>
</div>

@include('partials.ai-chat-widget')
@endsection

@section('css')
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
</style>
@endsection
