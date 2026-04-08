import { apiFetch } from '../../../../../shared/services/api'

function toFormData(payload = {}) {
    const formData = new FormData()

    Object.entries(payload).forEach(([key, value]) => {
        if (value === undefined || value === null) {
            return
        }

        if (key === 'logo' && value instanceof File) {
            formData.append('logo', value)
            return
        }

        formData.append(key, typeof value === 'boolean' ? (value ? '1' : '0') : String(value))
    })

    return formData
}

export function fetchTenantSettings() {
    return apiFetch('/api/backoffice/tenant-settings')
}

export function updateTenantSettings(payload) {
    return apiFetch('/api/backoffice/tenant-settings', {
        method: 'POST',
        body: toFormData(payload),
    })
}
