<!DOCTYPE html>
<html lang="nl">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Kaart printen</title>
    <style>
        :root {
            --card-width: 85.60mm;
            --card-height: 53.98mm;
            --safe-margin: 1.35mm;
            --printable-width: calc(var(--card-width) - (var(--safe-margin) * 2));
            --printable-height: calc(var(--card-height) - (var(--safe-margin) * 2));
            --preview-width: min(92vw, 980px);
            --preview-ratio: 85.60 / 53.98;
        }

        @page {
            size: 85.60mm 53.98mm;
            margin: 0;
        }

        * {
            box-sizing: border-box;
        }

        html,
        body {
            margin: 0;
            padding: 0;
            font-family: Arial, Helvetica, sans-serif;
            -webkit-print-color-adjust: exact;
            print-color-adjust: exact;
            background: #0f172a;
            color: #e2e8f0;
        }

        body {
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 24px;
        }

        .screen-shell {
            display: flex;
            flex-direction: column;
            gap: 14px;
            align-items: center;
            width: 100%;
        }

        .screen-note {
            max-width: 980px;
            border: 1px solid rgba(148, 163, 184, 0.2);
            background: rgba(15, 23, 42, 0.78);
            border-radius: 16px;
            padding: 12px 16px;
            font-size: 13px;
            line-height: 1.45;
            box-shadow: 0 18px 48px rgba(15, 23, 42, 0.35);
        }

        .screen-note strong {
            color: #f8fafc;
        }

        .preview-frame {
            width: var(--preview-width);
            aspect-ratio: var(--preview-ratio);
            display: flex;
            align-items: center;
            justify-content: center;
        }

        .print-page {
            width: var(--card-width);
            height: var(--card-height);
            background: #ffffff;
            overflow: hidden;
            position: relative;
        }

        .preview-frame .print-page {
            width: 100%;
            height: 100%;
            box-shadow: 0 28px 80px rgba(15, 23, 42, 0.48);
            border-radius: 14px;
        }

        .card-safe {
            position: absolute;
            inset: var(--safe-margin);
            overflow: hidden;
            background: #ffffff;
            border-radius: 2.4mm;
        }

        .preview-frame .card-safe {
            outline: 1px dashed rgba(15, 23, 42, 0.12);
            outline-offset: -1px;
        }

        .card-surface {
            position: absolute;
            inset: 0;
            overflow: hidden;
            background: {{ $template['backgroundColor'] ?? '#111827' }};
            background-image: @if(!empty($template['backgroundImageUrl'])) url('{{ $template['backgroundImageUrl'] }}') @else none @endif;
            background-size: {{ $template['backgroundSize'] ?? 'cover' }};
            background-position: {{ $template['backgroundPosition'] ?? 'center' }};
            background-repeat: no-repeat;
        }

        .element {
            position: absolute;
            overflow: hidden;
            display: block;
        }

        .element--image img,
        .element--logo img,
        .element--photo img {
            width: 100%;
            height: 100%;
            display: block;
        }

        .element--shape .shape-box {
            width: 100%;
            height: 100%;
        }

        .element--text,
        .element--field,
        .element--shape,
        .element--qr,
        .element--image,
        .element--logo,
        .element--photo {
            -webkit-print-color-adjust: exact;
            print-color-adjust: exact;
        }

        .element-text-box {
            width: 100%;
            height: 100%;
            display: flex;
            align-items: center;
            line-height: 1.08;
            white-space: pre-wrap;
            word-break: break-word;
            padding: 0.8mm 1.1mm;
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
            border: 0.45mm solid #0f172a;
            border-radius: 1.8mm;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            gap: 0.8mm;
            color: #0f172a;
            text-align: center;
            padding: 1.2mm;
            background: #ffffff;
        }

        .qr-placeholder__title {
            font-size: 2.2mm;
            font-weight: 700;
            letter-spacing: 0.08em;
            text-transform: uppercase;
        }

        .qr-placeholder__value {
            font-size: 1.7mm;
            font-weight: 600;
            word-break: break-all;
        }

        .missing-image {
            width: 100%;
            height: 100%;
            display: flex;
            align-items: center;
            justify-content: center;
            text-align: center;
            padding: 1.2mm;
            font-size: 1.8mm;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.08em;
        }

        @media print {
            html,
            body {
                width: var(--card-width);
                height: var(--card-height);
                background: #ffffff;
                overflow: hidden;
            }

            body {
                display: block;
                min-height: 0;
                padding: 0;
            }

            .screen-shell,
            .preview-frame {
                width: auto;
                height: auto;
                display: block;
            }

            .screen-note {
                display: none !important;
            }

            .preview-frame .print-page {
                width: var(--card-width);
                height: var(--card-height);
                box-shadow: none;
                border-radius: 0;
            }

            .preview-frame .card-safe {
                outline: none;
                border-radius: 0;
            }
        }
    </style>
</head>
<body>
    <div class="screen-shell">
        <div class="screen-note">
            <strong>Badgy 100 printmodus.</strong> Deze pagina staat nu op echt kaartformaat.
            Zet in Firefox nog wel <strong>kopteksten/voetteksten uit</strong> en laat de schaal op <strong>100%</strong> of <strong>werkelijke grootte</strong> staan.
            De buitenste witte rand van 1,35 mm is bewust voorzien voor de Badgy 100.
        </div>

        <div class="preview-frame">
            <div class="print-page">
                <div class="card-safe">
                    <div class="card-surface">
                        @php
                            $templateWidth = max(1, (float) ($template['width'] ?? 1016));
                            $templateHeight = max(1, (float) ($template['height'] ?? 638));
                        @endphp

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
                                $fit = $element['fit'] ?? (in_array($type, ['logo'], true) ? 'contain' : 'cover');
                                $imageUrl = $element['imageUrl'] ?? '';
                                $source = $element['source'] ?? '';
                                $fieldValue = $fields[$source] ?? null;
                                $displayText = $type === 'field'
                                    ? ($fieldValue ?: '{{ ' . ($source ?: 'veld') . ' }}')
                                    : (($element['text'] ?? '') !== '' ? $element['text'] : ($element['label'] ?? ''));
                                $justify = $textAlign === 'center' ? 'center' : ($textAlign === 'right' ? 'flex-end' : 'flex-start');
                                $leftPercent = ($left / $templateWidth) * 100;
                                $topPercent = ($top / $templateHeight) * 100;
                                $widthPercent = ($width / $templateWidth) * 100;
                                $heightPercent = ($height / $templateHeight) * 100;
                            @endphp

                            <div
                                class="element element--{{ $type }}"
                                style="
                                    left: {{ $leftPercent }}%;
                                    top: {{ $topPercent }}%;
                                    width: {{ $widthPercent }}%;
                                    height: {{ $heightPercent }}%;
                                    border-radius: calc(var(--printable-width) * {{ $radius }} / {{ $templateWidth }});
                                    opacity: {{ $opacity }};
                                    z-index: {{ $zIndex }};
                                "
                            >
                                @if($type === 'shape')
                                    <div
                                        class="shape-box"
                                        style="
                                            background: {{ $bg }};
                                            border-radius: calc(var(--printable-width) * {{ $radius }} / {{ $templateWidth }});
                                        "
                                    ></div>
                                @elseif(in_array($type, ['image', 'logo', 'photo'], true))
                                    @if($imageUrl)
                                        <img
                                            src="{{ $imageUrl }}"
                                            alt=""
                                            style="
                                                object-fit: {{ $fit }};
                                                border-radius: calc(var(--printable-width) * {{ $radius }} / {{ $templateWidth }});
                                                background: {{ $bg }};
                                            "
                                        >
                                    @else
                                        <div
                                            class="missing-image"
                                            style="
                                                border-radius: calc(var(--printable-width) * {{ $radius }} / {{ $templateWidth }});
                                                background: {{ $bg !== 'transparent' ? $bg : '#1e293b' }};
                                                color: {{ $color }};
                                            "
                                        >
                                            {{ $element['label'] ?? 'Afbeelding' }}
                                        </div>
                                    @endif
                                @elseif($type === 'qr')
                                    <div class="qr-placeholder">
                                        <div class="qr-placeholder__title">QR</div>
                                        <div class="qr-placeholder__value">{{ $fields['voucher_code'] ?? ($card->rfid_uid ?? 'KAART') }}</div>
                                    </div>
                                @else
                                    <div
                                        class="element-text-box"
                                        style="
                                            justify-content: {{ $justify }};
                                            color: {{ $color }};
                                            background: {{ $bg }};
                                            font-size: calc(var(--printable-height) * {{ $fontSize }} / {{ $templateHeight }});
                                            font-weight: {{ $fontWeight }};
                                            text-align: {{ $textAlign }};
                                            border-radius: calc(var(--printable-width) * {{ $radius }} / {{ $templateWidth }});
                                        "
                                    >
                                        {{ $displayText }}
                                    </div>
                                @endif
                            </div>
                        @endforeach
                    </div>
                </div>
            </div>
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
            setTimeout(() => window.print(), 180)
        })

        window.addEventListener('afterprint', () => {
            setTimeout(() => {
                try {
                    window.close()
                } catch (error) {
                    // ignore
                }
            }, 180)
        })
    </script>
</body>
</html>
