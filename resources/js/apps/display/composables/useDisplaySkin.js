import axios from 'axios'
import { onBeforeUnmount, onMounted, ref } from 'vue'

/**
 * Skin van het klantenscherm (Backoffice → Klantenscherm): skin, teksten, filmpjes, openingsuren.
 * Wordt elke 5 minuten ververst; de laatste versie wordt lokaal bewaard zodat het scherm na een
 * herstart meteen in de juiste skin opkomt, ook als de server even niet antwoordt.
 */
const REFRESH_MS = 5 * 60 * 1000
const CACHE_KEY = 'playdrive-display-skin'

function readCache() {
    try {
        const raw = window.localStorage.getItem(CACHE_KEY)
        return raw ? JSON.parse(raw) : null
    } catch {
        return null
    }
}

function writeCache(value) {
    try {
        window.localStorage.setItem(CACHE_KEY, JSON.stringify(value))
    } catch {
        // niet kritisch
    }
}

export function useDisplaySkin() {
    const skin = ref(readCache())
    let timer = null

    async function refresh() {
        try {
            const { data } = await axios.get('/api/display/skin')
            if (data && typeof data === 'object' && data.skin) {
                skin.value = data
                writeCache(data)
            }
        } catch {
            // Geen tenant (niet ingelogd) of server weg: vorige skin houden.
        }
    }

    onMounted(() => {
        refresh()
        timer = window.setInterval(refresh, REFRESH_MS)
        window.addEventListener('online', refresh)
    })

    onBeforeUnmount(() => {
        window.clearInterval(timer)
        window.removeEventListener('online', refresh)
    })

    return { skin, refresh }
}
