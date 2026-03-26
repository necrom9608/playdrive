<template>
    <div class="flex h-full min-h-0 flex-col overflow-hidden rounded-3xl border border-slate-800 bg-slate-900 shadow-xl">
        <RegistrationToolbar
            :search="store.reservationSearch"
            :view-mode="store.reservationViewMode"
            :show-filters="showFilters"
            :status-filters="store.reservationStatusFilters"
            :status-options="statusOptions"
            @new="openNewRegistrationModal"
            @search="store.setReservationSearch"
            @change-view-mode="store.setReservationViewMode"
            @toggle-filters="toggleFilters"
            @toggle-status-filter="toggleStatusFilter"
            @reset-filters="resetFilters"
            @close-filters="closeFilters"
        />

        <RegistrationStats :stats="store.reservationStats" />

        <RegistrationList
            :reservations="store.filteredReservations"
            :selected-id="store.selectedReservationId"
            @select="store.selectReservation"
            @edit="openEditRegistrationModal"
        />

        <RegistrationActions
            :has-selection="!!store.selectedReservationId"
            @check-in="handleCheckIn"
            @check-out="handleCheckOut"
            @edit="openEditRegistrationModal"
            @cancel="handleCancelReservation"
            @no-show="handleNoShowReservation"
            @delete="handleDeleteReservation"
            @clear-selection="store.clearReservationSelection"
        />

        <RegistrationModal
            :open="showRegistrationModal"
            :initial-values="editingReservation"
            @close="closeRegistrationModal"
            @submit="handleRegistrationSubmit"
        />
    </div>
</template>

<script setup>
import { onMounted } from 'vue'
import { usePosStore } from '../../stores/usePosStore.js'
import { useRegistrationActions } from '../../composables/useRegistrationActions'
import RegistrationModal from './RegistrationModal.vue'
import RegistrationToolbar from './RegistrationToolbar.vue'
import RegistrationStats from './RegistrationStats.vue'
import RegistrationList from './RegistrationList.vue'
import RegistrationActions from './RegistrationActions.vue'

const store = usePosStore()

const {
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
} = useRegistrationActions(store)

const statusOptions = [
    { label: 'Nieuw', value: 'new' },
    { label: 'Bevestigd', value: 'confirmed' },
    { label: 'Ingecheckt', value: 'checked_in' },
    { label: 'Uitgecheckt', value: 'checked_out' },
    { label: 'Betaald', value: 'paid' },
    { label: 'Geannuleerd', value: 'cancelled' },
    { label: 'No-show', value: 'no_show' },
]

onMounted(() => {
    store.fetchReservations()
})
</script>
