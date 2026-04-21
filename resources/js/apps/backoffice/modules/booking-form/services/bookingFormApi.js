import { apiFetch } from '../../../../../shared/services/api'

export function fetchBookingFormConfig() {
    return apiFetch('/api/backoffice/booking-form-config')
}

export function saveBookingFormConfig(payload = {}) {
    return apiFetch('/api/backoffice/booking-form-config', {
        method: 'POST',
        body: JSON.stringify(payload),
    })
}
