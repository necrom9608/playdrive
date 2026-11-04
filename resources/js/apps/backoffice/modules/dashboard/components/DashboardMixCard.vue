<template>
    <section class="flex flex-col gap-6 rounded-2xl border border-slate-800 bg-slate-900/90 p-5 shadow-xl">
        <h2 class="text-base font-semibold text-white">{{ title }}</h2>

        <div v-for="group in normalizedGroups" :key="group.title" class="flex flex-col gap-2.5">
            <h3 class="text-xs font-semibold uppercase tracking-[0.1em] text-slate-400">{{ group.title }}</h3>

            <div v-if="group.total" class="flex h-3 gap-0.5 overflow-hidden rounded-full bg-slate-950/70">
                <div
                    v-for="row in group.rows"
                    :key="row.label"
                    :style="{ width: `${row.share}%`, backgroundColor: row.color }"
                    :title="`${row.label}: ${formatPercent(row.share)}`"
                ></div>
            </div>
            <div v-else class="h-3 rounded-full bg-slate-950/70"></div>

            <ul class="flex flex-wrap gap-x-5 gap-y-1.5">
                <li v-for="row in group.rows" :key="row.label" class="flex items-center gap-2 text-sm">
                    <span class="h-2.5 w-2.5 shrink-0 rounded-sm" :style="{ backgroundColor: row.color }"></span>
                    <span class="font-medium text-slate-100">{{ row.label }}</span>
                    <span class="text-slate-400 tabular-nums">{{ formatPercent(row.share) }} · {{ formatCurrency(row.amount) }} · {{ row.countLabel }}</span>
                </li>
                <li v-if="!group.rows.length" class="text-sm text-slate-400">Geen verkopen in deze periode.</li>
            </ul>
        </div>
    </section>
</template>

<script setup>
import { computed } from 'vue'
import { formatCurrency, formatNumber, formatPercent } from '../utils/format'

// Cyaan, paars, en daarna neutrale tinten: lichtheid verschilt, niet enkel de kleur.
const COLORS = ['#22d3ee', '#a78bfa', '#64748b', '#94a3b8', '#475569', '#cbd5e1']

const props = defineProps({
    title: { type: String, required: true },
    // [{ title, unit, rows: [{ label, amount, count }] }]
    groups: { type: Array, required: true },
})

const normalizedGroups = computed(() => props.groups.map((group) => {
    const rows = (group.rows || []).filter(row => Number(row.amount || 0) !== 0)
    const total = rows.reduce((sum, row) => sum + Math.max(0, Number(row.amount || 0)), 0)

    return {
        title: group.title,
        total,
        rows: rows.map((row, index) => ({
            label: row.label,
            amount: Number(row.amount || 0),
            share: total > 0 ? (Math.max(0, Number(row.amount || 0)) / total) * 100 : 0,
            countLabel: `${formatNumber(row.count)} ${group.unit || ''}`.trim(),
            color: COLORS[index % COLORS.length],
        })),
    }
}))
</script>
