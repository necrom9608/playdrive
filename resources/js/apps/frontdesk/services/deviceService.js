import { generateUuid } from '../../../shared/utils/identity'
import { getStorageItem, setStorageItem, removeStorageItem } from '../../../shared/utils/storage'
import { getRuntimeSummary } from '../../../shared/runtime/environment'

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


export function getStoredDeviceName(prefix = 'playdrive_frontdesk_device') {
    const keys = prefix === 'playdrive_frontdesk_device'
        ? DEFAULT_KEYS
        : resolveKeys(prefix)

    return getStorageItem(keys.name)
}

export function storeDeviceName(name, prefix = 'playdrive_frontdesk_device') {
    const keys = prefix === 'playdrive_frontdesk_device'
        ? DEFAULT_KEYS
        : resolveKeys(prefix)

    if (name && String(name).trim()) {
        setStorageItem(keys.name, String(name).trim())
    }
}

export async function loadConfiguredDeviceName(prefix = 'playdrive_frontdesk_device') {
    const runtime = getRuntimeSummary()

    if (runtime.environment === 'tauri') {
        try {
            const { invoke } = await import('@tauri-apps/api/core')
            const config = await invoke('load_desktop_config')
            const configuredName = config?.deviceName ?? config?.device_name ?? ''

            if (configuredName && String(configuredName).trim()) {
                const normalizedName = String(configuredName).trim()
                storeDeviceName(normalizedName, prefix)
                return normalizedName
            }
        } catch (error) {
            console.warn('Desktop config laden mislukt, fallback naar lokale toestelnaam.', error)
        }
    }

    return getStoredDeviceName(prefix) || ''
}
