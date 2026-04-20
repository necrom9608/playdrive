import { apiFetch } from '../../../../../shared/services/api'

export function fetchAdminStaff() {
    return apiFetch('/admin/api/staff')
}

export function createAdminStaff(data) {
    return apiFetch('/admin/api/staff', {
        method: 'POST',
        body: JSON.stringify(data),
    })
}

export function updateAdminStaff(id, data) {
    return apiFetch(`/admin/api/staff/${id}`, {
        method: 'PUT',
        body: JSON.stringify(data),
    })
}

export function deleteAdminStaff(id) {
    return apiFetch(`/admin/api/staff/${id}`, {
        method: 'DELETE',
    })
}
