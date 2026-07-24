<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\StationScoped;
use App\Models\Flight;
use App\Models\PoliceAdministration;
use App\Models\BorderPoliceStation;
use Carbon\Carbon;
use Illuminate\Http\Request;
use PhpOffice\PhpWord\PhpWord;
use PhpOffice\PhpWord\SimpleType\TblWidth;

class ReportController extends Controller
{
    use StationScoped;

    public function index(Request $request)
    {
        $month     = (int) $request->input('month', now()->month);
        $year      = (int) $request->input('year',  now()->year);
        $adminId   = $request->input('administration_id');
        $stationId = $request->input('station_id');

        $user            = auth()->user();
        $isSuperAdmin    = $user->station_id === null;
        $administrations = $isSuperAdmin
            ? PoliceAdministration::with('stations')->orderBy('name')->get()
            : null;

        // Admin manages a whole administration — offer a station filter scoped to it.
        $stations = (!$isSuperAdmin && $user->hasRole('admin') && $user->station?->police_administration_id)
            ? BorderPoliceStation::where('police_administration_id', $user->station->police_administration_id)
                ->orderBy('name')->get()
            : null;

        $isPilot = $user->hasRole('pilot');

        $data = $this->buildReportData($month, $year, $adminId, $stationId);

        $selectedAdmin   = $isPilot ? null : ($adminId   ? PoliceAdministration::find($adminId)  : null);
        $selectedStation = $isPilot ? null : ($stationId ? BorderPoliceStation::find($stationId) : null);

        $scopedIds         = $this->scopedStationIds();
        $showStationColumn = !$isPilot && !$selectedStation && ($scopedIds === null || count($scopedIds) > 1);

        return view('reports.index', array_merge($data, compact(
            'month', 'year', 'administrations', 'stations',
            'adminId', 'stationId', 'selectedAdmin', 'selectedStation', 'isSuperAdmin', 'showStationColumn', 'isPilot'
        )));
    }

    public function export(Request $request)
    {
        $month     = (int) $request->input('month', now()->month);
        $year      = (int) $request->input('year',  now()->year);
        $adminId   = $request->input('administration_id');
        $stationId = $request->input('station_id');

        $user    = auth()->user();
        $isPilot = $user->hasRole('pilot');

        $selectedAdmin   = $isPilot ? null : ($adminId   ? PoliceAdministration::find($adminId)  : null);
        $selectedStation = $isPilot ? null : ($stationId ? BorderPoliceStation::find($stationId) : null);

        $data        = $this->buildReportData($month, $year, $adminId, $stationId);
        $scopedIds   = $this->scopedStationIds();
        $showStation = !$isPilot && !$selectedStation && ($scopedIds === null || count($scopedIds) > 1);
        $subtitle    = $isPilot ? ('Moji letovi — ' . $user->name) : null;
        $phpWord     = $this->buildWordDocument($data['flights'], $month, $year, $selectedAdmin, $selectedStation, $showStation, $subtitle);

        $slug     = $isPilot
            ? str_replace(' ', '_', $user->name)
            : collect([$selectedAdmin?->name, $selectedStation?->name])
                ->filter()->map(fn($s) => str_replace(' ', '_', $s))->implode('-');
        $filename = 'letovi_' . $year . '_' . str_pad($month, 2, '0', STR_PAD_LEFT)
                    . ($slug ? "_{$slug}" : '') . '.docx';

        $temp = tempnam(sys_get_temp_dir(), 'dronemgr_');
        \PhpOffice\PhpWord\IOFactory::createWriter($phpWord, 'Word2007')->save($temp);

        return response()->download($temp, $filename, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        ])->deleteFileAfterSend(true);
    }

    private function buildReportData(int $month, int $year, ?string $adminId, ?string $stationId): array
    {
        $from = Carbon::create($year, $month, 1)->startOfMonth();
        $to   = $from->copy()->endOfMonth();

        $query = Flight::with(['drone', 'pilot', 'station.administration'])
            ->whereBetween('flight_date', [$from, $to])
            ->orderBy('flight_date');

        $user = auth()->user();

        if ($user->hasRole('pilot')) {
            // Pilots only ever see their own flights, regardless of any
            // station_id/administration_id query params.
            $query->where('user_id', $user->id);
            $flights = $query->get();

            return [
                'flights' => $flights,
                'totals'  => [
                    'flights' => $flights->count(),
                    'minutes' => (int) $flights->sum('duration_minutes'),
                ],
            ];
        }

        $scopedIds = $this->scopedStationIds();

        if ($scopedIds !== null) {
            // Non-super-admin: admins get every station in their administration,
            // viewers just their own — optionally narrowed to one station of their scope.
            if ($stationId && in_array((int) $stationId, $scopedIds, true)) {
                $query->where('station_id', $stationId);
            } else {
                $query->whereIn('station_id', $scopedIds);
            }
        } else {
            // Super admin: optional filters
            if ($stationId) {
                $query->where('station_id', $stationId);
            } elseif ($adminId) {
                $query->whereHas('station', fn($q) =>
                    $q->where('police_administration_id', $adminId)
                );
            }
        }

        $flights = $query->get();

        $totals = [
            'flights' => $flights->count(),
            'minutes' => (int) $flights->sum('duration_minutes'),
        ];

        return compact('flights', 'totals');
    }

    private function buildWordDocument($flights, int $month, int $year, $admin, $station, bool $showStation, ?string $subtitleOverride = null): PhpWord
    {
        $phpWord = new PhpWord();
        $phpWord->setDefaultFontName('Calibri');
        $phpWord->setDefaultFontSize(11);

        $section = $phpWord->addSection([
            'marginTop' => 1000, 'marginBottom' => 1000,
            'marginLeft' => 1200, 'marginRight' => 1200,
        ]);

        $monthName   = Carbon::create($year, $month, 1)->locale('hr')->isoFormat('MMMM YYYY');
        $daysInMonth = Carbon::create($year, $month, 1)->daysInMonth;

        // ── Naslov ───────────────────────────────────────────────────────────
        $section->addText(
            'IZVJEŠTAJ O LETOVIMA',
            ['bold' => true, 'size' => 18, 'color' => '1F3864'],
            ['alignment' => 'center', 'spaceAfter' => 0]
        );

        if ($subtitleOverride) {
            $section->addText(
                $subtitleOverride,
                ['bold' => true, 'size' => 14, 'color' => '2F5496'],
                ['alignment' => 'center', 'spaceAfter' => 0]
            );
        } elseif ($admin || $station) {
            $subtitle = $admin?->name ?? '';
            if ($station) {
                $subtitle .= ($subtitle ? ' — ' : '') . $station->name;
            }
            $section->addText(
                $subtitle,
                ['bold' => true, 'size' => 14, 'color' => '2F5496'],
                ['alignment' => 'center', 'spaceAfter' => 0]
            );
        }

        $section->addText(
            strtoupper($monthName),
            ['bold' => true, 'size' => 13, 'color' => '2F5496'],
            ['alignment' => 'center', 'spaceAfter' => 200]
        );
        $section->addText(
            'Razdoblje: 1. ' . Carbon::create($year, $month, 1)->locale('hr')->isoFormat('MMMM') .
            ' – ' . $daysInMonth . '. ' . Carbon::create($year, $month, 1)->locale('hr')->isoFormat('MMMM YYYY'),
            ['size' => 10, 'color' => '555555'],
            ['alignment' => 'center']
        );
        $section->addText(
            'Generirano: ' . now()->format('d.m.Y H:i'),
            ['size' => 10, 'color' => '555555'],
            ['alignment' => 'center', 'spaceAfter' => 400]
        );

        $section->addLine(['weight' => 1, 'color' => '2F5496', 'width' => 5000]);
        $section->addTextBreak(1);
        $section->addText(
            'Pregled letova',
            ['bold' => true, 'size' => 13, 'color' => '1F3864'],
            ['spaceAfter' => 160]
        );

        // ── Tablica ──────────────────────────────────────────────────────────
        $tableStyle = [
            'borderSize' => 6, 'borderColor' => 'AAAAAA',
            'cellMargin' => 80, 'unit' => TblWidth::PERCENT, 'width' => 100 * 50,
        ];
        $hStyle  = ['bgColor' => '2F5496'];
        $hFont   = ['bold' => true, 'color' => 'FFFFFF', 'size' => 9];
        $even    = ['bgColor' => 'F5F5F5'];
        $odd     = ['bgColor' => 'FFFFFF'];
        $nFont   = ['size' => 9];
        $tFont   = ['bold' => true, 'size' => 9, 'color' => '1F3864'];
        $tCell   = ['bgColor' => 'DCE6F1'];

        $headers = $showStation
            ? ['Datum', 'Uprava / Postaja', 'Dron', 'Pilot', 'Lokacija', 'Trajanje', 'Status']
            : ['Datum', 'Dron', 'Pilot', 'Lokacija', 'Trajanje', 'Status'];
        $colCount = count($headers);

        $table = $section->addTable($tableStyle);
        $table->addRow(400);
        foreach ($headers as $h) {
            $table->addCell(null, $hStyle)->addText($h, $hFont, ['alignment' => 'center']);
        }

        foreach ($flights as $i => $fl) {
            $bg = $i % 2 === 0 ? $even : $odd;
            $table->addRow(350);
            $table->addCell(null, $bg)->addText(
                $fl->flight_date?->format('d.m.Y') ?? '—', $nFont, ['alignment' => 'center']
            );
            if ($showStation) {
                $adminName = $fl->station?->administration?->name ?? '';
                $stName    = $fl->station?->name ?? '—';
                $cell = $table->addCell(null, $bg);
                if ($adminName) {
                    $cell->addText($adminName, ['size' => 8, 'color' => '888888']);
                }
                $cell->addText($stName, $nFont);
            }
            $table->addCell(null, $bg)->addText($fl->drone?->name ?? '—', $nFont);
            $table->addCell(null, $bg)->addText($fl->pilot?->name ?? '—', $nFont);
            $table->addCell(null, $bg)->addText($fl->location ?? '—', $nFont);
            $table->addCell(null, $bg)->addText(
                ($fl->duration_minutes ?? 0) . ' min', $nFont, ['alignment' => 'center']
            );
            $table->addCell(null, $bg)->addText(
                ucfirst($fl->status ?? '—'), $nFont, ['alignment' => 'center']
            );
        }

        if ($flights->isEmpty()) {
            $table->addRow(350);
            $table->addCell(null, ['gridSpan' => $colCount])->addText(
                'Nema zabilježenih letova za odabrano razdoblje.',
                ['italic' => true, 'color' => '888888'],
                ['alignment' => 'center']
            );
        }

        // Ukupno red
        $table->addRow(400);
        $table->addCell(null, $tCell)->addText('UKUPNO', $tFont);
        foreach (range(2, $colCount - 2) as $_) {
            $table->addCell(null, $tCell)->addText('', $tFont);
        }
        $table->addCell(null, $tCell)->addText(
            $flights->sum('duration_minutes') . ' min', $tFont, ['alignment' => 'center']
        );
        $table->addCell(null, $tCell)->addText(
            $flights->count() . ' letova', $tFont, ['alignment' => 'center']
        );

        $section->addTextBreak(2);
        $section->addText(
            'Ovaj izvještaj automatski je generiran sustavom SkyGuard.',
            ['italic' => true, 'size' => 9, 'color' => '888888'],
            ['alignment' => 'center']
        );

        return $phpWord;
    }
}
