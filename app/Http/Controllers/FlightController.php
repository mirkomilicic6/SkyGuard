<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\StationScoped;
use App\Models\Drone;
use App\Models\DroneCheckout;
use App\Models\Flight;
use App\Models\GpxPoint;
use App\Models\User;
use App\Notifications\FlightUploaded;
use App\Services\GpxParser;
use Illuminate\Http\Request;

class FlightController extends Controller
{
    use StationScoped;

    public function index(Request $request)
    {
        $dateFrom = $request->input('date_from');
        $dateTo   = $request->input('date_to');

        $flights = $this->flightScope()
            ->with(['drone', 'pilot', 'station.administration'])
            ->when($dateFrom, fn($q) => $q->whereDate('flight_date', '>=', $dateFrom))
            ->when($dateTo,   fn($q) => $q->whereDate('flight_date', '<=', $dateTo))
            ->latest('flight_date')
            ->paginate(15)
            ->appends($request->query());

        $showStationColumn = $this->showStationColumn();

        return view('flights.index', compact('flights', 'dateFrom', 'dateTo', 'showStationColumn'));
    }

    public function create()
    {
        $this->denyViewer();
        $drones = $this->availableDrones();
        return view('flights.create', compact('drones'));
    }

    public function store(Request $request)
    {
        $this->denyViewer();

        /** @var \App\Models\User $pilot */
        $pilot = auth()->user();

        $validated = $request->validate([
            'drone_id' => 'required|exists:drones,id',
            'location' => 'nullable|string|max:255',
            'purpose'  => 'nullable|string',
            'gpx_file' => 'required|file|mimes:gpx,xml|max:10240',
        ]);

        $this->authorizeDrone($validated['drone_id']);

        $storagePath = $request->file('gpx_file')->store('gpx', 'public');

        $flight = Flight::create([
            'drone_id'      => $validated['drone_id'],
            'user_id'       => $pilot->id,
            'station_id'    => $pilot->station_id,
            'location'      => $validated['location'] ?? null,
            'purpose'       => $validated['purpose'] ?? null,
            'gpx_file_path' => $storagePath,
            'flight_date'   => now(),
            'status'        => 'completed',
        ]);

        $this->processGpxFile($flight, $storagePath);

        $flight->load('drone');
        $admins  = User::role('admin')->get();
        $viewers = User::role('viewer')->where('station_id', $pilot->station_id)->get();
        $admins->merge($viewers)->each(fn($u) => $u->notify(new FlightUploaded($flight, $pilot)));

        return redirect()->route('flights.index')->with('success', 'Let uspješno dodan.');
    }

    public function show(Flight $flight)
    {
        $this->authorizeAccess($flight);

        $flight->load(['drone', 'pilot', 'gpxPoints']);
        return view('flights.show', compact('flight'));
    }

    public function edit(Flight $flight)
    {
        $this->denyViewer();
        $this->authorizeAccess($flight);

        $drones = $this->availableDrones();
        return view('flights.edit', compact('flight', 'drones'));
    }

    public function update(Request $request, Flight $flight)
    {
        $this->denyViewer();
        $this->authorizeAccess($flight);

        $validated = $request->validate([
            'drone_id' => 'required|exists:drones,id',
            'location' => 'nullable|string|max:255',
            'purpose'  => 'nullable|string',
            'gpx_file' => 'nullable|file|mimes:gpx,xml|max:10240',
        ]);

        $this->authorizeDrone($validated['drone_id']);

        $flight->update([
            'drone_id' => $validated['drone_id'],
            'location' => $validated['location'] ?? null,
            'purpose'  => $validated['purpose'] ?? null,
        ]);

        if ($request->hasFile('gpx_file')) {
            $path = $request->file('gpx_file')->store('gpx', 'public');
            $flight->update(['gpx_file_path' => $path]);
            $this->processGpxFile($flight, $path);
        }

        return redirect()->route('flights.show', $flight)->with('success', 'Flight updated successfully.');
    }

    public function destroy(Flight $flight)
    {
        if (!auth()->user()->hasRole('admin')) {
            abort(403, 'Samo admin može brisati letove.');
        }
        $this->authorizeAccess($flight);

        $flight->delete();
        return redirect()->route('flights.index')->with('success', 'Flight deleted.');
    }

    private function flightScope()
    {
        $user = auth()->user();

        if ($user->hasRole(['admin', 'viewer'])) {
            return $this->applyStationScope(Flight::query());
        }

        // Pilot sees own flights only
        return Flight::where('user_id', $user->id);
    }

    private function availableDrones()
    {
        $user = auth()->user();

        if ($user->hasRole('admin')) {
            $q = Drone::where('status', 'active');
            return $this->applyStationScope($q)->get();
        }

        // Pilot — only checked-out drone
        $checkout = DroneCheckout::where('user_id', $user->id)
            ->whereNull('checked_in_at')
            ->first();

        if ($checkout) {
            return Drone::where('id', $checkout->drone_id)->where('status', 'active')->get();
        }

        return collect();
    }

    private function authorizeAccess(Flight $flight): void
    {
        $user = auth()->user();

        if ($user->hasRole(['admin', 'viewer'])) {
            if (!$this->inUserStation($flight->station_id)) {
                abort(403);
            }
            return;
        }

        if ($flight->user_id !== $user->id) {
            abort(403, 'You do not have access to this flight.');
        }
    }

    private function authorizeDrone(int $droneId): void
    {
        $user = auth()->user();

        if ($user->hasRole('admin')) {
            return;
        }

        if (!$user->drones->contains($droneId)) {
            abort(403, 'You are not assigned to this drone.');
        }
    }

    private function processGpxFile(Flight $flight, string $storagePath): void
    {
        $absolutePath = storage_path('app/public/' . $storagePath);

        if (!file_exists($absolutePath)) {
            return;
        }

        $result = (new GpxParser())->parse($absolutePath);

        if (empty($result['points'])) {
            return;
        }

        $flight->gpxPoints()->delete();

        $rows = array_map(fn($p) => [
            'flight_id'   => $flight->id,
            'latitude'    => $p['lat'],
            'longitude'   => $p['lon'],
            'altitude'    => $p['alt'],
            'speed'       => $p['speed'],
            'timestamp'   => $p['time']?->format('Y-m-d H:i:s'),
            'point_order' => $p['order'],
            'created_at'  => now(),
            'updated_at'  => now(),
        ], $result['points']);

        GpxPoint::insert($rows);

        $firstTime = $result['points'][0]['time'] ?? null;
        if ($firstTime) {
            $flight->update(['flight_date' => $firstTime->format('Y-m-d H:i:s')]);
        }

        $stats = array_filter($result['stats'], fn($v) => $v !== null);
        if (!empty($stats)) {
            $flight->update($stats);
        }
    }
}
