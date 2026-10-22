<template>
    <div class="flex flex-col gap-4">
        <!-- Mailbox nog niet ingesteld / fout -->
        <div
            v-if="mailbox && (!mailbox.configured || !mailbox.enabled)"
            class="flex flex-wrap items-center justify-between gap-3 rounded-3xl border border-blue-500/30 bg-blue-500/10 px-5 py-4"
        >
            <div>
                <p class="font-semibold text-blue-200">
                    {{ mailbox.configured ? 'De mailassistent staat uit.' : 'Koppel eerst de mailbox van de zaak.' }}
                </p>
                <p class="mt-0.5 text-sm text-blue-100/80">Onder Instellingen vul je IMAP/SMTP in en zet je de assistent aan.</p>
            </div>
            <button type="button" class="rounded-2xl border border-blue-500 bg-blue-600 px-4 py-2.5 text-sm font-semibold text-white" @click="$emit('open-settings')">
                Naar instellingen
            </button>
        </div>
        <div v-else-if="mailbox?.last_error" class="rounded-3xl border border-amber-500/30 bg-amber-500/10 px-5 py-4 text-sm text-amber-100">
            <span class="font-semibold text-amber-200">Laatste controle mislukt:</span> {{ mailbox.last_error }}
        </div>

        <!-- Kerncijfers -->
        <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
            <article v-for="card in summaryCards" :key="card.label" class="rounded-3xl border px-5 py-4 shadow-lg" :class="card.wrapper">
                <p class="text-xs font-medium uppercase tracking-wide" :class="card.labelClass">{{ card.label }}</p>
                <p class="mt-1.5 text-3xl font-semibold text-white">{{ card.value }}</p>
            </article>
        </div>

        <div v-if="error" class="rounded-2xl border border-rose-500/30 bg-rose-500/10 px-5 py-3 text-sm text-rose-200">{{ error }}</div>
        <div v-if="notice" class="rounded-2xl border border-emerald-500/30 bg-emerald-500/10 px-5 py-3 text-sm text-emerald-200">{{ notice }}</div>

        <div class="flex flex-col gap-4 xl:flex-row xl:items-start">
            <!-- Lijst -->
            <section class="rounded-3xl border border-slate-800 bg-slate-900/90 p-4 shadow-xl xl:w-[360px] xl:shrink-0" aria-label="Mails">
                <div class="mb-3 flex items-center justify-between gap-2">
                    <h2 class="text-lg font-semibold text-white">Inbox</h2>
                    <button
                        type="button"
                        class="inline-flex items-center gap-2 rounded-2xl border border-slate-700 bg-slate-950/55 px-3 py-2 text-xs font-semibold text-slate-200 transition hover:bg-slate-800 disabled:opacity-50"
                        :disabled="checking || !mailbox?.configured"
                        @click="checkNow"
                    >
                        <ArrowPathIcon class="h-4 w-4" :class="checking ? 'animate-spin' : ''" />
                        {{ checking ? 'Bezig…' : 'Controleer nu' }}
                    </button>
                </div>
                <p v-if="mailbox?.address" class="-mt-2 mb-3 text-xs text-slate-400">
                    {{ mailbox.address }}<template v-if="mailbox.last_checked_at"> · gecontroleerd {{ formatDateTime(mailbox.last_checked_at) }}</template>
                </p>

                <div class="mb-3 flex flex-wrap gap-1.5">
                    <button
                        v-for="f in filters"
                        :key="f.key"
                        type="button"
                        class="rounded-full px-3 py-1.5 text-xs font-semibold transition"
                        :class="filter === f.key ? 'bg-blue-500/20 text-blue-300' : 'bg-slate-800 text-slate-400 hover:text-slate-200'"
                        @click="setFilter(f.key)"
                    >
                        {{ f.label }}<span v-if="stats[f.key]" class="ml-1 opacity-80">{{ stats[f.key] }}</span>
                    </button>
                </div>

                <input
                    v-model="search"
                    type="search"
                    placeholder="Zoek op naam, e-mail of onderwerp"
                    class="mb-3 w-full rounded-2xl border border-white/10 bg-slate-950/55 px-4 py-2.5 text-sm text-white outline-none placeholder:text-slate-400/70 focus:border-blue-500/60"
                    @keyup.enter="load()"
                >

                <div v-if="loading" class="py-10 text-center text-sm text-slate-400">Laden…</div>
                <div v-else-if="!messages.length" class="py-10 text-center text-sm text-slate-400">Geen mails in deze lijst.</div>

                <ul v-else class="flex max-h-[calc(100vh-420px)] min-h-[200px] flex-col gap-2 overflow-y-auto pr-1">
                    <li v-for="m in messages" :key="m.id">
                        <button
                            type="button"
                            class="w-full rounded-[20px] border px-3.5 py-3 text-left transition"
                            :class="m.id === selectedId
                                ? 'border-blue-500/50 bg-blue-600/15'
                                : 'border-slate-800 bg-slate-950/70 hover:border-slate-700'"
                            @click="select(m.id)"
                        >
                            <div class="flex items-baseline justify-between gap-2">
                                <span class="truncate font-semibold text-white">{{ m.from_name || m.from_email }}</span>
                                <span class="shrink-0 text-xs text-slate-400">{{ formatDateTime(m.received_at) }}</span>
                            </div>
                            <div class="mt-0.5 truncate text-sm text-slate-300">{{ m.subject || '(geen onderwerp)' }}</div>
                            <div class="mt-2 flex flex-wrap gap-1.5">
                                <span v-if="m.category_label" class="rounded-full bg-slate-700/60 px-2.5 py-0.5 text-xs text-slate-300">{{ m.category_label }}</span>
                                <span class="rounded-full px-2.5 py-0.5 text-xs font-semibold" :class="statusMeta(m.status).class">{{ statusMeta(m.status).label }}</span>
                            </div>
                        </button>
                    </li>
                </ul>
            </section>

            <!-- Detail -->
            <div v-if="!selectedId" class="flex min-h-[320px] flex-1 items-center justify-center rounded-3xl border border-slate-800 bg-slate-900/90 p-6 text-sm text-slate-400">
                Selecteer een mail.
            </div>
            <div v-else-if="loadingDetail && !detail" class="flex min-h-[320px] flex-1 items-center justify-center rounded-3xl border border-slate-800 bg-slate-900/90 text-sm text-slate-400">
                Laden…
            </div>

            <template v-else-if="detail">
                <div class="flex min-w-0 flex-1 flex-col gap-4">
                    <article class="rounded-3xl border border-slate-800 bg-slate-900/90 p-5 shadow-xl">
                        <div class="flex flex-wrap items-start justify-between gap-3">
                            <div class="min-w-0">
                                <h2 class="text-xl font-semibold text-white">{{ detail.subject || '(geen onderwerp)' }}</h2>
                                <p class="mt-1 text-sm text-slate-400">
                                    {{ detail.from_name ? `${detail.from_name} · ` : '' }}{{ detail.from_email }} · {{ formatDateTime(detail.received_at) }}
                                </p>
                            </div>
                            <div class="flex flex-wrap gap-1.5">
                                <span v-if="detail.category_label" class="rounded-full border border-slate-700 bg-slate-950/70 px-3 py-1 text-xs font-semibold text-slate-300">{{ detail.category_label }}</span>
                                <span v-if="detail.confidence !== null && detail.draft_body" class="rounded-full px-3 py-1 text-xs font-semibold" :class="confidenceClass(detail.confidence)">
                                    Zekerheid {{ detail.confidence }}%
                                </span>
                                <span class="rounded-full px-3 py-1 text-xs font-semibold" :class="statusMeta(detail.status).class">{{ statusMeta(detail.status).label }}</span>
                            </div>
                        </div>

                        <div class="mt-4 max-h-[360px] overflow-y-auto whitespace-pre-line rounded-3xl border border-slate-800 bg-slate-950/70 px-4 py-4 text-sm leading-relaxed text-slate-300">{{ detail.body_text || '(lege mail)' }}</div>
                    </article>

                    <!-- Verstuurd -->
                    <section v-if="detail.status === 'sent'" class="rounded-3xl border border-emerald-500/30 bg-emerald-500/5 p-5">
                        <div class="mb-3 flex flex-wrap items-center justify-between gap-2">
                            <h3 class="font-semibold text-emerald-200">Verstuurd antwoord</h3>
                            <span class="text-xs text-slate-400">{{ formatDateTime(detail.sent_at) }}<template v-if="detail.sent_by"> · {{ detail.sent_by }}</template></span>
                        </div>
                        <div class="whitespace-pre-line text-sm leading-relaxed text-slate-200">{{ detail.sent_body }}</div>
                    </section>

                    <!-- Concept -->
                    <section
                        v-else-if="detail.status !== 'ignored' || detail.draft_body"
                        class="overflow-hidden rounded-3xl border border-blue-500/45 bg-[linear-gradient(180deg,rgba(12,26,58,0.96)_0%,rgba(8,18,43,0.92)_100%)] shadow-xl"
                        aria-label="Voorgesteld antwoord"
                    >
                        <div class="flex flex-wrap items-center justify-between gap-2 border-b border-blue-500/25 px-5 py-4">
                            <div class="flex items-center gap-2 font-semibold text-blue-300">
                                <SparklesIcon class="h-5 w-5" />
                                Voorgesteld antwoord
                                <span v-if="dirty" class="text-xs font-normal text-amber-300">· niet bewaard</span>
                                <span v-else-if="detail.draft_edited" class="text-xs font-normal text-slate-400">· aangepast</span>
                            </div>
                            <span class="text-xs text-slate-400">naar {{ detail.reply_to }}</span>
                        </div>

                        <div v-if="detail.status === 'new'" class="px-5 py-6 text-sm text-slate-300">
                            Het voorstel wordt opgesteld… Dat gebeurt automatisch binnen enkele minuten, of klik op "Opnieuw opstellen".
                        </div>
                        <div v-else-if="detail.status === 'error'" class="px-5 py-4 text-sm text-amber-200">
                            Opstellen mislukt: {{ detail.error }}
                        </div>

                        <div class="px-5 py-4">
                            <label class="sr-only" for="mail-draft">Antwoord</label>
                            <textarea
                                id="mail-draft"
                                v-model="draft"
                                rows="14"
                                class="w-full resize-y rounded-2xl border border-white/10 bg-slate-950/55 px-4 py-3 text-sm leading-relaxed text-white outline-none placeholder:text-slate-400/70 focus:border-blue-500/60"
                                placeholder="Schrijf hier je antwoord…"
                            ></textarea>
                        </div>

                        <div class="flex flex-wrap items-center gap-2 border-t border-white/10 px-5 py-4">
                            <button
                                v-if="!confirmSend"
                                type="button"
                                class="rounded-2xl bg-[linear-gradient(180deg,#3483ff_0%,#255df4_100%)] px-6 py-3 text-sm font-semibold text-white shadow-[0_14px_28px_rgba(37,93,244,0.28)] transition hover:brightness-110 disabled:opacity-50"
                                :disabled="!draft.trim() || busy"
                                @click="confirmSend = true"
                            >
                                Versturen
                            </button>
                            <template v-else>
                                <button
                                    type="button"
                                    class="rounded-2xl bg-emerald-600 px-6 py-3 text-sm font-semibold text-white transition hover:bg-emerald-500 disabled:opacity-50"
                                    :disabled="busy"
                                    @click="send"
                                >
                                    {{ busy === 'send' ? 'Versturen…' : `Bevestig: verstuur naar ${detail.reply_to}` }}
                                </button>
                                <button type="button" class="rounded-2xl px-4 py-3 text-sm font-semibold text-slate-300 hover:text-white" :disabled="busy" @click="confirmSend = false">
                                    Annuleer
                                </button>
                            </template>

                            <button
                                v-if="dirty"
                                type="button"
                                class="rounded-2xl border border-slate-700 bg-slate-950/55 px-4 py-3 text-sm font-semibold text-slate-200 transition hover:bg-slate-800 disabled:opacity-50"
                                :disabled="busy"
                                @click="saveCurrentDraft"
                            >
                                {{ busy === 'save' ? 'Bewaren…' : 'Concept bewaren' }}
                            </button>
                            <button
                                type="button"
                                class="rounded-2xl border border-slate-700 bg-slate-950/55 px-4 py-3 text-sm font-semibold text-slate-200 transition hover:bg-slate-800 disabled:opacity-50"
                                :disabled="busy"
                                @click="regenerate"
                            >
                                {{ busy === 'regenerate' ? 'Opstellen…' : 'Opnieuw opstellen' }}
                            </button>

                            <div class="ml-auto flex flex-wrap gap-1">
                                <button type="button" class="rounded-2xl px-3 py-3 text-sm font-semibold text-slate-400 hover:text-white disabled:opacity-50" :disabled="busy" title="Zelf beantwoord buiten Playdrive" @click="changeStatus('done')">
                                    Afgehandeld
                                </button>
                                <button v-if="detail.status !== 'manual'" type="button" class="rounded-2xl px-3 py-3 text-sm font-semibold text-slate-400 hover:text-white disabled:opacity-50" :disabled="busy" @click="changeStatus('manual')">
                                    Naar medewerker
                                </button>
                                <button type="button" class="rounded-2xl px-3 py-3 text-sm font-semibold text-slate-400 hover:text-white disabled:opacity-50" :disabled="busy" @click="changeStatus('ignored')">
                                    Negeren
                                </button>
                            </div>
                        </div>
                    </section>

                    <div v-else class="flex flex-wrap items-center justify-between gap-3 rounded-3xl border border-slate-800 bg-slate-900/90 px-5 py-4 text-sm text-slate-300">
                        <span>Genegeerd: {{ detail.ignore_reason || 'automatisch bericht' }}</span>
                        <button type="button" class="rounded-2xl border border-slate-700 px-4 py-2.5 text-sm font-semibold text-slate-200 hover:bg-slate-800" :disabled="busy" @click="reopenAndDraft">
                            Toch beantwoorden
                        </button>
                    </div>
                </div>

                <!-- Context -->
                <aside class="flex flex-col gap-4 xl:w-[320px] xl:shrink-0" aria-label="Context">
                    <div v-if="detail.needs_human && detail.needs_human_reason" class="rounded-3xl border border-rose-500/30 bg-rose-500/10 px-4 py-4">
                        <p class="font-semibold text-rose-200">Voor een medewerker</p>
                        <p class="mt-1 text-sm text-rose-100/90">{{ detail.needs_human_reason }}</p>
                    </div>

                    <div v-if="detail.draft_warnings?.length" class="rounded-3xl border border-amber-500/30 bg-amber-500/10 px-4 py-4">
                        <p class="font-semibold text-amber-200">Nakijken</p>
                        <ul class="mt-1.5 list-disc space-y-1 pl-4 text-sm text-amber-100/90">
                            <li v-for="(w, i) in detail.draft_warnings" :key="i">{{ w }}</li>
                        </ul>
                    </div>

                    <div class="rounded-3xl border border-slate-800 bg-slate-900/90 p-4">
                        <h3 class="mb-3 font-semibold text-white">Gebruikte gegevens</h3>
                        <ul v-if="detail.draft_sources?.length" class="flex flex-col gap-2">
                            <li v-for="(s, i) in detail.draft_sources" :key="i" class="flex items-start gap-3 rounded-2xl border border-slate-800 bg-slate-950/70 px-3 py-2.5">
                                <span class="mt-1.5 h-2.5 w-2.5 shrink-0 rounded-full bg-emerald-400"></span>
                                <div class="min-w-0">
                                    <p class="text-sm font-semibold text-white">{{ s.label }}</p>
                                    <p v-if="s.detail" class="text-xs text-slate-400">{{ s.detail }}</p>
                                </div>
                            </li>
                        </ul>
                        <p v-else class="text-sm text-slate-400">Geen Playdrive-gegevens gebruikt.</p>
                        <p v-if="detail.account" class="mt-3 text-xs text-slate-400">Account: <span class="text-slate-200">{{ detail.account.name }}</span></p>
                    </div>

                    <div v-if="detail.progress && !detail.progress.locked" class="rounded-3xl border border-slate-800 bg-slate-900/90 p-4">
                        <div class="flex items-baseline justify-between gap-2">
                            <h3 class="font-semibold text-white">{{ detail.progress.label }}</h3>
                            <span class="rounded-full border border-slate-700 bg-slate-950/70 px-2.5 py-0.5 text-xs text-slate-300">
                                {{ Math.min(detail.progress.streak, detail.progress.threshold) }} / {{ detail.progress.threshold }}
                            </span>
                        </div>
                        <p class="mb-3 mt-1.5 text-xs text-slate-400">
                            Na elkaar verstuurd zonder aanpassing. Vanaf {{ detail.progress.threshold }} kan deze categorie later automatisch.
                        </p>
                        <div class="flex gap-1" role="img" :aria-label="`${detail.progress.streak} van ${detail.progress.threshold}`">
                            <span
                                v-for="n in detail.progress.threshold"
                                :key="n"
                                class="h-1.5 flex-1 rounded-full"
                                :class="n <= detail.progress.streak ? 'bg-emerald-400' : 'bg-slate-700'"
                            ></span>
                        </div>
                    </div>

                    <div v-if="detail.history?.length" class="rounded-3xl border border-slate-800 bg-slate-900/90 p-4">
                        <h3 class="mb-2 font-semibold text-white">Eerdere mails</h3>
                        <ul class="space-y-1.5">
                            <li v-for="h in detail.history" :key="h.id">
                                <button type="button" class="w-full truncate text-left text-xs text-slate-400 hover:text-slate-200" @click="select(h.id)">
                                    {{ formatDateTime(h.received_at) }} · {{ h.subject || '(geen onderwerp)' }} · {{ statusMeta(h.status).label }}
                                </button>
                            </li>
                        </ul>
                    </div>
                </aside>
            </template>
        </div>
    </div>
</template>

<script setup>
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue'
import { ArrowPathIcon, SparklesIcon } from '@heroicons/vue/24/outline'
import {
    STATUS_META,
    checkMailbox,
    errorMessage,
    fetchInbox,
    fetchMessage,
    formatDateTime,
    regenerateDraft,
    saveDraft,
    sendReply,
    setMessageStatus,
} from '../services/mailAssistantApi'

defineEmits(['open-settings'])

const filters = [
    { key: 'review', label: 'Te beoordelen' },
    { key: 'manual', label: 'Medewerker' },
    { key: 'sent', label: 'Afgehandeld' },
    { key: 'ignored', label: 'Genegeerd' },
]

const filter = ref('review')
const search = ref('')
const messages = ref([])
const stats = ref({})
const mailbox = ref(null)
const loading = ref(false)
const checking = ref(false)
const error = ref('')
const notice = ref('')

const selectedId = ref(null)
const detail = ref(null)
const loadingDetail = ref(false)
const draft = ref('')
const busy = ref('')
const confirmSend = ref(false)

let refreshTimer = null

const dirty = computed(() => detail.value && draft.value !== (detail.value.draft_body ?? ''))

const summaryCards = computed(() => [
    { label: 'Te beoordelen', value: stats.value.review ?? 0, wrapper: 'border-blue-500/30 bg-blue-500/10', labelClass: 'text-blue-300' },
    { label: 'Voor een medewerker', value: stats.value.manual ?? 0, wrapper: 'border-rose-500/30 bg-rose-500/10', labelClass: 'text-rose-300' },
    { label: 'Vandaag verstuurd', value: stats.value.sent_today ?? 0, wrapper: 'border-emerald-500/30 bg-emerald-500/10', labelClass: 'text-emerald-300' },
    { label: 'Genegeerd', value: stats.value.ignored ?? 0, wrapper: 'border-slate-800 bg-slate-900/90', labelClass: 'text-slate-400' },
])

function statusMeta(status) {
    return STATUS_META[status] ?? { label: status, class: 'bg-slate-800 text-slate-300' }
}

function confidenceClass(value) {
    if (value >= 80) return 'bg-emerald-500/15 text-emerald-300'
    if (value >= 50) return 'bg-amber-500/15 text-amber-300'
    return 'bg-rose-500/15 text-rose-300'
}

function flash(message) {
    notice.value = message
    error.value = ''
    setTimeout(() => {
        if (notice.value === message) notice.value = ''
    }, 5000)
}

async function load({ silent = false } = {}) {
    if (!silent) loading.value = true

    try {
        const data = await fetchInbox({ tab: filter.value, search: search.value || undefined })
        messages.value = data.messages
        stats.value = data.stats
        mailbox.value = data.mailbox

        if (!silent && !selectedId.value && messages.value.length) {
            select(messages.value[0].id)
        }
    } catch (e) {
        if (!silent) error.value = errorMessage(e, 'Inbox laden mislukt.')
    } finally {
        loading.value = false
    }
}

function setFilter(key) {
    filter.value = key
    selectedId.value = null
    detail.value = null
    load()
}

async function select(id) {
    if (dirty.value && !window.confirm('Je concept is niet bewaard. Toch een andere mail openen?')) {
        return
    }

    selectedId.value = id
    confirmSend.value = false
    loadingDetail.value = true

    try {
        applyDetail(await fetchMessage(id))
    } catch (e) {
        error.value = errorMessage(e, 'Mail laden mislukt.')
    } finally {
        loadingDetail.value = false
    }
}

function applyDetail(data) {
    detail.value = data
    draft.value = data.draft_body ?? ''
}

function patchListItem(data) {
    const index = messages.value.findIndex(m => m.id === data.id)
    if (index !== -1) {
        messages.value[index] = { ...messages.value[index], ...data }
    }
}

async function saveCurrentDraft() {
    busy.value = 'save'
    try {
        const res = await saveDraft(detail.value.id, draft.value)
        applyDetail(res.data)
        flash(res.message)
    } catch (e) {
        error.value = errorMessage(e, 'Bewaren mislukt.')
    } finally {
        busy.value = ''
    }
}

async function send() {
    busy.value = 'send'
    try {
        const res = await sendReply(detail.value.id, draft.value)
        applyDetail(res.data)
        stats.value = res.stats
        confirmSend.value = false
        flash(res.message)
        removeFromListIfFiltered(res.data)
    } catch (e) {
        error.value = errorMessage(e, 'Versturen mislukt.')
    } finally {
        busy.value = ''
    }
}

async function regenerate() {
    if (dirty.value && !window.confirm('Je aanpassingen gaan verloren. Opnieuw opstellen?')) {
        return
    }

    busy.value = 'regenerate'
    try {
        const res = await regenerateDraft(detail.value.id)
        applyDetail(res.data)
        stats.value = res.stats
        patchListItem(res.data)
        flash(res.message)
    } catch (e) {
        error.value = errorMessage(e, 'Opstellen mislukt.')
        await select(detail.value.id)
    } finally {
        busy.value = ''
    }
}

async function changeStatus(status) {
    busy.value = 'status'
    try {
        const res = await setMessageStatus(detail.value.id, status)
        applyDetail(res.data)
        stats.value = res.stats
        removeFromListIfFiltered(res.data)
    } catch (e) {
        error.value = errorMessage(e, 'Wijzigen mislukt.')
    } finally {
        busy.value = ''
    }
}

async function reopenAndDraft() {
    await changeStatus('review')
    await regenerate()
}

function removeFromListIfFiltered(data) {
    const map = { review: ['draft', 'new', 'error'], manual: ['manual'], sent: ['sent', 'done'], ignored: ['ignored'] }

    if (!(map[filter.value] ?? []).includes(data.status)) {
        messages.value = messages.value.filter(m => m.id !== data.id)
    } else {
        patchListItem(data)
    }
}

async function checkNow() {
    checking.value = true
    try {
        const res = await checkMailbox()
        stats.value = res.stats
        flash(res.message)
        await load({ silent: true })
    } catch (e) {
        error.value = errorMessage(e, 'Mailbox controleren mislukt.')
    } finally {
        checking.value = false
    }
}

watch(search, (value) => {
    if (value === '') load()
})

onMounted(() => {
    load()
    refreshTimer = setInterval(() => load({ silent: true }), 60000)
})

onBeforeUnmount(() => clearInterval(refreshTimer))
</script>
