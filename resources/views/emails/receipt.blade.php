<!DOCTYPE html>
<html lang="nl">
<head>
    <meta charset="UTF-8">
    <title>Bon</title>
</head>
<body style="margin:0;padding:24px;background:#f8fafc;font-family:Arial,Helvetica,sans-serif;color:#0f172a;">
    <div style="max-width:640px;margin:0 auto;background:#ffffff;border:1px solid #e2e8f0;border-radius:16px;overflow:hidden;">
        <div style="padding:24px;border-bottom:1px solid #e2e8f0;">
            @if(!empty($receipt['meta']['logo_url']))
                <div style="margin-bottom:16px;">
                    <img src="{{ $receipt['meta']['logo_url'] }}" alt="Logo" style="max-height:64px;max-width:220px;display:block;">
                </div>
            @endif

            <h1 style="margin:0 0 8px;font-size:22px;line-height:1.2;">{{ $receipt['meta']['tenant_name'] }}</h1>
            @if(!empty($receipt['meta']['address']))<div style="font-size:14px;color:#475569;">{{ $receipt['meta']['address'] }}</div>@endif
            @if(!empty($receipt['meta']['phone']))<div style="font-size:14px;color:#475569;">Tel: {{ $receipt['meta']['phone'] }}</div>@endif
            @if(!empty($receipt['meta']['email']))<div style="font-size:14px;color:#475569;">{{ $receipt['meta']['email'] }}</div>@endif
            @if(!empty($receipt['meta']['vat']))<div style="font-size:14px;color:#475569;">BTW: {{ $receipt['meta']['vat'] }}</div>@endif
        </div>

        <div style="padding:24px;">
            <p style="margin:0 0 16px;font-size:16px;">Hierbij ontvang je jouw bon.</p>

            <div style="margin-bottom:20px;font-size:14px;color:#334155;">
                <div><strong>Bon:</strong> #{{ $receipt['order']['id'] }}</div>
                <div><strong>Datum:</strong> {{ $receipt['order']['paid_at'] }}</div>
                <div><strong>Betaalmethode:</strong> {{ $receipt['order']['payment_method'] }}</div>
                @if($receipt['registration'])
                    <div><strong>Reservatie:</strong> {{ $receipt['registration']['name'] }}</div>
                @endif
            </div>

            <table style="width:100%;border-collapse:collapse;font-size:14px;">
                <thead>
                    <tr>
                        <th style="text-align:left;padding:10px 0;border-bottom:1px solid #e2e8f0;">Product</th>
                        <th style="text-align:center;padding:10px 0;border-bottom:1px solid #e2e8f0;">Aantal</th>
                        <th style="text-align:right;padding:10px 0;border-bottom:1px solid #e2e8f0;">Prijs</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($receipt['lines'] as $line)
                        <tr>
                            <td style="padding:10px 0;border-bottom:1px solid #f1f5f9;">{{ $line['name'] }}</td>
                            <td style="padding:10px 0;text-align:center;border-bottom:1px solid #f1f5f9;">{{ $line['quantity'] }}</td>
                            <td style="padding:10px 0;text-align:right;border-bottom:1px solid #f1f5f9;">€ {{ number_format($line['total'], 2, ',', '.') }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>

            <div style="margin-top:20px;font-size:14px;color:#334155;">
                <div style="display:flex;justify-content:space-between;padding:4px 0;"><span>Excl. btw</span><strong>€ {{ number_format($receipt['order']['subtotal'], 2, ',', '.') }}</strong></div>
                <div style="display:flex;justify-content:space-between;padding:4px 0;"><span>BTW</span><strong>€ {{ number_format($receipt['order']['vat'], 2, ',', '.') }}</strong></div>
                <div style="display:flex;justify-content:space-between;padding:8px 0 0;margin-top:8px;border-top:1px solid #e2e8f0;font-size:16px;"><span>Totaal</span><strong>€ {{ number_format($receipt['order']['total'], 2, ',', '.') }}</strong></div>
            </div>

            @if($receipt['order']['notes'])
                <p style="margin:18px 0 0;font-size:14px;color:#475569;"><strong>Opmerking:</strong> {{ $receipt['order']['notes'] }}</p>
            @endif
        </div>

        <div style="padding:20px 24px;background:#f8fafc;border-top:1px solid #e2e8f0;font-size:13px;color:#64748b;">
            {{ $receipt['meta']['footer'] }}
        </div>
    </div>
</body>
</html>
