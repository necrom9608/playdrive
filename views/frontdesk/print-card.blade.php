<!DOCTYPE html>
<html lang="nl">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Kaart printen</title>
    <style>
        :root {
            --card-width: {{ (int) ($template['width'] ?? 1016) }}px;
            --card-height: {{ (int) ($template['height'] ?? 638) }}px;
        }

        @page {
            size: auto;
            margin: 8mm;
        }

        * { box-sizing: border-box; }
        html, body { margin: 0; padding: 0; background: #0f172a; color: #fff; font-family: Arial, Helvetica, sans-serif; }
        body { display: flex; min-height: 100vh; align-items: center; justify-content: center; padding: 24px; }
        .sheet { display: flex; flex-direction: column; gap: 16px; align-items: center; }
        .sheet__meta { display: flex; gap: 10px; flex-wrap: wrap; justify-content: center; color: #cbd5e1; font-size: 12px; }
        .sheet__meta span { border: 1px solid rgba(148, 163, 184, 0.25); background: rgba(15, 23, 42, 0.65); border-radius: 999px; padding: 6px 10px; }
        .card {
            position: relative;
            width: var(--card-width);
            height: var(--card-height);
            overflow: hidden;
            border-radius: 28px;
            background: {{ $template['backgroundColor'] ?? '#111827' }};
            background-image: @if(!empty($template['backgroundImageUrl'])) url('{{ $template['backgroundImageUrl'] }}') @else none @endif;
            background-size: {{ $template['backgroundSize'] ?? 'cover' }};
            background-position: {{ $template['backgroundPosition'] ?? 'center' }};
            background-repeat: no-repeat;
            box-shadow: 0 24px 80px rgba(15, 23, 42, 0.55);
            -webkit-print-color-adjust: exact;
            print-color-adjust: exact;
        }
        .element {
            position: absolute;
            overflow: hidden;
            display: block;
            -webkit-print-color-adjust: exact;
            print-color-adjust: exact;
        }
        .element--text,
        .element--field {
            padding: 8px 12px;
            display: flex;
            align-items: center;
            line-height: 1.1;
            white-space: pre-wrap;
        }
        .element--image img,
        .element--logo img,
        .element--photo img {
            width: 100%;
            height: 100%;
            display: block;
        }
        .element--qr {
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 8%;
            background: #ffffff;
        }
        .qr-placeholder {
            width: 100%;
            height: 100%;
            border: 6px solid #0f172a;
            border-radius: 18px;
            display: flex;
            align-items: center;
            justify-content: center;
            flex-direction: column;
            gap: 8px;
            color: #0f172a;
            text-align: center;
            padding: 12px;
        }
        .qr-placeholder__title { font-size: 18px; font-weight: 700; letter-spacing: 0.08em; text-transform: uppercase; }
        .qr-placeholder__value { font-size: 14px; font-weight: 600; word-break: break-all; }
        .shape-box { width: 100%; height: 100%; }

        @media print {
            html, body { background: #fff; }
            body { padding: 0; min-height: auto; }
            .sheet__meta { display: none; }
            .card { box-shadow: none; }
        }
    </style>
</head>
<body>
    <div class="sheet">
        <div class="sheet__meta">
            <span>Kaart: {{ $card->rfid_uid }}</span>
            <span>Type: {{ $card->voucherTemplate?->name ?? 'Onbekend' }}</span>
            <span>Template: {{ $card->voucherTemplate?->badgeTemplate?->name ?? 'Geen template' }}</span>
        </div>

        <div class="card">
            @foreach(($template['elements'] ?? []) as $element)
                @php
                    $type = $element['type'] ?? 'text';
                    $left = (float) ($element['x'] ?? 0);
                    $top = (float) ($element['y'] ?? 0);
                    $width = max(1, (float) ($element['width'] ?? 1));
                    $height = max(1, (float) ($element['height'] ?? 1));
                    $radius = (float) ($element['borderRadius'] ?? 0);
                    $opacity = (float) ($element['opacity'] ?? 1);
                    $zIndex = (int) ($element['zIndex'] ?? 1);
                    $bg = $element['backgroundColor'] ?? 'transparent';
                    $color = $element['color'] ?? '#ffffff';
                    $fontSize = (float) ($element['fontSize'] ?? 32);
                    $fontWeight = (int) ($element['fontWeight'] ?? 700);
                    $textAlign = $element['textAlign'] ?? 'left';
                    $fit = $element['fit'] ?? (in_array($type, ['logo']) ? 'contain' : 'cover');
                    $imageUrl = $element['imageUrl'] ?? '';
                    $source = $element['source'] ?? '';
                    $fieldValue = $fields[$source] ?? null;
                    $displayText = $type === 'field'
                        ? ($fieldValue ?: '{{ ' . ($source ?: 'veld') . ' }}')
                        : (($element['text'] ?? '') !== '' ? $element['text'] : ($element['label'] ?? ''));
                @endphp
                <div
                    class="element element--{{ $type }}"
                    style="left: {{ $left }}px; top: {{ $top }}px; width: {{ $width }}px; height: {{ $height }}px; border-radius: {{ $radius }}px; opacity: {{ $opacity }}; z-index: {{ $zIndex }};"
                >
                    @if($type === 'shape')
                        <div class="shape-box" style="background: {{ $bg }}; border-radius: {{ $radius }}px;"></div>
                    @elseif(in_array($type, ['image', 'logo', 'photo'], true))
                        @if($imageUrl)
                            <img src="{{ $imageUrl }}" alt="" style="object-fit: {{ $fit }}; border-radius: {{ $radius }}px; background: {{ $bg }};">
                        @else
                            <div style="width: 100%; height: 100%; display: flex; align-items: center; justify-content: center; text-align: center; padding: 12px; border-radius: {{ $radius }}px; background: {{ $bg !== 'transparent' ? $bg : '#1e293b' }}; color: {{ $color }}; font-size: 14px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.1em;">{{ $element['label'] ?? 'Afbeelding' }}</div>
                        @endif
                    @elseif($type === 'qr')
                        <div class="qr-placeholder">
                            <div class="qr-placeholder__title">QR</div>
                            <div class="qr-placeholder__value">{{ $fields['voucher_code'] ?? ($card->rfid_uid ?? 'KAART') }}</div>
                        </div>
                    @else
                        @php
                            $justify = $textAlign === 'center' ? 'center' : ($textAlign === 'right' ? 'flex-end' : 'flex-start');
                        @endphp
                        <div
                            style="width: 100%; height: 100%; display: flex; align-items: center; justify-content: {{ $justify }}; color: {{ $color }}; background: {{ $bg }}; font-size: {{ $fontSize }}px; font-weight: {{ $fontWeight }}; text-align: {{ $textAlign }}; border-radius: {{ $radius }}px;"
                        >
                            {{ $displayText }}
                        </div>
                    @endif
                </div>
            @endforeach
        </div>
    </div>

    <script>
        function waitForImages() {
            const images = Array.from(document.images || [])

            if (!images.length) {
                return Promise.resolve()
            }

            return Promise.all(images.map((image) => {
                if (image.complete) {
                    return Promise.resolve()
                }

                return new Promise((resolve) => {
                    image.addEventListener('load', resolve, { once: true })
                    image.addEventListener('error', resolve, { once: true })
                })
            }))
        }

        window.addEventListener('load', async () => {
            await waitForImages()
            window.focus()
            setTimeout(() => window.print(), 150)
        })

        window.addEventListener('afterprint', () => {
            setTimeout(() => {
                try {
                    window.close()
                } catch (e) {
                    // ignore
                }
            }, 200)
        })
    </script>
</body>
</html>
