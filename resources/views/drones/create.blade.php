@extends('adminlte::page')

@section('title', __('ui.drone_form.add_title'))

@section('content_header')
    <h1>{{ __('ui.drone_form.add_title') }}</h1>
@endsection

@section('content')
<div class="card">
    <div class="card-body">
        <form action="{{ route('drones.store') }}" method="POST" enctype="multipart/form-data">
            @csrf
            <div class="row">
                <div class="col-md-6">
                    <div class="form-group">
                        <label>{{ __('ui.drone_form.name_required') }}</label>
                        <input type="text" name="name" class="form-control @error('name') is-invalid @enderror"
                            value="{{ old('name') }}" required>
                        @error('name')<span class="invalid-feedback">{{ $message }}</span>@enderror
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="form-group">
                        <label>{{ __('ui.drone_form.serial_required') }}</label>
                        <input type="text" name="serial_number" class="form-control @error('serial_number') is-invalid @enderror"
                            value="{{ old('serial_number') }}" required>
                        @error('serial_number')<span class="invalid-feedback">{{ $message }}</span>@enderror
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="form-group">
                        <label>{{ __('ui.drone_form.model_required') }}</label>
                        <input type="text" name="model" class="form-control @error('model') is-invalid @enderror"
                            value="{{ old('model') }}" required>
                        @error('model')<span class="invalid-feedback">{{ $message }}</span>@enderror
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="form-group">
                        <label>{{ __('ui.drones.manufacturer') }}</label>
                        <input type="text" name="manufacturer" class="form-control"
                            value="{{ old('manufacturer') }}">
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="form-group">
                        <label>{{ __('ui.drones.purchase_date') }}</label>
                        <input type="date" name="purchase_date" class="form-control"
                            value="{{ old('purchase_date') }}">
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="form-group">
                        <label>{{ __('ui.drone_form.status_required') }}</label>
                        <select name="status" class="form-control" required>
                            <option value="active" {{ old('status') === 'active' ? 'selected' : '' }}>{{ __('ui.drone_form.status_active') }}</option>
                            <option value="in_maintenance" {{ old('status') === 'in_maintenance' ? 'selected' : '' }}>{{ __('ui.drone_form.status_maint') }}</option>
                            <option value="retired" {{ old('status') === 'retired' ? 'selected' : '' }}>{{ __('ui.drone_form.status_retired') }}</option>
                        </select>
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="form-group">
                        <label>{{ __('ui.drone_form.photo') }}</label>
                        <input type="file" name="photo" class="form-control-file">
                    </div>
                </div>
                <div class="col-md-6">
                    <div class="form-group">
                        <label>{{ __('ui.drone_form.assign_pilots') }}</label>
                        @if($pilotStations->count() > 1)
                        <div class="form-row mb-2">
                            <div class="col-6">
                                <select id="pilot-filter-admin" class="form-control form-control-sm">
                                    <option value="">— {{ __('ui.drone_form.all_administrations') }} —</option>
                                    @foreach($pilotAdministrations as $adm)
                                        <option value="{{ $adm->id }}">{{ $adm->name }}</option>
                                    @endforeach
                                </select>
                            </div>
                            <div class="col-6">
                                <select id="pilot-filter-station" class="form-control form-control-sm">
                                    <option value="">— {{ __('ui.drone_form.all_stations') }} —</option>
                                    @foreach($pilotStations as $st)
                                        <option value="{{ $st->id }}" data-admin="{{ $st->police_administration_id }}">{{ $st->name }}</option>
                                    @endforeach
                                </select>
                            </div>
                        </div>
                        @endif
                        <select name="pilots[]" id="pilots-select" class="form-control @error('pilots') is-invalid @enderror" multiple required>
                            @foreach($pilots as $pilot)
                                <option value="{{ $pilot->id }}"
                                    data-admin="{{ $pilot->station?->police_administration_id }}"
                                    data-station="{{ $pilot->station_id }}"
                                    {{ in_array($pilot->id, old('pilots', [])) ? 'selected' : '' }}>
                                    {{ $pilot->name }}{{ $pilotStations->count() > 1 ? ' — ' . ($pilot->station->name ?? '') : '' }}
                                </option>
                            @endforeach
                        </select>
                        <small class="text-muted">{{ __('ui.drone_form.ctrl_hint') }}</small>
                        @error('pilots')<span class="invalid-feedback d-block">{{ $message }}</span>@enderror
                    </div>
                </div>
                <div class="col-12">
                    <div class="form-group">
                        <label>{{ __('ui.drones.notes') }}</label>
                        <textarea name="notes" class="form-control" rows="3">{{ old('notes') }}</textarea>
                    </div>
                </div>
            </div>
            <button type="submit" class="btn btn-primary">
                <i class="fas fa-save"></i> {{ __('ui.drone_form.save_drone') }}
            </button>
            <a href="{{ route('drones.index') }}" class="btn btn-secondary">{{ __('ui.cancel') }}</a>
        </form>
    </div>
</div>
@endsection

@if($pilotStations->count() > 1)
@section('js')
<script>
const pilotFilterAdmin   = document.getElementById('pilot-filter-admin');
const pilotFilterStation = document.getElementById('pilot-filter-station');
const pilotsSelect        = document.getElementById('pilots-select');

function syncPilotStationOptions() {
    const adminId = pilotFilterAdmin.value;
    Array.from(pilotFilterStation.options).forEach(opt => {
        if (!opt.value) return;
        opt.hidden = adminId !== '' && opt.dataset.admin != adminId;
    });
    if (adminId !== '' && pilotFilterStation.value &&
        pilotFilterStation.options[pilotFilterStation.selectedIndex]?.dataset.admin != adminId) {
        pilotFilterStation.value = '';
    }
}

function applyPilotFilter() {
    const adminId   = pilotFilterAdmin.value;
    const stationId = pilotFilterStation.value;
    Array.from(pilotsSelect.options).forEach(opt => {
        const matchesAdmin   = adminId === '' || opt.dataset.admin === adminId;
        const matchesStation = stationId === '' || opt.dataset.station === stationId;
        opt.hidden = !(matchesAdmin && matchesStation);
    });
}

pilotFilterAdmin.addEventListener('change', () => { syncPilotStationOptions(); applyPilotFilter(); });
pilotFilterStation.addEventListener('change', applyPilotFilter);
syncPilotStationOptions();
</script>
@endsection
@endif
