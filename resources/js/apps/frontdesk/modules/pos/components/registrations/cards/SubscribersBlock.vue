<template>
    <div class="rounded-xl border border-slate-700 bg-slate-950/60 p-3">
        <div class="mb-3 flex items-center justify-between">
            <div class="flex items-center gap-1.5 text-xs font-medium text-slate-400">
                <TicketIcon class="h-4 w-4" />
                Abonnees in deze groep
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
                    <XMarkIcon class="h-4 w-4" />
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
                <PlusIcon class="h-4 w-4" />
                Abonnee zoeken
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

                <div v-else-if="!results.length && !families.length" class="px-3 py-3 text-xs text-slate-500">
                    Geen leden gevonden
                </div>

                <template v-if="!searching">
                    <div
                        v-for="family in families"
                        :key="'fam-' + family.head_membership_id"
                        class="flex items-center gap-3 border-b border-slate-800 bg-violet-500/5 px-3 py-2"
                    >
                        <div class="flex h-8 w-8 shrink-0 items-center justify-center rounded-lg bg-violet-500/15 text-sm">
                            <UserGroupIcon class="h-4 w-4 text-violet-300" />
                        </div>
                        <div class="min-w-0 flex-1">
                            <div class="truncate text-sm font-semibold text-slate-100">{{ family.name }}</div>
                            <div class="truncate text-[11px] text-slate-400">
                                {{ family.members.length }} personen
                                <template v-if="family.membership_ends_at">· t/m {{ formatDate(family.membership_ends_at) }}</template>
                            </div>
                        </div>
                        <button
                            v-if="family.is_valid"
                            type="button"
                            class="shrink-0 rounded-lg border border-violet-500/35 bg-violet-500/15 px-2.5 py-1.5 text-[11px] font-semibold text-violet-200 transition hover:bg-violet-500/25"
                            @click="openFamilyPick(family)"
                        >
                            Gezin toevoegen
                        </button>
                        <span v-else class="shrink-0 rounded-full bg-rose-500/15 px-2 py-0.5 text-[10px] font-semibold text-rose-300">Verlopen</span>
                    </div>
                </template>

                <template v-if="!searching">
                <button
                    v-for="candidate in results"
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
                </template>
            </div>
        </div>

        <!-- Voorstel na scan van een gezinslid -->
        <div
            v-if="familyPrompt && !familyPick"
            class="mt-3 flex items-center gap-2 rounded-xl border border-violet-500/30 bg-violet-500/10 px-3 py-2 text-xs text-violet-200"
        >
            <span class="flex-1">Ook andere leden van {{ familyPrompt.name }} toevoegen?</span>
            <button type="button" class="rounded-lg bg-violet-500/20 px-2.5 py-1 font-semibold hover:bg-violet-500/30" @click="openFamilyPick(familyPrompt)">Ja</button>
            <button type="button" class="rounded-lg px-2 py-1 text-slate-400 hover:text-white" @click="familyPrompt = null">Nee</button>
        </div>

        <!-- Gezin: wie is er mee? -->
        <div v-if="familyPick" class="mt-3 rounded-xl border border-violet-500/35 bg-violet-500/5 p-3">
            <div class="mb-1 text-sm font-semibold text-slate-100">{{ familyPick.name }} — wie is er vandaag mee?</div>

            <label
                v-for="person in familyPick.members"
                :key="'pick-' + person.tenant_membership_id"
                class="flex items-center gap-3 rounded-lg px-1 py-1.5"
                :class="isAdded(person) || !person.is_valid ? 'opacity-50' : 'cursor-pointer'"
            >
                <input
                    v-model="pickedIds"
                    type="checkbox"
                    :value="person.tenant_membership_id"
                    :disabled="isAdded(person) || !person.is_valid"
                    class="h-4 w-4 rounded border-slate-600 bg-slate-900 text-violet-500"
                >
                <span class="min-w-0 flex-1 truncate text-sm text-slate-200">{{ person.full_name }}</span>
                <span class="shrink-0 text-[11px] text-slate-400">
                    {{ isAdded(person) ? 'al toegevoegd' : typeLabel(person.membership_type) }}
                </span>
            </label>

            <div class="mt-2 flex gap-2">
                <button type="button" class="flex-1 rounded-lg border border-slate-700 bg-slate-800 px-3 py-2 text-xs font-semibold text-slate-200 hover:bg-slate-700" @click="closeFamilyPick">
                    Annuleren
                </button>
                <button
                    type="button"
                    class="flex-1 rounded-lg bg-blue-600 px-3 py-2 text-xs font-semibold text-white hover:bg-blue-500 disabled:opacity-50"
                    :disabled="!pickedIds.length"
                    @click="confirmFamilyPick"
                >
                    Toevoegen ({{ pickedIds.length }})
                </button>
            </div>
        </div>
    </div>
</template>

<script setup>
import { PlusIcon, TicketIcon, UserGroupIcon, XMarkIcon } from '@heroicons/vue/24/outline'
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
const families = ref([])
const familyPick = ref(null)
const familyPrompt = ref(null)
const pickedIds = ref([])

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
    if (type === 'family') return 'Volwassene · gezinshoofd'
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

    return {
        results: response.data?.data ?? [],
        families: response.data?.families ?? [],
    }
}

function addSubscriber(candidate, { close = true } = {}) {
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

    if (close) closeSearch()
}

function openFamilyPick(family) {
    error.value = ''
    familyPrompt.value = null
    familyPick.value = family
    pickedIds.value = family.members
        .filter(person => person.is_valid && !isAdded(person))
        .map(person => person.tenant_membership_id)
}

function closeFamilyPick() {
    familyPick.value = null
    pickedIds.value = []
}

function confirmFamilyPick() {
    const family = familyPick.value
    if (!family) return

    family.members
        .filter(person => pickedIds.value.includes(person.tenant_membership_id))
        .forEach(person => addSubscriber(person, { close: false }))

    // Lege of automatisch ingevulde klantnaam → gezinsnaam
    const firstNames = family.members.map(person => person.full_name)
    if (!String(model.value.name || '').trim() || firstNames.includes(model.value.name)) {
        model.value.name = family.name
    }

    closeFamilyPick()
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
    families.value = []
}

async function handleRfid(uid) {
    const rfid = String(uid || '').trim()
    if (!rfid) return

    error.value = ''

    try {
        const found = await lookup({ rfid })

        if (!found.results.length) {
            error.value = 'Geen abonnee gevonden voor deze kaart.'
            return
        }

        const candidate = found.results[0]
        addSubscriber(candidate)

        // Gezinslid gescand: voorstellen om de rest van het gezin toe te voegen
        const family = found.families.find(f => f.head_membership_id === candidate.family_membership_id)
        if (family && family.is_valid && family.members.some(person => person.is_valid && !isAdded(person))) {
            familyPrompt.value = family
        }
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
        families.value = []
        searching.value = false
        return
    }

    searching.value = true
    const seq = ++searchSeq

    searchTimer = window.setTimeout(async () => {
        try {
            const found = await lookup({ q })
            if (seq === searchSeq) {
                results.value = found.results
                families.value = found.families
            }
        } catch (e) {
            console.error('subscriber lookup failed', e)
            if (seq === searchSeq) {
                results.value = []
                families.value = []
            }
        } finally {
            if (seq === searchSeq) searching.value = false
        }
    }, 250)
})
</script>
