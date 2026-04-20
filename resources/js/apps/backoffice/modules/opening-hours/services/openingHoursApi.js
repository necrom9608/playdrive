import { apiFetch } from '../../../../../shared/services/api'

export function fetchOpeningHours() {
    return apiFetch('/api/backoffice/opening-hours')
}

export function saveHours(hours) {
    return apiFetch('/api/backoffice/opening-hours', {
        method: 'POST',
        body: JSON.stringify({ hours }),
    })
}

export function createException(data) {
    return apiFetch('/api/backoffice/opening-hours/exceptions', {
        method: 'POST',
        body: JSON.stringify(data),
    })
}

export function deleteException(id) {
    return apiFetch(`/api/backoffice/opening-hours/exceptions/${id}`, {
        method: 'DELETE',
    })
}
