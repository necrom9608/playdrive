import { defineStore } from 'pinia'
import { apiFetch } from '../../../shared/services/api'

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

/** Maandag van de week waarin `date` valt (lokaal, ISO-week). */
function mondayOf(date) {
  const d = new Date(date)
  const day = (d.getDay() + 6) % 7 // 0 = maandag
  d.setDate(d.getDate() - day)
  return d
}

function thisMonday() {
  return formatLocalDate(mondayOf(new Date()))
}

export const useStaffRosterStore = defineStore('staffRoster', {
  state: () => ({
    loading: false,
    weekStart: thisMonday(),
    data: {
      week_start: thisMonday(),
      week_end: thisMonday(),
      range_label: '',
      is_current_week: true,
      totals: { shifts: 0, minutes: 0, hours_label: '0min' },
      days: [],
    },
  }),
  getters: {
    // Enkel de dagen waarop deze medewerker werkt (mobielvriendelijk).
    workingDays: (state) => state.data.days.filter((d) => d.shifts.length > 0),
    hasShifts: (state) => (state.data.totals?.shifts ?? 0) > 0,
  },
  actions: {
    async fetchRoster() {
      this.loading = true
      try {
        const response = await apiFetch(`/api/staff/roster?week_start=${this.weekStart}`)
        this.data = response.data ?? this.data
        this.weekStart = this.data.week_start || this.weekStart
      } finally {
        this.loading = false
      }
    },
    async shiftWeek(direction) {
      const d = parseLocalDate(this.weekStart)
      d.setDate(d.getDate() + direction * 7)
      this.weekStart = formatLocalDate(d)
      await this.fetchRoster()
    },
    async goToThisWeek() {
      this.weekStart = thisMonday()
      await this.fetchRoster()
    },
  },
})
