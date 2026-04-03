<template>
    <div class="space-y-6">
        <header class="flex flex-wrap items-end justify-between gap-4 rounded-3xl border border-slate-800 bg-slate-900/90 p-6 shadow-xl">
            <div>
                <p class="text-sm font-medium uppercase tracking-[0.16em] text-cyan-300">Backoffice · Dashboard</p>
                <h1 class="mt-2 text-3xl font-bold text-white">Bedrijfsinzichten in één oogopslag</h1>
                <p class="mt-2 max-w-3xl text-sm text-slate-400">
                    Snel overzicht van omzet, bronverdeling, topverkopers en schoolvakanties. Loyverse-import en nieuwe PlayDrive-verkopen zitten samen in één scherm.
                </p>
            </div>

            <button
                type="button"
                class="rounded-2xl border border-slate-700 bg-slate-950/80 px-4 py-3 text-sm font-semibold text-slate-200 transition hover:border-cyan-400/40 hover:text-white"
                @click="loadDashboard"
            >
                Vernieuwen
            </button>
        </header>

        <div v-if="loading" class="rounded-3xl border border-slate-800 bg-slate-900/90 px-6 py-16 text-center text-sm text-slate-400 shadow-xl">
            Dashboard laden...
        </div>

        <div v-else class="space-y-6">
            <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-4">
                <StatsCard label="Omzet vandaag" :value="formatCurrency(summary.today?.revenue)" :subtext="`${summary.today?.order_count || 0} orders`" />
                <StatsCard label="Omzet deze maand" :value="formatCurrency(summary.month?.revenue)" :subtext="`${summary.month?.order_count || 0} orders`" />
                <StatsCard label="Gemiddelde ticketwaarde" :value="formatCurrency(summary.month?.avg_order_value)" subtext="Laatste 30 dagen en import inbegrepen" />
                <StatsCard label="Reservaties deze maand" :value="formatNumber(reservationOverview.reservation_count)" :subtext="`Gem. groepsgrootte: ${formatDecimal(reservationOverview.average_group_size)}`" />
            </div>

            <div class="grid gap-6 xl:grid-cols-[minmax(0,1.4fr)_minmax(0,0.8fr)]">
                <LineComparisonChart
                    title="Omzettrend laatste 30 dagen"
                    subtitle="Dagelijkse netto-omzet over de meest recente 30 dagen. Handig om pieken meteen te spotten."
                    :primary="revenueTrend"
                    :comparison="comparisonTrend"
                    primary-label="Laatste 30 dagen"
                    comparison-label="Vorige 30 dagen"
                />

                <BreakdownTableCard
                    title="Betalingsmix deze maand"
                    subtitle="Cash, kaart en andere methodes met omzet en aantallen."
                    :rows="paymentBreakdown"
                    :columns="[
                        { key: 'amount', label: 'Omzet', type: 'currency' },
                        { key: 'count', label: 'Orders', type: 'number' },
                    ]"
                    first-column-label="Methode"
                />
            </div>

            <div class="grid gap-6 xl:grid-cols-3">
                <BreakdownTableCard
                    title="Omzet per categorie"
                    subtitle="Inkom, drank, snacks en legacy categorieën."
                    :rows="categoryBreakdown"
                    :columns="[
                        { key: 'revenue', label: 'Omzet', type: 'currency' },
                        { key: 'quantity', label: 'Aantal', type: 'number' },
                    ]"
                    first-column-label="Categorie"
                />

                <BreakdownTableCard
                    title="Topproducten"
                    subtitle="Meest verkochte artikels op omzetbasis."
                    :rows="topProducts"
                    :columns="[
                        { key: 'category', label: 'Categorie', type: 'text' },
                        { key: 'quantity', label: 'Aantal', type: 'number' },
                        { key: 'revenue', label: 'Omzet', type: 'currency' },
                    ]"
                    first-column-label="Product"
                />

                <section class="rounded-3xl border border-slate-800 bg-slate-900/90 p-5 shadow-xl">
                    <div class="mb-4">
                        <h3 class="text-lg font-semibold text-white">Bronnen & reservaties</h3>
                        <p class="mt-1 text-sm text-slate-400">Verdeling tussen Loyverse, reservaties en losse verkoop plus reservatiestatussen.</p>
                    </div>

                    <div class="space-y-3">
                        <article v-for="item in sourceBreakdown" :key="item.key" class="rounded-2xl border border-slate-800 bg-slate-950/70 p-4">
                            <div class="flex items-center justify-between gap-3">
                                <div>
                                    <p class="font-semibold text-white">{{ item.label }}</p>
                                    <p class="mt-1 text-sm text-slate-400">{{ formatNumber(item.count) }} orders</p>
                                </div>
                                <div class="text-right font-semibold text-cyan-300">{{ formatCurrency(item.revenue) }}</div>
                            </div>
                        </article>
                    </div>

                    <div class="mt-6 grid grid-cols-2 gap-3 text-sm text-slate-300">
                        <div class="rounded-2xl border border-slate-800 bg-slate-950/70 px-4 py-3">
                            <div class="text-xs uppercase tracking-[0.16em] text-slate-500">No-shows</div>
                            <div class="mt-2 text-2xl font-semibold text-white">{{ formatNumber(reservationOverview.no_show_count) }}</div>
                        </div>
                        <div class="rounded-2xl border border-slate-800 bg-slate-950/70 px-4 py-3">
                            <div class="text-xs uppercase tracking-[0.16em] text-slate-500">Geannuleerd</div>
                            <div class="mt-2 text-2xl font-semibold text-white">{{ formatNumber(reservationOverview.cancelled_count) }}</div>
                        </div>
                    </div>
                </section>
            </div>

            <section class="rounded-3xl border border-slate-800 bg-slate-900/90 p-5 shadow-xl">
                <div class="mb-4 flex flex-wrap items-center justify-between gap-3">
                    <div>
                        <h3 class="text-lg font-semibold text-white">Schoolvakanties vergelijken</h3>
                        <p class="mt-1 text-sm text-slate-400">Snelle vergelijking van vakantieblokken volgens de Vlaamse schoolkalender.</p>
                    </div>
                    <span class="rounded-full border border-slate-700 bg-slate-950/70 px-3 py-1 text-xs text-slate-300">{{ holidayComparisons.length }} vergelijkingen</span>
                </div>

                <div v-if="holidayComparisons.length" class="grid gap-4 xl:grid-cols-3">
                    <article v-for="holiday in holidayComparisons" :key="holiday.key" class="rounded-3xl border border-slate-800 bg-slate-950/70 p-5">
                        <div class="flex items-start justify-between gap-3">
                            <div>
                                <p class="text-sm font-medium text-cyan-300">{{ holiday.label }}</p>
                                <p class="mt-2 text-2xl font-bold text-white">{{ formatCurrency(holiday.current?.revenue) }}</p>
                                <p class="mt-1 text-sm text-slate-400">Vorige jaarblok: {{ formatCurrency(holiday.previous?.revenue) }}</p>
                            </div>
                            <div class="rounded-2xl px-3 py-2 text-sm font-semibold" :class="holiday.delta_percentage >= 0 ? 'border border-emerald-500/20 bg-emerald-500/10 text-emerald-200' : 'border border-rose-500/20 bg-rose-500/10 text-rose-200'">
                                {{ formatDelta(holiday.delta_percentage) }}
                            </div>
                        </div>

                        <div class="mt-4 grid grid-cols-2 gap-3 text-sm text-slate-300">
                            <div class="rounded-2xl border border-slate-800 bg-slate-900/80 px-4 py-3">
                                <div class="text-xs uppercase tracking-[0.16em] text-slate-500">Orders</div>
                                <div class="mt-2 text-xl font-semibold text-white">{{ formatNumber(holiday.current?.order_count) }}</div>
                            </div>
                            <div class="rounded-2xl border border-slate-800 bg-slate-900/80 px-4 py-3">
                                <div class="text-xs uppercase tracking-[0.16em] text-slate-500">Gem. ticket</div>
                                <div class="mt-2 text-xl font-semibold text-white">{{ formatCurrency(holiday.current?.avg_order_value) }}</div>
                            </div>
                        </div>
                    </article>
                </div>

                <div v-else class="rounded-3xl border border-dashed border-slate-700 bg-slate-950/60 px-4 py-10 text-center text-sm text-slate-400">
                    Nog geen afgeronde vakantieblokken beschikbaar om te vergelijken.
                </div>
            </section>
        </div>
    </div>
</template>

<script setup>
import { computed, onMounted, ref } from 'vue'
import { fetchDashboard } from '../../analytics/services/analyticsApi'
import StatsCard from '../../analytics/components/StatsCard.vue'
import LineComparisonChart from '../../analytics/components/LineComparisonChart.vue'
import BreakdownTableCard from '../../analytics/components/BreakdownTableCard.vue'

const loading = ref(true)
const dashboard = ref({})

const summary = computed(() => dashboard.value.summary || {})
const paymentBreakdown = computed(() => dashboard.value.payment_breakdown || [])
const categoryBreakdown = computed(() => dashboard.value.category_breakdown || [])
const topProducts = computed(() => dashboard.value.top_products || [])
const reservationOverview = computed(() => dashboard.value.reservation_overview || {})
const sourceBreakdown = computed(() => dashboard.value.source_breakdown || [])
const holidayComparisons = computed(() => dashboard.value.holiday_comparisons || [])
const revenueTrend = computed(() => dashboard.value.revenue_trend || [])
const comparisonTrend = computed(() => dashboard.value.previous_revenue_trend || [])

onMounted(loadDashboard)

async function loadDashboard() {
    loading.value = true
    try {
        dashboard.value = await fetchDashboard()
    } finally {
        loading.value = false
    }
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

function formatDelta(value) {
    if (value === null || value === undefined) {
        return 'n.v.t.'
    }

    return `${value > 0 ? '+' : ''}${formatDecimal(value)}%`
}
</script>
