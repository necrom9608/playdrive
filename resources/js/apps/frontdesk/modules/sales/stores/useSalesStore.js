import { defineStore } from 'pinia'
import axios from '@/lib/http'

function todayString() {
    const now = new Date()
    const year = now.getFullYear()
    const month = String(now.getMonth() + 1).padStart(2, '0')
    const day = String(now.getDate()).padStart(2, '0')
    return `${year}-${month}-${day}`
}

export const useSalesStore = defineStore('sales', {
    state: () => ({
        loading: false,
        error: null,

        selectedDate: todayString(),
        invoiceFilter: 'all', // all | yes | no
        paymentMethodFilter: 'all', // all | cash | bancontact
        sourceFilter: 'all', // all | walk_in | reservation

        summary: null,
        orders: [],
        selectedOrder: null,
    }),

    getters: {
        hasOrders(state) {
            return state.orders.length > 0
        },
    },

    actions: {
        async fetchSales() {
            this.loading = true
            this.error = null

            try {
                const params = {
                    date: this.selectedDate,
                }

                if (this.invoiceFilter === 'yes') {
                    params.invoice_requested = 1
                } else if (this.invoiceFilter === 'no') {
                    params.invoice_requested = 0
                }

                if (this.paymentMethodFilter !== 'all') {
                    params.payment_method = this.paymentMethodFilter
                }

                if (this.sourceFilter !== 'all') {
                    params.source = this.sourceFilter
                }

                const response = await axios.get('/api/frontdesk/sales', { params })

                this.summary = response.data?.data?.summary ?? null
                this.orders = response.data?.data?.orders ?? []

                if (this.orders.length) {
                    const currentSelectedId = this.selectedOrder?.id
                    this.selectedOrder =
                        this.orders.find(order => order.id === currentSelectedId)
                        ?? this.orders[0]
                } else {
                    this.selectedOrder = null
                }
            } catch (error) {
                console.error('Failed to fetch sales', error)
                this.error = error?.response?.data?.message ?? 'Verkopen konden niet geladen worden.'
                this.summary = null
                this.orders = []
                this.selectedOrder = null
            } finally {
                this.loading = false
            }
        },

        selectOrder(orderId) {
            this.selectedOrder = this.orders.find(order => order.id === orderId) ?? null
        },

        setSelectedDate(value) {
            this.selectedDate = value
            this.fetchSales()
        },

        setInvoiceFilter(value) {
            this.invoiceFilter = value
            this.fetchSales()
        },

        setPaymentMethodFilter(value) {
            this.paymentMethodFilter = value
            this.fetchSales()
        },

        setSourceFilter(value) {
            this.sourceFilter = value
            this.fetchSales()
        },

        resetFilters() {
            this.selectedDate = todayString()
            this.invoiceFilter = 'all'
            this.paymentMethodFilter = 'all'
            this.sourceFilter = 'all'
            this.fetchSales()
        },

        async cancelSelectedOrder(reason = '') {
            if (!this.selectedOrder?.id) {
                return null
            }

            try {
                await axios.post(`/api/frontdesk/orders/${this.selectedOrder.id}/cancel`, {
                    reason,
                })

                const currentId = this.selectedOrder.id
                await this.fetchSales()
                this.selectedOrder = this.orders.find(order => order.id === currentId) ?? this.orders[0] ?? null
                return true
            } catch (error) {
                console.error('Failed to cancel order', error)
                this.error = error?.response?.data?.message ?? 'Order annuleren mislukt.'
                return false
            }
        },

        async refundSelectedOrder(payload = {}) {
            if (!this.selectedOrder?.id) {
                return null
            }

            try {
                await axios.post(`/api/frontdesk/orders/${this.selectedOrder.id}/refund`, {
                    reason: payload.reason ?? '',
                    refund_method: payload.refund_method ?? 'cash',
                })

                const currentId = this.selectedOrder.id
                await this.fetchSales()
                this.selectedOrder = this.orders.find(order => order.id === currentId) ?? this.orders[0] ?? null
                return true
            } catch (error) {
                console.error('Failed to refund order', error)
                this.error = error?.response?.data?.message ?? 'Terugbetaling registreren mislukt.'
                return false
            }
        },
    },
})
