<template>
    <section class="flex h-full min-h-0 flex-col gap-6">
        <div class="rounded-3xl border border-slate-800 bg-slate-900/90 p-6 shadow-xl">
            <div class="mb-6 flex flex-wrap items-center justify-between gap-4">
                <div>
                    <h1 class="text-2xl font-semibold text-white">Dagtotalen</h1>
                    <p class="mt-1 text-sm text-slate-400">
                        Boekhoudkundig overzicht per dag, met uitsplitsing per btw-tarief.
                    </p>
                </div>

                <a
                    :href="exportUrl"
                    class="inline-flex items-center gap-2 rounded-2xl border border-emerald-500/30 bg-emerald-500/10 px-4 py-2 text-sm font-semibold text-emerald-300 transition hover:bg-emerald-500/20"
                >
                    Export Excel
                </a>
            </div>

            <div class="grid gap-4 md:grid-cols-5">
                <div>
                    <label class="mb-2 block text-sm font-medium text-slate-300">Modus</label>
                    <select
                        v-model="filters.mode"
                        class="w-full rounded-2xl border border-slate-700 bg-slate-950 px-4 py-3 text-sm text-white"
                    >
                        <option value="month">Maand</option>
                        <option value="quarter">Kwartaal</option>
                        <option value="range">Start / einde</option>
                    </select>
                </div>

                <template v-if="filters.mode === 'month'">
                    <div>
                        <label class="mb-2 block text-sm font-medium text-slate-300">Jaar</label>
                        <select
                            v-model="filters.year"
                            class="w-full rounded-2xl border border-slate-700 bg-slate-950 px-4 py-3 text-sm text-white"
                        >
                            <option v-for="year in yearOptions" :key="year" :value="year">
                                {{ year }}
                            </option>
                        </select>
                    </div>

                    <div>
                        <label class="mb-2 block text-sm font-medium text-slate-300">Maand</label>
                        <select
                            v-model="filters.month"
                            class="w-full rounded-2xl border border-slate-700 bg-slate-950 px-4 py-3 text-sm text-white"
                        >
                            <option v-for="month in monthOptions" :key="month.value" :value="month.value">
                                {{ month.label }}
                            </option>
                        </select>
                    </div>
                </template>

                <template v-if="filters.mode === 'quarter'">
                    <div>
                        <label class="mb-2 block text-sm font-medium text-slate-300">Jaar</label>
                        <select
                            v-model="filters.year"
                            class="w-full rounded-2xl border border-slate-700 bg-slate-950 px-4 py-3 text-sm text-white"
                        >
                            <option v-for="year in yearOptions" :key="year" :value="year">
                                {{ year }}
                            </option>
                        </select>
                    </div>

                    <div>
                        <label class="mb-2 block text-sm font-medium text-slate-300">Kwartaal</label>
                        <select
                            v-model="filters.quarter"
                            class="w-full rounded-2xl border border-slate-700 bg-slate-950 px-4 py-3 text-sm text-white"
                        >
                            <option v-for="quarter in quarterOptions" :key="quarter.value" :value="quarter.value">
                                {{ quarter.label }}
                            </option>
                        </select>
                    </div>
                </template>

                <template v-if="filters.mode === 'range'">
                    <div>
                        <label class="mb-2 block text-sm font-medium text-slate-300">Start</label>
                        <input
                            v-model="filters.start"
                            type="date"
                            class="w-full rounded-2xl border border-slate-700 bg-slate-950 px-4 py-3 text-sm text-white"
                        />
                    </div>

                    <div>
                        <label class="mb-2 block text-sm font-medium text-slate-300">Einde</label>
                        <input
                            v-model="filters.end"
                            type="date"
                            class="w-full rounded-2xl border border-slate-700 bg-slate-950 px-4 py-3 text-sm text-white"
                        />
                    </div>
                </template>

                <div class="flex items-end">
                    <button
                        @click="loadData"
                        :disabled="loading"
                        class="w-full rounded-2xl border border-violet-500/30 bg-violet-500/10 px-4 py-3 text-sm font-semibold text-violet-300 transition hover:bg-violet-500/20 disabled:cursor-not-allowed disabled:opacity-60"
                    >
                        {{ loading ? 'Laden...' : 'Toepassen' }}
                    </button>
                </div>
            </div>

            <div class="mt-4 rounded-2xl border border-slate-800 bg-slate-950/60 px-4 py-3 text-sm text-slate-300">
                <span class="font-semibold text-white">Periode:</span>
                {{ period.start || '—' }} → {{ period.end || '—' }}
            </div>
        </div>

        <div class="rounded-3xl border border-slate-800 bg-slate-900/90 p-5 shadow-xl">
            <p class="mb-3 text-sm font-medium text-slate-300">Kolommen tonen</p>

            <div class="flex flex-wrap gap-3">
                <label
                    v-for="rate in availableRateToggles"
                    :key="rate.key"
                    class="inline-flex items-center gap-2 rounded-2xl border border-slate-700 bg-slate-950 px-3 py-2 text-sm text-slate-200"
                >
                    <input
                        v-model="visibleGroups[rate.key]"
                        type="checkbox"
                        class="h-4 w-4 rounded border-slate-600 bg-slate-900 text-violet-500 focus:ring-violet-500"
                    />
                    <span>{{ rate.label }}</span>
                </label>
            </div>
        </div>

        <div class="grid gap-4 md:grid-cols-3">
            <div class="rounded-3xl border border-slate-800 bg-slate-900/90 p-5 shadow-xl">
                <p class="text-sm text-slate-400">Totaal excl. btw</p>
                <p class="mt-2 text-2xl font-semibold text-white">{{ formatCurrency(totals.total_excl_vat) }}</p>
            </div>

            <div class="rounded-3xl border border-slate-800 bg-slate-900/90 p-5 shadow-xl">
                <p class="text-sm text-slate-400">Totaal btw</p>
                <p class="mt-2 text-2xl font-semibold text-white">{{ formatCurrency(totals.total_vat) }}</p>
            </div>

            <div class="rounded-3xl border border-slate-800 bg-slate-900/90 p-5 shadow-xl">
                <p class="text-sm text-slate-400">Totaal incl. btw</p>
                <p class="mt-2 text-2xl font-semibold text-white">{{ formatCurrency(totals.total_incl_vat) }}</p>
            </div>
        </div>

        <div class="min-h-0 overflow-hidden rounded-3xl border border-slate-800 bg-slate-900/90 shadow-xl">
            <div class="overflow-auto">
                <table class="min-w-full divide-y divide-slate-800 text-sm">
                    <thead class="bg-slate-950/80 text-slate-300">
                    <tr>
                        <th rowspan="2" class="px-4 py-3 text-left align-middle">Datum</th>

                        <template v-if="visibleGroups.rate0">
                            <th colspan="3" class="px-4 py-3 text-center">0%</th>
                        </template>

                        <template v-if="visibleGroups.rate6">
                            <th colspan="3" class="px-4 py-3 text-center">6%</th>
                        </template>

                        <template v-if="visibleGroups.rate12">
                            <th colspan="3" class="px-4 py-3 text-center">12%</th>
                        </template>

                        <template v-if="visibleGroups.rate21">
                            <th colspan="3" class="px-4 py-3 text-center">21%</th>
                        </template>

                        <template v-if="visibleGroups.total">
                            <th colspan="3" class="px-4 py-3 text-center">Totaal</th>
                        </template>
                    </tr>

                    <tr>
                        <template v-if="visibleGroups.rate0">
                            <th class="px-4 py-3 text-right">Excl.</th>
                            <th class="px-4 py-3 text-right">Btw</th>
                            <th class="px-4 py-3 text-right">Incl.</th>
                        </template>

                        <template v-if="visibleGroups.rate6">
                            <th class="px-4 py-3 text-right">Excl.</th>
                            <th class="px-4 py-3 text-right">Btw</th>
                            <th class="px-4 py-3 text-right">Incl.</th>
                        </template>

                        <template v-if="visibleGroups.rate12">
                            <th class="px-4 py-3 text-right">Excl.</th>
                            <th class="px-4 py-3 text-right">Btw</th>
                            <th class="px-4 py-3 text-right">Incl.</th>
                        </template>

                        <template v-if="visibleGroups.rate21">
                            <th class="px-4 py-3 text-right">Excl.</th>
                            <th class="px-4 py-3 text-right">Btw</th>
                            <th class="px-4 py-3 text-right">Incl.</th>
                        </template>

                        <template v-if="visibleGroups.total">
                            <th class="px-4 py-3 text-right">Excl.</th>
                            <th class="px-4 py-3 text-right">Btw</th>
                            <th class="px-4 py-3 text-right">Incl.</th>
                        </template>
                    </tr>
                    </thead>

                    <tbody class="divide-y divide-slate-800 text-slate-200">
                    <tr v-for="row in rows" :key="row.date" class="hover:bg-slate-800/30">
                        <td class="px-4 py-3">{{ formatDate(row.date) }}</td>

                        <template v-if="visibleGroups.rate0">
                            <td class="px-4 py-3 text-right">{{ formatCurrency(row.vat_breakdown['0']?.excl || 0) }}</td>
                            <td class="px-4 py-3 text-right">{{ formatCurrency(row.vat_breakdown['0']?.vat || 0) }}</td>
                            <td class="px-4 py-3 text-right">{{ formatCurrency(row.vat_breakdown['0']?.incl || 0) }}</td>
                        </template>

                        <template v-if="visibleGroups.rate6">
                            <td class="px-4 py-3 text-right">{{ formatCurrency(row.vat_breakdown['6']?.excl || 0) }}</td>
                            <td class="px-4 py-3 text-right">{{ formatCurrency(row.vat_breakdown['6']?.vat || 0) }}</td>
                            <td class="px-4 py-3 text-right">{{ formatCurrency(row.vat_breakdown['6']?.incl || 0) }}</td>
                        </template>

                        <template v-if="visibleGroups.rate12">
                            <td class="px-4 py-3 text-right">{{ formatCurrency(row.vat_breakdown['12']?.excl || 0) }}</td>
                            <td class="px-4 py-3 text-right">{{ formatCurrency(row.vat_breakdown['12']?.vat || 0) }}</td>
                            <td class="px-4 py-3 text-right">{{ formatCurrency(row.vat_breakdown['12']?.incl || 0) }}</td>
                        </template>

                        <template v-if="visibleGroups.rate21">
                            <td class="px-4 py-3 text-right">{{ formatCurrency(row.vat_breakdown['21']?.excl || 0) }}</td>
                            <td class="px-4 py-3 text-right">{{ formatCurrency(row.vat_breakdown['21']?.vat || 0) }}</td>
                            <td class="px-4 py-3 text-right">{{ formatCurrency(row.vat_breakdown['21']?.incl || 0) }}</td>
                        </template>

                        <template v-if="visibleGroups.total">
                            <td class="px-4 py-3 text-right">{{ formatCurrency(row.total_excl_vat) }}</td>
                            <td class="px-4 py-3 text-right">{{ formatCurrency(row.total_vat) }}</td>
                            <td class="px-4 py-3 text-right font-semibold text-white">{{ formatCurrency(row.total_incl_vat) }}</td>
                        </template>
                    </tr>

                    <tr v-if="rows.length" class="bg-slate-950/70 font-semibold text-white">
                        <td class="px-4 py-3">Totaal</td>

                        <template v-if="visibleGroups.rate0">
                            <td class="px-4 py-3 text-right">{{ formatCurrency(totals.vat_breakdown['0']?.excl || 0) }}</td>
                            <td class="px-4 py-3 text-right">{{ formatCurrency(totals.vat_breakdown['0']?.vat || 0) }}</td>
                            <td class="px-4 py-3 text-right">{{ formatCurrency(totals.vat_breakdown['0']?.incl || 0) }}</td>
                        </template>

                        <template v-if="visibleGroups.rate6">
                            <td class="px-4 py-3 text-right">{{ formatCurrency(totals.vat_breakdown['6']?.excl || 0) }}</td>
                            <td class="px-4 py-3 text-right">{{ formatCurrency(totals.vat_breakdown['6']?.vat || 0) }}</td>
                            <td class="px-4 py-3 text-right">{{ formatCurrency(totals.vat_breakdown['6']?.incl || 0) }}</td>
                        </template>

                        <template v-if="visibleGroups.rate12">
                            <td class="px-4 py-3 text-right">{{ formatCurrency(totals.vat_breakdown['12']?.excl || 0) }}</td>
                            <td class="px-4 py-3 text-right">{{ formatCurrency(totals.vat_breakdown['12']?.vat || 0) }}</td>
                            <td class="px-4 py-3 text-right">{{ formatCurrency(totals.vat_breakdown['12']?.incl || 0) }}</td>
                        </template>

                        <template v-if="visibleGroups.rate21">
                            <td class="px-4 py-3 text-right">{{ formatCurrency(totals.vat_breakdown['21']?.excl || 0) }}</td>
                            <td class="px-4 py-3 text-right">{{ formatCurrency(totals.vat_breakdown['21']?.vat || 0) }}</td>
                            <td class="px-4 py-3 text-right">{{ formatCurrency(totals.vat_breakdown['21']?.incl || 0) }}</td>
                        </template>

                        <template v-if="visibleGroups.total">
                            <td class="px-4 py-3 text-right">{{ formatCurrency(totals.total_excl_vat) }}</td>
                            <td class="px-4 py-3 text-right">{{ formatCurrency(totals.total_vat) }}</td>
                            <td class="px-4 py-3 text-right">{{ formatCurrency(totals.total_incl_vat) }}</td>
                        </template>
                    </tr>

                    <tr v-if="!rows.length && !loading">
                        <td :colspan="tableColspan" class="px-4 py-10 text-center text-slate-400">
                            Geen gegevens gevonden voor deze periode.
                        </td>
                    </tr>

                    <tr v-if="loading">
                        <td :colspan="tableColspan" class="px-4 py-10 text-center text-slate-400">
                            Gegevens laden...
                        </td>
                    </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </section>
</template>

<script setup>
import { computed, onMounted, reactive, ref } from 'vue'
import { fetchDayTotals, getDayTotalsExportUrl } from '../services/dayTotalsApi'

const now = new Date()

const rows = ref([])
const loading = ref(false)

const period = reactive({
    start: null,
    end: null,
})

const totals = reactive({
    total_excl_vat: 0,
    total_vat: 0,
    total_incl_vat: 0,
    vat_breakdown: {
        0: { excl: 0, vat: 0, incl: 0 },
        6: { excl: 0, vat: 0, incl: 0 },
        12: { excl: 0, vat: 0, incl: 0 },
        21: { excl: 0, vat: 0, incl: 0 },
    },
})

const filters = reactive({
    mode: 'month',
    year: now.getFullYear(),
    month: now.getMonth() + 1,
    quarter: Math.floor(now.getMonth() / 3) + 1,
    start: new Date().toISOString().slice(0, 10),
    end: new Date().toISOString().slice(0, 10),
})

const visibleGroups = reactive({
    rate0: false,
    rate6: true,
    rate12: false,
    rate21: true,
    total: true,
})

const availableRateToggles = [
    { key: 'rate0', label: '0%' },
    { key: 'rate6', label: '6%' },
    { key: 'rate12', label: '12%' },
    { key: 'rate21', label: '21%' },
    { key: 'total', label: 'Totaal' },
]

const yearOptions = computed(() => {
    const currentYear = new Date().getFullYear()
    const years = []

    for (let year = currentYear; year >= 2022; year--) {
        years.push(year)
    }

    return years
})

const monthOptions = [
    { value: 1, label: 'Januari' },
    { value: 2, label: 'Februari' },
    { value: 3, label: 'Maart' },
    { value: 4, label: 'April' },
    { value: 5, label: 'Mei' },
    { value: 6, label: 'Juni' },
    { value: 7, label: 'Juli' },
    { value: 8, label: 'Augustus' },
    { value: 9, label: 'September' },
    { value: 10, label: 'Oktober' },
    { value: 11, label: 'November' },
    { value: 12, label: 'December' },
]

const quarterOptions = [
    { value: 1, label: 'Q1' },
    { value: 2, label: 'Q2' },
    { value: 3, label: 'Q3' },
    { value: 4, label: 'Q4' },
]

const requestParams = computed(() => ({
    mode: filters.mode,
    year: filters.year,
    month: filters.month,
    quarter: filters.quarter,
    start: filters.start,
    end: filters.end,
}))

const exportUrl = computed(() => getDayTotalsExportUrl(requestParams.value))

const tableColspan = computed(() => {
    let count = 1
    if (visibleGroups.rate0) count += 3
    if (visibleGroups.rate6) count += 3
    if (visibleGroups.rate12) count += 3
    if (visibleGroups.rate21) count += 3
    if (visibleGroups.total) count += 3
    return count
})

async function loadData() {
    loading.value = true

    try {
        const data = await fetchDayTotals(requestParams.value)

        rows.value = data.rows ?? []
        period.start = data.period?.start ?? null
        period.end = data.period?.end ?? null

        totals.total_excl_vat = data.totals?.total_excl_vat ?? 0
        totals.total_vat = data.totals?.total_vat ?? 0
        totals.total_incl_vat = data.totals?.total_incl_vat ?? 0
        totals.vat_breakdown = data.totals?.vat_breakdown ?? {
            0: { excl: 0, vat: 0, incl: 0 },
            6: { excl: 0, vat: 0, incl: 0 },
            12: { excl: 0, vat: 0, incl: 0 },
            21: { excl: 0, vat: 0, incl: 0 },
        }

        updateVisibleGroupsFromData()
    } finally {
        loading.value = false
    }
}

function updateVisibleGroupsFromData() {
    const has0 = hasAnyAmountForRate('0')
    const has6 = hasAnyAmountForRate('6')
    const has12 = hasAnyAmountForRate('12')
    const has21 = hasAnyAmountForRate('21')

    if (!has0) visibleGroups.rate0 = false
    if (!has6) visibleGroups.rate6 = false
    if (!has12) visibleGroups.rate12 = false
    if (!has21) visibleGroups.rate21 = false
}

function hasAnyAmountForRate(rate) {
    return rows.value.some(row => {
        const breakdown = row.vat_breakdown?.[rate]
        return Number(breakdown?.excl || 0) !== 0 ||
            Number(breakdown?.vat || 0) !== 0 ||
            Number(breakdown?.incl || 0) !== 0
    })
}

function formatCurrency(value) {
    return new Intl.NumberFormat('nl-BE', {
        style: 'currency',
        currency: 'EUR',
    }).format(Number(value || 0))
}

function formatDate(value) {
    if (!value) {
        return '—'
    }

    const [year, month, day] = value.split('-')
    return `${day}/${month}/${year}`
}

onMounted(loadData)
</script>
