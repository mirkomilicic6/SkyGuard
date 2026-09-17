@extends('adminlte::page')

@section('title', 'ML analiza')

@section('content_header')
    <h1><i class="fas fa-laptop-code mr-2" style="color:#f0c040"></i>ML analiza</h1>
@endsection

@section('content')

{{-- ══ 1. Izvorni podaci ══════════════════════════════════════════════════ --}}
<div class="db-card mb-4">
    <div class="db-card-header">
        <div>
            <div class="db-card-title"><i class="fas fa-database mr-2" style="color:var(--gold)"></i>1. Izvorni podaci — skup za nadzirano učenje</div>
            <div class="db-card-subtitle">
                Metoda: point-in-time feature engineering nad DBSCAN zonama — po jedan redak za svaku kombinaciju
                (zona, dan, vremenski blok) kroz cijelu povijest, sa značajkama izračunatima isključivo iz podataka
                do jučer (kumulativni i klizni prozori, pomak unatrag), kako ništa od ishoda ne bi procurilo u
                vlastite značajke. Oznaka (label) = je li se u toj zoni/danu/bloku stvarno dogodila detekcija;
                dani bez detekcije su pravi negativni primjeri, ne sintetička pozadina.
            </div>
        </div>
    </div>
    <div class="db-card-body">
        <div class="row mb-3" id="datasetSummary">
            <div class="col-md-3"><div class="ac-stat"><div class="ac-stat-value" id="dsRows">—</div><div class="ac-stat-label">redaka (zona×dan×blok)</div></div></div>
            <div class="col-md-3"><div class="ac-stat"><div class="ac-stat-value" id="dsPositive">—</div><div class="ac-stat-label">pozitivnih primjera</div></div></div>
            <div class="col-md-3"><div class="ac-stat"><div class="ac-stat-value" id="dsZones">—</div><div class="ac-stat-label">DBSCAN zona</div></div></div>
            <div class="col-md-3"><div class="ac-stat"><div class="ac-stat-value" id="dsRate">—</div><div class="ac-stat-label">udio pozitivnih</div></div></div>
        </div>
        <details class="ac-col-legend mb-3">
            <summary>Objašnjenje stupaca u tablici ispod</summary>
            <div class="ac-col-legend-grid">
                <div><b>zone</b> — DBSCAN žarište kojem redak pripada (K1, K2…)</div>
                <div><b>date</b> — dan na koji se redak odnosi</div>
                <div><b>block</b> — vremenski blok tog dana (0–5, svaki po 4h)</div>
                <div><b>dow</b> — dan u tjednu (0=pon … 6=ned)</div>
                <div><b>month</b> — mjesec (1–12)</div>
                <div><b>hour</b> — početni sat bloka (npr. blok 2 → 08)</div>
                <div><b>flights_cum</b> — ukupno letova kroz zonu, zaključno s jučer</div>
                <div><b>minutes_cum</b> — ukupno minuta nadzora u zoni, zaključno s jučer</div>
                <div><b>dets_cum</b> — ukupno detekcija u zoni, zaključno s jučer</div>
                <div><b>flights_30d</b> — letova kroz zonu u zadnjih 30 dana</div>
                <div><b>minutes_30d</b> — minuta nadzora u zoni u zadnjih 30 dana</div>
                <div><b>dets_7d</b> — detekcija u zoni u zadnjih 7 dana</div>
                <div><b>dets_30d</b> — detekcija u zoni u zadnjih 30 dana</div>
                <div><b>flights_no_det_cum</b> — letova koji nisu naišli ni na jednu detekciju, zaključno s jučer</div>
                <div><b>days_since_last_det</b> — koliko je dana prošlo od zadnje detekcije u zoni</div>
                <div><b>trend</b> — % promjena detekcija (zadnjih 30 dana naspram prethodnih 30)</div>
                <div><b>label</b> — je li se u tom retku (zona/dan/blok) stvarno dogodila detekcija (1) ili ne (0) — ovo model uči predviđati</div>
            </div>
            <div class="ac-col-legend-note">
                Sve osim <code>zone</code>, <code>date</code>, <code>block</code> i <code>label</code> je izračunato isključivo iz podataka
                do jučer (vidi napomenu o point-in-time metodi iznad) — ništa od budućnosti/ishoda ne smije procuriti u značajke.
            </div>
        </details>
        <div class="table-responsive">
            <table class="table table-sm table-striped mb-0" id="datasetTable" style="font-size:.78rem">
                <thead><tr id="datasetHead"></tr></thead>
                <tbody id="datasetBody"><tr><td colspan="10" class="text-center py-3 text-muted">Učitavanje...</td></tr></tbody>
            </table>
        </div>
    </div>
</div>

{{-- ══ 2. DBSCAN žarišta ══════════════════════════════════════════════════ --}}
<div class="db-card mb-4">
    <div class="db-card-header">
        <div>
            <div class="db-card-title"><i class="fas fa-braille mr-2" style="color:#6f42c1"></i>2. DBSCAN žarišta (nenadzirano učenje)</div>
            <div class="db-card-subtitle" id="zonesSubtitle">Učitavanje...</div>
        </div>
    </div>
    <div id="zonesMap" style="height:460px;border-radius:0 0 12px 12px"></div>
</div>

{{-- ══ 3. Usporedba modela ════════════════════════════════════════════════ --}}
<div class="db-card mb-4">
    <div class="db-card-header">
        <div>
            <div class="db-card-title"><i class="fas fa-balance-scale mr-2" style="color:var(--gold)"></i>3. Usporedba modela (nadzirano učenje)</div>
            <div class="db-card-subtitle">
                Trenira se i testira kronološki (80/20 po datumu, ne nasumično) da rezultati ne ispadnu umjetno dobri. Najbolji
                model bira se po F1, ne po accuracy ni precisionu — model koji nikad ne javi opasnost imao bi accuracy ~99% i
                precision 0. Nizak precision čak i kod najboljeg modela je očekivan: stvarnih pozitivnih primjera ima jako malo,
                pa i mala stopa lažnih uzbuna na ogromnoj negativnoj pozadini generira više lažnih nego stvarnih pogodaka.
            </div>
            <div class="db-card-subtitle" id="modelsSubtitle">Učitavanje...</div>
        </div>
    </div>
    <div class="db-card-body">
        <div class="table-responsive mb-3">
            <table class="table table-sm table-striped" style="font-size:.82rem">
                <thead><tr>
                    <th>Model</th><th>Accuracy</th><th>Precision</th><th>Recall</th><th>F1</th><th>ROC-AUC</th><th>TP/FP/FN/TN</th>
                </tr></thead>
                <tbody id="modelsTable"><tr><td colspan="7" class="text-center py-3 text-muted">Učitavanje...</td></tr></tbody>
            </table>
        </div>
        <div class="row">
            <div class="col-md-6"><canvas id="rocChart" height="220"></canvas></div>
            <div class="col-md-6"><canvas id="prChart" height="220"></canvas></div>
        </div>
    </div>
</div>

{{-- ══ 4. Prediktivna karta ═══════════════════════════════════════════════ --}}
<div class="db-card mb-4">
    <div class="db-card-header">
        <div>
            <div class="db-card-title"><i class="fas fa-satellite-dish mr-2" style="color:#f46d43"></i>4. Prediktivna karta — najbolji potvrđeni model</div>
            <div class="db-card-subtitle" id="predMapSubtitle">Učitavanje...</div>
        </div>
        <div class="ml-auto d-flex align-items-center" style="gap:.5rem;font-size:.76rem;color:rgba(255,255,255,.5)">
            <span class="ai-legend-dot" style="background:#dc3545"></span>Visoka
            <span class="ai-legend-dot" style="background:#fd7e14;margin-left:6px"></span>Povećana
            <span class="ai-legend-dot" style="background:#f0c040;margin-left:6px"></span>Srednja
            <span class="ai-legend-dot" style="background:#28a745;margin-left:6px"></span>Niska
            <span class="ai-legend-dot" style="background:#6c757d;margin-left:6px"></span>Nedovoljno podataka
        </div>
    </div>
    <div id="predMap" style="height:460px;border-radius:0 0 12px 12px"></div>
</div>

{{-- ══ 5. Preporuke nadzora ═══════════════════════════════════════════════ --}}
<div class="db-card mb-4">
    <div class="db-card-header">
        <div>
            <div class="db-card-title"><i class="fas fa-crosshairs mr-2" style="color:#f46d43"></i>5. Preporuke nadzora — model + pravila</div>
            <div class="db-card-subtitle" id="recSubtitle">Učitavanje...</div>
        </div>
        <div class="ml-auto d-flex align-items-center" style="gap:.5rem;font-size:.76rem;color:rgba(255,255,255,.5)">
            <span class="ai-legend-dot" style="background:#dc3545"></span>Pojačati
            <span class="ai-legend-dot" style="background:#f0c040;margin-left:6px"></span>Zadržati
            <span class="ai-legend-dot" style="background:#28a745;margin-left:6px"></span>Razmotriti smanjenje
            <span class="ai-legend-dot" style="background:#6c757d;margin-left:6px"></span>Nedovoljno podataka
        </div>
    </div>
    <div id="recMap" style="height:460px;border-radius:0 0 12px 12px"></div>
    <div class="table-responsive">
        <table class="table table-sm table-striped mb-0" style="font-size:.8rem">
            <thead><tr><th>Zona</th><th>Preporuka</th><th>Vjerojatnost</th><th>Letova</th><th>Sati</th><th>Objašnjenje</th></tr></thead>
            <tbody id="recTable"><tr><td colspan="6" class="text-center py-3 text-muted">Učitavanje...</td></tr></tbody>
        </table>
    </div>
</div>

{{-- ══ Dodatni alati — izvan glavnog pristupa (koraci 1–5) ═══════════════════ --}}
<div class="alert" style="background:rgba(13,110,253,0.12);border:1px solid rgba(13,110,253,0.35);color:#9fc3ff;border-radius:10px;padding:.85rem 1.4rem;margin:2rem 0 1.2rem;font-size:.85rem">
    <i class="fas fa-flask mr-2"></i>
    Alati ispod nisu dio gornjeg glavnog pristupa (koraci 1–5) — to su konceptualno odvojeni, alternativni pristupi: brza
    heuristika po lokaciji i RandomForest treniran po prostornoj mreži umjesto po DBSCAN zonama.
</div>

{{-- ── Usporedba pristupa ──────────────────────────────────────────────────── --}}
<div class="db-card mb-4">
    <div class="db-card-header">
        <div class="db-card-title"><i class="fas fa-balance-scale mr-2" style="color:var(--gold)"></i>Usporedba pristupa</div>
        <div class="db-card-subtitle">
            Brojevi (accuracy/precision/...) iz glavnog pristupa i iz mrežnog RandomForesta nisu izravno usporedivi — različito
            su definirani pozitivni/negativni primjeri i granularnost predikcije. Ovo je zato kvalitativna usporedba pristupa, ne brojki.
        </div>
    </div>
    <div class="table-responsive">
        <table class="table table-sm table-striped mb-0" style="font-size:.8rem">
            <thead>
                <tr>
                    <th style="width:16%">Kriterij</th>
                    <th>Glavni pristup (koraci 1–5)</th>
                    <th>Mrežni pristup (RandomForest)</th>
                    <th>Brza heuristika</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td><b>Vrsta učenja</b></td>
                    <td>Nenadzirano (DBSCAN) + nadzirano (5 modela)</td>
                    <td>Nadzirano, presence-only (MaxEnt-stil, kao u modeliranju rasprostranjenosti vrsta)</td>
                    <td>Nema učenja — ručno pravilo</td>
                </tr>
                <tr>
                    <td><b>Negativni primjeri</b></td>
                    <td>Stvarni — dani/blokovi u zoni bez ijedne detekcije</td>
                    <td>Sintetički — nasumični prostorno-vremenski uzorci ("pozadina")</td>
                    <td>—</td>
                </tr>
                <tr>
                    <td><b>Granularnost predikcije</b></td>
                    <td>Po DBSCAN zoni × dan × vremenski blok</td>
                    <td>Po ćeliji mreže, preko cijelog pokrivenog područja</td>
                    <td>Bilo koja točka (klik na kartu)</td>
                </tr>
                <tr>
                    <td><b>Evaluacija</b></td>
                    <td>Kronološki train/test split (80/20 po datumu) — trening na prošlosti, test na budućnosti</td>
                    <td>Nasumičan stratificirani split (dev dijagnostika) — sam kod to označava kao privremeno, ne rigorozno rješenje</td>
                    <td>Nema formalne evaluacije</td>
                </tr>
                <tr>
                    <td><b>Glavno ograničenje</b></td>
                    <td>Nizak precision i kod najboljeg modela zbog ekstremno rijetkih pozitivnih primjera</td>
                    <td>Umjetna pozadina nije stvaran "ništa se nije dogodilo" negativ; slabija evaluacija</td>
                    <td>Ne generalizira — samo broji povijesne slučajeve, ne prepoznaje obrasce</td>
                </tr>
                <tr>
                    <td><b>Najbolje za</b></td>
                    <td>Pouzdanu, akademski obranjivu predikciju za već identificirana žarišta</td>
                    <td>Pokrivenost cijelog područja, uključujući mjesta bez poznatih žarišta</td>
                    <td>Brz, transparentan odgovor za jednu konkretnu lokaciju</td>
                </tr>
            </tbody>
        </table>
    </div>
    <div class="db-card-body" style="font-size:.8rem;color:rgba(255,255,255,.55);font-style:italic;padding-top:0">
        Zaključak: za formalnu evaluaciju najjači je glavni pristup — jedini s kronološkim splitom bez curenja podataka.
        Mrežni RandomForest dobro nadopunjuje pokrivenošću izvan poznatih žarišta, ali sam kod otvoreno priznaje da mu
        evaluacija nije rigorozna. Heuristika je samo brz alat za pojedinačan upit, ne metodološki doprinos.
    </div>
</div>

{{-- ── Predict tool (heuristika, ne ML model) ──────────────────────────────── --}}
<div class="db-card mb-4">
    <div class="db-card-header">
        <div class="db-card-title"><i class="fas fa-crosshairs mr-2" style="color:var(--gold)"></i>Brza procjena rizika lokacije</div>
        <div class="db-card-subtitle ml-2">Heuristika (nije trenirani model) — kliknite na kartu ili unesite koordinate</div>
    </div>
    <div class="row" style="margin:0">
        <div class="col-md-5" style="padding:1.2rem 1.4rem;border-right:1px solid rgba(255,255,255,.06)">
            <div class="mb-3">
                <label class="ai-label">Koordinate</label>
                <div class="d-flex" style="gap:.5rem">
                    <input id="pLat" type="number" step="0.0001" placeholder="Lat (45.15)" class="form-control form-control-sm">
                    <input id="pLon" type="number" step="0.0001" placeholder="Lon (18.09)" class="form-control form-control-sm">
                </div>
            </div>
            <div class="mb-3">
                <label class="ai-label">Sat (0–23)</label>
                <input id="pHour" type="number" min="0" max="23" value="{{ now()->hour }}" class="form-control form-control-sm">
            </div>
            <div class="mb-3">
                <label class="ai-label">Dan u tjednu</label>
                <select id="pDow" class="form-control form-control-sm">
                    @foreach(['Ponedjeljak','Utorak','Srijeda','Četvrtak','Petak','Subota','Nedjelja'] as $i=>$d)
                        <option value="{{ $i }}" {{ $i === now()->dayOfWeekIso-1 ? 'selected' : '' }}>{{ $d }}</option>
                    @endforeach
                </select>
            </div>
            <button id="predictBtn" class="btn btn-block" style="background:rgba(240,192,64,0.15);border:1px solid rgba(240,192,64,0.4);color:var(--gold);border-radius:9px;font-weight:600">
                <i class="fas fa-calculator mr-2"></i>Procijeni rizik
            </button>
        </div>
        <div class="col-md-4" style="padding:1.2rem 1.4rem;border-right:1px solid rgba(255,255,255,.06)">
            <div id="predictMap" style="height:220px;border-radius:10px"></div>
        </div>
        <div class="col-md-3" style="padding:1.2rem 1.4rem;display:flex;flex-direction:column;justify-content:center;align-items:center">
            <div id="riskResult" class="text-center" style="display:none">
                <div id="riskScore" style="font-size:4rem;font-weight:700;line-height:1"></div>
                <div id="riskLevel" style="font-size:1rem;font-weight:600;margin-top:.3rem;text-transform:uppercase;letter-spacing:1px"></div>
                <div style="margin-top:1.2rem;width:100%">
                    <div class="ai-factor-row"><span>Prostorni</span><span id="fSpatial">—</span></div>
                    <div class="ai-factor-row"><span>Vremenski</span><span id="fTemporal">—</span></div>
                    <div class="ai-factor-row" style="border:none"><span>Obližnjih</span><span id="fNearby">—</span></div>
                </div>
            </div>
            <div id="riskEmpty" style="color:rgba(255,255,255,.3);text-align:center">
                <i class="fas fa-crosshairs" style="font-size:2.5rem"></i>
                <div style="font-size:.85rem;margin-top:.75rem">Odaberite lokaciju i kliknite Procijeni</div>
            </div>
        </div>
    </div>
</div>

{{-- ── Predictive risk grid (RandomForest, mrežni pristup) ─────────────────── --}}
<div class="db-card mb-4">
    <div class="db-card-header">
        <div>
            <div class="db-card-title"><i class="fas fa-satellite-dish mr-2" style="color:#f46d43"></i>Prediktivna mapa nadzora — mrežni pristup</div>
            <div class="db-card-subtitle">RandomForest treniran po prostornoj mreži (ne po DBSCAN zonama), uspoređen s dosadašnjim letnim pokrivanjem</div>
        </div>
        <div class="ml-auto" id="riskGridStatus" style="font-size:.78rem;color:rgba(255,255,255,.5)">Učitavanje...</div>
    </div>
    <div id="riskGridMap" style="height:460px;border-radius:0 0 12px 12px"></div>
</div>

<div class="row mb-4">
    <div class="col-lg-6">
        <div class="db-card h-100">
            <div class="db-card-header">
                <div class="db-card-title"><i class="fas fa-arrow-up mr-2" style="color:#dc3545"></i>Preporuka: pojačati nadzor</div>
            </div>
            <div class="db-card-body p-0" id="increaseList">
                <p class="text-muted text-center py-4 mb-0">Učitavanje...</p>
            </div>
        </div>
    </div>
    <div class="col-lg-6">
        <div class="db-card h-100">
            <div class="db-card-header">
                <div class="db-card-title"><i class="fas fa-arrow-down mr-2" style="color:#28a745"></i>Preporuka: smanjiti nadzor</div>
            </div>
            <div class="db-card-body p-0" id="decreaseList">
                <p class="text-muted text-center py-4 mb-0">Učitavanje...</p>
            </div>
        </div>
    </div>
</div>

@endsection

@section('css')
<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css"/>
<style>
.ai-legend-dot{display:inline-block;width:9px;height:9px;border-radius:50%;margin-right:4px;}
.ac-stat{text-align:center;padding:.6rem;border-radius:8px;background:rgba(255,255,255,.03)}
.ac-stat-value{font-size:1.6rem;font-weight:700;color:#f0c040}
.ac-stat-label{font-size:.72rem;color:rgba(255,255,255,.5);text-transform:uppercase;letter-spacing:.04em}

.ac-col-legend{font-size:.78rem;color:rgba(255,255,255,.6)}
.ac-col-legend summary{cursor:pointer;color:var(--gold);font-weight:600;list-style:revert}
.ac-col-legend summary:hover{color:#fff}
.ac-col-legend-grid{display:grid;grid-template-columns:repeat(2,1fr);gap:.4rem 1.2rem;margin-top:.7rem;padding:.8rem 1rem;background:rgba(255,255,255,.03);border-radius:8px}
.ac-col-legend-grid b{color:rgba(255,255,255,.85);font-family:monospace;font-weight:600}
.ac-col-legend-note{margin-top:.6rem;font-size:.74rem;color:rgba(255,255,255,.45);font-style:italic}
.ac-col-legend-note code{background:rgba(255,255,255,.06);padding:1px 5px;border-radius:4px;color:rgba(255,255,255,.7)}
@media(max-width:768px){.ac-col-legend-grid{grid-template-columns:1fr}}
</style>
@endsection

@section('js')
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.2/dist/chart.umd.min.js"></script>
<script>
const tooltip = { backgroundColor: '#0d2460', borderColor: 'rgba(240,192,64,0.4)', borderWidth: 1, titleColor: '#f0c040', bodyColor: 'rgba(255,255,255,0.8)', padding: 10 };
const gridColor = 'rgba(255,255,255,0.05)', tickColor = 'rgba(255,255,255,0.4)';

function baseMap(id) {
    const map = L.map(id).setView([45.15, 18.1], 8);
    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', { attribution: '© OpenStreetMap', maxZoom: 19 }).addTo(map);
    return map;
}

// The 5 cards below are loaded ONE AT A TIME (not in parallel): the ml-service
// dev process is single-worker, and firing all 5 requests at once (two of
// which — model comparison and prediction — retrain several models) starves
// the cheap requests and produces intermittent timeouts. Sequencing keeps
// every load reliable at the cost of a slightly longer total page load.

// ── 1. Dataset preview ────────────────────────────────────────────────────────
async function loadDataset() {
    try {
        const d = await fetch('{{ route('ai.zoneDatasetPreview') }}?rows=12').then(r => r.json());
        if (d.message) {
            document.getElementById('datasetBody').innerHTML = `<tr><td colspan="10" class="text-center py-3 text-muted">${d.message}</td></tr>`;
            return;
        }
        document.getElementById('dsRows').textContent = d.n_rows.toLocaleString();
        document.getElementById('dsPositive').textContent = d.n_positive.toLocaleString();
        document.getElementById('dsZones').textContent = d.n_zones;
        document.getElementById('dsRate').textContent = (d.n_positive / d.n_rows * 100).toFixed(2) + '%';

        const cols = d.columns;
        document.getElementById('datasetHead').innerHTML = cols.map(c => `<th>${c}</th>`).join('');
        document.getElementById('datasetBody').innerHTML = d.rows.map(row => '<tr>' +
            cols.map(c => `<td>${typeof row[c] === 'number' ? Math.round(row[c] * 100) / 100 : row[c]}</td>`).join('') + '</tr>'
        ).join('');
    } catch {
        document.getElementById('datasetBody').innerHTML = '<tr><td colspan="10" class="text-center py-3 text-muted">Greška pri učitavanju</td></tr>';
    }
}

// ── 2. DBSCAN zones map ──────────────────────────────────────────────────────
async function loadZonesMap() {
    const zonesMap = baseMap('zonesMap');
    try {
        const d = await fetch('{{ route('ai.clusters') }}').then(r => r.json());
        const clusters = d.clusters || [];
        document.getElementById('zonesSubtitle').textContent = `${clusters.length} žarišta (K1..K${clusters.length}) · ${d.total_detections ?? 0} detekcija`;
        clusters.forEach(cl => {
            const color = cl.risk >= 70 ? '#dc3545' : cl.risk >= 40 ? '#fd7e14' : '#f0c040';
            L.circle([cl.lat, cl.lon], { radius: Math.max(cl.radius_km * 1000, 500), color, fillColor: color, fillOpacity: 0.18, weight: 2 })
                .bindPopup(`<b>${cl.id}</b><br><small>${cl.count} detekcija · ${cl.total_individuals} entiteta<br>
                    Dominantna vrsta: ${cl.dominant_type}<br>Najaktivniji dan/blok: ${cl.most_active_dow ?? '—'} / blok ${cl.most_active_block ?? '—'}</small>`)
                .addTo(zonesMap);
            L.marker([cl.lat, cl.lon], { icon: L.divIcon({ className: '', html: `<div style="color:#fff;font-weight:700;font-size:11px;text-shadow:0 0 3px #000">${cl.id}</div>` }) }).addTo(zonesMap);
        });
        if (clusters.length) zonesMap.fitBounds(L.latLngBounds(clusters.map(c => [c.lat, c.lon])).pad(0.2));
    } catch {
        document.getElementById('zonesSubtitle').textContent = 'Greška pri učitavanju';
    }
}

// ── 3. Model comparison ──────────────────────────────────────────────────────
async function loadModelComparison() {
    try {
        const d = await fetch('{{ route('ai.zoneModelMetrics') }}').then(r => r.json());
        if (!d.trained) {
            document.getElementById('modelsSubtitle').textContent = d.message || 'Nedovoljno podataka';
            document.getElementById('modelsTable').innerHTML = `<tr><td colspan="7" class="text-center py-3 text-muted">${d.message || ''}</td></tr>`;
            return;
        }
        document.getElementById('modelsSubtitle').textContent =
            `${d.n_rows.toLocaleString()} redaka (${d.n_positive} pozitivnih) · trening/test split na ${d.split_date} · najbolji: ${d.models[d.best_model]?.label ?? d.best_model}`;

        const rows = Object.entries(d.models);
        document.getElementById('modelsTable').innerHTML = rows.map(([key, m]) => `
            <tr style="${key === d.best_model ? 'background:rgba(240,192,64,0.08)' : ''}">
                <td>${m.label}${key === d.best_model ? ' <i class=\"fas fa-star\" style=\"color:#f0c040\"></i>' : ''}</td>
                <td>${(m.accuracy * 100).toFixed(1)}%</td>
                <td>${(m.precision * 100).toFixed(1)}%</td>
                <td>${(m.recall * 100).toFixed(1)}%</td>
                <td>${(m.f1 * 100).toFixed(1)}%</td>
                <td>${m.roc_auc != null ? m.roc_auc.toFixed(3) : '—'}</td>
                <td><small>${m.confusion_matrix.tp}/${m.confusion_matrix.fp}/${m.confusion_matrix.fn}/${m.confusion_matrix.tn}</small></td>
            </tr>`).join('');

        const palette = ['#f0c040', '#dc3545', '#0d6efd', '#28a745', '#6f42c1'];
        const rocDatasets = rows.filter(([, m]) => m.roc_curve).map(([, m], i) => ({
            label: m.label, data: m.roc_curve.fpr.map((f, j) => ({ x: f, y: m.roc_curve.tpr[j] })),
            borderColor: palette[i % palette.length], backgroundColor: 'transparent', showLine: true, pointRadius: 0, borderWidth: 2,
        }));
        new Chart(document.getElementById('rocChart'), {
            type: 'scatter',
            data: { datasets: rocDatasets },
            options: { responsive: true, plugins: { title: { display: true, text: 'ROC krivulje', color: tickColor }, legend: { labels: { color: tickColor, font: { size: 10 } } }, tooltip },
                scales: { x: { title: { display: true, text: 'FPR', color: tickColor }, grid: { color: gridColor }, ticks: { color: tickColor }, min: 0, max: 1 },
                          y: { title: { display: true, text: 'TPR', color: tickColor }, grid: { color: gridColor }, ticks: { color: tickColor }, min: 0, max: 1 } } }
        });

        const prDatasets = rows.filter(([, m]) => m.pr_curve).map(([, m], i) => ({
            label: m.label, data: m.pr_curve.recall.map((r, j) => ({ x: r, y: m.pr_curve.precision[j] })),
            borderColor: palette[i % palette.length], backgroundColor: 'transparent', showLine: true, pointRadius: 0, borderWidth: 2,
        }));
        new Chart(document.getElementById('prChart'), {
            type: 'scatter',
            data: { datasets: prDatasets },
            options: { responsive: true, plugins: { title: { display: true, text: 'Precision–Recall krivulje', color: tickColor }, legend: { labels: { color: tickColor, font: { size: 10 } } }, tooltip },
                scales: { x: { title: { display: true, text: 'Recall', color: tickColor }, grid: { color: gridColor }, ticks: { color: tickColor }, min: 0, max: 1 },
                          y: { title: { display: true, text: 'Precision', color: tickColor }, grid: { color: gridColor }, ticks: { color: tickColor }, min: 0, max: 1 } } }
        });
    } catch {
        document.getElementById('modelsSubtitle').textContent = 'Greška pri učitavanju';
    }
}

// ── 4. Predictive map ────────────────────────────────────────────────────────
async function loadPredictiveMap() {
    const predMap = baseMap('predMap');
    const tierColor = { red: '#dc3545', orange: '#fd7e14', yellow: '#f0c040', green: '#28a745', insufficient_data: '#6c757d' };
    try {
        const d = await fetch('{{ route('ai.zonePredictions') }}').then(r => r.json());
        const zs = d.zones || [];
        document.getElementById('predMapSubtitle').textContent = `Model: ${d.model ?? '—'} · ${zs.length} zona`;
        zs.forEach(z => {
            const color = tierColor[z.tier] || '#6c757d';
            const label = z.probability !== null
                ? `Predviđena vjerojatnost detekcije: ${z.probability}%`
                : 'Nedovoljno podataka';
            L.circle([z.lat, z.lon], { radius: Math.max(z.radius_km * 1000, 500), color, fillColor: color, fillOpacity: 0.22, weight: 2 })
                .bindPopup(`<b>${z.id}</b><br><small>${label}<br>
                    Letovi kroz zonu: ${z.flights_count}<br>
                    Vrijeme nadzora: ${Math.floor(z.total_hours)} h ${Math.round((z.total_hours % 1) * 60)} min<br>
                    Povijesne detekcije: ${z.detections_count}<br>
                    Detekcije po satu: ${z.rate_per_hour}<br></small>`)
                .addTo(predMap);
        });
        if (zs.length) predMap.fitBounds(L.latLngBounds(zs.map(z => [z.lat, z.lon])).pad(0.2));
    } catch {
        document.getElementById('predMapSubtitle').textContent = 'Greška pri učitavanju';
    }
}

// ── 5. Recommendations ───────────────────────────────────────────────────────
async function loadRecommendations() {
    const recMap = baseMap('recMap');
    const recColor = { increase: '#dc3545', maintain: '#f0c040', consider_reduction: '#28a745', insufficient_data: '#6c757d' };
    const recLabel = { increase: 'Pojačati', maintain: 'Zadržati', consider_reduction: 'Razmotriti smanjenje', insufficient_data: 'Nedovoljno podataka' };
    try {
        const d = await fetch('{{ route('recommendations.dbscanZones') }}').then(r => r.json());
        const zs = d.zones || [];
        document.getElementById('recSubtitle').textContent = `${zs.length} zona`;
        zs.forEach(z => {
            const color = recColor[z.type] || '#6c757d';
            L.circle([z.lat, z.lon], { radius: Math.max(z.radius_km * 1000, 500), color, fillColor: color, fillOpacity: 0.22, weight: 2 })
                .bindPopup(`<b>${z.id}</b> — ${recLabel[z.type]}<br><small>${z.explanation}</small>`)
                .addTo(recMap);
        });
        if (zs.length) recMap.fitBounds(L.latLngBounds(zs.map(z => [z.lat, z.lon])).pad(0.2));

        document.getElementById('recTable').innerHTML = zs
            .sort((a, b) => (b.probability || 0) - (a.probability || 0))
            .map(z => `<tr>
                <td><b>${z.id}</b></td>
                <td><span style="color:${recColor[z.type]}">●</span> ${recLabel[z.type]}</td>
                <td>${z.probability !== null ? z.probability + '%' : '—'}</td>
                <td>${z.flights_count}</td>
                <td>${z.total_hours}</td>
                <td><small>${z.explanation}</small></td>
            </tr>`).join('');
    } catch {
        document.getElementById('recSubtitle').textContent = 'Greška pri učitavanju';
    }
}

// ══ Additional tools — outside the numbered academic pipeline ═══════════════

// ── Predict tool (heuristic, not a trained model) ────────────────────────────
const predictMap = L.map('predictMap').setView([45.15, 18.1], 9);
L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
    attribution: '© OpenStreetMap', maxZoom: 19
}).addTo(predictMap);

let predictMarker = null;

predictMap.on('click', e => {
    document.getElementById('pLat').value = e.latlng.lat.toFixed(5);
    document.getElementById('pLon').value = e.latlng.lng.toFixed(5);
    if (predictMarker) predictMarker.remove();
    predictMarker = L.circleMarker([e.latlng.lat, e.latlng.lng], {
        radius: 7, color:'#6f42c1', fillColor:'#6f42c1', fillOpacity:0.8, weight:2
    }).addTo(predictMap);
});

document.getElementById('predictBtn').addEventListener('click', async () => {
    const lat  = parseFloat(document.getElementById('pLat').value);
    const lon  = parseFloat(document.getElementById('pLon').value);
    const hour = parseInt(document.getElementById('pHour').value);
    const dow  = parseInt(document.getElementById('pDow').value);

    if (isNaN(lat) || isNaN(lon)) {
        alert('Unesite koordinate ili kliknite na kartu.');
        return;
    }

    const btn = document.getElementById('predictBtn');
    btn.disabled = true;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i>Računam...';

    try {
        const res = await fetch(`/ai/predict?lat=${lat}&lon=${lon}&hour=${hour}&dow=${dow}`);
        const d   = await res.json();

        const color = d.risk >= 70 ? '#dc3545' : (d.risk >= 40 ? '#fd7e14' : (d.risk >= 20 ? '#f0c040' : '#28a745'));
        document.getElementById('riskScore').style.color = color;
        document.getElementById('riskScore').textContent = d.risk;
        document.getElementById('riskLevel').style.color = color;
        document.getElementById('riskLevel').textContent = d.level;
        document.getElementById('fSpatial').textContent  = d.factors?.prostorni ?? 0;
        document.getElementById('fTemporal').textContent = d.factors?.vremenski ?? 0;
        document.getElementById('fNearby').textContent   = (d.nearby_count ?? 0) + ' det.';

        document.getElementById('riskResult').style.display = 'block';
        document.getElementById('riskEmpty').style.display  = 'none';

        if (predictMarker) predictMarker.remove();
        predictMarker = L.circle([lat, lon], {
            radius: 3000, color, fillColor: color, fillOpacity: 0.2, weight: 2
        }).addTo(predictMap);
        predictMap.setView([lat, lon], 11);
    } catch(e) {
        alert('Greška pri spajanju na ML servis.');
    } finally {
        btn.disabled = false;
        btn.innerHTML = '<i class="fas fa-calculator mr-2"></i>Procijeni rizik';
    }
});

// ── Predictive risk grid (RandomForest, mrežni pristup) ──────────────────────
const riskGridMap = L.map('riskGridMap').setView([45.15, 18.1], 9);
L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
    attribution: '© OpenStreetMap', maxZoom: 19
}).addTo(riskGridMap);

function riskFillColor(r) {
    if (r >= 70) return '#dc3545';
    if (r >= 40) return '#fd7e14';
    if (r >= 20) return '#f0c040';
    return '#28a745';
}

function renderRecList(el, items, kind) {
    if (!items.length) {
        el.innerHTML = '<p class="text-muted text-center py-4 mb-0">Nema preporuka</p>';
        return;
    }
    el.innerHTML = items.map((c, i) => `
        <div class="db-stat-row" style="${i === items.length - 1 ? 'border:none' : ''}">
            <div class="db-stat-icon" style="background:${kind === 'up' ? 'rgba(220,53,69,0.2)' : 'rgba(40,167,69,0.2)'};color:${kind === 'up' ? '#f5a0a0' : '#7ddc9f'}">
                <i class="fas ${kind === 'up' ? 'fa-arrow-up' : 'fa-arrow-down'}"></i>
            </div>
            <div style="flex:1;min-width:0">
                <div style="font-size:.82rem;color:#fff;font-weight:600">${c.lat.toFixed(4)}, ${c.lon.toFixed(4)}</div>
                <div style="font-size:.72rem;color:rgba(255,255,255,.4)">Rizik ${c.risk} · Pokrivenost ${c.coverage}</div>
            </div>
        </div>
    `).join('');
}

async function loadRiskGrid() {
    try {
        const d = await fetch('{{ route('ai.riskGrid') }}').then(r => r.json());
        const status = document.getElementById('riskGridStatus');
        if (d.message) {
            status.textContent = d.message;
            document.getElementById('increaseList').innerHTML = `<p class="text-muted text-center py-4 mb-0">${d.message}</p>`;
            document.getElementById('decreaseList').innerHTML = `<p class="text-muted text-center py-4 mb-0">${d.message}</p>`;
            return;
        }
        status.textContent = `Trenirano na ${d.trained_on} detekcija · ćelija ${d.cell_km} km`;

        if (d.border) {
            ['hr_bih', 'hr_srb'].forEach(key => {
                L.polyline(d.border[key], { color: '#0d2460', weight: 2.5, opacity: 0.8, dashArray: '4,5' }).addTo(riskGridMap);
            });
        }

        const CELL_SCALE = 0.7; // shrink rendered cells a bit so they read as distinct squares, not a solid blob
        const halfLat = (d.cell_km / 111) / 2 * CELL_SCALE;
        (d.cells || []).forEach(c => {
            if (c.risk < 8) return;
            const halfLon = halfLat / Math.max(Math.cos(c.lat * Math.PI / 180), 0.2);
            L.rectangle([[c.lat - halfLat, c.lon - halfLon], [c.lat + halfLat, c.lon + halfLon]], {
                color: 'transparent', fillColor: riskFillColor(c.risk), fillOpacity: Math.min(0.6, c.risk / 140),
                weight: 0
            }).bindPopup(`<b>Rizik: ${c.risk}/100</b><br><small>Pokrivenost: ${c.coverage}</small>`).addTo(riskGridMap);
        });

        (d.recommend_increase || []).forEach(c => {
            const bearing = c.bearing ?? 0;
            L.marker([c.lat, c.lon], {
                icon: L.divIcon({
                    className: '',
                    html: `<i class="fas fa-arrow-up" style="color:#dc3545;font-size:16px;text-shadow:0 0 4px #000;display:inline-block;transform:rotate(${bearing}deg)"></i>`
                })
            }).bindPopup(`<b>Pojačati nadzor</b><br><small>Rizik: ${c.risk}/100</small>`).addTo(riskGridMap);
        });

        if (d.cells && d.cells.length) {
            const bounds = L.latLngBounds(d.cells.map(c => [c.lat, c.lon]));
            riskGridMap.fitBounds(bounds.pad(0.05));
        }

        renderRecList(document.getElementById('increaseList'), d.recommend_increase || [], 'up');
        renderRecList(document.getElementById('decreaseList'), d.recommend_decrease || [], 'down');
    } catch {
        document.getElementById('riskGridStatus').textContent = 'Greška pri učitavanju';
    }
}

(async function loadAcademicPipeline() {
    await loadDataset();
    await loadZonesMap();
    await loadModelComparison();
    await loadPredictiveMap();
    await loadRecommendations();
    await loadRiskGrid();
})();
</script>
@endsection
