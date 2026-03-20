<?php
require __DIR__ . '/config.php';

// ─── Helpers ──────────────────────────────────────────────────────────────────
define('CACHE_DIR', __DIR__ . '/cache');
define('CACHE_TTL', 1800);

set_time_limit(120);
if (!is_dir(CACHE_DIR)) mkdir(CACHE_DIR, 0755, true);

function fetchFeed(string $url): ?string {
    $f = CACHE_DIR . '/' . md5($url) . '.xml';
    if (file_exists($f) && (time() - filemtime($f)) < CACHE_TTL) return file_get_contents($f);

    $ua = 'Broadsheet/1.0 (+localhost)';
    if (function_exists('curl_init')) {
        $ch = curl_init($url);
        curl_setopt_array($ch, [CURLOPT_RETURNTRANSFER => true, CURLOPT_FOLLOWLOCATION => true,
                                CURLOPT_TIMEOUT => 12, CURLOPT_USERAGENT => $ua, CURLOPT_ENCODING => '']);
        $data = curl_exec($ch);
    } else {
        $ctx  = stream_context_create(['http' => ['user_agent' => $ua, 'timeout' => 12]]);
        $data = @file_get_contents($url, false, $ctx);
    }
    if ($data) { file_put_contents($f, $data); return $data; }
    return null;
}

function parseFeed(?string $xml, int $limit): array {
    if (!$xml) return [];
    libxml_use_internal_errors(true);
    $feed = simplexml_load_string($xml);
    libxml_clear_errors();
    if (!$feed) return [];

    $entries = $feed->channel->item ?? [];
    if (!count($entries)) {
        $feed->registerXPathNamespace('a', 'http://www.w3.org/2005/Atom');
        $entries = $feed->xpath('//a:entry') ?? [];
    }
    $items = []; $n = 0;
    foreach ($entries as $e) {
        if ($n >= $limit) break;
        $title = html_entity_decode(strip_tags((string)($e->title ?? '')), ENT_QUOTES, 'UTF-8');
        $link  = (string)($e->link ?? $e->id ?? '');
        if (empty($link) && isset($e->link['href'])) $link = (string)$e->link['href'];
        $title = trim(preg_replace('/\s*\(arXiv:\S+\)\s*$/', '', $title), ". \t\n\r");
        if ($title && $link) { $items[] = ['title' => $title, 'link' => $link]; $n++; }
    }
    return $items;
}

// ─── Proxy endpoint (custom feeds fetched by JS) ──────────────────────────────
if (isset($_GET['proxy'])) {
    $url = trim($_GET['proxy'] ?? '');
    if (!filter_var($url, FILTER_VALIDATE_URL) || !preg_match('/^https?:\/\//', $url)) {
        http_response_code(400); header('Content-Type: application/json');
        echo json_encode(['error' => 'Invalid URL']); exit;
    }
    header('Content-Type: application/json');
    echo json_encode(parseFeed(fetchFeed($url), 20));
    exit;
}

// ─── Fetch all built-in feeds ────────────────────────────────────────────────
$rendered = [];
foreach ($feeds as $fd) {
    $items = parseFeed(fetchFeed($fd['url']), $fd['limit']);
    $rendered[$fd['id']] = ['id' => $fd['id'], 'col' => $fd['col'], 'title' => $fd['title'], 'items' => $items];
}

// Master list for JS (metadata only, no items)
$master = array_values(array_map(fn($f) => [
    'id'       => $f['id'],
    'title'    => $f['title'],
    'category' => $f['category'],
    'col'      => $f['col'],
    'default'  => $f['default'],
], $feeds));
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Broadsheet</title>
<style>
:root { --feed-font: 13px; }
* { box-sizing: border-box; margin: 0; padding: 0; }

body {
    font-family: Arial, Helvetica, sans-serif;
    font-size: 13px;
    background: #e8e8e8;
    color: #222;
    overflow-x: hidden;
}

/* ── Header ── */
header {
    background: #1a1a1a;
    color: #fff;
    padding: 9px 14px;
    display: flex;
    align-items: baseline;
    gap: 10px;
    position: sticky;
    top: 0;
    z-index: 100;
}
header h1   { font-size: 15px; font-weight: bold; letter-spacing: .3px; }
header .date { font-size: 11px; color: #777; flex: 1; }
header button {
    font-size: 11px; padding: 3px 9px;
    background: #2e2e2e; color: #bbb;
    border: 1px solid #444; border-radius: 3px; cursor: pointer;
}
header button:hover { background: #3a3a3a; color: #fff; }
header #settings-btn { color: #e0e0e0; border-color: #555; }

/* ── Settings overlay ── */
#settings-overlay {
    display: none;
    position: fixed; inset: 0;
    background: rgba(0,0,0,.35);
    z-index: 200;
}
#settings-overlay.open { display: block; }

/* ── Settings panel (right drawer) ── */
#settings-panel {
    position: fixed;
    top: 0; right: 0;
    width: 340px; height: 100vh;
    background: #fff;
    box-shadow: -3px 0 20px rgba(0,0,0,.2);
    z-index: 201;
    display: flex; flex-direction: column;
    transform: translateX(100%);
    transition: transform .2s ease;
}
#settings-panel.open { transform: translateX(0); }

.sp-head {
    background: #1a1a1a; color: #fff;
    padding: 10px 12px;
    display: flex; justify-content: space-between; align-items: center;
    flex-shrink: 0;
}
.sp-head strong { font-size: 13px; }
.sp-head button {
    background: none; border: none; color: #aaa;
    font-size: 16px; cursor: pointer; line-height: 1; padding: 2px 4px;
}
.sp-head button:hover { color: #fff; }

.sp-body {
    flex: 1; overflow-y: auto;
    padding: 0;
}

.sp-section { border-bottom: 1px solid #eee; padding: 10px 12px; }
.sp-slider-row {
    display: flex; align-items: center; gap: 8px;
}
.sp-slider-row input[type=range] { flex: 1; }
.sp-slider-row .sp-slider-val { font-size: 12px; color: #555; min-width: 32px; text-align: right; }
.sp-section-title {
    font-size: 10px; font-weight: bold; text-transform: uppercase;
    letter-spacing: .6px; color: #888; margin-bottom: 8px;
}

.sp-checkboxes {
    display: grid; grid-template-columns: 1fr 1fr; gap: 4px 8px;
}
.sp-checkboxes label {
    display: flex; align-items: center; gap: 5px;
    font-size: 12px; cursor: pointer; line-height: 1.3;
    padding: 2px 0;
}
.sp-checkboxes label:hover { color: #1a0dab; }
.sp-checkboxes input[type=checkbox] { cursor: pointer; flex-shrink: 0; }

/* Custom feeds section */
.cf-add-row {
    display: flex; gap: 6px; margin-bottom: 8px; flex-wrap: wrap;
}
.cf-add-row input {
    flex: 1; min-width: 0;
    border: 1px solid #ccc; border-radius: 3px;
    padding: 4px 6px; font-size: 12px;
}
.cf-add-row input:focus { outline: none; border-color: #4a9eff; }
.cf-add-row button {
    padding: 4px 10px; font-size: 12px;
    background: #1a1a1a; color: #fff;
    border: none; border-radius: 3px; cursor: pointer; white-space: nowrap;
}
.cf-add-row button:hover { background: #333; }

.cf-item {
    display: flex; align-items: center; gap: 6px;
    padding: 4px 0; font-size: 12px; border-bottom: 1px solid #f0f0f0;
}
.cf-item span { flex: 1; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
.cf-item button {
    background: none; border: none; cursor: pointer;
    color: #bbb; font-size: 14px; padding: 0 2px; flex-shrink: 0;
}
.cf-item button:hover { color: #c00; }

/* Footer */
.sp-foot {
    border-top: 1px solid #ddd; padding: 10px 12px;
    display: flex; gap: 6px; flex-shrink: 0;
}
.sp-foot button {
    flex: 1; padding: 6px 0; font-size: 12px;
    border: 1px solid #ccc; border-radius: 3px;
    background: #f5f5f5; cursor: pointer; color: #333;
}
.sp-foot button:hover { background: #eee; }
.sp-foot #sp-apply {
    background: #1a1a1a; color: #fff; border-color: #1a1a1a;
}
.sp-foot #sp-apply:hover { background: #333; }
.sp-foot label {
    flex: 1; padding: 6px 0; font-size: 12px;
    border: 1px solid #ccc; border-radius: 3px;
    background: #f5f5f5; cursor: pointer; color: #333;
    text-align: center;
}
.sp-foot label:hover { background: #eee; }

/* ── Main grid ── */
.grid {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    gap: 12px; padding: 12px;
    align-items: start;
    opacity: 0;
    transition: opacity .12s;
}
.grid.ready { opacity: 1; }

.col {
    display: flex; flex-direction: column;
    gap: 0; min-width: 0;
}

/* ── Feed card ── */
.feed {
    background: #fff;
    border: 1px solid #ccc; border-radius: 4px;
    overflow: hidden;
    box-shadow: 0 1px 3px rgba(0,0,0,.07);
}
.feed.is-hidden { display: none; }
.feed.is-dragging { opacity: .4; box-shadow: none; }

.feed-title {
    background: #2c2c2c; color: #e0e0e0;
    padding: 6px 8px 6px 10px;
    font-size: var(--feed-font); font-weight: bold; letter-spacing: .3px;
    cursor: grab; display: flex; align-items: center; gap: 7px;
    user-select: none;
}
.feed-title:active { cursor: grabbing; }
.feed-title .handle { color: #666; font-size: 13px; flex-shrink: 0; }

/* ── Post rows ── */
.feed ul { list-style: none; }
.feed ul li {
    padding: 4px 8px 4px 7px;
    border-bottom: 1px solid #efefef;
    line-height: 1.45;
    display: flex; align-items: baseline; gap: 5px;
}
.feed ul li:last-child { border-bottom: none; }
.feed ul li:nth-child(even) { background: #f7f7f7; }
.feed ul li::before { content: "▸"; color: #bbb; font-size: 10px; flex-shrink: 0; }
.feed ul li a { color: #1a0dab; text-decoration: none; word-break: break-word; font-size: var(--feed-font); }
.feed ul li a:visited { color: #609; }
.feed ul li a:hover   { text-decoration: underline; }
.feed-empty { padding: 8px 10px; color: #bbb; font-style: italic; font-size: 11px; }

/* ── Drop zones ── */
.drop-zone {
    height: 18px; border-radius: 3px; flex-shrink: 0;
    transition: height .1s, background .1s;
}
.drop-zone.active { height: 36px; background: #4a9eff44; border: 2px dashed #4a9eff; }

/* ── Responsive ── */
@media (max-width: 1100px) { .grid { grid-template-columns: repeat(2, 1fr); } }
@media (max-width: 560px)  { .grid { grid-template-columns: 1fr; padding: 8px; gap: 8px; } }
</style>
</head>
<body>

<header>
    <h1>Broadsheet</h1>
    <span class="date"><?= date('D d M Y') ?></span>
    <button id="settings-btn">&#9881; Settings</button>
    <button id="reset-btn">Reset layout</button>
</header>

<!-- Settings drawer -->
<div id="settings-overlay"></div>
<div id="settings-panel">
    <div class="sp-head">
        <strong>Settings</strong>
        <button id="sp-close">&#x2715;</button>
    </div>
    <div class="sp-body" id="sp-body"></div>
    <div class="sp-foot">
        <button id="sp-export">&#8595; Export</button>
        <label for="sp-import-file">&#8593; Import<input type="file" id="sp-import-file" accept=".json" style="display:none"></label>
        <button id="sp-apply">Apply</button>
    </div>
</div>

<!-- Feed grid (PHP-rendered, JS controls visibility & order) -->
<div class="grid" id="grid">
<?php
// Render feeds grouped by default col; JS will reorder based on saved layout
$byCol = [0 => [], 1 => [], 2 => [], 3 => []];
foreach ($rendered as $fd) $byCol[$fd['col']][] = $fd;
foreach ($byCol as $col => $colFeeds): ?>
<div class="col">
    <?php foreach ($colFeeds as $fd): ?>
    <div class="feed" data-id="<?= $fd['id'] ?>">
        <div class="feed-title">
            <span class="handle">&#x2807;</span>
            <?= htmlspecialchars($fd['title']) ?>
        </div>
        <?php if ($fd['items']): ?>
        <ul>
            <?php foreach ($fd['items'] as $item): ?>
            <li><a href="<?= htmlspecialchars($item['link']) ?>" target="_blank" rel="noopener noreferrer"><?= htmlspecialchars($item['title']) ?></a></li>
            <?php endforeach; ?>
        </ul>
        <?php else: ?>
        <div class="feed-empty">Feed unavailable — check back shortly.</div>
        <?php endif; ?>
    </div>
    <?php endforeach; ?>
</div>
<?php endforeach; ?>
</div>

<script>
const FEED_MASTER = <?= json_encode($master) ?>;
const STORAGE_KEY = 'cm-state-v2';

// ─── State ────────────────────────────────────────────────────────────────────
const CM = {
    state: { enabled: [], custom: [], layout: {}, fontSize: 13 },

    loadState() {
        const raw = localStorage.getItem(STORAGE_KEY);
        if (raw) {
            try {
                this.state = JSON.parse(raw);
                if (!this.state.fontSize) this.state.fontSize = 13;
                this.applyFontSize();
                return;
            } catch(e) {}
        }
        // First visit: build defaults from master
        this.state.enabled = FEED_MASTER.filter(f => f.default).map(f => f.id);
        this.state.custom  = [];
        this.state.layout  = {};
        FEED_MASTER.forEach(f => {
            if (!this.state.layout[f.col]) this.state.layout[f.col] = [];
            this.state.layout[f.col].push(f.id);
        });
        this.saveState();
    },

    saveState() {
        localStorage.setItem(STORAGE_KEY, JSON.stringify(this.state));
    },

    // ── Font size ─────────────────────────────────────────────────────────────
    applyFontSize() {
        document.documentElement.style.setProperty('--feed-font', this.state.fontSize + 'px');
    },

    // ── DOM: visibility ───────────────────────────────────────────────────────
    applyVisibility() {
        document.querySelectorAll('.feed[data-id]').forEach(el => {
            const hidden = !this.state.enabled.includes(el.dataset.id);
            el.classList.toggle('is-hidden', hidden);
            el.setAttribute('draggable', hidden ? 'false' : 'true');
        });
    },

    // ── DOM: column layout ────────────────────────────────────────────────────
    applyLayout() {
        const allFeeds = {};
        document.querySelectorAll('.feed[data-id]').forEach(f => allFeeds[f.dataset.id] = f);
        const cols = document.querySelectorAll('.col');

        cols.forEach((col, i) => {
            (this.state.layout[i] || []).forEach(id => {
                if (allFeeds[id]) col.appendChild(allFeeds[id]);
            });
        });
    },

    // ── Custom feeds (fetched via proxy) ──────────────────────────────────────
    async loadCustomFeeds() {
        for (const cf of (this.state.custom || [])) {
            try {
                const res  = await fetch('?proxy=' + encodeURIComponent(cf.url));
                const data = await res.json();
                if (Array.isArray(data)) this.renderCustomFeed(cf, data);
            } catch(e) {
                this.renderCustomFeed(cf, []);
            }
        }
        this.refreshZones();
    },

    renderCustomFeed(cf, items) {
        // Find target col from layout, default to col 0
        let targetColIdx = 0;
        for (const [i, ids] of Object.entries(this.state.layout)) {
            if (ids.includes(cf.id)) { targetColIdx = parseInt(i); break; }
        }
        const cols = document.querySelectorAll('.col');
        const col  = cols[targetColIdx] || cols[0];

        // Remove existing card if re-rendering
        document.querySelector(`.feed[data-id="${cf.id}"]`)?.remove();

        const card = document.createElement('div');
        card.className = 'feed';
        card.dataset.id = cf.id;
        card.setAttribute('draggable', 'true');

        const ul = items.length
            ? `<ul>${items.map(it =>
                `<li><a href="${escHtml(it.link)}" target="_blank" rel="noopener noreferrer">${escHtml(it.title)}</a></li>`
              ).join('')}</ul>`
            : `<div class="feed-empty">Feed unavailable — check back shortly.</div>`;

        card.innerHTML = `<div class="feed-title"><span class="handle">&#x2807;</span>${escHtml(cf.title || cf.url)}</div>${ul}`;
        col.appendChild(card);
        this.initFeed(card);
    },

    // ── Drag & drop ───────────────────────────────────────────────────────────
    dragging: null,

    initDragDrop() {
        document.querySelectorAll('.feed[data-id]').forEach(f => this.initFeed(f));
    },

    initFeed(feed) {
        feed.addEventListener('dragstart', e => {
            this.dragging = feed;
            e.dataTransfer.effectAllowed = 'move';
            setTimeout(() => feed.classList.add('is-dragging'), 0);
        });
        feed.addEventListener('dragend', () => {
            feed.classList.remove('is-dragging');
            this.clearActiveZones();
            this.dragging = null;
            this.saveLayout();
        });
    },

    makeDropZone() {
        const dz = document.createElement('div');
        dz.className = 'drop-zone';
        dz.addEventListener('dragover', e => {
            e.preventDefault(); e.dataTransfer.dropEffect = 'move';
            this.clearActiveZones(); dz.classList.add('active');
        });
        dz.addEventListener('dragleave', () => dz.classList.remove('active'));
        dz.addEventListener('drop', e => {
            e.preventDefault();
            if (!this.dragging) return;
            dz.parentNode.insertBefore(this.dragging, dz);
            this.refreshZones();
            this.saveLayout();
        });
        return dz;
    },

    refreshZones() {
        document.querySelectorAll('.drop-zone').forEach(z => z.remove());
        document.querySelectorAll('.col').forEach(col => {
            const feeds = [...col.querySelectorAll('.feed:not(.is-hidden)')];
            if (!feeds.length) { col.appendChild(this.makeDropZone()); return; }
            col.insertBefore(this.makeDropZone(), feeds[0]);
            feeds.forEach(f => f.after(this.makeDropZone()));
        });
    },

    clearActiveZones() {
        document.querySelectorAll('.drop-zone.active').forEach(z => z.classList.remove('active'));
    },

    saveLayout() {
        this.state.layout = {};
        document.querySelectorAll('.col').forEach((col, i) => {
            this.state.layout[i] = [...col.querySelectorAll('.feed')].map(f => f.dataset.id);
        });
        this.saveState();
    },

    // ── Settings panel ────────────────────────────────────────────────────────
    initSettings() {
        document.getElementById('settings-btn').onclick   = () => this.openSettings();
        document.getElementById('sp-close').onclick       = () => this.closeSettings();
        document.getElementById('settings-overlay').onclick = () => this.closeSettings();
        document.getElementById('sp-apply').onclick       = () => this.applySettings();
        document.getElementById('sp-export').onclick      = () => this.exportConfig();
        document.getElementById('sp-import-file').onchange = e => {
            const file = e.target.files[0]; if (!file) return;
            const reader = new FileReader();
            reader.onload = ev => { try { this.importConfig(JSON.parse(ev.target.result)); } catch(err) { alert('Invalid JSON file.'); } };
            reader.readAsText(file);
            e.target.value = '';
        };
        document.addEventListener('keydown', e => { if (e.key === 'Escape') this.closeSettings(); });

        document.getElementById('reset-btn').onclick = () => {
            if (!confirm('Reset layout and settings to defaults?')) return;
            localStorage.removeItem(STORAGE_KEY);
            location.reload();
        };
    },

    openSettings() {
        this.renderSettingsPanel();
        document.getElementById('settings-panel').classList.add('open');
        document.getElementById('settings-overlay').classList.add('open');
    },

    closeSettings() {
        document.getElementById('settings-panel').classList.remove('open');
        document.getElementById('settings-overlay').classList.remove('open');
    },

    renderSettingsPanel() {
        const body = document.getElementById('sp-body');
        body.innerHTML = '';

        // Font size slider
        const fsec = document.createElement('div');
        fsec.className = 'sp-section';
        fsec.innerHTML = `<div class="sp-section-title">Font Size</div>
            <div class="sp-slider-row">
                <input type="range" id="fs-slider" min="10" max="20" step="1" value="${this.state.fontSize}">
                <span class="sp-slider-val" id="fs-val">${this.state.fontSize}px</span>
            </div>`;
        body.appendChild(fsec);
        fsec.querySelector('#fs-slider').oninput = e => {
            document.getElementById('fs-val').textContent = e.target.value + 'px';
            this.state.fontSize = parseInt(e.target.value);
            this.applyFontSize();
        };

        // Group by category
        const categories = {};
        FEED_MASTER.forEach(f => {
            if (!categories[f.category]) categories[f.category] = [];
            categories[f.category].push(f);
        });

        for (const [cat, feeds] of Object.entries(categories)) {
            const sec = document.createElement('div');
            sec.className = 'sp-section';
            sec.innerHTML = `<div class="sp-section-title">${escHtml(cat)}</div>`;
            const grid = document.createElement('div');
            grid.className = 'sp-checkboxes';
            feeds.forEach(f => {
                const label = document.createElement('label');
                label.innerHTML = `<input type="checkbox" data-id="${f.id}"${this.state.enabled.includes(f.id) ? ' checked' : ''}> ${escHtml(f.title)}`;
                grid.appendChild(label);
            });
            sec.appendChild(grid);
            body.appendChild(sec);
        }

        // Custom feeds section
        const csec = document.createElement('div');
        csec.className = 'sp-section';
        csec.innerHTML = `<div class="sp-section-title">Custom Feeds</div>
            <div class="cf-add-row">
                <input type="text" id="cf-title" placeholder="Feed name">
                <input type="url"  id="cf-url"   placeholder="https://…/feed.rss">
                <button id="cf-add">+ Add</button>
            </div>
            <div id="cf-list"></div>`;
        body.appendChild(csec);

        this.renderCustomList();

        document.getElementById('cf-add').onclick = () => {
            const title = document.getElementById('cf-title').value.trim();
            const url   = document.getElementById('cf-url').value.trim();
            if (!url) return;
            const id = 'custom-' + Date.now().toString(36);
            this.state.custom.push({ id, title: title || url, url });
            document.getElementById('cf-title').value = '';
            document.getElementById('cf-url').value   = '';
            this.renderCustomList();
        };
    },

    renderCustomList() {
        const list = document.getElementById('cf-list');
        if (!list) return;
        list.innerHTML = '';
        (this.state.custom || []).forEach(cf => {
            const row = document.createElement('div');
            row.className = 'cf-item';
            row.innerHTML = `<span title="${escHtml(cf.url)}">${escHtml(cf.title || cf.url)}</span><button data-id="${cf.id}">&#x2715;</button>`;
            row.querySelector('button').onclick = () => {
                this.state.custom = this.state.custom.filter(c => c.id !== cf.id);
                this.renderCustomList();
            };
            list.appendChild(row);
        });
    },

    applySettings() {
        // Collect enabled IDs from checkboxes
        this.state.enabled = [...document.querySelectorAll('#sp-body input[type=checkbox]:checked')]
            .map(cb => cb.dataset.id);
        this.saveState();
        this.applyVisibility();
        this.refreshZones();
        // Load any newly added custom feeds
        this.loadCustomFeeds();
        this.closeSettings();
    },

    // ── Export / Import ───────────────────────────────────────────────────────
    exportConfig() {
        const json = JSON.stringify(this.state, null, 2);
        const a = Object.assign(document.createElement('a'), {
            href: URL.createObjectURL(new Blob([json], {type: 'application/json'})),
            download: 'broadsheet-config.json',
        });
        a.click(); URL.revokeObjectURL(a.href);
    },

    importConfig(data) {
        if (!data.enabled || !Array.isArray(data.enabled)) { alert('Invalid config file.'); return; }
        this.state = { enabled: data.enabled, custom: data.custom || [], layout: data.layout || {}, fontSize: data.fontSize || 13 };
        this.saveState();
        location.reload();
    },

    // ── Init ──────────────────────────────────────────────────────────────────
    init() {
        this.loadState();
        this.applyFontSize();
        this.applyVisibility();
        this.applyLayout();
        this.initDragDrop();
        this.refreshZones();
        this.loadCustomFeeds();
        this.initSettings();
        document.getElementById('grid').classList.add('ready');
    },
};

function escHtml(s) {
    return String(s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
}

CM.init();
</script>
</body>
</html>
