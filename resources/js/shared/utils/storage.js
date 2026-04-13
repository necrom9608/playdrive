export function getStorageItem(key, fallback = null) {
    if (typeof window === 'undefined' || !window.localStorage) {
        return fallback
    }

    const value = window.localStorage.getItem(key)

    return value ?? fallback
}

export function setStorageItem(key, value) {
    if (typeof window === 'undefined' || !window.localStorage) {
        return
    }

    if (value === null || typeof value === 'undefined') {
        window.localStorage.removeItem(key)
        return
    }

    window.localStorage.setItem(key, String(value))
}

export function removeStorageItem(key) {
    if (typeof window === 'undefined' || !window.localStorage) {
        return
    }

    window.localStorage.removeItem(key)
}
