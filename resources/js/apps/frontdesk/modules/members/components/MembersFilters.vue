<template>
    <div class="rounded-3xl border border-slate-800 bg-slate-900 p-4 shadow-xl">
        <div class="flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
            <div class="grid flex-1 gap-4 md:grid-cols-[minmax(0,1fr)_auto]">
                <label class="space-y-2 text-sm text-slate-300">
                    <span>Zoeken</span>
                    <input
                        :value="search"
                        type="text"
                        placeholder="Naam, e-mail, login of RFID"
                        class="w-full rounded-2xl border border-slate-700 bg-slate-950 px-4 py-3 text-white outline-none transition focus:border-blue-500"
                        @input="$emit('update:search', $event.target.value)"
                        @keyup.enter="$emit('search')"
                    >
                </label>

                <div class="space-y-2 text-sm text-slate-300">
                    <span>Status</span>

                    <div class="relative">
                        <button
                            type="button"
                            class="flex min-w-[240px] items-center justify-between rounded-2xl border border-slate-700 bg-slate-950 px-4 py-3 text-left text-white transition hover:border-slate-600"
                            @click="toggleOpen"
                        >
                            <span class="flex flex-wrap gap-2">
                                <template v-if="selectedBadges.length">
                                    <span
                                        v-for="badge in selectedBadges"
                                        :key="badge.value"
                                        class="inline-flex items-center rounded-full px-2.5 py-1 text-xs font-semibold"
                                        :class="badge.class"
                                    >
                                        {{ badge.label }}
                                    </span>
                                </template>
                                <span v-else class="text-slate-400">Alle statussen</span>
                            </span>

                            <svg class="ml-3 h-4 w-4 shrink-0 text-slate-400" viewBox="0 0 20 20" fill="currentColor">
                                <path fill-rule="evenodd" d="M5.23 7.21a.75.75 0 0 1 1.06.02L10 11.168l3.71-3.938a.75.75 0 1 1 1.08 1.04l-4.25 4.512a.75.75 0 0 1-1.08 0L5.21 8.27a.75.75 0 0 1 .02-1.06Z" clip-rule="evenodd" />
                            </svg>
                        </button>

                        <div
                            v-if="isOpen"
                            class="absolute left-0 top-[calc(100%+0.5rem)] z-20 w-[280px] rounded-2xl border border-slate-700 bg-slate-950 p-4 shadow-2xl"
                        >
                            <div class="space-y-3">
                                <label
                                    v-for="option in statusOptions"
                                    :key="option.value"
                                    class="flex items-center gap-3 rounded-xl px-2 py-2 text-sm text-slate-200 transition hover:bg-slate-900"
                                >
                                    <input
                                        type="checkbox"
                                        class="h-4 w-4 rounded border-slate-600 bg-slate-900 text-blue-600"
                                        :checked="selectedStatuses.includes(option.value)"
                                        @change="toggleStatus(option.value)"
                                    >
                                    <span
                                        class="inline-flex rounded-full px-2.5 py-1 text-xs font-semibold"
                                        :class="option.class"
                                    >
                                        {{ option.label }}
                                    </span>
                                </label>
                            </div>

                            <div class="mt-4 flex justify-between gap-3 border-t border-slate-800 pt-4">
                                <button
                                    type="button"
                                    class="rounded-xl border border-slate-700 bg-slate-900 px-3 py-2 text-sm font-semibold text-slate-200 transition hover:bg-slate-800"
                                    @click="clearStatuses"
                                >
                                    Wissen
                                </button>
                                <button
                                    type="button"
                                    class="rounded-xl bg-blue-600 px-3 py-2 text-sm font-semibold text-white transition hover:bg-blue-500"
                                    @click="isOpen = false"
                                >
                                    Klaar
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div class="flex flex-wrap gap-3">
                <button
                    type="button"
                    class="rounded-2xl border border-slate-700 bg-slate-800 px-4 py-3 text-sm font-semibold text-slate-200 transition hover:bg-slate-700"
                    @click="$emit('search')"
                >
                    Zoeken
                </button>
                <button
                    type="button"
                    class="rounded-2xl bg-blue-600 px-4 py-3 text-sm font-semibold text-white transition hover:bg-blue-500"
                    @click="$emit('new')"
                >
                    Nieuwe abonnee
                </button>
            </div>
        </div>
    </div>
</template>

<script setup>
import { computed, ref } from 'vue'

const props = defineProps({
    search: {
        type: String,
        default: '',
    },
    selectedStatuses: {
        type: Array,
        default: () => [],
    },
})

const emit = defineEmits(['update:search', 'update:selected-statuses', 'search', 'new'])

const isOpen = ref(false)

const statusOptions = [
    { value: 'active', label: 'Actief', class: 'bg-emerald-500/15 text-emerald-300' },
    { value: 'expiring', label: 'Vervalt binnenkort', class: 'bg-amber-500/15 text-amber-300' },
    { value: 'expired', label: 'Vervallen', class: 'bg-rose-500/15 text-rose-300' },
    { value: 'inactive', label: 'Inactief', class: 'bg-slate-500/15 text-slate-300' },
]

const selectedBadges = computed(() =>
    statusOptions.filter(option => props.selectedStatuses.includes(option.value))
)

function toggleOpen() {
    isOpen.value = !isOpen.value
}

function toggleStatus(value) {
    const next = props.selectedStatuses.includes(value)
        ? props.selectedStatuses.filter(item => item !== value)
        : [...props.selectedStatuses, value]

    emit('update:selected-statuses', next)
}

function clearStatuses() {
    emit('update:selected-statuses', [])
}
</script>
