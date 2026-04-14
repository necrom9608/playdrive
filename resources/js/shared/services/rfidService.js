import { isTauriRuntime } from '../runtime/environment'

async function getTauriInvoke() {
    const module = await import('@tauri-apps/api/core')
    return module.invoke
}

export function isNativeRfidSupported() {
    return isTauriRuntime()
}

export async function scanRfidNative(options = {}) {
    if (!isNativeRfidSupported()) {
        throw new Error('Native RFID scanning is alleen beschikbaar in de lokale app.')
    }

    const invoke = await getTauriInvoke()
    const timeoutMs = Number.isFinite(options.timeoutMs) ? Math.max(0, Number(options.timeoutMs)) : 15000

    const value = await invoke('scan_rfid_once', {
        timeoutMs,
    })

    return String(value ?? '').trim()
}

export async function cancelRfidNativeScan() {
    if (!isNativeRfidSupported()) {
        return false
    }

    const invoke = await getTauriInvoke()
    return invoke('cancel_rfid_scan')
}
