import { defineStore } from 'pinia'
import axios from 'axios'

export const usePosStore = defineStore('pos', {
    state: () => ({
        categories: [],
        products: [],
        selectedCategoryId: null,
        orderItems: [],
        loadingCatalog: false,

        reservations: [],
        loadingReservations: false,

        selectedReservationId: null,
        reservationSearch: '',
        reservationViewMode: 'today',
        reservationStatusFilters: {
            new: true,
            confirmed: true,
            checked_in: true,
            checked_out: true,
            paid: false,
            cancelled: false,
            no_show: false,
        },
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

        orderSubtotal(state) {
            return state.orderItems.reduce((sum, item) => {
                return sum + (parseFloat(item.price_incl_vat) * item.quantity)
            }, 0)
        },

        orderCount(state) {
            return state.orderItems.reduce((sum, item) => sum + item.quantity, 0)
        },

        selectedReservation(state) {
            return state.reservations.find(r => r.id === state.selectedReservationId) ?? null
        },

        filteredReservations(state) {
            let items = [...state.reservations]

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

        reservationStats(state) {
            const totalReservations = state.reservations.length
            const totalPersons = state.reservations.reduce((sum, r) => sum + Number(r.total_count ?? 0), 0)

            const confirmed = state.reservations.filter(r => r.status === 'confirmed')
            const checkedIn = state.reservations.filter(r => r.status === 'checked_in')
            const checkedOut = state.reservations.filter(r => r.status === 'checked_out')
            const noShow = state.reservations.filter(r => r.status === 'no_show')

            const sumPersons = (items) => items.reduce((sum, r) => sum + Number(r.total_count ?? 0), 0)

            return {
                totalReservations,
                totalPersons,
                confirmedReservations: confirmed.length,
                confirmedPersons: sumPersons(confirmed),
                checkedInReservations: checkedIn.length,
                checkedInPersons: sumPersons(checkedIn),
                checkedOutReservations: checkedOut.length,
                checkedOutPersons: sumPersons(checkedOut),
                noShowReservations: noShow.length,
                noShowPersons: sumPersons(noShow),
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
        },

        selectCategory(categoryId) {
            this.selectedCategoryId = categoryId
        },

        addProduct(product) {
            const existing = this.orderItems.find(item => item.id === product.id)

            if (existing) {
                existing.quantity += 1
                return
            }

            this.orderItems.push({
                id: product.id,
                name: product.name,
                price_incl_vat: parseFloat(product.price_incl_vat),
                quantity: 1,
            })
        },

        increaseItem(productId) {
            const item = this.orderItems.find(item => item.id === productId)

            if (item) {
                item.quantity += 1
            }
        },

        decreaseItem(productId) {
            const item = this.orderItems.find(item => item.id === productId)

            if (!item) return

            item.quantity -= 1

            if (item.quantity <= 0) {
                this.removeItem(productId)
            }
        },

        removeItem(productId) {
            this.orderItems = this.orderItems.filter(item => item.id !== productId)
        },

        clearOrder() {
            this.orderItems = []
        },

        selectReservation(id) {
            this.selectedReservationId = id
        },

        clearReservationSelection() {
            this.selectedReservationId = null
        },

        setReservationSearch(value) {
            this.reservationSearch = value
        },

        setReservationViewMode(mode) {
            this.reservationViewMode = mode

            if (mode === 'open') {
                this.applyOpenReservationFilters()
            }

            if (mode === 'today') {
                this.resetReservationStatusFilters()
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
    },
})
