<!DOCTYPE html>
<html lang="nl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex">
    <title>{{ $setup->name }} · {{ $tenant->display_name }}</title>
    <link rel="icon" type="image/png" sizes="32x32" href="/images/logos/favicon-32.png">

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">

    <style>
        :root {
            --bg-base: #0a0612;
            --bg-card: rgba(255, 255, 255, 0.04);
            --border: rgba(207, 199, 246, 0.14);
            --text: #f4f0fb;
            --text-2: rgba(207, 199, 246, 0.72);
            --text-3: rgba(207, 199, 246, 0.45);
            --purple: #8a7fe8;
            --pink: #d4537e;
            --amber: #f5b14c;
        }
        * { box-sizing: border-box; }
        html, body { margin: 0; padding: 0; }
        body {
            font-family: 'Inter', system-ui, -apple-system, sans-serif;
            background: var(--bg-base);
            color: var(--text);
            min-height: 100vh;
            line-height: 1.5;
        }
        body::before {
            content: '';
            position: fixed; inset: 0; pointer-events: none;
            background:
                radial-gradient(ellipse 80% 60% at 30% 0%, rgba(127, 119, 221, 0.25), transparent 60%),
                radial-gradient(ellipse 60% 50% at 80% 40%, rgba(212, 83, 126, 0.16), transparent 60%);
        }
        .wrap { position: relative; max-width: 480px; margin: 0 auto; padding: 32px 16px 48px; }
        .venue { display: flex; align-items: center; gap: 12px; margin-bottom: 28px; }
        .venue img { width: 44px; height: 44px; border-radius: 12px; object-fit: contain; background: rgba(255,255,255,0.06); }
        .venue-name { font-weight: 600; font-size: 15px; }
        .venue-sub { font-size: 12px; color: var(--text-3); }
        .card {
            background: var(--bg-card);
            border: 1px solid var(--border);
            border-radius: 24px;
            padding: 24px;
            backdrop-filter: blur(16px);
        }
        .chip {
            display: inline-flex; align-items: center; gap: 6px;
            font-size: 12px; font-weight: 600; letter-spacing: .02em;
            padding: 4px 10px; border-radius: 999px;
            border: 1px solid var(--border); color: var(--text-2);
        }
        .chip.warn { border-color: rgba(245,177,76,.4); color: var(--amber); background: rgba(245,177,76,.08); }
        h1 { font-size: 28px; line-height: 1.15; margin: 14px 0 6px; font-weight: 700; }
        .zone { color: var(--text-2); font-size: 14px; }
        .desc { color: var(--text-2); font-size: 14px; margin-top: 14px; white-space: pre-line; }
        .section-title { font-size: 12px; text-transform: uppercase; letter-spacing: .08em; color: var(--text-3); margin: 22px 0 10px; }
        .games { display: flex; flex-wrap: wrap; gap: 8px; }
        .game { font-size: 13px; padding: 6px 12px; border-radius: 10px; background: rgba(138,127,232,.12); border: 1px solid rgba(138,127,232,.25); }
        .soon {
            margin-top: 20px; padding: 16px; border-radius: 16px;
            border: 1px dashed var(--border); color: var(--text-2); font-size: 14px;
            display: flex; gap: 12px; align-items: flex-start;
        }
        .soon svg { flex-shrink: 0; width: 22px; height: 22px; color: var(--purple); }
        .btn {
            display: block; text-align: center; margin-top: 20px; padding: 14px 16px;
            border-radius: 14px; font-weight: 600; font-size: 15px; text-decoration: none; color: #fff;
            background: linear-gradient(135deg, var(--purple), var(--pink));
        }
        .foot { margin-top: 28px; text-align: center; font-size: 12px; color: var(--text-3); }
        .foot a { color: var(--text-2); text-decoration: none; }
    </style>
</head>
<body>
<div class="wrap">
    <div class="venue">
        @if ($tenant->logo_url)
            <img src="{{ $tenant->logo_url }}" alt="{{ $tenant->display_name }}">
        @endif
        <div>
            <div class="venue-name">{{ $tenant->display_name }}</div>
            @if ($tenant->city)
                <div class="venue-sub">{{ $tenant->city }}</div>
            @endif
        </div>
    </div>

    <div class="card">
        <span class="chip">{{ $typeLabel }}</span>
        @if ($setup->status === 'maintenance')
            <span class="chip warn">In onderhoud</span>
        @endif

        <h1>{{ $setup->name }}</h1>
        @if ($setup->zone)
            <div class="zone">{{ $setup->zone }}</div>
        @endif

        @if ($setup->description)
            <div class="desc">{{ $setup->description }}</div>
        @endif

        @if (! empty($setup->games))
            <div class="section-title">{{ count($setup->games) === 1 ? 'Spel' : 'Spellen' }}</div>
            <div class="games">
                @foreach ($setup->games as $game)
                    <span class="game">{{ $game }}</span>
                @endforeach
            </div>
        @endif

        <div class="soon">
            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" d="M16.5 18.75h-9m9 0a3 3 0 0 1 3 3h-15a3 3 0 0 1 3-3m9 0v-3.375c0-.621-.503-1.125-1.125-1.125h-.871M7.5 18.75v-3.375c0-.621.504-1.125 1.125-1.125h.872m5.007 0H9.497m5.007 0a7.454 7.454 0 0 1-.982-3.172M9.497 14.25a7.454 7.454 0 0 0 .981-3.172M5.25 4.236c-.982.143-1.954.317-2.916.52A6.003 6.003 0 0 0 7.73 9.728M5.25 4.236V4.5c0 2.108.966 3.99 2.48 5.228M5.25 4.236V2.721C7.456 2.41 9.71 2.25 12 2.25c2.291 0 4.545.16 6.75.47v1.516M7.73 9.728a6.726 6.726 0 0 0 2.748 1.35m8.272-6.842V4.5c0 2.108-.966 3.99-2.48 5.228m2.48-5.492a46.32 46.32 0 0 1 2.916.52 6.003 6.003 0 0 1-5.395 4.972m0 0a6.726 6.726 0 0 1-2.749 1.35m0 0a6.772 6.772 0 0 1-3.044 0" />
            </svg>
            <div>Binnenkort kan je hier je highscore registreren en het klassement bekijken.</div>
        </div>

        @if ($venueUrl)
            <a class="btn" href="{{ $venueUrl }}">Meer over {{ $tenant->display_name }}</a>
        @endif
    </div>

    <div class="foot">Powered by <a href="{{ url('/') }}">Playdrive</a></div>
</div>
</body>
</html>
