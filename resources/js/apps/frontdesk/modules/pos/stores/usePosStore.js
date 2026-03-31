import { defineStore } from 'pinia'
import axios from 'axios'

function generateLineId() {
    if (typeof crypto !== 'undefined' && crypto.randomUUID) {
        return crypto.randomUUID()
    }

    return `line_${Date.now()}_${Math.random().toString(36).slice(2, 10)}`
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
                price_incl_vat: Number(item.price_incl_vat ?? item.price ?? 0),
                quantity: Number(item.quantity ?? 0),
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

        statsReservations(state) {
            let items = [...state.reservations]

            if (state.reservationViewMode === 'today') {
                const today = todayString()
                items = items.filter(reservation => reservation.event_date === today)
            }

            if (state.reservationViewMode === 'date') {
                const selectedDate = state.reservationSelectedDate || todayString()
                items = items.filter(reservation => reservation.event_date === selectedDate)
            }

            if (state.reservationViewMode === 'open') {
                items = items.filter(reservation =>
                    ['new', 'confirmed'].includes(reservation.status)
                )
            }

            return items
        },

        reservationStats() {
            const items = this.statsReservations

            const totalReservations = items.length
            const totalPersons = items.reduce((sum, r) => sum + Number(r.total_count ?? 0), 0)

            const newItems = items.filter(r => r.status === 'new')
            const confirmed = items.filter(r => r.status === 'confirmed')
            const checkedIn = items.filter(r => r.status === 'checked_in')
            const checkedOut = items.filter(r => r.status === 'checked_out')
            const paid = items.filter(r => r.status === 'paid')
            const cancelled = items.filter(r => r.status === 'cancelled')
            const noShow = items.filter(r => r.status === 'no_show')

            const sumPersons = (rows) => rows.reduce((sum, r) => sum + Number(r.total_count ?? 0), 0)

            return {
                totalReservations,
                totalPersons,
                newReservations: newItems.length,
                newPersons: sumPersons(newItems),
                confirmedReservations: confirmed.length,
                confirmedPersons: sumPersons(confirmed),
                checkedInReservations: checkedIn.length,
                checkedInPersons: sumPersons(checkedIn),
                checkedOutReservations: checkedOut.length,
                checkedOutPersons: sumPersons(checkedOut),
                paidReservations: paid.length,
                paidPersons: sumPersons(paid),
                cancelledReservations: cancelled.length,
                cancelledPersons: sumPersons(cancelled),
                noShowReservations: noShow.length,
                noShowPersons: sumPersons(noShow),
                openReservations: newItems.length + confirmed.length,
                openPersons: sumPersons(newItems) + sumPersons(confirmed),
            }
        },
    },

    actions: {
        async loadCatalog() {
            this.loadingCatalog = true

            try {
                const [categoriesResponse, productsResponse] = await Promise.all([
                    axios.get('/api/backoffice/product-categories'),
                    axios.get('/api/backoffice/products'),
                ])

                this.categories = categoriesResponse.data
                this.products = productsResponse.data

                if (
                    this.selectedCategoryId === null &&
                    Array.isArray(this.categories) &&
                    this.categories.length > 0
                ) {
                    this.selectedCategoryId = this.categories[0].id
                }
            } finally {
                this.loadingCatalog = false
            }
        },

        async fetchReservations() {
            this.loadingReservations = true

            try {
                const response = await axios.get('/api/frontdesk/registrations')
                this.reservations = response.data.data ?? []
            } catch (error) {
                console.error('Failed to fetch reservations', error)
            } finally {
                this.loadingReservations = false
            }
        },

        async fetchOrders() {
            try {
                const response = await axios.get('/api/frontdesk/orders')
                const orders = response.data?.data ?? []

                this.reservationOrders = {}
                this.walkInOrder = createEmptyOrder('walk_in', null)

                orders.forEach(order => {
                    if (order.context === 'reservation' && order.reservation_id) {
                        this.reservationOrders[order.reservation_id] = cloneOrder(order)
                    } else if (order.context === 'walk_in') {
                        this.walkInOrder = cloneOrder(order)
                    }
                })

                if (this.selectedReservationId) {
                    const selectedReservationOrder = this.reservationOrders[this.selectedReservationId] ?? null
                    this.selectedOrderId = selectedReservationOrder?.id ?? null
                } else {
                    this.selectedOrderId = this.walkInOrder?.id ?? null
                }
            } catch (error) {
                console.error('Failed to fetch orders', error)
            }
        },

        addReservation(reservation) {
            this.reservations.unshift(reservation)
        },

        updateReservation(updatedReservation) {
            const index = this.reservations.findIndex(item => item.id === updatedReservation.id)

            if (index === -1) {
                this.reservations.unshift(updatedReservation)
                return
            }

            this.reservations[index] = updatedReservation
        },

        removeReservation(id) {
            this.reservations = this.reservations.filter(item => item.id !== id)

            if (this.selectedReservationId === id) {
                this.selectedReservationId = null
                this.selectedOrderId = null
            }

            if (this.reservationOrders[id]) {
                delete this.reservationOrders[id]
            }
        },

        selectCategory(categoryId) {
            this.selectedCategoryId = categoryId
        },

        ensureReservationOrder(reservationId) {
            if (!this.reservationOrders[reservationId]) {
                this.reservationOrders[reservationId] = createEmptyOrder('reservation', reservationId)
            }

            return this.reservationOrders[reservationId]
        },

        getMutableCurrentOrder() {
            if (this.selectedReservationId) {
                return this.ensureReservationOrder(this.selectedReservationId)
            }

            if (!this.walkInOrder) {
                this.walkInOrder = createEmptyOrder('walk_in', null)
            }

            return this.walkInOrder
        },

        upsertOrder(order) {
            const normalized = cloneOrder(order)

            if (normalized.context === 'reservation' && normalized.reservation_id) {
                this.reservationOrders[normalized.reservation_id] = normalized

                if (this.selectedReservationId === normalized.reservation_id) {
                    this.selectedOrderId = normalized.id ?? null
                }

                return
            }

            this.walkInOrder = normalized

            if (!this.selectedReservationId) {
                this.selectedOrderId = normalized.id ?? null
            }
        },

        setSelectedOrderId(id) {
            this.selectedOrderId = id
        },

        async persistAddProduct(product) {
            const response = await axios.post('/api/frontdesk/orders/items', {
                reservation_id: this.selectedReservationId,
                product_id: Number(product.id),
                quantity: 1,
            })

            const savedOrder = response.data?.data ?? null

            if (!savedOrder) {
                return null
            }

            this.upsertOrder(savedOrder)

            const savedItems = savedOrder.items ?? []
            const matchingItems = savedItems.filter(item => Number(item.product_id) === Number(product.id) && (item.source ?? 'manual') === 'manual')
            const lastItem = matchingItems.length ? matchingItems[matchingItems.length - 1] : savedItems[savedItems.length - 1] ?? null
            this.lastAddedLineId = lastItem?.line_id ?? null

            return savedOrder
        },

        async addProduct(product) {
            this.checkoutError = null

            try {
                await this.persistAddProduct(product)
            } catch (error) {
                console.error('Failed to add product to order', error)
                this.checkoutError = error?.response?.data?.message ?? 'Product toevoegen mislukt.'
            }
        },

        async increaseItem(lineId) {
            const order = this.getMutableCurrentOrder()
            const item = order.items.find(entry => entry.line_id === lineId)

            if (!item || !order?.id || !item?.id) {
                return
            }

            this.checkoutError = null

            try {
                const response = await axios.patch(`/api/frontdesk/orders/${order.id}/items/${item.id}`, {
                    quantity: Number(item.quantity) + 1,
                })

                const savedOrder = response.data?.data ?? null

                if (!savedOrder) {
                    return
                }

                this.upsertOrder(savedOrder)
                this.lastAddedLineId = lineId
            } catch (error) {
                console.error('Failed to increase order item', error)
                this.checkoutError = error?.response?.data?.message ?? 'Aantal verhogen mislukt.'
            }
        },

        async decreaseItem(lineId) {
            const order = this.getMutableCurrentOrder()
            const item = order.items.find(entry => entry.line_id === lineId)

            if (!item || !order?.id || !item?.id) {
                return
            }

            this.checkoutError = null

            if (Number(item.quantity) <= 1) {
                await this.removeItem(lineId)
                return
            }

            try {
                const response = await axios.patch(`/api/frontdesk/orders/${order.id}/items/${item.id}`, {
                    quantity: Number(item.quantity) - 1,
                })

                const savedOrder = response.data?.data ?? null

                if (!savedOrder) {
                    return
                }

                this.upsertOrder(savedOrder)
                this.lastAddedLineId = lineId
            } catch (error) {
                console.error('Failed to decrease order item', error)
                this.checkoutError = error?.response?.data?.message ?? 'Aantal verlagen mislukt.'
            }
        },

        async removeItem(lineId) {
            const order = this.getMutableCurrentOrder()
            const item = order.items.find(entry => entry.line_id === lineId)

            if (!item || !order?.id || !item?.id) {
                return
            }

            this.checkoutError = null

            try {
                const response = await axios.delete(`/api/frontdesk/orders/${order.id}/items/${item.id}`)
                const savedOrder = response.data?.data ?? null

                if (!savedOrder) {
                    return
                }

                this.upsertOrder(savedOrder)

                if (this.lastAddedLineId === lineId) {
                    const currentItems = savedOrder.items ?? []
                    this.lastAddedLineId = currentItems.length
                        ? currentItems[currentItems.length - 1].line_id
                        : null
                }
            } catch (error) {
                console.error('Failed to remove order item', error)
                this.checkoutError = error?.response?.data?.message ?? 'Item verwijderen mislukt.'
            }
        },


        async applyVoucher(code) {
            this.checkoutError = null

            try {
                const response = await axios.post('/api/frontdesk/vouchers/validate', {
                    code,
                    reservation_id: this.selectedReservationId,
                })

                const order = response.data?.data?.order ?? null
                const voucher = response.data?.data?.voucher ?? null

                if (order) {
                    this.upsertOrder(order)
                }

                if (voucher) {
                    const exists = this.appliedVouchers.find(item => item.id === voucher.id)
                    if (!exists) {
                        this.appliedVouchers.push(voucher)
                    }
                }

                return voucher
            } catch (error) {
                console.error('Failed to apply voucher', error)
                this.checkoutError = error?.response?.data?.message ?? 'Cadeaubon valideren mislukt.'
                return null
            }
        },

        async clearOrder() {
            const order = this.currentOrder
            const items = [...(order?.items ?? [])]

            this.checkoutError = null
            this.lastAddedLineId = null
            this.appliedVouchers = []

            if (!items.length) {
                return
            }

            for (const item of items) {
                if (!item?.line_id) {
                    continue
                }

                // eslint-disable-next-line no-await-in-loop
                await this.removeItem(item.line_id)
            }
        },

        replaceWalkInOrder(order) {
            this.walkInOrder = cloneOrder({
                ...order,
                context: 'walk_in',
                reservation_id: null,
            })

            if (this.walkInOrder.id) {
                this.selectedOrderId = this.walkInOrder.id
            }
        },

        replaceReservationOrder(reservationId, order) {
            this.reservationOrders[reservationId] = cloneOrder({
                ...order,
                context: 'reservation',
                reservation_id: reservationId,
            })

            if (this.reservationOrders[reservationId]?.id) {
                this.selectedOrderId = this.reservationOrders[reservationId].id
            }
        },

        selectReservation(id) {
            this.selectedReservationId = id

            if (id) {
                const order = this.ensureReservationOrder(id)

                if (order?.id) {
                    this.selectedOrderId = order.id
                } else {
                    this.selectedOrderId = null
                }
            } else {
                this.selectedOrderId = this.walkInOrder?.id ?? null
            }

            this.lastAddedLineId = null
            this.checkoutError = null
            this.appliedVouchers = []
        },

        clearReservationSelection() {
            this.selectedReservationId = null
            this.selectedOrderId = this.walkInOrder?.id ?? null
            this.lastAddedLineId = null
            this.checkoutError = null
            this.appliedVouchers = []
        },

        setReservationSearch(value) {
            this.reservationSearch = value
        },

        setReservationSelectedDate(value) {
            this.reservationSelectedDate = value
        },

        setReservationViewMode(mode) {
            this.reservationViewMode = mode

            if (mode === 'today') {
                this.reservationSelectedDate = todayString()
                this.resetReservationStatusFilters()
            }

            if (mode === 'open') {
                this.applyOpenReservationFilters()
            }

            if (mode === 'date') {
                this.resetReservationStatusFilters()
            }
        },

        toggleReservationStatusFilter(status) {
            this.reservationStatusFilters[status] = !this.reservationStatusFilters[status]
        },

        resetReservationStatusFilters() {
            this.reservationStatusFilters = {
                new: true,
                confirmed: true,
                checked_in: true,
                checked_out: true,
                paid: false,
                cancelled: false,
                no_show: false,
            }
        },

        applyOpenReservationFilters() {
            this.reservationStatusFilters = {
                new: true,
                confirmed: true,
                checked_in: false,
                checked_out: false,
                paid: false,
                cancelled: false,
                no_show: false,
            }
        },

        async checkoutCurrentOrder(payload = {}) {
            const order = this.currentOrder
            const items = (order?.items ?? [])
                .filter(item => Number(item.quantity) > 0)
                .map(item => ({
                    product_id: Number(item.product_id),
                    quantity: Number(item.quantity),
                }))

            if (!items.length) {
                this.checkoutError = 'Voeg eerst minstens één product toe aan de bestelling.'
                return null
            }

            this.checkoutProcessing = true
            this.checkoutError = null

            try {
                const response = await axios.post('/api/frontdesk/orders/checkout', {
                    order_id: order?.id ?? null,
                    reservation_id: this.selectedReservationId,
                    payment_method: payload.payment_method ?? 'cash',
                    notes: payload.notes ?? null,
                    invoice_requested: payload.invoice_requested ?? false,
                    items,
                })

                const checkoutData = response.data?.data ?? null
                this.lastCheckoutSummary = checkoutData

                if (this.selectedReservationId && this.selectedReservation) {
                    this.updateReservation({
                        ...this.selectedReservation,
                        status: 'paid',
                    })
                }

                if (this.selectedReservationId) {
                    this.reservationOrders[this.selectedReservationId] = createEmptyOrder(
                        'reservation',
                        this.selectedReservationId
                    )
                    this.selectedOrderId = null
                } else {
                    this.walkInOrder = createEmptyOrder('walk_in', null)
                    this.selectedOrderId = null
                }

                this.lastAddedLineId = null
                this.appliedVouchers = []
                return checkoutData
            } catch (error) {
                this.checkoutError = error?.response?.data?.message ?? 'Afrekenen mislukt.'
                return null
            } finally {
                this.checkoutProcessing = false
            }
        },
    },
})
