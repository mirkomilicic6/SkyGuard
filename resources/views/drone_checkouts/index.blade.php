@extends('adminlte::page')

@section('title', __('ui.checkout.title'))

@section('content_header')
    <h1>{{ __('ui.checkout.title') }}</h1>
@endsection

@section('content')

{{-- Aktivna zaduženja --}}
<div class="card card-outline card-success">
    <div class="card-header">
        <h3 class="card-title">
            <i class="fas fa-circle text-success mr-1" style="font-size:.65rem;vertical-align:middle"></i>
            {{ __('ui.checkout.active') }}
            <span class="badge badge-success ml-1">{{ $active->count() }}</span>
        </h3>
    </div>
    <div class="card-body p-0">
        @if($active->isEmpty())
            <p class="text-muted text-center py-3 mb-0">{{ __('ui.checkout.no_active') }}</p>
        @else
        <table class="table table-sm table-hover mb-0">
            <thead class="thead-light">
                <tr>
                    <th>{{ __('ui.checkout.pilot') }}</th>
                    <th>{{ __('ui.checkout.drone') }}</th>
                    <th>{{ __('ui.checkout.checked_out_at') }}</th>
                    <th>{{ __('ui.flights.duration') }}</th>
                    <th>{{ __('ui.checkout.notes') }}</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                @foreach($active as $c)
                <tr>
                    <td><i class="fas fa-user mr-1 text-muted"></i>{{ $c->user->name }}</td>
                    <td><i class="fas fa-helicopter mr-1 text-muted"></i>{{ $c->drone->name }}
                        <small class="text-muted">({{ $c->drone->serial_number }})</small>
                    </td>
                    <td>{{ $c->checked_out_at->format('d.m.Y H:i') }}</td>
                    <td>{{ $c->checked_out_at->diffForHumans(null, true) }}</td>
                    <td><small>{{ $c->notes ?? '—' }}</small></td>
                    <td>
                        @unless(auth()->user()->hasRole('viewer'))
                        <form method="POST" action="{{ route('drone_checkouts.checkIn', $c) }}">
                            @csrf @method('PATCH')
                            <button class="btn btn-xs btn-warning" onclick="return confirm('{{ __('ui.checkout.check_in_confirm') }}')">
                                <i class="fas fa-sign-out-alt"></i> {{ __('ui.checkout.check_in') }}
                            </button>
                        </form>
                        @endunless
                    </td>
                </tr>
                @endforeach
            </tbody>
        </table>
        @endif
    </div>
</div>

{{-- Povijest --}}
<div class="card">
    <div class="card-header">
        <h3 class="card-title"><i class="fas fa-history mr-1"></i> {{ __('ui.checkout.history') }}</h3>
    </div>
    <div class="card-body p-0">
        @if($history->isEmpty())
            <p class="text-muted text-center py-3 mb-0">{{ __('ui.checkout.no_history') }}</p>
        @else
        <table class="table table-sm table-hover mb-0">
            <thead class="thead-light">
                <tr>
                    <th>{{ __('ui.checkout.pilot') }}</th>
                    <th>{{ __('ui.checkout.drone') }}</th>
                    <th>{{ __('ui.checkout.checked_out_at') }}</th>
                    <th>{{ __('ui.checkout.checked_in_at') }}</th>
                    <th>{{ __('ui.checkout.duration') }}</th>
                    <th>{{ __('ui.checkout.notes') }}</th>
                </tr>
            </thead>
            <tbody>
                @foreach($history as $c)
                <tr>
                    <td>{{ $c->user->name }}</td>
                    <td>{{ $c->drone->name }}
                        <small class="text-muted">({{ $c->drone->serial_number }})</small>
                    </td>
                    <td>{{ $c->checked_out_at->format('d.m.Y H:i') }}</td>
                    <td>{{ $c->checked_in_at->format('d.m.Y H:i') }}</td>
                    <td>
                        @php $min = $c->durationMinutes(); @endphp
                        @if($min !== null)
                            @if($min >= 60)
                                {{ intdiv($min, 60) }}h {{ $min % 60 }}min
                            @else
                                {{ $min }} min
                            @endif
                        @else
                            —
                        @endif
                    </td>
                    <td><small>{{ $c->notes ?? '—' }}</small></td>
                </tr>
                @endforeach
            </tbody>
        </table>
        <div class="p-3">
            {{ $history->links() }}
        </div>
        @endif
    </div>
</div>

@endsection
