import { defineStore } from 'pinia'
import { apiFetch } from '../../../shared/services/api'

export const useStaffDashboardStore = defineStore('staffDashboard', {
  state: () => ({ loading: false, saving: false, data: { attendance: {}, stats: {}, tasks: [], sessions_today: [] }, flash: '' }),
  actions: {
    async fetchDashboard() {
      this.loading = true
      try {
        const response = await apiFetch('/api/staff/dashboard')
        this.data = response.data ?? this.data
      } finally {
        this.loading = false
      }
    },
    async toggleAttendance() {
      this.saving = true
      try {
        const response = await apiFetch('/api/staff/attendance/toggle', { method: 'POST' })
        this.flash = response.message || ''
        await this.fetchDashboard()
      } finally {
        this.saving = false
      }
    },
  },
})
