<!DOCTYPE html>
<html lang="nl">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>PlayDrive Member — Dev Frame</title>
    <style>
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

        body {
            background: #0f1117;
            min-height: 100vh;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            font-family: ui-sans-serif, system-ui, sans-serif;
            padding: 24px;
            gap: 20px;
        }

        .toolbar {
            display: flex;
            align-items: center;
            gap: 8px;
            flex-wrap: wrap;
            justify-content: center;
        }
        .toolbar-label {
            font-size: 11px;
            font-weight: 600;
            color: #4b5563;
            text-transform: uppercase;
            letter-spacing: 0.08em;
            margin-right: 4px;
        }
        .btn {
            background: #1c1f2a;
            border: 1px solid #2d3344;
            border-radius: 8px;
            color: #9ca3af;
            font-size: 12px;
            font-weight: 500;
            padding: 6px 12px;
            cursor: pointer;
            transition: all 0.15s;
            white-space: nowrap;
        }
        .btn:hover  { background: #252a38; color: #e5e7eb; border-color: #3d4560; }
        .btn.active { background: #1e3a5f; color: #60a5fa; border-color: #3b82f6; }
        .sep { width: 1px; height: 20px; background: #2d3344; margin: 0 4px; }
        .url-input {
            background: #1c1f2a;
            border: 1px solid #2d3344;
            border-radius: 8px;
            color: #9ca3af;
            font-size: 12px;
            padding: 6px 10px;
            width: 240px;
            outline: none;
            transition: border-color 0.15s;
            font-family: ui-monospace, monospace;
        }
        .url-input:focus { border-color: #3b82f6; color: #e5e7eb; }

        /* ── Frame afmetingen per device ── */
        .frame-outer { position: relative; transition: all 0.3s ease; }

        .frame-outer.iphone  { width: 393px; height: 852px; }
        .frame-outer.pixel   { width: 412px; height: 892px; }
        .frame-outer.compact { width: 375px; height: 667px; }

        .frame-outer.iphone  .frame-shell { border-radius: 54px; padding: 12px; }
        .frame-outer.pixel   .frame-shell { border-radius: 40px; padding: 14px; }
        .frame-outer.compact .frame-shell { border-radius: 46px; padding: 12px; }

        .frame-outer.iphone  .frame-inner { border-radius: 44px; }
        .frame-outer.pixel   .frame-inner { border-radius: 28px; }
        .frame-outer.compact .frame-inner { border-radius: 36px; }

        /* Status bar hoogte per device */
        .frame-outer.iphone  .status-bar { height: 54px; padding: 0 24px; }
        .frame-outer.pixel   .status-bar { height: 48px; padding: 0 20px; }
        .frame-outer.compact .status-bar { height: 44px; padding: 0 20px; }

        /* Notch enkel iPhone, punch enkel Pixel */
        .notch { display: none; }
        .punch  { display: none; }
        .frame-outer.iphone .notch { display: flex; }
        .frame-outer.pixel  .punch { display: flex; }

        /* Landscape */
        .frame-outer.landscape { transform: rotate(90deg); transform-origin: center center; }
        .frame-outer.landscape.iphone  { margin: 228px 0; }
        .frame-outer.landscape.pixel   { margin: 240px 0; }
        .frame-outer.landscape.compact { margin: 145px 0; }

        /* ── Shell (behuizing) ── */
        .frame-shell {
            width: 100%;
            height: 100%;
            background: linear-gradient(160deg, #2a2d38 0%, #1a1d26 60%, #111318 100%);
            border: 1px solid #3a3f52;
            box-shadow:
                inset 0 0 0 1px rgba(255,255,255,0.06),
                0 30px 80px rgba(0,0,0,0.7),
                0 8px 24px rgba(0,0,0,0.5);
            position: relative;
            overflow: hidden;
        }
        .frame-shell::before {
            content: '';
            position: absolute;
            left: -3px; top: 120px;
            width: 3px; height: 36px;
            background: #2a2d38;
            border-radius: 2px 0 0 2px;
            box-shadow: 0 48px 0 #2a2d38, 0 96px 0 #2a2d38;
        }
        .frame-shell::after {
            content: '';
            position: absolute;
            right: -3px; top: 160px;
            width: 3px; height: 60px;
            background: #2a2d38;
            border-radius: 0 2px 2px 0;
        }

        /* ── Scherm — flex kolom zodat statusbar boven iframe staat ── */
        .frame-inner {
            width: 100%;
            height: 100%;
            background: #030814;
            overflow: hidden;
            position: relative;
            display: flex;
            flex-direction: column;
        }

        /* ── Status bar: flex-item, NIET position:absolute ── */
        .status-bar {
            flex-shrink: 0;
            display: flex;
            align-items: center;
            justify-content: space-between;
            pointer-events: none;
            position: relative;
            z-index: 20;
            /* hoogte + padding komen van .frame-outer.X .status-bar */
        }

        /* Dynamic Island zweeft over de status bar */
        .notch {
            position: absolute;
            top: 10px;
            left: 50%;
            transform: translateX(-50%);
            width: 126px;
            height: 34px;
            background: #080808;
            border-radius: 20px;
            z-index: 30;
            align-items: center;
            justify-content: space-between;
            padding: 0 14px;
            pointer-events: none;
        }
        .notch-camera {
            width: 12px; height: 12px;
            background: #1a1a2e;
            border-radius: 50%;
            border: 2px solid #222;
        }
        .notch-speaker {
            width: 52px; height: 6px;
            background: #111;
            border-radius: 3px;
        }

        /* Punch-hole */
        .punch {
            position: absolute;
            top: 13px;
            left: 50%;
            transform: translateX(-50%);
            width: 12px; height: 12px;
            background: #080808;
            border-radius: 50%;
            z-index: 30;
            border: 2px solid #1a1a1a;
            pointer-events: none;
        }

        .status-time {
            font-size: 15px;
            font-weight: 600;
            color: #fff;
            font-variant-numeric: tabular-nums;
            letter-spacing: -0.3px;
            line-height: 1;
        }
        .status-icons { display: flex; align-items: center; gap: 6px; }
        .status-icons svg { fill: #fff; display: block; }

        /* ── iframe vult resterende hoogte ── */
        .iframe-wrap { flex: 1; min-height: 0; overflow: hidden; }
        iframe { width: 100%; height: 100%; border: none; display: block; }

        .dev-badge {
            position: absolute;
            bottom: -28px;
            left: 50%;
            transform: translateX(-50%);
            font-size: 10px;
            color: #374151;
            white-space: nowrap;
            letter-spacing: 0.05em;
        }

        .hints { font-size: 10px; color: #374151; text-align: center; letter-spacing: 0.04em; }
        kbd {
            background: #1c1f2a;
            border: 1px solid #2d3344;
            border-radius: 4px;
            padding: 1px 5px;
            font-size: 10px;
            color: #6b7280;
        }
    </style>
</head>
<body>

    <div class="toolbar">
        <span class="toolbar-label">Device</span>
        <button class="btn active" id="btn-iphone"  onclick="setDevice('iphone')">iPhone 15 Pro</button>
        <button class="btn"        id="btn-pixel"   onclick="setDevice('pixel')">Pixel 8</button>
        <button class="btn"        id="btn-compact" onclick="setDevice('compact')">iPhone SE</button>
        <div class="sep"></div>
        <button class="btn" id="btn-rotate" onclick="toggleLandscape()">⟳ Landscape</button>
        <div class="sep"></div>
        <span class="toolbar-label">Scherm</span>
        <input class="url-input" id="url-input" value="/member" placeholder="/member/..." />
        <button class="btn" onclick="navigate()">Ga</button>
        <div class="sep"></div>
        <button class="btn" onclick="reloadFrame()">↺ Herladen</button>
    </div>

    <div class="frame-outer iphone" id="frame-outer">
        <div class="frame-shell">
            <div class="frame-inner">

                <!-- Dynamic Island (iPhone) — zweeft over de statusbalk -->
                <div class="notch" id="notch">
                    <div class="notch-camera"></div>
                    <div class="notch-speaker"></div>
                </div>

                <!-- Punch-hole (Pixel) -->
                <div class="punch" id="punch"></div>

                <!-- Statusbalk als flex-item BOVEN de iframe -->
                <div class="status-bar">
                    <span class="status-time" id="status-time">9:41</span>
                    <div class="status-icons">
                        <svg width="17" height="12" viewBox="0 0 17 12">
                            <rect x="0"    y="8"   width="3" height="4"   rx="0.5" opacity="0.3"/>
                            <rect x="4.5"  y="5.5" width="3" height="6.5" rx="0.5" opacity="0.5"/>
                            <rect x="9"    y="3"   width="3" height="9"   rx="0.5" opacity="0.7"/>
                            <rect x="13.5" y="0"   width="3" height="12"  rx="0.5"/>
                        </svg>
                        <svg width="16" height="12" viewBox="0 0 16 12">
                            <path d="M8 9.5a1.5 1.5 0 110 3 1.5 1.5 0 010-3z"/>
                            <path d="M3.5 6.5C5 4.9 6.4 4 8 4s3 .9 4.5 2.5" stroke="#fff" stroke-width="1.5" fill="none" stroke-linecap="round" opacity="0.7"/>
                            <path d="M1 4C3.2 1.6 5.5 0 8 0s4.8 1.6 7 4" stroke="#fff" stroke-width="1.5" fill="none" stroke-linecap="round" opacity="0.4"/>
                        </svg>
                        <svg width="25" height="12" viewBox="0 0 25 12">
                            <rect x="0.5" y="0.5" width="21" height="11" rx="3" stroke="#fff" stroke-width="1" fill="none" opacity="0.35"/>
                            <rect x="2"   y="2"   width="16" height="8"  rx="1.5" fill="#fff"/>
                            <path d="M22.5 4v4a2 2 0 000-4z" fill="#fff" opacity="0.4"/>
                        </svg>
                    </div>
                </div>

                <!-- iframe vult de rest van de hoogte -->
                <div class="iframe-wrap">
                    <iframe id="app-frame" src="/member" title="PlayDrive Member App"></iframe>
                </div>

            </div>
        </div>
        <div class="dev-badge">DEV · PlayDrive Member</div>
    </div>

    <div class="hints">
        <kbd>D</kbd> Device wisselen &nbsp;·&nbsp;
        <kbd>R</kbd> Herladen &nbsp;·&nbsp;
        <kbd>L</kbd> Landscape
    </div>

    <script>
        let currentDevice = 'iphone'
        let isLandscape   = false
        const devices     = ['iphone', 'pixel', 'compact']

        function setDevice(d) {
            const outer = document.getElementById('frame-outer')
            outer.classList.remove(...devices)
            outer.classList.add(d)
            currentDevice = d
            document.getElementById('notch').style.display = (d === 'iphone') ? 'flex'  : 'none'
            document.getElementById('punch').style.display  = (d === 'pixel')  ? 'flex' : 'none'
            devices.forEach(x => document.getElementById('btn-' + x).classList.remove('active'))
            document.getElementById('btn-' + d).classList.add('active')
        }

        function toggleLandscape() {
            isLandscape = !isLandscape
            document.getElementById('frame-outer').classList.toggle('landscape', isLandscape)
            document.getElementById('btn-rotate').classList.toggle('active', isLandscape)
        }

        function navigate() {
            const path = document.getElementById('url-input').value.trim() || '/member'
            document.getElementById('app-frame').src = path
        }

        function reloadFrame() {
            const f = document.getElementById('app-frame')
            f.src = f.src
        }

        function updateClock() {
            const now = new Date()
            document.getElementById('status-time').textContent =
                now.getHours().toString().padStart(2,'0') + ':' +
                now.getMinutes().toString().padStart(2,'0')
        }
        updateClock()
        setInterval(updateClock, 10000)

        document.addEventListener('keydown', e => {
            if (e.target.tagName === 'INPUT') return
            if (e.key === 'd' || e.key === 'D') setDevice(devices[(devices.indexOf(currentDevice) + 1) % devices.length])
            if (e.key === 'r' || e.key === 'R') reloadFrame()
            if (e.key === 'l' || e.key === 'L') toggleLandscape()
        })
        document.getElementById('url-input').addEventListener('keydown', e => {
            if (e.key === 'Enter') navigate()
        })
    </script>
</body>
</html>
