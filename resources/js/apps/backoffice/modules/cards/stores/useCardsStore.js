import { defineStore } from 'pinia'
import axios from '@/lib/http'

export const useCardsStore = defineStore('backofficeCards', {
    state: () => ({
        loading: false,
        saving: false,
        printing: false,
        error: null,
        search: '',
        statuses: [],
        voucherTemplateId: '',
        summary: {
            total: 0,
            stock: 0,
            in_circulation: 0,
            returned: 0,
            blocked: 0,
        },
        voucherTemplates: [],
        cards: [],
        selectedCardId: null,
    }),

    getters: {
        selectedCard(state) {
            return state.cards.find(card => card.id === state.selectedCardId) ?? null
        },
    },

    actions: {
        async fetchCards() {
            this.loading = true
            this.error = null

            try {
                const response = await axios.get('/api/backoffice/cards', {
                    params: {
                        search: this.search || undefined,
                        statuses: this.statuses.length ? this.statuses : undefined,
                        voucher_template_id: this.voucherTemplateId || undefined,
                    },
                })

                this.summary = response.data?.data?.summary ?? this.summary
                this.voucherTemplates = response.data?.data?.voucher_templates ?? []
                this.cards = response.data?.data?.cards ?? []
                this.selectedCardId = this.cards.find(card => card.id === this.selectedCardId)?.id ?? this.cards[0]?.id ?? null
            } catch (error) {
                console.error('Failed to fetch cards', error)
                this.error = error?.response?.data?.message ?? 'Kaarten konden niet geladen worden.'
                this.cards = []
            } finally {
                this.loading = false
            }
        },

        selectCard(cardId) {
            this.selectedCardId = cardId
        },

        async saveCard(payload) {
            this.saving = true
            this.error = null

            try {
                if (payload.id) {
                    await axios.put(`/api/backoffice/cards/${payload.id}`, payload)
                } else {
                    await axios.post('/api/backoffice/cards', payload)
                }

                await this.fetchCards()
                return true
            } catch (error) {
                console.error('Failed to save card', error)
                this.error = error?.response?.data?.message ?? 'Kaart opslaan mislukt.'
                throw error
            } finally {
                this.saving = false
            }
        },

        async printCard(cardId) {
            if (!cardId || this.printing) {
                return false
            }

            this.printing = true
            this.error = null

            const printWindow = typeof window !== 'undefined' ? window.open('', '_blank', 'noopener,noreferrer') : null

            try {
                const response = await axios.post(`/api/backoffice/cards/${cardId}/mark-printed`)
                const printUrl = response.data?.data?.print_url || `/backoffice/cards/${cardId}/print`

                if (printWindow) {
                    printWindow.location.href = printUrl
                } else if (typeof window !== 'undefined') {
                    window.open(printUrl, '_blank', 'noopener,noreferrer')
                }

                await this.fetchCards()
                return true
            } catch (error) {
                console.error('Failed to print card', error)
                this.error = error?.response?.data?.message ?? 'Kaart afdrukken mislukt.'

                if (printWindow && !printWindow.closed) {
                    printWindow.close()
                }

                throw error
            } finally {
                this.printing = false
            }
        },
    },
})
