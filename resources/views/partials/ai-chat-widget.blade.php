{{-- ── AI asistent — floating chat widget ──────────────────────────────── --}}
<button id="aiChatToggle" class="ai-chat-toggle" title="AI asistent">
    <i class="fas fa-robot"></i>
</button>

<div id="aiChatPanel" class="ai-chat-panel" style="display:none">
    <div class="ai-chat-header">
        <div><i class="fas fa-robot mr-2" style="color:var(--gold)"></i>AI asistent</div>
        <div class="ai-chat-header-actions">
            <button id="aiChatExpand" class="ai-chat-icon-btn" title="Otvori u punom prikazu"><i class="fas fa-expand-alt"></i></button>
            <button id="aiChatClose" class="ai-chat-close">&times;</button>
        </div>
    </div>
    <div id="aiChatMessages" class="ai-chat-messages">
        <div class="ai-chat-msg ai-chat-msg-bot">
            Pozdrav! Mogu ti pomoći s pitanjima o rizičnim zonama, preporukama za nadzor/letove i statistici detekcija. Pitaj me nešto, npr. "Gdje bismo trebali pojačati nadzor?"
        </div>
    </div>
    <div class="ai-chat-input-row">
        <input id="aiChatInput" type="text" class="form-control" placeholder="Postavi pitanje..." autocomplete="off">
        <button id="aiChatSend" class="ai-chat-send"><i class="fas fa-paper-plane"></i></button>
    </div>
</div>

<div id="aiChatMapModal" class="ai-chat-map-modal" style="display:none">
    <div class="ai-chat-map-modal-backdrop"></div>
    <div class="ai-chat-map-modal-inner">
        <div class="ai-chat-map-modal-header">
            <div><i class="fas fa-map-marked-alt mr-2" style="color:var(--gold)"></i>Prikaz na karti</div>
            <button id="aiChatMapModalClose" class="ai-chat-close">&times;</button>
        </div>
        <div id="aiChatMapModalMap" class="ai-chat-map-modal-map"></div>
        <div id="aiChatMapModalLegend" class="ai-chat-map-legend px-1 pb-2"></div>
    </div>
</div>

<style>
/* ── AI chat widget ───────────────────────────────────────────── */
.ai-chat-toggle {
    position: fixed; bottom: 24px; right: 24px; z-index: 1050;
    width: 56px; height: 56px; border-radius: 50%;
    background: var(--gold); color: #0d2460; border: none;
    font-size: 1.4rem; box-shadow: 0 4px 16px rgba(0,0,0,0.35);
    cursor: pointer;
}
.ai-chat-toggle:hover { filter: brightness(1.08); }
.ai-chat-panel {
    position: fixed; bottom: 92px; right: 24px; z-index: 1050;
    width: 360px; max-width: calc(100vw - 32px); height: 480px;
    background: #0d2460; border: 1px solid rgba(240,192,64,0.3);
    border-radius: 14px; box-shadow: 0 8px 32px rgba(0,0,0,0.5);
    display: flex; flex-direction: column; overflow: hidden;
}
.ai-chat-header {
    display: flex; align-items: center; justify-content: space-between;
    padding: .8rem 1rem; font-weight: 600; color: #fff;
    border-bottom: 1px solid rgba(255,255,255,0.08);
}
.ai-chat-header-actions { display: flex; align-items: center; gap: .6rem; }
.ai-chat-close { background: none; border: none; color: rgba(255,255,255,0.5); font-size: 1.4rem; line-height: 1; cursor: pointer; }
.ai-chat-close:hover { color: #fff; }
.ai-chat-icon-btn { background: none; border: none; color: rgba(255,255,255,0.5); font-size: 1rem; line-height: 1; cursor: pointer; }
.ai-chat-icon-btn:hover { color: var(--gold); }
.ai-chat-messages { flex: 1; overflow-y: auto; padding: .9rem; display: flex; flex-direction: column; gap: .6rem; }
.ai-chat-msg { font-size: .85rem; line-height: 1.4; padding: .5rem .75rem; border-radius: 10px; max-width: 90%; white-space: pre-wrap; }
.ai-chat-msg-bot { background: rgba(255,255,255,0.06); color: rgba(255,255,255,0.85); align-self: flex-start; }
.ai-chat-msg-user { background: rgba(240,192,64,0.18); color: #fff; align-self: flex-end; }
.ai-chat-msg-error { background: rgba(220,53,69,0.18); color: #f5a0a0; align-self: flex-start; }
.ai-chat-input-row { display: flex; gap: .5rem; padding: .7rem; border-top: 1px solid rgba(255,255,255,0.08); }
.ai-chat-input-row input {
    background: rgba(255,255,255,0.05); border: 1px solid rgba(255,255,255,0.1); color: #fff;
    border-radius: 8px; font-size: .85rem;
}
.ai-chat-input-row input:focus { background: rgba(255,255,255,0.08); color: #fff; box-shadow: none; border-color: rgba(240,192,64,0.4); }
.ai-chat-send {
    background: rgba(240,192,64,0.15); border: 1px solid rgba(240,192,64,0.4); color: var(--gold);
    border-radius: 8px; width: 40px; flex-shrink: 0; cursor: pointer;
}
.ai-chat-send:hover { background: rgba(240,192,64,0.28); }
.ai-chat-send:disabled { opacity: .5; cursor: default; }

.ai-chat-map-wrap { align-self: stretch; position: relative; }
.ai-chat-map {
    height: 190px;
    border-radius: 10px;
    overflow: hidden;
    border: 1px solid rgba(255,255,255,0.1);
}
.ai-chat-map-legend {
    display: flex; flex-wrap: wrap; gap: .6rem;
    font-size: .68rem; color: rgba(255,255,255,.45);
    margin-top: .4rem;
}
.ai-chat-map-legend span { display: inline-flex; align-items: center; gap: 4px; }
.ai-chat-map-legend i { width: 8px; height: 8px; border-radius: 50%; display: inline-block; }
.leaflet-popup-content-wrapper, .leaflet-popup-tip { background: #0d2460; color: #fff; }
.leaflet-popup-content { font-size: .8rem; margin: .6rem .8rem; }

.ai-chat-map-expand {
    position: absolute; top: 8px; right: 8px; z-index: 500;
    width: 28px; height: 28px; border-radius: 7px;
    background: rgba(13,36,96,0.85); border: 1px solid rgba(255,255,255,0.2); color: #fff;
    display: flex; align-items: center; justify-content: center; font-size: .78rem; cursor: pointer;
}
.ai-chat-map-expand:hover { background: rgba(13,36,96,1); border-color: var(--gold); color: var(--gold); }

/* ── Expanded map modal ──────────────────────────────────────── */
.ai-chat-map-modal { position: fixed; inset: 0; z-index: 2000; display: flex; align-items: center; justify-content: center; }
.ai-chat-map-modal-backdrop { position: absolute; inset: 0; background: rgba(0,0,0,0.6); }
.ai-chat-map-modal-inner {
    position: relative; width: min(900px, 92vw); max-height: 88vh;
    background: #0d2460; border: 1px solid rgba(240,192,64,0.3); border-radius: 14px;
    box-shadow: 0 12px 48px rgba(0,0,0,0.6); overflow: hidden; display: flex; flex-direction: column;
}
.ai-chat-map-modal-header {
    display: flex; align-items: center; justify-content: space-between;
    padding: .8rem 1rem; font-weight: 600; color: #fff;
    border-bottom: 1px solid rgba(255,255,255,0.08);
}
.ai-chat-map-modal-map { width: 100%; height: min(70vh, 620px); }
</style>

<script>
// ── AI chat widget ────────────────────────────────────────────────────────
(function () {
    const toggle    = document.getElementById('aiChatToggle');
    const panel     = document.getElementById('aiChatPanel');
    const closeBtn  = document.getElementById('aiChatClose');
    const expandBtn = document.getElementById('aiChatExpand');
    const messages  = document.getElementById('aiChatMessages');
    const input     = document.getElementById('aiChatInput');
    const sendBtn   = document.getElementById('aiChatSend');

    let history = [];
    let busy = false;
    let mapSeq = 0;
    let modalMap = null;

    const modal      = document.getElementById('aiChatMapModal');
    const modalClose = document.getElementById('aiChatMapModalClose');
    const modalLegend = document.getElementById('aiChatMapModalLegend');

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

            // The user's own station is a fixed reference point, not a
            // recommendation — draw it distinctly (square icon, name instead
            // of a risk score) so it reads as "you are here", not another pin.
            if (p.kind === 'station') {
                const marker = L.marker([p.lat, p.lon], {
                    icon: L.divIcon({
                        className: '',
                        html: `<div style="width:14px;height:14px;background:${style.color};border:2px solid #fff;box-shadow:0 0 3px rgba(0,0,0,.5)"></div>`,
                        iconSize: [14, 14], iconAnchor: [7, 7],
                    }),
                }).addTo(map);
                marker.bindPopup(`<strong>${style.label}</strong><br>${p.value ?? ''}`);
                return marker;
            }

            const marker = L.circleMarker([p.lat, p.lon], {
                radius: 8, color: style.color, fillColor: style.color, fillOpacity: 0.75, weight: 2,
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
            modalMap = L.map('aiChatMapModalMap', { zoomControl: true, scrollWheelZoom: true });
            L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', { maxZoom: 19, attribution: '© OpenStreetMap' }).addTo(modalMap);
            const markers = addMarkersTo(modalMap, points);
            setTimeout(() => {
                modalMap.invalidateSize();
                fitToMarkers(modalMap, markers);
            }, 50);
        });
    }

    function closeMapModal() {
        modal.style.display = 'none';
    }

    modalClose.addEventListener('click', closeMapModal);
    modal.querySelector('.ai-chat-map-modal-backdrop').addEventListener('click', closeMapModal);
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
        wrap.className = 'ai-chat-msg ai-chat-msg-bot ai-chat-map-wrap';
        const mapId = 'aiChatMap' + (++mapSeq);
        wrap.innerHTML = `
            <div id="${mapId}" class="ai-chat-map"></div>
            <button type="button" class="ai-chat-map-expand" title="Prikaži veću kartu"><i class="fas fa-expand"></i></button>
            <div class="ai-chat-map-legend">${legendHtml(validPoints)}</div>
        `;
        messages.appendChild(wrap);
        messages.scrollTop = messages.scrollHeight;

        wrap.querySelector('.ai-chat-map-expand').addEventListener('click', () => openMapModal(validPoints));

        loadLeaflet().then(() => {
            const map = L.map(mapId, { zoomControl: true, attributionControl: false, scrollWheelZoom: false });
            L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', { maxZoom: 19, attribution: '© OpenStreetMap' }).addTo(map);
            const markers = addMarkersTo(map, validPoints);
            fitToMarkers(map, markers);
            messages.scrollTop = messages.scrollHeight;
        });
    }

    toggle.addEventListener('click', () => {
        panel.style.display = panel.style.display === 'none' ? 'flex' : 'none';
        if (panel.style.display === 'flex') input.focus();
    });
    closeBtn.addEventListener('click', () => { panel.style.display = 'none'; });

    // Hands the conversation off to the full-page assistant: the visible
    // transcript (read straight from the DOM, so it reflects whatever the
    // bubbles actually ended up showing) plus the raw API `history` needed
    // to keep going. Map visualisations from earlier turns aren't part of
    // `history`, so they aren't restored on the other side — only future
    // turns will render maps again.
    expandBtn.addEventListener('click', () => {
        // "kind" is just the bubble variant (bot/user/error), independent of
        // either page's own CSS class prefix.
        const transcript = Array.from(messages.children)
            .filter(el => el.classList.contains('ai-chat-msg') && !el.classList.contains('ai-chat-map-wrap'))
            .map(el => ({ text: el.textContent, kind: el.classList.contains('ai-chat-msg-user') ? 'user'
                : el.classList.contains('ai-chat-msg-error') ? 'error' : 'bot' }));

        try {
            sessionStorage.setItem('aiChatTransfer', JSON.stringify({ history, transcript }));
        } catch (e) { /* storage unavailable — full page just starts fresh */ }

        window.location.href = '{{ route('chat.page') }}';
    });

    function addMessage(text, cls) {
        const div = document.createElement('div');
        div.className = 'ai-chat-msg ' + cls;
        div.textContent = text;
        messages.appendChild(div);
        messages.scrollTop = messages.scrollHeight;
        return div;
    }

    async function send() {
        const text = input.value.trim();
        if (!text || busy) return;

        addMessage(text, 'ai-chat-msg-user');
        input.value = '';
        busy = true;
        sendBtn.disabled = true;
        const thinking = addMessage('...', 'ai-chat-msg-bot');

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
                thinking.className = 'ai-chat-msg ai-chat-msg-error';
            } else {
                thinking.textContent = data.reply;
                history = data.history || history;
                if (data.mapPoints && data.mapPoints.length) {
                    renderMapPoints(data.mapPoints);
                }
            }
        } catch (e) {
            thinking.textContent = 'Greška pri spajanju na server.';
            thinking.className = 'ai-chat-msg ai-chat-msg-error';
        } finally {
            busy = false;
            sendBtn.disabled = false;
            input.focus();
        }
    }

    sendBtn.addEventListener('click', send);
    input.addEventListener('keydown', e => { if (e.key === 'Enter') send(); });
})();
</script>
