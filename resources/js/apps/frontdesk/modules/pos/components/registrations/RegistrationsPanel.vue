<template>
    <div class="flex h-full min-h-0 flex-col overflow-hidden rounded-3xl border border-slate-800 bg-slate-900 shadow-xl">
        <div class="border-b border-slate-800 bg-slate-900/80 p-4">
            <div class="space-y-4">
                <div class="flex items-center justify-between gap-3">
                    <div>
                        <h2 class="text-lg font-semibold text-slate-100">
                            Registraties
                        </h2>

                        <p class="text-sm text-slate-400">
                            Overzicht, filters en snelle acties
                        </p>
                    </div>

                    <button
                        type="button"
                        class="inline-flex items-center rounded-2xl bg-green-600 px-4 py-2.5 text-sm font-semibold text-white shadow-sm transition hover:bg-green-700 hover:shadow-md"
                        @click="openNewRegistrationModal"
                    >
                        <span class="mr-2 text-base leading-none">+</span>
                        Nieuw
                    </button>
                </div>

                <div class="grid grid-cols-2 gap-3 xl:grid-cols-5">
                    <div class="rounded-2xl border border-slate-700 bg-slate-800 p-3 shadow-sm">
                        <div class="text-[11px] font-medium uppercase tracking-wide text-slate-400">
                            Totaal
                        </div>

                        <div class="mt-1 text-2xl font-semibold leading-none text-slate-100">
                            {{ store.reservationStats.totalReservations }}
                            <span class="text-slate-500">/</span>
                            {{ store.reservationStats.totalPersons }}
                        </div>
                    </div>

                    <div class="rounded-2xl border border-orange-900 bg-orange-950/40 p-3 shadow-sm">
                        <div class="text-[11px] font-medium uppercase tracking-wide text-orange-400">
                            Gereserveerd
                        </div>

                        <div class="mt-1 text-2xl font-semibold leading-none text-orange-300">
                            {{ store.reservationStats.confirmedReservations }}
                            <span class="text-orange-700">/</span>
                            {{ store.reservationStats.confirmedPersons }}
                        </div>
                    </div>

                    <div class="rounded-2xl border border-blue-900 bg-blue-950/40 p-3 shadow-sm">
                        <div class="text-[11px] font-medium uppercase tracking-wide text-blue-400">
                            In
                        </div>

                        <div class="mt-1 text-2xl font-semibold leading-none text-blue-300">
                            {{ store.reservationStats.checkedInReservations }}
                            <span class="text-blue-700">/</span>
                            {{ store.reservationStats.checkedInPersons }}
                        </div>
                    </div>

                    <div class="rounded-2xl border border-green-900 bg-green-950/40 p-3 shadow-sm">
                        <div class="text-[11px] font-medium uppercase tracking-wide text-green-400">
                            Uit
                        </div>

                        <div class="mt-1 text-2xl font-semibold leading-none text-green-300">
                            {{ store.reservationStats.checkedOutReservations }}
                            <span class="text-green-700">/</span>
                            {{ store.reservationStats.checkedOutPersons }}
                        </div>
                    </div>

                    <div class="rounded-2xl border border-red-900 bg-red-950/40 p-3 shadow-sm">
                        <div class="text-[11px] font-medium uppercase tracking-wide text-red-400">
                            No-show
                        </div>

                        <div class="mt-1 text-2xl font-semibold leading-none text-red-300">
                            {{ store.reservationStats.noShowReservations }}
                            <span class="text-red-700">/</span>
                            {{ store.reservationStats.noShowPersons }}
                        </div>
                    </div>
                </div>

                <div class="grid gap-3 lg:grid-cols-[minmax(0,1fr)_auto_auto]">
                    <div class="relative">
                        <input
                            :value="store.reservationSearch"
                            type="text"
                            placeholder="Zoeken op naam, telefoon of e-mail..."
                            class="w-full rounded-2xl border border-slate-700 bg-slate-800 px-4 py-3 text-sm text-slate-100 placeholder:text-slate-500 shadow-sm transition focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-900"
                            @input="store.setReservationSearch($event.target.value)"
                        >
                    </div>

                    <div class="grid grid-cols-3 gap-2 rounded-2xl bg-slate-800 p-1 ring-1 ring-slate-700 shadow-sm">
                        <button
                            type="button"
                            class="rounded-xl px-3 py-2 text-sm font-semibold transition"
                            :class="store.reservationViewMode === 'today' ? 'bg-blue-600 text-white shadow-sm' : 'text-slate-300 hover:bg-slate-700'"
                            @click="store.setReservationViewMode('today')"
                        >
                            Vandaag
                        </button>

                        <button
                            type="button"
                            class="rounded-xl px-3 py-2 text-sm font-semibold transition"
                            :class="store.reservationViewMode === 'date' ? 'bg-blue-600 text-white shadow-sm' : 'text-slate-300 hover:bg-slate-700'"
                            @click="store.setReservationViewMode('date')"
                        >
                            Datum
                        </button>

                        <button
                            type="button"
                            class="rounded-xl px-3 py-2 text-sm font-semibold transition"
                            :class="store.reservationViewMode === 'open' ? 'bg-blue-600 text-white shadow-sm' : 'text-slate-300 hover:bg-slate-700'"
                            @click="store.setReservationViewMode('open')"
                        >
                            Open
                        </button>
                    </div>

                    <div
                        ref="filterMenuRef"
                        class="relative"
                    >
                        <button
                            type="button"
                            class="inline-flex h-full items-center justify-center gap-2 rounded-2xl border border-slate-700 bg-slate-800 px-4 py-3 text-sm font-semibold text-slate-200 shadow-sm transition hover:bg-slate-700"
                            @click="showFilters = !showFilters"
                        >
                            <span>Filters</span>

                            <span
                                class="inline-flex h-5 min-w-5 items-center justify-center rounded-full bg-blue-600 px-1.5 text-[11px] font-bold text-white"
                            >
                                !
                            </span>
                        </button>

                        <div
                            v-if="showFilters"
                            class="absolute right-0 z-30 mt-3 w-[520px] rounded-3xl border border-slate-700 bg-slate-900 p-5 shadow-2xl"
                        >
                            <div class="mb-3 text-xs font-semibold uppercase tracking-wide text-slate-400">
                                Status
                            </div>

                            <div class="flex flex-wrap gap-3">
                                <button
                                    v-for="option in statusOptions"
                                    :key="option.value"
                                    type="button"
                                    class="inline-flex items-center gap-2 rounded-full border px-4 py-2 text-sm font-semibold transition"
                                    :class="store.reservationStatusFilters[option.value]
                                        ? 'border-blue-500 bg-blue-600 text-white'
                                        : 'border-slate-700 bg-slate-950 text-slate-300 hover:bg-slate-800'"
                                    @click="toggleStatusFilter(option.value)"
                                >
                                    <span
                                        class="flex h-5 w-5 items-center justify-center rounded-md border text-[11px]"
                                        :class="store.reservationStatusFilters[option.value]
                                            ? 'border-blue-200 bg-blue-500 text-white'
                                            : 'border-slate-600 bg-slate-900 text-transparent'"
                                    >
                                        ✓
                                    </span>

                                    <span>{{ option.label }}</span>
                                </button>
                            </div>

                            <div class="mt-6 flex items-center justify-between">
                                <button
                                    type="button"
                                    class="rounded-2xl border border-slate-700 bg-slate-950 px-4 py-3 text-sm font-semibold text-slate-200 transition hover:bg-slate-800"
                                    @click="resetFilters"
                                >
                                    Reset filters
                                </button>

                                <button
                                    type="button"
                                    class="rounded-2xl bg-slate-700 px-4 py-3 text-sm font-semibold text-white transition hover:bg-slate-600"
                                    @click="closeFilters"
                                >
                                    Sluiten
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="flex-1 overflow-y-auto bg-slate-900">
            <div class="flex min-h-full flex-col gap-2 p-3">
                <div class="grid grid-cols-[6px_minmax(260px,2fr)_52px_52px_52px_72px_90px] gap-2 rounded-2xl border border-slate-800 bg-slate-900/70 p-2 text-center text-xs font-semibold text-slate-300">
                    <div></div>
                    <div class="flex items-center justify-center rounded-xl bg-slate-800 px-3 py-2">
                        Naam
                    </div>
                    <div class="flex items-center justify-center rounded-xl bg-slate-800 text-[11px] font-semibold text-slate-400">
                        K
                    </div>
                    <div class="flex items-center justify-center rounded-xl bg-slate-800 text-[11px] font-semibold text-slate-400">
                        V
                    </div>
                    <div class="flex items-center justify-center rounded-xl bg-slate-800 text-[11px] font-semibold text-slate-300">
                        T
                    </div>
                    <div class="flex items-center justify-center rounded-xl bg-slate-800">
                        Van
                    </div>
                    <div class="flex items-center justify-center rounded-xl bg-slate-800">
                        Duur
                    </div>
                </div>

                <button
                    v-for="reservation in store.filteredReservations"
                    :key="reservation.id"
                    type="button"
                    class="grid grid-cols-[6px_minmax(260px,2fr)_52px_52px_52px_72px_90px] gap-2 rounded-2xl border p-2 text-left shadow-sm transition"
                    :class="store.selectedReservationId === reservation.id
                        ? 'border-blue-700 bg-blue-950/40'
                        : 'border-slate-800 bg-slate-950 hover:border-slate-700 hover:bg-slate-900'"
                    @click="store.selectReservation(reservation.id)"
                >
                    <div
                        class="rounded-full"
                        :class="statusStripeClass(reservation.status)"
                    />

                    <div class="flex min-w-0 flex-col justify-center rounded-xl bg-slate-900 px-3 py-2">
                        <div class="truncate text-sm font-semibold text-white">
                            {{ reservation.name }}
                        </div>
                        <div class="truncate text-xs text-slate-400">
                            {{ reservation.phone || reservation.email }}
                        </div>
                    </div>

                    <div class="flex items-center justify-center rounded-xl bg-slate-900 text-sm font-semibold text-slate-300">
                        {{ reservation.participants_children ?? 0 }}
                    </div>

                    <div class="flex items-center justify-center rounded-xl bg-slate-900 text-sm font-semibold text-slate-300">
                        {{ reservation.participants_adults ?? 0 }}
                    </div>

                    <div class="flex items-center justify-center rounded-xl bg-slate-900 text-sm font-semibold text-white">
                        {{ reservation.total_count ?? 0 }}
                    </div>

                    <div class="flex items-center justify-center rounded-xl bg-slate-900 text-sm font-semibold text-slate-200">
                        {{ reservation.event_time ?? '-' }}
                    </div>

                    <div class="flex items-center justify-center rounded-xl bg-slate-900 text-sm font-semibold text-slate-400">
                        {{ reservation.duration_label ?? '-' }}
                    </div>
                </button>

                <div
                    v-if="store.filteredReservations.length === 0"
                    class="rounded-3xl border border-dashed border-slate-700 bg-slate-900 px-6 py-10 text-center shadow-sm"
                >
                    <div class="text-base font-semibold text-slate-200">
                        Geen registraties gevonden
                    </div>

                    <div class="mt-1 text-sm text-slate-400">
                        Pas je filters aan of maak een nieuwe registratie aan.
                    </div>
                </div>
            </div>
        </div>

        <div class="border-t border-slate-800 bg-slate-900 p-4">
            <div class="flex gap-3">
                <div class="flex-1 space-y-3">
                    <div class="grid grid-cols-3 gap-3">
                        <button
                            type="button"
                            class="inline-flex h-12 items-center justify-center gap-2 rounded-2xl px-4 text-sm font-semibold shadow-sm transition"
                            :class="store.selectedReservationId ? 'bg-blue-600 text-white hover:bg-blue-700' : 'cursor-not-allowed bg-slate-800 text-slate-500'"
                            :disabled="!store.selectedReservationId"
                            @click="handleCheckIn"
                        >
                            Inchecken
                        </button>

                        <button
                            type="button"
                            class="inline-flex h-12 items-center justify-center gap-2 rounded-2xl px-4 text-sm font-semibold shadow-sm transition"
                            :class="store.selectedReservationId ? 'bg-emerald-600 text-white hover:bg-emerald-700' : 'cursor-not-allowed bg-slate-800 text-slate-500'"
                            :disabled="!store.selectedReservationId"
                            @click="handleCheckOut"
                        >
                            Uitchecken
                        </button>

                        <button
                            type="button"
                            class="inline-flex h-12 items-center justify-center gap-2 rounded-2xl px-4 text-sm font-semibold shadow-sm transition"
                            :class="store.selectedReservationId ? 'bg-amber-500 text-white hover:bg-amber-600' : 'cursor-not-allowed bg-slate-800 text-slate-500'"
                            :disabled="!store.selectedReservationId"
                        >
                            Bewerken
                        </button>
                    </div>

                    <div class="grid grid-cols-3 gap-3">
                        <button
                            type="button"
                            class="inline-flex h-12 items-center justify-center gap-2 rounded-2xl px-4 text-sm font-semibold shadow-sm transition"
                            :class="store.selectedReservationId ? 'bg-slate-600 text-white hover:bg-slate-500' : 'cursor-not-allowed bg-slate-800 text-slate-500'"
                            :disabled="!store.selectedReservationId"
                            @click="handleCancelReservation"
                        >
                            Annuleren
                        </button>

                        <button
                            type="button"
                            class="inline-flex h-12 items-center justify-center gap-2 rounded-2xl px-4 text-sm font-semibold shadow-sm transition"
                            :class="store.selectedReservationId ? 'bg-rose-600 text-white hover:bg-rose-700' : 'cursor-not-allowed bg-slate-800 text-slate-500'"
                            :disabled="!store.selectedReservationId"
                            @click="handleNoShowReservation"
                        >
                            No-show
                        </button>

                        <button
                            type="button"
                            class="inline-flex h-12 items-center justify-center gap-2 rounded-2xl px-4 text-sm font-semibold shadow-sm transition"
                            :class="store.selectedReservationId ? 'bg-red-600 text-white hover:bg-red-700' : 'cursor-not-allowed bg-slate-800 text-slate-500'"
                            :disabled="!store.selectedReservationId"
                            @click="handleDeleteReservation"
                        >
                            Verwijderen
                        </button>
                    </div>
                </div>

                <div class="w-56 shrink-0">
                    <button
                        type="button"
                        class="inline-flex h-full min-h-[108px] w-full flex-col items-center justify-center gap-2 rounded-2xl px-4 text-sm font-semibold shadow-sm transition"
                        :class="store.selectedReservationId ? 'bg-slate-700 text-white hover:bg-slate-600' : 'cursor-not-allowed bg-slate-800 text-slate-500'"
                        :disabled="!store.selectedReservationId"
                        @click="store.clearReservationSelection()"
                    >
                        <span>Selectie wissen</span>
                    </button>
                </div>
            </div>
        </div>

        <RegistrationModal
            :open="showRegistrationModal"
            @close="closeRegistrationModal"
            @submit="handleRegistrationSubmit"
        />
    </div>
</template>

<script setup>
import axios from 'axios'
import { onBeforeUnmount, onMounted, ref } from 'vue'
import { usePosStore } from '../../stores/usePosStore.js'
import RegistrationModal from './RegistrationModal.vue'

const store = usePosStore()

const showRegistrationModal = ref(false)
const showFilters = ref(false)
const filterMenuRef = ref(null)

const statusOptions = [
    { label: 'Nieuw', value: 'new' },
    { label: 'Bevestigd', value: 'confirmed' },
    { label: 'Ingecheckt', value: 'checked_in' },
    { label: 'Uitgecheckt', value: 'checked_out' },
    { label: 'Betaald', value: 'paid' },
    { label: 'Geannuleerd', value: 'cancelled' },
    { label: 'No-show', value: 'no_show' },
]

function openNewRegistrationModal() {
    showRegistrationModal.value = true
}

function closeRegistrationModal() {
    showRegistrationModal.value = false
}

async function handleRegistrationSubmit(payload) {
    try {
        const response = await axios.post('/api/frontdesk/registrations', payload)
        store.addReservation(response.data.data)
        showRegistrationModal.value = false
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
        const response = await axios.post(`/api/frontdesk/registrations/${store.selectedReservationId}/check-in`)
        store.updateReservation(response.data.data)
    } catch (error) {
        console.error(error)
    }
}

async function handleCheckOut() {
    if (!store.selectedReservationId) return

    try {
        const response = await axios.post(`/api/frontdesk/registrations/${store.selectedReservationId}/check-out`)
        store.updateReservation(response.data.data)
    } catch (error) {
        console.error(error)
    }
}

async function handleCancelReservation() {
    if (!store.selectedReservationId) return

    try {
        const response = await axios.post(`/api/frontdesk/registrations/${store.selectedReservationId}/cancel`)
        store.updateReservation(response.data.data)
    } catch (error) {
        console.error(error)
    }
}

async function handleNoShowReservation() {
    if (!store.selectedReservationId) return

    try {
        const response = await axios.post(`/api/frontdesk/registrations/${store.selectedReservationId}/no-show`)
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

function statusStripeClass(status) {
    switch (status) {
        case 'checked_in':
            return 'bg-blue-500'
        case 'checked_out':
            return 'bg-green-500'
        case 'confirmed':
            return 'bg-orange-500'
        case 'no_show':
            return 'bg-red-500'
        case 'cancelled':
            return 'bg-slate-500'
        case 'paid':
            return 'bg-emerald-400'
        case 'new':
            return 'bg-indigo-500'
        default:
            return 'bg-slate-600'
    }
}

function handleClickOutside(event) {
    if (!filterMenuRef.value) return

    if (!filterMenuRef.value.contains(event.target)) {
        showFilters.value = false
    }
}

onMounted(() => {
    document.addEventListener('click', handleClickOutside)
    store.fetchReservations()
})

onBeforeUnmount(() => {
    document.removeEventListener('click', handleClickOutside)
})
</script>
