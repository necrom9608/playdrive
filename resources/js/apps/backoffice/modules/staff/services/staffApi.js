export async function apiFetch(url, options = {}) {
    const response = await fetch(url, {
        headers: {
            Accept: 'application/json',
            'Content-Type': 'application/json',
            ...(options.headers || {}),
        },
        credentials: 'same-origin',
        ...options,
    })

    const contentType = response.headers.get('content-type') || ''
    let data = null

    try {
        if (contentType.includes('application/json')) {
            data = await response.json()
        } else {
            const text = await response.text()
            data = text ? { message: text } : null
        }
    } catch (parseError) {
        data = { message: 'Kon serverantwoord niet lezen.' }
    }

    if (!response.ok) {
        const error = new Error(data?.message || `API request failed with status ${response.status}`)
        error.status = response.status
        error.data = data
        throw error
    }

    return data
}
