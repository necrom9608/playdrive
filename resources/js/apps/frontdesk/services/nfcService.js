import { isTauriRuntime } from '../../../shared/runtime/environment'

export function getNfcProvider() {
    if (isTauriRuntime()) {
        return 'tauri'
    }

    return 'browser'
}

export function isNfcSupported() {
    return false
}

export async function scanNfcCard() {
    throw new Error('NFC scanning is not configured yet for this runtime.')
}
