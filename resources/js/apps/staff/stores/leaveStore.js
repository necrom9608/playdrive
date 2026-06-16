import { defineStore } from 'pinia'
import { apiFetch } from '../../../shared/services/api'

export const useStaffLeaveStore = defineStore('staffLeave', {
  state: () => ({
    loading: false,
    saving: false,
    cancellingId: null,
    requests: [],
    error: '',
  }),
  actions: {
    async fetchLeave() {
      this.loading = true
      try {
        const response = await apiFetch('/api/staff/leave')
        this.requests = response.data ?? []
      } finally {
        this.loading = false
      }
    },
    async submitLeave(payload) {
      this.saving = true
      this.error = ''
      try {
        const response = await apiFetch('/api/staff/leave', {
          method: 'POST',
          body: JSON.stringify(payload),
        })
        if (response.data) this.requests.unshift(response.data)
        return true
      } catch (error) {
        this.error = error?.data?.message
          || (error?.data?.errors ? Object.values(error.data.errors)[0]?.[0] : '')
          || 'Aanvraag mislukt.'
        return false
      } finally {
        this.saving = false
      }
    },
    async cancelLeave(id) {
      this.cancellingId = id
      try {
        const response = await apiFetch(`/api/staff/leave/${id}`, { method: 'DELETE' })
        if (response.data) {
          this.requests = this.requests.map(r => (r.id === response.data.id ? response.data : r))
        }
      } finally {
        this.cancellingId = null
      }
    },
  },
})
