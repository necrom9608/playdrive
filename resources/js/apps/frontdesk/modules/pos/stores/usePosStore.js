import { defineStore } from 'pinia'
import axios from 'axios'

const POS_UUID_KEY = 'playdrive_pos_device_uuid'
const POS_TOKEN_KEY = 'playdrive_pos_device_token'

function generateUuid() {
    if (typeof window !== 'undefined' && window.crypto && typeof window.crypto.randomUUID === 'function') {
        return window.crypto.randomUUID()
    }

    if (typeof window !== 'undefined' && window.crypto && typeof window.crypto.getRandomValues === 'function') {
        const bytes = new Uint8Array(16)
        window.crypto.getRandomValues(bytes)

        bytes[6] = (bytes[6] & 0x0f) | 0x40
        bytes[8] = (bytes[8] & 0x3f) | 0x80

        const hex = Array.from(bytes, byte => byte.toString(16).padStart(2, '0'))

        return [
            hex.slice(0, 4).join(''),
            hex.slice(4, 6).join(''),
            hex.slice(6, 8).join(''),
            hex.slice(8, 10).join(''),
            hex.slice(10, 16).join(''),
        ].join('-')
    }

    return 'xxxxxxxx-xxxx-4xxx-yxxx-xxxxxxxxxxxx'.replace(/[xy]/g, (char) => {
        const random = Math.floor(Math.random() * 16)
        const value = char === 'x' ? random : ((random & 0x3) | 0x8)
        return value.toString(16)
    })
}

function getOrCreatePosDeviceUuid() {
    let value = localStorage.getItem(POS_UUID_KEY)

    if (!value) {
        value = generateUuid()
        localStorage.setItem(POS_UUID_KEY, value)
    }

    return value
}

function getStoredPosDeviceToken() {
    return localStorage.getItem(POS_TOKEN_KEY)
}

function storePosDeviceToken(token) {
    localStorage.setItem(POS_TOKEN_KEY, token)
}

function generateLineId() {
    return generateUuid()
}

function todayString() {
    const now = new Date()
    const year = now.getFullYear()
    const month = String(now.getMonth() + 1).padStart(2, '0')
    const day = String(now.getDate()).padStart(2, '0')

    return `${year}-${month}-${day}`
}

function createEmptyOrder(context = 'walk_in', reservationId = null) {
    return {
        id: null,
        context,
        reservation_id: reservationId,
        items: [],
        subtotal_excl_vat: 0,
        total_vat: 0,
        total_incl_vat: 0,
    }
}

function cloneOrder(order) {
    return {
        id: order?.id ?? null,
        context: order?.context ?? 'walk_in',
        reservation_id: order?.reservation_id ?? null,
        subtotal_excl_vat: Number(order?.subtotal_excl_vat ?? 0),
        total_vat: Number(order?.total_vat ?? 0),
        total_incl_vat: Number(order?.total_incl_vat ?? 0),
        items: Array.isArray(order?.items)
            ? order.items.map(item => ({
                id: item.id ?? null,
                line_id: item.line_id ?? generateLineId(),
                product_id: item.product_id ?? item.id,
                name: item.name,
                price_incl_vat: Number(item.price_incl_vat ?? item.price ?? item.unit_price_incl_vat ?? 0),
                quantity: Number(item.quantity ?? 0),
                line_total_incl_vat: Number(item.line_total_incl_vat ?? 0),
                source: item.source ?? 'manual',
                source_reference: item.source_reference ?? null,
            }))
            : [],
    }
}

export const usePosStore = defineStore('pos', {
    state: () => ({
        categories: [],
        products: [],
        selectedCategoryId: null,
        loadingCatalog: false,

        reservations: [],
        loadingReservations: false,

        selectedReservationId: null,
        selectedOrderId: null,

        reservationSearch: '',
        reservationViewMode: 'today',
        reservationSelectedDate: todayString(),
        reservationStatusFilters: {
            new: true,
            confirmed: true,
            checked_in: true,
            checked_out: true,
            paid: false,
            cancelled: false,
            no_show: false,
        },

        walkInOrder: createEmptyOrder('walk_in', null),
        reservationOrders: {},
        lastAddedLineId: null,

        checkoutProcessing: false,
        checkoutError: null,
        lastCheckoutSummary: null,
        appliedVouchers: [],

        posDevice: null,
        displaySyncReady: false,
        displaySyncError: null,
    }),

    getters: {
        filteredProducts(state) {
            if (!state.selectedCategoryId) {
                return state.products
            }

            return state.products.filter(
                product => product.product_category_id === state.selectedCategoryId
            )
        },

        selectedReservation(state) {
            return state.reservations.find(r => r.id === state.selectedReservationId) ?? null
        },

        selectedOrder(state) {
            if (!state.selectedOrderId) {
                return null
            }

            if (state.selectedReservationId && state.reservationOrders[state.selectedReservationId]) {
                const reservationOrder = state.reservationOrders[state.selectedReservationId]

                if (reservationOrder.id === state.selectedOrderId) {
                    return reservationOrder
                }
            }

            if (state.walkInOrder?.id === state.selectedOrderId) {
                return state.walkInOrder
            }

            return null
        },

        currentOrder(state) {
            if (state.selectedReservationId) {
                const reservationOrder = state.reservationOrders[state.selectedReservationId]

                if (reservationOrder) {
                    return reservationOrder
                }

                return createEmptyOrder('reservation', state.selectedReservationId)
            }

            return state.walkInOrder
        },

        currentOrderItems() {
            return this.currentOrder.items
        },

        orderSubtotal() {
            const backendTotal = Number(this.currentOrder?.total_incl_vat ?? 0)

            if (backendTotal > 0) {
                return backendTotal
            }

            return this.currentOrder.items.reduce((sum, item) => {
                return sum + (Number(item.price_incl_vat) * Number(item.quantity))
            }, 0)
        },

        orderCount() {
            return this.currentOrder.items.reduce((sum, item) => {
                return sum + Number(item.quantity)
            }, 0)
        },

        currentOrderLabel() {
            if (this.selectedReservation) {
                return `Actieve reservatie: ${this.selectedReservation.name}`
            }

            return 'Losse verkoop'
        },

        filteredReservations(state) {
            let items = [...state.reservations]

            if (state.reservationViewMode === 'today') {
                const today = todayString()
                items = items.filter(reservation => reservation.event_date === today)
            }

            if (state.reservationViewMode === 'date') {
                const selectedDate = state.reservationSelectedDate || todayString()
                items = items.filter(reservation => reservation.event_date === selectedDate)
            }

            const activeStatuses = Object.entries(state.reservationStatusFilters)
                .filter(([, enabled]) => enabled)
                .map(([status]) => status)

            items = items.filter(reservation => activeStatuses.includes(reservation.status))

            const q = state.reservationSearch.trim().toLowerCase()

            if (!q) {
                return items
            }

            return items.filter((reservation) => {
                return [
                    reservation.name,
                    reservation.phone,
                    reservation.email,
                    reservation.municipality,
                ]
                    .filter(Boolean)
                    .some(value => String(value).toLowerCase().includes(q))
            })
        },

        todayCounts() {
            const today = todayString()
            const todaysReservations = this.reservations.filter(reservation => reservation.event_date === today)

            return {
                total: todaysReservations.length,
                open: todaysReservations.filter(r => ['new', 'confirmed'].includes(r.status)).length,
                checked_in: todaysReservations.filter(r => r.status === 'checked_in').length,
                checked_out: todaysReservations.filter(r => r.status === 'checked_out').length,
            }
        },
    },

    actions: {
        async loadCatalog() {
            if (this.loadingCatalog) {
                return
            }

            this.loadingCatalog = true

            try {
                const [categoriesResponse, productsResponse] = await Promise.all([
                    axios.get('/api/frontdesk/catalog/product-categories'),
                    axios.get('/api/frontdesk/catalog/products'),
                ])

                this.categories = categoriesResponse.data ?? []
                this.products = productsResponse.data ?? []

                if (!this.selectedCategoryId && this.categories.length > 0) {
                    this.selectedCategoryId = this.categories[0].id
                }
            } finally {
                this.loadingCatalog = false
            }
        },

        async loadReservations() {
            if (this.loadingReservations) {
                return
            }

            this.loadingReservations = true

            try {
                const response = await axios.get('/api/frontdesk/registrations')
                this.reservations = response.data?.data ?? response.data ?? []
            } finally {
                this.loadingReservations = false
            }
        },

        setSelectedCategory(categoryId) {
            this.selectedCategoryId = categoryId
        },

        selectReservation(reservationId) {
            this.selectedReservationId = reservationId

            if (!reservationId) {
                this.selectedOrderId = this.walkInOrder?.id ?? null
                return
            }

            const reservationOrder = this.reservationOrders[reservationId] ?? createEmptyOrder('reservation', reservationId)
            this.reservationOrders = {
                ...this.reservationOrders,
                [reservationId]: reservationOrder,
            }

            this.selectedOrderId = reservationOrder.id
        },

        selectWalkIn() {
            this.selectedReservationId = null
            this.selectedOrderId = this.walkInOrder?.id ?? null
        },

        setReservationViewMode(mode) {
            this.reservationViewMode = mode
        },

        setReservationSelectedDate(value) {
            this.reservationSelectedDate = value
        },

        setReservationSearch(value) {
            this.reservationSearch = value
        },

        toggleReservationStatusFilter(status) {
            this.reservationStatusFilters[status] = !this.reservationStatusFilters[status]
        },

        replaceCurrentOrder(order) {
            const cloned = cloneOrder(order)

            if (cloned.context === 'reservation' && cloned.reservation_id) {
                this.reservationOrders = {
                    ...this.reservationOrders,
                    [cloned.reservation_id]: cloned,
                }
            } else {
                this.walkInOrder = cloned
            }

            this.selectedOrderId = cloned.id
        },

        async addProduct(product) {
            const payload = {
                product_id: product.id,
                reservation_id: this.selectedReservationId,
            }

            const response = await axios.post('/api/frontdesk/orders/items', payload)
            const order = response.data?.order ?? response.data

            this.replaceCurrentOrder(order)
            this.lastAddedLineId = order?.items?.[order.items.length - 1]?.line_id ?? null
        },

        async updateItem(lineItem, quantity) {
            const order = this.currentOrder

            if (!order?.id || !lineItem?.id) {
                return
            }

            const response = await axios.patch(`/api/frontdesk/orders/${order.id}/items/${lineItem.id}`, {
                quantity,
            })

            this.replaceCurrentOrder(response.data?.order ?? response.data)
        },

        async deleteItem(lineItem) {
            const order = this.currentOrder

            if (!order?.id || !lineItem?.id) {
                return
            }

            const response = await axios.delete(`/api/frontdesk/orders/${order.id}/items/${lineItem.id}`)
            this.replaceCurrentOrder(response.data?.order ?? response.data)
        },

        async validateVoucher(code) {
            const response = await axios.post('/api/frontdesk/vouchers/validate', { code })
            return response.data
        },

        async checkout(payload) {
            this.checkoutProcessing = true
            this.checkoutError = null

            try {
                const response = await axios.post('/api/frontdesk/orders/checkout', payload)
                this.lastCheckoutSummary = response.data ?? null
                this.appliedVouchers = []
                return response.data
            } catch (error) {
                this.checkoutError = error?.response?.data?.message ?? 'Afrekenen mislukt.'
                throw error
            } finally {
                this.checkoutProcessing = false
            }
        },

        async bootstrapDisplaySync() {
            try {
                const response = await axios.post('/api/display/bootstrap', {
                    uuid: getOrCreatePosDeviceUuid(),
                    token: getStoredPosDeviceToken(),
                })

                this.posDevice = response.data?.device ?? null

                if (response.data?.token) {
                    storePosDeviceToken(response.data.token)
                }

                this.displaySyncReady = true
                this.displaySyncError = null
            } catch (error) {
                this.displaySyncReady = false
                this.displaySyncError = error?.response?.data?.message ?? 'Display-koppeling mislukt.'
            }
        },

        async syncDisplay(payload = {}) {
            if (!this.displaySyncReady) {
                return
            }

            try {
                await axios.post('/api/frontdesk/display/sync', payload)
            } catch (error) {
                this.displaySyncError = error?.response?.data?.message ?? 'Display sync mislukt.'
            }
        },
    },
})
