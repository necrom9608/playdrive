<template>
    <div class="flex flex-col gap-4">
        <!-- Header -->
        <header class="flex flex-wrap items-center justify-between gap-3">
            <div>
                <p class="text-[11px] font-semibold uppercase tracking-[0.14em] text-cyan-300">Backoffice · Dashboard</p>
                <h1 class="mt-1 text-2xl font-bold text-white">{{ periodTitle }}</h1>
            </div>

            <div class="flex flex-wrap items-center gap-2.5">
                <div class="flex gap-0.5 rounded-xl border border-slate-800 bg-slate-900/90 p-[3px]" role="group" aria-label="Periode">
                    <button
                        v-for="option in PERIODS"
                        :key="option.key"
                        type="button"
                        class="min-h-9 rounded-lg px-3.5 text-[13px] font-medium transition"
                        :class="period === option.key ? 'bg-slate-700/70 font-semibold text-white' : 'text-slate-400 hover:text-white'"
                        :aria-pressed="period === option.key"
                        @click="setPeriod(option.key)"
                    >{{ option.label }}</button>
                </div>

                <template v-if="period === 'custom'">
                    <label class="sr-only" for="dash-start">Van</label>
                    <input id="dash-start" v-model="customStart" type="date" class="min-h-10 rounded-lg border border-slate-800 bg-slate-900/90 px-2.5 text-[13px] text-white" @change="loadDashboard()">
                    <span class="text-slate-500" aria-hidden="true">–</span>
                    <label class="sr-only" for="dash-end">Tot</label>
                    <input id="dash-end" v-model="customEnd" type="date" class="min-h-10 rounded-lg border border-slate-800 bg-slate-900/90 px-2.5 text-[13px] text-white" @change="loadDashboard()">
                </template>

                <label class="flex items-center gap-2 text-[13px] text-slate-400">
                    Vergelijk met
                    <select v-model="compare" class="min-h-10 rounded-lg border border-slate-800 bg-slate-900/90 px-2.5 text-[13px] text-white" @change="persistAndLoad">
                        <option value="previous_year">Vorig jaar</option>
                        <option value="previous_period">Vorige periode</option>
                    </select>
                </label>

                <button
                    type="button"
                    class="flex min-h-10 items-center gap-1.5 rounded-lg px-2 text-xs text-slate-400 hover:text-white"
                    title="Nu vernieuwen"
                    @click="loadDashboard()"
                >
                    <span class="h-2 w-2 rounded-full" :class="refreshing ? 'animate-pulse bg-cyan-400' : 'bg-emerald-400'"></span>
                    Live · bijgewerkt {{ updatedLabel }}
                    <ArrowPathIcon class="h-4 w-4" :class="refreshing ? 'animate-spin' : ''" aria-hidden="true" />
                </button>
            </div>
        </header>

        <div v-if="loading" class="rounded-2xl border border-slate-800 bg-slate-900/90 px-6 py-16 text-center text-sm text-slate-400 shadow-xl">
            Dashboard laden...
        </div>

        <div v-else-if="error" class="rounded-2xl border border-orange-500/30 bg-orange-500/10 px-6 py-10 text-center text-sm text-orange-200">
            {{ error }}
        </div>

        <template v-else>
            <!-- KPI's -->
            <div class="grid grid-cols-[repeat(auto-fit,minmax(230px,1fr))] gap-4">
                <DashboardKpiCard
                    label="Omzet"
                    :value="formatCurrency(kpis.revenue?.value)"
                    :delta="kpis.revenue?.delta_percentage"
                    :subtext="`t.o.v. ${compareRangeLabel}`"
                />
                <DashboardKpiCard
                    label="Orders"
                    :value="formatNumber(kpis.orders?.value)"
                    :delta="kpis.orders?.delta_percentage"
                    :subtext="`gem. ticket ${formatCurrency(kpis.orders?.avg_order_value)}`"
                />
                <DashboardKpiCard
                    label="Bezoekers"
                    :value="formatNumber(kpis.visitors?.value)"
                    :delta="kpis.visitors?.delta_percentage"
                    subtext="ingecheckte deelnemers"
                />
                <DashboardKpiCard
                    label="Omzet per bezoeker"
                    :value="formatCurrency(kpis.revenue_per_visitor?.value)"
                    :delta="kpis.revenue_per_visitor?.delta_percentage"
                    :subtext="`t.o.v. ${compareRangeLabel}`"
                />
            </div>

            <!-- Trend + Vandaag -->
            <div class="flex flex-wrap items-stretch gap-4">
                <DashboardTrendChart
                    class="min-w-0 flex-[999_1_640px]"
                    :title="trendTitle"
                    :trend="dashboard.trend || { points: [] }"
                    :compare-label="compare === 'previous_year' ? 'vorig jaar' : 'vorige periode'"
                />

                <div class="flex min-w-0 flex-[1_1_320px] flex-col gap-4">
                    <section class="flex grow flex-col gap-3 rounded-2xl border border-slate-800 bg-slate-900/90 p-5 shadow-xl">
                        <div class="flex items-start justify-between gap-3">
                            <div>
                                <h2 class="text-base font-semibold text-white">Vandaag</h2>
                                <p class="mt-1 text-2xl font-bold text-white tabular-nums">{{ formatCurrency(today.revenue?.value) }}</p>
                                <p class="mt-0.5 flex flex-wrap items-center gap-x-2 text-xs">
                                    <span v-if="today.revenue?.delta_percentage !== null && today.revenue?.delta_percentage !== undefined" :class="today.revenue.delta_percentage >= 0 ? 'text-emerald-400' : 'text-orange-400'" class="font-semibold">
                                        {{ today.revenue.delta_percentage >= 0 ? '▲' : '▼' }} {{ formatPercent(Math.abs(today.revenue.delta_percentage)) }}
                                    </span>
                                    <span class="text-slate-400">{{ formatNumber(today.revenue?.order_count) }} orders · t.o.v. {{ formatDayShort(today.revenue?.compare_date) }} zelfde uur</span>
                                </p>
                            </div>
                        </div>

                        <h3 class="mt-1 text-xs font-semibold uppercase tracking-[0.1em] text-slate-400">Reservaties</h3>
                        <ul v-if="today.reservations?.length" class="flex flex-col gap-2">
                            <li v-for="reservation in today.reservations" :key="reservation.id" class="flex items-center gap-3 rounded-xl border border-slate-800 bg-slate-950/70 px-3 py-2.5">
                                <span class="w-12 shrink-0 text-[15px] font-semibold text-white tabular-nums">{{ reservation.time || '—' }}</span>
                                <div class="min-w-0 grow">
                                    <p class="truncate text-[13px] font-semibold text-white">{{ reservation.name || 'Zonder naam' }}</p>
                                    <p class="truncate text-xs text-slate-400">{{ reservationMeta(reservation) }}</p>
                                </div>
                                <span class="shrink-0 whitespace-nowrap rounded-md px-2 py-1 text-[11px] font-semibold" :class="statusClass(reservation.status)">{{ reservation.status_label }}</span>
                            </li>
                        </ul>
                        <p v-else class="rounded-xl border border-dashed border-slate-700 px-3 py-4 text-center text-sm text-slate-400">Geen reservaties vandaag.</p>

                        <p class="mt-auto text-xs text-slate-400">
                            Morgen: {{ formatNumber(today.tomorrow?.reservation_count) }} {{ today.tomorrow?.reservation_count === 1 ? 'reservatie' : 'reservaties' }} · {{ formatNumber(today.tomorrow?.participants) }} personen
                        </p>
                    </section>

                    <section class="flex flex-wrap items-center justify-between gap-3 rounded-2xl border border-slate-800 bg-slate-900/90 px-5 py-4 shadow-xl">
                        <div>
                            <p class="text-sm font-medium text-slate-400">Omzet per personeelsuur</p>
                            <p class="mt-1 text-2xl font-bold text-white tabular-nums">{{ formatCurrency(today.staff?.revenue_per_hour) }}</p>
                        </div>
                        <p class="text-right text-xs leading-relaxed text-slate-400">
                            {{ formatNumber(today.staff?.active_count) }} ingecheckt<br>
                            {{ formatHours(today.staff?.hours) }} gewerkt vandaag
                        </p>
                    </section>
                </div>
            </div>

            <!-- Verdeling + topproducten -->
            <div class="flex flex-wrap items-stretch gap-4">
                <DashboardMixCard class="min-w-0 flex-[1_1_480px]" :title="`Verdeling omzet · ${periodShort}`" :groups="mixGroups" />

                <section class="flex min-w-0 flex-[1_1_480px] flex-col gap-3 rounded-2xl border border-slate-800 bg-slate-900/90 p-5 shadow-xl">
                    <div class="flex items-center justify-between gap-3">
                        <h2 class="text-base font-semibold text-white">Topproducten · {{ periodShort }}</h2>
                        <RouterLink to="/reporting" class="text-xs text-cyan-300 hover:text-cyan-200">Alle producten</RouterLink>
                    </div>

                    <div v-if="topProducts.length">
                        <div class="grid grid-cols-[minmax(0,1fr)_60px_100px] gap-3 border-b border-slate-800 pb-2 text-xs text-slate-400">
                            <span>Product</span><span class="text-right">Aantal</span><span class="text-right">Omzet</span>
                        </div>
                        <div v-for="product in topProducts" :key="`${product.label}-${product.category}`" class="grid grid-cols-[minmax(0,1fr)_60px_100px] items-center gap-3 border-b border-slate-800 py-3 text-sm">
                            <div class="flex min-w-0 flex-col gap-1.5">
                                <span class="truncate font-medium text-slate-100">{{ product.label }}</span>
                                <div class="h-1 rounded bg-slate-950/70">
                                    <div class="h-1 rounded bg-cyan-400" :style="{ width: `${topProductMax ? (product.revenue / topProductMax) * 100 : 0}%` }"></div>
                                </div>
                            </div>
                            <span class="text-right text-slate-400 tabular-nums">{{ formatNumber(product.quantity) }}</span>
                            <span class="text-right font-semibold text-white tabular-nums">{{ formatCurrency(product.revenue) }}</span>
                        </div>
                    </div>
                    <p v-else class="py-6 text-center text-sm text-slate-400">Geen verkopen in deze periode.</p>
                </section>
            </div>

            <!-- Schoolvakanties -->
            <section v-if="holidayComparisons.length" class="rounded-2xl border border-slate-800 bg-slate-900/90 p-5 shadow-xl">
                <h2 class="mb-4 text-base font-semibold text-white">Schoolvakanties t.o.v. vorig jaar</h2>
                <div class="grid grid-cols-[repeat(auto-fill,minmax(220px,1fr))] gap-3">
                    <article v-for="holiday in holidayComparisons" :key="holiday.key" class="rounded-xl border border-slate-800 bg-slate-950/70 px-4 py-3">
                        <p class="text-xs font-medium text-violet-300">{{ holiday.label }}</p>
                        <div class="mt-1 flex items-baseline justify-between gap-2">
                            <p class="text-xl font-bold text-white tabular-nums">{{ formatCurrency(holiday.current?.revenue, { round: true }) }}</p>
                            <span class="text-xs font-semibold" :class="holiday.delta_percentage === null ? 'text-slate-500' : holiday.delta_percentage >= 0 ? 'text-emerald-400' : 'text-orange-400'">
                                {{ holiday.delta_percentage === null ? 'n.v.t.' : `${holiday.delta_percentage >= 0 ? '▲' : '▼'} ${formatPercent(Math.abs(holiday.delta_percentage))}` }}
                            </span>
                        </div>
                        <p class="mt-0.5 text-xs text-slate-400">vorig jaar {{ formatCurrency(holiday.previous?.revenue, { round: true }) }} · {{ formatNumber(holiday.current?.order_count) }} orders</p>
                    </article>
                </div>
            </section>
        </template>
    </div>
</template>

<script setup>
import { computed, onBeforeUnmount, onMounted, ref } from 'vue'
import { RouterLink } from 'vue-router'
import { ArrowPathIcon } from '@heroicons/vue/20/solid'
import { fetchDashboard } from '../../analytics/services/analyticsApi'
import DashboardKpiCard from '../components/DashboardKpiCard.vue'
import DashboardTrendChart from '../components/DashboardTrendChart.vue'
import DashboardMixCard from '../components/DashboardMixCard.vue'
import { formatCurrency, formatDate, formatDayShort, formatNumber, formatPercent, todayIso } from '../utils/format'

const PERIODS = [
    { key: 'today', label: 'Vandaag' },
    { key: 'week', label: 'Week' },
    { key: 'month', label: 'Maand' },
    { key: 'custom', label: 'Vrij' },
]
const STORAGE_KEY = 'playdrive.backoffice.dashboard'
const REFRESH_MS = 5 * 60 * 1000

const stored = readStored()
const period = ref(stored.period || 'month')
const compare = ref(stored.compare || 'previous_year')
const customStart = ref(stored.customStart || `${todayIso().slice(0, 8)}01`)
const customEnd = ref(stored.customEnd || todayIso())

const loading = ref(true)
const refreshing = ref(false)
const error = ref('')
const dashboard = ref({})
const updatedAt = ref(null)
let timer = null

const kpis = computed(() => dashboard.value.kpis || {})
const today = computed(() => dashboard.value.today || {})
const topProducts = computed(() => dashboard.value.top_products || [])
const topProductMax = computed(() => Math.max(0, ...topProducts.value.map(p => Number(p.revenue) || 0)))
const holidayComparisons = computed(() => dashboard.value.holiday_comparisons || [])

const mixGroups = computed(() => [
    {
        title: 'Categorie',
        unit: 'st.',
        rows: (dashboard.value.category_breakdown || []).map(r => ({ label: r.label, amount: r.revenue, count: r.quantity })),
    },
    {
        title: 'Bron',
        unit: 'orders',
        rows: (dashboard.value.source_breakdown || []).map(r => ({ label: r.label, amount: r.revenue, count: r.count })),
    },
    {
        title: 'Betaalmethode',
        unit: 'orders',
        rows: (dashboard.value.payment_breakdown || []).map(r => ({ label: r.label, amount: r.amount, count: r.count })),
    },
])

const periodTitle = computed(() => {
    const p = dashboard.value.period
    if (!p) return 'Dashboard'
    const start = new Date(`${p.start}T00:00:00`)
    if (p.key === 'today') return `Vandaag · ${formatDayShort(p.start)}`
    if (p.key === 'month') {
        const label = new Intl.DateTimeFormat('nl-BE', { month: 'long', year: 'numeric' }).format(start)
        return label.charAt(0).toUpperCase() + label.slice(1)
    }
    if (p.key === 'week') return `Deze week · vanaf ${formatDayShort(p.start)}`
    return `${formatDate(p.start)} – ${formatDate(p.end)}`
})

const periodShort = computed(() => ({ today: 'vandaag', week: 'deze week', month: 'deze maand' }[period.value] || 'periode'))

const compareRangeLabel = computed(() => {
    const p = dashboard.value.period
    if (!p) return ''
    if (p.compare_start === p.compare_end) return formatDayShort(p.compare_start)
    return `${formatDate(p.compare_start)} – ${formatDate(p.compare_end)}`
})

const trendTitle = computed(() => {
    const trend = dashboard.value.trend
    if (!trend?.points?.length) return 'Omzet per dag'
    if (trend.bucket === 'week') return 'Omzet per week'
    const p = dashboard.value.period
    return p && trend.start === p.start ? `Omzet per dag · ${periodShort.value}` : 'Omzet per dag · laatste 30 dagen'
})

const updatedLabel = computed(() => (updatedAt.value
    ? new Intl.DateTimeFormat('nl-BE', { hour: '2-digit', minute: '2-digit' }).format(updatedAt.value)
    : '—'))

onMounted(() => {
    loadDashboard({ initial: true })
    timer = setInterval(() => loadDashboard(), REFRESH_MS)
})

onBeforeUnmount(() => clearInterval(timer))

function setPeriod(key) {
    period.value = key
    persistAndLoad()
}

function persistAndLoad() {
    try {
        localStorage.setItem(STORAGE_KEY, JSON.stringify({
            period: period.value,
            compare: compare.value,
            customStart: customStart.value,
            customEnd: customEnd.value,
        }))
    } catch (_) { /* opslag niet beschikbaar: geen probleem */ }
    loadDashboard()
}

function readStored() {
    try {
        return JSON.parse(localStorage.getItem(STORAGE_KEY) || '{}') || {}
    } catch (_) {
        return {}
    }
}

async function loadDashboard({ initial = false } = {}) {
    if (initial) loading.value = true
    refreshing.value = true

    const params = { period: period.value, compare: compare.value }
    if (period.value === 'custom') {
        params.start_date = customStart.value
        params.end_date = customEnd.value
        persistCustom()
    }

    try {
        dashboard.value = await fetchDashboard(params)
        updatedAt.value = new Date()
        error.value = ''
    } catch (e) {
        if (initial || !dashboard.value.kpis) {
            error.value = e?.response?.data?.message || 'Dashboard kon niet geladen worden.'
        }
    } finally {
        loading.value = false
        refreshing.value = false
    }
}

function persistCustom() {
    try {
        const current = readStored()
        localStorage.setItem(STORAGE_KEY, JSON.stringify({ ...current, period: period.value, customStart: customStart.value, customEnd: customEnd.value }))
    } catch (_) { /* negeren */ }
}

function reservationMeta(reservation) {
    return [
        reservation.participants ? `${reservation.participants} pers.` : null,
        reservation.event_type,
        reservation.stay_option,
    ].filter(Boolean).join(' · ')
}

function statusClass(status) {
    switch (status) {
        case 'checked_in':
        case 'checked_out':
        case 'paid':
            return 'bg-emerald-500/15 text-emerald-300'
        case 'confirmed':
            return 'bg-cyan-500/15 text-cyan-300'
        case 'no_show':
            return 'bg-orange-500/15 text-orange-300'
        default:
            return 'bg-slate-700/50 text-slate-300'
    }
}

function formatHours(hours) {
    const value = Number(hours || 0)
    return `${value.toLocaleString('nl-BE', { maximumFractionDigits: 1 })} u`
}
</script>
