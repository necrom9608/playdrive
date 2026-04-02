import { apiFetch } from '../../../../../shared/services/api'

export function fetchOptions(type = 'catering-options') {
    return apiFetch(`/api/backoffice/options/${type}`)
}

export function createOption(type = 'catering-options', payload = {}) {
    return apiFetch(`/api/backoffice/options/${type}`, {
        method: 'POST',
        body: JSON.stringify(payload),
    })
}

export function updateOption(type = 'catering-options', id, payload = {}) {
    return apiFetch(`/api/backoffice/options/${type}/${id}`, {
        method: 'PUT',
        body: JSON.stringify(payload),
    })
}

export function deleteOption(type = 'catering-options', id) {
    return apiFetch(`/api/backoffice/options/${type}/${id}`, {
        method: 'DELETE',
    })
}

export function reorderOptions(type = 'catering-options', items = []) {
    return apiFetch(`/api/backoffice/options/${type}/reorder`, {
        method: 'POST',
        body: JSON.stringify({ items }),
    })
}
