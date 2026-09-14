<?php

use App\Http\Controllers\DroneController;
use App\Http\Controllers\FlightController;
use App\Http\Controllers\HuntingCameraController;
use App\Http\Controllers\MaintenanceController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\DetectionController;
use App\Http\Controllers\DroneCheckoutController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\AnalyticsController;
use App\Http\Controllers\AiController;
use App\Http\Controllers\ChatController;
use App\Http\Controllers\SearchController;
use App\Http\Controllers\StationBoundaryController;
use App\Http\Controllers\PoliceStationController;
use App\Http\Controllers\PoliceAdministrationController;
use App\Http\Controllers\AdministrationBoundaryController;
use Illuminate\Support\Facades\Route;

Route::get('/', fn() => redirect()->route('dashboard'));

Route::get('language/{locale}', function (string $locale) {
    if (in_array($locale, ['hr', 'en'])) {
        session(['locale' => $locale]);
    }
    return redirect()->back();
})->name('language.switch');

Route::middleware(['auth'])->group(function () {

    Route::get('/dashboard', [DashboardController::class, 'index'])->name('dashboard');

    // ── Drones — index/show: all auth; create/edit/delete: admin only ─────────
    // NOTE: 'drones/create' must be registered before the 'drones/{drone}' wildcard,
    // otherwise Laravel matches the wildcard first and 404s via failed model binding.
    Route::get('drones', [DroneController::class, 'index'])->name('drones.index');
    Route::middleware('role:admin')->get('drones/create', [DroneController::class, 'create'])->name('drones.create');
    Route::get('drones/{drone}', [DroneController::class, 'show'])->name('drones.show');
    Route::middleware('role:admin')->group(function () {
        Route::post('drones', [DroneController::class, 'store'])->name('drones.store');
        Route::get('drones/{drone}/edit', [DroneController::class, 'edit'])->name('drones.edit');
        Route::put('drones/{drone}', [DroneController::class, 'update'])->name('drones.update');
        Route::delete('drones/{drone}', [DroneController::class, 'destroy'])->name('drones.destroy');
    });

    // ── Flights ───────────────────────────────────────────────────────────────
    Route::resource('flights', FlightController::class);
    Route::get('detections', [DetectionController::class, 'index'])->name('detections.index');
    Route::get('detections/create', [DetectionController::class, 'create'])->name('detections.create');
    Route::post('detections', [DetectionController::class, 'storeManual'])->name('detections.storeManual');
    Route::post('flights/{flight}/detections', [DetectionController::class, 'store'])->name('detections.store');
    Route::delete('detections/{detection}', [DetectionController::class, 'destroy'])->name('detections.destroy');

    // ── Analytics ─────────────────────────────────────────────────────────────
    Route::get('analytics', [AnalyticsController::class, 'index'])->name('analytics.index');
    Route::get('ai', [AiController::class, 'index'])->name('ai.index');
    Route::get('ai/predict', [AiController::class, 'predict'])->name('ai.predict');
    Route::get('ai/risk-grid', [AiController::class, 'riskGrid'])->name('ai.riskGrid');
    Route::get('ai/dev-metrics', [AiController::class, 'devMetrics'])->name('ai.devMetrics');
    Route::post('chat', [ChatController::class, 'respond'])->name('chat.respond');

    // ── Maintenance — all auth; workflow transitions: admin only ──────────────
    Route::get('maintenance', [MaintenanceController::class, 'index'])->name('maintenance.index');
    Route::get('maintenance/create', [MaintenanceController::class, 'create'])->name('maintenance.create');
    Route::post('maintenance', [MaintenanceController::class, 'store'])->name('maintenance.store');
    Route::get('maintenance/{maintenance}', [MaintenanceController::class, 'show'])->name('maintenance.show');
    Route::get('maintenance/{maintenance}/edit', [MaintenanceController::class, 'edit'])->name('maintenance.edit');
    Route::put('maintenance/{maintenance}', [MaintenanceController::class, 'update'])->name('maintenance.update');
    Route::delete('maintenance/{maintenance}', [MaintenanceController::class, 'destroy'])->name('maintenance.destroy');
    Route::post('maintenance/{maintenance}/accept', [MaintenanceController::class, 'accept'])->name('maintenance.accept');
    Route::post('maintenance/{maintenance}/start', [MaintenanceController::class, 'startWork'])->name('maintenance.start');
    Route::post('maintenance/{maintenance}/resolve', [MaintenanceController::class, 'resolve'])->name('maintenance.resolve');

    // ── Hunting Cameras ───────────────────────────────────────────────────────
    Route::get('cameras/export-word', [HuntingCameraController::class, 'exportWord'])->name('cameras.exportWord');
    Route::get('cameras', [HuntingCameraController::class, 'index'])->name('cameras.index');
    Route::get('cameras/create', [HuntingCameraController::class, 'create'])->name('cameras.create');
    Route::post('cameras', [HuntingCameraController::class, 'store'])->name('cameras.store');
    Route::get('cameras/{camera}', [HuntingCameraController::class, 'show'])->name('cameras.show');
    Route::get('cameras/{camera}/edit', [HuntingCameraController::class, 'edit'])->name('cameras.edit');
    Route::put('cameras/{camera}', [HuntingCameraController::class, 'update'])->name('cameras.update');
    Route::delete('cameras/{camera}', [HuntingCameraController::class, 'destroy'])->name('cameras.destroy');

    // ── Drone checkouts ───────────────────────────────────────────────────────
    Route::post('drone-checkouts', [DroneCheckoutController::class, 'store'])->name('drone_checkouts.store');
    Route::patch('drone-checkouts/{checkout}/check-in', [DroneCheckoutController::class, 'checkIn'])->name('drone_checkouts.checkIn');

    // ── Users — read: all auth; write: admin only ─────────────────────────────
    // NOTE: 'users/create' must be registered before 'users/{user}', otherwise
    // Laravel matches it as the {user} wildcard first and 404s (no user with
    // id/route-key "create").
    Route::get('users', [UserController::class, 'index'])->name('users.index');
    Route::middleware('role:admin')->group(function () {
        Route::get('users/create', [UserController::class, 'create'])->name('users.create');
        Route::post('users', [UserController::class, 'store'])->name('users.store');
    });
    Route::get('users/{user}', [UserController::class, 'show'])->name('users.show');
    Route::middleware('role:admin')->group(function () {
        Route::get('users/{user}/edit', [UserController::class, 'edit'])->name('users.edit');
        Route::put('users/{user}', [UserController::class, 'update'])->name('users.update');
        Route::delete('users/{user}', [UserController::class, 'destroy'])->name('users.destroy');
    });

    // ── Admin + viewer ────────────────────────────────────────────────────────
    Route::middleware('role:admin|viewer')->group(function () {
        Route::get('drone-checkouts', [DroneCheckoutController::class, 'index'])->name('drone_checkouts.index');
    });

    // ── Reports — admin + viewer see their scope, pilot sees own flights only ──
    Route::middleware('role:admin|viewer|pilot')->group(function () {
        Route::get('reports', [ReportController::class, 'index'])->name('reports.index');
        Route::get('reports/export', [ReportController::class, 'export'])->name('reports.export');
    });

    // ── Global search (admin only) ─────────────────────────────────────────────
    Route::middleware('role:admin')->group(function () {
        Route::get('search', [SearchController::class, 'index'])->name('search.index');
    });

    // ── Police administrations (list: admin + viewer; add/edit/delete: super admin only) ─
    Route::middleware('can:manage station boundary')->group(function () {
        Route::get('police-administrations', [PoliceAdministrationController::class, 'index'])->name('police-administrations.index');
        Route::get('police-administrations/create', [PoliceAdministrationController::class, 'create'])->name('police-administrations.create');
        Route::post('police-administrations', [PoliceAdministrationController::class, 'store'])->name('police-administrations.store');
        Route::get('police-administrations/{administration}/edit', [PoliceAdministrationController::class, 'edit'])->name('police-administrations.edit');
        Route::put('police-administrations/{administration}', [PoliceAdministrationController::class, 'update'])->name('police-administrations.update');
        Route::delete('police-administrations/{administration}', [PoliceAdministrationController::class, 'destroy'])->name('police-administrations.destroy');
    });

    // ── Police stations (list: admin + viewer; add/edit/delete: super admin only) ─
    Route::middleware('can:manage station boundary')->group(function () {
        Route::get('police-stations', [PoliceStationController::class, 'index'])->name('police-stations.index');
        Route::get('police-stations/create', [PoliceStationController::class, 'create'])->name('police-stations.create');
        Route::post('police-stations', [PoliceStationController::class, 'store'])->name('police-stations.store');
        Route::get('police-stations/{station}/edit', [PoliceStationController::class, 'edit'])->name('police-stations.edit');
        Route::put('police-stations/{station}', [PoliceStationController::class, 'update'])->name('police-stations.update');
        Route::delete('police-stations/{station}', [PoliceStationController::class, 'destroy'])->name('police-stations.destroy');
    });

    // ── Station boundaries (admin + viewer draw/edit their own scope) ─────────
    Route::middleware('can:manage station boundary')->group(function () {
        Route::get('station-boundary', [StationBoundaryController::class, 'index'])->name('station-boundary.index');
        Route::get('station-boundary/{station}/edit', [StationBoundaryController::class, 'edit'])->name('station-boundary.edit');
        Route::put('station-boundary/{station}', [StationBoundaryController::class, 'update'])->name('station-boundary.update');
    });

    // ── Administration boundaries (admin + viewer draw/edit their own uprava) ──
    Route::middleware('can:manage station boundary')->group(function () {
        Route::get('administration-boundary/{administration}/edit', [AdministrationBoundaryController::class, 'edit'])->name('administration-boundary.edit');
        Route::put('administration-boundary/{administration}', [AdministrationBoundaryController::class, 'update'])->name('administration-boundary.update');
    });

    // ── Notifications (admin + pilot) ────────────────────────────────────────
    Route::get('notifications/data', [NotificationController::class, 'data'])->name('notifications.data');
    Route::get('notifications/mark-all-read', [NotificationController::class, 'markAllRead'])->name('notifications.markAllRead');
    Route::get('notifications/history', [NotificationController::class, 'history'])->name('notifications.history');
    Route::get('notifications/{id}/redirect', [NotificationController::class, 'redirect'])->name('notifications.redirect');
});

require __DIR__.'/auth.php';
