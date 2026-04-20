import { apiFetch } from '../../../../../shared/services/api'

export function fetchTenants() {
    return apiFetch('/admin/api/tenants')
}

export function createTenant(data) {
    return apiFetch('/admin/api/tenants', {
        method: 'POST',
        body: JSON.stringify(data),
    })
}

export function updateTenant(id, data) {
    return apiFetch(`/admin/api/tenants/${id}`, {
        method: 'PUT',
        body: JSON.stringify(data),
    })
}

export function deleteTenant(id) {
    return apiFetch(`/admin/api/tenants/${id}`, {
        method: 'DELETE',
    })
}
