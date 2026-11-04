import { apiFetch } from '../../../../../shared/services/api'

const BASE = '/api/backoffice/display-skin'

export function fetchDisplaySkin() {
    return apiFetch(BASE)
}
export function saveDisplaySkin(payload) {
    return apiFetch(BASE, { method: 'PUT', body: JSON.stringify(payload) })
}
export function updateDisplayVideo(id, payload) {
    return apiFetch(`${BASE}/videos/${id}`, { method: 'PUT', body: JSON.stringify(payload) })
}
export function deleteDisplayVideo(id) {
    return apiFetch(`${BASE}/videos/${id}`, { method: 'DELETE' })
}
export function reorderDisplayVideos(ids) {
    return apiFetch(`${BASE}/videos/reorder`, { method: 'POST', body: JSON.stringify({ ids }) })
}
export function retryDisplayVideo(id) {
    return apiFetch(`${BASE}/videos/${id}/retry`, { method: 'POST' })
}

/**
 * Filmpje uploaden in stukken (< 1 MB per verzoek, zodat nginx/PHP-limieten geen rol spelen).
 * @param {File} file
 * @param {{title?: string, zone?: string|null}} meta
 * @param {(fraction: number) => void} onProgress
 */
export async function uploadDisplayVideo(file, meta, onProgress = () => {}) {
    const created = await apiFetch(`${BASE}/videos`, {
        method: 'POST',
        body: JSON.stringify({ ...meta, original_name: file.name, size: file.size }),
    })
    const video = created.video
    const chunkSize = Number(created.chunk_size) || 768 * 1024

    let offset = 0
    while (offset < file.size) {
        const end = Math.min(file.size, offset + chunkSize)
        const form = new FormData()
        form.append('offset', String(offset))
        form.append('chunk', file.slice(offset, end), 'chunk.bin')

        let attempt = 0
        for (;;) {
            try {
                const res = await apiFetch(`${BASE}/videos/${video.id}/chunk`, { method: 'POST', body: form })
                offset = Number(res.received) || end
                break
            } catch (e) {
                // Netwerkhapering: tot 3 keer opnieuw; de server negeert een dubbel stuk.
                if (++attempt >= 3 || e.status === 409 || e.status === 422 || e.status === 404) throw e
                await new Promise(r => setTimeout(r, 1000 * attempt))
            }
        }
        onProgress(offset / file.size)
    }

    return (await apiFetch(`${BASE}/videos/${video.id}/complete`, { method: 'POST' })).video
}
