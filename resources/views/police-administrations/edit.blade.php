@extends('adminlte::page')

@section('title', __('ui.police_administrations.edit_administration'))

@section('content_header')
    <h1>{{ __('ui.police_administrations.edit_administration') }}: {{ $administration->name }}
        <a href="{{ route('police-administrations.index') }}" class="btn btn-secondary btn-sm float-right">
            <i class="fas fa-arrow-left"></i> {{ __('ui.back') }}
        </a>
    </h1>
@endsection

@section('content')
<div class="card">
    <div class="card-body">
        <form action="{{ route('police-administrations.update', $administration) }}" method="POST">
            @csrf @method('PUT')
            <div class="form-group">
                <label>{{ __('ui.police_administrations.name') }} *</label>
                <input type="text" name="name" class="form-control @error('name') is-invalid @enderror"
                    value="{{ old('name', $administration->name) }}" required autofocus>
                @error('name')<span class="invalid-feedback">{{ $message }}</span>@enderror
            </div>
            <button type="submit" class="btn btn-primary">
                <i class="fas fa-save mr-1"></i>{{ __('ui.save') }}
            </button>
            <a href="{{ route('police-administrations.index') }}" class="btn btn-secondary">{{ __('ui.cancel') }}</a>
        </form>
    </div>
</div>
@endsection
