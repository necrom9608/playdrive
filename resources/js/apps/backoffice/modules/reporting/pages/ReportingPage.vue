<template>
    <div class="space-y-6">
        <header class="rounded-3xl border border-slate-800 bg-slate-900/90 p-6 shadow-xl">
            <div class="flex flex-wrap items-end justify-between gap-4">
                <div>
                    <p class="text-sm font-medium uppercase tracking-[0.16em] text-violet-300">Backoffice · Rapportering</p>
                    <h1 class="mt-2 text-3xl font-bold text-white">Vergelijk periodes, vakantieblokken en productmix</h1>
                    <p class="mt-2 max-w-4xl text-sm text-slate-400">
                        Vergelijk twee periodes in één grafiek, filter op bron of betaalmethode en bekijk meteen omzet, categorieën, producten en reservaties.
                    </p>
                </div>
            </div>

            <div class="mt-6 grid gap-4 xl:grid-cols-6">
                <label class="block space-y-2 text-sm text-slate-300 xl:col-span-2">
                    <span>Vergelijkingsmodus</span>
                    <select v-model="filters.mode" class="w-full rounded-2xl border border-slate-700 bg-slate-950 px-4 py-3 text-white outline-none focus:border-violet-500">
                        <option value="previous_period">Vorige periode</option>
                        <option value="same_period_last_year">Zelfde periode vorig jaar</option>
                        <option value="custom">Custom vergelijking</option>
                        <option value="holiday">Schoolvakantie</option>
                    </select>
                </label>

                <label class="block space-y-2 text-sm text-slate-300">
                    <span>Metric</span>
                    <select v-model="filters.metric" class="w-full rounded-2xl border border-slate-700 bg-slate-950 px-4 py-3 text-white outline-none focus:border-violet-500">
                        <option value="revenue">Omzet</option>
                        <option value="orders">Orders</option>
                        <option value="avg_order_value">Gemiddelde ticketwaarde</option>
                        <option value="items">Items</option>
                    </select>
                </label>

                <label class="block space-y-2 text-sm text-slate-300">
                    <span>Bron</span>
                    <select v-model="filters.source" class="w-full rounded-2xl border border-slate-700 bg-slate-950 px-4 py-3 text-white outline-none focus:border-violet-500">
                        <option value="">Alles</option>
                        <option value="loyverse">Loyverse import</option>
                        <option value="walk_in">Losse verkoop</option>
                        <option value="reservation">Reservatie</option>
                    </select>
                </label>

                <label class="block space-y-2 text-sm text-slate-300">
                    <span>Betaalmethode</span>
                    <select v-model="filters.payment_method" class="w-full rounded-2xl border border-slate-700 bg-slate-950 px-4 py-3 text-white outline-none focus:border-violet-500">
                        <option value="">Alles</option>
                        <option value="cash">Cash</option>
                        <option value="card">Kaart</option>
                        <option value="bancontact">Bancontact</option>
                    </select>
                </label>

                <div class="flex items-end">
                    <button type="button" class="w-full rounded-2xl bg-violet-500 px-4 py-3 text-sm font-semibold text-slate-950 transition hover:bg-violet-400" @click="loadReporting">
                        Rapport laden
                    </button>
                </div>
            </div>

            <div v-if="filters.mode !== 'holiday'" class="mt-4 space-y-4">
                <div class="flex flex-wrap items-center gap-3">
                    <span class="text-sm text-slate-300">Periode invoer</span>
                    <button
                        type="button"
                        class="rounded-2xl border px-4 py-2 text-sm font-medium transition"
                        :class="filters.period_input_mode === 'range' ? 'border-violet-500 bg-violet-500/15 text-violet-200' : 'border-slate-700 bg-slate-950 text-slate-300 hover:border-slate-600'"
                        @click="filters.period_input_mode = 'range'"
                    >
                        Start / einde
                    </button>
                    <button
                        type="button"
                        class="rounded-2xl border px-4 py-2 text-sm font-medium transition"
                        :class="filters.period_input_mode === 'month' ? 'border-violet-500 bg-violet-500/15 text-violet-200' : 'border-slate-700 bg-slate-950 text-slate-300 hover:border-slate-600'"
                        @click="filters.period_input_mode = 'month'"
                    >
                        Maand selecteren
                    </button>
                </div>

                <div v-if="filters.period_input_mode === 'range'" class="grid gap-4 xl:grid-cols-4">
                    <label class="block space-y-2 text-sm text-slate-300">
                        <span>Periode start</span>
                        <input v-model="filters.start_date" type="date" class="w-full rounded-2xl border border-slate-700 bg-slate-950 px-4 py-3 text-white outline-none focus:border-violet-500">
                    </label>
                    <label class="block space-y-2 text-sm text-slate-300">
                        <span>Periode einde</span>
                        <input v-model="filters.end_date" type="date" class="w-full rounded-2xl border border-slate-700 bg-slate-950 px-4 py-3 text-white outline-none focus:border-violet-500">
                    </label>
                    <label v-if="filters.mode === 'custom'" class="block space-y-2 text-sm text-slate-300">
                        <span>Vergelijk start</span>
                        <input v-model="filters.compare_start_date" type="date" class="w-full rounded-2xl border border-slate-700 bg-slate-950 px-4 py-3 text-white outline-none focus:border-violet-500">
                    </label>
                    <label v-if="filters.mode === 'custom'" class="block space-y-2 text-sm text-slate-300">
                        <span>Vergelijk einde</span>
                        <input v-model="filters.compare_end_date" type="date" class="w-full rounded-2xl border border-slate-700 bg-slate-950 px-4 py-3 text-white outline-none focus:border-violet-500">
                    </label>
                </div>

                <div v-else class="grid gap-4 xl:grid-cols-4">
                    <label class="block space-y-2 text-sm text-slate-300">
                        <span>Maand A</span>
                        <input v-model="filters.month" type="month" class="w-full rounded-2xl border border-slate-700 bg-slate-950 px-4 py-3 text-white outline-none focus:border-violet-500">
                    </label>
                    <div class="rounded-2xl border border-slate-800 bg-slate-950/70 px-4 py-3 text-sm text-slate-300">
                        <div class="text-xs uppercase tracking-[0.16em] text-slate-500">Periode A</div>
                        <div class="mt-2 font-medium text-white">{{ monthPrimaryRangeLabel }}</div>
                    </div>
                    <label v-if="filters.mode === 'custom'" class="block space-y-2 text-sm text-slate-300">
                        <span>Maand B</span>
                        <input v-model="filters.compare_month" type="month" class="w-full rounded-2xl border border-slate-700 bg-slate-950 px-4 py-3 text-white outline-none focus:border-violet-500">
                    </label>
                    <div class="rounded-2xl border border-slate-800 bg-slate-950/70 px-4 py-3 text-sm text-slate-300">
                        <div class="text-xs uppercase tracking-[0.16em] text-slate-500">Vergelijkperiode</div>
                        <div class="mt-2 font-medium text-white">{{ monthComparisonRangeLabel }}</div>
                    </div>
                </div>
            </div>

            <div v-else class="mt-4 grid gap-4 xl:grid-cols-3">
                <label class="block space-y-2 text-sm text-slate-300 xl:col-span-2">
                    <span>Schoolvakantie</span>
                    <select v-model="filters.holiday_key" class="w-full rounded-2xl border border-slate-700 bg-slate-950 px-4 py-3 text-white outline-none focus:border-violet-500">
                        <option v-for="option in holidayKeys" :key="option.key" :value="option.key">{{ option.label }}</option>
                    </select>
                </label>
                <div class="grid gap-4 md:grid-cols-2 xl:col-span-1">
                    <label class="block space-y-2 text-sm text-slate-300">
                        <span>Jaar A</span>
                        <select v-model.number="filters.holiday_year" class="w-full rounded-2xl border border-slate-700 bg-slate-950 px-4 py-3 text-white outline-none focus:border-violet-500">
                            <option v-for="year in holidayYears" :key="year" :value="year">{{ year }}</option>
                        </select>
                    </label>
                    <label class="block space-y-2 text-sm text-slate-300">
                        <span>Jaar B</span>
                        <select v-model.number="filters.holiday_compare_year" class="w-full rounded-2xl border border-slate-700 bg-slate-950 px-4 py-3 text-white outline-none focus:border-violet-500">
                            <option v-for="year in holidayYears" :key="`compare-${year}`" :value="year">{{ year }}</option>
                        </select>
                    </label>
                </div>
            </div>

            <div class="mt-4 grid gap-3 md:grid-cols-2">
                <div class="rounded-2xl border border-slate-800 bg-slate-950/70 px-4 py-3 text-sm text-slate-300">
                    <div class="text-xs uppercase tracking-[0.16em] text-slate-500">Actieve periode A</div>
                    <div class="mt-2 font-medium text-white">{{ resolvedPrimaryPeriodLabel }}</div>
                </div>
                <div class="rounded-2xl border border-slate-800 bg-slate-950/70 px-4 py-3 text-sm text-slate-300">
                    <div class="text-xs uppercase tracking-[0.16em] text-slate-500">Actieve periode B</div>
                    <div class="mt-2 font-medium text-white">{{ resolvedComparisonPeriodLabel }}</div>
                </div>
            </div>
        </header>

        <div v-if="loading" class="rounded-3xl border border-slate-800 bg-slate-900/90 px-6 py-16 text-center text-sm text-slate-400 shadow-xl">
            Rapport laden...
        </div>

        <div v-else class="space-y-6">
            <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-4">
                <StatsCard label="Periode A" :value="formatCurrency(reporting.summary?.primary?.revenue)" :subtext="reporting.primary_period?.label || ''" />
                <StatsCard label="Periode B" :value="formatCurrency(reporting.summary?.comparison?.revenue)" :subtext="reporting.comparison_period?.label || ''" />
                <StatsCard label="Verschil omzet" :value="deltaLabel" subtext="Vergelijking netto-omzet" :badge="deltaBadge" />
                <StatsCard label="Gem. ticket A" :value="formatCurrency(reporting.summary?.primary?.avg_order_value)" :subtext="`${formatNumber(reporting.summary?.primary?.order_count)} orders`" />
            </div>

            <LineComparisonChart
                title="Periodevergelijking in één grafiek"
                :subtitle="chartSubtitle"
                :primary="reporting.series?.primary || []"
                :comparison="reporting.series?.comparison || []"
                :primary-label="reporting.primary_period?.label || 'Periode A'"
                :comparison-label="reporting.comparison_period?.label || 'Periode B'"
            />

            <div class="grid gap-6 xl:grid-cols-3">
                <BreakdownTableCard
                    title="Categorieën"
                    subtitle="Omzet en aantallen in de geselecteerde hoofdperiode."
                    :rows="reporting.category_breakdown || []"
                    :columns="[
                        { key: 'revenue', label: 'Omzet', type: 'currency' },
                        { key: 'quantity', label: 'Aantal', type: 'number' },
                    ]"
                    first-column-label="Categorie"
                />

                <BreakdownTableCard
                    title="Topproducten"
                    subtitle="Beste verkopers in de hoofdperiode."
                    :rows="reporting.top_products || []"
                    :columns="[
                        { key: 'category', label: 'Categorie', type: 'text' },
                        { key: 'quantity', label: 'Aantal', type: 'number' },
                        { key: 'revenue', label: 'Omzet', type: 'currency' },
                    ]"
                    first-column-label="Product"
                />

                <section class="rounded-3xl border border-slate-800 bg-slate-900/90 p-5 shadow-xl">
                    <div class="mb-4">
                        <h3 class="text-lg font-semibold text-white">Reservaties & bronmix</h3>
                        <p class="mt-1 text-sm text-slate-400">Combineer omzetdata met reservatie- en broninzichten.</p>
                    </div>

                    <div class="grid grid-cols-2 gap-3">
                        <div class="rounded-2xl border border-slate-800 bg-slate-950/70 px-4 py-3">
                            <div class="text-xs uppercase tracking-[0.16em] text-slate-500">Reservaties</div>
                            <div class="mt-2 text-2xl font-semibold text-white">{{ formatNumber(reporting.reservation_overview?.reservation_count) }}</div>
                        </div>
                        <div class="rounded-2xl border border-slate-800 bg-slate-950/70 px-4 py-3">
                            <div class="text-xs uppercase tracking-[0.16em] text-slate-500">Bezoekers</div>
                            <div class="mt-2 text-2xl font-semibold text-white">{{ formatNumber(reporting.reservation_overview?.visitor_count) }}</div>
                        </div>
                    </div>

                    <div class="mt-4 space-y-3">
                        <article v-for="item in reporting.source_breakdown || []" :key="item.key" class="rounded-2xl border border-slate-800 bg-slate-950/70 px-4 py-3">
                            <div class="flex items-center justify-between gap-3">
                                <div>
                                    <p class="font-semibold text-white">{{ item.label }}</p>
                                    <p class="mt-1 text-xs text-slate-400">{{ formatNumber(item.count) }} orders</p>
                                </div>
                                <div class="font-semibold text-violet-300">{{ formatCurrency(item.revenue) }}</div>
                            </div>
                        </article>
                    </div>

                    <div class="mt-4 grid gap-3 text-sm text-slate-300">
                        <div class="rounded-2xl border border-slate-800 bg-slate-950/70 px-4 py-3">No-shows: <span class="font-semibold text-white">{{ formatNumber(reporting.reservation_overview?.no_show_count) }}</span></div>
                        <div class="rounded-2xl border border-slate-800 bg-slate-950/70 px-4 py-3">Gem. groepsgrootte: <span class="font-semibold text-white">{{ formatDecimal(reporting.reservation_overview?.average_group_size) }}</span></div>
                    </div>
                </section>
            </div>
        </div>
    </div>
</template>

<script setup>
import { computed, onMounted, reactive, ref } from 'vue'
import StatsCard from '../../analytics/components/StatsCard.vue'
import LineComparisonChart from '../../analytics/components/LineComparisonChart.vue'
import BreakdownTableCard from '../../analytics/components/BreakdownTableCard.vue'
import { fetchReporting } from '../../analytics/services/analyticsApi'

const loading = ref(true)
const reporting = ref({})

const today = new Date()

const filters = reactive({
    mode: 'previous_period',
    metric: 'revenue',
    source: '',
    payment_method: '',
    period_input_mode: 'range',
    start_date: defaultStartDate(),
    end_date: defaultEndDate(),
    compare_start_date: '',
    compare_end_date: '',
    month: defaultMonthValue(),
    compare_month: previousMonthValue(defaultMonthValue()),
    holiday_key: 'easter',
    holiday_year: today.getFullYear(),
    holiday_compare_year: today.getFullYear() - 1,
})

const holidayKeys = computed(() => {
    const map = new Map()
    ;(reporting.value.holiday_options || []).forEach(item => {
        if (!map.has(item.key)) {
            map.set(item.key, { key: item.key, label: item.label.replace(/\s\d{4}$/, '') })
        }
    })
    return Array.from(map.values())
})

const holidayYears = computed(() => {
    const years = new Set((reporting.value.holiday_options || []).map(item => item.year))
    return Array.from(years).sort((a, b) => b - a)
})

const deltaValue = computed(() => {
    const a = Number(reporting.value.summary?.primary?.revenue || 0)
    const b = Number(reporting.value.summary?.comparison?.revenue || 0)
    if (!b) return null
    return ((a - b) / b) * 100
})
const deltaLabel = computed(() => deltaValue.value === null ? 'n.v.t.' : `${deltaValue.value >= 0 ? '+' : ''}${formatDecimal(deltaValue.value)}%`)
const deltaBadge = computed(() => deltaValue.value === null ? '' : deltaValue.value >= 0 ? 'Sterker' : 'Zachter')
const chartSubtitle = computed(() => {
    const labelMap = {
        revenue: 'Netto-omzet per dagindex',
        orders: 'Aantal orders per dagindex',
        avg_order_value: 'Gemiddelde ticketwaarde per dagindex',
        items: 'Aantal items per dagindex',
    }
    return labelMap[filters.metric] || 'Vergelijking per dagindex'
})

const monthPrimaryRangeLabel = computed(() => describeMonthRange(filters.month))
const monthComparisonRangeLabel = computed(() => {
    if (filters.mode === 'custom') {
        return describeMonthRange(filters.compare_month)
    }
    return describeDerivedComparisonRange()
})
const resolvedPrimaryPeriodLabel = computed(() => reporting.value.primary_period?.label || previewPrimaryPeriodLabel())
const resolvedComparisonPeriodLabel = computed(() => reporting.value.comparison_period?.label || previewComparisonPeriodLabel())

onMounted(loadReporting)

async function loadReporting() {
    loading.value = true
    try {
        const params = {
            metric: filters.metric,
            source: filters.source || undefined,
            payment_method: filters.payment_method || undefined,
        }

        if (filters.mode === 'holiday') {
            params.holiday_key = filters.holiday_key
            params.holiday_year = filters.holiday_year
            params.holiday_compare_year = filters.holiday_compare_year
        } else {
            const primaryRange = filters.period_input_mode === 'month'
                ? monthRange(filters.month)
                : { start: filters.start_date, end: filters.end_date }

            params.start_date = primaryRange.start
            params.end_date = primaryRange.end
            params.compare_mode = filters.mode

            if (filters.mode === 'custom') {
                const comparisonRange = filters.period_input_mode === 'month'
                    ? monthRange(filters.compare_month)
                    : { start: filters.compare_start_date, end: filters.compare_end_date }

                params.compare_start_date = comparisonRange.start
                params.compare_end_date = comparisonRange.end
            }
        }

        reporting.value = await fetchReporting(params)
    } finally {
        loading.value = false
    }
}

function previewPrimaryPeriodLabel() {
    if (filters.mode === 'holiday') {
        return 'Wordt bepaald door gekozen schoolvakantie'
    }

    if (filters.period_input_mode === 'month') {
        return describeMonthRange(filters.month)
    }

    return formatDateRange(filters.start_date, filters.end_date)
}

function previewComparisonPeriodLabel() {
    if (filters.mode === 'holiday') {
        return 'Wordt bepaald door gekozen schoolvakantie'
    }

    if (filters.mode === 'custom') {
        if (filters.period_input_mode === 'month') {
            return describeMonthRange(filters.compare_month)
        }
        return formatDateRange(filters.compare_start_date, filters.compare_end_date)
    }

    return describeDerivedComparisonRange()
}

function describeDerivedComparisonRange() {
    const primaryRange = filters.period_input_mode === 'month'
        ? monthRange(filters.month)
        : { start: filters.start_date, end: filters.end_date }

    if (!primaryRange.start || !primaryRange.end) {
        return 'Kies eerst een geldige periode'
    }

    const start = new Date(`${primaryRange.start}T00:00:00`)
    const end = new Date(`${primaryRange.end}T00:00:00`)

    if (Number.isNaN(start.getTime()) || Number.isNaN(end.getTime())) {
        return 'Kies eerst een geldige periode'
    }

    if (filters.mode === 'same_period_last_year') {
        const compareStart = new Date(start)
        compareStart.setFullYear(compareStart.getFullYear() - 1)
        const compareEnd = new Date(end)
        compareEnd.setFullYear(compareEnd.getFullYear() - 1)
        return formatDateRange(dateToIso(compareStart), dateToIso(compareEnd))
    }

    const days = Math.round((end - start) / 86400000) + 1
    const compareEnd = new Date(start)
    compareEnd.setDate(compareEnd.getDate() - 1)
    const compareStart = new Date(compareEnd)
    compareStart.setDate(compareStart.getDate() - (days - 1))

    return formatDateRange(dateToIso(compareStart), dateToIso(compareEnd))
}

function monthRange(value) {
    if (!value || !/^\d{4}-\d{2}$/.test(value)) {
        return { start: '', end: '' }
    }

    const [year, month] = value.split('-').map(Number)
    const start = new Date(year, month - 1, 1)
    const end = new Date(year, month, 0)

    return {
        start: dateToIso(start),
        end: dateToIso(end),
    }
}

function describeMonthRange(value) {
    const range = monthRange(value)
    return formatDateRange(range.start, range.end)
}

function formatDateRange(start, end) {
    if (!start || !end) {
        return 'Kies eerst een geldige periode'
    }

    return `${formatDate(start)} t.e.m. ${formatDate(end)}`
}

function formatDate(value) {
    if (!value) return '—'
    const [year, month, day] = value.split('-')
    if (!year || !month || !day) return value
    return `${day}/${month}/${year}`
}

function dateToIso(date) {
    return `${date.getFullYear()}-${String(date.getMonth() + 1).padStart(2, '0')}-${String(date.getDate()).padStart(2, '0')}`
}

function formatCurrency(value) {
    return new Intl.NumberFormat('nl-BE', { style: 'currency', currency: 'EUR' }).format(Number(value || 0))
}
function formatNumber(value) {
    return new Intl.NumberFormat('nl-BE').format(Number(value || 0))
}
function formatDecimal(value) {
    return new Intl.NumberFormat('nl-BE', { minimumFractionDigits: 1, maximumFractionDigits: 1 }).format(Number(value || 0))
}
function defaultStartDate() {
    const now = new Date()
    return `${now.getFullYear()}-${String(now.getMonth() + 1).padStart(2, '0')}-01`
}
function defaultEndDate() {
    const now = new Date()
    return `${now.getFullYear()}-${String(now.getMonth() + 1).padStart(2, '0')}-${String(now.getDate()).padStart(2, '0')}`
}
function defaultMonthValue() {
    const now = new Date()
    return `${now.getFullYear()}-${String(now.getMonth() + 1).padStart(2, '0')}`
}
function previousMonthValue(value) {
    if (!value || !/^\d{4}-\d{2}$/.test(value)) {
        return defaultMonthValue()
    }

    const [year, month] = value.split('-').map(Number)
    const date = new Date(year, month - 2, 1)
    return `${date.getFullYear()}-${String(date.getMonth() + 1).padStart(2, '0')}`
}
</script>
