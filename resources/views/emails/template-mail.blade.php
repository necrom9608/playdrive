@php
    /*
     * Gedeelde layout voor alle template-mails (reservaties, …).
     * Tabellen en inline stijlen, zodat het ook in Outlook en Gmail goed oogt.
     * $brand: logo, tagline, address, phone, email, website (alles optioneel).
     */
    $brand = $brand ?? [];
    $accent = \App\Services\ReservationMailVars::ACCENT;
@endphp
<!DOCTYPE html>
<html lang="nl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="color-scheme" content="light">
    <meta name="supported-color-schemes" content="light">
    <title>{{ $mailSubject }}</title>
    <style>
        body { margin: 0; padding: 0; }
        .body p { margin: 0 0 14px; }
        @media (max-width: 600px) { .px { padding-left: 22px !important; padding-right: 22px !important; } }
    </style>
</head>
<body style="margin:0;padding:0;background:#eef1f6;font-family:-apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Arial,sans-serif;color:#0f172a;">
<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#eef1f6;">
    <tr><td align="center" style="padding:32px 12px;">
        <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="max-width:580px;background:#ffffff;border-radius:18px;overflow:hidden;box-shadow:0 6px 30px rgba(15,23,42,.08);">
            {{-- Kop: logo (of naam) op donkere band met accentlijn --}}
            <tr><td class="px" style="background:#0f172a;padding:24px 36px;">
                <table role="presentation" width="100%" cellpadding="0" cellspacing="0"><tr>
                    <td style="vertical-align:middle;">
                        @if(! empty($brand['logo']))
                            <img src="{{ $brand['logo'] }}" alt="{{ $tenantName }}" height="44" style="display:block;height:44px;width:auto;max-width:220px;border:0;">
                        @else
                            <span style="color:#ffffff;font-size:20px;font-weight:800;letter-spacing:.02em;">{{ $tenantName }}</span>
                        @endif
                    </td>
                    @if(! empty($brand['tagline']))
                        <td align="right" style="vertical-align:middle;color:#94a3b8;font-size:12px;">{{ $brand['tagline'] }}</td>
                    @endif
                </tr></table>
            </td></tr>
            <tr><td style="height:4px;background:{{ $accent }};line-height:4px;font-size:0;">&nbsp;</td></tr>

            {{-- Inhoud --}}
            <tr><td class="px body" style="padding:32px 36px 24px;font-size:15px;line-height:1.65;color:#334155;">
                {!! $bodyHtml !!}
            </td></tr>

            {{-- Voet --}}
            <tr><td class="px" style="padding:20px 36px 26px;border-top:1px solid #eef1f6;text-align:center;font-size:12px;line-height:1.7;color:#94a3b8;">
                <strong style="color:#64748b;">{{ $tenantName }}</strong>
                @if(! empty($brand['address']))<br>{{ $brand['address'] }}@endif
                @if(! empty($brand['phone']) || ! empty($brand['email']))
                    <br>
                    @if(! empty($brand['phone'])){{ $brand['phone'] }}@endif
                    @if(! empty($brand['phone']) && ! empty($brand['email'])) &middot; @endif
                    @if(! empty($brand['email']))<a href="mailto:{{ $brand['email'] }}" style="color:#94a3b8;">{{ $brand['email'] }}</a>@endif
                @endif
                @if(! empty($brand['website']))<br><a href="{{ $brand['website'] }}" style="color:#94a3b8;">{{ preg_replace('#^https?://(www\.)?#', '', rtrim($brand['website'], '/')) }}</a>@endif
                <br><span style="color:#cbd5e1;">Verzonden via PlayDrive</span>
            </td></tr>
        </table>
    </td></tr>
</table>
</body>
</html>
