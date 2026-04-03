import { defineStore } from 'pinia'
import axios from '@/lib/http'

export const useVouchersStore = defineStore('frontdeskVouchers', {
    state: () => ({
        loading: false,
        saving: false,
        error: null,
        search: '',
        statuses: [],
        summary: { total: 0, active: 0, validated: 0, redeemed: 0 },
        vouchers: [],
        selectedVoucherId: null,
    }),

    getters: {
        selectedVoucher(state) {
            return state.vouchers.find(voucher => voucher.id === state.selectedVoucherId) ?? null
        },
    },

    actions: {
        async fetchVouchers() {
            this.loading = true
            this.error = null

            try {
                const response = await axios.get('/api/frontdesk/vouchers', {
                    params: {
                        search: this.search || undefined,
                        statuses: this.statuses.length ? this.statuses : undefined,
                    },
                })

                this.summary = response.data?.data?.summary ?? this.summary
                this.vouchers = response.data?.data?.vouchers ?? []
                this.selectedVoucherId = this.vouchers.find(voucher => voucher.id === this.selectedVoucherId)?.id ?? this.vouchers[0]?.id ?? null
            } catch (error) {
                console.error('Failed to fetch vouchers', error)
                this.error = error?.response?.data?.message ?? 'Cadeaubonnen konden niet geladen worden.'
                this.vouchers = []
            } finally {
                this.loading = false
            }
        },

        async saveVoucher(payload) {
            this.saving = true
            this.error = null

            try {
                if (payload.id) {
                    await axios.put(`/api/frontdesk/vouchers/${payload.id}`, payload)
                } else {
                    await axios.post('/api/frontdesk/vouchers', payload)
                }

                await this.fetchVouchers()
                return true
            } catch (error) {
                console.error('Failed to save voucher', error)
                this.error = error?.response?.data?.message ?? 'Cadeaubon opslaan mislukt.'
                throw error
            } finally {
                this.saving = false
            }
        },
    },
})
