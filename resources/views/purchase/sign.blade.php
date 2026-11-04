@php
    /** @var \App\Models\PrivatePurchase|null $purchase */
    $tenantName = $tenant?->display_name ?? 'Playdrive';
    $money = fn ($v) => \App\Models\PrivatePurchase::money($v);
    $qty = fn ($q) => ((float) $q == floor((float) $q)) ? (int) $q : number_format((float) $q, 2, ',', '.');
@endphp
<!DOCTYPE html>
<html lang="nl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <meta name="robots" content="noindex, nofollow">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Aankoopborderel{{ $purchase ? ' ' . $purchase->number : '' }} · {{ $tenantName }}</title>
    <link rel="icon" type="image/png" sizes="32x32" href="/images/logos/favicon-32.png">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        :root {
            --bg: #f8fafc; --card: #ffffff; --border: #e2e8f0; --input: #cbd5e1;
            --text: #0f172a; --text-2: #334155; --muted: #475569;
            --accent: #2563eb; --accent-hover: #1d4ed8; --accent-soft: #eff6ff;
            --ok: #166534; --ok-soft: #dcfce7; --err: #b91c1c; --err-soft: #fef2f2;
        }
        * { box-sizing: border-box; }
        html, body { margin: 0; padding: 0; }
        body { font-family: 'Inter', system-ui, -apple-system, sans-serif; background: var(--bg); color: var(--text); line-height: 1.5; -webkit-text-size-adjust: 100%; }
        header { background: var(--card); border-bottom: 1px solid var(--border); }
        .bar { max-width: 560px; margin: 0 auto; padding: 14px 20px; display: flex; align-items: center; justify-content: space-between; gap: 12px; }
        .brand { display: flex; align-items: center; gap: 10px; font-weight: 700; font-size: 17px; }
        .brand img { height: 32px; width: auto; max-width: 140px; object-fit: contain; }
        .via { font-size: 12px; color: var(--muted); }
        main { max-width: 560px; margin: 0 auto; padding: 20px 20px 48px; display: flex; flex-direction: column; gap: 18px; }
        h1 { margin: 0; font-size: 24px; line-height: 1.25; }
        .lead { margin: 6px 0 0; font-size: 14px; color: var(--muted); }
        .steps { list-style: none; margin: 0; padding: 0; display: flex; gap: 6px; }
        .steps li { flex: 1; display: flex; flex-direction: column; gap: 6px; font-size: 12px; color: var(--muted); }
        .steps li::before { content: ''; height: 4px; border-radius: 2px; background: var(--input); }
        .steps li.done::before, .steps li.active::before { background: var(--accent); }
        .steps li.active { color: var(--accent); font-weight: 600; }
        .card { background: var(--card); border: 1px solid var(--border); border-radius: 14px; padding: 16px; display: flex; flex-direction: column; gap: 14px; }
        .card h2 { margin: 0; font-size: 16px; font-weight: 600; }
        .row { display: flex; justify-content: space-between; gap: 12px; font-size: 14px; }
        .row .amount { font-weight: 600; white-space: nowrap; }
        .total { border-top: 1px solid var(--border); padding-top: 12px; align-items: baseline; }
        .total strong { font-size: 18px; }
        .small { font-size: 13px; color: var(--muted); }
        .photos { display: flex; gap: 8px; flex-wrap: wrap; }
        .photos img { width: 84px; height: 64px; object-fit: cover; border-radius: 8px; border: 1px solid var(--border); }
        label.field { display: flex; flex-direction: column; gap: 6px; font-size: 13px; color: var(--text-2); }
        input[type=text], input[type=email], input[type=tel] {
            height: 48px; padding: 0 12px; border: 1px solid var(--input); border-radius: 8px; font: inherit; font-size: 16px; color: var(--text); background: #fff; width: 100%; min-width: 0;
        }
        input:focus { outline: 2px solid var(--accent); outline-offset: 1px; border-color: var(--accent); }
        input.invalid { border-color: var(--err); }
        .pair { display: flex; gap: 12px; }
        .pair > * { flex: 1; min-width: 0; }
        .pair > .narrow { flex: 0 0 110px; }
        .check { display: flex; gap: 12px; align-items: flex-start; font-size: 14px; line-height: 1.5; cursor: pointer; }
        .check input { width: 22px; height: 22px; flex: none; margin: 0; accent-color: var(--accent); }
        .err { display: none; font-size: 13px; color: var(--err); background: var(--err-soft); border: 1px solid #fecaca; border-radius: 8px; padding: 10px 12px; }
        .err.show { display: block; }
        .btn { display: flex; align-items: center; justify-content: center; gap: 8px; width: 100%; min-height: 52px; border: 0; border-radius: 10px; background: var(--accent); color: #fff; font: inherit; font-size: 16px; font-weight: 600; text-decoration: none; cursor: pointer; }
        .btn:hover { background: var(--accent-hover); }
        .btn:disabled { opacity: .6; cursor: wait; }
        .btn.secondary { background: #fff; color: var(--text); border: 1px solid var(--input); }
        .btn svg { width: 18px; height: 18px; }
        .linkbtn { border: 0; background: transparent; color: var(--accent); font: inherit; font-size: 14px; font-weight: 600; min-height: 44px; padding: 0 8px; cursor: pointer; }
        .back { display: inline-flex; align-items: center; gap: 6px; color: var(--text); text-decoration: none; font-size: 14px; border: 0; background: none; font: inherit; cursor: pointer; min-height: 44px; padding: 0; }
        .pad-wrap { position: relative; height: 220px; background: #fff; border: 2px dashed #94a3b8; border-radius: 12px; touch-action: none; }
        .pad-wrap canvas { position: absolute; inset: 0; width: 100%; height: 100%; border-radius: 12px; touch-action: none; cursor: crosshair; }
        .pad-line { position: absolute; left: 20px; right: 20px; bottom: 40px; border-top: 1px solid var(--input); pointer-events: none; }
        .pad-hint { position: absolute; left: 20px; bottom: 12px; font-size: 12px; color: var(--muted); pointer-events: none; }
        .summary { display: flex; flex-direction: column; gap: 4px; font-size: 14px; }
        .center { text-align: center; align-items: center; }
        .icon-circle { width: 72px; height: 72px; border-radius: 999px; display: flex; align-items: center; justify-content: center; margin: 12px auto 0; }
        .icon-circle.ok { background: var(--ok-soft); color: var(--ok); }
        .icon-circle.warn { background: #fef3c7; color: #92400e; }
        .icon-circle svg { width: 36px; height: 36px; }
        .hidden { display: none !important; }
        .foot { text-align: center; font-size: 12px; color: var(--muted); }
    </style>
</head>
<body>
<header>
    <div class="bar">
        <div class="brand">
            @if ($tenant?->logo_url)
                <img src="{{ $tenant->logo_url }}" alt="{{ $tenantName }}">
            @else
                <span>{{ $tenantName }}</span>
            @endif
        </div>
        <span class="via">via Playdrive</span>
    </div>
</header>

<main>
@if ($state === 'invalid')
    <div class="card center">
        <div class="icon-circle warn"><svg aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126ZM12 15.75h.007v.008H12v-.008Z"/></svg></div>
        <h1>Deze link werkt niet</h1>
        <p class="lead">Het borderel bestaat niet (meer) of werd geannuleerd. Neem contact op met {{ $tenantName }}.</p>
    </div>
@elseif ($state === 'expired')
    <div class="card center">
        <div class="icon-circle warn"><svg aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6h4.5m4.5 0a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z"/></svg></div>
        <h1>Deze link is verlopen</h1>
        <p class="lead">Vraag {{ $tenantName }} om een nieuwe link voor borderel {{ $purchase->number }}.</p>
    </div>
@elseif ($state === 'signed')
    <div class="card center">
        <div class="icon-circle ok"><svg aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"><path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12.75 6 6 9-13.5"/></svg></div>
        <h1>Al ondertekend</h1>
        <p class="lead">Borderel {{ $purchase->number }} werd ondertekend op {{ $purchase->signed_at?->copy()->setTimezone(\App\Models\PrivatePurchase::TIMEZONE)->format('d/m/Y H:i') }}.</p>
    </div>
    <a class="btn secondary" href="{{ url('/borderel/' . $token . '/pdf') }}" target="_blank" rel="noopener">
        <svg aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75V16.5M16.5 12 12 16.5m0 0L7.5 12m4.5 4.5V3"/></svg>
        PDF downloaden
    </a>
@else
    @php $transfer = $purchase->payment_method === \App\Models\PrivatePurchase::PAYMENT_TRANSFER; @endphp

    <ol class="steps" aria-label="Stappen">
        <li id="st1" class="active">1. Gegevens</li>
        <li id="st2">2. Handtekening</li>
        <li id="st3">3. Klaar</li>
    </ol>

    {{-- Stap 1: gegevens --}}
    <section id="step1" style="display:flex;flex-direction:column;gap:18px;">
        <div>
            <h1>Aankoopborderel</h1>
            <p class="lead">{{ $tenantName }} koopt onderstaande goederen van je. Vul je gegevens in en teken. Je krijgt het document daarna per mail.</p>
        </div>

        <div class="card">
            <div class="row" style="align-items:baseline;"><h2>Wat je verkoopt</h2><span class="small">{{ $purchase->number }}</span></div>
            @foreach ($purchase->items ?? [] as $item)
                <div class="row"><span>{{ $qty($item['quantity']) }} × {{ $item['description'] }}</span><span class="amount">{{ $money((float) $item['quantity'] * (float) $item['unit_price']) }}</span></div>
            @endforeach
            <div class="row total"><strong style="font-size:14px;">Totaal</strong><strong>{{ $money($purchase->total_amount) }}</strong></div>
            <div class="small">Betaling {{ $transfer ? 'per overschrijving' : 'contant' }}</div>
            @if (! empty($purchase->photos))
                <div class="photos">
                    @foreach ($purchase->photos as $i => $photo)
                        <img src="{{ url('/borderel/' . $token . '/photos/' . $i) }}" alt="Foto {{ $i + 1 }}" loading="lazy">
                    @endforeach
                </div>
            @endif
        </div>

        <form id="form" class="card" novalidate>
            <h2>Jouw gegevens</h2>
            <div class="pair">
                <label class="field">Voornaam<input type="text" name="first_name" autocomplete="given-name" required></label>
                <label class="field">Achternaam<input type="text" name="last_name" autocomplete="family-name" required></label>
            </div>
            <label class="field">Straat en nummer<input type="text" name="street" autocomplete="street-address" required></label>
            <div class="pair">
                <label class="field narrow">Postcode<input type="text" name="postal_code" inputmode="numeric" autocomplete="postal-code" required></label>
                <label class="field">Gemeente<input type="text" name="city" autocomplete="address-level2" required></label>
            </div>
            <label class="field">Land<input type="text" name="country" value="België" autocomplete="country-name"></label>
            <label class="field">E-mail <span class="small" style="font-size:12px;">hier sturen we het borderel naartoe</span>
                <input type="email" name="email" value="{{ $purchase->invite_email }}" autocomplete="email" required></label>
            <label class="field">Gsm (optioneel)<input type="tel" name="phone" autocomplete="tel" placeholder="+32 4.."></label>

            @if ($transfer)
                <h2 style="margin-top:8px;">Uitbetaling</h2>
                <label class="field">IBAN-rekeningnummer<input type="text" name="iban" autocomplete="off" placeholder="BE00 0000 0000 0000" style="letter-spacing:.02em;" required></label>
                <label class="field">Op naam van<input type="text" name="iban_holder" autocomplete="name" required></label>
            @endif

            <h2 style="margin-top:8px;">Verklaring</h2>
            <label class="check"><input type="checkbox" name="accept_owner"> {{ $declarations['owner'] }}</label>
            <label class="check"><input type="checkbox" name="accept_private"> {{ $declarations['private'] }}</label>
            <label class="check"><input type="checkbox" name="accept_price"> {{ $declarations['price'] }}</label>
            <p class="small" style="margin:0;font-size:12px;">Je gegevens worden enkel gebruikt voor dit borderel en de boekhouding van {{ $tenantName }}.</p>

            <div id="err1" class="err" role="alert"></div>
            <button type="submit" class="btn">Verder naar handtekening
                <svg aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M13.5 4.5 21 12m0 0-7.5 7.5M21 12H3"/></svg>
            </button>
        </form>
    </section>

    {{-- Stap 2: handtekening --}}
    <section id="step2" class="hidden" style="flex-direction:column;gap:18px;">
        <button type="button" class="back" id="back">
            <svg aria-hidden="true" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M10.5 19.5 3 12m0 0 7.5-7.5M3 12h18"/></svg>
            Terug naar gegevens
        </button>

        <div class="card summary">
            <span class="small">Je verkoopt aan {{ $tenantName }}</span>
            <strong>{{ count($purchase->items ?? []) }} {{ count($purchase->items ?? []) === 1 ? 'artikel' : 'artikelen' }} · {{ $money($purchase->total_amount) }}</strong>
            <span class="small" id="sum-seller"></span>
        </div>

        <div style="display:flex;flex-direction:column;gap:8px;">
            <div class="row" style="align-items:center;">
                <strong id="sig-label" style="font-size:15px;">Teken hieronder met je vinger</strong>
                <button type="button" class="linkbtn" id="clear">Wissen</button>
            </div>
            <div class="pad-wrap" role="img" aria-labelledby="sig-label">
                <div class="pad-line"></div>
                <span class="pad-hint">Handtekening verkoper</span>
                <canvas id="pad"></canvas>
            </div>
            <span class="small">Getekend{{ $purchase->sign_place ? ' te ' . $purchase->sign_place : '' }} op {{ now(\App\Models\PrivatePurchase::TIMEZONE)->format('d/m/Y') }}</span>
        </div>

        <p class="small" style="margin:0;font-size:12px;">Met ondertekenen bevestig je de verklaring uit stap 1. Tijdstip en IP-adres worden als bewijs bij het document bewaard.</p>

        <div id="err2" class="err" role="alert"></div>
        <button type="button" class="btn" id="sign">
            <svg aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="m16.862 4.487 1.687-1.688a1.875 1.875 0 1 1 2.652 2.652L10.582 16.07a4.5 4.5 0 0 1-1.897 1.13L6 18l.8-2.685a4.5 4.5 0 0 1 1.13-1.897l8.932-8.931Z"/></svg>
            Ondertekenen en verzenden
        </button>
    </section>

    {{-- Stap 3: klaar --}}
    <section id="step3" class="hidden" style="flex-direction:column;gap:18px;">
        <div class="card center">
            <div class="icon-circle ok"><svg aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2"><path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12.75 6 6 9-13.5"/></svg></div>
            <h1 id="done-title">Bedankt</h1>
            <p class="lead" id="done-text">Het borderel is ondertekend.</p>
        </div>
        <div class="card" style="gap:10px;">
            <div class="row"><span class="small">Borderel</span><strong>{{ $purchase->number }}</strong></div>
            <div class="row"><span class="small">Bedrag</span><strong>{{ $money($purchase->total_amount) }}</strong></div>
            <div class="row"><span class="small">Betaling</span><strong>{{ $transfer ? 'per overschrijving' : 'contant' }}</strong></div>
            <div class="row"><span class="small">Ondertekend</span><strong id="done-at"></strong></div>
        </div>
        <a class="btn secondary" id="pdf" href="#" target="_blank" rel="noopener">
            <svg aria-hidden="true" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75V16.5M16.5 12 12 16.5m0 0L7.5 12m4.5 4.5V3"/></svg>
            PDF downloaden
        </a>
    </section>

    <script>
    (function () {
        var submitUrl = @json(url('/borderel/' . $token));
        var transfer = @json($transfer);
        var form = document.getElementById('form');
        var step1 = document.getElementById('step1');
        var step2 = document.getElementById('step2');
        var step3 = document.getElementById('step3');
        var err1 = document.getElementById('err1');
        var err2 = document.getElementById('err2');
        var data = {};

        function showStep(n) {
            step1.classList.toggle('hidden', n !== 1);
            step2.classList.toggle('hidden', n !== 2);
            step3.classList.toggle('hidden', n !== 3);
            if (n === 2) { step2.style.display = 'flex'; resizePad(); }
            if (n === 3) { step3.style.display = 'flex'; }
            ['st1', 'st2', 'st3'].forEach(function (id, i) {
                var el = document.getElementById(id);
                el.classList.toggle('active', i + 1 === n);
                el.classList.toggle('done', i + 1 < n);
            });
            window.scrollTo(0, 0);
        }

        function showError(el, msg) { el.textContent = msg; el.classList.toggle('show', !!msg); }

        // IBAN mod-97 (zelfde controle als de server)
        function ibanValid(value) {
            var iban = (value || '').toUpperCase().replace(/[^A-Z0-9]/g, '');
            if (!/^[A-Z]{2}\d{2}[A-Z0-9]{10,30}$/.test(iban)) return false;
            if (iban.indexOf('BE') === 0 && iban.length !== 16) return false;
            var r = iban.slice(4) + iban.slice(0, 4), rem = 0;
            for (var i = 0; i < r.length; i++) {
                var c = r.charCodeAt(i), d = c >= 65 ? String(c - 55) : r[i];
                for (var j = 0; j < d.length; j++) rem = (rem * 10 + Number(d[j])) % 97;
            }
            return rem === 1;
        }

        form.addEventListener('submit', function (e) {
            e.preventDefault();
            showError(err1, '');
            var fd = new FormData(form);
            data = {};
            fd.forEach(function (v, k) { data[k] = typeof v === 'string' ? v.trim() : v; });

            var required = ['first_name', 'last_name', 'street', 'postal_code', 'city', 'email'];
            if (transfer) required.push('iban', 'iban_holder');
            var missing = false;
            required.forEach(function (k) {
                var input = form.elements[k];
                var bad = !data[k];
                input.classList.toggle('invalid', bad);
                if (bad) missing = true;
            });
            if (missing) return showError(err1, 'Vul de gemarkeerde velden in.');
            if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(data.email)) {
                form.elements.email.classList.add('invalid');
                return showError(err1, 'Dit e-mailadres is ongeldig.');
            }
            if (transfer && !ibanValid(data.iban)) {
                form.elements.iban.classList.add('invalid');
                return showError(err1, 'Dit rekeningnummer klopt niet. Controleer het even.');
            }
            if (!form.elements.accept_owner.checked || !form.elements.accept_private.checked || !form.elements.accept_price.checked) {
                return showError(err1, 'Bevestig alle verklaringen.');
            }
            data.accept_owner = data.accept_private = data.accept_price = true;

            document.getElementById('sum-seller').textContent = data.first_name + ' ' + data.last_name
                + (transfer ? ' · ' + data.iban.toUpperCase().replace(/\s+/g, '').slice(0, 4) + ' •••• ' + data.iban.replace(/\s+/g, '').slice(-4) : '');
            showStep(2);
        });

        document.getElementById('back').addEventListener('click', function () { showStep(1); });

        // ---- Handtekening ----
        var canvas = document.getElementById('pad');
        var ctx = canvas.getContext('2d');
        var strokes = [], current = null, cssW = 0, cssH = 0;

        function resizePad() {
            var rect = canvas.getBoundingClientRect();
            if (!rect.width) return;
            var ratio = window.devicePixelRatio || 1;
            cssW = rect.width; cssH = rect.height;
            canvas.width = Math.round(rect.width * ratio);
            canvas.height = Math.round(rect.height * ratio);
            ctx.setTransform(ratio, 0, 0, ratio, 0, 0);
            redraw();
        }

        function redraw() {
            ctx.clearRect(0, 0, cssW, cssH);
            ctx.lineWidth = 2.6; ctx.lineCap = 'round'; ctx.lineJoin = 'round'; ctx.strokeStyle = '#0f172a';
            strokes.forEach(function (s) {
                ctx.beginPath();
                ctx.moveTo(s[0][0], s[0][1]);
                if (s.length === 1) ctx.lineTo(s[0][0] + 0.1, s[0][1]);
                for (var i = 1; i < s.length; i++) ctx.lineTo(s[i][0], s[i][1]);
                ctx.stroke();
            });
        }

        function point(e) {
            var rect = canvas.getBoundingClientRect();
            return [Math.round((e.clientX - rect.left) * 10) / 10, Math.round((e.clientY - rect.top) * 10) / 10];
        }

        canvas.addEventListener('pointerdown', function (e) {
            e.preventDefault();
            canvas.setPointerCapture(e.pointerId);
            current = [point(e)];
            strokes.push(current);
            showError(err2, '');
            redraw();
        });
        canvas.addEventListener('pointermove', function (e) {
            if (!current) return;
            e.preventDefault();
            var events = e.getCoalescedEvents ? e.getCoalescedEvents() : [e];
            events.forEach(function (ev) {
                var p = point(ev), last = current[current.length - 1];
                if (Math.abs(p[0] - last[0]) + Math.abs(p[1] - last[1]) >= 1.5) current.push(p);
            });
            redraw();
        });
        function endStroke() { current = null; }
        canvas.addEventListener('pointerup', endStroke);
        canvas.addEventListener('pointercancel', endStroke);
        window.addEventListener('resize', function () { if (!step2.classList.contains('hidden')) resizePad(); });

        document.getElementById('clear').addEventListener('click', function () { strokes = []; redraw(); });

        function inkPoints() { return strokes.reduce(function (n, s) { return n + s.length; }, 0); }

        var signBtn = document.getElementById('sign');
        signBtn.addEventListener('click', function () {
            showError(err2, '');
            if (inkPoints() < 8) return showError(err2, 'Zet eerst je handtekening in het vak.');

            signBtn.disabled = true;
            signBtn.lastChild.textContent = ' Bezig met ondertekenen…';

            var payload = Object.assign({}, data, { signature: { w: cssW, h: cssH, strokes: strokes } });

            fetch(submitUrl, {
                method: 'POST',
                credentials: 'same-origin',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                    'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
                },
                body: JSON.stringify(payload)
            }).then(function (res) {
                return res.json().catch(function () { return {}; }).then(function (body) { return { ok: res.ok, status: res.status, body: body }; });
            }).then(function (r) {
                if (!r.ok) {
                    var msg = r.body && r.body.message ? r.body.message : 'Er ging iets mis. Probeer opnieuw.';
                    if (r.body && r.body.errors) {
                        var first = Object.keys(r.body.errors)[0];
                        msg = r.body.errors[first][0];
                        if (first !== 'signature') { showStep(1); showError(err1, msg); return; }
                    }
                    if (r.status === 419) msg = 'De pagina stond te lang open. Herlaad de pagina en probeer opnieuw.';
                    showError(err2, msg);
                    return;
                }
                var d = r.body.data || {};
                document.getElementById('done-title').textContent = 'Bedankt, ' + (d.first_name || data.first_name);
                document.getElementById('done-text').textContent = d.email
                    ? 'Het borderel is ondertekend. Je ontvangt het document als PDF op ' + d.email + '.'
                    : 'Het borderel is ondertekend.';
                document.getElementById('done-at').textContent = d.signed_at || '';
                document.getElementById('pdf').href = d.pdf_url;
                showStep(3);
            }).catch(function () {
                showError(err2, 'Geen verbinding. Controleer je internet en probeer opnieuw.');
            }).finally(function () {
                signBtn.disabled = false;
                signBtn.lastChild.textContent = ' Ondertekenen en verzenden';
            });
        });
    })();
    </script>
@endif

    <div class="foot">Powered by Playdrive</div>
</main>
</body>
</html>
