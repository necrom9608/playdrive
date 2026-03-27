import { apiFetch } from '../../../../../shared/services/api'

export function fetchOptions(type) {
    return apiFetch(`/api/backoffice/options/${type}`)
}

export function createOption(type, payload) {
    return apiFetch(`/api/backoffice/options/${type}`, {
        method: 'POST',
        body: JSON.stringify(payload),
    })
}

export function updateOption(type, id, payload) {
    return apiFetch(`/api/backoffice/options/${type}/${id}`, {
        method: 'PUT',
        body: JSON.stringify(payload),
    })
}

export function deleteOption(type, id) {
    return apiFetch(`/api/backoffice/options/${type}/${id}`, {
        method: 'DELETE',
    })
}

export function reorderOptions(type, items) {
    return apiFetch(`/api/backoffice/options/${type}/reorder`, {
        method: 'POST',
        body: JSON.stringify({ items }),
    })
}
