@extends('adminlte::page')

@section('title', __('ui.users.title'))

@section('content_header')
    <h1>
        {{ $pilotView ? __('ui.users.station_crew') : __('ui.users.title') }}
        @if(!$pilotView && auth()->user()->hasRole('admin'))
        <a href="{{ route('users.create') }}" class="btn btn-primary btn-sm float-right">
            <i class="fas fa-plus"></i> {{ __('ui.users.add_user') }}
        </a>
        @endif
    </h1>
@endsection

@section('content')
@if(session('success'))
    <div class="alert alert-success">{{ session('success') }}</div>
@endif
@if(session('error'))
    <div class="alert alert-danger">{{ session('error') }}</div>
@endif

@if($pilotView)
{{-- ── Pilot view: simple crew list ──────────────────────────────────────── --}}
<div class="card">
    <div class="card-header">
        <span class="card-title">
            <i class="fas fa-shield-alt mr-2" style="color:var(--gold)"></i>
            {{ auth()->user()->station?->name ?? '' }}
        </span>
    </div>
    <div class="card-body p-0">
        <table class="table table-striped mb-0">
            <thead>
                <tr>
                    <th>{{ __('ui.users.name') }}</th>
                    <th>{{ __('ui.users.email') }}</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                @forelse($users as $user)
                <tr>
                    <td>
                        <strong>{{ $user->name }}</strong>
                        @if($user->id === auth()->id())
                            <span class="badge badge-secondary ml-1">{{ __('ui.you') }}</span>
                        @endif
                    </td>
                    <td>{{ $user->email }}</td>
                    <td>
                        <a href="{{ route('users.show', $user) }}" class="btn btn-xs btn-info">
                            <i class="fas fa-eye"></i>
                        </a>
                    </td>
                </tr>
                @empty
                <tr><td colspan="3" class="text-center">—</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>

@else
{{-- ── Admin / viewer: full user management ──────────────────────────────── --}}

@if($administrations)
<div class="card card-outline card-primary mb-3">
    <div class="card-body py-2">
        <form method="GET" action="{{ route('users.index') }}" class="form-inline" id="filter-form">
            <div class="form-group mr-2">
                <select name="administration_id" id="filter-admin" class="form-control form-control-sm">
                    <option value="">— Sva uprava —</option>
                    @foreach($administrations as $adm)
                        <option value="{{ $adm->id }}" {{ request('administration_id') == $adm->id ? 'selected' : '' }}
                            data-stations="{{ $adm->stations->map(fn($s)=>['id'=>$s->id,'name'=>$s->name])->toJson() }}">
                            {{ $adm->name }}
                        </option>
                    @endforeach
                </select>
            </div>
            <div class="form-group mr-2">
                <select name="station_id" id="filter-station" class="form-control form-control-sm">
                    <option value="">— Sve postaje —</option>
                    @foreach($administrations->flatMap->stations as $st)
                        <option value="{{ $st->id }}" {{ request('station_id') == $st->id ? 'selected' : '' }}
                            data-admin="{{ $st->police_administration_id }}">
                            {{ $st->name }}
                        </option>
                    @endforeach
                </select>
            </div>
            <div class="form-group mr-2">
                <select name="role" id="filter-role" class="form-control form-control-sm">
                    <option value="">— {{ __('ui.users.all_roles') }} —</option>
                    @foreach($roles as $r)
                        <option value="{{ $r->name }}" {{ request('role') === $r->name ? 'selected' : '' }}>
                            {{ ucfirst($r->name) }}
                        </option>
                    @endforeach
                </select>
            </div>
            <button type="submit" class="btn btn-sm btn-primary mr-1">
                <i class="fas fa-filter mr-1"></i> Filter
            </button>
            <a href="{{ route('users.index') }}" class="btn btn-sm btn-secondary">
                <i class="fas fa-times"></i>
            </a>
        </form>
    </div>
</div>
@endif

<div class="card">
    <div class="card-body p-0">
        <table class="table table-striped">
            <thead>
                <tr>
                    <th>{{ __('ui.users.name') }}</th>
                    <th>{{ __('ui.users.email') }}</th>
                    <th>{{ __('ui.users.role') }}</th>
                    <th>{{ __('ui.users.station') }}</th>
                    <th>{{ __('ui.users.flights') }}</th>
                    <th>{{ __('ui.users.maintenance') }}</th>
                    <th>{{ __('ui.users.joined') }}</th>
                    <th>{{ __('ui.users.actions') }}</th>
                </tr>
            </thead>
            <tbody>
                @forelse($users as $user)
                <tr>
                    <td>
                        <strong>{{ $user->name }}</strong>
                        @if($user->id === auth()->id())
                            <span class="badge badge-secondary ml-1">{{ __('ui.you') }}</span>
                        @endif
                    </td>
                    <td>{{ $user->email }}</td>
                    <td>
                        @foreach($user->roles as $role)
                            <span class="badge badge-{{ $role->name === 'admin' ? 'danger' : ($role->name === 'pilot' ? 'primary' : 'secondary') }}">
                                {{ ucfirst($role->name) }}
                            </span>
                        @endforeach
                    </td>
                    <td>
                        @if($user->station)
                            <small class="text-muted d-block">{{ $user->station->administration->name ?? '' }}</small>
                            {{ $user->station->name }}
                        @else
                            <span class="text-muted">—</span>
                        @endif
                    </td>
                    <td>{{ $user->flights_count }}</td>
                    <td>{{ $user->maintenance_logs_count }}</td>
                    <td>{{ $user->created_at->translatedFormat('d F Y') }}</td>
                    <td>
                        <a href="{{ route('users.show', $user) }}" class="btn btn-xs btn-info">
                            <i class="fas fa-eye"></i>
                        </a>
                        @if(auth()->user()->hasRole('admin'))
                        <a href="{{ route('users.edit', $user) }}" class="btn btn-xs btn-warning">
                            <i class="fas fa-edit"></i>
                        </a>
                        @if($user->id !== auth()->id())
                        <form action="{{ route('users.destroy', $user) }}" method="POST" style="display:inline"
                            onsubmit="return confirm('{{ __('ui.are_you_sure') }}')">
                            @csrf @method('DELETE')
                            <button class="btn btn-xs btn-danger"><i class="fas fa-trash"></i></button>
                        </form>
                        @endif
                        @endif
                    </td>
                </tr>
                @empty
                <tr><td colspan="8" class="text-center py-3">{{ __('ui.users.no_users') }}</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    @if(method_exists($users, 'links'))
    <div class="card-footer">{{ $users->links() }}</div>
    @endif
</div>
@endif
@endsection

@if(!($pilotView ?? false) && $administrations)
@section('js')
<script>
const adminSelect   = document.getElementById('filter-admin');
const stationSelect = document.getElementById('filter-station');

function syncStations() {
    const adminId = adminSelect.value;
    Array.from(stationSelect.options).forEach(opt => {
        if (!opt.value) return;
        opt.hidden = adminId !== '' && opt.dataset.admin != adminId;
    });
    if (adminId !== '' && stationSelect.value &&
        stationSelect.options[stationSelect.selectedIndex]?.dataset.admin != adminId) {
        stationSelect.value = '';
    }
}

adminSelect.addEventListener('change', syncStations);
syncStations();
</script>
@endsection
@endif
