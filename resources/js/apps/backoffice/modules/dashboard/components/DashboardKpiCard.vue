<template>
    <section class="flex flex-col gap-2 rounded-2xl border border-slate-800 bg-slate-900/90 px-5 py-4 shadow-xl">
        <p class="text-sm font-medium text-slate-400">{{ label }}</p>
        <p class="text-3xl font-bold tracking-tight text-white tabular-nums">{{ value }}</p>
        <div class="flex flex-wrap items-center gap-x-2 gap-y-1 text-xs">
            <span v-if="delta !== null && delta !== undefined" class="inline-flex items-center gap-0.5 font-semibold" :class="delta >= 0 ? 'text-emerald-400' : 'text-orange-400'">
                <ArrowUpIcon v-if="delta >= 0" class="h-3.5 w-3.5" aria-hidden="true" />
                <ArrowDownIcon v-else class="h-3.5 w-3.5" aria-hidden="true" />
                {{ formatPercent(Math.abs(delta)) }}
            </span>
            <span v-else class="font-semibold text-slate-500">n.v.t.</span>
            <span class="text-slate-400">{{ subtext }}</span>
        </div>
    </section>
</template>

<script setup>
import { ArrowDownIcon, ArrowUpIcon } from '@heroicons/vue/20/solid'
import { formatPercent } from '../utils/format'

defineProps({
    label: { type: String, required: true },
    value: { type: String, required: true },
    delta: { type: Number, default: null },
    subtext: { type: String, default: '' },
})
</script>
