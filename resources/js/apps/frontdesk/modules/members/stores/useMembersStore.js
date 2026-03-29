import { defineStore } from 'pinia'
import axios from 'axios'

export const useMembersStore = defineStore('members', {
    state: () => ({
        loading: false,
        saving: false,
        error: null,
        search: '',
        statusFilter: 'all',
        summary: {
            total: 0,
            active: 0,
            expiring_soon: 0,
            expired: 0,
        },
        members: [],
        selectedMemberId: null,
    }),

    getters: {
        selectedMember(state) {
            return state.members.find(member => member.id === state.selectedMemberId) ?? null
        },
    },

    actions: {
        async fetchMembers() {
            this.loading = true
            this.error = null

            try {
                const response = await axios.get('/api/frontdesk/members', {
                    params: {
                        search: this.search || undefined,
                        status: this.statusFilter,
                    },
                })

                this.summary = response.data?.data?.summary ?? this.summary
                this.members = response.data?.data?.members ?? []

                if (this.selectedMemberId) {
                    const existing = this.members.find(member => member.id === this.selectedMemberId)
                    this.selectedMemberId = existing?.id ?? this.members[0]?.id ?? null
                } else {
                    this.selectedMemberId = this.members[0]?.id ?? null
                }
            } catch (error) {
                console.error('Failed to fetch members', error)
                this.error = error?.response?.data?.message ?? 'Abonnees konden niet geladen worden.'
                this.members = []
                this.selectedMemberId = null
            } finally {
                this.loading = false
            }
        },

        setSearch(value) {
            this.search = value
        },

        setStatusFilter(value) {
            this.statusFilter = value
            return this.fetchMembers()
        },

        selectMember(id) {
            this.selectedMemberId = id
        },

        async saveMember(payload) {
            this.saving = true
            this.error = null

            try {
                if (payload.id) {
                    await axios.put(`/api/frontdesk/members/${payload.id}`, payload)
                } else {
                    await axios.post('/api/frontdesk/members', payload)
                }

                await this.fetchMembers()
                return true
            } catch (error) {
                console.error('Failed to save member', error)
                this.error = error?.response?.data?.message ?? 'Abonnee opslaan mislukt.'
                throw error
            } finally {
                this.saving = false
            }
        },

        async renewMember(memberId) {
            this.error = null

            try {
                await axios.post(`/api/frontdesk/members/${memberId}/renew`)
                await this.fetchMembers()
                return true
            } catch (error) {
                console.error('Failed to renew member', error)
                this.error = error?.response?.data?.message ?? 'Abonnement verlengen mislukt.'
                return false
            }
        },

        async sendLifecycleEmail(memberId, type) {
            this.error = null

            try {
                await axios.post(`/api/frontdesk/members/${memberId}/send-email`, { type })
                await this.fetchMembers()
                return true
            } catch (error) {
                console.error('Failed to send member email', error)
                this.error = error?.response?.data?.message ?? 'Mail verzenden mislukt.'
                return false
            }
        },
    },
})
