<?php
// Pagina Tabellone per videoproiettore: mostra ordini pronti
// Layout standalone ad alto contrasto su sfondo nero
if (!headers_sent()) { @ob_start(); }
require_once __DIR__ . '/config/database.php';

$db = getDB();

// Endpoint JSON: restituisce ordini in stato "ready"
if (isset($_GET['ajax'])) {
    header('Content-Type: application/json; charset=utf-8');
    try {
        $rows = $db->query(
            "SELECT id, order_number, customer_name, ready_at, created_at FROM orders WHERE status = 'ready' ORDER BY ready_at ASC"
        )->fetchAll();
        $prepRows = $db->query(
            "SELECT id, order_number, customer_name, created_at FROM orders WHERE status = 'preparing' ORDER BY created_at ASC"
        )->fetchAll();
        echo json_encode([
            'ok' => true,
            'ready' => array_map(function($r) {
                return [
                    'id' => $r['id'],
                    'order_number' => $r['order_number'] ?? $r['id'],
                    'customer_name' => $r['customer_name'] ?? '',
                    'ready_at' => $r['ready_at'],
                    'created_at' => $r['created_at'] ?? null
                ];
            }, $rows),
            'preparing' => array_map(function($r) {
                return [
                    'id' => $r['id'],
                    'order_number' => $r['order_number'] ?? $r['id'],
                    'customer_name' => $r['customer_name'] ?? '',
                    'created_at' => $r['created_at']
                ];
            }, $prepRows)
        ], JSON_UNESCAPED_UNICODE);
    } catch (Exception $e) {
        http_response_code(500);
        echo json_encode(['ok' => false, 'error' => 'Errore recupero ordini ready']);
    }
    exit;
}
?>
<!DOCTYPE html>
<html lang="it">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Tabellone Ordini - OrdiGO</title>
    <link rel="stylesheet" href="assets/tailwind.css">
    <style>
        body { background: #000; color: #fff; font-family: Inter, system-ui, -apple-system, Segoe UI, Roboto, 'Helvetica Neue', Arial, 'Noto Sans', sans-serif; }
        .blink { animation: blink 2.5s ease-in-out infinite; }
        @keyframes blink { 0%, 100% { opacity: 1; } 50% { opacity: 0.2; } }
        .board-card { border: 2px solid #fff; border-radius: 12px; padding: 18px; text-align: center; height: 100%; display: flex; flex-direction: column; justify-content: center; }
        .order-number { font-size: 8.5rem; font-weight: 900; letter-spacing: 0.5px; line-height: 1; font-family: 'Arial Black', Impact, 'Segoe UI Black', system-ui, -apple-system, Segoe UI, Roboto, 'Helvetica Neue', Arial, 'Noto Sans', sans-serif; text-shadow: 0 2px 6px rgba(0,0,0,0.35); }
        .customer-name { font-size: 2.4rem; font-weight: 700; opacity: 0.95; }
        .timer { font-size: 2rem; font-weight: 900; display: flex; align-items: center; justify-content: center; gap: 0.35em; width: 100%; }
        .grid-auto { display: grid; grid-template-columns: repeat(auto-fit, minmax(320px, 1fr)); gap: 18px; }
        .muted { color: #9ca3af; }
        .header { position: sticky; top: 0; background: #000; z-index: 10; }
        .fs-btn { border: 2px solid #fff; padding: 6px 12px; border-radius: 8px; }
        /* Stati colore per soglie tempo */
        .status-green { background: #a7f3d0; color: #111; border-color: #10b981; }
        .status-orange { background: #f59e0b; color: #111; border-color: #b45309; }
        .status-red { background: #dc2626; color: #fff; border-color: #7f1d1d; }
    /* Ticker ordini in preparazione */
    .ticker { position: sticky; top: 0; z-index: 1000; overflow: hidden; white-space: nowrap; border-top: 2px solid #333; border-bottom: 2px solid #333; background: #111; padding: 10px 0; display: flex; align-items: center; }
    .ticker-track { display: inline-block; padding-left: 100%; animation: ticker-scroll 30s linear infinite; }
        @keyframes ticker-scroll { 0% { transform: translateX(0); } 100% { transform: translateX(-100%); } }
        .ticker-item { display: inline-flex; align-items: center; margin-right: 40px; padding: 6px 12px; border-radius: 8px; background: #f9d71c; color: #111; font-weight: 900; font-size: 1.5rem; }
        .ticker-num { font-size: 2.6rem; font-weight: 900; line-height: 1; color: #dc2626; }
        /* Contenitore griglia scrollabile entro la viewport */
        #boardWrap { overflow-y: auto; max-height: calc(100vh - 100px); }
        /* Layout fullscreen: font ridotti per 3x4 */
        html.fs .order-number { font-size: 6.8rem; }
        html.fs .timer { font-size: 1.2rem; }
        html.fs .customer-name { font-size: 1.6rem; }
        /* Icona del timer */
        .timer .icon { display: inline-block; width: 1.25em; height: 1.25em; }
        .timer .icon svg { display: block; width: 100%; height: 100%; fill: currentColor; }
        .timer .elapsed { line-height: 1; }
        /* Riduci leggermente gli spazi verticali in fullscreen per mantenere l'altezza */
        html.fs #board .timer.mt-2 { margin-top: 4px; }
        html.fs #board .customer-name.mt-2 { margin-top: 4px; }
        html.fs .board-card { padding: 12px; }
        html.fs #boardWrap { padding: 10px; }
    </style>
</head>
<body>
    <!-- Header rimosso: il ticker in preparazione resta fisso in alto -->
    <div id="prepTicker" class="ticker">
        <div id="prepTickerTrack" class="ticker-track"></div>
    </div>
    <!-- Bottone fullscreen -->
    <button id="fullscreenBtn" class="fs-btn fixed top-2 right-2 bg-black/70 text-white">Schermo intero</button>
    <!-- Controlli di ordinamento -->
    <div id="sortControls" class="p-2 pl-4 text-sm text-white/90">
        <label for="sortMode" class="mr-2">Ordina per:</label>
        <select id="sortMode" class="bg-black border border-gray-600 rounded px-2 py-1">
            <option value="inserimento">Inserimento (recenti in alto)</option>
            <option value="numerico">Numero ordine (crescente)</option>
        </select>
    </div>
    <!-- Sezione tabellone ordini pronti -->
    <div id="boardWrap" class="p-4">
        <div id="empty" class="muted text-center py-8 hidden">Nessun ordine pronto</div>
        <div id="board" class="grid-auto"></div>
    </div>

    <script>
    const state = { orders: [], preparing: [], tick: Date.now(), sortMode: 'inserimento' };

    function parseSqliteDate(str) {
        // Converte 'YYYY-MM-DD HH:MM:SS' in Date, assumendo UTC
        if (!str) return null;
        try { return new Date(str.replace(' ', 'T') + 'Z'); } catch (e) { return null; }
    }

    function formatElapsed(fromDate, now) {
        if (!fromDate) return '--:--';
        const ms = Math.max(0, now - fromDate.getTime());
        const h = Math.floor(ms / 3600000);
        const m = Math.floor((ms % 3600000) / 60000);
        const s = Math.floor((ms % 60000) / 1000);
        const mm = String(m).padStart(2, '0');
        const ss = String(s).padStart(2, '0');
        return h > 0 ? `${h}:${mm}:${ss}` : `${mm}:${ss}`;
    }

    function getNumericOrder(o) {
        const raw = (o.order_number ?? o.id);
        if (typeof raw === 'string') {
            const num = parseInt(raw, 10);
            if (!Number.isNaN(num)) return num;
        }
        const n = Number(raw);
        return Number.isFinite(n) ? n : 0;
    }

    function sortOrders() {
        const mode = state.sortMode || 'inserimento';
        if (!Array.isArray(state.orders)) return;
        if (mode === 'numerico') {
            state.orders.sort((a, b) => getNumericOrder(a) - getNumericOrder(b));
        } else {
            // Inserimento: ordina per ready_at DESC, fallback su created_at
            state.orders.sort((a, b) => {
                const at = parseSqliteDate(a.ready_at)?.getTime() ?? parseSqliteDate(a.created_at)?.getTime() ?? 0;
                const bt = parseSqliteDate(b.ready_at)?.getTime() ?? parseSqliteDate(b.created_at)?.getTime() ?? 0;
                if (at === bt) return getNumericOrder(a) - getNumericOrder(b); // tie-break
                return bt - at;
            });
        }
    }

    function urgencyClass(fromDate, now) {
        // < 2 min: verde; 2-5 min: arancione con testo nero; >=5 min: rosso con testo bianco e lampeggiante
        if (!fromDate) return 'status-green';
        const ms = Math.max(0, now - fromDate.getTime());
        const minutes = Math.floor(ms / 60000);
        if (minutes >= 5) return 'status-red';
        if (minutes >= 2) return 'status-orange';
        return 'status-green';
    }

    function render() {
        const container = document.getElementById('board');
        const empty = document.getElementById('empty');
        const now = Date.now();
        container.innerHTML = '';
        sortOrders();
        if (!state.orders || state.orders.length === 0) {
            empty.classList.remove('hidden');
            stopAutoScroll();
            return;
        }
        empty.classList.add('hidden');
        state.orders.forEach(o => {
            const readyAt = parseSqliteDate(o.ready_at);
            const card = document.createElement('div');
            card.className = 'board-card';
            const uClass = urgencyClass(readyAt, now);
            if (uClass) {
                uClass.split(/\s+/).forEach(cls => { if (cls) card.classList.add(cls); });
            }
            const orderNum = (o.order_number || o.id);
            const custName = (o.customer_name || '').trim();
            const elapsed = formatElapsed(readyAt, now);
            card.innerHTML = `
                <div class="order-number">${orderNum}</div>
                <div class="timer mt-2"><span class="icon" aria-hidden="true">
                    <svg viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                        <path d="M12 1a1 1 0 0 1 1 1v1.06a9 9 0 1 1-2 0V2a1 1 0 0 1 1-1zm0 4a7 7 0 1 0 0 14 7 7 0 0 0 0-14zm1 3a1 1 0 0 0-2 0v4.382a1 1 0 0 0 .293.707l2.121 2.121a1 1 0 1 0 1.414-1.414l-1.828-1.828V8z" />
                    </svg>
                </span><span class="elapsed">${elapsed}</span></div>
                <div class="customer-name mt-2">${custName ? custName : '&nbsp;'}</div>
            `;
            container.appendChild(card);
        });
        // In fullscreen non vogliamo autoscroll; fuori fullscreen lo manteniamo
        if (!isFullscreen()) {
            requestAnimationFrame(() => { startAutoScroll(); });
        } else {
            stopAutoScroll();
        }
        // Applica layout fullscreen dopo il render
        applyFullscreenLayout();
    }

    async function fetchOrders() {
        try {
            const res = await fetch('tabellone.php?ajax=1', { headers: { 'Accept': 'application/json' } });
            const data = await res.json();
            if (!data.ok) throw new Error('Feed non disponibile');
            state.orders = Array.isArray(data.ready) ? data.ready : [];
            state.preparing = Array.isArray(data.preparing) ? data.preparing : [];
            render();
            renderTicker();
        } catch (e) {
            console.error(e);
        }
    }

    // Orologio rimosso

    // Aggiorna timers e classi per ordini pronti (sempre attivo)
    function tickTimers() {
        const now = Date.now();
        const cards = document.querySelectorAll('#board .board-card');
        cards.forEach((card, idx) => {
            const o = state.orders[idx];
            if (!o) return;
            const readyAt = parseSqliteDate(o.ready_at);
            const timerEl = card.querySelector('.timer');
            if (timerEl) {
                const elapsedEl = timerEl.querySelector('.elapsed');
                const t = formatElapsed(readyAt, now);
                if (elapsedEl) { elapsedEl.textContent = t; }
                else { timerEl.textContent = t; }
            }
            card.classList.remove('blink','status-green','status-orange','status-red');
            const uClass = urgencyClass(readyAt, now);
            if (uClass) {
                uClass.split(/\s+/).forEach(cls => { if (cls) card.classList.add(cls); });
            }
        });
    }

    // Fullscreen
    function isFullscreen() {
        // Fullscreen API o fullscreen del browser (F11): rilevamento geometrico con tolleranza
        const viaApi = !!document.fullscreenElement;
        const wDiff = Math.abs((window.innerWidth || 0) - (screen.width || 0));
        const hDiff = Math.abs((window.innerHeight || 0) - (screen.height || 0));
        const nearFullscreen = (wDiff <= 4 && hDiff <= 4);
        return viaApi || nearFullscreen;
    }
    async function toggleFullscreen() {
        try {
            if (!isFullscreen()) {
                await document.documentElement.requestFullscreen();
            } else {
                await document.exitFullscreen();
            }
        } catch (e) { console.error(e); }
    }
    function updateFsBtn() {
        const b = document.getElementById('fullscreenBtn');
        if (!b) return;
        const fs = isFullscreen();
        // Nascondi il bottone quando già in fullscreen (anche via F11)
        b.classList.toggle('hidden', fs);
        // Testo mantenuto per stato non-fullscreen
        if (!fs) b.textContent = 'Schermo intero';
    }
    document.getElementById('fullscreenBtn')?.addEventListener('click', toggleFullscreen);
    function updateSortControlsVisibility() {
        const sc = document.getElementById('sortControls');
        if (!sc) return;
        if (isFullscreen()) {
            sc.classList.add('hidden');
        } else {
            sc.classList.remove('hidden');
        }
    }
    function applyFullscreenLayout() {
        const board = document.getElementById('board');
        const wrap = document.getElementById('boardWrap');
        if (!board || !wrap) return;
        const fs = isFullscreen();
        document.documentElement.classList.toggle('fs', fs);
        if (fs) {
            // Calcola altezza disponibile precisa e imposta 3 righe e 4 colonne
            const top = wrap.getBoundingClientRect().top;
            const available = Math.max(200, Math.floor(window.innerHeight - top - 8));
            // Impedisce scrollbar verticale in fullscreen
            wrap.style.maxHeight = 'none';
            wrap.style.height = available + 'px';
            wrap.style.overflowY = 'hidden';
            // Gap e padding effettivi
            const boardStyles = getComputedStyle(board);
            const wrapStyles = getComputedStyle(wrap);
            const gapPx = parseInt(boardStyles.gap) || 18;
            const padTop = parseInt(wrapStyles.paddingTop) || 0;
            const padBottom = parseInt(wrapStyles.paddingBottom) || 0;
            // Aggiungi un piccolo spazio di respiro in fondo per mostrare gli angoli
            const safePaddingBottom = Math.max(padBottom, Math.round(gapPx * 0.75));
            wrap.style.paddingBottom = safePaddingBottom + 'px';
            // Altezza contenuto: togli padding alto/basso
            const contentAvailable = Math.max(180, available - padTop - safePaddingBottom);
            // 3 righe --> 2 gap verticali
            const verticalGaps = gapPx * 2;
            const rowH = Math.max(120, Math.floor((contentAvailable - verticalGaps) / 3));
            board.style.gridTemplateColumns = 'repeat(4, 1fr)';
            board.style.gridAutoRows = rowH + 'px';
        } else {
            // Ripristina impostazioni predefinite
            board.style.gridTemplateColumns = '';
            board.style.gridAutoRows = '';
            wrap.style.height = '';
            wrap.style.maxHeight = 'calc(100vh - 100px)';
            wrap.style.overflowY = 'auto';
            wrap.style.paddingBottom = '';
        }
    }
    document.addEventListener('fullscreenchange', () => { updateFsBtn(); updateSortControlsVisibility(); applyFullscreenLayout(); });
    // Aggiorna visibilità e layout anche su resize (utile quando si usa F11)
    window.addEventListener('resize', () => { updateFsBtn(); updateSortControlsVisibility(); applyFullscreenLayout(); });
    updateFsBtn();
    updateSortControlsVisibility();
    applyFullscreenLayout();

    // Ordinamento: controlli UI
    const sortSelect = document.getElementById('sortMode');
    if (sortSelect) {
        sortSelect.value = state.sortMode;
        sortSelect.addEventListener('change', (e) => {
            state.sortMode = e.target.value;
            render();
        });
    }

    function renderTicker() {
        const wrap = document.getElementById('prepTicker');
        const track = document.getElementById('prepTickerTrack');
        if (!wrap || !track) return;
        const list = state.preparing || [];
        // Ticker sempre visibile anche se non ci sono ordini in preparazione
        wrap.classList.remove('hidden');
        const items = list.map(o => {
            const num = (o.order_number || o.id);
            const cust = (o.customer_name || '').trim();
            return `<span class="ticker-item">In preparazione:&nbsp;&nbsp;<span class="ticker-num">${num}</span>${cust ? '&nbsp;&nbsp;' + cust : ''}</span>`;
        }).join(' ');
        const html = items ? (items + ' ' + items) : `<span class="ticker-item muted">In preparazione: nessun ordine</span>`;
        if (track.innerHTML !== html) {
            track.innerHTML = html;
        }
        updateTickerSpeed();
    }

    // Imposta velocità costante del ticker indipendente dal numero di ordini
    function updateTickerSpeed() {
        const track = document.getElementById('prepTickerTrack');
        if (!track) return;
        // Velocità costante e più lenta: px per secondo
        const SPEED_PX_S = 42; // ancora un po' più veloce
        // L'animazione scorre di -100% della larghezza del track: durata = larghezza / velocità
        const width = track.scrollWidth || 1;
        const durationSec = Math.max(20, Math.round(width / SPEED_PX_S));
        track.style.animationDuration = durationSec + 's';
    }

    // Autoscroll verticale rimosso: visualizzazione solo ticker orizzontale
    // Autoscroll verticale: sposta il contenuto del board quando eccede l’altezza
    let autoScrollTimer = null;
    let autoScrollDir = 1; // 1 giù, -1 su
    let autoScrollPauseUntil = 0; // timestamp per pausa ai bordi
    function startAutoScroll() {
        const wrap = document.getElementById('boardWrap');
        const board = document.getElementById('board');
        if (!wrap || !board) return;
        // Imposta altezza disponibile dinamicamente in base alla posizione nel viewport
        const top = wrap.getBoundingClientRect().top;
        const available = Math.max(200, Math.floor(window.innerHeight - top - 12));
        wrap.style.maxHeight = available + 'px';
        wrap.style.overflowY = 'auto';
        // Se il contenuto non eccede l’altezza, non avviare
        const overflow = board.scrollHeight > wrap.clientHeight;
        if (!overflow) {
            wrap.scrollTop = 0;
            stopAutoScroll();
            return;
        }
        // Se già attivo, non riavviare per preservare direzione e pausa
        if (autoScrollTimer) return;
        autoScrollTimer = setInterval(() => {
            const nowTs = Date.now();
            if (autoScrollPauseUntil && nowTs < autoScrollPauseUntil) return;
            const maxScroll = Math.max(0, board.scrollHeight - wrap.clientHeight);
            wrap.scrollTop = Math.min(maxScroll, Math.max(0, wrap.scrollTop + autoScrollDir));
            const atBottom = wrap.scrollTop >= maxScroll;
            const atTop = wrap.scrollTop <= 0;
            if (atBottom || atTop) {
                wrap.scrollTop = atBottom ? maxScroll : 0;
                autoScrollPauseUntil = nowTs + 5000;
                autoScrollDir = atBottom ? -1 : 1;
            }
        }, 50);
    }
    function stopAutoScroll() {
        if (autoScrollTimer) { clearInterval(autoScrollTimer); autoScrollTimer = null; }
    }
    // Aggiorna su resize
    window.addEventListener('resize', () => { stopAutoScroll(); startAutoScroll(); });

    // Avvio: visuale unica, entrambi visibili
    startAutoScroll();

    fetchOrders();
    setInterval(fetchOrders, 5000); // Poll ogni 5s
    setInterval(() => { tickTimers(); }, 1000);
    </script>
</body>
</html>