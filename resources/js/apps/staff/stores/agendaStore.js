import { defineStore } from 'pinia'
import { apiFetch } from '../../../shared/services/api'

function today() {
  return new Date().toISOString().slice(0, 10)
}

export const useStaffAgendaStore = defineStore('staffAgenda', {
  state: () => ({ loading: false, view: 'day', date: today(), data: { range: {}, summary: {}, day_registrations: [], days: [] } }),
  actions: {
    async fetchAgenda() {
      this.loading = true
      try {
        const response = await apiFetch(`/api/staff/agenda?view=${this.view}&date=${this.date}`)
        this.data = response.data ?? this.data
      } finally {
        this.loading = false
      }
    },
  },
})
