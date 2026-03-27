function getCsrfToken() {
    return document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || ''
}

export async function apiFetch(url, options = {}) {
    const csrfToken = getCsrfToken()

    const response = await fetch(url, {
        credentials: 'same-origin',
        headers: {
            Accept: 'application/json',
            'Content-Type': 'application/json',
            'X-Requested-With': 'XMLHttpRequest',
            ...(csrfToken ? { 'X-CSRF-TOKEN': csrfToken } : {}),
            ...(options.headers || {}),
        },
        ...options,
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
        throw error
    }

    return data
}
