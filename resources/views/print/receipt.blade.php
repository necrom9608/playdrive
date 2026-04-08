<!DOCTYPE html>
<html lang="nl">
<head>
    <meta charset="UTF-8">
    <title>Bon #{{ $receipt['order']['id'] }}</title>
    <style>
        @page { size: 80mm auto; margin: 0; }
        body { font-family: Arial, sans-serif; margin: 0; color: #0f172a; }
        .receipt { width: 72mm; padding: 4mm; }
        .center { text-align: center; }
        .small { font-size: 11px; line-height: 1.4; }
        .normal { font-size: 12px; line-height: 1.45; }
        .large { font-size: 16px; font-weight: bold; }
        .muted { color: #475569; }
        table { width: 100%; border-collapse: collapse; font-size: 12px; }
        th, td { padding: 4px 0; vertical-align: top; }
        .border-top { border-top: 1px dashed #64748b; margin-top: 8px; padding-top: 8px; }
        .border-bottom { border-bottom: 1px dashed #64748b; margin-bottom: 8px; padding-bottom: 8px; }
        .logo-wrap { margin-bottom: 8px; }
        .logo { max-width: 40mm; max-height: 18mm; height: auto; }
    </style>
</head>
<body>
<div class="receipt">
    <div class="center border-bottom">
        @if(!empty($receipt['meta']['logo_url']))
            <div class="logo-wrap">
                <img src="{{ $receipt['meta']['logo_url'] }}" alt="Logo" class="logo">
            </div>
        @endif
        <div class="large">{{ $receipt['meta']['tenant_name'] }}</div>
        @if($receipt['meta']['address'])<div class="small">{{ $receipt['meta']['address'] }}</div>@endif
        @if($receipt['meta']['phone'])<div class="small">{{ $receipt['meta']['phone'] }}</div>@endif
        @if($receipt['meta']['email'])<div class="small">{{ $receipt['meta']['email'] }}</div>@endif
        @if($receipt['meta']['vat'])<div class="small">BTW: {{ $receipt['meta']['vat'] }}</div>@endif
    </div>

    <div class="normal">Bon: <strong>#{{ $receipt['order']['id'] }}</strong></div>
    <div class="normal">Datum: {{ $receipt['order']['paid_at'] }}</div>
    <div class="normal">Betaalmethode: {{ $receipt['order']['payment_method'] }}</div>
    @if($receipt['registration'])
        <div class="normal">Reservatie: {{ $receipt['registration']['name'] }}</div>
    @endif

    <div class="border-top border-bottom">
        <table>
            @foreach($receipt['lines'] as $line)
                <tr>
                    <td style="width:58%;">{{ $line['name'] }}</td>
                    <td style="width:12%; text-align:center;">{{ $line['quantity'] }}x</td>
                    <td style="width:30%; text-align:right;">€ {{ number_format($line['total'], 2, ',', '.') }}</td>
                </tr>
            @endforeach
        </table>
    </div>

    <table>
        <tr>
            <td>Excl. btw</td>
            <td style="text-align:right;">€ {{ number_format($receipt['order']['subtotal'], 2, ',', '.') }}</td>
        </tr>
        <tr>
            <td>BTW</td>
            <td style="text-align:right;">€ {{ number_format($receipt['order']['vat'], 2, ',', '.') }}</td>
        </tr>
        <tr>
            <td><strong>Totaal</strong></td>
            <td style="text-align:right;"><strong>€ {{ number_format($receipt['order']['total'], 2, ',', '.') }}</strong></td>
        </tr>
    </table>

    @if($receipt['order']['notes'])
        <div class="border-top">
            <div class="small">Opmerking: {{ $receipt['order']['notes'] }}</div>
        </div>
    @endif

    <div class="center small border-top">{{ $receipt['meta']['footer'] }}</div>
</div>
</body>
</html>
