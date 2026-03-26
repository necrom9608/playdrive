<template>
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
                    @click="$emit('new')"
                >
                    <span class="mr-2 text-base leading-none">+</span>
                    Nieuw
                </button>
            </div>

            <div class="grid gap-3 lg:grid-cols-[minmax(0,1fr)_auto_auto]">
                <div class="relative">
                    <input
                        :value="search"
                        type="text"
                        placeholder="Zoeken op naam, telefoon of e-mail..."
                        class="w-full rounded-2xl border border-slate-700 bg-slate-800 px-4 py-3 text-sm text-slate-100 placeholder:text-slate-500 shadow-sm transition focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-900"
                        @input="$emit('search', $event.target.value)"
                    >
                </div>

                <div class="grid grid-cols-3 gap-2 rounded-2xl bg-slate-800 p-1 ring-1 ring-slate-700 shadow-sm">
                    <button
                        type="button"
                        class="rounded-xl px-3 py-2 text-sm font-semibold transition"
                        :class="viewMode === 'today' ? 'bg-blue-600 text-white shadow-sm' : 'text-slate-300 hover:bg-slate-700'"
                        @click="$emit('change-view-mode', 'today')"
                    >
                        Vandaag
                    </button>

                    <button
                        type="button"
                        class="rounded-xl px-3 py-2 text-sm font-semibold transition"
                        :class="viewMode === 'date' ? 'bg-blue-600 text-white shadow-sm' : 'text-slate-300 hover:bg-slate-700'"
                        @click="$emit('change-view-mode', 'date')"
                    >
                        Datum
                    </button>

                    <button
                        type="button"
                        class="rounded-xl px-3 py-2 text-sm font-semibold transition"
                        :class="viewMode === 'open' ? 'bg-blue-600 text-white shadow-sm' : 'text-slate-300 hover:bg-slate-700'"
                        @click="$emit('change-view-mode', 'open')"
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
                        @click="$emit('toggle-filters')"
                    >
                        <span>Filters</span>

                        <span class="inline-flex h-5 min-w-5 items-center justify-center rounded-full bg-blue-600 px-1.5 text-[11px] font-bold text-white">
                            !
                        </span>
                    </button>

                    <RegistrationFiltersMenu
                        :open="showFilters"
                        :status-filters="statusFilters"
                        :status-options="statusOptions"
                        @toggle-status-filter="$emit('toggle-status-filter', $event)"
                        @reset-filters="$emit('reset-filters')"
                        @close="$emit('close-filters')"
                    />
                </div>
            </div>
        </div>
    </div>
</template>

<script setup>
import RegistrationFiltersMenu from './RegistrationFiltersMenu.vue'

defineProps({
    search: {
        type: String,
        required: true,
    },
    viewMode: {
        type: String,
        required: true,
    },
    showFilters: {
        type: Boolean,
        default: false,
    },
    statusFilters: {
        type: Object,
        required: true,
    },
    statusOptions: {
        type: Array,
        required: true,
    },
})

defineEmits([
    'new',
    'search',
    'change-view-mode',
    'toggle-filters',
    'toggle-status-filter',
    'reset-filters',
    'close-filters',
])
</script>
