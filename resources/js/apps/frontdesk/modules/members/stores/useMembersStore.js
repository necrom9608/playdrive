import { defineStore } from 'pinia'
import axios from '@/lib/http'

export const useMembersStore = defineStore('members', {
    state: () => ({
        loading: false,
        saving: false,
        error: null,
        search: '',
        selectedStatuses: [],
        summary: { total: 0, none: 0, active: 0, expiring_soon: 0, expired: 0 },
        memberBadgeTemplates: [],
        members: [],
        selectedMemberId: null,
        _pollTimer: null,
    }),

    getters: {
        selectedMember(state) {
            return state.members.find(m => m.id === state.selectedMemberId) ?? null
        },
    },

    actions: {
        async fetchMembers() {
            this.loading = true
            this.error = null
            try {
                const { data } = await axios.get('/api/frontdesk/members', {
                    params: {
                        search: this.search || undefined,
                        selected_statuses: this.selectedStatuses.length ? this.selectedStatuses : undefined,
                    },
                })
                this.summary              = data?.data?.summary ?? this.summary
                this.memberBadgeTemplates = data?.data?.member_badge_templates ?? []
                this.members              = data?.data?.members ?? []

                if (this.selectedMemberId) {
                    const existing = this.members.find(m => m.id === this.selectedMemberId)
                    this.selectedMemberId = existing?.id ?? this.members[0]?.id ?? null
                } else {
                    this.selectedMemberId = this.members[0]?.id ?? null
                }
            } catch (err) {
                this.error = err?.response?.data?.message ?? 'Leden konden niet geladen worden.'
                this.members = []
                this.selectedMemberId = null
            } finally {
                this.loading = false
            }
        },

        setSearch(value) { this.search = value },

        async setSelectedStatuses(value) {
            this.selectedStatuses = Array.isArray(value) ? value : []
            await this.fetchMembers()
        },

        selectMember(id) { this.selectedMemberId = id },

        startPolling(intervalMs = 30000) {
            this.stopPolling()
            this._pollTimer = setInterval(async () => {
                try {
                    const { data } = await axios.get('/api/frontdesk/members', {
                        params: {
                            search: this.search || undefined,
                            selected_statuses: this.selectedStatuses.length ? this.selectedStatuses : undefined,
                        },
                    })
                    this.members              = data?.data?.members ?? []
                    this.summary              = data?.data?.summary ?? this.summary
                    this.memberBadgeTemplates = data?.data?.member_badge_templates ?? this.memberBadgeTemplates
                    if (this.selectedMemberId && !this.members.find(m => m.id === this.selectedMemberId)) {
                        this.selectedMemberId = this.members[0]?.id ?? null
                    }
                } catch {}
            }, intervalMs)
        },

        stopPolling() {
            if (this._pollTimer) { clearInterval(this._pollTimer); this._pollTimer = null }
        },

        async saveMember(payload) {
            this.saving = true
            this.error = null
            try {
                const { data } = await axios.put(`/api/frontdesk/members/${payload.id}`, payload)
                const savedId = data?.data?.id ?? payload.id ?? null
                await this.fetchMembers()
                if (savedId) this.selectedMemberId = savedId
                return true
            } catch (err) {
                this.error = err?.response?.data?.message ?? 'Opslaan mislukt.'
                throw err
            } finally {
                this.saving = false
            }
        },

        async renewMember(memberId) {
            this.error = null
            try {
                await axios.post(`/api/frontdesk/members/${memberId}/renew`)
                await this.fetchMembers()
                this.selectedMemberId = memberId
                return true
            } catch (err) {
                this.error = err?.response?.data?.message ?? 'Verlengen mislukt.'
                return false
            }
        },
    },
})
