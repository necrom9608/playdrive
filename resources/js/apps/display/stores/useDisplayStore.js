import { defineStore } from 'pinia'
import axios from 'axios'
import { getDisplayToken, getOrCreateDisplayUuid, storeDisplayToken } from '../shared/device'

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
    }),

    getters: {
        pairingUuid(state) {
            return state.device?.pairing_uuid ?? null
        },
        reservation(state) {
            return state.payload?.reservation ?? null
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

        applyDeviceState(device) {
            this.mode = device?.current_mode ?? 'standby'
            this.payload = device?.current_payload ?? null
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
    },
})
