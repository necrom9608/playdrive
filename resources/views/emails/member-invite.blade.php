<!DOCTYPE html>
<html lang="nl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Activeer je PlayDrive account</title>
    <style>
        body { margin: 0; padding: 0; background: #f1f5f9; font-family: ui-sans-serif, system-ui, sans-serif; }
        .wrapper { padding: 40px 16px; }
        .card { max-width: 520px; margin: 0 auto; background: #ffffff; border-radius: 16px; overflow: hidden; box-shadow: 0 4px 24px rgba(0,0,0,0.08); }
        .header { background: linear-gradient(135deg, #0f172a 0%, #1e3a5f 100%); padding: 36px 40px; text-align: center; }
        .header-title { color: #ffffff; font-size: 22px; font-weight: 700; letter-spacing: 0.1em; margin: 0; }
        .header-sub { color: #94a3b8; font-size: 13px; margin: 6px 0 0; }
        .body { padding: 36px 40px; }
        .greeting { font-size: 18px; font-weight: 600; color: #0f172a; margin: 0 0 16px; }
        .text { font-size: 15px; line-height: 1.7; color: #475569; margin: 0 0 20px; }
        .btn-wrap { text-align: center; margin: 32px 0; }
        .btn { display: inline-block; background: #2563eb; color: #ffffff; text-decoration: none; font-size: 15px; font-weight: 600; padding: 14px 32px; border-radius: 12px; }
        .note { font-size: 13px; color: #94a3b8; line-height: 1.6; margin: 0 0 20px; }
        .url-box { background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 8px; padding: 12px 16px; font-size: 12px; color: #64748b; word-break: break-all; margin: 0 0 24px; }
        .divider { border: none; border-top: 1px solid #e2e8f0; margin: 24px 0; }
        .footer { padding: 20px 40px 32px; text-align: center; }
        .footer-text { font-size: 12px; color: #94a3b8; line-height: 1.6; margin: 0; }
    </style>
</head>
<body>
<div class="wrapper">
    <div class="card">
        <div class="header">
            <p class="header-title">PLAYDRIVE</p>
            <p class="header-sub">{{ $tenantName ?? 'Jouw speelwereld' }}</p>
        </div>

        <div class="body">
            <p class="greeting">Hallo {{ $account->first_name }},</p>

            <p class="text">
                Je bent al een tijdje lid{{ $tenantName ? ' van ' . $tenantName : '' }}.
                We hebben een nieuw ledenplatform gelanceerd waarmee je jouw lidmaatschap, bezoeken en gegevens zelf kan beheren via de PlayDrive app.
            </p>

            <p class="text">
                Klik op de knop hieronder om je wachtwoord in te stellen en je account te activeren. De link is <strong>24 uur geldig</strong>.
            </p>

            <div class="btn-wrap">
                <a href="{{ $resetUrl }}" class="btn">Account activeren</a>
            </div>

            <p class="note">
                Werkt de knop niet? Kopieer dan deze link in je browser:
            </p>
            <div class="url-box">{{ $resetUrl }}</div>

            <hr class="divider">

            <p class="note">
                Als je deze mail niet verwacht had, kan je hem veilig negeren. Er wordt niets gewijzigd aan je account zolang je de link niet gebruikt.
            </p>
        </div>

        <div class="footer">
            <p class="footer-text">
                © {{ date('Y') }} PlayDrive · Dit is een automatisch gegenereerde mail, je kan er niet op antwoorden.
            </p>
        </div>
    </div>
</div>
</body>
</html>
