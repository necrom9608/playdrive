import { defineStore } from 'pinia'
import { apiFetch } from '../../../shared/services/api'

function today() {
  return new Date().toISOString().slice(0, 10)
}

function shiftDate(dateString, days) {
  const date = new Date(`${dateString}T00:00:00`)
  date.setDate(date.getDate() + days)
  return date.toISOString().slice(0, 10)
}

function shiftMonth(dateString, months) {
  const date = new Date(`${dateString}T00:00:00`)
  date.setMonth(date.getMonth() + months, 1)
  return date.toISOString().slice(0, 10)
}

export const useStaffAgendaStore = defineStore('staffAgenda', {
  state: () => ({ loading: false, view: 'day', date: today(), data: { range: {}, summary: {}, day_registrations: [], days: [] } }),
  actions: {
    setToday() {
      this.date = today()
    },
    shiftRange(direction) {
      if (this.view === 'month') {
        this.date = shiftMonth(this.date, direction)
        return
      }

      if (this.view === 'week') {
        this.date = shiftDate(this.date, direction * 7)
        return
      }

      this.date = shiftDate(this.date, direction)
    },
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
