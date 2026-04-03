import { defineStore } from 'pinia'
import axios from '@/lib/http'

export const useAgendaStore = defineStore('frontdeskAgenda', {
    state: () => ({
        loading: false,
        view: 'day',
        date: today(),
        rangeLabel: '',
        summary: {
            reservations: 0,
            participants: 0,
            children: 0,
            adults: 0,
            supervisors: 0,
            status_totals: [],
        },
        dayRegistrations: [],
        days: [],
    }),

    actions: {
        async fetchAgenda() {
            this.loading = true

            try {
                const { data } = await axios.get('/api/frontdesk/agenda', {
                    params: {
                        view: this.view,
                        date: this.date,
                    },
                })

                const payload = data?.data ?? {}

                this.rangeLabel = payload.range?.label ?? ''
                this.summary = payload.summary ?? {
                    reservations: 0,
                    participants: 0,
                    children: 0,
                    adults: 0,
                    supervisors: 0,
                    status_totals: [],
                }
                this.dayRegistrations = payload.day_registrations ?? []
                this.days = payload.days ?? []
            } finally {
                this.loading = false
            }
        },

        async setView(view) {
            this.view = view
            await this.fetchAgenda()
        },

        async setDate(date) {
            this.date = date
            await this.fetchAgenda()
        },

        async goToToday() {
            this.date = today()
            await this.fetchAgenda()
        },

        async goToPrevious() {
            const base = new Date(this.date)

            if (this.view === 'day') {
                base.setDate(base.getDate() - 1)
            } else if (this.view === 'week') {
                base.setDate(base.getDate() - 7)
            } else {
                base.setMonth(base.getMonth() - 1)
            }

            this.date = formatDate(base)
            await this.fetchAgenda()
        },

        async goToNext() {
            const base = new Date(this.date)

            if (this.view === 'day') {
                base.setDate(base.getDate() + 1)
            } else if (this.view === 'week') {
                base.setDate(base.getDate() + 7)
            } else {
                base.setMonth(base.getMonth() + 1)
            }

            this.date = formatDate(base)
            await this.fetchAgenda()
        },

        async openDay(date) {
            this.view = 'day'
            this.date = date
            await this.fetchAgenda()
        },
    },
})

function today() {
    return formatDate(new Date())
}

function formatDate(date) {
    const year = date.getFullYear()
    const month = String(date.getMonth() + 1).padStart(2, '0')
    const day = String(date.getDate()).padStart(2, '0')

    return `${year}-${month}-${day}`
}
