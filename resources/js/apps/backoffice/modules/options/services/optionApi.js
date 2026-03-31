import { apiFetch } from '../../../../../shared/services/api'

export function fetchOptions() {
    return apiFetch('/api/backoffice/catering-options')
}

export function createOption(payload) {
    return apiFetch('/api/backoffice/catering-options', {
        method: 'POST',
        body: JSON.stringify(payload),
    })
}

export function updateOption(id, payload) {
    return apiFetch(`/api/backoffice/catering-options/${id}`, {
        method: 'PUT',
        body: JSON.stringify(payload),
    })
}

export function deleteOption(id) {
    return apiFetch(`/api/backoffice/catering-options/${id}`, {
        method: 'DELETE',
    })
}

export function reorderOptions(items) {
    return apiFetch('/api/backoffice/catering-options/reorder', {
        method: 'POST',
        body: JSON.stringify({ items }),
    })
}
