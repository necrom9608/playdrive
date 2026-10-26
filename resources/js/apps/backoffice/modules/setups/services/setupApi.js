import { apiFetch } from '../../../../../shared/services/api'

const BASE = '/api/backoffice'

// ── Speelplekken ────────────────────────────────────────────────────────────
export function fetchSetups() {
    return apiFetch(`${BASE}/setups`)
}
export function createSetup(payload) {
    return apiFetch(`${BASE}/setups`, { method: 'POST', body: JSON.stringify(payload) })
}
export function updateSetup(id, payload) {
    return apiFetch(`${BASE}/setups/${id}`, { method: 'PUT', body: JSON.stringify(payload) })
}
export function reorderSetups(items) {
    return apiFetch(`${BASE}/setups/reorder`, { method: 'POST', body: JSON.stringify({ items }) })
}
export function regenerateSetupQr(id) {
    return apiFetch(`${BASE}/setups/${id}/regenerate-qr`, { method: 'POST' })
}
export function deleteSetup(id) {
    return apiFetch(`${BASE}/setups/${id}`, { method: 'DELETE' })
}

// ── Toestellen (kiosk, Playscope) ───────────────────────────────────────────
export function fetchVenueDevices() {
    return apiFetch(`${BASE}/venue-devices`)
}
export function claimVenueDevice(payload) {
    return apiFetch(`${BASE}/venue-devices/claim`, { method: 'POST', body: JSON.stringify(payload) })
}
export function updateVenueDevice(id, payload) {
    return apiFetch(`${BASE}/venue-devices/${id}`, { method: 'PUT', body: JSON.stringify(payload) })
}
export function deleteVenueDevice(id) {
    return apiFetch(`${BASE}/venue-devices/${id}`, { method: 'DELETE' })
}
