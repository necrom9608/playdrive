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
// Instellingen van een Playscope-toestel: { idle_minutes } (0 = uit, null = standaard 10)
export function updateVenueDeviceSettings(id, payload) {
    return apiFetch(`${BASE}/venue-devices/${id}/settings`, { method: 'PUT', body: JSON.stringify(payload) })
}
// Bediening op afstand (Playscope): opdrachten en hun status
export function fetchDeviceCommands(id) {
    return apiFetch(`${BASE}/venue-devices/${id}/commands`)
}
export function sendDeviceCommand(id, payload) {
    return apiFetch(`${BASE}/venue-devices/${id}/commands`, { method: 'POST', body: JSON.stringify(payload) })
}
// Beheerpincode (Playscope: kioskmodus verlaten). pin = null wist de code.
export function updateDeviceAdminPin(pin) {
    return apiFetch(`${BASE}/venue-devices/admin-pin`, { method: 'PUT', body: JSON.stringify({ pin }) })
}

// ── Groepen met automatische rotatie (Playscope, bv. racers met TeknoParrot) ──
export function fetchDeviceGroups() {
    return apiFetch(`${BASE}/device-groups`)
}
export function createDeviceGroup(payload) {
    return apiFetch(`${BASE}/device-groups`, { method: 'POST', body: JSON.stringify(payload) })
}
export function updateDeviceGroup(id, payload) {
    return apiFetch(`${BASE}/device-groups/${id}`, { method: 'PUT', body: JSON.stringify(payload) })
}
export function deleteDeviceGroup(id) {
    return apiFetch(`${BASE}/device-groups/${id}`, { method: 'DELETE' })
}
// Nu wisselen: naar game_id (moet in de afspeellijst staan) of de volgende
export function nextDeviceGroupGame(id, gameId = null) {
    return apiFetch(`${BASE}/device-groups/${id}/next`, { method: 'POST', body: JSON.stringify({ game_id: gameId }) })
}
export function pauseDeviceGroup(id, paused) {
    return apiFetch(`${BASE}/device-groups/${id}/pause`, { method: 'POST', body: JSON.stringify({ paused }) })
}
