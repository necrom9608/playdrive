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

        .print-image {
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
<body>
<div class="preview-stack">
    <div class="preview-meta">
        <span>Kaart: {{ $card->rfid_uid }}</span>
        <span>Type: {{ $card->card_type }}</span>
        <span>PNG direct print</span>
    </div>

    <div class="print-sheet">
        <img id="printImage" class="print-image" alt="Kaart preview" src="{{ $previewImageUrl }}" />
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
