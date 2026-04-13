import { defineStore } from 'pinia'
import { apiFetch } from '../../../shared/services/api'
import { frontdeskConfig } from '../config/frontdeskConfig'

export const useAuthStore = defineStore('frontdeskAuth', {
    state: () => ({
        user: null,
        initialized: false,
        loading: false,
    }),

    getters: {
        isAuthenticated: state => !!state.user,
        displayName: state => state.user?.name || '',
    },

    actions: {
        async initialize() {
            if (this.initialized) {
                return
            }

            this.loading = true

            try {
                const response = await apiFetch(frontdeskConfig.buildApiUrl('/auth/me'))
                this.user = response.user ?? null
            } catch (error) {
                this.user = null
            } finally {
                this.initialized = true
                this.loading = false
            }
        },

        async login(username, password) {
            const response = await apiFetch(frontdeskConfig.buildApiUrl('/auth/login'), {
                method: 'POST',
                body: JSON.stringify({ username, password }),
            })

            this.user = response.user ?? null
            this.initialized = true

            window.dispatchEvent(new CustomEvent('frontdesk-auth-changed', {
                detail: { authenticated: true, user: this.user },
            }))

            return response
        },

        async loginWithCard(rfidUid) {
            const response = await apiFetch(frontdeskConfig.buildApiUrl('/auth/login-card'), {
                method: 'POST',
                body: JSON.stringify({ rfid_uid: rfidUid }),
            })

            this.user = response.user ?? null
            this.initialized = true

            window.dispatchEvent(new CustomEvent('frontdesk-auth-changed', {
                detail: { authenticated: true, user: this.user },
            }))

            return response
        },

        async logout() {
            await apiFetch(frontdeskConfig.buildApiUrl('/auth/logout'), {
                method: 'POST',
            })

            this.user = null
            this.initialized = true

            window.dispatchEvent(new CustomEvent('frontdesk-auth-changed', {
                detail: { authenticated: false, user: null },
            }))
        },
    },
})
