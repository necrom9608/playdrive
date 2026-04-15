import { generateUuid } from '../../../shared/utils/identity'
import { getStorageItem, setStorageItem, removeStorageItem } from '../../../shared/utils/storage'
import { getRuntimeSummary, isTauriRuntime } from '../../../shared/runtime/environment'

const DEFAULT_KEYS = {
    uuid: 'playdrive_frontdesk_device_uuid',
    token: 'playdrive_frontdesk_device_token',
    name: 'playdrive_frontdesk_device_name',
}

function resolveKeys(prefix = 'playdrive_frontdesk_device') {
    return {
        uuid: `${prefix}_uuid`,
        token: `${prefix}_token`,
        name: `${prefix}_name`,
    }
}

function getKeys(prefix = 'playdrive_frontdesk_device') {
    return prefix === 'playdrive_frontdesk_device'
        ? DEFAULT_KEYS
        : resolveKeys(prefix)
}

async function getTauriInvoke() {
    if (!isTauriRuntime()) {
        return null
    }

    try {
        const module = await import('@tauri-apps/api/core')
        return module.invoke
    } catch {
        return null
    }
}

export function getDeviceRuntimeSummary() {
    return getRuntimeSummary()
}

export function getOrCreateDeviceUuid(prefix = 'playdrive_frontdesk_device') {
    const keys = getKeys(prefix)

    let value = getStorageItem(keys.uuid)

    if (!value) {
        value = generateUuid()
        setStorageItem(keys.uuid, value)
    }

    return value
}

export function getStoredDeviceToken(prefix = 'playdrive_frontdesk_device') {
    const keys = getKeys(prefix)
    return getStorageItem(keys.token)
}

export function storeDeviceToken(token, prefix = 'playdrive_frontdesk_device') {
    const keys = getKeys(prefix)
    setStorageItem(keys.token, token)
}

export function clearDeviceToken(prefix = 'playdrive_frontdesk_device') {
    const keys = getKeys(prefix)
    removeStorageItem(keys.token)
}

export function getStoredDeviceName(prefix = 'playdrive_frontdesk_device') {
    const keys = getKeys(prefix)
    return getStorageItem(keys.name)
}

export function storeDeviceName(name, prefix = 'playdrive_frontdesk_device') {
    const keys = getKeys(prefix)

    if (!name || !String(name).trim()) {
        removeStorageItem(keys.name)
        return
    }

    setStorageItem(keys.name, String(name).trim())
}

export async function resolveConfiguredDeviceName(prefix = 'playdrive_frontdesk_device', fallback = 'Frontdesk POS') {
    const storedName = getStoredDeviceName(prefix)

    if (storedName) {
        return storedName
    }

    const invoke = await getTauriInvoke()

    if (!invoke) {
        return fallback
    }

    try {
        const config = await invoke('load_desktop_config')
        const configuredName = config?.deviceName || config?.device_name || null

        if (configuredName && String(configuredName).trim()) {
            const normalized = String(configuredName).trim()
            storeDeviceName(normalized, prefix)
            return normalized
        }
    } catch {
        // ignore and fall back below
    }

    return fallback
}
