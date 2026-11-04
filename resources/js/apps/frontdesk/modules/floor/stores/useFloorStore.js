import { defineStore } from 'pinia'
import {
    fetchCommand,
    fetchDeviceGames,
    fetchFloor,
    pauseGroup,
    sendDeviceCommand,
    setSetupStatus,
    shutdownPcs,
    switchGroup,
} from '../services/floorApi'

const POLL_MS = 5000
const COMMAND_POLL_MS = 1500
const COMMAND_TIMEOUT_MS = 30000

let pollTimer = null
let toastSeq = 0

const sleep = (ms) => new Promise(resolve => window.setTimeout(resolve, ms))

export const useFloorStore = defineStore('frontdeskFloor', {
    state: () => ({
        loaded: false,
        loading: false,
        error: '',
        setups: [],
        groups: [],
        adminPinSet: false,
        clockOffset: 0,          // servertijd − lokale tijd (ms), voor aftellen
        selectedSetupId: null,
        zone: 'all',
        games: {},               // deviceId → { loading, error, items }
        toasts: [],
    }),

    getters: {
        selectedSetup(state) {
            return state.setups.find(s => s.id === state.selectedSetupId) ?? null
        },
        /** Speelplekken die niet volledig in een groep zitten, per zone. */
        zones(state) {
            const map = new Map()
            for (const setup of state.setups) {
                if (setup.group_id) continue
                const zone = setup.zone || 'Zonder zone'
                if (!map.has(zone)) map.set(zone, [])
                map.get(zone).push(setup)
            }
            return [...map.entries()]
                .sort(([a], [b]) => (a === 'Zonder zone') - (b === 'Zonder zone') || a.localeCompare(b, 'nl'))
                .map(([zone, items]) => ({ zone, items }))
        },
        groupOf(state) {
            return (groupId) => state.groups.find(g => g.id === groupId) ?? null
        },
    },

    actions: {
        async fetch({ silent = false } = {}) {
            if (!silent) this.loading = true
            try {
                const data = await fetchFloor()
                this.setups = data.setups ?? []
                this.groups = data.groups ?? []
                this.adminPinSet = Boolean(data.admin_pin_set)
                if (data.server_time) {
                    this.clockOffset = new Date(data.server_time).getTime() - Date.now()
                }
                this.error = ''
                this.loaded = true
                if (this.selectedSetupId && !this.setups.some(s => s.id === this.selectedSetupId)) {
                    this.selectedSetupId = null
                }
            } catch (e) {
                if (!silent || !this.loaded) this.error = e.message || 'Zaal laden mislukt.'
            } finally {
                this.loading = false
            }
        },

        startPolling() {
            this.stopPolling()
            pollTimer = window.setInterval(() => {
                if (!document.hidden) this.fetch({ silent: true })
            }, POLL_MS)
        },

        stopPolling() {
            if (pollTimer) window.clearInterval(pollTimer)
            pollTimer = null
        },

        select(setupId) {
            this.selectedSetupId = this.selectedSetupId === setupId ? null : setupId
        },

        toast(text, tone = 'ok') {
            const id = ++toastSeq
            this.toasts.push({ id, text, tone })
            window.setTimeout(() => {
                this.toasts = this.toasts.filter(t => t.id !== id)
            }, tone === 'error' ? Math.min(20000, Math.max(6000, text.length * 60)) : 3500)
        },

        async loadGames(deviceId, { force = false } = {}) {
            const current = this.games[deviceId]
            if (current && (current.loading || (current.items && !force))) return
            this.games[deviceId] = { loading: true, error: '', items: current?.items ?? null }
            try {
                const data = await fetchDeviceGames(deviceId)
                this.games[deviceId] = { loading: false, error: '', items: data.games ?? [] }
            } catch (e) {
                this.games[deviceId] = { loading: false, error: e.message || 'Games laden mislukt.', items: null }
            }
        },

        /**
         * Opdracht naar een Playscope-pc. Volgt de status op en toont het resultaat.
         * @returns {Promise<boolean>} false als PlayDrive de opdracht weigerde (bv. foute pincode)
         */
        async sendCommand(device, command, { payload = null, gameId = null, pin = null, label = '' } = {}) {
            let created
            try {
                created = await sendDeviceCommand(device.id, {
                    command,
                    payload,
                    game_id: gameId,
                    pin,
                })
            } catch (e) {
                this.toast(e.message || 'Opdracht versturen mislukt.', 'error')
                return false
            }

            const what = label || created.label
            this.toast(`${device.name || 'Pc'}: ${what} verstuurd`, 'info')
            this.followCommand(device, created.id, what)
            return true
        },

        async followCommand(device, commandId, what) {
            const started = Date.now()
            while (Date.now() - started < COMMAND_TIMEOUT_MS) {
                await sleep(COMMAND_POLL_MS)
                let cmd
                try {
                    cmd = await fetchCommand(commandId)
                } catch {
                    continue
                }
                if (cmd.status === 'done') {
                    this.toast(`${device.name || 'Pc'}: ${cmd.result || `${what} gelukt`}`, 'ok')
                    this.fetch({ silent: true })
                    return
                }
                if (cmd.status === 'failed' || cmd.status === 'expired') {
                    this.toast(`${device.name || 'Pc'}: ${cmd.result || `${what} mislukt`}`, 'error')
                    return
                }
            }
            this.toast(`${device.name || 'Pc'} reageert niet. Staat Playscope aan?`, 'error')
        },

        async setStatus(setup, status) {
            try {
                await setSetupStatus(setup.id, status)
                const row = this.setups.find(s => s.id === setup.id)
                if (row) row.status = status
                this.toast(status === 'maintenance' ? `${setup.name} in onderhoud` : `${setup.name} terug beschikbaar`)
            } catch (e) {
                this.toast(e.message || 'Status wijzigen mislukt.', 'error')
            }
        },

        async groupSwitch(group, game = null) {
            try {
                await switchGroup(group.id, game?.id ?? null)
                this.toast(game ? `${group.name} wisselt naar ${game.name}` : `${group.name} wisselt naar de volgende game`)
                await this.fetch({ silent: true })
            } catch (e) {
                this.toast(e.message || 'Wisselen mislukt.', 'error')
            }
        },

        async groupPause(group, paused) {
            try {
                await pauseGroup(group.id, paused)
                this.toast(paused ? `${group.name}: rotatie vastgezet` : `${group.name}: rotatie hervat`)
                await this.fetch({ silent: true })
            } catch (e) {
                this.toast(e.message || 'Mislukt.', 'error')
            }
        },

        /** Zaal sluiten: alle online Playscope-pc's netjes uitzetten. → aantal pc's */
        async shutdownAllPcs() {
            const res = await shutdownPcs()
            return Number(res?.count ?? 0)
        },
    },
})
