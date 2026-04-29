import { apiFetch } from '../../../shared/services/api'

const BASE = '/portal/api/venue'

// ----------------------------------------------------------------------
// Info
// ----------------------------------------------------------------------

export const getInfo = () => apiFetch(`${BASE}/info`)
export const updateInfo = (data) => apiFetch(`${BASE}/info`, {
    method: 'PUT',
    body: JSON.stringify(data),
})

// ----------------------------------------------------------------------
// Media
// ----------------------------------------------------------------------

export const getMedia = () => apiFetch(`${BASE}/media`)

export function uploadLogo(file) {
    const fd = new FormData()
    fd.append('file', file)
    return apiFetch(`${BASE}/media/logo`, { method: 'POST', body: fd })
}

export const deleteLogo = () => apiFetch(`${BASE}/media/logo`, { method: 'DELETE' })

export function uploadHero(file) {
    const fd = new FormData()
    fd.append('file', file)
    return apiFetch(`${BASE}/media/hero`, { method: 'POST', body: fd })
}

export const deleteHero = () => apiFetch(`${BASE}/media/hero`, { method: 'DELETE' })

export function uploadPhoto(file, altText = null) {
    const fd = new FormData()
    fd.append('file', file)
    if (altText) fd.append('alt_text', altText)
    return apiFetch(`${BASE}/media/photos`, { method: 'POST', body: fd })
}

export const deletePhoto = (id) => apiFetch(`${BASE}/media/photos/${id}`, { method: 'DELETE' })

export const reorderPhotos = (ids) => apiFetch(`${BASE}/media/photos/order`, {
    method: 'PUT',
    body: JSON.stringify({ order: ids }),
})

// ----------------------------------------------------------------------
// Activities
// ----------------------------------------------------------------------

export const getActivities = () => apiFetch(`${BASE}/activities`)

export const createActivity = (data) => apiFetch(`${BASE}/activities`, {
    method: 'POST',
    body: JSON.stringify(data),
})

export const updateActivity = (id, data) => apiFetch(`${BASE}/activities/${id}`, {
    method: 'PUT',
    body: JSON.stringify(data),
})

export const deleteActivity = (id) => apiFetch(`${BASE}/activities/${id}`, { method: 'DELETE' })

export const reorderActivities = (ids) => apiFetch(`${BASE}/activities/order`, {
    method: 'PUT',
    body: JSON.stringify({ order: ids }),
})

// ----------------------------------------------------------------------
// Amenities
// ----------------------------------------------------------------------

export const getAmenities = () => apiFetch(`${BASE}/amenities`)

export const updateAmenities = (amenities) => apiFetch(`${BASE}/amenities`, {
    method: 'PUT',
    body: JSON.stringify({ amenities }),
})

// ----------------------------------------------------------------------
// Links
// ----------------------------------------------------------------------

export const getLinks = () => apiFetch(`${BASE}/links`)

export const createLink = (data) => apiFetch(`${BASE}/links`, {
    method: 'POST',
    body: JSON.stringify(data),
})

export const updateLink = (id, data) => apiFetch(`${BASE}/links/${id}`, {
    method: 'PUT',
    body: JSON.stringify(data),
})

export const deleteLink = (id) => apiFetch(`${BASE}/links/${id}`, { method: 'DELETE' })

// ----------------------------------------------------------------------
// Publication
// ----------------------------------------------------------------------

export const getPublication = () => apiFetch(`${BASE}/publication`)

export const updateSlug = (slug) => apiFetch(`${BASE}/publication/slug`, {
    method: 'PUT',
    body: JSON.stringify({ public_slug: slug }),
})

export const publish = () => apiFetch(`${BASE}/publication/publish`, { method: 'POST' })
export const unpublish = () => apiFetch(`${BASE}/publication/unpublish`, { method: 'POST' })
