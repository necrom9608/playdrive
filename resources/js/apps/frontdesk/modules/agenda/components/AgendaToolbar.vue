<template>
    <div class="rounded-3xl border border-slate-800 bg-slate-900 p-4 shadow-xl">
        <div class="flex flex-col gap-4 xl:flex-row xl:items-center xl:justify-between">
            <div>
                <h2 class="text-3xl font-bold text-white">Agenda</h2>
                <p class="mt-2 text-slate-400">
                    Overzicht van alle reservaties per dag, week of maand.
                </p>
            </div>

            <div class="flex flex-col gap-3 xl:items-end">
                <div class="flex flex-wrap items-center gap-2">
                    <button
                        v-for="option in views"
                        :key="option.value"
                        type="button"
                        class="rounded-2xl border px-4 py-2 text-sm font-semibold transition"
                        :class="store.view === option.value
                            ? 'border-blue-500 bg-blue-600 text-white'
                            : 'border-slate-700 bg-slate-800 text-slate-200 hover:border-slate-600 hover:bg-slate-700'"
                        @click="store.setView(option.value)"
                    >
                        {{ option.label }}
                    </button>
                </div>

                <div class="flex flex-wrap items-center gap-2">
                    <button
                        type="button"
                        class="rounded-2xl border border-slate-700 bg-slate-800 px-3 py-2 text-sm font-semibold text-slate-200 transition hover:border-slate-600 hover:bg-slate-700"
                        @click="store.goToPrevious()"
                    >
                        Vorige
                    </button>

                    <input
                        :value="store.selectedDate"
                        type="date"
                        class="rounded-2xl border border-slate-700 bg-slate-950 px-3 py-2 text-sm text-white outline-none transition focus:border-blue-500"
                        @change="store.setSelectedDate($event.target.value)"
                    >

                    <button
                        type="button"
                        class="rounded-2xl border border-slate-700 bg-slate-800 px-3 py-2 text-sm font-semibold text-slate-200 transition hover:border-slate-600 hover:bg-slate-700"
                        @click="store.goToNext()"
                    >
                        Volgende
                    </button>

                    <button
                        type="button"
                        class="rounded-2xl border border-slate-700 bg-slate-800 px-3 py-2 text-sm font-semibold text-slate-200 transition hover:border-slate-600 hover:bg-slate-700"
                        @click="store.goToToday()"
                    >
                        Vandaag
                    </button>
                </div>

                <p class="text-sm font-medium text-slate-300">
                    {{ store.range?.label ?? '...' }}
                </p>
            </div>
        </div>
    </div>
</template>

<script setup>
import { useAgendaStore } from '../stores/useAgendaStore'

const store = useAgendaStore()

const views = [
    { value: 'day', label: 'Dag' },
    { value: 'week', label: 'Week' },
    { value: 'month', label: 'Maand' },
]
</script>
