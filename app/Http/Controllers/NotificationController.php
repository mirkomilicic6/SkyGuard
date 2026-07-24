<?php

namespace App\Http\Controllers;

class NotificationController extends Controller
{
    public function data()
    {
        $user   = auth()->user();
        $unread = $user->unreadNotifications()->latest()->take(8)->get();
        $count  = $user->unreadNotifications()->count();

        $dropdown = '';
        foreach ($unread as $n) {
            $d   = $n->data;
            $ago = $n->created_at->diffForHumans();
            $url = route('notifications.redirect', $n->id);

            $type = $d['type'] ?? (isset($d['flight_id']) ? 'flight_uploaded' : 'fault_reported');

            if ($type === 'fault_reported') {
                $icon    = 'fas fa-wrench';
                $color   = '#f0c040';
                $title   = e($d['reporter_name']) . ' je prijavio kvar';
                $sub     = e($d['drone_name']) . ' &mdash; ' . e($d['fault_type']);
                $desc    = e($d['description'] ?? '');
            } elseif ($type === 'fault_accepted') {
                $icon    = 'fas fa-check-circle';
                $color   = '#28a745';
                $title   = 'Vaša prijava je prihvaćena';
                $sub     = e($d['drone_name']) . ' &mdash; ' . e($d['fault_type']);
                $desc    = 'Admin je uzeo vašu prijavu na razmatranje.';
            } elseif ($type === 'maintenance_started') {
                $icon    = 'fas fa-tools';
                $color   = '#fd7e14';
                $title   = 'Popravak je u tijeku';
                $sub     = e($d['drone_name']) . ' &mdash; ' . e($d['fault_type']);
                $desc    = 'Admin je uzeo kvar u rad.';
            } elseif ($type === 'maintenance_resolved') {
                $icon    = 'fas fa-check-double';
                $color   = '#20c997';
                $title   = 'Kvar je riješen';
                $sub     = e($d['drone_name']) . ' &mdash; ' . e($d['fault_type']);
                $desc    = 'Admin je završio popravak.';
            } else {
                $icon    = 'fas fa-route';
                $color   = '#4da6ff';
                $title   = e($d['pilot_name']) . ' je unio let';
                $loc     = !empty($d['location']) ? ' &mdash; ' . e($d['location']) : '';
                $sub     = e($d['drone_name']) . $loc;
                $desc    = e($d['flight_date'] ?? '');
            }

            $dropdown .= '
                <a href="' . $url . '" class="notif-item d-flex align-items-start p-3 border-bottom">
                    <div class="notif-icon mr-3 mt-1" style="color:' . $color . ';font-size:1.2rem;width:22px;text-align:center;flex-shrink:0">
                        <i class="' . $icon . '"></i>
                    </div>
                    <div style="flex:1;min-width:0">
                        <div class="notif-title font-weight-bold" style="font-size:.82rem;color:#1a1a2e;line-height:1.3">' . $title . '</div>
                        <div class="notif-sub" style="font-size:.78rem;color:#555;margin-top:1px">' . $sub . '</div>'
                        . ($desc ? '<div class="notif-desc" style="font-size:.75rem;color:#777;margin-top:2px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;max-width:220px">' . $desc . '</div>' : '') . '
                        <div class="notif-ago" style="font-size:.72rem;color:#aaa;margin-top:3px"><i class="fas fa-clock mr-1"></i>' . $ago . '</div>
                    </div>
                </a>';
        }

        if (!$dropdown) {
            $dropdown = '<p class="text-center text-muted py-3 my-0" style="font-size:.83rem">Nema novih obavijesti</p>';
        }

        return response()->json([
            'label'       => $count > 0 ? (string) $count : '',
            'label_color' => $count > 0 ? 'danger' : '',
            'dropdown'    => $dropdown,
        ]);
    }

    public function redirect(string $id)
    {
        $n = auth()->user()->notifications()->findOrFail($id);
        $n->markAsRead();

        $d = $n->data;
        if (isset($d['maintenance_id'])) {
            return redirect()->route('maintenance.show', $d['maintenance_id']);
        }
        if (isset($d['flight_id'])) {
            return redirect()->route('flights.show', $d['flight_id']);
        }
        return redirect()->route('dashboard');
    }

    public function markAllRead()
    {
        auth()->user()->unreadNotifications->markAsRead();
        return back();
    }

    public function history()
    {
        $notifications = auth()->user()
            ->notifications()
            ->latest()
            ->paginate(30);

        return view('notifications.history', compact('notifications'));
    }
}
