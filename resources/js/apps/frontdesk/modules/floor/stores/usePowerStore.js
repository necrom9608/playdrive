import { defineStore } from 'pinia'
import { fetchPower, storeHubToken, syncHub } from '../services/floorApi'
import {
    dirigeraAvailable,
    errorText,
    isMissingCommand,
    listOutlets,
    pairFinish,
    pairStart,
    setOutlet,
} from '../services/dirigera'

/**
 * Slimme stekkers (IKEA DIRIGERA) vanuit de kassa-app.
 *
 * - Stekkers uitlezen: elke 10 s rechtstreeks van de hubs (lokaal netwerk); om de minuut
 *   krijgt PlayDrive een kopie (zo kan de backoffice ze aan speelplekken koppelen).
 * - Zaal openen:  per golf (backoffice → Speelplekken → Stroom) de stekkers aan, met een paar
 *   seconden tussen twee stekkers en een wachttijd na elke golf.
 * - Zaal sluiten: eerst alle Playscope-pc's netjes uitzetten, wachten, dan de stekkers uit in
 *   omgekeerde volgorde.
 * De sequentie loopt in deze store, dus ook verder als je naar een ander scherm gaat.
 */

const POLL_MS = 10000
const SYNC_MS = 60000
const PAIR_TIMEOUT_MS = 90000
const PAIR_POLL_MS = 2000
export const SHUTDOWN_GRACE_SECONDS = 60
const OFF_STAGGER_MS = 500

let pollTimer = null
let run = null // { cancelled, skip, wake }

function sleepRun(ms) {
    return new Promise((resolve) => {
        const timer = window.setTimeout(done, ms)
        function done() {
            window.clearTimeout(timer)
            if (run) run.wake = null
            resolve()
        }
        if (run) run.wake = done
    })
}

function key(hubId, externalId) {
    return `${hubId}:${externalId}`
}

export const usePowerStore = defineStore('frontdeskPower', {
    state: () => ({
        available: dirigeraAvailable(),
        appOutdated: false,
        loaded: false,
        error: '',
        hubs: [],
        waves: [],
        plugs: [],
        live: {},          // hubId → { at, error, outlets: { externalId: outlet } }
        lastSync: {},      // hubId → ms
        pending: {},       // "hub:ext" → true (bezig met schakelen)
        sequence: null,
        pairing: null,     // { hubId, state: starting|waiting|done|error, message, deadline }
    }),

    getters: {
        pairedHubs: (state) => state.hubs.filter(h => h.paired && h.token),
        hasHubs: (state) => state.hubs.length > 0,
        sequencePlugs: (state) => state.plugs.filter(p => p.wave_id),
        canControl(state) {
            return state.available && !state.appOutdated && this.pairedHubs.length > 0
        },
        sequenceRunning: (state) => state.sequence?.status === 'running',
        plugsForSetup: (state) => (setupId) => state.plugs.filter(p => p.setup_id === setupId),
        outlet: (state) => (plug) => state.live[plug.hub_id]?.outlets?.[plug.external_id] ?? null,
        hubError: (state) => (hubId) => state.live[hubId]?.error ?? null,
        isPending: (state) => (plug) => Boolean(state.pending[key(plug.hub_id, plug.external_id)]),
        /** Golven met hun stekkers, in volgorde. */
        plan(state) {
            return [...state.waves]
                .sort((a, b) => a.position - b.position || a.id - b.id)
                .map(wave => ({
                    ...wave,
                    plugs: state.plugs
                        .filter(p => p.wave_id === wave.id)
                        .sort((a, b) => a.wave_order - b.wave_order || a.name.localeCompare(b.name, 'nl')),
                }))
                .filter(wave => wave.plugs.length)
        },
        /** Geschatte duur van "Zaal openen" in seconden. */
        openDuration() {
            const waves = this.plan
            return waves.reduce((sum, w, i) => sum
                + Math.max(0, w.plugs.length - 1) * w.stagger_seconds
                + (i < waves.length - 1 ? w.delay_after_seconds : 0), 0)
        },
    },

    actions: {
        async load() {
            try {
                const data = await fetchPower()
                this.hubs = data.hubs ?? []
                this.waves = data.waves ?? []
                this.plugs = data.plugs ?? []
                this.error = ''
                this.loaded = true
            } catch (e) {
                this.error = e.message || 'Stekkers laden mislukt.'
            }
        },

        start() {
            this.stop()
            this.load().then(() => this.poll())
            pollTimer = window.setInterval(() => {
                if (!document.hidden && !this.sequenceRunning) this.poll()
            }, POLL_MS)
        },

        stop() {
            if (pollTimer) window.clearInterval(pollTimer)
            pollTimer = null
        },

        hub(hubId) {
            return this.hubs.find(h => h.id === hubId) ?? null
        },

        /** Alle gekoppelde hubs uitlezen. */
        async poll() {
            if (!this.available || this.appOutdated) return
            await Promise.all(this.pairedHubs.map(hub => this.pollHub(hub)))
        },

        async pollHub(hub) {
            const now = Date.now()
            try {
                const outlets = await listOutlets(hub.host, hub.token)
                const map = {}
                for (const o of outlets) map[o.id] = o
                this.live[hub.id] = { at: now, error: null, outlets: map }

                if (now - (this.lastSync[hub.id] ?? 0) > SYNC_MS) {
                    this.lastSync[hub.id] = now
                    const res = await syncHub(hub.id, {
                        devices: outlets.map(o => ({
                            id: o.id,
                            name: o.name,
                            room: o.room,
                            model: o.model,
                            is_on: o.isOn,
                            reachable: o.reachable,
                            power_w: o.powerW,
                        })),
                    }).catch(() => null)
                    // Nieuwe stekkers op de hub: lijst opnieuw ophalen.
                    if (res?.new) await this.load()
                }
            } catch (e) {
                if (isMissingCommand(e)) {
                    this.appOutdated = true
                    return
                }
                const message = errorText(e)
                this.live[hub.id] = { ...(this.live[hub.id] ?? { outlets: {} }), at: now, error: message }
                if (now - (this.lastSync[hub.id] ?? 0) > SYNC_MS) {
                    this.lastSync[hub.id] = now
                    syncHub(hub.id, { error: message }).catch(() => {})
                }
            }
        },

        /** Eén stekker schakelen. Gooit een fout met leesbare tekst. */
        async setPlug(plug, on) {
            const hub = this.hub(plug.hub_id)
            if (!hub?.paired || !hub.token) throw new Error(`Hub van ${plug.name} is niet gekoppeld.`)
            const k = key(plug.hub_id, plug.external_id)
            this.pending[k] = true
            try {
                await setOutlet(hub.host, hub.token, plug.external_id, on)
                const outlet = this.live[hub.id]?.outlets?.[plug.external_id]
                if (outlet) outlet.isOn = on
            } catch (e) {
                if (isMissingCommand(e)) this.appOutdated = true
                throw new Error(errorText(e))
            } finally {
                delete this.pending[k]
            }
            window.setTimeout(() => this.pollHub(hub), 2500)
        },

        // ---------------------------------------------------------- koppelen

        async pair(hub) {
            if (!this.available) return
            this.pairing = { hubId: hub.id, state: 'starting', message: '', deadline: null }
            try {
                const { code, verifier } = await pairStart(hub.host)
                this.pairing = { hubId: hub.id, state: 'waiting', message: '', deadline: Date.now() + PAIR_TIMEOUT_MS }

                while (this.pairing?.hubId === hub.id && this.pairing.state === 'waiting') {
                    if (Date.now() > this.pairing.deadline) {
                        this.pairing = { hubId: hub.id, state: 'error', message: 'Geen knopdruk gezien. Probeer opnieuw en druk binnen de minuut op de knop onderaan de hub.' }
                        return
                    }
                    await new Promise(r => window.setTimeout(r, PAIR_POLL_MS))
                    if (this.pairing?.hubId !== hub.id || this.pairing.state !== 'waiting') return
                    const res = await pairFinish(hub.host, code, verifier)
                    if (res?.done && res.token) {
                        await storeHubToken(hub.id, res.token)
                        this.pairing = { hubId: hub.id, state: 'done', message: `${hub.name} is gekoppeld.` }
                        this.lastSync[hub.id] = 0
                        await this.load()
                        await this.poll()
                        return
                    }
                }
            } catch (e) {
                if (isMissingCommand(e)) this.appOutdated = true
                this.pairing = { hubId: hub.id, state: 'error', message: errorText(e) }
            }
        },

        cancelPairing() {
            this.pairing = null
        },

        // ---------------------------------------------------------- sequenties

        cancelSequence() {
            if (!run) return
            run.cancelled = true
            run.wake?.()
        },

        skipWait() {
            if (!run) return
            run.skip = true
            run.wake?.()
        },

        clearSequence() {
            if (!this.sequenceRunning) this.sequence = null
        },

        async wait(seconds, label) {
            if (seconds <= 0 || run.cancelled) return
            this.sequence.waitLabel = label
            this.sequence.waitUntil = Date.now() + seconds * 1000
            run.skip = false
            await sleepRun(seconds * 1000)
            this.sequence.waitLabel = ''
            this.sequence.waitUntil = null
        },

        newSequence(kind, steps) {
            this.sequence = {
                kind,
                status: 'running',
                phase: '',
                waitLabel: '',
                waitUntil: null,
                steps,
                startedAt: Date.now(),
                finishedAt: null,
            }
            run = { cancelled: false, skip: false, wake: null }
        },

        finishSequence() {
            const failed = this.sequence.steps.some(s => s.state === 'failed')
            this.sequence.status = run.cancelled ? 'cancelled' : (failed ? 'failed' : 'done')
            this.sequence.phase = run.cancelled ? 'Gestopt' : (failed ? 'Klaar, met fouten' : 'Klaar')
            this.sequence.finishedAt = Date.now()
            this.sequence.waitLabel = ''
            this.sequence.waitUntil = null
            run = null
            this.poll()
        },

        async switchStep(step, plug, on) {
            step.state = 'busy'
            try {
                await this.setPlug(plug, on)
                step.state = 'ok'
                return true
            } catch (e) {
                step.state = 'failed'
                step.error = e.message
                return false
            }
        },

        async openFloor() {
            if (this.sequenceRunning || !this.canControl) return
            await this.poll()
            const plan = this.plan
            this.newSequence('open', plan.flatMap(w => w.plugs.map(p => ({ plugId: p.id, name: p.name, wave: w.name, state: 'pending', error: '' }))))
            const steps = this.sequence.steps // reactieve versie

            for (const [i, wave] of plan.entries()) {
                if (run.cancelled) break
                this.sequence.phase = `${wave.name} (${i + 1}/${plan.length})`
                let switched = 0
                for (const plug of wave.plugs) {
                    if (run.cancelled) break
                    const step = steps.find(s => s.plugId === plug.id)
                    if (this.outlet(plug)?.isOn === true) {
                        step.state = 'skipped'
                        step.error = 'Stond al aan'
                        continue
                    }
                    if (switched > 0 && wave.stagger_seconds > 0) await sleepRun(wave.stagger_seconds * 1000)
                    if (run.cancelled) break
                    await this.switchStep(step, plug, true)
                    switched++
                }
                if (switched > 0 && i < plan.length - 1) {
                    await this.wait(wave.delay_after_seconds, `Wachten voor ${plan[i + 1].name}`)
                }
            }

            this.finishSequence()
        },

        /** @param {() => Promise<number>} shutdownPcs zet de Playscope-pc's uit, geeft het aantal terug */
        async closeFloor(shutdownPcs) {
            if (this.sequenceRunning || !this.canControl) return
            await this.poll()
            const plan = [...this.plan].reverse().map(w => ({ ...w, plugs: [...w.plugs].reverse() }))
            this.newSequence('close', plan.flatMap(w => w.plugs.map(p => ({ plugId: p.id, name: p.name, wave: w.name, state: 'pending', error: '' }))))
            const steps = this.sequence.steps // reactieve versie

            this.sequence.phase = 'Pc\'s afsluiten'
            let count = 0
            try {
                count = await shutdownPcs()
            } catch (e) {
                this.sequence.phase = `Pc's afsluiten mislukt: ${e.message || 'onbekende fout'}`
            }
            if (count > 0) {
                await this.wait(SHUTDOWN_GRACE_SECONDS, `${count} pc${count === 1 ? '' : '\'s'} sluiten netjes af`)
            }

            for (const [i, wave] of plan.entries()) {
                if (run.cancelled) break
                this.sequence.phase = `${wave.name} uit (${i + 1}/${plan.length})`
                for (const plug of wave.plugs) {
                    if (run.cancelled) break
                    const step = steps.find(s => s.plugId === plug.id)
                    if (this.outlet(plug)?.isOn === false) {
                        step.state = 'skipped'
                        step.error = 'Stond al uit'
                        continue
                    }
                    await this.switchStep(step, plug, false)
                    await sleepRun(OFF_STAGGER_MS)
                }
            }

            this.finishSequence()
        },
    },
})
