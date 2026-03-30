import { apiFetch } from '../../../../../shared/services/api'

function toProductFormData(payload, method = null) {
    const formData = new FormData()

    if (method) {
        formData.append('_method', method)
    }

    Object.entries(payload).forEach(([key, value]) => {
        if (value === undefined || value === null || value === '') {
            return
        }

        if (key === 'image' && value instanceof File) {
            formData.append('image', value)
            return
        }

        formData.append(key, value)
    })

    if (payload.product_category_id === null || payload.product_category_id === '') {
        formData.append('product_category_id', '')
    }

    formData.append('is_active', payload.is_active ? '1' : '0')

    return formData
}

export function fetchProducts() {
    return apiFetch('/api/backoffice/products')
}

export function createProduct(payload) {
    return apiFetch('/api/backoffice/products', {
        method: 'POST',
        body: toProductFormData(payload),
    })
}

export function updateProduct(id, payload) {
    return apiFetch(`/api/backoffice/products/${id}`, {
        method: 'POST',
        body: toProductFormData(payload, 'PUT'),
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
