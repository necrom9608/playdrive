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
    }
}

function cloneOrder(order) {
    return {
        id: order?.id ?? null,
        context: order?.context ?? 'walk_in',
        reservation_id: order?.reservation_id ?? null,
        items: Array.isArray(order?.items)
            ? order.items.map(item => ({
                line_id: item.line_id ?? generateLineId(),
                product_id: item.product_id ?? item.id,
                name: item.name,
                price_incl_vat: Number(item.price_incl_vat ?? item.price ?? 0),
                quantity: Number(item.quantity ?? 0),
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

        currentOrder(state) {
            if (state.selectedReservationId) {
                return state.reservationOrders[state.selectedReservationId]
                    ?? createEmptyOrder('reservation', state.selectedReservationId)
            }

            return state.walkInOrder
        },

        currentOrderItems() {
            return this.currentOrder.items
        },

        orderSubtotal() {
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

        addProduct(product) {
            const order = this.getMutableCurrentOrder()
            const items = order.items
            const lastItem = items.length ? items[items.length - 1] : null

            if (lastItem && Number(lastItem.product_id) === Number(product.id)) {
                lastItem.quantity += 1
                this.lastAddedLineId = lastItem.line_id
                return
            }

            const newLine = {
                line_id: generateLineId(),
                product_id: product.id,
                name: product.name,
                price_incl_vat: Number(product.price_incl_vat ?? product.price ?? 0),
                quantity: 1,
            }

            items.push(newLine)
            this.lastAddedLineId = newLine.line_id
        },

        increaseItem(lineId) {
            const order = this.getMutableCurrentOrder()
            const item = order.items.find(entry => entry.line_id === lineId)

            if (item) {
                item.quantity += 1
                this.lastAddedLineId = item.line_id
            }
        },

        decreaseItem(lineId) {
            const order = this.getMutableCurrentOrder()
            const item = order.items.find(entry => entry.line_id === lineId)

            if (!item) {
                return
            }

            item.quantity -= 1
            this.lastAddedLineId = item.line_id

            if (item.quantity <= 0) {
                this.removeItem(lineId)
            }
        },

        removeItem(lineId) {
            const order = this.getMutableCurrentOrder()
            order.items = order.items.filter(item => item.line_id !== lineId)

            if (this.lastAddedLineId === lineId) {
                this.lastAddedLineId = order.items.length
                    ? order.items[order.items.length - 1].line_id
                    : null
            }
        },

        clearOrder() {
            this.lastAddedLineId = null
            this.checkoutError = null

            if (this.selectedReservationId) {
                this.reservationOrders[this.selectedReservationId] = createEmptyOrder(
                    'reservation',
                    this.selectedReservationId
                )
                return
            }

            this.walkInOrder = createEmptyOrder('walk_in', null)
        },

        replaceWalkInOrder(order) {
            this.walkInOrder = cloneOrder({
                ...order,
                context: 'walk_in',
                reservation_id: null,
            })
        },

        replaceReservationOrder(reservationId, order) {
            this.reservationOrders[reservationId] = cloneOrder({
                ...order,
                context: 'reservation',
                reservation_id: reservationId,
            })
        },

        selectReservation(id) {
            this.selectedReservationId = id

            if (id) {
                this.ensureReservationOrder(id)
            }

            this.lastAddedLineId = null
            this.checkoutError = null
        },

        clearReservationSelection() {
            this.selectedReservationId = null
            this.lastAddedLineId = null
            this.checkoutError = null
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
                    reservation_id: this.selectedReservationId,
                    payment_method: payload.payment_method ?? 'cash',
                    notes: payload.notes ?? null,
                    invoice_requested: payload.invoice_requested ?? false,
                    items,
                })

                this.lastCheckoutSummary = response.data?.data ?? null

                if (this.selectedReservationId && this.selectedReservation) {
                    this.updateReservation({
                        ...this.selectedReservation,
                        status: 'paid',
                    })
                }

                this.clearOrder()
                this.lastCheckoutSummary = response.data?.data ?? null
                return response.data?.data ?? null
            } catch (error) {
                this.checkoutError = error?.response?.data?.message ?? 'Afrekenen mislukt.'
                return null
            } finally {
                this.checkoutProcessing = false
            }
        },
    },
})
