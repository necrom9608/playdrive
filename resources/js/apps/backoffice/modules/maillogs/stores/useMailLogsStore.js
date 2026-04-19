import { defineStore } from 'pinia'
import axios from 'axios'

export const useMailLogsStore = defineStore('backofficeMailLogs', {
    state: () => ({
        loading: false,
        error: null,
        search: '',
        selectedStatuses: [],
        selectedTypes: [],
        summary: { total: 0, sent_today: 0, delivered: 0, opened: 0, issues: 0 },
        logs: [],
        meta: { current_page: 1, last_page: 1, per_page: 50, total: 0 },
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
        async fetchLogs(page = 1) {
            this.loading = true
            this.error = null
            try {
                const { data } = await axios.get('/api/backoffice/mail-logs', {
                    params: {
                        search:   this.search || undefined,
                        statuses: this.selectedStatuses.length ? this.selectedStatuses : undefined,
                        types:    this.selectedTypes.length ? this.selectedTypes : undefined,
                        page,
                        per_page: this.meta.per_page,
                    },
                })
                this.summary = data?.data?.summary ?? this.summary
                this.logs    = data?.data?.logs ?? []
                this.meta    = data?.data?.meta ?? this.meta

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
                const { data } = await axios.get(`/api/backoffice/mail-logs/${id}`)
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
        goToPage(page)   { this.fetchLogs(page) },
    },
})
