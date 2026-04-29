import { defineStore } from 'pinia'
import { apiFetch } from '../../../shared/services/api'

export const usePortalAuthStore = defineStore('portalAuth', {
    state: () => ({
        user: null,
        tenant: null,
        initialized: false,
        loading: false,
    }),

    getters: {
        isAuthenticated: state => !!state.user,
    },

    actions: {
        async initialize() {
            if (this.initialized) {
                return
            }

            this.loading = true

            try {
                const response = await apiFetch('/portal/api/auth/me')
                this.user = response.user ?? null
                this.tenant = response.tenant ?? null
            } catch {
                this.user = null
                this.tenant = null
            } finally {
                this.initialized = true
                this.loading = false
            }
        },

        async login(username, password) {
            const response = await apiFetch('/portal/api/auth/login', {
                method: 'POST',
                body: JSON.stringify({ username, password }),
            })

            this.user = response.user ?? null
            this.tenant = response.tenant ?? null
            this.initialized = true
            return response
        },

        async logout() {
            await apiFetch('/portal/api/auth/logout', { method: 'POST' })
            this.user = null
            this.tenant = null
            this.initialized = true
            window.location.reload()
        },

        updateTenant(updates) {
            if (this.tenant) {
                this.tenant = { ...this.tenant, ...updates }
            }
        },
    },
})
