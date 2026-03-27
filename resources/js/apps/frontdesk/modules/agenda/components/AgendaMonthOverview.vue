<template>
    <div class="flex h-full min-h-0 flex-col overflow-hidden rounded-3xl border border-slate-800 bg-slate-900 shadow-xl">
        <div class="shrink-0 border-b border-slate-800 px-4 py-3">
            <h3 class="text-lg font-semibold text-white">Maandoverzicht</h3>
            <p class="mt-1 text-sm text-slate-400">
                Kalender met totalen per dag. Klik op een dag om het dagoverzicht te openen.
            </p>
        </div>

        <div class="shrink-0 grid grid-cols-7 border-b border-slate-800 bg-slate-950/60 text-center text-[11px] font-semibold uppercase tracking-wide text-slate-500">
            <div
                v-for="label in weekdayLabels"
                :key="label"
                class="px-2 py-2"
            >
                {{ label }}
            </div>
        </div>

        <div class="min-h-0 flex-1">
            <div
                class="grid h-full grid-cols-7 gap-px bg-slate-800"
                :style="{ gridTemplateRows: `repeat(${rowCount}, minmax(0, 1fr))` }"
            >
                <AgendaMonthDayCell
                    v-for="day in days"
                    :key="day.date"
                    :day="day"
                    @select-day="$emit('select-day', $event)"
                />
            </div>
        </div>
    </div>
</template>

<script setup>
import { computed } from 'vue'
import AgendaMonthDayCell from './AgendaMonthDayCell.vue'

const props = defineProps({
    days: {
        type: Array,
        default: () => [],
    },
})

defineEmits(['select-day'])

const weekdayLabels = ['Ma', 'Di', 'Wo', 'Do', 'Vr', 'Za', 'Zo']

const rowCount = computed(() => {
    const count = Math.ceil((props.days?.length ?? 0) / 7)
    return Math.max(count, 1)
})
</script>
