import { defineStore } from 'pinia'
import axios from '@/lib/http'

export const useMembersStore = defineStore('members', {
    state: () => ({
        loading: false,
        saving: false,
        error: null,
        search: '',
        selectedStatuses: [],
        summary: {
            total: 0,
            active: 0,
            expiring_soon: 0,
            expired: 0,
        },
        memberBadgeTemplates: [],
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
                        selected_statuses: this.selectedStatuses.length ? this.selectedStatuses : undefined,
                    },
                })

                this.summary = response.data?.data?.summary ?? {
                    total: 0,
                    active: 0,
                    expiring_soon: 0,
                    expired: 0,
                }

                this.memberBadgeTemplates = response.data?.data?.member_badge_templates ?? []
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

        async setSelectedStatuses(value) {
            this.selectedStatuses = Array.isArray(value) ? value : []
            await this.fetchMembers()
        },

        selectMember(id) {
            this.selectedMemberId = id
        },

        async openCreateViaDisplay(posStore) {
            this.error = null

            try {
                await this.fetchMembers()
            } catch {
                // fout staat al in store, maar displayflow mag nog proberen als templates al gekend zijn
            }

            try {
                await posStore?.initializeDisplayBridge?.()
            } catch {
                // fout afhandelen hieronder
            }

            if (!posStore?.displaySyncReady) {
                this.error = posStore?.displaySyncError ?? 'De koppeling met de display kon niet geïnitialiseerd worden.'
                return false
            }

            if (!posStore?.posDevice?.display_device_id || !posStore?.posDevice?.device_uuid) {
                this.error = 'Er is geen display gekoppeld aan deze frontdesk. Nieuw lid via display is daarom niet mogelijk.'
                return false
            }

            const payload = {
                member_registration: {
                    step: 1,
                    submitted: false,
                    success: false,
                    templates: this.memberBadgeTemplates ?? [],
                    defaults: {
                        type: 'adult',
                        badge_template_id: this.memberBadgeTemplates.find(template => template.is_default)?.id ?? this.memberBadgeTemplates[0]?.id ?? null,
                    },
                },
            }

            try {
                await axios.post('/api/frontdesk/display/sync', {
                    device_uuid: posStore.posDevice.device_uuid,
                    device_token: posStore.posDevice.device_token,
                    mode: 'member_registration',
                    reservation_id: null,
                    registration_id: null,
                    payload,
                })

                if (typeof posStore?.pushDisplayOverride === 'function') {
                    posStore.displayModeOverride = {
                        mode: 'member_registration',
                        payload,
                        reservationId: null,
                    }
                    posStore.displayModeOverrideExpiresAt = Date.now() + (10 * 60 * 1000)
                }

                return true
            } catch (error) {
                console.error('Failed to open member wizard on display', error)
                this.error = error?.response?.data?.message ?? 'Wizard op display openen mislukt.'
                return false
            }
        },

        async saveMember(payload) {
            this.saving = true
            this.error = null

            try {
                let response

                const normalizedPayload = {
                    ...payload,
                    login: payload.email || payload.login || '',
                }

                if (payload.id) {
                    response = await axios.put(`/api/frontdesk/members/${payload.id}`, normalizedPayload)
                } else {
                    response = await axios.post('/api/frontdesk/members', normalizedPayload)
                }

                const savedId = response?.data?.data?.id ?? payload.id ?? null

                await this.fetchMembers()

                if (savedId) {
                    this.selectedMemberId = savedId
                }

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
                this.selectedMemberId = memberId
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
                this.selectedMemberId = memberId
                return true
            } catch (error) {
                console.error('Failed to send member email', error)
                this.error = error?.response?.data?.message ?? 'Mail verzenden mislukt.'
                return false
            }
        },
    },
})
