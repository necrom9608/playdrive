import { apiFetch } from '../../../../../shared/services/api'

const BASE = '/api/backoffice'

// ── Rollen ──────────────────────────────────────────────────────────────────
export function fetchRoles() {
    return apiFetch(`${BASE}/roster-roles`)
}
export function createRole(payload) {
    return apiFetch(`${BASE}/roster-roles`, { method: 'POST', body: JSON.stringify(payload) })
}
export function updateRole(id, payload) {
    return apiFetch(`${BASE}/roster-roles/${id}`, { method: 'PUT', body: JSON.stringify(payload) })
}
export function reorderRoles(items) {
    return apiFetch(`${BASE}/roster-roles/reorder`, { method: 'POST', body: JSON.stringify({ items }) })
}
export function deleteRole(id) {
    return apiFetch(`${BASE}/roster-roles/${id}`, { method: 'DELETE' })
}

// ── Basisdata ───────────────────────────────────────────────────────────────
export function fetchRosterBase() {
    return apiFetch(`${BASE}/rosters`)
}

// ── Algemeen rooster (slots) ────────────────────────────────────────────────
export function fetchSlots(seasonKey) {
    const params = new URLSearchParams({ season_key: seasonKey })
    return apiFetch(`${BASE}/rosters/slots?${params.toString()}`)
}
export function createSlot(payload) {
    return apiFetch(`${BASE}/rosters/slots`, { method: 'POST', body: JSON.stringify(payload) })
}
export function updateSlot(id, payload) {
    return apiFetch(`${BASE}/rosters/slots/${id}`, { method: 'PUT', body: JSON.stringify(payload) })
}
export function deleteSlot(id) {
    return apiFetch(`${BASE}/rosters/slots/${id}`, { method: 'DELETE' })
}

// ── Weekplanning ────────────────────────────────────────────────────────────
export function fetchWeek(weekStart) {
    const params = new URLSearchParams({ week_start: weekStart })
    return apiFetch(`${BASE}/rosters/week?${params.toString()}`)
}
export function generateWeek(weekStart) {
    return apiFetch(`${BASE}/rosters/week/generate`, { method: 'POST', body: JSON.stringify({ week_start: weekStart }) })
}
export function resetWeek(weekStart) {
    return apiFetch(`${BASE}/rosters/week/reset`, { method: 'POST', body: JSON.stringify({ week_start: weekStart }) })
}

export function createShift(payload) {
    return apiFetch(`${BASE}/rosters/shifts`, { method: 'POST', body: JSON.stringify(payload) })
}
export function updateShift(id, payload) {
    return apiFetch(`${BASE}/rosters/shifts/${id}`, { method: 'PUT', body: JSON.stringify(payload) })
}
export function deleteShift(id) {
    return apiFetch(`${BASE}/rosters/shifts/${id}`, { method: 'DELETE' })
}

export function addAssignment(shiftId, userId) {
    return apiFetch(`${BASE}/rosters/shifts/${shiftId}/assignments`, {
        method: 'POST',
        body: JSON.stringify({ user_id: userId }),
    })
}
export function removeAssignment(assignmentId) {
    return apiFetch(`${BASE}/rosters/assignments/${assignmentId}`, { method: 'DELETE' })
}
