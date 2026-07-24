<!DOCTYPE html>
<html>
<head>
<meta charset="UTF-8">
<style>
body { font-family: Arial, sans-serif; font-size: 11pt; }
h2 { text-align: center; font-size: 13pt; margin-bottom: 2pt; }
.date { text-align: center; font-size: 10pt; margin-bottom: 16pt; color: #555; }
table { width: 100%; border-collapse: collapse; }
th { background-color: #1a2e5a; color: #fff; padding: 6pt 8pt; text-align: left; border: 1pt solid #1a2e5a; font-size: 10pt; }
td { padding: 5pt 8pt; border: 1pt solid #ccc; font-size: 10pt; vertical-align: top; }
tr:nth-child(even) td { background-color: #f4f6fa; }
.coords { font-family: Courier New, monospace; font-size: 9.5pt; }
.footer { text-align: center; font-size: 8pt; color: #888; margin-top: 20pt; }
</style>
</head>
<body>
<h2>{{ $stationName }}</h2>
<div class="date">Lovačke kamere — popis stanja — {{ $date }}</div>

<table>
    <thead>
        <tr>
            <th>Oznaka kamere</th>
            <th>Lokacija</th>
            <th>S (lat)</th>
            <th>I (lon)</th>
            <th>Zadnja izmjena</th>
            <th>Ažurirao</th>
        </tr>
    </thead>
    <tbody>
        @forelse($cameras as $cam)
        <tr>
            <td><strong>{{ $cam->name }}</strong></td>
            <td>{{ $cam->location_name }}</td>
            <td class="coords">{{ $cam->latitude ? number_format((float)$cam->latitude, 5) : '—' }}</td>
            <td class="coords">{{ $cam->longitude ? number_format((float)$cam->longitude, 5) : '—' }}</td>
            <td>{{ $cam->updated_at->format('d.m.Y H:i') }}</td>
            <td>{{ $cam->lastUpdatedBy?->name ?? '—' }}</td>
        </tr>
        @empty
        <tr><td colspan="6" style="text-align:center">Nema kamera.</td></tr>
        @endforelse
    </tbody>
</table>

<div class="footer">Ispis generiran: {{ $date }} | SkyGuard sustav</div>
</body>
</html>
