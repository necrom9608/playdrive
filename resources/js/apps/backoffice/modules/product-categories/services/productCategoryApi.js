import { apiFetch } from '../../../../../shared/services/api'

export function fetchProductCategories() {
    return apiFetch('/api/backoffice/product-categories')
}

export function createProductCategory(payload) {
    return apiFetch('/api/backoffice/product-categories', {
        method: 'POST',
        body: JSON.stringify(payload),
    })
}

export function updateProductCategory(id, payload) {
    return apiFetch(`/api/backoffice/product-categories/${id}`, {
        method: 'PUT',
        body: JSON.stringify(payload),
    })
}

export function deleteProductCategory(id) {
    return apiFetch(`/api/backoffice/product-categories/${id}`, {
        method: 'DELETE',
    })
}
