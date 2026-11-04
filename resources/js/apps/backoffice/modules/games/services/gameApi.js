import { apiFetch } from '../../../../../shared/services/api'

const BASE = '/api/backoffice'

// ── Games (catalogus gevoed door Playscope) ─────────────────────────────────
export function fetchGames() {
    return apiFetch(`${BASE}/games`)
}
export function fetchGamesSummary() {
    return apiFetch(`${BASE}/games/summary`)
}
export function updateGame(id, payload) {
    return apiFetch(`${BASE}/games/${id}`, { method: 'PUT', body: JSON.stringify(payload) })
}
export function markAllGamesReviewed() {
    return apiFetch(`${BASE}/games/mark-all-reviewed`, { method: 'POST' })
}
/** Categorieën + spelers ophalen (Steam/SteamSpy/PCGamingWiki) voor een stukje games → {processed, remaining, last_id, games, categories} */
export function fetchGameMetadata(payload = {}) {
    return apiFetch(`${BASE}/games/fetch-metadata`, { method: 'POST', body: JSON.stringify(payload) })
}
export function deleteGame(id) {
    return apiFetch(`${BASE}/games/${id}`, { method: 'DELETE' })
}

// ── Categorieën ─────────────────────────────────────────────────────────────
export function createGameCategory(payload) {
    return apiFetch(`${BASE}/game-categories`, { method: 'POST', body: JSON.stringify(payload) })
}
export function updateGameCategory(id, payload) {
    return apiFetch(`${BASE}/game-categories/${id}`, { method: 'PUT', body: JSON.stringify(payload) })
}
export function reorderGameCategories(items) {
    return apiFetch(`${BASE}/game-categories/reorder`, { method: 'POST', body: JSON.stringify({ items }) })
}
export function deleteGameCategory(id) {
    return apiFetch(`${BASE}/game-categories/${id}`, { method: 'DELETE' })
}

/** Laat de teller in het menu weten dat het aantal nieuwe games veranderde. */
export function announceNewGamesCount(count) {
    window.dispatchEvent(new CustomEvent('backoffice-games-new', { detail: count }))
}
