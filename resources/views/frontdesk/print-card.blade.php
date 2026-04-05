<!DOCTYPE html>
<html lang="nl">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Kaart printen</title>
    <style>
        @page {
            size: 85.60mm 53.98mm;
            margin: 0;
        }

        * { box-sizing: border-box; }

        html,
        body {
            margin: 0;
            padding: 0;
            width: 85.60mm;
            height: 53.98mm;
            overflow: hidden;
            background: #ffffff;
            font-family: Arial, Helvetica, sans-serif;
            -webkit-print-color-adjust: exact;
            print-color-adjust: exact;
        }

        body {
            display: block;
        }

        .print-sheet {
            width: 85.60mm;
            height: 53.98mm;
            overflow: hidden;
            position: relative;
            background: #ffffff;
        }

        .print-svg {
            display: block;
            width: 85.60mm;
            height: 53.98mm;
        }

        @media screen {
            html,
            body {
                background: #0f172a;
                width: 100%;
                height: 100%;
            }

            body {
                display: flex;
                align-items: center;
                justify-content: center;
                padding: 24px;
            }

            .preview-stack {
                display: flex;
                flex-direction: column;
                align-items: center;
                gap: 14px;
            }

            .preview-meta {
                display: flex;
                gap: 10px;
                flex-wrap: wrap;
                justify-content: center;
                color: #cbd5e1;
                font-size: 12px;
            }

            .preview-meta span {
                border: 1px solid rgba(148, 163, 184, 0.25);
                background: rgba(15, 23, 42, 0.65);
                border-radius: 999px;
                padding: 6px 10px;
            }

            .print-sheet {
                box-shadow: 0 24px 80px rgba(15, 23, 42, 0.55);
            }
        }

        @media print {
            .preview-meta {
                display: none;
            }
        }
    </style>
</head>
@php
    $svgWidth = (int) ($template['width'] ?? 1011);
    $svgHeight = (int) ($template['height'] ?? 638);

    $escape = static fn (?string $value): string => e($value ?? '');

    $hexToRgb = static function (?string $value): string {
        $value = trim((string) $value);
        if ($value === '') {
            return '0 0 0';
        }

        if (preg_match('/^rgba?\(([^)]+)\)$/i', $value, $matches)) {
            $parts = array_map('trim', explode(',', $matches[1]));
            return implode(' ', array_slice($parts, 0, 3));
        }

        $value = ltrim($value, '#');
        if (strlen($value) === 3) {
            $value = $value[0].$value[0].$value[1].$value[1].$value[2].$value[2];
        }

        if (strlen($value) !== 6 || ! ctype_xdigit($value)) {
            return '0 0 0';
        }

        return hexdec(substr($value, 0, 2)).' '.hexdec(substr($value, 2, 2)).' '.hexdec(substr($value, 4, 2));
    };

    $alphaFromColor = static function (?string $value): float {
        $value = trim((string) $value);
        if (preg_match('/^rgba\(([^)]+)\)$/i', $value, $matches)) {
            $parts = array_map('trim', explode(',', $matches[1]));
            return isset($parts[3]) ? max(0, min(1, (float) $parts[3])) : 1.0;
        }

        return $value === 'transparent' ? 0.0 : 1.0;
    };

    $svgFill = static function (?string $value) use ($hexToRgb, $alphaFromColor): array {
        $value = trim((string) $value);
        if ($value === '' || $value === 'transparent') {
            return ['fill' => 'transparent', 'opacity' => 0];
        }

        if (str_starts_with($value, 'rgb')) {
            return ['fill' => 'rgb('.$hexToRgb($value).')', 'opacity' => $alphaFromColor($value)];
        }

        return ['fill' => $value, 'opacity' => $alphaFromColor($value)];
    };

    $imageAspect = static fn (string $fit): string => $fit === 'contain' ? 'xMidYMid meet' : 'xMidYMid slice';

    $textAnchor = static fn (string $align): string => $align === 'center' ? 'middle' : ($align === 'right' ? 'end' : 'start');
    $textX = static function (array $element): int {
        return $element['textAlign'] === 'center'
            ? (int) round($element['x'] + ($element['width'] / 2))
            : ($element['textAlign'] === 'right'
                ? (int) round($element['x'] + $element['width'] - 12)
                : (int) round($element['x'] + 12));
    };
    $textY = static function (array $element, array $lines): int {
        $fontSize = (int) $element['fontSize'];
        $lineHeight = (int) round($fontSize * 1.08);
        $contentHeight = max($lineHeight, count($lines) * $lineHeight);
        return (int) round($element['y'] + (($element['height'] - $contentHeight) / 2) + $fontSize);
    };
    $normalizeLines = static function (?string $text): array {
        $text = trim(str_replace(["\r\n", "\r"], "\n", (string) $text));
        return $text === '' ? [''] : explode("\n", $text);
    };

    $background = $svgFill($template['backgroundColor'] ?? '#ffffff');
@endphp
<body>
    <div class="preview-stack">
        <div class="preview-meta">
            <span>Kaart: {{ $card->rfid_uid }}</span>
            <span>Type: {{ $card->voucherTemplate?->name ?? 'Onbekend' }}</span>
            <span>300 dpi render · 1011 × 638 px</span>
        </div>

        <div class="print-sheet">
            <svg
                class="print-svg"
                xmlns="http://www.w3.org/2000/svg"
                xmlns:xlink="http://www.w3.org/1999/xlink"
                viewBox="0 0 {{ $svgWidth }} {{ $svgHeight }}"
                width="85.60mm"
                height="53.98mm"
                preserveAspectRatio="none"
                shape-rendering="geometricPrecision"
                text-rendering="geometricPrecision"
            >
                <rect x="0" y="0" width="{{ $svgWidth }}" height="{{ $svgHeight }}" fill="{{ $background['fill'] }}" fill-opacity="{{ $background['opacity'] }}" />

                @if(!empty($template['backgroundImageUrl']))
                    <image
                        x="0"
                        y="0"
                        width="{{ $svgWidth }}"
                        height="{{ $svgHeight }}"
                        href="{{ $template['backgroundImageUrl'] }}"
                        preserveAspectRatio="xMidYMid slice"
                    />
                @endif

                @foreach(($template['elements'] ?? []) as $element)
                    @php
                        $type = $element['type'] ?? 'text';
                        $x = (int) ($element['x'] ?? 0);
                        $y = (int) ($element['y'] ?? 0);
                        $width = max(1, (int) ($element['width'] ?? 1));
                        $height = max(1, (int) ($element['height'] ?? 1));
                        $radius = max(0, (int) ($element['borderRadius'] ?? 0));
                        $opacity = max(0, min(1, (float) ($element['opacity'] ?? 1)));
                        $fill = $svgFill($element['backgroundColor'] ?? 'transparent');
                        $textFill = $svgFill($element['color'] ?? '#ffffff');
                        $lines = $normalizeLines($element['displayText'] ?? '');
                    @endphp

                    <g opacity="{{ $opacity }}">
                        @if($type === 'shape')
                            <rect x="{{ $x }}" y="{{ $y }}" width="{{ $width }}" height="{{ $height }}" rx="{{ $radius }}" ry="{{ $radius }}" fill="{{ $fill['fill'] }}" fill-opacity="{{ $fill['opacity'] }}" />
                        @elseif(in_array($type, ['image', 'logo', 'photo'], true))
                            @if(!empty($element['imageUrl']))
                                @if($radius > 0)
                                    <defs>
                                        <clipPath id="clip-{{ $loop->index }}">
                                            <rect x="{{ $x }}" y="{{ $y }}" width="{{ $width }}" height="{{ $height }}" rx="{{ $radius }}" ry="{{ $radius }}" />
                                        </clipPath>
                                    </defs>
                                @endif
                                @if($fill['opacity'] > 0)
                                    <rect x="{{ $x }}" y="{{ $y }}" width="{{ $width }}" height="{{ $height }}" rx="{{ $radius }}" ry="{{ $radius }}" fill="{{ $fill['fill'] }}" fill-opacity="{{ $fill['opacity'] }}" />
                                @endif
                                <image
                                    x="{{ $x }}"
                                    y="{{ $y }}"
                                    width="{{ $width }}"
                                    height="{{ $height }}"
                                    href="{{ $element['imageUrl'] }}"
                                    preserveAspectRatio="{{ $imageAspect($element['fit'] ?? 'cover') }}"
                                    @if($radius > 0) clip-path="url(#clip-{{ $loop->index }})" @endif
                                />
                            @else
                                <rect x="{{ $x }}" y="{{ $y }}" width="{{ $width }}" height="{{ $height }}" rx="{{ $radius }}" ry="{{ $radius }}" fill="{{ $fill['opacity'] > 0 ? $fill['fill'] : '#1e293b' }}" fill-opacity="{{ $fill['opacity'] > 0 ? $fill['opacity'] : 1 }}" />
                                <text
                                    x="{{ (int) round($x + ($width / 2)) }}"
                                    y="{{ (int) round($y + ($height / 2) + 6) }}"
                                    text-anchor="middle"
                                    font-size="16"
                                    font-weight="700"
                                    fill="{{ $textFill['fill'] }}"
                                    fill-opacity="{{ $textFill['opacity'] }}"
                                    font-family="Arial, Helvetica, sans-serif"
                                >{{ $escape($element['label'] ?? 'Afbeelding') }}</text>
                            @endif
                        @elseif($type === 'qr')
                            <rect x="{{ $x }}" y="{{ $y }}" width="{{ $width }}" height="{{ $height }}" rx="{{ $radius }}" ry="{{ $radius }}" fill="#ffffff" />
                            <rect x="{{ $x + 24 }}" y="{{ $y + 24 }}" width="{{ max(1, $width - 48) }}" height="{{ max(1, $height - 48) }}" rx="16" ry="16" fill="none" stroke="#0f172a" stroke-width="10" />
                            <text
                                x="{{ (int) round($x + ($width / 2)) }}"
                                y="{{ (int) round($y + ($height / 2) - 12) }}"
                                text-anchor="middle"
                                font-size="26"
                                font-weight="700"
                                fill="#0f172a"
                                font-family="Arial, Helvetica, sans-serif"
                            >QR</text>
                            <text
                                x="{{ (int) round($x + ($width / 2)) }}"
                                y="{{ (int) round($y + ($height / 2) + 24) }}"
                                text-anchor="middle"
                                font-size="15"
                                font-weight="600"
                                fill="#0f172a"
                                font-family="Arial, Helvetica, sans-serif"
                            >{{ $escape($fields['voucher_code'] ?? ($card->rfid_uid ?? 'KAART')) }}</text>
                        @else
                            @if($fill['opacity'] > 0)
                                <rect x="{{ $x }}" y="{{ $y }}" width="{{ $width }}" height="{{ $height }}" rx="{{ $radius }}" ry="{{ $radius }}" fill="{{ $fill['fill'] }}" fill-opacity="{{ $fill['opacity'] }}" />
                            @endif

                            @php
                                $fontSize = (int) ($element['fontSize'] ?? 32);
                                $lineHeight = (int) round($fontSize * 1.08);
                                $anchor = $textAnchor($element['textAlign'] ?? 'left');
                                $textStartX = $textX($element);
                                $textStartY = $textY($element, $lines);
                            @endphp
                            <text
                                x="{{ $textStartX }}"
                                y="{{ $textStartY }}"
                                text-anchor="{{ $anchor }}"
                                font-size="{{ $fontSize }}"
                                font-weight="{{ (int) ($element['fontWeight'] ?? 700) }}"
                                fill="{{ $textFill['fill'] }}"
                                fill-opacity="{{ $textFill['opacity'] }}"
                                font-family="Arial, Helvetica, sans-serif"
                                letter-spacing="0"
                            >
                                @foreach($lines as $line)
                                    <tspan x="{{ $textStartX }}" dy="{{ $loop->first ? 0 : $lineHeight }}">{{ $escape($line) }}</tspan>
                                @endforeach
                            </text>
                        @endif
                    </g>
                @endforeach
            </svg>
        </div>
    </div>

    <script>
        window.addEventListener('load', () => {
            setTimeout(() => window.print(), 120)
        })
    </script>
</body>
</html>
