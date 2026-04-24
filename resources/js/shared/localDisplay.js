/**
 * localDisplay.js
 *
 * Bridge voor directe communicatie tussen het Frontdesk- en Display-venster
 * in de Tauri desktop-app.
 *
 * Strategie:
 *   1. BroadcastChannel (werkt als beide vensters hetzelfde process delen)
 *   2. localStorage events (fallback — werkt cross-process binnen dezelfde origin)
 *
 * Beide methodes worden parallel gebruikt zodat het werkt in zowel browser
 * als Tauri's WebView2 (waar processen mogelijk gescheiden zijn).
 */

const CHANNEL_NAME = 'playdrive-display'
const STORAGE_KEY = 'playdrive-display-channel'

let _channel = null

function getChannel() {
    if (!_channel && typeof BroadcastChannel !== 'undefined') {
        try {
            _channel = new BroadcastChannel(CHANNEL_NAME)
        } catch {
            // ignore
        }
    }
    return _channel
}

/**
 * Is lokale display-modus actief?
 * Detectie via:
 *   1. URL hash fragment #local (gezet door Tauri bij het openen van het venster)
 *   2. window.PLAYDRIVE_LOCAL_DISPLAY === true (manuele override)
 *   3. window.__TAURI_INTERNALS__ (Tauri runtime detectie)
 */
export function isLocalDisplayMode() {
    if (typeof window === 'undefined') return false

    try {
        const hash = String(window.location.hash || '').toLowerCase()
        if (hash.includes('local')) return true
    } catch {
        // ignore
    }

    if (window.PLAYDRIVE_LOCAL_DISPLAY === true) return true
    return typeof window.__TAURI_INTERNALS__ !== 'undefined'
}

/**
 * Stuur een display-state update naar het Display-venster.
 */
export function localDisplaySend(payload) {
    const message = {
        type: 'display-state',
        timestamp: Date.now(),
        ...payload,
    }

    const channel = getChannel()
    if (channel) {
        try {
            channel.postMessage(message)
        } catch {
            // ignore
        }
    }

    try {
        localStorage.setItem(STORAGE_KEY, JSON.stringify(message))
    } catch {
        // ignore
    }
}

/**
 * Luister naar display-state updates.
 */
export function localDisplayListen(handler) {
    const cleanups = []

    const channel = getChannel()
    if (channel) {
        const channelListener = (event) => {
            if (event.data?.type === 'display-state') {
                handler(event.data)
            }
        }
        channel.addEventListener('message', channelListener)
        cleanups.push(() => channel.removeEventListener('message', channelListener))
    }

    const storageListener = (event) => {
        if (event.key !== STORAGE_KEY || !event.newValue) return
        try {
            const message = JSON.parse(event.newValue)
            if (message?.type === 'display-state') {
                handler(message)
            }
        } catch {
            // ignore
        }
    }

    if (typeof window !== 'undefined') {
        window.addEventListener('storage', storageListener)
        cleanups.push(() => window.removeEventListener('storage', storageListener))
    }

    return () => {
        cleanups.forEach((fn) => fn())
    }
}

export function localDisplayClose() {
    if (_channel) {
        try { _channel.close() } catch {}
        _channel = null
    }
}
