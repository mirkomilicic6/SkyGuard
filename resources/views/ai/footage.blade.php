@extends('adminlte::page')

@section('title', 'Analiza snimke')

@section('content_header')
    <h1><i class="fas fa-video mr-2" style="color:#f0c040"></i>Analiza snimke</h1>
@endsection

@section('content')

<div class="db-card mb-4">
    <div class="db-card-header">
        <div>
            <div class="db-card-title"><i class="fas fa-street-view mr-2" style="color:var(--gold)"></i>Detekcija osoba i životinja na videu (YOLOv8)</div>
            <div class="db-card-subtitle">
                Pretrenirani model (COCO težine) — nema treniranja, samo prepoznavanje na već naučenim obrascima. Prepoznaje osobu
                (zeleni okvir) te pticu, mačku, psa, konja, ovcu, kravu i medvjeda (narančasti okvir). Video se ne obrađuje
                sličicu-po-sličicu (presporo na CPU-u) nego se uzorkuje ~2 sličice u sekundi; okviri se prikazuju sinkronizirano
                s reprodukcijom, pa izgleda kao "uživo" praćenje iako je cijela obrada gotova prije nego što video krene.
                Za termalne/infracrvene (FLIR) snimke uključite opciju ispod — običan model loše prepoznaje na termalnim snimkama
                jer izgledaju posve drukčije (toplinski potpis, ne boja/tekstura); termalni model prepoznaje samo osobu.
            </div>
        </div>
    </div>
    <div class="db-card-body">
        <div class="mb-3 d-flex align-items-center" style="gap:.6rem;flex-wrap:wrap">
            <input type="file" id="footageInput" accept="video/*" class="form-control form-control-sm" style="max-width:340px">
            <div class="form-check" style="padding-left:1.6rem">
                <input type="checkbox" id="footageThermal" class="form-check-input">
                <label for="footageThermal" class="form-check-label" style="font-size:.85rem;color:rgba(255,255,255,.7)">
                    <i class="fas fa-temperature-three-quarters mr-1"></i>Termalna/infracrvena (FLIR) snimka
                </label>
            </div>
            <button id="footageAnalyzeBtn" class="btn btn-sm" style="background:rgba(240,192,64,0.15);border:1px solid rgba(240,192,64,0.4);color:var(--gold);border-radius:8px" disabled>
                <i class="fas fa-magnifying-glass mr-1"></i>Analiziraj
            </button>
        </div>
        <div id="footageStatus" style="font-size:.83rem;color:rgba(255,255,255,.55);margin-bottom:.8rem"></div>
        <div id="footageStage" style="position:relative;display:none;max-width:900px">
            <video id="footageVideo" style="width:100%;display:block;border-radius:10px;background:#000" controls></video>
            <canvas id="footageCanvas" style="position:absolute;top:0;left:0;width:100%;height:100%;pointer-events:none"></canvas>
        </div>
    </div>
</div>

@endsection

@section('js')
<script>
(function () {
    const input   = document.getElementById('footageInput');
    const thermal = document.getElementById('footageThermal');
    const btn     = document.getElementById('footageAnalyzeBtn');
    const status  = document.getElementById('footageStatus');
    const stage   = document.getElementById('footageStage');
    const video   = document.getElementById('footageVideo');
    const canvas  = document.getElementById('footageCanvas');
    const ctx     = canvas.getContext('2d');

    let frames = [];
    let rafId  = null;

    input.addEventListener('change', () => {
        const file = input.files[0];
        btn.disabled = !file;
        frames = [];
        status.textContent = '';
        if (!file) {
            stage.style.display = 'none';
            return;
        }
        video.src = URL.createObjectURL(file);
        stage.style.display = 'block';
    });

    // Boxes are only known for the ~2/s sampled frames the video was
    // analysed at — hold whichever sample is closest to the current
    // playback time rather than trying to interpolate between them.
    function nearestFrame(t) {
        if (!frames.length) return null;
        let best = frames[0];
        for (const f of frames) {
            if (Math.abs(f.time - t) < Math.abs(best.time - t)) best = f;
        }
        return best;
    }

    function drawLoop() {
        if (canvas.width !== video.clientWidth || canvas.height !== video.clientHeight) {
            canvas.width = video.clientWidth;
            canvas.height = video.clientHeight;
        }
        ctx.clearRect(0, 0, canvas.width, canvas.height);

        const f = nearestFrame(video.currentTime);
        if (f) {
            f.boxes.forEach(b => {
                const x = b.x1 * canvas.width, y = b.y1 * canvas.height;
                const w = (b.x2 - b.x1) * canvas.width, h = (b.y2 - b.y1) * canvas.height;
                const color = b.label === 'Osoba' ? '#28a745' : '#fd7e14';

                ctx.strokeStyle = color;
                ctx.lineWidth = 2;
                ctx.strokeRect(x, y, w, h);

                const label = `${b.label} ${b.confidence}%`;
                ctx.font = '13px sans-serif';
                const textW = ctx.measureText(label).width;
                ctx.fillStyle = color;
                ctx.globalAlpha = 0.85;
                ctx.fillRect(x, Math.max(0, y - 18), textW + 8, 18);
                ctx.globalAlpha = 1;
                ctx.fillStyle = '#fff';
                ctx.fillText(label, x + 4, Math.max(12, y - 5));
            });
        }

        if (!video.paused && !video.ended) {
            rafId = requestAnimationFrame(drawLoop);
        }
    }

    video.addEventListener('play', () => {
        cancelAnimationFrame(rafId);
        drawLoop();
    });
    video.addEventListener('pause', () => cancelAnimationFrame(rafId));
    video.addEventListener('seeked', drawLoop);

    btn.addEventListener('click', async () => {
        const file = input.files[0];
        if (!file || btn.disabled) return;

        btn.disabled = true;
        input.disabled = true;
        status.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i>Obrada u tijeku (YOLOv8, uzorkovanje ~2 sličice/sekundi) — može potrajati ovisno o duljini videa...';

        const formData = new FormData();
        formData.append('video', file);
        formData.append('thermal', thermal.checked ? '1' : '0');

        try {
            const res = await fetch('{{ route('ai.footageAnalysis.analyze') }}', {
                method: 'POST',
                headers: { 'X-CSRF-TOKEN': '{{ csrf_token() }}' },
                body: formData,
            });
            const data = await res.json();

            if (!res.ok || data.error) {
                status.textContent = data.error || 'Greška pri obradi videa.';
            } else {
                frames = data.frames || [];
                const totalBoxes = frames.reduce((sum, f) => sum + f.boxes.length, 0);
                status.textContent = `Gotovo — analizirano ${data.frame_count} sličica (${data.sample_fps}/s), ukupno ${totalBoxes} detekcija kroz video. Pokrenite reprodukciju za prikaz okvira.`;
            }
        } catch (e) {
            status.textContent = 'Greška pri spajanju na server.';
        } finally {
            btn.disabled = false;
            input.disabled = false;
        }
    });
})();
</script>
@endsection
