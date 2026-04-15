const UUID_KEY = 'playdrive_display_device_uuid'
const TOKEN_KEY = 'playdrive_display_device_token'
const NAME_KEY = 'playdrive_display_name'

function generateUuid() {
    if (typeof window !== 'undefined' && window.crypto && typeof window.crypto.randomUUID === 'function') {
        return window.crypto.randomUUID()
    }

    if (typeof window !== 'undefined' && window.crypto && typeof window.crypto.getRandomValues === 'function') {
        const bytes = new Uint8Array(16)
        window.crypto.getRandomValues(bytes)
        bytes[6] = (bytes[6] & 0x0f) | 0x40
        bytes[8] = (bytes[8] & 0x3f) | 0x80
        const hex = Array.from(bytes, (byte) => byte.toString(16).padStart(2, '0'))

        return [
            hex.slice(0, 4).join(''),
            hex.slice(4, 6).join(''),
            hex.slice(6, 8).join(''),
            hex.slice(8, 10).join(''),
            hex.slice(10, 16).join(''),
        ].join('-')
    }

    return 'xxxxxxxx-xxxx-4xxx-yxxx-xxxxxxxxxxxx'.replace(/[xy]/g, (char) => {
        const random = Math.floor(Math.random() * 16)
        const value = char === 'x' ? random : ((random & 0x3) | 0x8)
        return value.toString(16)
    })
}

export function getOrCreateDisplayUuid() {
    let value = localStorage.getItem(UUID_KEY)

    if (!value) {
        value = generateUuid()
        localStorage.setItem(UUID_KEY, value)
    }

    return value
}

export function getDisplayToken() {
    return localStorage.getItem(TOKEN_KEY)
}

export function storeDisplayToken(token) {
    localStorage.setItem(TOKEN_KEY, token)
}

export function getDisplayName() {
    return (localStorage.getItem(NAME_KEY) || '').trim()
}

export function storeDisplayName(name) {
    const normalizedName = String(name || '').trim()

    if (!normalizedName) {
        localStorage.removeItem(NAME_KEY)
        return ''
    }

    localStorage.setItem(NAME_KEY, normalizedName)
    return normalizedName
}
