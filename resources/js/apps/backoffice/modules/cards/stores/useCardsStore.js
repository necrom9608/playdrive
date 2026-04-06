import { defineStore } from 'pinia'
import axios from '@/lib/http'
import { renderCardToDataUrl } from '../utils/cardRenderer'

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
                const response = payload.id
                    ? await axios.put(`/api/backoffice/cards/${payload.id}`, payload)
                    : await axios.post('/api/backoffice/cards', payload)

                let card = response.data?.data ?? null

                if (card?.render_template && card?.render_fields) {
                    card = await this.uploadRenderImage(card)
                }

                await this.fetchCards()
                this.selectedCardId = card?.id ?? this.selectedCardId
                return true
            } catch (error) {
                console.error('Failed to save card', error)
                this.error = error?.response?.data?.message ?? 'Kaart opslaan mislukt.'
                throw error
            } finally {
                this.saving = false
            }
        },

        async uploadRenderImage(card) {
            const dataUrl = await renderCardToDataUrl({
                template: card.render_template,
                fields: card.render_fields,
                card,
            })

            const response = await axios.post(`/api/backoffice/cards/${card.id}/render-image`, {
                data_url: dataUrl,
            })

            return response.data?.data ?? card
        },

        async ensureRenderImage(card) {
            if (!card) {
                return null
            }

            if (card.preview_image_url) {
                return card
            }

            const updatedCard = await this.uploadRenderImage(card)
            const index = this.cards.findIndex(entry => entry.id === updatedCard.id)
            if (index !== -1) {
                this.cards[index] = updatedCard
            }

            if (this.selectedCardId === updatedCard.id) {
                this.selectedCardId = updatedCard.id
            }

            return updatedCard
        },

        async printCard(cardId) {
            if (!cardId || this.printing) {
                return false
            }

            this.printing = true
            this.error = null

            const printWindow = typeof window !== 'undefined' ? window.open('', '_blank', 'noopener,noreferrer') : null

            try {
                const selected = this.cards.find(card => card.id === cardId) ?? null
                if (selected) {
                    await this.ensureRenderImage(selected)
                }

                const response = await axios.post(`/api/backoffice/cards/${cardId}/mark-printed`)
                const pdfUrl = response.data?.data?.pdf_url || `/api/backoffice/cards/${cardId}/pdf`

                if (printWindow) {
                    printWindow.location.href = pdfUrl
                } else if (typeof window !== 'undefined') {
                    window.open(pdfUrl, '_blank', 'noopener,noreferrer')
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
