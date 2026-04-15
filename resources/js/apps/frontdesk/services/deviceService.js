import { generateUuid } from '../../../shared/utils/identity'
import { getStorageItem, setStorageItem, removeStorageItem } from '../../../shared/utils/storage'
import { getRuntimeSummary, isTauriRuntime } from '../../../shared/runtime/environment'

const DEFAULT_KEYS = {
    uuid: 'playdrive_frontdesk_device_uuid',
    token: 'playdrive_frontdesk_device_token',
}

const NAME_STORAGE_KEY = 'playdrive_frontdesk_device_name'

async function getTauriInvoke() {
    const module = await import('@tauri-apps/api/core')
    return module.invoke
}

export function getStoredDeviceName() {
    return (getStorageItem(NAME_STORAGE_KEY) || '').trim()
}

export function storeDeviceName(name) {
    const normalized = String(name ?? '').trim()

    if (!normalized) {
        removeStorageItem(NAME_STORAGE_KEY)
        return ''
    }

    setStorageItem(NAME_STORAGE_KEY, normalized)
    return normalized
}

export async function loadConfiguredDeviceName() {
    if (isTauriRuntime()) {
        try {
            const invoke = await getTauriInvoke()
            const config = await invoke('load_desktop_config')
            const tauriName = String(config?.deviceName ?? config?.device_name ?? '').trim()

            if (tauriName) {
                storeDeviceName(tauriName)
                return tauriName
            }
        } catch (error) {
            console.warn('Desktopconfig laden voor toestelnaam mislukt.', error)
        }
    }

    return getStoredDeviceName()
}

function resolveKeys(prefix = 'playdrive_frontdesk_device') {
    return {
        uuid: `${prefix}_uuid`,
        token: `${prefix}_token`,
    }
}

export function getDeviceRuntimeSummary() {
    return getRuntimeSummary()
}

export function getOrCreateDeviceUuid(prefix = 'playdrive_frontdesk_device') {
    const keys = prefix === 'playdrive_frontdesk_device'
        ? DEFAULT_KEYS
        : resolveKeys(prefix)

    let value = getStorageItem(keys.uuid)

    if (!value) {
        value = generateUuid()
        setStorageItem(keys.uuid, value)
    }

    return value
}

export function getStoredDeviceToken(prefix = 'playdrive_frontdesk_device') {
    const keys = prefix === 'playdrive_frontdesk_device'
        ? DEFAULT_KEYS
        : resolveKeys(prefix)

    return getStorageItem(keys.token)
}

export function storeDeviceToken(token, prefix = 'playdrive_frontdesk_device') {
    const keys = prefix === 'playdrive_frontdesk_device'
        ? DEFAULT_KEYS
        : resolveKeys(prefix)

    setStorageItem(keys.token, token)
}

export function clearDeviceToken(prefix = 'playdrive_frontdesk_device') {
    const keys = prefix === 'playdrive_frontdesk_device'
        ? DEFAULT_KEYS
        : resolveKeys(prefix)

    removeStorageItem(keys.token)
}
