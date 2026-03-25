export async function apiFetch(url, options = {}) {
    const response = await fetch(url, {
        headers: {
            'Accept': 'application/json',
            'Content-Type': 'application/json',
            ...(options.headers || {}),
        },
        ...options,
    })

    if (!response.ok) {
        const text = await response.text()
        throw new Error(text || `API request failed with status ${response.status}`)
    }

    return response.json()
}
