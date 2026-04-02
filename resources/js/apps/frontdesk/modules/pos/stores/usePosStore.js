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

function createEmptyOrder(context = 'walk_in', registrationId = null) {
    return {
        id: null,
        context,
        registration_id: registrationId,
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
        registration_id: order?.registration_id ?? order?.reservation_id ?? null,
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
            const totalPersons = items.reduce((sum, r) => sum + Number(r.total_count ?? r.total_participants ?? 0), 0)

            const newItems = items.filter(r => r.status === 'new')
            const confirmed = items.filter(r => r.status === 'confirmed')
            const checkedIn = items.filter(r => r.status === 'checked_in')
            const checkedOut = items.filter(r => r.status === 'checked_out')
            const paid = items.filter(r => r.status === 'paid')
            const cancelled = items.filter(r => r.status === 'cancelled')
            const noShow = items.filter(r => r.status === 'no_show')

            const sumPersons = (rows) => rows.reduce((sum, r) => sum + Number(r.total_count ?? r.total_participants ?? 0), 0)

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
        async initializeDisplayBridge() {
            try {
                const pairingUuid = new URLSearchParams(window.location.search).get('display')
                const response = await axios.post('/api/display/bootstrap', {
                    role: 'pos',
                    device_uuid: getOrCreatePosDeviceUuid(),
                    device_token: getStoredPosDeviceToken(),
                    pairing_uuid: pairingUuid || null,
                    name: 'Frontdesk POS',
                })

                this.posDevice = response.data?.data ?? null

                if (this.posDevice?.device_token) {
                    storePosDeviceToken(this.posDevice.device_token)
                }

                this.displaySyncReady = true
                this.displaySyncError = null
                await this.syncCustomerDisplay()
            } catch (error) {
                this.displaySyncReady = false
                this.displaySyncError = error?.response?.data?.message ?? 'Displaykoppeling initialiseren mislukt.'
            }
        },

        async syncCustomerDisplay() {
            if (!this.displaySyncReady || !this.posDevice?.display_device_id || !this.posDevice?.device_uuid) {
                return
            }

            const reservation = this.selectedReservation
            const mode = reservation ? 'reservation' : 'standby'

            try {
                await axios.post('/api/frontdesk/display/sync', {
                    device_uuid: this.posDevice.device_uuid,
                    device_token: getStoredPosDeviceToken(),
                    mode,
                    registration_id: reservation?.id ?? null,
                    payload: reservation ? {
                        registration: reservation,
                        order: this.currentOrder,
                    } : {},
                })

                this.displaySyncError = null
            } catch (error) {
                this.displaySyncError = error?.response?.data?.message ?? 'Display synchroniseren mislukt.'
            }
        },

        async loadCatalog() {
            this.loadingCatalog = true

            try {
                const [categoriesResponse, productsResponse] = await Promise.all([
                    axios.get('/api/frontdesk/catalog/product-categories'),
                    axios.get('/api/frontdesk/catalog/products'),
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
                    const registrationId = order.registration_id ?? order.reservation_id ?? null

                    if (order.context === 'reservation' && registrationId) {
                        this.reservationOrders[registrationId] = cloneOrder(order)
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

        ensureReservationOrder(registrationId) {
            if (!this.reservationOrders[registrationId]) {
                this.reservationOrders[registrationId] = createEmptyOrder('reservation', registrationId)
            }

            return this.reservationOrders[registrationId]
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
            const registrationId = normalized.registration_id ?? null

            if (normalized.context === 'reservation' && registrationId) {
                this.reservationOrders[registrationId] = normalized

                if (this.selectedReservationId === registrationId) {
                    this.selectedOrderId = normalized.id ?? null
                }

                this.syncCustomerDisplay()
                return
            }

            this.walkInOrder = normalized

            this.syncCustomerDisplay()

            if (!this.selectedReservationId) {
                this.selectedOrderId = normalized.id ?? null
            }
        },

        setSelectedOrderId(id) {
            this.selectedOrderId = id
        },

        async persistAddProduct(product) {
            const response = await axios.post('/api/frontdesk/orders/items', {
                registration_id: this.selectedReservationId,
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
                    registration_id: this.selectedReservationId,
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

                await this.removeItem(item.line_id)
            }
        },

        replaceWalkInOrder(order) {
            this.walkInOrder = cloneOrder({
                ...order,
                context: 'walk_in',
                registration_id: null,
            })

            if (this.walkInOrder.id) {
                this.selectedOrderId = this.walkInOrder.id
            }
        },

        replaceReservationOrder(registrationId, order) {
            this.reservationOrders[registrationId] = cloneOrder({
                ...order,
                context: 'reservation',
                registration_id: registrationId,
            })

            if (this.reservationOrders[registrationId]?.id) {
                this.selectedOrderId = this.reservationOrders[registrationId].id
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
            this.syncCustomerDisplay()
        },

        clearReservationSelection() {
            this.selectedReservationId = null
            this.selectedOrderId = this.walkInOrder?.id ?? null
            this.lastAddedLineId = null
            this.checkoutError = null
            this.appliedVouchers = []
            this.syncCustomerDisplay()
        },

        setReservationSearch(value) {
            this.reservationSearch = value
        },

        setReservationSelectedDate(value) {
            this.reservationSelectedDate = value
        },

        setReservationViewMode(value) {
            this.reservationViewMode = value
        },

        toggleReservationStatusFilter(status) {
            if (!(status in this.reservationStatusFilters)) {
                return
            }

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

        async checkoutOrder(payload) {
            this.checkoutProcessing = true
            this.checkoutError = null

            try {
                const response = await axios.post('/api/frontdesk/orders/checkout', payload)
                await this.fetchOrders()
                await this.fetchReservations()
                this.appliedVouchers = []
                return response.data?.data ?? response.data
            } catch (error) {
                console.error('Checkout failed', error)
                this.checkoutError = error?.response?.data?.message ?? 'Afrekenen mislukt.'
                throw error
            } finally {
                this.checkoutProcessing = false
            }
        },

        async checkoutCurrentOrder(payload) {
            return this.checkoutOrder(payload)
        },
    },
})
