// Client voor de toestel-API (/api/device/v1). Stateless, Bearer-token per toestel.
// Hetzelfde protocol gebruikt Playscope op de LAN-/couch-PC's.

const BASE = '/api/device/v1'
const TOKEN_KEY = 'playdrive.device.token'

export function getToken() {
    try {
        return localStorage.getItem(TOKEN_KEY) || ''
    } catch {
        return ''
    }
}

export function setToken(token) {
    try {
        if (token) localStorage.setItem(TOKEN_KEY, token)
        else localStorage.removeItem(TOKEN_KEY)
    } catch {
        // geen storage → toestel moet na herstart opnieuw koppelen
    }
}

async function request(path, { method = 'GET', body, auth = true } = {}) {
    const headers = { Accept: 'application/json' }
    if (body) headers['Content-Type'] = 'application/json'
    if (auth) headers.Authorization = `Bearer ${getToken()}`

    const response = await fetch(`${BASE}${path}`, {
        method,
        headers,
        body: body ? JSON.stringify(body) : undefined,
        credentials: 'omit',
    })

    let data = null
    try {
        data = await response.json()
    } catch {
        data = null
    }

    if (!response.ok) {
        const error = new Error(data?.message || `HTTP ${response.status}`)
        error.status = response.status
        error.data = data
        throw error
    }

    return data
}

export function startPairing(type = 'kiosk') {
    return request('/pair/start', {
        method: 'POST',
        auth: false,
        body: {
            type,
            platform: (navigator.userAgentData?.platform || navigator.platform || '').slice(0, 40) || null,
            app_version: import.meta.env.VITE_APP_VERSION || null,
        },
    })
}

export function pollPairing(pollSecret) {
    return request('/pair/poll', { method: 'POST', auth: false, body: { poll_secret: pollSecret } })
}

export function fetchMe() {
    return request('/me')
}

export function sendHeartbeat(state = null) {
    return request('/heartbeat', {
        method: 'POST',
        body: { app_version: import.meta.env.VITE_APP_VERSION || null, state },
    })
}
