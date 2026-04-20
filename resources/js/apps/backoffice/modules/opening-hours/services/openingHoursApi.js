import { apiFetch } from '../../../../../shared/services/api'

export function fetchOpeningHours() {
    return apiFetch('/backoffice/api/opening-hours')
}

export function saveHours(hours) {
    return apiFetch('/backoffice/api/opening-hours', {
        method: 'POST',
        body: JSON.stringify({ hours }),
    })
}

export function createException(data) {
    return apiFetch('/backoffice/api/opening-hours/exceptions', {
        method: 'POST',
        body: JSON.stringify(data),
    })
}

export function deleteException(id) {
    return apiFetch(`/backoffice/api/opening-hours/exceptions/${id}`, {
        method: 'DELETE',
    })
}
