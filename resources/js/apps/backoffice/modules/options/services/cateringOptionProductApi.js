import { apiFetch } from '../../../../../shared/services/api'

export function fetchCateringOptionProducts(cateringOptionId) {
    return apiFetch(`/api/backoffice/catering-options/${cateringOptionId}/products`)
}

export function createCateringOptionProduct(cateringOptionId, payload) {
    return apiFetch(`/api/backoffice/catering-options/${cateringOptionId}/products`, {
        method: 'POST',
        body: JSON.stringify(payload),
    })
}

export function updateCateringOptionProduct(id, payload) {
    return apiFetch(`/api/backoffice/catering-option-products/${id}`, {
        method: 'PUT',
        body: JSON.stringify(payload),
    })
}

export function deleteCateringOptionProduct(id) {
    return apiFetch(`/api/backoffice/catering-option-products/${id}`, {
        method: 'DELETE',
    })
}

export function reorderCateringOptionProducts(cateringOptionId, items) {
    return apiFetch(`/api/backoffice/catering-options/${cateringOptionId}/products/reorder`, {
        method: 'POST',
        body: JSON.stringify({ items }),
    })
}
