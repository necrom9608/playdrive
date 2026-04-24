import { defineStore } from 'pinia'
import axios from 'axios'
import { getDisplayToken, getOrCreateDisplayUuid, storeDisplayToken } from '../shared/device'
import { isLocalDisplayMode, localDisplayListen, localDisplayClose } from '../../../../shared/localDisplay'

const POLL_INTERVAL = 3000

export const useDisplayStore = defineStore('display', {
    state: () => ({
        device: null,
        mode: 'standby',
        payload: null,
        initialized: false,
        loading: false,
        error: null,
        pollHandle: null,
        // Lokale modus (Tauri tweede scherm)
        localMode: false,
        _localCleanup: null,
    }),

    getters: {
        pairingUuid(state) {
            return state.device?.pairing_uuid ?? null
        },
        reservation(state) {
            return state.payload?.reservation ?? state.payload?.registration ?? null
        },
        order(state) {
            return state.payload?.order ?? null
        },
        totalAmount() {
            return Number(this.order?.total_incl_vat ?? 0)
        },
    },

    actions: {
        async initialize() {
            this.loading = true
            this.error = null

            // ── Lokale modus via BroadcastChannel ──
            if (isLocalDisplayMode()) {
                this.localMode = true
                this.initialized = true
                this.mode = 'standby'
                this.loading = false

                const cleanup = localDisplayListen((message) => {
                    this.applyLocalMessage(message)
                })

                this._localCleanup = cleanup
                return
            }

            // ── Normale server-modus (bestaand gedrag) ──
            try {
                const response = await axios.post('/api/display/bootstrap', {
                    role: 'display',
                    device_uuid: getOrCreateDisplayUuid(),
                    device_token: getDisplayToken(),
                    name: 'Customer Display',
                })

                this.device = response.data?.data ?? null

                if (this.device?.device_token) {
                    storeDisplayToken(this.device.device_token)
                }

                this.applyDeviceState(this.device)
                this.initialized = true
                this.startPolling()
            } catch (error) {
                this.error = error?.response?.data?.message ?? 'Display initialiseren mislukt.'
            } finally {
                this.loading = false
            }
        },

        /**
         * Verwerk een bericht van de lokale BroadcastChannel.
         */
        applyLocalMessage(message) {
            this.mode = message.mode ?? 'standby'
            const payload = message.payload ?? null

            this.payload = payload
                ? {
                    ...payload,
                    reservation: payload?.reservation ?? payload?.registration ?? null,
                    order: payload?.order ?? null,
                }
                : null
        },

        applyDeviceState(device) {
            const payload = device?.current_payload ?? device?.payload ?? null

            this.mode = device?.current_mode ?? device?.mode ?? 'standby'
            this.payload = payload
                ? {
                    ...payload,
                    reservation: payload?.reservation ?? payload?.registration ?? null,
                    order: payload?.order ?? null,
                }
                : null
        },

        startPolling() {
            this.stopPolling()
            this.pollHandle = window.setInterval(() => {
                this.refreshState()
            }, POLL_INTERVAL)
        },

        stopPolling() {
            if (this.pollHandle) {
                window.clearInterval(this.pollHandle)
                this.pollHandle = null
            }
        },

        async refreshState() {
            if (!this.device?.device_uuid || !getDisplayToken()) {
                return
            }

            try {
                const response = await axios.get('/api/display/state', {
                    params: {
                        device_uuid: this.device.device_uuid,
                        device_token: getDisplayToken(),
                    },
                })

                this.device = response.data?.data ?? this.device
                this.applyDeviceState(this.device)
            } catch (error) {
                this.error = error?.response?.data?.message ?? 'Display synchroniseren mislukt.'
            }
        },

        destroy() {
            this.stopPolling()
            if (this._localCleanup) {
                this._localCleanup()
                this._localCleanup = null
            }
            localDisplayClose()
        },
    },
})
