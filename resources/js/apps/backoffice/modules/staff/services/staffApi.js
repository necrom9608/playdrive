import { apiFetch } from '../../../../../shared/services/api'

export function fetchStaff() {
    return apiFetch('/api/backoffice/staff')
}

export function createStaff(payload) {
    return apiFetch('/api/backoffice/staff', {
        method: 'POST',
        body: JSON.stringify(payload),
    })
}

export function updateStaff(id, payload) {
    return apiFetch(`/api/backoffice/staff/${id}`, {
        method: 'PUT',
        body: JSON.stringify(payload),
    })
}

export function deleteStaff(id) {
    return apiFetch(`/api/backoffice/staff/${id}`, {
        method: 'DELETE',
    })
}

export function reorderStaff(items) {
    return apiFetch('/api/backoffice/staff/reorder', {
        method: 'POST',
        body: JSON.stringify({ items }),
    })
}
