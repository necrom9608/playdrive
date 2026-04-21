const BASE = '/api/public'

async function apiFetch(url, options = {}) {
    const res = await fetch(url, {
        headers: {
            'Content-Type': 'application/json',
            'Accept': 'application/json',
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content ?? '',
            ...options.headers,
        },
        ...options,
    })

    const data = await res.json().catch(() => ({}))

    if (!res.ok) {
        const error = new Error(data.message ?? 'Er ging iets mis.')
        error.data = data
        error.status = res.status
        throw error
    }

    return data
}

export function fetchBookingFormSetup(tenant) {
    return apiFetch(`${BASE}/booking-form/setup?tenant=${encodeURIComponent(tenant)}`)
}

export function submitReservation(payload) {
    return apiFetch(`${BASE}/booking-form/submit`, {
        method: 'POST',
        body: JSON.stringify(payload),
    })
}
