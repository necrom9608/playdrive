<template>
    <div class="rounded-xl border border-slate-700 bg-slate-950/60 p-3">
        <div class="mb-3 flex items-center justify-between">
            <div class="text-xs font-medium text-slate-400">
                🎫 Abonnees in deze groep
            </div>

            <span
                class="inline-flex rounded-full px-2 py-0.5 text-[11px] font-semibold"
                :class="model.subscribers.length
                    ? 'bg-emerald-500/15 text-emerald-300 ring-1 ring-emerald-500/25'
                    : 'bg-slate-800 text-slate-400'"
            >
                {{ model.subscribers.length }}
            </span>
        </div>

        <!-- Gekoppelde abonnees -->
        <div v-if="model.subscribers.length" class="space-y-2">
            <div
                v-for="subscriber in model.subscribers"
                :key="subscriber.tenant_membership_id ?? subscriber.id"
                class="flex items-center gap-3 rounded-xl border border-emerald-500/25 bg-emerald-500/10 px-3 py-2"
            >
                <div class="flex h-8 w-8 shrink-0 items-center justify-center rounded-lg bg-emerald-500/20 text-xs font-bold text-emerald-300">
                    {{ initials(subscriber.full_name) }}
                </div>

                <div class="min-w-0 flex-1">
                    <div class="truncate text-sm font-semibold text-slate-100">
                        {{ subscriber.full_name }}
                    </div>
                    <div class="truncate text-[11px] text-slate-400">
                        {{ typeLabel(subscriber.membership_type) }}
                        <template v-if="subscriber.membership_ends_at">
                            · t/m {{ formatDate(subscriber.membership_ends_at) }}
                        </template>
                        <span v-if="subscriber.expires_soon" class="ml-1 text-amber-300">· verloopt binnenkort</span>
                    </div>
                </div>

                <button
                    type="button"
                    class="inline-flex h-7 w-7 shrink-0 items-center justify-center rounded-lg text-slate-400 transition hover:bg-slate-800 hover:text-white"
                    title="Verwijderen"
                    @click="removeSubscriber(subscriber)"
                >
                    ✕
                </button>
            </div>
        </div>

        <div
            v-else
            class="rounded-xl border border-dashed border-slate-700 px-3 py-3 text-center text-xs text-slate-500"
        >
            Geen abonnees — iedereen betaalt speeltijd
        </div>

        <!-- Fout -->
        <div
            v-if="error"
            class="mt-2 rounded-xl border border-rose-500/25 bg-rose-500/10 px-3 py-2 text-xs text-rose-200"
        >
            {{ error }}
        </div>

        <!-- Acties -->
        <div class="mt-3 flex gap-2">
            <button
                type="button"
                class="inline-flex flex-1 items-center justify-center gap-1 rounded-xl border border-slate-700 bg-slate-800 px-3 py-2 text-xs font-semibold text-slate-200 transition hover:bg-slate-700"
                @click="toggleSearch"
            >
                ＋ Abonnee zoeken
            </button>

            <ScanRfidButton
                label="Scan"
                title="Abonneekaart scannen"
                description="Scan de kaart van de abonnee."
                confirm-label="Toevoegen"
                @confirmed="handleRfid"
            />
        </div>

        <!-- Zoeken -->
        <div v-if="showSearch" class="mt-3">
            <input
                ref="searchInput"
                v-model="query"
                type="text"
                placeholder="Naam, e-mail of RFID…"
                autocomplete="off"
                class="w-full rounded-xl border border-slate-700 bg-slate-950 px-3 py-2.5 text-sm text-slate-100 outline-none transition focus:border-blue-500"
                @keydown.enter.prevent="addFirstValidResult"
                @keydown.esc.prevent="closeSearch"
            >

            <div
                v-if="query.trim().length >= 2"
                class="mt-2 max-h-64 overflow-y-auto rounded-xl border border-slate-700 bg-slate-900"
            >
                <div v-if="searching" class="px-3 py-3 text-xs text-slate-400">
                    Zoeken…
                </div>

                <div v-else-if="!results.length" class="px-3 py-3 text-xs text-slate-500">
                    Geen leden gevonden
                </div>

                <button
                    v-for="candidate in results"
                    v-else
                    :key="candidate.tenant_membership_id"
                    type="button"
                    class="flex w-full items-center gap-3 border-b border-slate-800 px-3 py-2 text-left last:border-b-0"
                    :class="isSelectable(candidate) ? 'hover:bg-slate-800' : 'cursor-not-allowed opacity-60'"
                    @click="addSubscriber(candidate)"
                >
                    <div
                        class="flex h-8 w-8 shrink-0 items-center justify-center rounded-lg text-xs font-bold"
                        :class="candidate.is_valid ? 'bg-emerald-500/20 text-emerald-300' : 'bg-slate-800 text-slate-400'"
                    >
                        {{ initials(candidate.full_name) }}
                    </div>

                    <div class="min-w-0 flex-1">
                        <div class="truncate text-sm font-semibold text-slate-100">
                            {{ candidate.full_name }}
                        </div>
                        <div class="truncate text-[11px] text-slate-400">
                            {{ typeLabel(candidate.membership_type) }}
                            <template v-if="candidate.membership_ends_at">
                                · t/m {{ formatDate(candidate.membership_ends_at) }}
                            </template>
                        </div>
                    </div>

                    <span
                        class="inline-flex shrink-0 rounded-full px-2 py-0.5 text-[10px] font-semibold"
                        :class="badgeClass(candidate)"
                    >
                        {{ badgeLabel(candidate) }}
                    </span>
                </button>
            </div>
        </div>
    </div>
</template>

<script setup>
import axios from '@/lib/http'
import { nextTick, ref, watch } from 'vue'
import ScanRfidButton from '@/shared/components/scanners/ScanRfidButton.vue'

const model = defineModel({ type: Object, required: true })

const showSearch = ref(false)
const query = ref('')
const results = ref([])
const searching = ref(false)
const error = ref('')
const searchInput = ref(null)

let searchTimer = null
let searchSeq = 0

const SCOPE_FIELD = {
    children: 'participants_children',
    adults: 'participants_adults',
}

function initials(name) {
    return String(name || '?')
        .split(' ')
        .filter(Boolean)
        .map(part => part[0])
        .slice(0, 2)
        .join('')
        .toUpperCase()
}

function typeLabel(type) {
    if (type === 'student') return 'Student'
    if (type === 'adult') return 'Volwassene'
    return 'Abonnement'
}

function formatDate(value) {
    if (!value) return ''
    const [y, m, d] = String(value).slice(0, 10).split('-')
    return `${d}/${m}/${y}`
}

function isAdded(candidate) {
    return model.value.subscribers.some(s => s.tenant_membership_id === candidate.tenant_membership_id)
}

function isSelectable(candidate) {
    return candidate.is_valid && !isAdded(candidate)
}

function badgeLabel(candidate) {
    if (isAdded(candidate)) return 'Toegevoegd'
    if (!candidate.is_active) return 'Inactief'
    if (!candidate.is_valid) return 'Verlopen'
    return 'Actief'
}

function badgeClass(candidate) {
    if (isAdded(candidate)) return 'bg-sky-500/15 text-sky-300'
    if (!candidate.is_valid) return 'bg-rose-500/15 text-rose-300'
    return 'bg-emerald-500/15 text-emerald-300'
}

async function lookup(params) {
    const response = await axios.get('/api/frontdesk/members/subscriber-lookup', {
        params: { ...params, date: model.value.event_date || undefined },
    })

    return response.data?.data ?? []
}

function addSubscriber(candidate) {
    error.value = ''

    if (isAdded(candidate)) {
        error.value = `${candidate.full_name} is al toegevoegd.`
        return
    }

    if (!candidate.is_valid) {
        error.value = candidate.membership_ends_at
            ? `${candidate.full_name} heeft geen actief abonnement (geldig t/m ${formatDate(candidate.membership_ends_at)}).`
            : `${candidate.full_name} heeft geen actief abonnement.`
        return
    }

    model.value.subscribers.push({
        tenant_membership_id: candidate.tenant_membership_id,
        account_id: candidate.account_id,
        full_name: candidate.full_name,
        email: candidate.email,
        membership_type: candidate.membership_type,
        participant_scope: candidate.participant_scope,
        membership_ends_at: candidate.membership_ends_at,
        is_active: candidate.is_active,
        is_valid: candidate.is_valid,
        expires_soon: candidate.expires_soon,
    })

    // Teller automatisch ophogen als er geen plaats meer is in de categorie
    const field = SCOPE_FIELD[candidate.participant_scope]
    if (field) {
        const count = model.value.subscribers.filter(s => s.participant_scope === candidate.participant_scope).length
        if (Number(model.value[field] || 0) < count) {
            model.value[field] = count
        }
    }

    // Lege klantnaam invullen met de eerste abonnee
    if (!String(model.value.name || '').trim()) {
        model.value.name = candidate.full_name
    }

    closeSearch()
}

function removeSubscriber(subscriber) {
    error.value = ''
    model.value.subscribers = model.value.subscribers.filter(s => s !== subscriber)
}

function addFirstValidResult() {
    const candidate = results.value.find(isSelectable)
    if (candidate) addSubscriber(candidate)
}

async function toggleSearch() {
    if (showSearch.value) {
        closeSearch()
        return
    }

    error.value = ''
    showSearch.value = true
    await nextTick()
    searchInput.value?.focus()
}

function closeSearch() {
    showSearch.value = false
    query.value = ''
    results.value = []
}

async function handleRfid(uid) {
    const rfid = String(uid || '').trim()
    if (!rfid) return

    error.value = ''

    try {
        const found = await lookup({ rfid })

        if (!found.length) {
            error.value = 'Geen abonnee gevonden voor deze kaart.'
            return
        }

        addSubscriber(found[0])
    } catch (e) {
        console.error('subscriber rfid lookup failed', e)
        error.value = 'Kaart kon niet opgezocht worden.'
    }
}

watch(query, (value) => {
    window.clearTimeout(searchTimer)

    const q = value.trim()
    if (q.length < 2) {
        results.value = []
        searching.value = false
        return
    }

    searching.value = true
    const seq = ++searchSeq

    searchTimer = window.setTimeout(async () => {
        try {
            const found = await lookup({ q })
            if (seq === searchSeq) results.value = found
        } catch (e) {
            console.error('subscriber lookup failed', e)
            if (seq === searchSeq) results.value = []
        } finally {
            if (seq === searchSeq) searching.value = false
        }
    }, 250)
})
</script>
