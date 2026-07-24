@extends('adminlte::page')

@section('title', 'Obavijesti')

@section('content_header')
    <h1>Obavijesti</h1>
@endsection

@section('content')
<div class="card">
    <div class="card-header d-flex align-items-center justify-content-between">
        <h3 class="card-title mb-0"><i class="far fa-bell mr-2"></i>Povijest obavijesti</h3>
        <a href="{{ route('notifications.markAllRead') }}" class="btn btn-sm btn-secondary">
            <i class="fas fa-check-double mr-1"></i> Označi sve kao pročitano
        </a>
    </div>
    <div class="card-body p-0">
        @forelse($notifications as $n)
        @php
            $d    = $n->data;
            $type = $d['type'] ?? (isset($d['flight_id']) ? 'flight_uploaded' : 'fault_reported');
            $read = $n->read_at !== null;

            [$icon, $color, $title, $sub, $desc] = match($type) {
                'fault_reported'       => ['fas fa-wrench',       '#f0c040', ($d['reporter_name'] ?? '?') . ' je prijavio kvar',  ($d['drone_name'] ?? '') . ' — ' . ($d['fault_type'] ?? ''), $d['description'] ?? ''],
                'fault_accepted'       => ['fas fa-check-circle', '#28a745', 'Vaša prijava je prihvaćena',                         ($d['drone_name'] ?? '') . ' — ' . ($d['fault_type'] ?? ''), 'Admin je uzeo vašu prijavu na razmatranje.'],
                'maintenance_started'  => ['fas fa-tools',        '#fd7e14', 'Popravak je u tijeku',                               ($d['drone_name'] ?? '') . ' — ' . ($d['fault_type'] ?? ''), 'Admin je uzeo kvar u rad.'],
                'maintenance_resolved' => ['fas fa-check-double', '#20c997', 'Kvar je riješen',                                    ($d['drone_name'] ?? '') . ' — ' . ($d['fault_type'] ?? ''), 'Admin je završio popravak.'],
                default                => ['fas fa-route',        '#4da6ff', ($d['pilot_name'] ?? '?') . ' je unio let',           ($d['drone_name'] ?? '') . (!empty($d['location']) ? ' — ' . $d['location'] : ''), $d['flight_date'] ?? ''],
            };

            $url = route('notifications.redirect', $n->id);
            $rowBg = $read ? 'transparent' : 'rgba(240,192,64,0.06)';
            $borderLeft = $read ? 'none' : '3px solid rgba(240,192,64,0.5)';
        @endphp
        <a href="{{ $url }}"
           class="d-flex align-items-start p-3 border-bottom text-decoration-none notif-row"
           style="background:{{ $rowBg }};border-left:{{ $borderLeft }};border-bottom-color:rgba(255,255,255,0.07)!important">
            <div class="mr-3 mt-1" style="color:{{ $color }};font-size:1.2rem;width:22px;text-align:center;flex-shrink:0">
                <i class="{{ $icon }}"></i>
            </div>
            <div class="flex-fill" style="min-width:0">
                <div style="font-size:.88rem;color:rgba(255,255,255,{{ $read ? '0.7' : '0.95' }});font-weight:{{ $read ? '400' : '600' }};line-height:1.3">
                    {{ $title }}
                    @if(!$read)
                        <span class="badge badge-warning ml-1" style="font-size:.6rem;vertical-align:middle">novo</span>
                    @endif
                </div>
                <div style="font-size:.8rem;color:rgba(255,255,255,0.5);margin-top:2px">{{ $sub }}</div>
                @if($desc)
                    <div style="font-size:.76rem;color:rgba(255,255,255,0.38);margin-top:2px">{{ $desc }}</div>
                @endif
                <div style="font-size:.72rem;color:rgba(255,255,255,0.3);margin-top:4px">
                    <i class="fas fa-clock mr-1"></i>{{ $n->created_at->diffForHumans() }}
                    @if($read)
                        &nbsp;&middot;&nbsp;<i class="fas fa-check mr-1"></i>pročitano
                    @endif
                </div>
            </div>
        </a>
        @empty
        <div class="text-center py-5" style="color:rgba(255,255,255,0.35)">
            <i class="far fa-bell-slash fa-2x d-block mb-2"></i>
            Nema obavijesti.
        </div>
        @endforelse
    </div>
    @if($notifications->hasPages())
    <div class="card-footer">
        {{ $notifications->links() }}
    </div>
    @endif
</div>
@endsection
