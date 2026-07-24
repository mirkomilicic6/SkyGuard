@extends('adminlte::page')

@section('title', __('ui.user_form.add_title'))

@section('content_header')
    <h1>{{ __('ui.user_form.add_title') }}</h1>
@endsection

@section('content')
<div class="card">
    <div class="card-body">
        <form action="{{ route('users.store') }}" method="POST">
            @csrf
            <div class="row">
                <div class="col-md-6">
                    <div class="form-group">
                        <label>{{ __('ui.user_form.name_required') }}</label>
                        <input type="text" name="name" class="form-control @error('name') is-invalid @enderror"
                            value="{{ old('name') }}" required>
                        @error('name')<span class="invalid-feedback">{{ $message }}</span>@enderror
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="form-group">
                        <label>{{ __('ui.user_form.email_required') }}</label>
                        <input type="email" name="email" class="form-control @error('email') is-invalid @enderror"
                            value="{{ old('email') }}" required placeholder="ime.prezime@mup.hr">
                        @error('email')
                            <span class="invalid-feedback">{{ $message }}</span>
                        @else
                            <small class="form-text text-muted">{{ __('ui.user_form.email_domain_hint') }}</small>
                        @enderror
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="form-group">
                        <label>{{ __('ui.user_form.password_required') }}</label>
                        <input type="password" name="password" class="form-control @error('password') is-invalid @enderror" required>
                        @error('password')<span class="invalid-feedback">{{ $message }}</span>@enderror
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="form-group">
                        <label>{{ __('ui.user_form.role_required') }}</label>
                        <select name="role" class="form-control @error('role') is-invalid @enderror" required>
                            <option value="">{{ __('ui.user_form.select_role') }}</option>
                            @foreach($roles as $role)
                                <option value="{{ $role->name }}" {{ old('role') === $role->name ? 'selected' : '' }}>
                                    {{ ucfirst($role->name) }}
                                </option>
                            @endforeach
                        </select>
                        @error('role')<span class="invalid-feedback">{{ $message }}</span>@enderror
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="form-group">
                        <label>{{ __('ui.user_form.administration') }}</label>
                        <select id="administration_select" class="form-control">
                            <option value="">{{ __('ui.user_form.select_administration') }}</option>
                            @foreach($administrations as $admin)
                                <option value="{{ $admin->id }}" {{ old('administration_id') == $admin->id ? 'selected' : '' }}>
                                    {{ $admin->name }}
                                </option>
                            @endforeach
                        </select>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="form-group">
                        <label>{{ __('ui.user_form.station') }}</label>
                        <select id="station_select" name="station_id" class="form-control @error('station_id') is-invalid @enderror">
                            <option value="">{{ __('ui.user_form.select_station') }}</option>
                        </select>
                        @error('station_id')<span class="invalid-feedback">{{ $message }}</span>@enderror
                    </div>
                </div>
            </div>
            <button type="submit" class="btn btn-primary">
                <i class="fas fa-save"></i> {{ __('ui.user_form.create_user') }}
            </button>
            <a href="{{ route('users.index') }}" class="btn btn-secondary">{{ __('ui.cancel') }}</a>
        </form>
    </div>
</div>
@endsection

@push('js')
<script>
const stationsByAdmin = @json($administrations->mapWithKeys(fn($a) => [
    $a->id => $a->stations->map(fn($s) => ['id' => $s->id, 'name' => $s->name])
]));

const selectedStation = {{ old('station_id', 'null') }};

const adminSelect   = document.getElementById('administration_select');
const stationSelect = document.getElementById('station_select');

function populateStations(adminId, preselect) {
    stationSelect.innerHTML = '<option value="">{{ __('ui.user_form.select_station') }}</option>';
    const stations = stationsByAdmin[adminId] || [];
    stations.forEach(s => {
        const opt = document.createElement('option');
        opt.value = s.id;
        opt.textContent = s.name;
        if (preselect && preselect == s.id) opt.selected = true;
        stationSelect.appendChild(opt);
    });
}

adminSelect.addEventListener('change', () => populateStations(adminSelect.value, null));

// Restore on validation error
if (adminSelect.value) {
    populateStations(adminSelect.value, selectedStation);
}
</script>
@endpush
