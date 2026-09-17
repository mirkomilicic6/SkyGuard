@extends('adminlte::page')

@section('title', 'AI asistent')

@section('content_header')
    <h1><i class="fas fa-robot mr-2" style="color:#f0c040"></i>AI asistent</h1>
@endsection

@section('content')

<div class="ac-chat-card">
    <div id="acChatMessages" class="ac-chat-messages">
        <div class="ac-chat-msg ac-chat-msg-bot">
            Pozdrav! Mogu ti pomoći s pitanjima o rizičnim zonama, preporukama za nadzor/letove i statistici detekcija. Pitaj me nešto, npr. "Gdje bismo trebali pojačati nadzor?"
        </div>
    </div>
    <div class="ac-chat-input-row">
        <input id="acChatInput" type="text" class="form-control" placeholder="Postavi pitanje..." autocomplete="off">
        <button id="acChatSend" class="ac-chat-send"><i class="fas fa-paper-plane mr-2"></i>Pošalji</button>
    </div>
</div>

<div id="acChatMapModal" class="ac-chat-map-modal" style="display:none">
    <div class="ac-chat-map-modal-backdrop"></div>
    <div class="ac-chat-map-modal-inner">
        <div class="ac-chat-map-modal-header">
            <div><i class="fas fa-map-marked-alt mr-2" style="color:var(--gold)"></i>Prikaz na karti</div>
            <button id="acChatMapModalClose" class="ac-chat-close">&times;</button>
        </div>
        <div id="acChatMapModalMap" class="ac-chat-map-modal-map"></div>
        <div id="acChatMapModalLegend" class="ac-chat-map-legend px-1 pb-2"></div>
    </div>
</div>

<style>
.ac-chat-card {
    background: #0d2460; border: 1px solid rgba(240,192,64,0.3); border-radius: 14px;
    box-shadow: 0 8px 32px rgba(0,0,0,0.35); display: flex; flex-direction: column; overflow: hidden;
    height: 74vh; min-height: 460px;
}
.ac-chat-messages { flex: 1; overflow-y: auto; padding: 1.4rem; display: flex; flex-direction: column; gap: .9rem; }
.ac-chat-msg { font-size: 1rem; line-height: 1.5; padding: .75rem 1rem; border-radius: 12px; max-width: 75%; white-space: pre-wrap; }
.ac-chat-msg-bot { background: rgba(255,255,255,0.06); color: rgba(255,255,255,0.9); align-self: flex-start; }
.ac-chat-msg-user { background: rgba(240,192,64,0.18); color: #fff; align-self: flex-end; }
.ac-chat-msg-error { background: rgba(220,53,69,0.18); color: #f5a0a0; align-self: flex-start; }
.ac-chat-input-row { display: flex; gap: .7rem; padding: 1rem 1.2rem; border-top: 1px solid rgba(255,255,255,0.08); }
.ac-chat-input-row input {
    background: rgba(255,255,255,0.05); border: 1px solid rgba(255,255,255,0.1); color: #fff;
    border-radius: 8px; font-size: 1rem; padding: .6rem .9rem;
}
.ac-chat-input-row input:focus { background: rgba(255,255,255,0.08); color: #fff; box-shadow: none; border-color: rgba(240,192,64,0.4); }
.ac-chat-send {
    background: rgba(240,192,64,0.15); border: 1px solid rgba(240,192,64,0.4); color: var(--gold);
    border-radius: 8px; padding: 0 1.3rem; flex-shrink: 0; cursor: pointer; font-size: 1rem;
}
.ac-chat-send:hover { background: rgba(240,192,64,0.28); }
.ac-chat-send:disabled { opacity: .5; cursor: default; }

.ac-chat-map-wrap { align-self: stretch; position: relative; }
.ac-chat-map { height: 340px; border-radius: 10px; overflow: hidden; border: 1px solid rgba(255,255,255,0.1); }
.ac-chat-map-legend { display: flex; flex-wrap: wrap; gap: .8rem; font-size: .8rem; color: rgba(255,255,255,.5); margin-top: .5rem; }
.ac-chat-map-legend span { display: inline-flex; align-items: center; gap: 5px; }
.ac-chat-map-legend i { width: 9px; height: 9px; border-radius: 50%; display: inline-block; }
.leaflet-popup-content-wrapper, .leaflet-popup-tip { background: #0d2460; color: #fff; }
.leaflet-popup-content { font-size: .85rem; margin: .6rem .8rem; }

.ac-chat-map-expand {
    position: absolute; top: 10px; right: 10px; z-index: 500;
    width: 32px; height: 32px; border-radius: 7px;
    background: rgba(13,36,96,0.85); border: 1px solid rgba(255,255,255,0.2); color: #fff;
    display: flex; align-items: center; justify-content: center; font-size: .85rem; cursor: pointer;
}
.ac-chat-map-expand:hover { background: rgba(13,36,96,1); border-color: var(--gold); color: var(--gold); }

.ac-chat-map-modal { position: fixed; inset: 0; z-index: 2000; display: flex; align-items: center; justify-content: center; }
.ac-chat-map-modal-backdrop { position: absolute; inset: 0; background: rgba(0,0,0,0.6); }
.ac-chat-map-modal-inner {
    position: relative; width: min(1000px, 94vw); max-height: 90vh;
    background: #0d2460; border: 1px solid rgba(240,192,64,0.3); border-radius: 14px;
    box-shadow: 0 12px 48px rgba(0,0,0,0.6); overflow: hidden; display: flex; flex-direction: column;
}
.ac-chat-map-modal-header { display: flex; align-items: center; justify-content: space-between; padding: .9rem 1.2rem; font-weight: 600; color: #fff; border-bottom: 1px solid rgba(255,255,255,0.08); }
.ac-chat-close { background: none; border: none; color: rgba(255,255,255,0.5); font-size: 1.4rem; line-height: 1; cursor: pointer; }
.ac-chat-close:hover { color: #fff; }
.ac-chat-map-modal-map { width: 100%; height: min(74vh, 680px); }
</style>

<script>
(function () {
    const messages = document.getElementById('acChatMessages');
    const input    = document.getElementById('acChatInput');
    const sendBtn  = document.getElementById('acChatSend');

    let history = [];
    let busy = false;
    let mapSeq = 0;
    let modalMap = null;

    const modal       = document.getElementById('acChatMapModal');
    const modalClose  = document.getElementById('acChatMapModalClose');
    const modalLegend = document.getElementById('acChatMapModalLegend');

    const MAP_KIND = {
        increase:   { color: '#dc3545', label: 'Pojačati nadzor' },
        decrease:   { color: '#28a745', label: 'Smanjiti nadzor' },
        cluster:    { color: '#fd7e14', label: 'Klaster detekcija' },
        prediction: { color: '#6f42c1', label: 'Procjena lokacije' },
        station:    { color: '#f0c040', label: 'Vaša postaja' },
    };

    function legendHtml(points) {
        const kindsUsed = [...new Set(points.map(p => p.kind))];
        return kindsUsed.map(k => `<span><i style="background:${(MAP_KIND[k] || {}).color || '#6f42c1'}"></i>${(MAP_KIND[k] || {}).label || k}</span>`).join('');
    }

    function addMarkersTo(map, points) {
        return points.map(p => {
            const style = MAP_KIND[p.kind] || { color: '#6f42c1', label: p.kind };

            if (p.kind === 'station') {
                const marker = L.marker([p.lat, p.lon], {
                    icon: L.divIcon({
                        className: '',
                        html: `<div style="width:16px;height:16px;background:${style.color};border:2px solid #fff;box-shadow:0 0 3px rgba(0,0,0,.5)"></div>`,
                        iconSize: [16, 16], iconAnchor: [8, 8],
                    }),
                }).addTo(map);
                marker.bindPopup(`<strong>${style.label}</strong><br>${p.value ?? ''}`);
                return marker;
            }

            const marker = L.circleMarker([p.lat, p.lon], {
                radius: 9, color: style.color, fillColor: style.color, fillOpacity: 0.75, weight: 2,
            }).addTo(map);
            const valueLine = (p.value ?? null) !== null ? `<br>Rizik: ${p.value}` : '';
            const nearLine = p.near ? `<br>${p.near}` : '';
            marker.bindPopup(`<strong>${style.label}</strong>${nearLine}<br><small>${p.lat.toFixed(4)}, ${p.lon.toFixed(4)}</small>${valueLine}`);
            return marker;
        });
    }

    function fitToMarkers(map, markers) {
        if (markers.length === 1) {
            map.setView(markers[0].getLatLng(), 13);
        } else {
            map.fitBounds(L.featureGroup(markers).getBounds().pad(0.25));
        }
    }

    function openMapModal(points) {
        modalLegend.innerHTML = legendHtml(points);
        modal.style.display = 'flex';

        loadLeaflet().then(() => {
            if (modalMap) {
                modalMap.remove();
                modalMap = null;
            }
            modalMap = L.map('acChatMapModalMap', { zoomControl: true, scrollWheelZoom: true });
            L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', { maxZoom: 19, attribution: '© OpenStreetMap' }).addTo(modalMap);
            const markers = addMarkersTo(modalMap, points);
            setTimeout(() => {
                modalMap.invalidateSize();
                fitToMarkers(modalMap, markers);
            }, 50);
        });
    }

    function closeMapModal() { modal.style.display = 'none'; }

    modalClose.addEventListener('click', closeMapModal);
    modal.querySelector('.ac-chat-map-modal-backdrop').addEventListener('click', closeMapModal);
    document.addEventListener('keydown', (e) => {
        if (e.key === 'Escape' && modal.style.display !== 'none') closeMapModal();
    });

    function loadLeaflet() {
        if (window.L) return Promise.resolve();
        if (window.__leafletLoading) return window.__leafletLoading;

        window.__leafletLoading = new Promise((resolve) => {
            const css = document.createElement('link');
            css.rel = 'stylesheet';
            css.href = 'https://unpkg.com/leaflet@1.9.4/dist/leaflet.css';
            document.head.appendChild(css);

            const script = document.createElement('script');
            script.src = 'https://unpkg.com/leaflet@1.9.4/dist/leaflet.js';
            script.onload = resolve;
            document.head.appendChild(script);
        });
        return window.__leafletLoading;
    }

    function renderMapPoints(points) {
        const validPoints = points.filter(p => Number.isFinite(p.lat) && Number.isFinite(p.lon));
        if (!validPoints.length) return;

        const wrap = document.createElement('div');
        wrap.className = 'ac-chat-msg ac-chat-msg-bot ac-chat-map-wrap';
        const mapId = 'acChatMap' + (++mapSeq);
        wrap.innerHTML = `
            <div id="${mapId}" class="ac-chat-map"></div>
            <button type="button" class="ac-chat-map-expand" title="Prikaži veću kartu"><i class="fas fa-expand"></i></button>
            <div class="ac-chat-map-legend">${legendHtml(validPoints)}</div>
        `;
        messages.appendChild(wrap);
        messages.scrollTop = messages.scrollHeight;

        wrap.querySelector('.ac-chat-map-expand').addEventListener('click', () => openMapModal(validPoints));

        loadLeaflet().then(() => {
            const map = L.map(mapId, { zoomControl: true, attributionControl: false, scrollWheelZoom: false });
            L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', { maxZoom: 19, attribution: '© OpenStreetMap' }).addTo(map);
            const markers = addMarkersTo(map, validPoints);
            fitToMarkers(map, markers);
            messages.scrollTop = messages.scrollHeight;
        });
    }

    function addMessage(text, cls) {
        const div = document.createElement('div');
        div.className = 'ac-chat-msg ' + cls;
        div.textContent = text;
        messages.appendChild(div);
        messages.scrollTop = messages.scrollHeight;
        return div;
    }

    async function send() {
        const text = input.value.trim();
        if (!text || busy) return;

        addMessage(text, 'ac-chat-msg-user');
        input.value = '';
        busy = true;
        sendBtn.disabled = true;
        const thinking = addMessage('...', 'ac-chat-msg-bot');

        try {
            const res = await fetch('{{ route('chat.respond') }}', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': '{{ csrf_token() }}',
                    'Accept': 'application/json',
                },
                body: JSON.stringify({ message: text, history }),
            });
            const data = await res.json();

            if (!res.ok) {
                thinking.textContent = data.error || 'Greška pri komunikaciji s AI servisom.';
                thinking.className = 'ac-chat-msg ac-chat-msg-error';
            } else {
                thinking.textContent = data.reply;
                history = data.history || history;
                if (data.mapPoints && data.mapPoints.length) {
                    renderMapPoints(data.mapPoints);
                }
            }
        } catch (e) {
            thinking.textContent = 'Greška pri spajanju na server.';
            thinking.className = 'ac-chat-msg ac-chat-msg-error';
        } finally {
            busy = false;
            sendBtn.disabled = false;
            input.focus();
        }
    }

    sendBtn.addEventListener('click', send);
    input.addEventListener('keydown', e => { if (e.key === 'Enter') send(); });

    // A conversation handed off from the floating widget (see
    // partials/ai-chat-widget.blade.php's "expand" button) arrives here via
    // sessionStorage: the visible transcript to re-render, plus the raw API
    // `history` needed to keep the conversation going server-side.
    try {
        const raw = sessionStorage.getItem('aiChatTransfer');
        if (raw) {
            sessionStorage.removeItem('aiChatTransfer');
            const data = JSON.parse(raw);
            if (data.transcript && data.transcript.length) {
                messages.innerHTML = '';
                data.transcript.forEach(m => addMessage(m.text, 'ac-chat-msg-' + m.kind));
            }
            if (data.history) {
                history = data.history;
            }
        }
    } catch (e) { /* ignore malformed/unavailable transfer data */ }

    input.focus();
})();
</script>

@endsection
