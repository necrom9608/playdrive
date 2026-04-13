import { isTauriRuntime } from '../../../shared/runtime/environment'

export function getPrinterProvider() {
    if (isTauriRuntime()) {
        return 'tauri'
    }

    return 'browser'
}

export function isPrinterSupported() {
    return true
}

export async function printUrl(url, target = '_blank') {
    if (typeof window === 'undefined') {
        throw new Error('Printing is only available in a browser-like runtime.')
    }

    const popup = window.open(url, target, 'noopener,noreferrer')

    if (!popup) {
        throw new Error('The print window could not be opened.')
    }

    return true
}
