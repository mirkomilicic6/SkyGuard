@extends('adminlte::page')

@section('title', __('ui.reports.title'))

@section('content_header')
    <h1>{{ __('ui.reports.title') }}</h1>
@endsection

@section('content')

<div class="card card-outline card-primary">
    <div class="card-header"><h3 class="card-title"><i class="fas fa-filter mr-1"></i> Filter izvještaja</h3></div>
    <div class="card-body">
        <form method="GET" action="{{ route('reports.index') }}" class="form-inline flex-wrap" style="gap:.75rem" id="report-form">

            {{-- Mjesec / Godina --}}
            <div class="form-group">
                <label class="mr-2 font-weight-bold">{{ __('ui.reports.month') }}</label>
                <select name="month" class="form-control form-control-sm">
                    @foreach(range(1, 12) as $m)
                        <option value="{{ $m }}" {{ $month == $m ? 'selected' : '' }}>
                            {{ \Carbon\Carbon::create(null, $m)->locale(app()->getLocale() === 'hr' ? 'hr' : 'en')->isoFormat('MMMM') }}
                        </option>
                    @endforeach
                </select>
            </div>
            <div class="form-group">
                <label class="mr-2 font-weight-bold">{{ __('ui.reports.year') }}</label>
                <select name="year" class="form-control form-control-sm">
                    @foreach(range(now()->year, now()->year - 3) as $y)
                        <option value="{{ $y }}" {{ $year == $y ? 'selected' : '' }}>{{ $y }}</option>
                    @endforeach
                </select>
            </div>

            {{-- Admin filteri (samo super admin) --}}
            @if($administrations)
            <select name="administration_id" id="filter-admin" class="form-control form-control-sm">
                <option value="">— Sve uprave —</option>
                @foreach($administrations as $adm)
                    <option value="{{ $adm->id }}"
                        {{ $adminId == $adm->id ? 'selected' : '' }}
                        data-stations="{{ $adm->stations->map(fn($s)=>['id'=>$s->id,'name'=>$s->name])->toJson() }}">
                        {{ $adm->name }}
                    </option>
                @endforeach
            </select>
            <select name="station_id" id="filter-station" class="form-control form-control-sm">
                <option value="">— Sve postaje —</option>
                @foreach($administrations->flatMap->stations as $st)
                    <option value="{{ $st->id }}"
                        {{ $stationId == $st->id ? 'selected' : '' }}
                        data-admin="{{ $st->police_administration_id }}">
                        {{ $st->name }}
                    </option>
                @endforeach
            </select>
            @endif

            {{-- Postaja filter za admina cijele uprave --}}
            @if($stations)
            <select name="station_id" class="form-control form-control-sm">
                <option value="">— Sve postaje —</option>
                @foreach($stations as $st)
                    <option value="{{ $st->id }}" {{ $stationId == $st->id ? 'selected' : '' }}>{{ $st->name }}</option>
                @endforeach
            </select>
            @endif

            <button type="submit" class="btn btn-primary btn-sm">
                <i class="fas fa-search mr-1"></i> {{ __('ui.generate') }}
            </button>
            <a href="{{ route('reports.index') }}" class="btn btn-secondary btn-sm">
                <i class="fas fa-times"></i>
            </a>
        </form>
    </div>
</div>

{{-- Naslov pregleda --}}
<div class="card">
    <div class="card-header d-flex align-items-center justify-content-between flex-wrap" style="gap:.5rem">
        <h3 class="card-title mb-0">
            <i class="fas fa-file-alt mr-1"></i>
            @if($isPilot)
                Moji letovi
            @elseif($selectedStation)
                {{ $selectedStation->administration->name ?? '' }} — {{ $selectedStation->name }}
            @elseif($selectedAdmin)
                {{ $selectedAdmin->name }} — sve postaje
            @elseif(!$isSuperAdmin && auth()->user()->hasRole('admin'))
                {{ auth()->user()->station->administration->name ?? '' }} — sve postaje
            @elseif(!$isSuperAdmin)
                {{ auth()->user()->station->name ?? '' }}
            @else
                Sve uprave i postaje
            @endif
            &mdash;
            {{ \Carbon\Carbon::create($year, $month, 1)->locale(app()->getLocale() === 'hr' ? 'hr' : 'en')->isoFormat('MMMM YYYY') }}
        </h3>
        <div class="d-flex align-items-center" style="gap:.5rem">
            <span class="badge badge-secondary" style="font-size:.8rem;padding:5px 10px">
                {{ $totals['flights'] }} letova · {{ $totals['minutes'] }} min
            </span>
            <a href="{{ route('reports.export', array_filter(['month'=>$month,'year'=>$year,'administration_id'=>$adminId,'station_id'=>$stationId])) }}"
               class="btn btn-success btn-sm">
                <i class="fas fa-file-word mr-1"></i> {{ __('ui.reports.export_word') }}
            </a>
        </div>
    </div>
    <div class="card-body p-0">
        <table class="table table-striped table-bordered mb-0" style="font-size:.88rem">
            <thead class="thead-dark">
                <tr>
                    <th>Datum</th>
                    @if($showStationColumn)
                    <th>Uprava / Postaja</th>
                    @endif
                    <th>Dron</th>
                    <th>Pilot</th>
                    <th>Lokacija</th>
                    <th class="text-center">Trajanje</th>
                    <th class="text-center">Status</th>
                </tr>
            </thead>
            <tbody>
                @forelse($flights as $fl)
                <tr>
                    <td class="text-nowrap">
                        <strong>{{ $fl->flight_date?->format('d.m.Y') }}</strong>
                        <small class="d-block text-muted">{{ $fl->flight_date?->format('H:i') }}</small>
                    </td>
                    @if($showStationColumn)
                    <td>
                        <small class="d-block text-muted">{{ $fl->station?->administration?->name ?? '' }}</small>
                        {{ $fl->station?->name ?? '—' }}
                    </td>
                    @endif
                    <td>{{ $fl->drone?->name ?? '—' }}</td>
                    <td>{{ $fl->pilot?->name ?? '—' }}</td>
                    <td>{{ $fl->location ?? '—' }}</td>
                    <td class="text-center">{{ $fl->duration_minutes ?? '—' }} min</td>
                    <td class="text-center">
                        <span class="badge badge-{{ match($fl->status) { 'completed'=>'success','aborted'=>'danger', default=>'secondary' } }}">
                            {{ __('ui.status.' . ($fl->status ?? 'unknown'), [], null) ?: ucfirst($fl->status ?? '—') }}
                        </span>
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="7" class="text-center text-muted py-4">
                        <i class="fas fa-inbox fa-2x d-block mb-2"></i>
                        Nema letova za odabrano razdoblje.
                    </td>
                </tr>
                @endforelse
            </tbody>
            @if($flights->isNotEmpty())
            <tfoot>
                <tr class="table-primary font-weight-bold">
                    <td colspan="{{ ($showStationColumn) ? 5 : 4 }}">Ukupno</td>
                    <td class="text-center">{{ $totals['minutes'] }} min</td>
                    <td class="text-center">{{ $totals['flights'] }} letova</td>
                </tr>
            </tfoot>
            @endif
        </table>
    </div>
    @if($flights->isNotEmpty())
    <div class="card-footer text-muted" style="font-size:.85rem">
        {{ __('ui.reports.generated_on') }} {{ now()->translatedFormat('d F Y H:i') }}
    </div>
    @endif
</div>

@endsection

@if($administrations)
@section('js')
<script>
const adminSel   = document.getElementById('filter-admin');
const stationSel = document.getElementById('filter-station');

function syncStations() {
    const aid = adminSel.value;
    Array.from(stationSel.options).forEach(opt => {
        if (!opt.value) return;
        opt.hidden = aid !== '' && opt.dataset.admin != aid;
    });
    if (aid !== '' && stationSel.value &&
        stationSel.options[stationSel.selectedIndex]?.dataset.admin != aid) {
        stationSel.value = '';
    }
}
adminSel.addEventListener('change', syncStations);
syncStations();
</script>
@endsection
@endif
