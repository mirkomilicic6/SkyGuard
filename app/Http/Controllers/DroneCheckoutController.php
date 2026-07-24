<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\StationScoped;
use App\Models\Drone;
use App\Models\DroneCheckout;
use Illuminate\Http\Request;

class DroneCheckoutController extends Controller
{
    use StationScoped;

    public function index()
    {
        $stationId = auth()->user()->station_id;

        $baseQuery = fn() => DroneCheckout::with(['drone', 'user'])
            ->when($stationId, fn($q) => $q->whereHas('drone', fn($dq) => $dq->where('station_id', $stationId)));

        $active = $baseQuery()
            ->whereNull('checked_in_at')
            ->orderBy('checked_out_at', 'desc')
            ->get();

        $history = $baseQuery()
            ->whereNotNull('checked_in_at')
            ->orderBy('checked_out_at', 'desc')
            ->paginate(25);

        return view('drone_checkouts.index', compact('active', 'history'));
    }

    public function store(Request $request)
    {
        $this->denyViewer();

        $request->validate([
            'drone_id' => 'required|exists:drones,id',
            'notes'    => 'nullable|string|max:500',
        ]);

        $user = auth()->user();

        $existing = DroneCheckout::where('user_id', $user->id)
            ->whereNull('checked_in_at')
            ->with('drone')
            ->first();

        if ($existing) {
            return back()->with('error', 'Već imaš zadužen dron: ' . $existing->drone->name . '. Razduži ga prije nego zadužiš drugi.');
        }

        $alreadyOut = DroneCheckout::where('drone_id', $request->drone_id)
            ->whereNull('checked_in_at')
            ->with('user')
            ->first();

        if ($alreadyOut) {
            return back()->with('error', 'Dron je već zadužen od strane pilota: ' . $alreadyOut->user->name . '.');
        }

        if (!$user->drones->contains($request->drone_id)) {
            abort(403, 'Nisi dodijeljen ovom dronu.');
        }

        $drone = Drone::findOrFail($request->drone_id);
        if ($drone->status === 'in_maintenance') {
            return back()->with('error', 'Dron ' . $drone->name . ' je pod aktivnim kvarom ili održavanjem i ne može se zadužiti.');
        }

        DroneCheckout::create([
            'drone_id'       => $request->drone_id,
            'user_id'        => $user->id,
            'checked_out_at' => now(),
            'notes'          => $request->notes,
        ]);

        return back()->with('success', 'Dron uspješno zadužen. Sretno na smjeni!');
    }

    public function checkIn(DroneCheckout $checkout)
    {
        $this->denyViewer();

        if ($checkout->user_id !== auth()->id() && !auth()->user()->hasRole('admin')) {
            abort(403);
        }

        if ($checkout->checked_in_at) {
            return back()->with('error', 'Dron je već razadužen.');
        }

        $checkout->update(['checked_in_at' => now()]);

        return back()->with('success', 'Dron uspješno razadužen.');
    }
}
