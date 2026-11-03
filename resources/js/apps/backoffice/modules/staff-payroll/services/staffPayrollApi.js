import { apiFetch } from '../../../../../shared/services/api'

export function fetchStaffPayroll({ month, staffId } = {}) {
    const search = new URLSearchParams()
    if (month) search.set('month', month)
    if (staffId) search.set('staff_id', staffId)
    const query = search.toString()

    return apiFetch(`/api/backoffice/staff-payroll${query ? `?${query}` : ''}`)
}

export function setStaffAbsence(payload) {
    return apiFetch('/api/backoffice/staff-payroll/absence', {
        method: 'PUT',
        body: JSON.stringify(payload),
    })
}

export function createStaffCheckin(payload) {
    return apiFetch('/api/backoffice/staff-payroll/checkins', {
        method: 'POST',
        body: JSON.stringify(payload),
    })
}

export function submitStaffPayroll(payload) {
    return apiFetch('/api/backoffice/staff-payroll/submit', {
        method: 'POST',
        body: JSON.stringify(payload),
    })
}
