<template>
    <div class="flex h-full min-h-0 flex-col gap-4 overflow-hidden">
        <div class="shrink-0 rounded-3xl border border-slate-800 bg-slate-900 shadow-xl">
            <div class="flex flex-col gap-4 border-b border-slate-800 p-4 lg:flex-row lg:items-center lg:justify-between">
                <div>
                    <h1 class="text-xl font-semibold text-white">Agenda</h1>
                    <p class="mt-1 text-sm text-slate-400">
                        Overzicht van alle reservaties en taken per dag, week en maand.
                    </p>
                </div>

                <div class="flex flex-wrap items-center gap-2">
                    <button
                        type="button"
                        class="rounded-2xl border border-slate-700 bg-slate-800 px-4 py-2 text-sm font-medium text-white transition hover:bg-slate-700"
                        @click="store.goToPrevious"
                    >
                        Vorige
                    </button>

                    <button
                        type="button"
                        class="rounded-2xl border border-slate-700 bg-slate-800 px-4 py-2 text-sm font-medium text-white transition hover:bg-slate-700"
                        @click="store.goToToday"
                    >
                        Vandaag
                    </button>

                    <button
                        type="button"
                        class="rounded-2xl border border-slate-700 bg-slate-800 px-4 py-2 text-sm font-medium text-white transition hover:bg-slate-700"
                        @click="store.goToNext"
                    >
                        Volgende
                    </button>
                </div>
            </div>

            <div class="flex flex-col gap-4 p-4 lg:flex-row lg:items-center lg:justify-between">
                <div>
                    <div class="text-lg font-semibold text-white">
                        {{ store.rangeLabel }}
                    </div>

                    <div class="mt-2 flex flex-wrap gap-2">
                        <div
                            v-for="status in activeStatuses(store.summary.status_totals)"
                            :key="status.key"
                            class="inline-flex items-center gap-1 rounded-full px-2 py-1 text-xs font-medium ring-1"
                            :class="status.colors.badge"
                        >
                            <span class="h-2 w-2 rounded-full" :class="status.colors.accent"></span>
                            <span>{{ status.label }}</span>
                            <span class="font-semibold">{{ status.count }}</span>
                        </div>
                    </div>
                </div>

                <div class="flex flex-wrap gap-2 text-xs text-slate-400">
                    <span class="rounded-full border border-slate-700 bg-slate-950 px-3 py-1">{{ store.summary.reservations || 0 }} reservaties</span>
                    <span class="rounded-full border border-pink-500/30 bg-pink-500/10 px-3 py-1 text-pink-200">{{ store.summary.tasks || 0 }} taken</span>
                </div>

                <div class="inline-flex rounded-2xl border border-slate-800 bg-slate-950 p-1">
                    <button
                        v-for="option in viewOptions"
                        :key="option.value"
                        type="button"
                        class="rounded-xl px-4 py-2 text-sm font-medium transition"
                        :class="store.view === option.value
                            ? 'bg-blue-500 text-white'
                            : 'text-slate-300 hover:bg-slate-800'"
                        @click="store.setView(option.value)"
                    >
                        {{ option.label }}
                    </button>
                </div>
            </div>
        </div>

        <div class="min-h-0 flex-1 overflow-hidden">
            <div v-if="store.loading" class="rounded-3xl border border-slate-800 bg-slate-900 p-8 text-sm text-slate-400 shadow-xl">
                Agenda laden...
            </div>

            <template v-else>
                <AgendaDayOverview
                    v-if="store.view === 'day'"
                    :registrations="store.dayRegistrations"
                />

                <AgendaWeekOverview
                    v-else-if="store.view === 'week'"
                    :days="store.days"
                    @select-day="store.openDay"
                />

                <AgendaMonthOverview
                    v-else
                    :days="store.days"
                    @select-day="store.openDay"
                />
            </template>
        </div>
    </div>
</template>

<script setup>
import { onMounted } from 'vue'
import { useAgendaStore } from '../stores/useAgendaStore'
import AgendaDayOverview from '../components/AgendaDayOverview.vue'
import AgendaWeekOverview from '../components/AgendaWeekOverview.vue'
import AgendaMonthOverview from '../components/AgendaMonthOverview.vue'

const store = useAgendaStore()

const viewOptions = [
    { label: 'Dag', value: 'day' },
    { label: 'Week', value: 'week' },
    { label: 'Maand', value: 'month' },
]

onMounted(() => {
    store.fetchAgenda()
})

function activeStatuses(statusTotals = []) {
    return statusTotals.filter(status => status.count > 0)
}
</script>
