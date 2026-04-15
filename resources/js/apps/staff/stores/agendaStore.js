import { defineStore } from 'pinia'
import { apiFetch } from '../../../shared/services/api'

function localToday() {
  return formatLocalDate(new Date())
}

function parseLocalDate(dateString) {
  const [year, month, day] = (dateString || '').split('-').map(Number)
  return new Date(year, (month || 1) - 1, day || 1)
}

function formatLocalDate(date) {
  const year = date.getFullYear()
  const month = String(date.getMonth() + 1).padStart(2, '0')
  const day = String(date.getDate()).padStart(2, '0')
  return `${year}-${month}-${day}`
}

function shiftDate(dateString, days) {
  const date = parseLocalDate(dateString)
  date.setDate(date.getDate() + days)
  return formatLocalDate(date)
}

function shiftMonth(dateString, months) {
  const date = parseLocalDate(dateString)
  date.setMonth(date.getMonth() + months, 1)
  return formatLocalDate(date)
}

export const useStaffAgendaStore = defineStore('staffAgenda', {
  state: () => ({ loading: false, view: 'day', date: localToday(), data: { range: {}, summary: {}, day_registrations: [], days: [] } }),
  actions: {
    setToday() {
      this.date = localToday()
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
