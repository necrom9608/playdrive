import { apiFetch } from '../../../../../shared/services/api'
import { frontdeskConfig } from '../../../config/frontdeskConfig'

const url = (path) => frontdeskConfig.buildApiUrl(path)
const post = (path, body = {}) => apiFetch(url(path), { method: 'POST', body: JSON.stringify(body) })

// ── Zaal ────────────────────────────────────────────────────────────────────
export function fetchFloor() {
    return apiFetch(url('/floor'))
}
export function fetchDeviceGames(deviceId) {
    return apiFetch(url(`/floor/devices/${deviceId}/games`))
}
// { command, payload?, game_id?, pin? }
export function sendDeviceCommand(deviceId, body) {
    return post(`/floor/devices/${deviceId}/commands`, body)
}
export function fetchCommand(commandId) {
    return apiFetch(url(`/floor/commands/${commandId}`))
}
export function setSetupStatus(setupId, status) {
    return post(`/floor/setups/${setupId}/status`, { status })
}
export function switchGroup(groupId, gameId = null) {
    return post(`/floor/groups/${groupId}/switch`, { game_id: gameId })
}
export function pauseGroup(groupId, paused) {
    return post(`/floor/groups/${groupId}/pause`, { paused })
}
export function shutdownPcs() {
    return post('/floor/shutdown-pcs')
}

// ── Stroom (DIRIGERA) ───────────────────────────────────────────────────────
export function fetchPower() {
    return apiFetch(url('/floor/power'))
}
export function storeHubToken(hubId, token) {
    return post(`/floor/power/hubs/${hubId}/token`, { token })
}
// { devices: [...] } of { error }
export function syncHub(hubId, body) {
    return post(`/floor/power/hubs/${hubId}/sync`, body)
}
