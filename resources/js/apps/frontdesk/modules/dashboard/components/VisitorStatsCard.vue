<template>
    <section class="flex h-full min-h-0 w-full flex-col rounded-3xl border border-slate-800 bg-slate-900/90 p-5 shadow-xl">
        <div class="mb-4 flex items-start justify-between gap-3">
            <div>
                <h2 class="text-xl font-semibold text-white">Bezoekersaantallen</h2>
                <p class="mt-1 text-sm text-slate-400">Vandaag · reservaties en bezoekers per status</p>
            </div>

            <div class="inline-flex rounded-2xl border border-slate-700 bg-slate-950/70 p-1.5 shadow-inner">
                <button
                    type="button"
                    class="inline-flex items-center gap-2 rounded-xl px-4 py-2.5 text-sm font-semibold transition"
                    :class="viewMode === 'chart' ? 'bg-slate-800 text-white shadow-sm' : 'text-slate-400 hover:text-slate-200'"
                    @click="viewMode = 'chart'"
                >
                    <ChartPieIcon class="h-5 w-5" />
                    <span>Pie chart</span>
                </button>

                <button
                    type="button"
                    class="inline-flex items-center gap-2 rounded-xl px-4 py-2.5 text-sm font-semibold transition"
                    :class="viewMode === 'table' ? 'bg-slate-800 text-white shadow-sm' : 'text-slate-400 hover:text-slate-200'"
                    @click="viewMode = 'table'"
                >
                    <TableCellsIcon class="h-5 w-5" />
                    <span>Totalen</span>
                </button>
            </div>
        </div>

        <div
            v-if="viewMode === 'table'"
            class="rounded-3xl border border-slate-800 bg-slate-950/50 p-3"
        >
            <div class="grid grid-cols-[220px_repeat(4,minmax(0,1fr))] gap-2">
                <div class="rounded-2xl border border-slate-700 bg-slate-900 px-4 py-3 text-sm font-semibold text-slate-200">
                    <div class="grid grid-cols-[44px_minmax(0,1fr)] items-center gap-3">
                        <div></div>
                        <span>Status</span>
                    </div>
                </div>

                <div class="rounded-2xl border border-slate-700 bg-slate-900 px-3 py-3 text-center text-sm font-semibold text-slate-200">
                    Reservaties
                </div>

                <div class="rounded-2xl border border-slate-700 bg-slate-900 px-3 py-3 text-center text-sm font-semibold text-slate-200">
                    Kinderen / studenten
                </div>

                <div class="rounded-2xl border border-slate-700 bg-slate-900 px-3 py-3 text-center text-sm font-semibold text-slate-200">
                    Volwassenen
                </div>

                <div class="rounded-2xl border border-white/15 bg-white/10 px-3 py-3 text-center text-sm font-semibold text-white shadow-[0_0_0_1px_rgba(255,255,255,0.04)]">
                    Totaal
                </div>
            </div>

            <div class="mt-3 space-y-2">
                <div
                    v-for="row in statusRows"
                    :key="row.key"
                    class="grid grid-cols-[220px_repeat(4,minmax(0,1fr))] gap-2"
                >
                    <div
                        class="rounded-3xl border px-4 py-3"
                        :class="row.labelClass"
                    >
                        <div class="grid grid-cols-[44px_minmax(0,1fr)] items-center gap-3">
                            <div class="flex h-11 w-11 items-center justify-center rounded-2xl border border-white/10 bg-black/10 text-white/95">
                                <component :is="row.icon" class="h-5 w-5" />
                            </div>

                            <p class="text-base font-semibold text-white">{{ row.label }}</p>
                        </div>
                    </div>

                    <div class="flex items-center justify-center rounded-3xl border px-3 py-3 text-center" :class="row.cellClass">
                        <span class="text-xl font-semibold leading-none tabular-nums text-white">{{ row.reservations }}</span>
                    </div>

                    <div class="flex items-center justify-center rounded-3xl border px-3 py-3 text-center" :class="row.cellClass">
                        <span class="text-xl font-semibold leading-none tabular-nums text-white">{{ row.childrenStudents }}</span>
                    </div>

                    <div class="flex items-center justify-center rounded-3xl border px-3 py-3 text-center" :class="row.cellClass">
                        <span class="text-xl font-semibold leading-none tabular-nums text-white">{{ row.adults }}</span>
                    </div>

                    <div class="flex items-center justify-center rounded-3xl border px-3 py-3 text-center" :class="row.totalCellClass">
                        <span class="text-2xl font-bold leading-none tabular-nums text-white">{{ row.visitors }}</span>
                    </div>
                </div>
            </div>
        </div>

        <div v-else class="flex min-h-0 flex-1 rounded-3xl border border-slate-800 bg-slate-950/50 p-6">
            <div class="grid w-full min-h-0 grid-rows-[minmax(0,1fr)_auto] gap-6">
                <div class="flex min-h-0 items-center justify-center">
                    <div class="mx-auto flex w-full max-w-[420px] flex-col items-center">
                        <div
                            class="relative h-72 w-72 rounded-full border border-slate-700 shadow-[0_0_40px_rgba(15,23,42,0.35)]"
                            :style="{ background: pieGradient }"
                        >
                            <div class="absolute inset-[22%] flex flex-col items-center justify-center rounded-full border border-slate-800 bg-slate-950 text-center shadow-inner">
                                <div class="text-xs font-semibold uppercase tracking-[0.2em] text-slate-500">Totaal</div>
                                <div class="mt-2 text-4xl font-bold text-white">{{ totalVisitors }}</div>
                                <div class="mt-1 text-sm text-slate-400">bezoekers</div>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="grid gap-3 sm:grid-cols-2 xl:grid-cols-3">
                    <article
                        v-for="segment in chartSegments"
                        :key="segment.key"
                        class="rounded-3xl border border-slate-800 bg-slate-900/80 p-4"
                    >
                        <div class="flex items-start justify-between gap-3">
                            <div class="flex min-w-0 items-center gap-3">
                                <span class="mt-1 h-3.5 w-3.5 rounded-full" :style="{ backgroundColor: segment.color }"></span>
                                <div class="min-w-0">
                                    <p class="truncate font-semibold text-white">{{ segment.label }}</p>
                                    <p class="mt-1 text-sm text-slate-400">{{ segment.reservations }} reservaties</p>
                                </div>
                            </div>
                            <div class="text-right">
                                <div class="text-2xl font-semibold text-white">{{ segment.visitors }}</div>
                                <div class="text-xs text-slate-400">{{ segment.percentage }}%</div>
                            </div>
                        </div>
                    </article>
                </div>
            </div>
        </div>
    </section>
</template>

<script setup>
import { computed, ref } from 'vue'
import {
    CalendarDaysIcon,
    IdentificationIcon,
    ArrowRightCircleIcon,
    ArrowLeftCircleIcon,
    BanknotesIcon,
    ExclamationTriangleIcon,
    ChartPieIcon,
    TableCellsIcon,
} from '@heroicons/vue/24/outline'

const props = defineProps({
    dateLabel: {
        type: String,
        required: true,
    },
    cards: {
        type: Array,
        required: true,
    },
})

const viewMode = ref('chart')

const rowOrder = ['reserved', 'members', 'checked_in', 'checked_out', 'paid', 'no_show']

const rowLabelMap = {
    reserved: 'Gereserveerd',
    members: 'Leden',
    checked_in: 'Ingecheckt',
    checked_out: 'Uitgecheckt',
    paid: 'Betaald',
    no_show: 'No-show',
}

const rowClassMap = {
    reserved: {
        labelClass: 'border-amber-500/25 bg-amber-500/15',
        cellClass: 'border-amber-500/25 bg-amber-500/10',
        totalCellClass: 'border-amber-400/40 bg-amber-400/18 shadow-[inset_0_1px_0_rgba(255,255,255,0.03)]',
        icon: CalendarDaysIcon,
        color: '#f59e0b',
    },
    members: {
        labelClass: 'border-cyan-500/25 bg-cyan-500/15',
        cellClass: 'border-cyan-500/25 bg-cyan-500/10',
        totalCellClass: 'border-cyan-400/40 bg-cyan-400/18 shadow-[inset_0_1px_0_rgba(255,255,255,0.03)]',
        icon: IdentificationIcon,
        color: '#06b6d4',
    },
    checked_in: {
        labelClass: 'border-sky-500/25 bg-sky-500/15',
        cellClass: 'border-sky-500/25 bg-sky-500/10',
        totalCellClass: 'border-sky-400/40 bg-sky-400/18 shadow-[inset_0_1px_0_rgba(255,255,255,0.03)]',
        icon: ArrowRightCircleIcon,
        color: '#0ea5e9',
    },
    checked_out: {
        labelClass: 'border-violet-500/25 bg-violet-500/15',
        cellClass: 'border-violet-500/25 bg-violet-500/10',
        totalCellClass: 'border-violet-400/40 bg-violet-400/18 shadow-[inset_0_1px_0_rgba(255,255,255,0.03)]',
        icon: ArrowLeftCircleIcon,
        color: '#8b5cf6',
    },
    paid: {
        labelClass: 'border-emerald-500/25 bg-emerald-500/15',
        cellClass: 'border-emerald-500/25 bg-emerald-500/10',
        totalCellClass: 'border-emerald-400/40 bg-emerald-400/18 shadow-[inset_0_1px_0_rgba(255,255,255,0.03)]',
        icon: BanknotesIcon,
        color: '#10b981',
    },
    no_show: {
        labelClass: 'border-rose-500/25 bg-rose-500/15',
        cellClass: 'border-rose-500/25 bg-rose-500/10',
        totalCellClass: 'border-rose-400/40 bg-rose-400/18 shadow-[inset_0_1px_0_rgba(255,255,255,0.03)]',
        icon: ExclamationTriangleIcon,
        color: '#f43f5e',
    },
}

const cardMap = computed(() => {
    const map = {}

    for (const card of props.cards) {
        map[card.key] = card
    }

    return map
})

const statusRows = computed(() => {
    return rowOrder.map((key) => {
        const card = cardMap.value[key] ?? {}

        return {
            key,
            label: rowLabelMap[key],
            reservations: Number(card.reservations ?? 0),
            childrenStudents: Number(card.childrenStudents ?? 0),
            adults: Number(card.adults ?? 0),
            visitors: Number(card.visitors ?? 0),
            ...rowClassMap[key],
        }
    })
})

const chartSegments = computed(() => {
    const relevantRows = statusRows.value.filter((row) => row.visitors > 0)
    const total = relevantRows.reduce((sum, row) => sum + row.visitors, 0)

    return relevantRows.map((row) => ({
        ...row,
        percentage: total > 0 ? Math.round((row.visitors / total) * 100) : 0,
    }))
})

const totalVisitors = computed(() => {
    return chartSegments.value.reduce((sum, row) => sum + row.visitors, 0)
})

const pieGradient = computed(() => {
    if (!chartSegments.value.length) {
        return 'conic-gradient(#1e293b 0deg 360deg)'
    }

    let current = 0
    const stops = chartSegments.value.map((segment) => {
        const slice = totalVisitors.value > 0 ? (segment.visitors / totalVisitors.value) * 360 : 0
        const start = current
        current += slice
        return `${segment.color} ${start}deg ${current}deg`
    })

    return `conic-gradient(${stops.join(', ')})`
})
</script>
