import { defineStore } from 'pinia'
import axios from '@/lib/http'

export const useMailLogsStore = defineStore('mailLogs', {
    state: () => ({
        loading: false,
        error: null,
        search: '',
        selectedStatuses: [],
        selectedTypes: [],
        summary: { total: 0, delivered: 0, opened: 0, issues: 0 },
        logs: [],
        selectedLogId: null,
        selectedLogDetail: null,
        loadingDetail: false,
    }),

    getters: {
        selectedLog(state) {
            return state.logs.find(l => l.id === state.selectedLogId) ?? null
        },
    },

    actions: {
        async fetchLogs() {
            this.loading = true
            this.error = null
            try {
                const { data } = await axios.get('/api/frontdesk/mail-logs', {
                    params: {
                        search: this.search || undefined,
                        statuses: this.selectedStatuses.length ? this.selectedStatuses : undefined,
                        types: this.selectedTypes.length ? this.selectedTypes : undefined,
                    },
                })
                this.summary = data?.data?.summary ?? this.summary
                this.logs    = data?.data?.logs ?? []
                if (this.selectedLogId && !this.logs.find(l => l.id === this.selectedLogId)) {
                    this.selectedLogId = this.logs[0]?.id ?? null
                } else if (!this.selectedLogId) {
                    this.selectedLogId = this.logs[0]?.id ?? null
                }
            } catch (err) {
                this.error = err?.response?.data?.message ?? 'Maillogs konden niet geladen worden.'
                this.logs = []
            } finally {
                this.loading = false
            }
        },

        async fetchDetail(id) {
            this.loadingDetail = true
            this.selectedLogDetail = null
            try {
                const { data } = await axios.get(`/api/frontdesk/mail-logs/${id}`)
                this.selectedLogDetail = data?.data ?? null
            } catch {
                this.selectedLogDetail = null
            } finally {
                this.loadingDetail = false
            }
        },

        selectLog(id) {
            this.selectedLogId = id
            this.selectedLogDetail = null
            if (id) this.fetchDetail(id)
        },

        setSearch(value) { this.search = value },
    },
})
