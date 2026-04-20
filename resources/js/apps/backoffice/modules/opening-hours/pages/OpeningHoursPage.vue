<template>
    <div class="space-y-6">
        <div class="flex flex-wrap items-start justify-between gap-4">
            <div>
                <h1 class="text-3xl font-bold text-white">Openingsuren</h1>
                <p class="mt-2 text-slate-400">
                    Stel de openingsuren in per seizoen en beheer uitzonderingen voor specifieke dagen.
                </p>
            </div>
            <button
                v-if="activeTab === 'hours'"
                type="button"
                :disabled="saving"
                class="rounded-2xl bg-blue-600 px-4 py-3 text-sm font-semibold text-white transition hover:bg-blue-500 disabled:opacity-50"
                @click="saveAllHours"
            >
                {{ saving ? 'Opslaan...' : 'Opslaan' }}
            </button>
        </div>

        <div v-if="error" class="rounded-2xl border border-rose-500/30 bg-rose-500/10 px-5 py-4 text-sm text-rose-200">
            {{ error }}
        </div>
        <div v-if="success" class="rounded-2xl border border-emerald-500/30 bg-emerald-500/10 px-5 py-4 text-sm text-emerald-200">
            {{ success }}
        </div>

        <!-- Tabs -->
        <div class="flex gap-1 rounded-2xl border border-slate-800 bg-slate-900 p-1 w-fit">
            <button
                v-for="tab in tabs"
                :key="tab.key"
                type="button"
                class="rounded-xl px-4 py-2 text-sm font-medium transition"
                :class="activeTab === tab.key
                    ? 'bg-slate-700 text-white'
                    : 'text-slate-400 hover:text-slate-200'"
                @click="activeTab = tab.key"
            >
                {{ tab.label }}
            </button>
        </div>

        <!-- Tab: Openingsuren per seizoen -->
        <div v-if="activeTab === 'hours'">
            <div v-if="loading" class="py-8 text-center text-sm text-slate-400">Laden...</div>

            <div v-else-if="!regionCode" class="rounded-2xl border border-amber-500/30 bg-amber-500/10 px-5 py-4 text-sm text-amber-200">
                Er is nog geen regio gekoppeld aan deze tenant. Vraag de platformbeheerder om een regio in te stellen.
            </div>

            <div v-else class="space-y-6">
                <!-- Per season_key een tabel -->
                <section
                    v-for="sk in seasonKeys"
                    :key="sk"
                    class="rounded-3xl border border-slate-800 bg-slate-900/80 p-6 shadow-xl shadow-slate-950/20"
                >
                    <div class="mb-4 flex items-center gap-3">
                        <span class="rounded-full bg-slate-800 px-3 py-1 font-mono text-xs font-semibold text-sky-300">
                            {{ sk }}
                        </span>
                        <h2 class="text-base font-semibold text-white">{{ seasonKeyLabel(sk) }}</h2>
                    </div>

                    <div class="overflow-x-auto">
                        <table class="min-w-full text-sm">
                            <thead>
                                <tr class="text-slate-400">
                                    <th class="pb-3 text-left font-medium">Dag</th>
                                    <th class="pb-3 text-center font-medium">Status</th>
                                    <th class="pb-3 text-left font-medium pl-4">Open van</th>
                                    <th class="pb-3 text-left font-medium pl-4">Open tot</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr
                                    v-for="wd in weekdays"
                                    :key="wd.value"
                                    class="border-t border-slate-800/60"
                                >
                                    <td class="py-3 pr-4 font-medium text-white w-28">{{ wd.label }}</td>
                                    <td class="py-3 text-center w-24">
                                        <button
                                            type="button"
                                            class="inline-flex items-center gap-1.5 rounded-xl border px-3 py-1.5 text-xs font-semibold transition"
                                            :class="getHour(sk, wd.value).is_open
                                                ? 'border-emerald-500/40 bg-emerald-500/10 text-emerald-300 hover:bg-emerald-500/20'
                                                : 'border-slate-700 bg-slate-800 text-slate-400 hover:bg-slate-700'"
                                            @click="toggleOpen(sk, wd.value)"
                                        >
                                            <span class="h-1.5 w-1.5 rounded-full" :class="getHour(sk, wd.value).is_open ? 'bg-emerald-400' : 'bg-slate-500'" />
                                            {{ getHour(sk, wd.value).is_open ? 'Open' : 'Gesloten' }}
                                        </button>
                                    </td>
                                    <td class="py-3 pl-4">
                                        <input
                                            v-if="getHour(sk, wd.value).is_open"
                                            v-model="getHour(sk, wd.value).open_from"
                                            type="time"
                                            class="w-32 rounded-xl border border-slate-700 bg-slate-950 px-3 py-1.5 text-sm text-white outline-none focus:border-sky-500"
                                        />
                                        <span v-else class="text-slate-600">—</span>
                                    </td>
                                    <td class="py-3 pl-4">
                                        <input
                                            v-if="getHour(sk, wd.value).is_open"
                                            v-model="getHour(sk, wd.value).open_until"
                                            type="time"
                                            class="w-32 rounded-xl border border-slate-700 bg-slate-950 px-3 py-1.5 text-sm text-white outline-none focus:border-sky-500"
                                        />
                                        <span v-else class="text-slate-600">—</span>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </section>
            </div>
        </div>

        <!-- Tab: Vakantieperiodes -->
        <div v-if="activeTab === 'seasons'">
            <div v-if="loading" class="py-8 text-center text-sm text-slate-400">Laden...</div>

            <div v-else-if="seasons.length === 0" class="rounded-2xl border border-slate-800 bg-slate-900 p-8 text-center text-sm text-slate-400">
                Nog geen vakantieperiodes gevonden voor uw regio. Neem contact op met de platformbeheerder.
            </div>

            <div v-else class="rounded-2xl border border-slate-800 bg-slate-900 overflow-x-auto">
                <table class="min-w-full text-sm">
                    <thead class="bg-slate-950 text-slate-300">
                        <tr>
                            <th class="px-4 py-3 text-left font-semibold">Periode</th>
                            <th class="px-4 py-3 text-left font-semibold">Season key</th>
                            <th class="px-4 py-3 text-left font-semibold">Van</th>
                            <th class="px-4 py-3 text-left font-semibold">Tot en met</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr
                            v-for="season in seasons"
                            :key="season.id"
                            class="border-t border-slate-800"
                        >
                            <td class="px-4 py-3 font-medium text-white">{{ season.season_name }}</td>
                            <td class="px-4 py-3">
                                <span class="rounded-full bg-slate-800 px-2.5 py-1 font-mono text-xs text-sky-300">
                                    {{ season.season_key }}
                                </span>
                            </td>
                            <td class="px-4 py-3 text-slate-300">{{ formatDate(season.date_from) }}</td>
                            <td class="px-4 py-3 text-slate-300">{{ formatDate(season.date_until) }}</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Tab: Uitzonderingen -->
        <div v-if="activeTab === 'exceptions'">
            <div class="mb-4 flex justify-end">
                <button
                    type="button"
                    class="rounded-xl bg-blue-600 px-4 py-3 text-sm font-semibold text-white transition hover:bg-blue-500"
                    @click="exceptionModalOpen = true"
                >
                    Uitzondering toevoegen
                </button>
            </div>

            <div v-if="loading" class="py-8 text-center text-sm text-slate-400">Laden...</div>

            <div v-else-if="exceptions.length === 0" class="rounded-2xl border border-slate-800 bg-slate-900 p-8 text-center text-sm text-slate-400">
                Geen uitzonderingen ingegeven.
            </div>

            <div v-else class="rounded-2xl border border-slate-800 bg-slate-900 overflow-x-auto">
                <table class="min-w-full text-sm">
                    <thead class="bg-slate-950 text-slate-300">
                        <tr>
                            <th class="px-4 py-3 text-left font-semibold">Datum</th>
                            <th class="px-4 py-3 text-left font-semibold">Label</th>
                            <th class="px-4 py-3 text-left font-semibold">Status</th>
                            <th class="px-4 py-3 text-left font-semibold">Uren</th>
                            <th class="px-4 py-3 text-right font-semibold">Acties</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr
                            v-for="ex in exceptionsSorted"
                            :key="ex.id"
                            class="border-t border-slate-800"
                        >
                            <td class="px-4 py-3 font-medium text-white">{{ formatDate(ex.date) }}</td>
                            <td class="px-4 py-3 text-slate-400">{{ ex.label || '—' }}</td>
                            <td class="px-4 py-3">
                                <span
                                    class="inline-flex rounded-full px-2.5 py-1 text-xs font-semibold"
                                    :class="ex.is_open
                                        ? 'bg-emerald-500/15 text-emerald-300'
                                        : 'bg-rose-500/15 text-rose-300'"
                                >
                                    {{ ex.is_open ? 'Open' : 'Gesloten' }}
                                </span>
                            </td>
                            <td class="px-4 py-3 text-slate-400">
                                {{ ex.is_open ? `${ex.open_from || '?'} – ${ex.open_until || '?'}` : '—' }}
                            </td>
                            <td class="px-4 py-3 text-right">
                                <button
                                    type="button"
                                    class="rounded-xl border border-rose-500/30 bg-rose-500/10 px-3 py-2 text-xs font-semibold text-rose-200 transition hover:bg-rose-500/20"
                                    @click="handleDeleteException(ex)"
                                >
                                    Verwijderen
                                </button>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <ExceptionFormModal
            :open="exceptionModalOpen"
            :saving="exceptionSaving"
            :error="exceptionError"
            @close="exceptionModalOpen = false"
            @submit="handleExceptionSubmit"
        />
    </div>
</template>

<script setup>
import { computed, onMounted, reactive, ref } from 'vue'
import ExceptionFormModal from '../components/ExceptionFormModal.vue'
import {
    createException,
    deleteException,
    fetchOpeningHours,
    saveHours,
} from '../services/openingHoursApi'

// ─── Constanten ───────────────────────────────────────────────────────────
const weekdays = [
    { value: 1, label: 'Maandag' },
    { value: 2, label: 'Dinsdag' },
    { value: 3, label: 'Woensdag' },
    { value: 4, label: 'Donderdag' },
    { value: 5, label: 'Vrijdag' },
    { value: 6, label: 'Zaterdag' },
    { value: 7, label: 'Zondag' },
]

const tabs = [
    { key: 'hours',      label: 'Openingsuren' },
    { key: 'seasons',    label: 'Vakantieperiodes' },
    { key: 'exceptions', label: 'Uitzonderingen' },
]

const SEASON_KEY_LABELS = {
    regular:    'Normale weken',
    school_vac: 'Schoolvakanties',
    summer:     'Zomervakantie',
}

// ─── State ────────────────────────────────────────────────────────────────
const activeTab  = ref('hours')
const loading    = ref(false)
const saving     = ref(false)
const error      = ref('')
const success    = ref('')

const regionCode = ref(null)
const seasons    = ref([])
const exceptions = ref([])

// hours: { [seasonKey]: { [weekday]: { is_open, open_from, open_until } } }
const hours = reactive({})

const exceptionModalOpen  = ref(false)
const exceptionSaving     = ref(false)
const exceptionError      = ref('')

// ─── Computed ─────────────────────────────────────────────────────────────
const seasonKeys = computed(() => {
    const fromHours  = Object.keys(hours)
    const fromSzns   = [...new Set(seasons.value.map(s => s.season_key))]
    const all        = [...new Set(['regular', ...fromSzns, ...fromHours])]
    return all
})

const exceptionsSorted = computed(() =>
    [...exceptions.value].sort((a, b) => a.date.localeCompare(b.date))
)

// ─── Helpers ──────────────────────────────────────────────────────────────
function seasonKeyLabel(sk) {
    return SEASON_KEY_LABELS[sk] ?? sk
}

function getHour(seasonKey, weekday) {
    if (!hours[seasonKey]) hours[seasonKey] = {}
    if (!hours[seasonKey][weekday]) {
        hours[seasonKey][weekday] = { is_open: false, open_from: '', open_until: '' }
    }
    return hours[seasonKey][weekday]
}

function toggleOpen(seasonKey, weekday) {
    const h = getHour(seasonKey, weekday)
    h.is_open = !h.is_open
    if (!h.is_open) {
        h.open_from  = ''
        h.open_until = ''
    }
}

function formatDate(dateStr) {
    if (!dateStr) return '—'
    return new Date(dateStr).toLocaleDateString('nl-BE', { day: '2-digit', month: 'short', year: 'numeric' })
}

// ─── Laden ────────────────────────────────────────────────────────────────
async function load() {
    loading.value = true
    error.value   = ''
    try {
        const res = await fetchOpeningHours()
        regionCode.value = res.region_code
        seasons.value    = res.seasons    ?? []
        exceptions.value = res.exceptions ?? []

        // Vul hours reactive object
        for (const seasonKey of Object.keys(hours)) {
            delete hours[seasonKey]
        }
        for (const h of (res.hours ?? [])) {
            if (!hours[h.season_key]) hours[h.season_key] = {}
            hours[h.season_key][h.weekday] = {
                is_open:    h.is_open,
                open_from:  h.open_from  ? h.open_from.slice(0, 5)  : '',
                open_until: h.open_until ? h.open_until.slice(0, 5) : '',
            }
        }
    } catch {
        error.value = 'Kon openingsuren niet laden.'
    } finally {
        loading.value = false
    }
}

// ─── Opslaan openingsuren ─────────────────────────────────────────────────
async function saveAllHours() {
    saving.value  = true
    error.value   = ''
    success.value = ''

    const payload = []
    for (const [sk, wds] of Object.entries(hours)) {
        for (const [wd, data] of Object.entries(wds)) {
            payload.push({
                season_key:  sk,
                weekday:     Number(wd),
                is_open:     data.is_open,
                open_from:   data.is_open ? (data.open_from  || null) : null,
                open_until:  data.is_open ? (data.open_until || null) : null,
            })
        }
    }

    try {
        await saveHours(payload)
        success.value = 'Openingsuren opgeslagen.'
        setTimeout(() => (success.value = ''), 3000)
    } catch {
        error.value = 'Opslaan mislukt.'
    } finally {
        saving.value = false
    }
}

// ─── Uitzonderingen ───────────────────────────────────────────────────────
async function handleExceptionSubmit(payload) {
    exceptionSaving.value = true
    exceptionError.value  = ''
    try {
        const res = await createException(payload)
        exceptions.value.push(res.exception)
        exceptionModalOpen.value = false
    } catch (err) {
        exceptionError.value = err?.data?.message || 'Opslaan mislukt.'
    } finally {
        exceptionSaving.value = false
    }
}

async function handleDeleteException(ex) {
    if (!confirm(`Uitzondering voor ${formatDate(ex.date)} verwijderen?`)) return
    try {
        await deleteException(ex.id)
        exceptions.value = exceptions.value.filter(e => e.id !== ex.id)
    } catch {
        error.value = 'Verwijderen mislukt.'
    }
}

onMounted(load)
</script>
