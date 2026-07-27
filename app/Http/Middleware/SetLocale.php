<?php

namespace App\Http\Middleware;

use Carbon\Carbon;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Config;
use Symfony\Component\HttpFoundation\Response;

class SetLocale
{
    public function handle(Request $request, Closure $next): Response
    {
        if (!session()->has('locale')) {
            session(['locale' => 'hr']);
        }

        $locale = session('locale');

        if (!in_array($locale, ['hr', 'en'])) {
            $locale = 'hr';
            session(['locale' => 'hr']);
        }

        App::setLocale($locale);
        Carbon::setLocale($locale);

        Config::set('adminlte.menu', $this->buildMenu($locale));

        return $next($request);
    }

    private function buildMenu(string $locale): array
    {
        $t = fn(string $key) => trans("menu.{$key}");

        return [
            [
                'text' => $t('dashboard'),
                'url'  => 'dashboard',
                'icon' => 'fas fa-tachometer-alt',
            ],
            ['header' => $t('aircraft_header')],
            [
                'text' => $t('drones'),
                'url'  => 'drones',
                'icon' => 'fas fa-helicopter',
            ],
            [
                'text' => $t('flights'),
                'url'  => 'flights',
                'icon' => 'fas fa-route',
            ],
            [
                'text' => $t('maintenance'),
                'url'  => 'maintenance',
                'icon' => 'fas fa-wrench',
            ],
            ['header' => $t('operations_header')],
            [
                'text' => $t('detections'),
                'url'  => 'detections',
                'icon' => 'fas fa-crosshairs',
            ],
            [
                'text' => $t('station_crew'),
                'url'  => 'users',
                'icon' => 'fas fa-id-badge',
            ],
            ['header' => $t('structure_header'), 'can' => 'manage station boundary'],
            [
                'text' => $t('police_administrations'),
                'url'  => 'police-administrations',
                'icon' => 'fas fa-sitemap',
                'can'  => 'manage station boundary',
            ],
            [
                'text' => $t('police_stations'),
                'url'  => 'police-stations',
                'icon' => 'fas fa-building',
                'can'  => 'manage station boundary',
            ],
            [
                'text' => $t('station_boundary'),
                'url'  => 'station-boundary',
                'icon' => 'fas fa-draw-polygon',
                'can'  => 'manage station boundary',
            ],
            ['header' => $t('ai_header')],
            [
                'text' => $t('analytics'),
                'url'  => 'analytics',
                'icon' => 'fas fa-chart-bar',
            ],
            [
                'text' => $t('ai_tools'),
                'url'  => 'ai',
                'icon' => 'fas fa-robot',
            ],
            ['header' => $t('cameras_header'), 'can' => 'view cameras'],
            [
                'text' => $t('cameras'),
                'url'  => 'cameras',
                'icon' => 'fas fa-camera',
                'can'  => 'view cameras',
            ],
            ['header' => $t('reports_header'), 'can' => 'view reports'],
            [
                'text' => $t('reports'),
                'url'  => 'reports',
                'icon' => 'fas fa-file-alt',
                'can'  => 'view reports',
            ],
            ['header' => $t('admin_header'), 'can' => 'manage users'],
            [
                'text' => $t('users'),
                'url'  => 'users',
                'icon' => 'fas fa-users',
                'can'  => 'manage users',
            ],
            [
                'text' => $t('drone_checkouts'),
                'url'  => 'drone-checkouts',
                'icon' => 'fas fa-clipboard-list',
                'can'  => 'manage users',
            ],
        ];
    }
}
