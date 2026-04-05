import { defineStore } from 'pinia'
import axios from '@/lib/http'

export const useCardsStore = defineStore('frontdeskCards', {
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
                const response = await axios.get('/api/frontdesk/cards', {
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
                    await axios.put(`/api/frontdesk/cards/${payload.id}`, payload)
                } else {
                    await axios.post('/api/frontdesk/cards', payload)
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

            const printWindow = typeof window !== 'undefined' ? window.open('about:blank', '_blank') : null

            if (printWindow?.document) {
                printWindow.document.open()
                printWindow.document.write(`<!DOCTYPE html>
<html lang="nl">
<head>
    <meta charset="utf-8">
    <title>Kaart voorbereiden...</title>
    <style>
        body {
            margin: 0;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            background: #0f172a;
            color: #e2e8f0;
            font-family: Arial, Helvetica, sans-serif;
        }
        .card {
            padding: 18px 22px;
            border-radius: 18px;
            border: 1px solid rgba(148, 163, 184, 0.25);
            background: rgba(15, 23, 42, 0.88);
            box-shadow: 0 16px 48px rgba(15, 23, 42, 0.45);
        }
    </style>
</head>
<body>
    <div class="card">Printweergave wordt voorbereid...</div>
</body>
</html>`)
                printWindow.document.close()
            }

            try {
                const response = await axios.post(`/api/frontdesk/cards/${cardId}/mark-printed`)
                const printUrl = response.data?.data?.print_url || `/frontdesk/cards/${cardId}/print`

                if (printWindow && !printWindow.closed) {
                    printWindow.location.replace(printUrl)
                } else if (typeof window !== 'undefined') {
                    window.open(printUrl, '_blank')
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
