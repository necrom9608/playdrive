<!DOCTYPE html>
<html lang="nl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex">
    <title>Afgemeld — PlayDrive</title>
    <style>
        body { margin: 0; min-height: 100vh; display: flex; align-items: center; justify-content: center; padding: 24px; box-sizing: border-box;
               background: radial-gradient(circle at top, #0f2d63 0%, #071327 46%, #030814 100%); font-family: ui-sans-serif, system-ui, sans-serif; color: #e2e8f0; }
        .card { max-width: 420px; width: 100%; background: rgba(15, 23, 42, .7); border: 1px solid rgba(75, 98, 148, .3); border-radius: 24px; padding: 32px 28px; text-align: center; }
        h1 { font-size: 20px; margin: 0 0 10px; color: #fff; }
        p { font-size: 14px; line-height: 1.6; color: #94a3b8; margin: 0 0 18px; }
        a { display: inline-block; background: #2563eb; color: #fff; text-decoration: none; font-size: 14px; font-weight: 600; padding: 12px 24px; border-radius: 12px; }
    </style>
</head>
<body>
    <div class="card">
        <h1>Je bent afgemeld</h1>
        <p>
            {{ $account->first_name }}, je krijgt geen mails meer wanneer iemand je eerste plaats afpakt.
            In de app zie je het nog wel op je Stats-kaart. Opnieuw aanzetten kan onder Profiel → Notificaties.
        </p>
        <a href="{{ url('/member#/profiel/notificaties') }}">Naar de app</a>
    </div>
</body>
</html>
