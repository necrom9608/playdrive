function hasWindow() {
    return typeof window !== 'undefined'
}

export function isTauriRuntime() {
    if (!hasWindow()) {
        return false
    }

    return Boolean(window.__TAURI__ || window.__TAURI_INTERNALS__)
}

export function getRuntimeEnvironment() {
    return isTauriRuntime() ? 'tauri' : 'web'
}

export function getRuntimeCapabilities() {
    return {
        tauri: isTauriRuntime(),
        localStorage: hasWindow() && Boolean(window.localStorage),
        serviceWorker: hasWindow() && 'serviceWorker' in navigator,
    }
}

export function getRuntimeSummary() {
    return {
        environment: getRuntimeEnvironment(),
        capabilities: getRuntimeCapabilities(),
    }
}
