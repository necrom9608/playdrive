import { defineStore } from 'pinia'
import { apiFetch } from '../../../shared/services/api'

export const useStaffAuthStore = defineStore('staffAuth', {
  state: () => ({ user: null, initialized: false, loading: false }),
  getters: {
    isAuthenticated: state => !!state.user,
  },
  actions: {
    async initialize() {
      if (this.initialized) return
      this.loading = true
      try {
        const response = await apiFetch('/api/staff/auth/me')
        this.user = response.user ?? null
      } catch {
        this.user = null
      } finally {
        this.initialized = true
        this.loading = false
      }
    },
    async login(username, password) {
      const response = await apiFetch('/api/staff/auth/login', { method: 'POST', body: JSON.stringify({ username, password }) })
      this.user = response.user ?? null
      this.initialized = true
    },
    async forgotPassword(username) {
      return apiFetch('/api/staff/auth/forgot-password', { method: 'POST', body: JSON.stringify({ username }) })
    },
    async logout() {
      await apiFetch('/api/staff/auth/logout', { method: 'POST' })
      this.user = null
      this.initialized = true
      window.location.href = '/staff/'
    },
  },
})
