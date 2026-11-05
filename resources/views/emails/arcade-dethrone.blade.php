<!DOCTYPE html>
<html lang="nl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Je #1 is overgenomen</title>
    <style>
        body { margin: 0; padding: 0; background: #f1f5f9; font-family: ui-sans-serif, system-ui, sans-serif; }
        .wrapper { padding: 40px 16px; }
        .card { max-width: 520px; margin: 0 auto; background: #ffffff; border-radius: 16px; overflow: hidden; box-shadow: 0 4px 24px rgba(0,0,0,0.08); }
        .header { background: linear-gradient(135deg, #0f172a 0%, #1e3a5f 100%); padding: 32px 40px; text-align: center; }
        .header-title { color: #ffffff; font-size: 22px; font-weight: 700; letter-spacing: 0.1em; margin: 0; }
        .header-sub { color: #fbbf24; font-size: 13px; margin: 6px 0 0; }
        .body { padding: 32px 40px; }
        .greeting { font-size: 18px; font-weight: 600; color: #0f172a; margin: 0 0 12px; }
        .text { font-size: 15px; line-height: 1.7; color: #475569; margin: 0 0 20px; }
        .item { border: 1px solid #e2e8f0; border-radius: 12px; padding: 14px 16px; margin: 0 0 10px; }
        .item-title { font-size: 15px; font-weight: 600; color: #0f172a; margin: 0; }
        .item-chart { font-size: 12px; color: #64748b; margin: 2px 0 8px; }
        .item-scores { font-size: 13px; color: #334155; margin: 0; }
        .item-scores b { color: #0f172a; }
        .btn-wrap { text-align: center; margin: 28px 0 8px; }
        .btn { display: inline-block; background: #2563eb; color: #ffffff; text-decoration: none; font-size: 15px; font-weight: 600; padding: 14px 32px; border-radius: 12px; }
        .footer { padding: 20px 40px 32px; text-align: center; }
        .footer-text { font-size: 12px; color: #94a3b8; line-height: 1.6; margin: 0; }
        .footer-text a { color: #64748b; }
    </style>
</head>
<body>
<div class="wrapper">
    <div class="card">
        <div class="header">
            <p class="header-title">PLAYDRIVE</p>
            <p class="header-sub">🏆 Je #1 is overgenomen</p>
        </div>

        <div class="body">
            <p class="greeting">Hallo {{ $account->first_name }},</p>

            <p class="text">
                @if (count($items) === 1)
                    Iemand heeft bij {{ $venueName }} je eerste plaats afgepakt.
                @else
                    Bij {{ $venueName }} ben je je eerste plaats kwijt op {{ count($items) }} charts.
                @endif
                Tijd om ze terug te pakken?
            </p>

            @foreach ($items as $item)
                <div class="item">
                    <p class="item-title">{{ $item['title'] }}</p>
                    <p class="item-chart">{{ $item['game'] }} · {{ $item['chart'] }}</p>
                    <p class="item-scores">
                        <b>{{ $item['by'] }}</b>: {{ $item['by_score'] !== null ? number_format($item['by_score'], 0, ',', '.') : '—' }}
                        &nbsp;·&nbsp; jij: {{ $item['your_score'] !== null ? number_format($item['your_score'], 0, ',', '.') : '—' }}
                    </p>
                </div>
            @endforeach

            <div class="btn-wrap">
                <a href="{{ $statsUrl }}" class="btn">Bekijk de ranking</a>
            </div>
        </div>

        <div class="footer">
            <p class="footer-text">
                Je krijgt deze mail omdat je arcade-scores aan je PlayDrive-account gekoppeld zijn.<br>
                <a href="{{ $unsubscribeUrl }}">Geen mails meer over overgenomen plaatsen</a>
                · je kan dit ook aanpassen in de app onder Profiel → Notificaties.
            </p>
        </div>
    </div>
</div>
</body>
</html>
