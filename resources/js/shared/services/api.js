function getCsrfToken() {
    return document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || ''
}

export async function apiFetch(url, options = {}) {
    const csrfToken = getCsrfToken()
    const isFormData = options.body instanceof FormData

    const headers = {
        Accept: 'application/json',
        'X-Requested-With': 'XMLHttpRequest',
        ...(csrfToken ? { 'X-CSRF-TOKEN': csrfToken } : {}),
        ...(options.headers || {}),
    }

    if (!isFormData) {
        headers['Content-Type'] = headers['Content-Type'] || 'application/json'
    }

    const response = await fetch(url, {
        credentials: 'same-origin',
        ...options,
        headers,
    })

    const contentType = response.headers.get('content-type') || ''

    let data = null

    if (contentType.includes('application/json')) {
        data = await response.json()
    } else {
        const text = await response.text()
        data = text ? { message: text } : null
    }

    if (!response.ok) {
        const error = new Error(data?.message || `API request failed with status ${response.status}`)
        error.status = response.status
        error.data = data

        if (response.status === 401) {
            window.dispatchEvent(new CustomEvent('frontdesk-auth-required'))
            window.dispatchEvent(new CustomEvent('backoffice-auth-required'))
            window.dispatchEvent(new CustomEvent('staff-auth-required'))
        }

        throw error
    }

    return data
}
