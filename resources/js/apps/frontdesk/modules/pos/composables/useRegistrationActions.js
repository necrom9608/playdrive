import axios from 'axios'
import { computed, ref } from 'vue'

export function useRegistrationActions(store) {
    const showRegistrationModal = ref(false)
    const showFilters = ref(false)
    const editingReservationId = ref(null)

    const editingReservation = computed(() => {
        if (!editingReservationId.value) {
            return {}
        }

        return store.reservations.find(
            item => item.id === editingReservationId.value
        ) ?? {}
    })

    function openNewRegistrationModal() {
        editingReservationId.value = null
        showRegistrationModal.value = true
    }

    function openEditRegistrationModal() {
        if (!store.selectedReservationId) return

        editingReservationId.value = store.selectedReservationId
        showRegistrationModal.value = true
    }

    function closeRegistrationModal() {
        showRegistrationModal.value = false
        editingReservationId.value = null
    }

    async function handleRegistrationSubmit(payload) {
        try {
            let response

            if (payload.id) {
                response = await axios.put(`/api/frontdesk/registrations/${payload.id}`, payload)
                store.updateReservation(response.data.data)
            } else {
                response = await axios.post('/api/frontdesk/registrations', payload)
                store.addReservation(response.data.data)
            }

            closeRegistrationModal()
        } catch (error) {
            console.error(error)

            if (error.response?.status === 422) {
                console.log('validation errors', error.response.data.errors)
            }
        }
    }

    async function handleCheckIn() {
        if (!store.selectedReservationId) return

        try {
            const response = await axios.post(
                `/api/frontdesk/registrations/${store.selectedReservationId}/check-in`
            )

            store.updateReservation(response.data.data)
        } catch (error) {
            console.error(error)
        }
    }

    async function handleCheckOut() {
        if (!store.selectedReservationId) return

        const reservationId = store.selectedReservationId
        store.checkoutError = null

        try {
            const response = await axios.post(
                `/api/frontdesk/registrations/${reservationId}/check-out`
            )

            const registration = response.data?.data ?? null
            const syncedOrder = response.data?.order ?? null

            if (registration) {
                store.updateReservation(registration)
            }

            if (syncedOrder) {
                if (typeof store.upsertOrder === 'function') {
                    store.upsertOrder(syncedOrder)
                } else if (typeof store.replaceReservationOrder === 'function') {
                    store.replaceReservationOrder(reservationId, syncedOrder)
                }
            }

            store.lastCheckoutSummary = {
                mode: 'registration_check_out',
                registration_id: reservationId,
                registration,
                order: syncedOrder,
            }

            if (typeof store.fetchReservations === 'function') {
                await store.fetchReservations()
            }

            if (typeof store.fetchOrders === 'function') {
                await store.fetchOrders()
            }

            if (syncedOrder?.id) {
                if (typeof store.setSelectedOrderId === 'function') {
                    store.setSelectedOrderId(syncedOrder.id)
                } else if ('selectedOrderId' in store) {
                    store.selectedOrderId = syncedOrder.id
                }
            }

            if (typeof store.selectReservation === 'function') {
                store.selectReservation(reservationId)
            } else if ('selectedReservationId' in store) {
                store.selectedReservationId = reservationId
            }
        } catch (error) {
            console.error(error)

            const message =
                error?.response?.data?.message
                ?? error?.message
                ?? 'Uitchecken mislukt.'

            store.checkoutError = message
        }
    }

    async function handleCancelReservation() {
        if (!store.selectedReservationId) return

        try {
            const response = await axios.post(
                `/api/frontdesk/registrations/${store.selectedReservationId}/cancel`
            )

            store.updateReservation(response.data.data)
        } catch (error) {
            console.error(error)
        }
    }

    async function handleNoShowReservation() {
        if (!store.selectedReservationId) return

        try {
            const response = await axios.post(
                `/api/frontdesk/registrations/${store.selectedReservationId}/no-show`
            )

            store.updateReservation(response.data.data)
        } catch (error) {
            console.error(error)
        }
    }

    async function handleDeleteReservation() {
        if (!store.selectedReservationId) return

        const id = store.selectedReservationId
        const confirmed = window.confirm('Ben je zeker dat je deze registratie wil verwijderen?')

        if (!confirmed) return

        try {
            await axios.delete(`/api/frontdesk/registrations/${id}`)
            store.removeReservation(id)
        } catch (error) {
            console.error(error)
        }
    }

    function toggleStatusFilter(value) {
        store.toggleReservationStatusFilter(value)
    }

    function resetFilters() {
        store.resetReservationStatusFilters()
    }

    function closeFilters() {
        showFilters.value = false
    }

    function toggleFilters() {
        showFilters.value = !showFilters.value
    }

    return {
        showRegistrationModal,
        showFilters,
        editingReservation,
        openNewRegistrationModal,
        openEditRegistrationModal,
        closeRegistrationModal,
        handleRegistrationSubmit,
        handleCheckIn,
        handleCheckOut,
        handleCancelReservation,
        handleNoShowReservation,
        handleDeleteReservation,
        toggleStatusFilter,
        resetFilters,
        closeFilters,
        toggleFilters,
    }
}
