<!DOCTYPE html>
<html lang="nl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Bon #{{ $receipt['order']['id'] }}</title>
    <style>
        @page { size: 80mm auto; margin: 0; }
        html, body { margin: 0; padding: 0; background: #fff; color: #000; font-family: Arial, Helvetica, sans-serif; }
        body { width: 80mm; }
        .receipt { width: 72mm; padding: 4mm; }
        .center { text-align: center; }
        .muted { color: #333; }
        .small { font-size: 11px; }
        .normal { font-size: 12px; }
        .large { font-size: 18px; font-weight: bold; }
        .divider { border-top: 1px dashed #000; margin: 8px 0; }
        table { width: 100%; border-collapse: collapse; }
        td { vertical-align: top; font-size: 12px; padding: 2px 0; }
        td.qty { width: 12%; }
        td.name { width: 56%; padding-right: 4px; }
        td.price { width: 32%; text-align: right; }
        .totals td { padding: 2px 0; }
        .totals .grand td { font-size: 16px; font-weight: bold; padding-top: 6px; }
    </style>
</head>
<body onload="window.print(); setTimeout(() => window.close(), 400);">
<div class="receipt">
    <div class="center">
        <div class="large">{{ $receipt['meta']['tenant_name'] }}</div>
        @if($receipt['meta']['address'])<div class="small">{{ $receipt['meta']['address'] }}</div>@endif
        @if($receipt['meta']['phone'])<div class="small">{{ $receipt['meta']['phone'] }}</div>@endif
        @if($receipt['meta']['vat'])<div class="small">BTW: {{ $receipt['meta']['vat'] }}</div>@endif
    </div>

    <div class="divider"></div>

    <div class="normal">Bon: <strong>#{{ $receipt['order']['id'] }}</strong></div>
    <div class="normal">Datum: {{ $receipt['order']['paid_at'] }}</div>
    <div class="normal">Betaalmethode: {{ $receipt['order']['payment_method'] }}</div>
    @if($receipt['registration'])
        <div class="normal">Reservatie: {{ $receipt['registration']['name'] }}</div>
    @endif

    <div class="divider"></div>

    <table>
        @foreach($receipt['lines'] as $line)
            <tr>
                <td class="qty">{{ $line['quantity'] }}x</td>
                <td class="name">{{ $line['name'] }}</td>
                <td class="price">€ {{ number_format($line['total'], 2, ',', '.') }}</td>
            </tr>
        @endforeach
    </table>

    <div class="divider"></div>

    <table class="totals">
        <tr>
            <td>Excl. BTW</td>
            <td style="text-align:right;">€ {{ number_format($receipt['order']['subtotal'], 2, ',', '.') }}</td>
        </tr>
        <tr>
            <td>BTW</td>
            <td style="text-align:right;">€ {{ number_format($receipt['order']['vat'], 2, ',', '.') }}</td>
        </tr>
        <tr class="grand">
            <td>Totaal</td>
            <td style="text-align:right;">€ {{ number_format($receipt['order']['total'], 2, ',', '.') }}</td>
        </tr>
    </table>

    @if($receipt['order']['notes'])
        <div class="divider"></div>
        <div class="small">Opmerking: {{ $receipt['order']['notes'] }}</div>
    @endif

    <div class="divider"></div>
    <div class="center small">{{ $receipt['meta']['footer'] }}</div>
    <div class="center small muted">Playdrive</div>
</div>
</body>
</html>
