import { apiFetch } from '../../../../../shared/services/api'

export function fetchProducts() {
    return apiFetch('/api/backoffice/products')
}

export function createProduct(payload) {
    return apiFetch('/api/backoffice/products', {
        method: 'POST',
        body: JSON.stringify(payload),
    })
}

export function updateProduct(id, payload) {
    return apiFetch(`/api/backoffice/products/${id}`, {
        method: 'PUT',
        body: JSON.stringify(payload),
    })
}

export function deleteProduct(id) {
    return apiFetch(`/api/backoffice/products/${id}`, {
        method: 'DELETE',
    })
}

export function reorderProducts(items) {
    return apiFetch('/api/backoffice/products/reorder', {
        method: 'POST',
        body: JSON.stringify({ items }),
    })
}
