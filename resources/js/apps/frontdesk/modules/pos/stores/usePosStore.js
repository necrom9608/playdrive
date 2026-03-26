import axios from 'axios'
import { computed, ref } from 'vue'
import { defineStore } from 'pinia'

export const usePosStore = defineStore('pos', () => {
    const reservations = ref([])
    const isLoadingReservations = ref(false)

    const selectedReservationId = ref(null)
    const reservationSearch = ref('')
    const reservationViewMode = ref('today')

    const reservationStatusFilters = ref({
        new: true,
        confirmed: true,
        checked_in: true,
        checked_out: true,
        paid: true,
        cancelled: false,
        no_show: false,
    })

    async function fetchReservations() {
        isLoadingReservations.value = true

        try {
            const response = await axios.get('/api/frontdesk/registrations')
            reservations.value = response.data.data ?? []
        } catch (error) {
            console.error('Failed to fetch reservations', error)
        } finally {
            isLoadingReservations.value = false
        }
    }

    function addReservation(reservation) {
        reservations.value.unshift(reservation)
    }

    function selectReservation(id) {
        selectedReservationId.value = id
    }

    function clearReservationSelection() {
        selectedReservationId.value = null
    }

    function setReservationSearch(value) {
        reservationSearch.value = value
    }

    function setReservationViewMode(mode) {
        reservationViewMode.value = mode
    }

    function toggleReservationStatusFilter(status) {
        reservationStatusFilters.value[status] = !reservationStatusFilters.value[status]
    }

    function resetReservationStatusFilters() {
        reservationStatusFilters.value = {
            new: true,
            confirmed: true,
            checked_in: true,
            checked_out: true,
            paid: true,
            cancelled: false,
            no_show: false,
        }
    }

    const filteredReservations = computed(() => {
        const search = reservationSearch.value.trim().toLowerCase()

        return reservations.value.filter((reservation) => {
            const matchesSearch = !search || [
                reservation.name,
                reservation.phone,
                reservation.email,
                reservation.municipality,
            ]
                .filter(Boolean)
                .some((value) => String(value).toLowerCase().includes(search))

            const matchesStatus = !!reservationStatusFilters.value[reservation.status]

            return matchesSearch && matchesStatus
        })
    })

    const selectedReservation = computed(() => {
        return reservations.value.find(
            (reservation) => reservation.id === selectedReservationId.value
        ) ?? null
    })

    const reservationStats = computed(() => {
        const stats = {
            totalReservations: 0,
            totalPersons: 0,

            confirmedReservations: 0,
            confirmedPersons: 0,

            checkedInReservations: 0,
            checkedInPersons: 0,

            checkedOutReservations: 0,
            checkedOutPersons: 0,

            noShowReservations: 0,
            noShowPersons: 0,
        }

        for (const reservation of filteredReservations.value) {
            const persons = Number(reservation.total_count ?? 0)

            stats.totalReservations += 1
            stats.totalPersons += persons

            if (reservation.status === 'confirmed') {
                stats.confirmedReservations += 1
                stats.confirmedPersons += persons
            }

            if (reservation.status === 'checked_in') {
                stats.checkedInReservations += 1
                stats.checkedInPersons += persons
            }

            if (reservation.status === 'checked_out') {
                stats.checkedOutReservations += 1
                stats.checkedOutPersons += persons
            }

            if (reservation.status === 'no_show') {
                stats.noShowReservations += 1
                stats.noShowPersons += persons
            }
        }

        return stats
    })

    return {
        reservations,
        isLoadingReservations,

        selectedReservationId,
        selectedReservation,

        reservationSearch,
        reservationViewMode,
        reservationStatusFilters,

        filteredReservations,
        reservationStats,

        fetchReservations,
        addReservation,

        selectReservation,
        clearReservationSelection,

        setReservationSearch,
        setReservationViewMode,
        toggleReservationStatusFilter,
        resetReservationStatusFilters,
    }
})
