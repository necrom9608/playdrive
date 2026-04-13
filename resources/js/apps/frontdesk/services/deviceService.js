import { generateUuid } from '../../../shared/utils/identity'
import { getStorageItem, setStorageItem, removeStorageItem } from '../../../shared/utils/storage'
import { getRuntimeSummary } from '../../../shared/runtime/environment'

const DEFAULT_KEYS = {
    uuid: 'playdrive_frontdesk_device_uuid',
    token: 'playdrive_frontdesk_device_token',
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
