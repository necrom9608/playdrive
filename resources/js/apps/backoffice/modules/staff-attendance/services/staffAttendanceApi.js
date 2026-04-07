import { apiFetch } from '../../../../../shared/services/api'

export function fetchStaffAttendance(params = {}) {
    const search = new URLSearchParams()

    Object.entries(params).forEach(([key, value]) => {
        if (value === null || value === undefined || value === '') {
            return
        }

        search.set(key, value)
    })

    const query = search.toString()

    return apiFetch(`/api/backoffice/staff-attendance${query ? `?${query}` : ''}`)
}

export function updateStaffAttendance(id, payload) {
    return apiFetch(`/api/backoffice/staff-attendance/${id}`, {
        method: 'PUT',
        body: JSON.stringify(payload),
    })
}

export function deleteStaffAttendance(id) {
    return apiFetch(`/api/backoffice/staff-attendance/${id}`, {
        method: 'DELETE',
    })
}
