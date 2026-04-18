import { Preferences } from '@capacitor/preferences'

const BASE_URL = import.meta.env.VITE_MEMBER_API_URL ?? '/member-api/v1'

// Storage abstraction — Capacitor Preferences op native, localStorage als fallback in browser
export const storage = {
    async get(key) {
        try {
            const { value } = await Preferences.get({ key })
            return value
        } catch {
            return localStorage.getItem(key)
        }
    },
    async set(key, value) {
        try {
            await Preferences.set({ key, value })
        } catch {
            localStorage.setItem(key, value)
        }
    },
    async remove(key) {
        try {
            await Preferences.remove({ key })
        } catch {
            localStorage.removeItem(key)
        }
    },
}

async function getToken() {
    return await storage.get('member_token')
}

export async function memberApi(path, options = {}) {
    const token = await getToken()

    const headers = {
        Accept: 'application/json',
        'Content-Type': 'application/json',
        ...(token ? { Authorization: `Bearer ${token}` } : {}),
        ...(options.headers ?? {}),
    }

    const response = await fetch(`${BASE_URL}${path}`, {
        ...options,
        headers,
        body: options.body ? JSON.stringify(options.body) : undefined,
    })

    const data = response.headers.get('content-type')?.includes('application/json')
        ? await response.json()
        : {}

    if (!response.ok) {
        const error = new Error(data.message ?? 'Er is een fout opgetreden.')
        error.status = response.status
        error.data = data
        throw error
    }

    return data
}

export const api = {
    get: (path, opts) => memberApi(path, { method: 'GET', ...opts }),
    post: (path, body, opts) => memberApi(path, { method: 'POST', body, ...opts }),
    put: (path, body, opts) => memberApi(path, { method: 'PUT', body, ...opts }),
    delete: (path, opts) => memberApi(path, { method: 'DELETE', ...opts }),
}
