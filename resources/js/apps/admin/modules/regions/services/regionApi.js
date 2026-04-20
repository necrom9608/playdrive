import { apiFetch } from '../../../../../shared/services/api'

// ─── Regio's ───────────────────────────────────────────────────────────────

export function fetchRegions() {
    return apiFetch('/admin/api/regions')
}

export function createRegion(data) {
    return apiFetch('/admin/api/regions', {
        method: 'POST',
        body: JSON.stringify(data),
    })
}

export function updateRegion(id, data) {
    return apiFetch(`/admin/api/regions/${id}`, {
        method: 'PUT',
        body: JSON.stringify(data),
    })
}

export function deleteRegion(id) {
    return apiFetch(`/admin/api/regions/${id}`, {
        method: 'DELETE',
    })
}

// ─── Seizoenen ────────────────────────────────────────────────────────────

export function fetchSeasons(regionId) {
    return apiFetch(`/admin/api/regions/${regionId}/seasons`)
}

export function createSeason(regionId, data) {
    return apiFetch(`/admin/api/regions/${regionId}/seasons`, {
        method: 'POST',
        body: JSON.stringify(data),
    })
}

export function updateSeason(regionId, seasonId, data) {
    return apiFetch(`/admin/api/regions/${regionId}/seasons/${seasonId}`, {
        method: 'PUT',
        body: JSON.stringify(data),
    })
}

export function deleteSeason(regionId, seasonId) {
    return apiFetch(`/admin/api/regions/${regionId}/seasons/${seasonId}`, {
        method: 'DELETE',
    })
}

export function copySeasons(regionId, fromYear, toYear) {
    return apiFetch(`/admin/api/regions/${regionId}/seasons/copy`, {
        method: 'POST',
        body: JSON.stringify({ from_year: fromYear, to_year: toYear }),
    })
}
