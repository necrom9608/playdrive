<template>
    <section class="flex h-full min-h-0 w-full flex-col rounded-3xl border border-slate-800 bg-slate-900/90 p-5 shadow-xl">
        <div class="mb-4 flex items-center justify-between gap-3">
            <div>
                <h2 class="text-xl font-semibold text-white">Bezoekersaantallen</h2>
                <p class="mt-1 text-sm text-slate-400">Vandaag · reservaties en bezoekers per status</p>
            </div>

            <span class="rounded-full border border-slate-700 bg-slate-950/70 px-3 py-1 text-xs font-semibold text-slate-300">
                {{ dateLabel }}
            </span>
        </div>

        <div class="min-h-0 flex-1 rounded-3xl border border-slate-800 bg-slate-950/50 p-3">
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

                <div class="rounded-2xl border border-slate-700 bg-slate-900 px-3 py-3 text-center text-sm font-semibold text-slate-200">
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
                        class="min-h-[72px] rounded-3xl border px-4 py-4"
                        :class="row.labelClass"
                    >
                        <div class="grid h-full grid-cols-[44px_minmax(0,1fr)] items-center gap-3">
                            <div class="flex h-11 w-11 items-center justify-center rounded-2xl border border-white/10 bg-black/10 text-white/95">
                                <component :is="row.icon" class="h-5 w-5" />
                            </div>

                            <p class="text-base font-semibold text-white">{{ row.label }}</p>
                        </div>
                    </div>

                    <div
                        class="flex min-h-[72px] items-center justify-center rounded-3xl border px-3 py-3 text-center"
                        :class="row.cellClass"
                    >
                        <span class="text-3xl font-semibold tabular-nums text-white">{{ row.reservations }}</span>
                    </div>

                    <div
                        class="flex min-h-[72px] items-center justify-center rounded-3xl border px-3 py-3 text-center"
                        :class="row.cellClass"
                    >
                        <span class="text-3xl font-semibold tabular-nums text-white">{{ row.childrenStudents }}</span>
                    </div>

                    <div
                        class="flex min-h-[72px] items-center justify-center rounded-3xl border px-3 py-3 text-center"
                        :class="row.cellClass"
                    >
                        <span class="text-3xl font-semibold tabular-nums text-white">{{ row.adults }}</span>
                    </div>

                    <div
                        class="flex min-h-[72px] items-center justify-center rounded-3xl border px-3 py-3 text-center"
                        :class="row.cellClass"
                    >
                        <span class="text-3xl font-semibold tabular-nums text-white">{{ row.visitors }}</span>
                    </div>
                </div>
            </div>
        </div>
    </section>
</template>

<script setup>
import { computed } from 'vue'
import {
    CalendarDaysIcon,
    IdentificationIcon,
    ArrowRightCircleIcon,
    ArrowLeftCircleIcon,
    BanknotesIcon,
    ExclamationTriangleIcon,
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
        icon: CalendarDaysIcon,
    },
    members: {
        labelClass: 'border-cyan-500/25 bg-cyan-500/15',
        cellClass: 'border-cyan-500/25 bg-cyan-500/10',
        icon: IdentificationIcon,
    },
    checked_in: {
        labelClass: 'border-sky-500/25 bg-sky-500/15',
        cellClass: 'border-sky-500/25 bg-sky-500/10',
        icon: ArrowRightCircleIcon,
    },
    checked_out: {
        labelClass: 'border-violet-500/25 bg-violet-500/15',
        cellClass: 'border-violet-500/25 bg-violet-500/10',
        icon: ArrowLeftCircleIcon,
    },
    paid: {
        labelClass: 'border-emerald-500/25 bg-emerald-500/15',
        cellClass: 'border-emerald-500/25 bg-emerald-500/10',
        icon: BanknotesIcon,
    },
    no_show: {
        labelClass: 'border-rose-500/25 bg-rose-500/15',
        cellClass: 'border-rose-500/25 bg-rose-500/10',
        icon: ExclamationTriangleIcon,
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
</script>
