import { defineStore } from 'pinia'
import { apiFetch } from '../../../shared/services/api'

export const useBackofficeAuthStore = defineStore('backofficeAuth', {
    state: () => ({
        user: null,
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
                const response = await apiFetch('/api/backoffice/auth/me')
                this.user = response.user ?? null
            } catch {
                this.user = null
            } finally {
                this.initialized = true
                this.loading = false
            }
        },

        async login(username, password) {
            const response = await apiFetch('/api/backoffice/auth/login', {
                method: 'POST',
                body: JSON.stringify({ username, password }),
            })

            this.user = response.user ?? null
            this.initialized = true
            return response
        },

        async logout() {
            await apiFetch('/api/backoffice/auth/logout', { method: 'POST' })
            this.user = null
            this.initialized = true
            window.location.reload()
        },
    },
})
