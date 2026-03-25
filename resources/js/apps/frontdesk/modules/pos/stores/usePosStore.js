import { defineStore } from 'pinia'
import axios from 'axios'

export const usePosStore = defineStore('pos', {
    state: () => ({
        categories: [],
        products: [],
        selectedCategoryId: null,
        orderItems: [],
        loadingCatalog: false,

        reservations: [
            {
                id: 1,
                name: 'Liam Vermeulen',
                phone: '0472 11 22 33',
                email: 'liam@example.com',
                kids_count: 6,
                adults_count: 2,
                total_count: 8,
                start_time: '14:00',
                duration_label: '2u',
                status: 'confirmed',
            },
            {
                id: 2,
                name: 'Emma De Smet',
                phone: '0499 55 66 77',
                email: 'emma@example.com',
                kids_count: 4,
                adults_count: 1,
                total_count: 5,
                start_time: '15:30',
                duration_label: '2u',
                status: 'checked_in',
            },
            {
                id: 3,
                name: 'Noah Van Damme',
                phone: '0488 99 88 77',
                email: 'noah@example.com',
                kids_count: 10,
                adults_count: 3,
                total_count: 13,
                start_time: '16:00',
                duration_label: '3u',
                status: 'new',
            },
            {
                id: 4,
                name: 'Olivia Maes',
                phone: '0470 44 55 66',
                email: 'olivia@example.com',
                kids_count: 5,
                adults_count: 2,
                total_count: 7,
                start_time: '13:00',
                duration_label: '2u',
                status: 'checked_out',
            },
        ],

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
                ]
                    .filter(Boolean)
                    .some(value => value.toLowerCase().includes(q))
            })
        },

        reservationStats(state) {
            const totalReservations = state.reservations.length
            const totalPersons = state.reservations.reduce((sum, r) => sum + r.total_count, 0)

            const confirmed = state.reservations.filter(r => r.status === 'confirmed')
            const checkedIn = state.reservations.filter(r => r.status === 'checked_in')
            const checkedOut = state.reservations.filter(r => r.status === 'checked_out')
            const noShow = state.reservations.filter(r => r.status === 'no_show')

            const sumPersons = (items) => items.reduce((sum, r) => sum + r.total_count, 0)

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
        },

        setReservationStatusFilter(value) {
            this.reservationStatusFilter = value
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
