<!DOCTYPE html>
<html lang="nl">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <title>PlayDrive — Telefoonweergave</title>
    <style>
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

        html, body { height: 100%; }
        body {
            background: radial-gradient(circle at 50% 0%, #161b2b 0%, #0b0d13 60%);
            display: flex;
            flex-direction: column;
            align-items: center;
            font-family: ui-sans-serif, system-ui, sans-serif;
            color: #9ca3af;
            overflow: hidden;
        }

        /* ── Toolbar ── */
        .toolbar {
            flex-shrink: 0;
            display: flex;
            align-items: center;
            gap: 8px;
            flex-wrap: wrap;
            justify-content: center;
            padding: 14px 16px 10px;
        }
        .label {
            font-size: 11px; font-weight: 600; color: #4b5563;
            text-transform: uppercase; letter-spacing: .08em; margin-right: 2px;
        }
        .btn, select, .url {
            background: #1c1f2a; border: 1px solid #2d3344; border-radius: 8px;
            color: #9ca3af; font-size: 12px; font-weight: 500; padding: 6px 11px;
            transition: all .15s; outline: none; font-family: inherit;
        }
        .btn { cursor: pointer; white-space: nowrap; }
        .btn:hover { background: #252a38; color: #e5e7eb; border-color: #3d4560; }
        .btn.active { background: #1e3a5f; color: #60a5fa; border-color: #3b82f6; }
        select { cursor: pointer; }
        .url { width: 260px; font-family: ui-monospace, monospace; }
        .url:focus { border-color: #3b82f6; color: #e5e7eb; }
        .sep { width: 1px; height: 20px; background: #2d3344; margin: 0 2px; }

        /* ── Stage: telefoon wordt geschaald om in het venster te passen ── */
        .stage {
            flex: 1; min-height: 0; width: 100%;
            display: flex; align-items: center; justify-content: center;
            padding-bottom: 28px;
        }
        .phone {
            position: relative;
            flex-shrink: 0;
            transform-origin: center center;
            transition: width .25s ease, height .25s ease;
        }
        .shell {
            width: 100%; height: 100%;
            background: linear-gradient(160deg, #2a2d38 0%, #1a1d26 60%, #111318 100%);
            border: 1px solid #3a3f52;
            box-shadow: inset 0 0 0 1px rgba(255,255,255,.06), 0 30px 80px rgba(0,0,0,.7), 0 8px 24px rgba(0,0,0,.5);
            position: relative;
        }
        .screen {
            width: 100%; height: 100%;
            background: #030814;
            overflow: hidden;
            position: relative;
            display: flex; flex-direction: column;
        }

        /* Statusbalk */
        .status {
            flex-shrink: 0; display: flex; align-items: center; justify-content: space-between;
            position: relative; z-index: 2; pointer-events: none;
            background: #030814;
        }
        .time { font-size: 15px; font-weight: 600; color: #fff; font-variant-numeric: tabular-nums; letter-spacing: -.3px; }
        .icons { display: flex; align-items: center; gap: 6px; }
        .icons svg { fill: #fff; display: block; }
        .island {
            position: absolute; top: 11px; left: 50%; transform: translateX(-50%);
            width: 122px; height: 34px; background: #000; border-radius: 20px; z-index: 3;
        }
        .punch {
            position: absolute; top: 14px; left: 50%; transform: translateX(-50%);
            width: 12px; height: 12px; background: #000; border-radius: 50%; z-index: 3;
        }

        .frame-wrap { flex: 1; min-height: 0; }
        iframe { width: 100%; height: 100%; border: 0; display: block; background: #030814; }

        /* Home-indicator (iPhone) */
        .home-bar {
            position: absolute; bottom: 7px; left: 50%; transform: translateX(-50%);
            width: 128px; height: 5px; border-radius: 3px; background: rgba(255,255,255,.55);
            z-index: 3; pointer-events: none;
        }

        .badge {
            position: absolute; bottom: -24px; left: 0; right: 0; text-align: center;
            font-size: 10px; color: #374151; letter-spacing: .05em; white-space: nowrap;
        }
        .hints { position: fixed; bottom: 8px; left: 0; right: 0; text-align: center; font-size: 10px; color: #374151; }
        kbd { background: #1c1f2a; border: 1px solid #2d3344; border-radius: 4px; padding: 1px 5px; font-size: 10px; color: #6b7280; }
    </style>
</head>
<body>

<div class="toolbar">
    <span class="label">App</span>
    <select id="app-select">
        @foreach ($apps as $key => $meta)
            <option value="{{ $key }}" @selected($key === $app)>{{ $meta['label'] }}</option>
        @endforeach
    </select>
    <div class="sep"></div>
    <span class="label">Toestel</span>
    <span id="device-buttons"></span>
    <button class="btn" id="btn-rotate" title="L">⟳ Landscape</button>
    <div class="sep"></div>
    <input class="url" id="url" spellcheck="false" />
    <button class="btn" id="btn-go">Ga</button>
    <button class="btn" id="btn-reload" title="R">↺</button>
    <button class="btn" id="btn-open" title="In nieuw tabblad openen">↗</button>
</div>

<div class="stage" id="stage">
    <div class="phone" id="phone">
        <div class="shell" id="shell">
            <div class="screen" id="screen">
                <div class="island" id="island"></div>
                <div class="punch" id="punch"></div>
                <div class="status" id="status">
                    <span class="time" id="time">9:41</span>
                    <div class="icons">
                        <svg width="17" height="12" viewBox="0 0 17 12"><rect x="0" y="8" width="3" height="4" rx=".5" opacity=".3"/><rect x="4.5" y="5.5" width="3" height="6.5" rx=".5" opacity=".5"/><rect x="9" y="3" width="3" height="9" rx=".5" opacity=".7"/><rect x="13.5" y="0" width="3" height="12" rx=".5"/></svg>
                        <svg width="16" height="12" viewBox="0 0 16 12"><path d="M8 9.5a1.5 1.5 0 110 3 1.5 1.5 0 010-3z"/><path d="M3.5 6.5C5 4.9 6.4 4 8 4s3 .9 4.5 2.5" stroke="#fff" stroke-width="1.5" fill="none" stroke-linecap="round" opacity=".7"/><path d="M1 4C3.2 1.6 5.5 0 8 0s4.8 1.6 7 4" stroke="#fff" stroke-width="1.5" fill="none" stroke-linecap="round" opacity=".4"/></svg>
                        <svg width="25" height="12" viewBox="0 0 25 12"><rect x=".5" y=".5" width="21" height="11" rx="3" stroke="#fff" stroke-width="1" fill="none" opacity=".35"/><rect x="2" y="2" width="16" height="8" rx="1.5" fill="#fff"/><path d="M22.5 4v4a2 2 0 000-4z" fill="#fff" opacity=".4"/></svg>
                    </div>
                </div>
                <div class="frame-wrap"><iframe id="frame" title="PlayDrive app"></iframe></div>
                <div class="home-bar" id="home-bar"></div>
            </div>
        </div>
        <div class="badge" id="badge"></div>
    </div>
</div>

<div class="hints"><kbd>D</kbd> toestel · <kbd>L</kbd> landscape · <kbd>R</kbd> herladen</div>

<script>
(() => {
    const APPS = @json($apps);
    const DEVICES = {
        iphone:  { label: 'iPhone 15 Pro', w: 393, h: 852, radius: 54, pad: 12, inner: 44, status: 54, island: true,  punch: false, home: true  },
        pixel:   { label: 'Pixel 8',       w: 412, h: 892, radius: 40, pad: 12, inner: 30, status: 40, island: false, punch: true,  home: false },
        galaxy:  { label: 'Galaxy S24',    w: 360, h: 780, radius: 38, pad: 10, inner: 30, status: 36, island: false, punch: true,  home: false },
        compact: { label: 'iPhone SE',     w: 375, h: 667, radius: 46, pad: 12, inner: 36, status: 20, island: false, punch: false, home: false },
    }

    const store = {
        get(k, d) { try { return localStorage.getItem('devframe.' + k) ?? d } catch { return d } },
        set(k, v) { try { localStorage.setItem('devframe.' + k, v) } catch {} },
    }

    let app = @json($app)
    let device = DEVICES[store.get('device')] ? store.get('device') : 'iphone'
    let landscape = store.get('landscape') === '1'

    const $ = id => document.getElementById(id)
    const frame = $('frame')

    // ── Toestellen ───────────────────────────────────────────
    const btns = $('device-buttons')
    for (const [key, d] of Object.entries(DEVICES)) {
        const b = document.createElement('button')
        b.className = 'btn'
        b.textContent = d.label
        b.dataset.device = key
        b.style.marginRight = '6px'
        b.onclick = () => setDevice(key)
        btns.appendChild(b)
    }

    function setDevice(key) {
        device = key
        store.set('device', key)
        layout()
    }

    function layout() {
        const d = DEVICES[device]
        const w = (landscape ? d.h : d.w) + d.pad * 2
        const h = (landscape ? d.w : d.h) + d.pad * 2
        const phone = $('phone')
        phone.style.width = w + 'px'
        phone.style.height = h + 'px'
        $('shell').style.borderRadius = d.radius + 'px'
        $('shell').style.padding = d.pad + 'px'
        $('screen').style.borderRadius = d.inner + 'px'

        const status = $('status')
        status.style.display = landscape ? 'none' : 'flex'
        status.style.height = d.status + 'px'
        status.style.padding = d.island ? '0 30px' : '0 22px'
        $('island').style.display = d.island && !landscape ? 'block' : 'none'
        $('punch').style.display = d.punch && !landscape ? 'block' : 'none'
        $('home-bar').style.display = d.home ? 'block' : 'none'

        btns.querySelectorAll('.btn').forEach(b => b.classList.toggle('active', b.dataset.device === device))
        $('btn-rotate').classList.toggle('active', landscape)
        $('badge').textContent = `${APPS[app].label} · ${landscape ? d.h + '×' + d.w : d.w + '×' + d.h}`
        fit()
    }

    // Telefoon verkleinen als het venster te klein is
    function fit() {
        const stage = $('stage')
        const phone = $('phone')
        const sw = stage.clientWidth - 32
        const sh = stage.clientHeight - 36
        const scale = Math.min(1, sw / phone.offsetWidth, sh / phone.offsetHeight)
        phone.style.transform = `scale(${scale})`
    }
    window.addEventListener('resize', fit)

    // ── Navigatie ───────────────────────────────────────────
    function currentPath() {
        try {
            const l = frame.contentWindow.location
            return l.pathname + l.search + l.hash
        } catch { return frame.getAttribute('src') }
    }

    function go(path) {
        if (!path.startsWith('/')) path = '/' + path
        frame.src = path
        $('url').value = path
        store.set('path.' + app, path)
    }

    function setApp(key) {
        app = key
        history.replaceState(null, '', '/_devframe/' + key)
        go(store.get('path.' + key, APPS[key].path))
        layout()
    }

    // URL-veld meelaten lopen met de app (zelfde origin → leesbaar)
    frame.addEventListener('load', () => {
        const p = currentPath()
        $('url').value = p
        store.set('path.' + app, p)
        try {
            frame.contentWindow.addEventListener('hashchange', () => {
                const q = currentPath()
                $('url').value = q
                store.set('path.' + app, q)
            })
        } catch {}
    })

    $('app-select').onchange = e => setApp(e.target.value)
    $('btn-go').onclick = () => go($('url').value.trim() || APPS[app].path)
    $('url').addEventListener('keydown', e => { if (e.key === 'Enter') $('btn-go').click() })
    $('btn-reload').onclick = () => frame.contentWindow.location.reload()
    $('btn-open').onclick = () => window.open(currentPath(), '_blank')
    $('btn-rotate').onclick = () => { landscape = !landscape; store.set('landscape', landscape ? '1' : '0'); layout() }

    document.addEventListener('keydown', e => {
        if (e.target.tagName === 'INPUT' || e.target.tagName === 'SELECT') return
        const keys = Object.keys(DEVICES)
        if (e.key === 'd' || e.key === 'D') setDevice(keys[(keys.indexOf(device) + 1) % keys.length])
        if (e.key === 'l' || e.key === 'L') $('btn-rotate').click()
        if (e.key === 'r' || e.key === 'R') $('btn-reload').click()
    })

    // Klok
    const tick = () => {
        const n = new Date()
        $('time').textContent = n.getHours() + ':' + String(n.getMinutes()).padStart(2, '0')
    }
    tick(); setInterval(tick, 10000)

    // Start: ?path=/member#/mijn/stats opent meteen dat scherm
    const startPath = new URLSearchParams(location.search).get('path')
    go(startPath || store.get('path.' + app, APPS[app].path))
    layout()
})()
</script>
</body>
</html>
