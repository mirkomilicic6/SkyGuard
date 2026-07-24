<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\StationScoped;
use App\Models\Drone;
use App\Models\MaintenanceLog;
use App\Models\User;
use App\Notifications\MaintenanceFaultReported;
use App\Notifications\MaintenanceAccepted;
use App\Notifications\MaintenanceStarted;
use App\Notifications\MaintenanceResolved;
use Illuminate\Http\Request;

class MaintenanceController extends Controller
{
    use StationScoped;

    public function index()
    {
        $logs = $this->maintenanceScope()
            ->with(['drone', 'reportedBy', 'station.administration'])
            ->latest()
            ->paginate(15);

        $showStationColumn = $this->showStationColumn();

        return view('maintenance.index', compact('logs', 'showStationColumn'));
    }

    public function create()
    {
        $this->denyViewer();
        $drones = $this->availableDrones();
        return view('maintenance.create', compact('drones'));
    }

    public function store(Request $request)
    {
        $this->denyViewer();
        $user = auth()->user();
        $isPilot = $user->hasRole('pilot');

        $rules = [
            'drone_id'    => 'required|exists:drones,id',
            'type'        => 'required|in:routine,repair,inspection,part_replacement',
            'description' => 'required|string',
        ];
        if (!$isPilot) {
            $rules['parts_replaced'] = 'nullable|string';
            $rules['cost']           = 'nullable|numeric';
        }

        $validated = $request->validate($rules);
        $this->authorizeDrone($validated['drone_id']);

        $validated['reported_by'] = $user->id;
        $validated['station_id']  = $user->station_id;
        $validated['status']      = $isPilot ? MaintenanceLog::STATUS_PENDING : MaintenanceLog::STATUS_OPEN;

        if (!$isPilot) {
            $validated['parts_replaced'] = $request->parts_replaced;
            $validated['cost']           = $request->cost;
        }

        $drone = Drone::find($validated['drone_id']);
        $log   = MaintenanceLog::create($validated);
        $this->syncDroneStatus($drone);

        if ($isPilot) {
            $admins  = User::role('admin')->get();
            $viewers = User::role('viewer')->where('station_id', $user->station_id)->get();
            $admins->merge($viewers)->each(fn($u) => $u->notify(new MaintenanceFaultReported($log, $user)));
        }

        return redirect()->route('maintenance.index')->with('success', __('ui.maintenance.created'));
    }

    public function show(MaintenanceLog $maintenance)
    {
        $this->authorizeAccess($maintenance);
        $maintenance->load(['drone', 'reportedBy', 'resolvedBy']);
        return view('maintenance.show', compact('maintenance'));
    }

    public function edit(MaintenanceLog $maintenance)
    {
        $this->denyViewer();
        $this->authorizeAccess($maintenance);
        $user = auth()->user();

        // Pilot can only edit their own pending_review logs
        if ($user->hasRole('pilot')) {
            if ($maintenance->reported_by !== $user->id || !$maintenance->isPendingReview()) {
                abort(403, __('ui.maintenance.pilot_edit_denied'));
            }
        }

        $drones = $this->availableDrones();
        return view('maintenance.edit', compact('maintenance', 'drones'));
    }

    public function update(Request $request, MaintenanceLog $maintenance)
    {
        $this->denyViewer();
        $this->authorizeAccess($maintenance);
        $user = auth()->user();
        $isPilot = $user->hasRole('pilot');

        if ($isPilot) {
            if ($maintenance->reported_by !== $user->id || !$maintenance->isPendingReview()) {
                abort(403, __('ui.maintenance.pilot_edit_denied'));
            }
            $validated = $request->validate([
                'type'        => 'required|in:routine,repair,inspection,part_replacement',
                'description' => 'required|string',
            ]);
            $maintenance->update($validated);
        } else {
            $validated = $request->validate([
                'drone_id'       => 'required|exists:drones,id',
                'type'           => 'required|in:routine,repair,inspection,part_replacement',
                'description'    => 'required|string',
                'parts_replaced' => 'nullable|string',
                'cost'           => 'nullable|numeric',
            ]);
            $this->authorizeDrone($validated['drone_id']);

            if ($request->filled('status')) {
                abort(422, 'Use workflow actions to change status.');
            }

            $maintenance->update($validated);
            $this->syncDroneStatus($maintenance->drone);
        }

        return redirect()->route('maintenance.show', $maintenance)->with('success', __('ui.maintenance.updated'));
    }

    public function destroy(MaintenanceLog $maintenance)
    {
        $this->denyViewer();
        $this->authorizeAccess($maintenance);
        $user = auth()->user();

        if ($user->hasRole('pilot')) {
            if ($maintenance->reported_by !== $user->id || !$maintenance->isPendingReview()) {
                abort(403);
            }
        }

        $drone = $maintenance->drone;
        $maintenance->delete();
        $this->syncDroneStatus($drone);

        return redirect()->route('maintenance.index')->with('success', __('ui.maintenance.deleted'));
    }

    // ── Workflow actions (admin only) ─────────────────────────────────────────

    public function accept(MaintenanceLog $maintenance)
    {
        $this->denyNonAdmin();
        $this->authorizeAccess($maintenance);

        if (!$maintenance->isPendingReview()) {
            return back()->with('error', __('ui.maintenance.invalid_transition'));
        }

        $maintenance->update(['status' => MaintenanceLog::STATUS_OPEN]);

        $maintenance->reportedBy?->notify(new MaintenanceAccepted($maintenance));

        return back()->with('success', __('ui.maintenance.accepted'));
    }

    public function startWork(MaintenanceLog $maintenance)
    {
        $this->denyNonAdmin();
        $this->authorizeAccess($maintenance);

        if (!$maintenance->isOpen()) {
            return back()->with('error', __('ui.maintenance.invalid_transition'));
        }

        $maintenance->update(['status' => MaintenanceLog::STATUS_IN_PROGRESS]);

        $maintenance->reportedBy?->notify(new MaintenanceStarted($maintenance));

        return back()->with('success', __('ui.maintenance.started'));
    }

    public function resolve(MaintenanceLog $maintenance)
    {
        $this->denyNonAdmin();
        $this->authorizeAccess($maintenance);

        if (!$maintenance->isInProgress()) {
            return back()->with('error', __('ui.maintenance.invalid_transition'));
        }

        $maintenance->update([
            'status'      => MaintenanceLog::STATUS_RESOLVED,
            'resolved_by' => auth()->id(),
            'resolved_at' => now(),
        ]);

        $this->syncDroneStatus($maintenance->drone);

        $maintenance->reportedBy?->notify(new MaintenanceResolved($maintenance));

        return back()->with('success', __('ui.maintenance.resolved'));
    }

    // ── Private helpers ───────────────────────────────────────────────────────

    private function denyNonAdmin(): void
    {
        if (!auth()->user()->hasRole('admin')) {
            abort(403);
        }
    }

    private function maintenanceScope()
    {
        $user = auth()->user();

        if ($user->hasRole(['admin', 'viewer'])) {
            return $this->applyStationScope(MaintenanceLog::query());
        }

        // Pilot sees logs they reported + logs for their assigned drones
        $droneIds = $user->drones->pluck('id');
        return MaintenanceLog::where('reported_by', $user->id)
            ->orWhereIn('drone_id', $droneIds);
    }

    private function availableDrones()
    {
        $user = auth()->user();

        if ($user->hasRole('admin')) {
            return $this->applyStationScope(Drone::query())->get();
        }

        return $user->drones()->get();
    }

    private function authorizeAccess(MaintenanceLog $maintenance): void
    {
        $user = auth()->user();

        if ($user->hasRole(['admin', 'viewer'])) {
            if (!$this->inUserStation($maintenance->station_id)) {
                abort(403);
            }
            return;
        }

        $pilotDroneIds = $user->drones->pluck('id');
        if ($maintenance->reported_by !== $user->id && !$pilotDroneIds->contains($maintenance->drone_id)) {
            abort(403);
        }
    }

    private function syncDroneStatus(Drone $drone): void
    {
        if ($drone->status === 'retired') {
            return;
        }

        $hasBlocking = $drone->maintenanceLogs()
            ->whereIn('status', [
                MaintenanceLog::STATUS_PENDING,
                MaintenanceLog::STATUS_OPEN,
                MaintenanceLog::STATUS_IN_PROGRESS,
            ])
            ->exists();

        $drone->update(['status' => $hasBlocking ? 'in_maintenance' : 'active']);
    }

    private function authorizeDrone(int $droneId): void
    {
        $user = auth()->user();
        if ($user->hasRole('admin')) return;

        if (!$user->drones->contains($droneId)) {
            abort(403);
        }
    }
}
