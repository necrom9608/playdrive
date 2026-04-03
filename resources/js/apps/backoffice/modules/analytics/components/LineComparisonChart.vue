<template>
    <section class="rounded-3xl border border-slate-800 bg-slate-900/90 p-5 shadow-xl">
        <div class="mb-4 flex flex-wrap items-center justify-between gap-3">
            <div>
                <h3 class="text-lg font-semibold text-white">{{ title }}</h3>
                <p class="mt-1 text-sm text-slate-400">{{ subtitle }}</p>
            </div>
            <div class="flex flex-wrap items-center gap-4 text-xs text-slate-300">
                <div class="flex items-center gap-2"><span class="h-2.5 w-2.5 rounded-full bg-cyan-400"></span>{{ primaryLabel }}</div>
                <div class="flex items-center gap-2"><span class="h-2.5 w-2.5 rounded-full bg-violet-400"></span>{{ comparisonLabel }}</div>
            </div>
        </div>

        <div class="rounded-3xl border border-slate-800 bg-slate-950/70 p-4">
            <svg viewBox="0 0 100 36" class="h-72 w-full overflow-visible">
                <line x1="0" y1="32" x2="100" y2="32" stroke="rgba(148,163,184,.35)" stroke-width="0.4" />
                <line x1="0" y1="2" x2="0" y2="32" stroke="rgba(148,163,184,.25)" stroke-width="0.4" />
                <path :d="primaryPath" fill="none" stroke="rgb(34 211 238)" stroke-width="1.2" stroke-linejoin="round" stroke-linecap="round" />
                <path :d="comparisonPath" fill="none" stroke="rgb(167 139 250)" stroke-width="1.2" stroke-linejoin="round" stroke-linecap="round" />
                <circle v-for="point in primaryPoints" :key="`p-${point.index}`" :cx="point.x" :cy="point.y" r="0.9" fill="rgb(34 211 238)" />
                <circle v-for="point in comparisonPoints" :key="`c-${point.index}`" :cx="point.x" :cy="point.y" r="0.9" fill="rgb(167 139 250)" />
            </svg>

            <div class="mt-4 grid grid-cols-2 gap-2 text-xs text-slate-400 md:grid-cols-6 xl:grid-cols-10">
                <div v-for="label in axisLabels" :key="label.index" class="truncate rounded-xl border border-slate-800 bg-slate-900/70 px-2 py-2 text-center">
                    {{ label.shortLabel }}
                </div>
            </div>
        </div>
    </section>
</template>

<script setup>
import { computed } from 'vue'

const props = defineProps({
    title: { type: String, required: true },
    subtitle: { type: String, default: '' },
    primary: { type: Array, required: true },
    comparison: { type: Array, required: true },
    primaryLabel: { type: String, required: true },
    comparisonLabel: { type: String, required: true },
})

const maxValue = computed(() => {
    const all = [...props.primary, ...props.comparison].map(item => Number(item.value || 0))
    return Math.max(...all, 1)
})

function buildPoints(series = []) {
    if (!series.length) {
        return []
    }

    const width = 100
    const height = 30
    const bottom = 32
    const step = series.length === 1 ? 0 : width / (series.length - 1)

    return series.map((item, index) => ({
        index: item.index,
        x: Number((index * step).toFixed(2)),
        y: Number((bottom - ((Number(item.value || 0) / maxValue.value) * height)).toFixed(2)),
    }))
}

function buildPath(points = []) {
    if (!points.length) {
        return ''
    }

    return points.map((point, index) => `${index === 0 ? 'M' : 'L'} ${point.x} ${point.y}`).join(' ')
}

const primaryPoints = computed(() => buildPoints(props.primary))
const comparisonPoints = computed(() => buildPoints(props.comparison))
const primaryPath = computed(() => buildPath(primaryPoints.value))
const comparisonPath = computed(() => buildPath(comparisonPoints.value))
const axisLabels = computed(() => props.primary.filter((_, index) => index === 0 || index === props.primary.length - 1 || index % Math.ceil(Math.max(props.primary.length, 1) / 8) === 0).map(item => ({ ...item, shortLabel: item.label?.slice(0, 6) || `Dag ${item.index}` })))
</script>
