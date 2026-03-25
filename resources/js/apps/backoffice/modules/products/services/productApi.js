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
