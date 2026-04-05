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

        .print-sheet {
            width: 85.60mm;
            height: 53.98mm;
            overflow: hidden;
            position: relative;
            background: #ffffff;
        }

        .print-image,
        .render-canvas {
            display: block;
            width: 85.60mm;
            height: 53.98mm;
        }

        .render-canvas {
            display: none;
        }

        .render-error {
            display: none;
            position: absolute;
            inset: 0;
            align-items: center;
            justify-content: center;
            padding: 24px;
            text-align: center;
            color: #7f1d1d;
            background: #fee2e2;
            font-size: 12px;
            font-weight: 700;
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
<body>
<div class="preview-stack">
    <div class="preview-meta">
        <span>Kaart: {{ $card->rfid_uid }}</span>
        <span>Type: {{ $card->voucherTemplate?->name ?? 'Onbekend' }}</span>
        <span>PNG render · {{ (int) ($template['width'] ?? 1011) }} × {{ (int) ($template['height'] ?? 638) }} px</span>
    </div>

    <div class="print-sheet">
        <canvas id="renderCanvas" class="render-canvas" width="{{ (int) ($template['width'] ?? 1011) }}" height="{{ (int) ($template['height'] ?? 638) }}"></canvas>
        <img id="printImage" class="print-image" alt="Kaart preview" />
        <div id="renderError" class="render-error">De kaart kon niet gerenderd worden voor print.</div>
    </div>
</div>

<script>
    const template = @json($template);
    const fields = @json($fields);
    const card = @json([
            'id' => $card->id,
            'rfid_uid' => $card->rfid_uid,
        ]);

    const canvas = document.getElementById('renderCanvas')
    const image = document.getElementById('printImage')
    const errorBox = document.getElementById('renderError')
    const ctx = canvas.getContext('2d')

    function roundedRectPath(context, x, y, width, height, radius) {
        const r = Math.max(0, Math.min(radius || 0, width / 2, height / 2))
        context.beginPath()
        context.moveTo(x + r, y)
        context.arcTo(x + width, y, x + width, y + height, r)
        context.arcTo(x + width, y + height, x, y + height, r)
        context.arcTo(x, y + height, x, y, r)
        context.arcTo(x, y, x + width, y, r)
        context.closePath()
    }

    function parseColor(value, fallback = 'transparent') {
        if (!value || value === 'transparent') {
            return fallback
        }
        return value
    }

    function loadImage(src) {
        return new Promise((resolve, reject) => {
            if (!src) {
                resolve(null)
                return
            }

            const img = new Image()
            if (/^https?:/i.test(src) && !src.startsWith(window.location.origin)) {
                img.crossOrigin = 'anonymous'
            }
            img.onload = () => resolve(img)
            img.onerror = () => reject(new Error(`Afbeelding kon niet geladen worden: ${src}`))
            img.src = src
        })
    }

    function drawFittedImage(context, img, x, y, width, height, fit = 'cover') {
        const imageRatio = img.width / img.height
        const targetRatio = width / height

        let drawWidth
        let drawHeight
        let offsetX = x
        let offsetY = y

        if (fit === 'contain') {
            if (imageRatio > targetRatio) {
                drawWidth = width
                drawHeight = width / imageRatio
                offsetY += (height - drawHeight) / 2
            } else {
                drawHeight = height
                drawWidth = height * imageRatio
                offsetX += (width - drawWidth) / 2
            }
        } else {
            if (imageRatio > targetRatio) {
                drawHeight = height
                drawWidth = height * imageRatio
                offsetX -= (drawWidth - width) / 2
            } else {
                drawWidth = width
                drawHeight = width / imageRatio
                offsetY -= (drawHeight - height) / 2
            }
        }

        context.drawImage(img, offsetX, offsetY, drawWidth, drawHeight)
    }

    function drawBackground(context, img, width, height, size = 'cover') {
        drawFittedImage(context, img, 0, 0, width, height, size === 'contain' ? 'contain' : 'cover')
    }

    function getDisplayText(element) {
        if (element.type === 'field') {
            return element.displayText || ('[[ ' + (element.source || 'veld') + ' ]]')
        }
        if (element.type === 'text') {
            return element.displayText || element.text || 'Tekst'
        }
        return element.displayText || element.label || ''
    }

    function drawTextElement(context, element) {
        const x = Number(element.x || 0)
        const y = Number(element.y || 0)
        const width = Number(element.width || 1)
        const height = Number(element.height || 1)
        const radius = Number(element.borderRadius || 0)
        const fontSize = Number(element.fontSize || 32)
        const fontWeight = Number(element.fontWeight || 700)
        const backgroundColor = parseColor(element.backgroundColor)
        const color = parseColor(element.color, '#ffffff')
        const align = element.textAlign || 'left'
        const text = String(getDisplayText(element) || '')
        const lines = text.replace(/\r\n/g, '\n').replace(/\r/g, '\n').split('\n')
        const horizontalPadding = 12
        const lineHeight = Math.round(fontSize * 1.08)
        const contentHeight = Math.max(lineHeight, lines.length * lineHeight)
        const startY = y + ((height - contentHeight) / 2) + fontSize

        context.save()
        context.globalAlpha = Number(element.opacity ?? 1)

        if (backgroundColor !== 'transparent') {
            roundedRectPath(context, x, y, width, height, radius)
            context.fillStyle = backgroundColor
            context.fill()
        }

        context.fillStyle = color
        context.font = `${fontWeight} ${fontSize}px Arial, Helvetica, sans-serif`
        context.textBaseline = 'alphabetic'
        context.textAlign = align === 'center' ? 'center' : (align === 'right' ? 'right' : 'left')

        const textX = align === 'center'
            ? x + (width / 2)
            : (align === 'right' ? x + width - horizontalPadding : x + horizontalPadding)

        lines.forEach((line, index) => {
            context.fillText(line, textX, startY + (index * lineHeight))
        })

        context.restore()
    }

    function drawShapeElement(context, element) {
        const x = Number(element.x || 0)
        const y = Number(element.y || 0)
        const width = Number(element.width || 1)
        const height = Number(element.height || 1)
        const radius = Number(element.borderRadius || 0)

        context.save()
        context.globalAlpha = Number(element.opacity ?? 1)
        roundedRectPath(context, x, y, width, height, radius)
        context.fillStyle = parseColor(element.backgroundColor, '#7c3aed')
        context.fill()
        context.restore()
    }

    async function drawMediaElement(context, element) {
        const x = Number(element.x || 0)
        const y = Number(element.y || 0)
        const width = Number(element.width || 1)
        const height = Number(element.height || 1)
        const radius = Number(element.borderRadius || 0)
        const backgroundColor = parseColor(element.backgroundColor)

        context.save()
        context.globalAlpha = Number(element.opacity ?? 1)

        if (backgroundColor !== 'transparent') {
            roundedRectPath(context, x, y, width, height, radius)
            context.fillStyle = backgroundColor
            context.fill()
        }

        if (element.imageUrl) {
            const img = await loadImage(element.imageUrl)
            if (img) {
                roundedRectPath(context, x, y, width, height, radius)
                context.clip()
                drawFittedImage(context, img, x, y, width, height, element.fit || (element.type === 'logo' ? 'contain' : 'cover'))
            }
        } else {
            roundedRectPath(context, x, y, width, height, radius)
            context.fillStyle = backgroundColor !== 'transparent' ? backgroundColor : '#1e293b'
            context.fill()
            context.fillStyle = '#e2e8f0'
            context.font = '700 16px Arial, Helvetica, sans-serif'
            context.textAlign = 'center'
            context.textBaseline = 'middle'
            context.fillText(element.label || 'Afbeelding', x + (width / 2), y + (height / 2))
        }

        context.restore()
    }

    function drawQrPlaceholder(context, element) {
        const x = Number(element.x || 0)
        const y = Number(element.y || 0)
        const width = Number(element.width || 1)
        const height = Number(element.height || 1)
        const radius = Number(element.borderRadius || 0)
        const code = fields.voucher_code || card.rfid_uid || 'KAART'

        context.save()
        context.globalAlpha = Number(element.opacity ?? 1)
        roundedRectPath(context, x, y, width, height, radius)
        context.fillStyle = '#ffffff'
        context.fill()

        context.strokeStyle = '#0f172a'
        context.lineWidth = 10
        roundedRectPath(context, x + 24, y + 24, Math.max(1, width - 48), Math.max(1, height - 48), 16)
        context.stroke()

        context.fillStyle = '#0f172a'
        context.textAlign = 'center'
        context.textBaseline = 'middle'
        context.font = '700 26px Arial, Helvetica, sans-serif'
        context.fillText('QR', x + (width / 2), y + (height / 2) - 12)
        context.font = '600 15px Arial, Helvetica, sans-serif'
        context.fillText(code, x + (width / 2), y + (height / 2) + 24)
        context.restore()
    }

    async function renderCard() {
        ctx.clearRect(0, 0, canvas.width, canvas.height)

        ctx.save()
        ctx.fillStyle = parseColor(template.backgroundColor, '#ffffff')
        ctx.fillRect(0, 0, canvas.width, canvas.height)
        ctx.restore()

        if (template.backgroundImageUrl) {
            const bg = await loadImage(template.backgroundImageUrl)
            if (bg) {
                drawBackground(ctx, bg, canvas.width, canvas.height, template.backgroundSize || 'cover')
            }
        }

        const elements = [...(template.elements || [])].sort((a, b) => (a.zIndex || 1) - (b.zIndex || 1))

        for (const element of elements) {
            if (element.type === 'shape') {
                drawShapeElement(ctx, element)
            } else if (['image', 'logo', 'photo'].includes(element.type)) {
                await drawMediaElement(ctx, element)
            } else if (element.type === 'qr') {
                drawQrPlaceholder(ctx, element)
            } else {
                drawTextElement(ctx, element)
            }
        }

        const dataUrl = canvas.toDataURL('image/png')
        image.src = dataUrl
        await new Promise((resolve, reject) => {
            image.onload = () => resolve()
            image.onerror = () => reject(new Error('PNG preview kon niet geladen worden.'))
        })
    }

    async function bootstrapPrint() {
        try {
            await renderCard()
            setTimeout(() => window.print(), 160)
        } catch (error) {
            console.error(error)
            errorBox.style.display = 'flex'
        }
    }

    window.addEventListener('load', bootstrapPrint)
</script>
</body>
</html>
