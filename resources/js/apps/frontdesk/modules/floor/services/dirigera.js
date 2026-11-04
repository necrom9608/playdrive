// IKEA DIRIGERA-hubs via de kassa-app (Tauri): de hubs zijn enkel in het lokale netwerk
// bereikbaar, dus de browser-versie van de frontdesk kan ze niet aansturen.
// Rust-kant: desktop/src-tauri/src/commands/dirigera.rs
import { isTauriRuntime } from '../../../../../shared/runtime/environment'

async function invoke(command, args) {
    const module = await import('@tauri-apps/api/core')
    return module.invoke(command, args)
}

export function dirigeraAvailable() {
    return isTauriRuntime()
}

/** Tauri geeft fouten als tekst terug; altijd een leesbare string maken. */
export function errorText(error) {
    if (!error) return 'Onbekende fout.'
    if (typeof error === 'string') return error
    return error.message || String(error)
}

/** Oudere kassa-app zonder deze commands. */
export function isMissingCommand(error) {
    return /not found|unknown command|not allowed/i.test(errorText(error))
}

/** → { code, verifier } */
export function pairStart(host) {
    return invoke('dirigera_pair_start', { host })
}

/** → { done, token } — done = false zolang de knop op de hub niet ingedrukt is */
export function pairFinish(host, code, verifier) {
    return invoke('dirigera_pair_finish', { host, code, verifier })
}

/** → [{ id, name, room, model, isOn, reachable, powerW }] */
export function listOutlets(host, token) {
    return invoke('dirigera_list_outlets', { host, token })
}

/** → { status, body, isOn, reachable } (isOn/reachable enkel met verify; oudere app: null) */
export function setOutlet(host, token, id, on, verify = false) {
    return invoke('dirigera_set_outlet', { host, token, id, on, verify })
}
