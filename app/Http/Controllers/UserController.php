<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\StationScoped;
use App\Models\PoliceAdministration;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;
use Spatie\Permission\Models\Role;

class UserController extends Controller
{
    use StationScoped;

    public function index(Request $request)
    {
        $authUser = auth()->user();

        if ($authUser->hasRole('pilot')) {
            $users = User::role('pilot')
                ->where('station_id', $authUser->station_id)
                ->with('station')
                ->orderBy('name')
                ->get();
            return view('users.index', compact('users'))
                ->with(['pilotView' => true, 'administrations' => null]);
        }

        $isSuperAdmin = $authUser->station_id === null;
        $query = $this->applyStationScope(User::query())
            ->with(['roles', 'station.administration'])
            ->withCount(['flights', 'maintenanceLogs']);

        if ($isSuperAdmin) {
            if ($request->filled('station_id')) {
                $query->where('station_id', $request->station_id);
            } elseif ($request->filled('administration_id')) {
                $query->whereHas('station', fn($q) =>
                    $q->where('police_administration_id', $request->administration_id)
                );
            }

            if ($request->filled('role')) {
                $query->role($request->role);
            }
        }

        $users = $query->latest()->paginate(15)->withQueryString();

        $administrations = $isSuperAdmin
            ? PoliceAdministration::with('stations')->orderBy('name')->get()
            : null;
        $roles = $isSuperAdmin ? Role::orderBy('name')->get() : null;

        return view('users.index', compact('users', 'administrations', 'roles'))->with('pilotView', false);
    }

    public function create()
    {
        $this->denyViewer();
        $roles = Role::all();
        $administrations = PoliceAdministration::with('stations')->orderBy('name')->get();
        return view('users.create', compact('roles', 'administrations'));
    }

    public function store(Request $request)
    {
        $this->denyViewer();

        $validated = $request->validate([
            'name'       => 'required|string|max:255',
            'email'      => 'required|email|unique:users|ends_with:@mup.hr',
            'password'   => ['required', Password::min(8)],
            'role'       => 'required|exists:roles,name',
            'station_id' => 'nullable|exists:border_police_stations,id',
        ], [
            'email.ends_with' => __('ui.user_form.email_domain_error'),
        ]);

        $user = User::create([
            'name'       => $validated['name'],
            'email'      => $validated['email'],
            'password'   => Hash::make($validated['password']),
            'station_id' => $validated['station_id'] ?? null,
        ]);

        $user->assignRole($validated['role']);

        return redirect()->route('users.index')->with('success', 'Korisnik uspješno kreiran.');
    }

    public function show(User $user)
    {
        $authUser = auth()->user();

        if ($authUser->hasRole('pilot')) {
            // Pilot can only see other pilots from same station
            if (!$user->hasRole('pilot') || $user->station_id !== $authUser->station_id) {
                abort(403);
            }
        } else {
            $this->authorizeUserAccess($user);
        }

        $user->load(['roles', 'drones', 'flights.drone', 'maintenanceLogs.drone', 'station.administration']);
        $recentFlights = $user->flights()->with('drone')->latest('flight_date')->take(5)->get();
        return view('users.show', compact('user', 'recentFlights'));
    }

    public function edit(User $user)
    {
        $this->denyViewer();
        $this->authorizeUserAccess($user);

        $roles = Role::all();
        $userRole = $user->roles->first()?->name;
        $administrations = PoliceAdministration::with('stations')->orderBy('name')->get();
        return view('users.edit', compact('user', 'roles', 'userRole', 'administrations'));
    }

    public function update(Request $request, User $user)
    {
        $this->denyViewer();
        $this->authorizeUserAccess($user);

        $validated = $request->validate([
            'name'       => 'required|string|max:255',
            'email'      => 'required|email|unique:users,email,' . $user->id . '|ends_with:@mup.hr',
            'password'   => ['nullable', Password::min(8)],
            'role'       => 'required|exists:roles,name',
            'station_id' => 'nullable|exists:border_police_stations,id',
        ], [
            'email.ends_with' => __('ui.user_form.email_domain_error'),
        ]);

        $user->update([
            'name'       => $validated['name'],
            'email'      => $validated['email'],
            'station_id' => $validated['station_id'] ?? null,
        ]);

        if (!empty($validated['password'])) {
            $user->update(['password' => Hash::make($validated['password'])]);
        }

        $user->syncRoles([$validated['role']]);

        return redirect()->route('users.show', $user)->with('success', 'Korisnik uspješno ažuriran.');
    }

    public function destroy(User $user)
    {
        $this->denyViewer();
        $this->authorizeUserAccess($user);

        if ($user->id === auth()->id()) {
            return back()->with('error', 'Ne možeš obrisati vlastiti račun.');
        }

        $user->delete();
        return redirect()->route('users.index')->with('success', 'Korisnik obrisan.');
    }

    private function authorizeUserAccess(User $user): void
    {
        if (!$this->inUserStation($user->station_id)) {
            abort(403);
        }
    }
}
