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

function proxyImageUrl(src) {
    if (!src) return src
    // Alleen externe URLs proxyen (andere origin dan huidige pagina)
    try {
        const url = new URL(src)
        if (url.origin !== window.location.origin) {
            return `/api/backoffice/image-proxy?url=${encodeURIComponent(src)}`
        }
    } catch (_) {
        // relatieve URL of ongeldige URL – ongewijzigd laten
    }
    return src
}

function loadImage(src) {
    return new Promise((resolve, reject) => {
        if (!src) {
            resolve(null)
            return
        }

        const img = new Image()
        // Laad via proxy zodat canvas niet tainted raakt door CORS
        img.src = proxyImageUrl(src)
        img.onload = () => resolve(img)
        img.onerror = () => reject(new Error(`Afbeelding kon niet geladen worden: ${src}`))
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
        const displayText = element.displayText
        if (displayText !== undefined && displayText !== null && String(displayText).trim() !== '') {
            return displayText
        }

        return `[[ ${element.source || 'veld'} ]]`
    }

    if (element.type === 'text') {
        return element.displayText || element.text || 'Tekst'
    }

    return element.displayText || element.label || ''
}

function escapeXml(value) {
    return String(value ?? '')
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#39;')
}

function buildHtmlLayerMarkup({ template, fields, card }) {
    const elements = [...(template.elements || [])].sort((a, b) => (a.zIndex || 1) - (b.zIndex || 1))

    const html = elements.map((element) => {
        const x = Number(element.x || 0)
        const y = Number(element.y || 0)
        const width = Number(element.width || 1)
        const height = Number(element.height || 1)
        const radius = Number(element.borderRadius || 0)
        const opacity = Number(element.opacity ?? 1)
        const zIndex = Number(element.zIndex || 1)
        const background = parseColor(element.backgroundColor)

        const wrapperStyle = [
            'position:absolute',
            `left:${x}px`,
            `top:${y}px`,
            `width:${width}px`,
            `height:${height}px`,
            `z-index:${zIndex}`,
            'overflow:hidden',
            `border-radius:${radius}px`,
            `opacity:${opacity}`,
            'box-sizing:border-box',
        ].join(';')

        if (element.type === 'shape') {
            return `<div style="${wrapperStyle};background:${escapeXml(background !== 'transparent' ? background : '#7c3aed')};"></div>`
        }

        if (['image', 'logo', 'photo'].includes(element.type)) {
            const fit = element.fit || (element.type === 'logo' ? 'contain' : 'cover')
            const imgTag = element.imageUrl
                ? `<img src="${escapeXml(element.imageUrl)}" style="width:100%;height:100%;object-fit:${escapeXml(fit)};display:block;" />`
                : `<div style="display:flex;align-items:center;justify-content:center;width:100%;height:100%;background:${escapeXml(background !== 'transparent' ? background : '#1e293b')};color:#e2e8f0;font:700 16px Arial, Helvetica, sans-serif;text-transform:uppercase;letter-spacing:.12em;">${escapeXml(element.label || 'Afbeelding')}</div>`

            const bgStyle = background !== 'transparent' ? `background:${escapeXml(background)};` : ''
            return `<div style="${wrapperStyle};${bgStyle}">${imgTag}</div>`
        }

        if (element.type === 'qr') {
            const code = fields?.voucher_code || card?.rfid_uid || 'KAART'
            return `
                <div style="${wrapperStyle};background:#ffffff;border-radius:${radius}px;display:flex;align-items:center;justify-content:center;">
                    <div style="width:72%;height:72%;border:10px solid #0f172a;border-radius:16px;display:flex;flex-direction:column;align-items:center;justify-content:center;box-sizing:border-box;color:#0f172a;font-family:Arial, Helvetica, sans-serif;">
                        <div style="font-size:26px;font-weight:700;line-height:1;">QR</div>
                        <div style="margin-top:8px;font-size:15px;font-weight:600;line-height:1;">${escapeXml(code)}</div>
                    </div>
                </div>
            `
        }

        const textAlign = element.textAlign || 'left'
        const justifyContent = textAlign === 'center' ? 'center' : (textAlign === 'right' ? 'flex-end' : 'flex-start')
        const fontSize = Number(element.fontSize || 32)
        const fontWeight = Number(element.fontWeight || 700)
        const color = parseColor(element.color, '#ffffff')
        const text = escapeXml(getDisplayText(element)).replace(/\r\n/g, '\n').replace(/\r/g, '\n').replace(/\n/g, '<br/>')
        const bgStyle = background !== 'transparent' ? `background:${escapeXml(background)};` : ''

        return `
            <div style="${wrapperStyle};${bgStyle}display:flex;align-items:center;justify-content:${justifyContent};padding:8px 12px;box-sizing:border-box;color:${escapeXml(color)};font-size:${fontSize}px;font-weight:${fontWeight};font-family:Arial, Helvetica, sans-serif;text-align:${textAlign};line-height:1.1;white-space:pre-wrap;word-break:break-word;">
                <div style="width:100%;text-align:${textAlign};">${text}</div>
            </div>
        `
    }).join('')

    return `
        <div xmlns="http://www.w3.org/1999/xhtml" style="position:relative;width:${Number(template.width || 1012)}px;height:${Number(template.height || 638)}px;box-sizing:border-box;overflow:hidden;">
            ${html}
        </div>
    `
}

async function drawHtmlLayer(context, { template, fields, card }) {
    const width = Number(template.width || 1012)
    const height = Number(template.height || 638)
    const html = buildHtmlLayerMarkup({ template, fields, card })
    const svg = `
        <svg xmlns="http://www.w3.org/2000/svg" width="${width}" height="${height}" viewBox="0 0 ${width} ${height}">
            <foreignObject x="0" y="0" width="100%" height="100%">${html}</foreignObject>
        </svg>
    `
    const dataUrl = `data:image/svg+xml;charset=utf-8,${encodeURIComponent(svg)}`
    const img = await loadImage(dataUrl)
    if (img) {
        context.drawImage(img, 0, 0, width, height)
        return true
    }
    return false
}

function drawTextElementFallback(context, element) {
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
    const lineHeight = Math.round(fontSize * 1.1)
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

export async function renderCardToCanvas({ template, fields, card }) {
    if (!template) {
        throw new Error('Geen render-template beschikbaar voor deze kaart.')
    }

    const canvas = document.createElement('canvas')
    canvas.width = Number(template.width || 1012)
    canvas.height = Number(template.height || 638)

    const ctx = canvas.getContext('2d')
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

    try {
        const drawn = await drawHtmlLayer(ctx, { template, fields, card })
        if (drawn) {
            return canvas
        }
    } catch (error) {
        console.warn('HTML kaartlaag kon niet gerenderd worden, fallback wordt gebruikt.', error)
    }

    const elements = [...(template.elements || [])].sort((a, b) => (a.zIndex || 1) - (b.zIndex || 1))

    for (const element of elements) {
        if (element.type === 'shape') {
            const x = Number(element.x || 0)
            const y = Number(element.y || 0)
            const width = Number(element.width || 1)
            const height = Number(element.height || 1)
            const radius = Number(element.borderRadius || 0)
            ctx.save()
            ctx.globalAlpha = Number(element.opacity ?? 1)
            roundedRectPath(ctx, x, y, width, height, radius)
            ctx.fillStyle = parseColor(element.backgroundColor, '#7c3aed')
            ctx.fill()
            ctx.restore()
        } else if (['image', 'logo', 'photo'].includes(element.type)) {
            const x = Number(element.x || 0)
            const y = Number(element.y || 0)
            const width = Number(element.width || 1)
            const height = Number(element.height || 1)
            const radius = Number(element.borderRadius || 0)
            const backgroundColor = parseColor(element.backgroundColor)
            ctx.save()
            ctx.globalAlpha = Number(element.opacity ?? 1)
            if (backgroundColor !== 'transparent') {
                roundedRectPath(ctx, x, y, width, height, radius)
                ctx.fillStyle = backgroundColor
                ctx.fill()
            }
            if (element.imageUrl) {
                const img = await loadImage(element.imageUrl)
                if (img) {
                    roundedRectPath(ctx, x, y, width, height, radius)
                    ctx.clip()
                    drawFittedImage(ctx, img, x, y, width, height, element.fit || (element.type === 'logo' ? 'contain' : 'cover'))
                }
            }
            ctx.restore()
        } else if (element.type === 'qr') {
            // minimal fallback
            const x = Number(element.x || 0)
            const y = Number(element.y || 0)
            const width = Number(element.width || 1)
            const height = Number(element.height || 1)
            const radius = Number(element.borderRadius || 0)
            ctx.save()
            roundedRectPath(ctx, x, y, width, height, radius)
            ctx.fillStyle = '#ffffff'
            ctx.fill()
            ctx.fillStyle = '#0f172a'
            ctx.font = '700 26px Arial, Helvetica, sans-serif'
            ctx.textAlign = 'center'
            ctx.textBaseline = 'middle'
            ctx.fillText('QR', x + (width / 2), y + (height / 2))
            ctx.restore()
        } else {
            drawTextElementFallback(ctx, element)
        }
    }

    return canvas
}

export async function renderCardToDataUrl(payload) {
    const canvas = await renderCardToCanvas(payload)
    return canvas.toDataURL('image/png')
}
