import { apiFetch } from '../../../../../shared/services/api'

export function fetchDevices() {
    return apiFetch('/api/backoffice/devices')
}

export function pairDevice(payload) {
    return apiFetch('/api/backoffice/devices/pair', {
        method: 'POST',
        body: JSON.stringify(payload),
    })
}

export function unpairPosDevice(id) {
    return apiFetch(`/api/backoffice/devices/pos/${id}/unpair`, {
        method: 'POST',
    })
}

export function updateDisplayDevice(id, payload) {
    return apiFetch(`/api/backoffice/devices/displays/${id}`, {
        method: 'PUT',
        body: JSON.stringify(payload),
    })
}

export function updatePosDevice(id, payload) {
    return apiFetch(`/api/backoffice/devices/pos/${id}`, {
        method: 'PUT',
        body: JSON.stringify(payload),
    })
}

export function deleteDisplayDevice(id) {
    return apiFetch(`/api/backoffice/devices/display/${id}`, {
        method: 'DELETE',
    })
}

export function deletePosDevice(id) {
    return apiFetch(`/api/backoffice/devices/pos/${id}`, {
        method: 'DELETE',
    })
}
