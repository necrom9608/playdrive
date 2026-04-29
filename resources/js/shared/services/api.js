function getCookie(name) {
    const value = `; ${document.cookie}`
    const parts = value.split(`; ${name}=`)

    if (parts.length === 2) {
        return parts.pop().split(';').shift() || ''
    }

    return ''
}

function getCsrfToken() {
    const xsrfCookie = getCookie('XSRF-TOKEN')

    if (xsrfCookie) {
        return decodeURIComponent(xsrfCookie)
    }

    return document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || ''
}

function updateMetaCsrfToken(token) {
    if (!token) {
        return
    }

    const meta = document.querySelector('meta[name="csrf-token"]')

    if (meta) {
        meta.setAttribute('content', token)
    }
}

export async function apiFetch(url, options = {}) {
    const csrfToken = getCsrfToken()
    const isFormData = options.body instanceof FormData

    const headers = {
        Accept: 'application/json',
        'X-Requested-With': 'XMLHttpRequest',
        ...(csrfToken ? { 'X-XSRF-TOKEN': csrfToken } : {}),
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

    const refreshedToken = getCsrfToken()
    if (refreshedToken) {
        updateMetaCsrfToken(refreshedToken)
    }

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
            window.dispatchEvent(new CustomEvent('admin-auth-required'))
            window.dispatchEvent(new CustomEvent('portal-auth-required'))
        }

        throw error
    }

    return data
}
