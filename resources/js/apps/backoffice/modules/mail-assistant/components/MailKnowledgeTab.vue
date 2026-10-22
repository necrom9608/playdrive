<template>
    <div class="flex flex-col gap-4">
        <!-- Importeren uit website -->
        <section class="rounded-3xl border border-blue-500/45 bg-[linear-gradient(180deg,rgba(12,26,58,0.96)_0%,rgba(8,18,43,0.92)_100%)] p-5 shadow-xl">
            <div class="flex flex-wrap items-start justify-between gap-3">
                <div>
                    <h2 class="flex items-center gap-2 text-xl font-semibold text-white">
                        <GlobeAltIcon class="h-6 w-6 text-blue-300" /> Importeren uit website
                    </h2>
                    <p class="mt-1 text-sm text-slate-300">
                        Leest de pagina's van je website en stelt vragen en antwoorden voor. Openingsuren en prijzen worden
                        bewust overgeslagen: die komen live uit Playdrive.
                    </p>
                </div>
            </div>

            <!-- Stap 1: adres -->
            <form class="mt-4 flex flex-wrap gap-2" @submit.prevent="findPages">
                <label class="sr-only" for="website-url">Website</label>
                <input
                    id="website-url"
                    v-model="websiteUrl"
                    type="text"
                    class="field min-w-[260px] flex-1"
                    placeholder="https://www.jouwzaak.be"
                    :disabled="importBusy"
                >
                <button
                    type="submit"
                    class="rounded-2xl border border-slate-700 bg-slate-950/55 px-5 py-3 text-sm font-semibold text-slate-200 transition hover:bg-slate-800 disabled:opacity-50"
                    :disabled="importBusy || !websiteUrl.trim()"
                >
                    {{ importStep === 'pages' ? 'Zoeken…' : "Pagina's zoeken" }}
                </button>
            </form>

            <!-- Stap 2: pagina's kiezen -->
            <div v-if="pages.length && !proposals.length && importStep !== 'extract'" class="mt-4">
                <div class="mb-2 flex flex-wrap items-center justify-between gap-2">
                    <p class="text-sm text-slate-300">{{ selectedPages.length }} van {{ pages.length }} pagina's geselecteerd</p>
                    <div class="flex gap-3 text-xs font-semibold">
                        <button type="button" class="text-blue-300 hover:text-blue-200" @click="setAllPages(true)">Alles</button>
                        <button type="button" class="text-blue-300 hover:text-blue-200" @click="setAllPages(false)">Niets</button>
                    </div>
                </div>
                <div class="grid max-h-72 gap-1.5 overflow-y-auto pr-1 sm:grid-cols-2 xl:grid-cols-3">
                    <label
                        v-for="page in pages"
                        :key="page.url"
                        class="flex cursor-pointer items-center gap-3 rounded-2xl border border-slate-800 bg-slate-950/70 px-3 py-2.5"
                    >
                        <input v-model="page.selected" type="checkbox" class="h-4 w-4 accent-blue-600">
                        <span class="min-w-0">
                            <span class="block truncate text-sm font-semibold text-white">{{ page.title }}</span>
                            <span class="block truncate text-xs text-slate-400">{{ page.url }}</span>
                        </span>
                    </label>
                </div>
                <button
                    type="button"
                    class="mt-3 rounded-2xl bg-[linear-gradient(180deg,#3483ff_0%,#255df4_100%)] px-5 py-3 text-sm font-semibold text-white shadow-[0_14px_28px_rgba(37,93,244,0.28)] disabled:opacity-50"
                    :disabled="!selectedPages.length"
                    @click="extract"
                >
                    Vragen opstellen
                </button>
            </div>

            <!-- Bezig -->
            <div v-if="importStep === 'extract'" class="mt-4">
                <p class="text-sm text-slate-300">
                    Pagina's lezen… {{ progress.done }} / {{ progress.total }} — {{ proposals.length }} voorstellen
                </p>
                <div class="mt-2 h-1.5 overflow-hidden rounded-full bg-slate-800">
                    <div class="h-full rounded-full bg-blue-500 transition-all" :style="{ width: progressPercent + '%' }"></div>
                </div>
            </div>

            <!-- Stap 3: voorstellen -->
            <div v-if="proposals.length && importStep !== 'extract'" class="mt-4">
                <div class="mb-2 flex flex-wrap items-center justify-between gap-2">
                    <p class="text-sm text-slate-300">{{ selectedProposals.length }} van {{ proposals.length }} voorstellen geselecteerd — pas gerust aan voor je toevoegt.</p>
                    <div class="flex gap-3 text-xs font-semibold">
                        <button type="button" class="text-blue-300 hover:text-blue-200" @click="setAllProposals(true)">Alles</button>
                        <button type="button" class="text-blue-300 hover:text-blue-200" @click="setAllProposals(false)">Niets</button>
                    </div>
                </div>
                <ul class="flex max-h-[520px] flex-col gap-2 overflow-y-auto pr-1">
                    <li
                        v-for="(p, i) in proposals"
                        :key="i"
                        class="flex gap-3 rounded-2xl border px-3 py-3"
                        :class="p.selected ? 'border-blue-500/40 bg-blue-600/10' : 'border-slate-800 bg-slate-950/70 opacity-60'"
                    >
                        <input v-model="p.selected" type="checkbox" class="mt-3 h-4 w-4 shrink-0 accent-blue-600" :aria-label="`Voorstel ${i + 1} selecteren`">
                        <div class="grid min-w-0 flex-1 gap-2 lg:grid-cols-[1fr_1.4fr]">
                            <input v-model="p.question" type="text" class="field" aria-label="Vraag">
                            <div>
                                <textarea v-model="p.answer" rows="2" class="field resize-y" aria-label="Antwoord"></textarea>
                                <a v-if="p.source_url" :href="p.source_url" target="_blank" rel="noopener" class="mt-1 block truncate text-xs text-slate-400 hover:text-slate-200">
                                    bron: {{ p.source_url }}
                                </a>
                            </div>
                        </div>
                    </li>
                </ul>
                <div class="mt-3 flex flex-wrap gap-2">
                    <button
                        type="button"
                        class="rounded-2xl bg-[linear-gradient(180deg,#3483ff_0%,#255df4_100%)] px-5 py-3 text-sm font-semibold text-white shadow-[0_14px_28px_rgba(37,93,244,0.28)] disabled:opacity-50"
                        :disabled="busy || !selectedProposals.length"
                        @click="addSelected"
                    >
                        {{ selectedProposals.length }} toevoegen aan kennisbank
                    </button>
                    <button type="button" class="rounded-2xl px-4 py-3 text-sm font-semibold text-slate-300 hover:text-white" @click="resetImport">
                        Annuleer
                    </button>
                </div>
            </div>

            <p v-if="importError" class="mt-3 text-sm text-rose-300">{{ importError }}</p>
        </section>

        <!-- Manueel toevoegen -->
        <section class="rounded-3xl border border-slate-800 bg-slate-900/90 p-5 shadow-xl">
            <h2 class="text-xl font-semibold text-white">Kennisbank</h2>
            <p class="mt-1 text-sm text-slate-400">
                Vaste antwoorden die niet in de andere modules staan (taart meebrengen, parking, minimumleeftijd VR, …).
                De assistent gebruikt ze letterlijk als bron.
            </p>

            <form class="mt-4 grid gap-3 lg:grid-cols-[1fr_1.4fr_auto] lg:items-end" @submit.prevent="add">
                <label class="flex flex-col gap-1.5 text-sm text-slate-300">
                    Vraag
                    <input v-model="newItem.question" type="text" class="field" placeholder="Mag ik zelf een taart meebrengen?" required>
                </label>
                <label class="flex flex-col gap-1.5 text-sm text-slate-300">
                    Antwoord
                    <textarea v-model="newItem.answer" rows="2" class="field resize-y" placeholder="Ja, taart meebrengen mag; borden en bestek zijn voorzien." required></textarea>
                </label>
                <button
                    type="submit"
                    class="rounded-2xl border border-slate-700 bg-slate-950/55 px-5 py-3 text-sm font-semibold text-slate-200 transition hover:bg-slate-800 disabled:opacity-50"
                    :disabled="busy || !newItem.question.trim() || !newItem.answer.trim()"
                >
                    Toevoegen
                </button>
            </form>
        </section>

        <div v-if="error" class="rounded-2xl border border-rose-500/30 bg-rose-500/10 px-5 py-3 text-sm text-rose-200">{{ error }}</div>
        <div v-if="notice" class="rounded-2xl border border-emerald-500/30 bg-emerald-500/10 px-5 py-3 text-sm text-emerald-200">{{ notice }}</div>

        <div v-if="loading" class="rounded-3xl border border-slate-800 bg-slate-900/90 p-10 text-center text-sm text-slate-400">Laden…</div>
        <div v-else-if="!items.length" class="rounded-3xl border border-slate-800 bg-slate-900/90 p-10 text-center text-sm text-slate-400">
            Nog geen vragen in de kennisbank.
        </div>

        <template v-else>
            <p class="px-1 text-sm text-slate-400">{{ items.length }} vragen · {{ items.filter(i => i.is_active).length }} actief</p>
            <ul class="flex flex-col gap-2">
                <li v-for="item in items" :key="item.id" class="rounded-3xl border border-slate-800 bg-slate-900/90 p-4">
                    <template v-if="editingId === item.id">
                        <div class="grid gap-3 lg:grid-cols-[1fr_1.4fr]">
                            <input v-model="edit.question" type="text" class="field" aria-label="Vraag">
                            <textarea v-model="edit.answer" rows="3" class="field resize-y" aria-label="Antwoord"></textarea>
                        </div>
                        <div class="mt-3 flex flex-wrap gap-2">
                            <button type="button" class="rounded-2xl border border-blue-500 bg-blue-600 px-4 py-2.5 text-sm font-semibold text-white disabled:opacity-50" :disabled="busy" @click="saveEdit(item)">
                                Bewaren
                            </button>
                            <button type="button" class="rounded-2xl px-4 py-2.5 text-sm font-semibold text-slate-300 hover:text-white" @click="editingId = null">Annuleer</button>
                        </div>
                    </template>

                    <div v-else class="flex flex-wrap items-start gap-4">
                        <div class="min-w-0 flex-1" :class="item.is_active ? '' : 'opacity-50'">
                            <p class="font-semibold text-white">{{ item.question }}</p>
                            <p class="mt-1 whitespace-pre-line text-sm text-slate-300">{{ item.answer }}</p>
                        </div>
                        <div class="flex items-center gap-2">
                            <ToggleSwitch :model-value="item.is_active" :aria-label="`${item.question} actief`" @update:model-value="toggle(item, $event)" />
                            <button type="button" class="rounded-xl p-2 text-slate-400 hover:bg-slate-800 hover:text-white" aria-label="Bewerken" @click="startEdit(item)">
                                <PencilSquareIcon class="h-5 w-5" />
                            </button>
                            <button type="button" class="rounded-xl p-2 text-slate-400 hover:bg-rose-500/15 hover:text-rose-300" aria-label="Verwijderen" @click="remove(item)">
                                <TrashIcon class="h-5 w-5" />
                            </button>
                        </div>
                    </div>
                </li>
            </ul>
        </template>
    </div>
</template>

<script setup>
import { computed, onMounted, reactive, ref } from 'vue'
import { GlobeAltIcon, PencilSquareIcon, TrashIcon } from '@heroicons/vue/24/outline'
import ToggleSwitch from '../../../components/ToggleSwitch.vue'
import {
    createFaq,
    createFaqsBulk,
    deleteFaq,
    errorMessage,
    extractFromWebsite,
    fetchFaqs,
    findWebsitePages,
    updateFaq,
} from '../services/mailAssistantApi'

const BATCH_SIZE = 4

const items = ref([])
const loading = ref(true)
const busy = ref(false)
const error = ref('')
const notice = ref('')
const editingId = ref(null)
const newItem = reactive({ question: '', answer: '' })
const edit = reactive({ question: '', answer: '' })

// Import
const websiteUrl = ref('')
const pages = ref([])
const proposals = ref([])
const importStep = ref('')
const importError = ref('')
const progress = reactive({ done: 0, total: 0 })

const importBusy = computed(() => importStep.value !== '')
const selectedPages = computed(() => pages.value.filter(p => p.selected))
const selectedProposals = computed(() => proposals.value.filter(p => p.selected))
const progressPercent = computed(() => progress.total ? Math.round((progress.done / progress.total) * 100) : 0)

async function run(fn, fallback) {
    busy.value = true
    error.value = ''
    try {
        const res = await fn()
        items.value = res.data
        if (res.message) notice.value = res.message
        return true
    } catch (e) {
        error.value = errorMessage(e, fallback)
        return false
    } finally {
        busy.value = false
    }
}

async function load() {
    loading.value = true
    try {
        const res = await fetchFaqs()
        items.value = res.data
        if (!websiteUrl.value && res.website_url) websiteUrl.value = res.website_url
    } catch (e) {
        error.value = errorMessage(e, 'Kennisbank laden mislukt.')
    } finally {
        loading.value = false
    }
}

async function add() {
    const ok = await run(() => createFaq({ question: newItem.question, answer: newItem.answer, is_active: true }), 'Toevoegen mislukt.')
    if (ok) {
        newItem.question = ''
        newItem.answer = ''
    }
}

function startEdit(item) {
    editingId.value = item.id
    edit.question = item.question
    edit.answer = item.answer
}

async function saveEdit(item) {
    const ok = await run(() => updateFaq(item.id, { question: edit.question, answer: edit.answer, is_active: item.is_active }), 'Bewaren mislukt.')
    if (ok) editingId.value = null
}

function toggle(item, value) {
    run(() => updateFaq(item.id, { question: item.question, answer: item.answer, is_active: value }), 'Wijzigen mislukt.')
}

function remove(item) {
    if (!window.confirm(`"${item.question}" verwijderen?`)) return
    run(() => deleteFaq(item.id), 'Verwijderen mislukt.')
}

// ── Import uit website ──────────────────────────────────────────────────

function resetImport() {
    pages.value = []
    proposals.value = []
    importStep.value = ''
    importError.value = ''
}

function setAllPages(value) {
    pages.value.forEach(p => { p.selected = value })
}

function setAllProposals(value) {
    proposals.value.forEach(p => { p.selected = value })
}

async function findPages() {
    resetImport()
    importStep.value = 'pages'
    try {
        const found = await findWebsitePages(websiteUrl.value)
        pages.value = found.map(p => ({ ...p, selected: true }))
        if (!found.length) importError.value = "Geen pagina's gevonden op dit adres."
    } catch (e) {
        importError.value = errorMessage(e, "Pagina's zoeken mislukt.")
    } finally {
        importStep.value = ''
    }
}

async function extract() {
    const urls = selectedPages.value.map(p => p.url)
    const batches = []
    for (let i = 0; i < urls.length; i += BATCH_SIZE) batches.push(urls.slice(i, i + BATCH_SIZE))

    importStep.value = 'extract'
    importError.value = ''
    proposals.value = []
    progress.done = 0
    progress.total = batches.length

    let failed = 0
    for (const batch of batches) {
        try {
            const known = proposals.value.map(p => p.question)
            const found = await extractFromWebsite(batch, known)
            proposals.value.push(...found.map(p => ({ ...p, selected: true })))
        } catch (e) {
            failed++
            importError.value = errorMessage(e, 'Een deel van de pagina\'s kon niet gelezen worden.')
        }
        progress.done++
    }

    importStep.value = ''

    if (!proposals.value.length && !importError.value) {
        importError.value = 'Geen bruikbare vragen gevonden op deze pagina\'s.'
    } else if (failed && proposals.value.length) {
        importError.value = `${failed} van ${batches.length} delen mislukten; de andere voorstellen staan hieronder.`
    }
}

async function addSelected() {
    const chosen = selectedProposals.value.map(p => ({ question: p.question, answer: p.answer }))
    const ok = await run(() => createFaqsBulk(chosen), 'Toevoegen mislukt.')
    if (ok) resetImport()
}

onMounted(load)
</script>

<style scoped>
.field {
    width: 100%;
    border-radius: 1rem;
    border: 1px solid rgba(255, 255, 255, 0.1);
    background: rgba(2, 6, 23, 0.55);
    padding: 0.75rem 1rem;
    color: #fff;
    outline: none;
    transition: border-color 0.2s ease, background-color 0.2s ease;
}

.field::placeholder {
    color: rgba(148, 163, 184, 0.7);
}

.field:focus {
    border-color: rgba(96, 165, 250, 0.65);
    background: rgba(2, 6, 23, 0.72);
}
</style>
