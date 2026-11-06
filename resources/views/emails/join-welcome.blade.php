<!DOCTYPE html>
<html lang="nl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Welkom bij {{ $tenantName }}</title>
    <style>
        body { margin: 0; padding: 0; background: #f1f5f9; font-family: ui-sans-serif, system-ui, sans-serif; }
        .wrapper { padding: 40px 16px; }
        .card { max-width: 520px; margin: 0 auto; background: #ffffff; border-radius: 16px; overflow: hidden; box-shadow: 0 4px 24px rgba(0,0,0,0.08); }
        .header { padding: 28px 40px; text-align: center; }
        .header img { max-height: 56px; max-width: 220px; }
        .header-title { color: #ffffff; font-size: 22px; font-weight: 700; letter-spacing: 0.08em; margin: 0; }
        .body { padding: 36px 40px; }
        .greeting { font-size: 20px; font-weight: 700; color: #0f172a; margin: 0 0 16px; }
        .text { font-size: 15px; line-height: 1.7; color: #475569; margin: 0 0 20px; }
        .btn-wrap { text-align: center; margin: 32px 0; }
        .btn { display: inline-block; color: #ffffff; text-decoration: none; font-size: 15px; font-weight: 700; padding: 14px 28px; border-radius: 12px; }
        .code { background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 12px; padding: 16px; text-align: center; margin: 0 0 24px; }
        .code small { display: block; font-size: 12px; color: #64748b; margin-bottom: 6px; }
        .code strong { font-size: 22px; letter-spacing: 0.08em; color: #0f172a; }
        .note { font-size: 13px; color: #94a3b8; line-height: 1.6; margin: 0 0 16px; }
        .url-box { background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 8px; padding: 12px 16px; font-size: 12px; color: #64748b; word-break: break-all; margin: 0 0 24px; }
        .footer { padding: 20px 40px 32px; text-align: center; }
        .footer-text { font-size: 12px; color: #94a3b8; line-height: 1.6; margin: 0; }
    </style>
</head>
<body>
<div class="wrapper">
    <div class="card">
        <div class="header" style="background: {{ $headerBg }};">
            @if ($logoUrl)
                <img src="{{ $logoUrl }}" alt="{{ $tenantName }}">
            @else
                <p class="header-title">{{ $tenantName }}</p>
            @endif
        </div>

        <div class="body">
            <p class="greeting">Hoi {{ $account->first_name }}, welkom bij {{ $tenantName }}!</p>

            <p class="text">
                Je bent nu lid. Bevestig nog even je e-mailadres en kies een wachtwoord.
                Daarmee log je in op de Playdrive-app, waar je je bezoeken, scores en events vindt.
            </p>

            <div class="btn-wrap">
                <a href="{{ $setupUrl }}" class="btn" style="background: {{ $accent }};">Bevestigen en wachtwoord kiezen</a>
            </div>

            <div class="code">
                <small>Nog geen lidkaart? Toon deze code aan de kassa</small>
                <strong>{{ $code }}</strong>
            </div>

            <p class="note">Werkt de knop niet? Kopieer dan deze link in je browser. De link is 24 uur geldig.</p>
            <div class="url-box">{{ $setupUrl }}</div>

            <p class="note">Heb je je niet geregistreerd bij {{ $tenantName }}? Dan mag je deze mail negeren.</p>
        </div>

        <div class="footer">
            <p class="footer-text">{{ $tenantName }} · powered by Playdrive · Dit is een automatische mail, je kan er niet op antwoorden.</p>
        </div>
    </div>
</div>
</body>
</html>
