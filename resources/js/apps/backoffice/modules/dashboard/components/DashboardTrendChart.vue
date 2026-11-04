<template>
    <section class="flex min-w-0 flex-col gap-4 rounded-2xl border border-slate-800 bg-slate-900/90 p-5 shadow-xl">
        <div class="flex flex-wrap items-start justify-between gap-4">
            <div>
                <h2 class="text-base font-semibold text-white">{{ title }}</h2>
                <p class="mt-1 text-xs text-slate-400">Netto, Loyverse-import en Playdrive samen · vergeleken met {{ compareLabel }}</p>
            </div>

            <dl v-if="active" class="flex flex-wrap gap-x-6 gap-y-2">
                <div>
                    <dt class="text-[11px] text-slate-400">{{ trend.bucket === 'week' ? `Week van ${formatDayShort(active.date)}` : formatDayShort(active.date) }}</dt>
                    <dd class="text-[15px] font-semibold text-white tabular-nums">{{ active.is_open || active.value ? formatCurrency(active.value) : 'Gesloten' }}</dd>
                </div>
                <div>
                    <dt class="text-[11px] text-slate-400">Orders</dt>
                    <dd class="text-[15px] font-semibold text-white tabular-nums">{{ formatNumber(active.orders) }}</dd>
                </div>
                <div>
                    <dt class="text-[11px] text-slate-400">{{ formatDayShort(active.compare_date) }} {{ compareYearSuffix(active.compare_date) }}</dt>
                    <dd class="text-[15px] font-semibold text-slate-300 tabular-nums">{{ formatCurrency(active.compare_value) }}</dd>
                </div>
            </dl>
            <dl v-else class="flex flex-wrap gap-x-6 gap-y-2">
                <div>
                    <dt class="text-[11px] text-slate-400">Totaal</dt>
                    <dd class="text-[15px] font-semibold text-white tabular-nums">{{ formatCurrency(trend.total, { round: true }) }}</dd>
                </div>
                <div>
                    <dt class="text-[11px] text-slate-400">Gem. per open dag</dt>
                    <dd class="text-[15px] font-semibold text-white tabular-nums">{{ formatCurrency(trend.average_per_open_day, { round: true }) }}</dd>
                </div>
                <div v-if="trend.best">
                    <dt class="text-[11px] text-slate-400">Beste {{ trend.bucket === 'week' ? 'week' : 'dag' }}</dt>
                    <dd class="text-[15px] font-semibold text-white tabular-nums">{{ formatDayShort(trend.best.date) }} · {{ formatCurrency(trend.best.value, { round: true }) }}</dd>
                </div>
            </dl>
        </div>

        <div class="overflow-x-auto">
            <div class="relative pl-12" :style="{ minWidth: `${Math.max(600, points.length * 14)}px` }" @mouseleave="hovered = null">
                <!-- y-as -->
                <template v-for="tick in ticks" :key="tick.value">
                    <div class="pointer-events-none absolute right-0 left-12 h-px" :class="tick.value === 0 ? 'bg-slate-700' : 'bg-slate-800'" :style="{ top: `${PLOT_TOP + PLOT_H * (1 - tick.value / scaleMax)}px` }"></div>
                    <div class="pointer-events-none absolute left-0 w-10 text-right text-[11px] text-slate-400 tabular-nums" :style="{ top: `${PLOT_TOP + PLOT_H * (1 - tick.value / scaleMax) - 8}px` }">{{ tick.label }}</div>
                </template>

                <div class="relative grid gap-y-1.5" :style="{ gridTemplateColumns: `repeat(${points.length}, minmax(0, 1fr))`, gridTemplateRows: `20px ${PLOT_H}px 18px` }">
                    <!-- vakantiebanden -->
                    <template v-for="band in bands" :key="`${band.label}-${band.from}`">
                        <div class="truncate self-center pl-1 text-[11px] font-medium text-violet-300" :style="{ gridColumn: `${band.from} / ${band.to + 1}`, gridRow: 1 }">{{ band.label }}</div>
                        <div class="rounded-t border-t-2 border-violet-400/50 bg-violet-400/10" :style="{ gridColumn: `${band.from} / ${band.to + 1}`, gridRow: 2 }"></div>
                    </template>

                    <!-- staven -->
                    <button
                        v-for="(point, index) in points"
                        :key="point.date"
                        type="button"
                        class="group relative z-10 flex h-full items-end justify-center px-[2px] focus:outline-none"
                        :style="{ gridColumn: index + 1, gridRow: 2 }"
                        :aria-label="`${formatDayShort(point.date)}: ${formatCurrency(point.value)}`"
                        @mouseenter="hovered = index"
                        @focus="hovered = index"
                        @click="hovered = index"
                    >
                        <span
                            class="block w-full max-w-7 rounded-t-[3px] transition-opacity"
                            :class="[barClass(point), hovered !== null && hovered !== index ? 'opacity-50' : '']"
                            :style="{ height: barHeight(point) }"
                        ></span>
                        <span
                            v-if="showCompare && point.compare_value > 0"
                            class="pointer-events-none absolute left-[18%] right-[18%] h-0.5 rounded bg-slate-200/70"
                            :style="{ bottom: `${Math.min(100, (point.compare_value / scaleMax) * 100)}%` }"
                        ></span>
                    </button>

                    <!-- x-as -->
                    <div
                        v-for="(point, index) in points"
                        :key="`l-${point.date}`"
                        class="whitespace-nowrap text-[11px]"
                        :class="point.is_today ? 'font-semibold text-white' : 'text-slate-400'"
                        :style="{ gridColumn: index + 1, gridRow: 3 }"
                    >{{ axisLabel(point, index) }}</div>
                </div>
            </div>
        </div>

        <div class="flex flex-wrap gap-x-5 gap-y-2 text-xs text-slate-400">
            <span class="flex items-center gap-1.5"><span class="h-2.5 w-2.5 rounded-sm bg-cyan-400"></span>{{ trend.bucket === 'week' ? 'Week' : 'Weekdag' }}</span>
            <span v-if="trend.bucket !== 'week'" class="flex items-center gap-1.5"><span class="h-2.5 w-2.5 rounded-sm bg-cyan-200"></span>Weekend</span>
            <span class="flex items-center gap-1.5"><span class="h-[3px] w-2.5 bg-slate-600"></span>Gesloten / geen omzet</span>
            <span v-if="showCompare" class="flex items-center gap-1.5"><span class="h-0.5 w-2.5 bg-slate-200/70"></span>{{ capitalize(compareLabel) }}</span>
            <span v-if="bands.length" class="flex items-center gap-1.5"><span class="h-2.5 w-2.5 rounded-sm bg-violet-400/40"></span>Schoolvakantie</span>
        </div>
    </section>
</template>

<script setup>
import { computed, ref } from 'vue'
import { dayOfMonth, formatCurrency, formatDayShort, formatNumber, isFirstOfMonth, isMonday, monthShort, weekdayShort } from '../utils/format'

const PLOT_H = 220
const PLOT_TOP = 26 // rij 1 (20px) + rij-gap (6px)

const props = defineProps({
    title: { type: String, default: 'Omzet per dag' },
    trend: { type: Object, required: true },
    compareLabel: { type: String, default: 'vorig jaar' },
})

const hovered = ref(null)
const points = computed(() => props.trend.points || [])
const active = computed(() => (hovered.value !== null ? points.value[hovered.value] : null))
const showCompare = computed(() => points.value.some(p => Number(p.compare_value) > 0))

const scaleMax = computed(() => {
    const max = Math.max(1, ...points.value.map(p => Math.max(Number(p.value) || 0, Number(p.compare_value) || 0)))
    const magnitude = 10 ** Math.floor(Math.log10(max))
    const step = [1, 2, 2.5, 5, 10].map(f => f * magnitude).find(s => s * 4 >= max) || magnitude * 10
    return step * 4
})

const ticks = computed(() => [4, 3, 2, 1, 0].map((i) => {
    const value = (scaleMax.value / 4) * i
    return {
        value,
        label: value >= 1000 ? `€ ${(value / 1000).toLocaleString('nl-BE', { maximumFractionDigits: 1 })}k` : `€ ${Math.round(value)}`,
    }
}))

// Aaneengesloten dagen met hetzelfde vakantielabel → één band.
const bands = computed(() => {
    const result = []
    points.value.forEach((point, index) => {
        const last = result[result.length - 1]
        if (!point.holiday) return
        if (last && last.label === point.holiday && last.to === index) {
            last.to = index + 1
        } else {
            result.push({ label: point.holiday, from: index + 1, to: index + 1 })
        }
    })
    return result
})

function barHeight(point) {
    const value = Number(point.value) || 0
    if (value <= 0) return '3px'
    return `${Math.max(4, (value / scaleMax.value) * PLOT_H)}px`
}

function barClass(point) {
    if ((Number(point.value) || 0) <= 0) return 'bg-slate-600'
    if (props.trend.bucket !== 'week' && point.is_weekend) return 'bg-cyan-200'
    return 'bg-cyan-400'
}

function axisLabel(point, index) {
    const last = points.value.length - 1
    if (props.trend.bucket === 'week') {
        return index === 0 || index % 4 === 0 ? `${dayOfMonth(point.date)} ${monthShort(point.date)}` : ''
    }
    if (points.value.length <= 31) {
        if (index === 0 || index === last || isMonday(point.date)) {
            return `${weekdayShort(point.date)} ${dayOfMonth(point.date)}`
        }
        return ''
    }
    return index === 0 || isFirstOfMonth(point.date) ? `${dayOfMonth(point.date)} ${monthShort(point.date)}` : ''
}

function compareYearSuffix(isoDate) {
    const year = String(isoDate).slice(0, 4)
    return year !== String(new Date().getFullYear()) ? year : ''
}

function capitalize(text) {
    return text ? text.charAt(0).toUpperCase() + text.slice(1) : text
}
</script>
