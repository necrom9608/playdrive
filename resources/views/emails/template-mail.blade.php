<!DOCTYPE html>
<html lang="nl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $subject }}</title>
    <style>
        body { margin: 0; padding: 0; background: #f1f5f9; font-family: ui-sans-serif, system-ui, Arial, sans-serif; color: #0f172a; }
        .wrapper { padding: 40px 16px; }
        .card { max-width: 560px; margin: 0 auto; background: #ffffff; border-radius: 16px; overflow: hidden; box-shadow: 0 4px 24px rgba(0,0,0,0.08); }
        .header { background: linear-gradient(135deg, #0f172a 0%, #1e3a5f 100%); padding: 28px 40px; }
        .header-title { color: #ffffff; font-size: 18px; font-weight: 700; margin: 0; letter-spacing: 0.05em; }
        .body { padding: 32px 40px; font-size: 15px; line-height: 1.7; color: #334155; }
        .body p { margin: 0 0 14px; }
        .body a { color: #3b82f6; }
        .footer { padding: 18px 40px 28px; text-align: center; font-size: 12px; color: #94a3b8; border-top: 1px solid #f1f5f9; }
    </style>
</head>
<body>
<div class="wrapper">
    <div class="card">
        <div class="header">
            <p class="header-title">{{ $tenantName }}</p>
        </div>
        <div class="body">
            {!! $bodyHtml !!}
        </div>
        <div class="footer">
            © {{ $tenantName }} &middot; Verzonden via PlayDrive
        </div>
    </div>
</div>
</body>
</html>
