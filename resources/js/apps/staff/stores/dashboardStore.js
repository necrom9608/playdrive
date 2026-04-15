import { defineStore } from 'pinia'
import { apiFetch } from '../../../shared/services/api'

function getTodayDateString() {
  return new Date().toISOString().slice(0, 10)
}

export const useStaffDashboardStore = defineStore('staffDashboard', {
  state: () => ({
    loading: false,
    saving: false,
    selectedDate: getTodayDateString(),
    data: {
      selected_date: getTodayDateString(),
      selected_date_label: '',
      is_today: true,
      attendance: {},
      reservations: { total: 0, participants: 0, statuses: [] },
      catering: { total: 0, items: [] },
      tasks: { open_count: 0, overdue_count: 0, items: [] },
      sessions_for_day: [],
      revenue: { visible: false, total: null, label: null },
    },
    flash: '',
  }),
  actions: {
    async fetchDashboard() {
      this.loading = true
      try {
        const params = new URLSearchParams()
        if (this.selectedDate) params.set('date', this.selectedDate)
        const response = await apiFetch(`/api/staff/dashboard${params.toString() ? `?${params.toString()}` : ''}`)
        this.data = response.data ?? this.data
        this.selectedDate = this.data.selected_date || this.selectedDate
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
    async setDate(date) {
      this.selectedDate = date
      await this.fetchDashboard()
    },
    async goToToday() {
      this.selectedDate = getTodayDateString()
      await this.fetchDashboard()
    },
    async shiftDate(days) {
      const current = new Date(`${this.selectedDate}T12:00:00`)
      current.setDate(current.getDate() + days)
      this.selectedDate = current.toISOString().slice(0, 10)
      await this.fetchDashboard()
    },
  },
})
