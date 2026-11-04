<template>
    <section class="flex h-full min-h-0 flex-col gap-6 overflow-auto">
        <div class="rounded-3xl border border-slate-800 bg-slate-900/90 p-6 shadow-xl">
            <div class="flex flex-wrap items-start justify-between gap-4">
                <div>
                    <h1 class="text-2xl font-semibold text-white">Aankopen van particulieren</h1>
                    <p class="mt-1 max-w-2xl text-sm text-slate-400">
                        Aankoopborderel dat de verkoper zelf invult en ondertekent op zijn gsm. Na ondertekening gaat de PDF automatisch naar de verkoper, de zaak en de boekhouding.
                    </p>
                </div>
                <router-link to="/purchases/new" class="inline-flex items-center gap-2 rounded-xl bg-blue-600 px-4 py-3 text-sm font-semibold text-white transition hover:bg-blue-500">
                    <PlusIcon class="h-5 w-5" /> Nieuwe aankoop
                </router-link>
            </div>

            <div class="mt-6 grid gap-4 sm:grid-cols-3">
                <div class="rounded-2xl border border-slate-800 bg-slate-950/60 px-5 py-4">
                    <p class="text-sm text-slate-400">Wacht op handtekening</p>
                    <p class="mt-1 text-2xl font-semibold text-white">{{ stats.awaiting_signature ?? 0 }}</p>
                </div>
                <div class="rounded-2xl border border-slate-800 bg-slate-950/60 px-5 py-4">
                    <p class="text-sm text-slate-400">Nog te betalen<span v-if="stats.unpaid_count"> ({{ stats.unpaid_count }})</span></p>
                    <p class="mt-1 text-2xl font-semibold text-white">{{ formatCurrency(stats.unpaid_amount) }}</p>
                </div>
                <div class="rounded-2xl border border-slate-800 bg-slate-950/60 px-5 py-4">
                    <p class="text-sm text-slate-400">Aangekocht dit kwartaal</p>
                    <p class="mt-1 text-2xl font-semibold text-white">{{ formatCurrency(stats.quarter_total) }}</p>
                </div>
            </div>
        </div>

        <div class="rounded-3xl border border-slate-800 bg-slate-900/90 p-6 shadow-xl">
            <div class="mb-5 flex flex-wrap items-center justify-between gap-3">
                <div class="flex flex-wrap gap-2" role="tablist" aria-label="Filter op status">
                    <button
                        v-for="tab in tabs"
                        :key="tab.value"
                        type="button"
                        role="tab"
                        :aria-selected="filter === tab.value"
                        class="rounded-full border px-4 py-2 text-sm transition"
                        :class="filter === tab.value ? 'border-blue-500 bg-blue-600 font-semibold text-white' : 'border-slate-700 text-slate-300 hover:bg-slate-800'"
                        @click="setFilter(tab.value)"
                    >
                        {{ tab.label }}
                    </button>
                </div>
                <input
                    v-model="search"
                    type="search"
                    placeholder="Zoek op nummer, verkoper of artikel"
                    class="w-full rounded-2xl border border-slate-700 bg-slate-950 px-4 py-2.5 text-sm text-white placeholder:text-slate-600 sm:w-72"
                    @input="debouncedLoad"
                />
            </div>

            <p v-if="feedback" class="mb-4 rounded-2xl border px-4 py-3 text-sm" :class="feedback.type === 'error' ? 'border-rose-500/30 bg-rose-500/10 text-rose-200' : 'border-emerald-500/30 bg-emerald-500/10 text-emerald-200'">
                {{ feedback.text }}
            </p>

            <div v-if="loading" class="py-10 text-center text-sm text-slate-400">Laden...</div>

            <div v-else-if="!purchases.length" class="flex flex-col items-center gap-3 py-12 text-center">
                <DocumentCheckIcon class="h-10 w-10 text-slate-600" />
                <p class="text-sm text-slate-400">{{ filter || search ? 'Geen aankopen gevonden.' : 'Nog geen aankopen. Maak je eerste borderel aan.' }}</p>
            </div>

            <div v-else class="overflow-x-auto">
                <table class="w-full min-w-[880px] text-sm">
                    <thead>
                        <tr class="border-b border-slate-800 text-left text-xs uppercase tracking-wide text-slate-400">
                            <th class="px-3 py-3 font-semibold">Nr.</th>
                            <th class="px-3 py-3 font-semibold">Datum</th>
                            <th class="px-3 py-3 font-semibold">Verkoper</th>
                            <th class="px-3 py-3 font-semibold">Goederen</th>
                            <th class="px-3 py-3 text-right font-semibold">Bedrag</th>
                            <th class="px-3 py-3 font-semibold">Betaling</th>
                            <th class="px-3 py-3 font-semibold">Status</th>
                            <th class="px-3 py-3 text-right font-semibold">Acties</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr
                            v-for="p in purchases"
                            :key="p.id"
                            class="cursor-pointer border-b border-slate-800/70 transition hover:bg-slate-800/40"
                            @click="open(p)"
                        >
                            <td class="px-3 py-3 font-semibold text-white">{{ p.number }}</td>
                            <td class="px-3 py-3 text-slate-300">{{ formatDate(p.purchase_date) }}</td>
                            <td class="px-3 py-3 text-slate-200">{{ p.seller_name || p.invite_email || '—' }}</td>
                            <td class="max-w-xs truncate px-3 py-3 text-slate-300">{{ p.items_summary }}</td>
                            <td class="px-3 py-3 text-right font-semibold text-white">{{ formatCurrency(p.total_amount) }}</td>
                            <td class="px-3 py-3 text-slate-300">{{ paymentLabel(p.payment_method) }}</td>
                            <td class="px-3 py-3">
                                <span class="inline-flex rounded-full border px-2.5 py-1 text-xs font-semibold" :class="statusClasses(p.status)">{{ statusLabel(p.status) }}</span>
                                <span v-if="p.link_expired" class="ml-2 text-xs text-amber-300">link verlopen</span>
                                <span v-else-if="p.status === 'sent' && p.opened_at" class="ml-2 text-xs text-slate-400">geopend</span>
                                <span v-if="p.mail_error" class="ml-2 text-xs text-rose-300" :title="p.mail_error">mail mislukt</span>
                            </td>
                            <td class="px-3 py-2 text-right" @click.stop>
                                <button v-if="p.status === 'sent' && p.link_active" type="button" class="rounded-xl px-3 py-2 text-sm font-semibold text-sky-300 hover:bg-slate-800" @click="showQr(p)">QR tonen</button>
                                <button v-else-if="p.status === 'signed'" type="button" class="rounded-xl px-3 py-2 text-sm font-semibold text-emerald-300 hover:bg-slate-800" :disabled="busy === p.id" @click="paid(p)">Markeer betaald</button>
                                <a v-if="['signed', 'paid'].includes(p.status)" :href="`/api/backoffice/purchases/${p.id}/pdf`" target="_blank" rel="noopener" class="rounded-xl px-3 py-2 text-sm font-semibold text-slate-200 hover:bg-slate-800">PDF</a>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <PurchaseShareModal :open="!!sharing" :purchase="sharing" @close="closeShare" @updated="onShareUpdated" />
    </section>
</template>

<script setup>
import { onMounted, reactive, ref } from 'vue'
import { useRouter } from 'vue-router'
import { DocumentCheckIcon, PlusIcon } from '@heroicons/vue/24/outline'
import PurchaseShareModal from '../components/PurchaseShareModal.vue'
import { errorMessage, fetchPurchase, fetchPurchases, markPurchasePaid } from '../services/purchaseApi'
import { formatCurrency, formatDate, paymentLabel, statusClasses, statusLabel } from '../utils/format'

const router = useRouter()

const tabs = [
    { value: '', label: 'Alle' },
    { value: 'draft', label: 'Concept' },
    { value: 'sent', label: 'Verstuurd' },
    { value: 'signed', label: 'Ondertekend' },
    { value: 'paid', label: 'Betaald' },
    { value: 'cancelled', label: 'Geannuleerd' },
]

const purchases = ref([])
const stats = reactive({})
const filter = ref('')
const search = ref('')
const loading = ref(true)
const feedback = ref(null)
const busy = ref(null)
const sharing = ref(null)
let searchTimer = null

async function load(showLoader = true) {
    if (showLoader) loading.value = true
    try {
        const response = await fetchPurchases({ status: filter.value || undefined, search: search.value || undefined })
        purchases.value = response.data || []
        Object.assign(stats, response.stats || {})
    } catch (error) {
        feedback.value = { type: 'error', text: errorMessage(error, 'Aankopen konden niet geladen worden.') }
    } finally {
        loading.value = false
    }
}

function setFilter(value) {
    filter.value = value
    load()
}

function debouncedLoad() {
    window.clearTimeout(searchTimer)
    searchTimer = window.setTimeout(() => load(false), 300)
}

function open(p) {
    router.push(`/purchases/${p.id}`)
}

async function showQr(p) {
    try {
        sharing.value = await fetchPurchase(p.id)
    } catch (error) {
        feedback.value = { type: 'error', text: errorMessage(error) }
    }
}

function onShareUpdated(fresh) {
    sharing.value = fresh
    load(false)
}

function closeShare() {
    sharing.value = null
    load(false)
}

async function paid(p) {
    busy.value = p.id
    feedback.value = null
    try {
        const response = await markPurchasePaid(p.id)
        feedback.value = { type: 'success', text: `${p.number}: ${response.message}` }
        await load(false)
    } catch (error) {
        feedback.value = { type: 'error', text: errorMessage(error) }
    } finally {
        busy.value = null
    }
}

onMounted(load)
</script>
