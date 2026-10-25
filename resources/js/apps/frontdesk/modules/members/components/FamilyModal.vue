<template>
    <div
        v-if="open"
        class="fixed inset-0 z-[100] flex items-center justify-center bg-black/70 p-6 backdrop-blur-sm"
        @click.self="$emit('close')"
    >
        <div class="flex max-h-[90vh] w-full max-w-2xl flex-col overflow-hidden rounded-3xl border border-slate-800 bg-slate-900 shadow-2xl">
            <!-- Header -->
            <div class="flex items-start justify-between border-b border-slate-800 px-6 py-5">
                <div>
                    <h2 class="text-xl font-semibold text-slate-100">Familieabonnement</h2>
                    <p class="mt-0.5 text-sm text-slate-400">
                        <template v-if="family">#{{ family.head_membership_id }} · {{ family.name }}</template>
                        <template v-else>Laden…</template>
                    </p>
                </div>
                <button type="button" class="inline-flex h-9 w-9 items-center justify-center rounded-xl text-slate-400 transition hover:bg-slate-800 hover:text-white" @click="$emit('close')">
                    <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12"/></svg>
                </button>
            </div>

            <!-- Body -->
            <div class="min-h-0 space-y-5 overflow-y-auto p-6">
                <div v-if="loading && !family" class="text-sm text-slate-400">Gezin laden…</div>

                <template v-if="family">
                    <!-- Hoofd + geldigheid -->
                    <div class="grid gap-4 md:grid-cols-3">
                        <div class="space-y-1 text-sm">
                            <div class="text-slate-400">Hoofd (betaler)</div>
                            <div class="font-semibold text-slate-100">{{ family.head.full_name }}</div>
                            <div class="text-xs text-slate-500">{{ family.head.email || 'Geen e-mail' }}</div>
                        </div>
                        <div class="space-y-1 text-sm">
                            <div class="text-slate-400">Geldig van</div>
                            <div class="font-semibold text-slate-100">{{ formatDate(family.membership_starts_at) }}</div>
                        </div>
                        <div class="space-y-1 text-sm">
                            <div class="text-slate-400">Geldig tot</div>
                            <div class="font-semibold text-slate-100">{{ formatDate(family.membership_ends_at) }}</div>
                        </div>
                    </div>
                    <p class="-mt-2 text-xs text-slate-500">
                        De geldigheid geldt voor alle gezinsleden. Aanpassen of verlengen doe je op het hoofdabonnement; verloopmails gaan enkel naar het hoofd.
                    </p>

                    <!-- Gezinsleden -->
                    <div class="rounded-2xl border border-slate-800 bg-slate-950/60 p-4">
                        <div class="mb-3 flex flex-wrap items-center justify-between gap-2">
                            <div class="text-sm font-semibold text-slate-200">Gezinsleden</div>
                            <div class="flex gap-2 text-xs">
                                <span class="rounded-full px-2.5 py-1 font-semibold" :class="limitClass('adults')">
                                    Volwassenen {{ family.counts.adults }} / {{ family.limits.adults ?? '∞' }}
                                </span>
                                <span class="rounded-full px-2.5 py-1 font-semibold" :class="limitClass('children')">
                                    Kinderen {{ family.counts.children }} / {{ family.limits.children ?? '∞' }}
                                </span>
                            </div>
                        </div>

                        <div class="space-y-2">
                            <div
                                v-for="person in people"
                                :key="person.tenant_membership_id"
                                class="flex items-center gap-3 rounded-xl border border-slate-800 bg-slate-900 px-3 py-2.5"
                            >
                                <div class="flex h-9 w-9 shrink-0 items-center justify-center rounded-xl bg-violet-500/15 text-xs font-bold text-violet-300">
                                    {{ initials(person.full_name) }}
                                </div>
                                <div class="min-w-0 flex-1">
                                    <div class="flex items-center gap-2">
                                        <span class="truncate text-sm font-semibold text-slate-100">{{ person.full_name }}</span>
                                        <span v-if="person.is_head" class="rounded-full bg-slate-800 px-2 py-0.5 text-[10px] font-semibold text-slate-400">hoofd</span>
                                    </div>
                                    <div class="truncate text-xs text-slate-400">
                                        {{ person.membership_type === 'student' ? 'Kind / student' : 'Volwassene' }}
                                        <template v-if="person.birth_date"> · °{{ formatDate(person.birth_date) }}</template>
                                        · {{ person.is_managed ? 'beheerd account' : (person.email ? 'eigen account' : 'geen e-mail') }}
                                        · {{ person.rfid_uid ? 'kaart gekoppeld' : 'geen kaart' }}
                                    </div>
                                </div>
                                <template v-if="!person.is_head">
                                    <button
                                        v-if="confirmRemoveId === person.tenant_membership_id"
                                        type="button"
                                        class="shrink-0 rounded-lg border border-rose-500/40 bg-rose-500/10 px-2.5 py-1.5 text-xs font-semibold text-rose-200 transition hover:bg-rose-500/20"
                                        :disabled="busy"
                                        @click="removeMember(person)"
                                    >
                                        Uit gezin halen?
                                    </button>
                                    <button
                                        v-else
                                        type="button"
                                        class="inline-flex h-8 w-8 shrink-0 items-center justify-center rounded-lg text-slate-400 transition hover:bg-slate-800 hover:text-rose-300"
                                        title="Uit gezin halen"
                                        @click="confirmRemoveId = person.tenant_membership_id"
                                    >
                                        <XMarkIcon class="h-4 w-4" />
                                    </button>
                                </template>
                            </div>
                        </div>

                        <!-- Limiet bereikt -->
                        <div v-if="limitMessage" class="mt-3 rounded-xl border border-amber-500/30 bg-amber-500/10 px-3 py-2.5 text-sm text-amber-200">
                            {{ limitMessage }}
                            <div class="mt-2">
                                <button type="button" class="rounded-xl border border-amber-500/40 px-3 py-1.5 text-xs font-semibold text-amber-100 transition hover:bg-amber-500/20" :disabled="busy" @click="retryWithForce">
                                    Toch toevoegen
                                </button>
                            </div>
                        </div>

                        <div v-if="!showForm && !showExisting" class="mt-3 flex flex-wrap gap-2">
                            <button
                                type="button"
                                class="rounded-xl border border-slate-700 bg-slate-800 px-3 py-2 text-xs font-semibold text-slate-200 transition hover:bg-slate-700"
                                @click="openForm"
                            >
                                <span class="inline-flex items-center gap-1.5"><PlusIcon class="h-4 w-4" /> Nieuw gezinslid</span>
                            </button>
                            <button
                                type="button"
                                class="rounded-xl border border-violet-500/30 bg-violet-500/10 px-3 py-2 text-xs font-semibold text-violet-200 transition hover:bg-violet-500/20"
                                @click="openExisting"
                            >
                                <span class="inline-flex items-center gap-1.5"><UserPlusIcon class="h-4 w-4" /> Bestaand lid toevoegen</span>
                            </button>
                        </div>

                        <!-- Bestaand lid toevoegen (huidig abonnement omzetten) -->
                        <div v-if="showExisting" class="mt-3 space-y-3 rounded-xl border border-slate-800 bg-slate-900 p-4">
                            <div class="text-sm font-semibold text-slate-200">Bestaand lid toevoegen</div>

                            <template v-if="!selectedExisting">
                                <input
                                    ref="existingInput"
                                    v-model="existingQuery"
                                    type="text"
                                    placeholder="Zoek op naam, e-mail of RFID…"
                                    autocomplete="off"
                                    :class="fieldClass"
                                >

                                <div v-if="existingQuery.trim().length >= 2" class="max-h-60 overflow-y-auto rounded-xl border border-slate-800">
                                    <div v-if="existingSearching" class="px-3 py-3 text-xs text-slate-400">Zoeken…</div>
                                    <div v-else-if="!existingResults.length" class="px-3 py-3 text-xs text-slate-500">Geen leden gevonden</div>
                                    <button
                                        v-for="candidate in existingResults"
                                        v-else
                                        :key="candidate.tenant_membership_id"
                                        type="button"
                                        class="flex w-full items-center gap-3 border-b border-slate-800 px-3 py-2 text-left last:border-b-0"
                                        :class="existingBlockReason(candidate) ? 'cursor-not-allowed opacity-50' : 'hover:bg-slate-800'"
                                        @click="selectExisting(candidate)"
                                    >
                                        <div class="flex h-8 w-8 shrink-0 items-center justify-center rounded-lg bg-slate-800 text-xs font-bold text-slate-300">
                                            {{ initials(candidate.full_name) }}
                                        </div>
                                        <div class="min-w-0 flex-1">
                                            <div class="truncate text-sm font-semibold text-slate-100">{{ candidate.full_name }}</div>
                                            <div class="truncate text-[11px] text-slate-400">
                                                {{ typeLabel(candidate.membership_type) }}
                                                · {{ candidate.membership_ends_at ? `abonnement t/m ${formatDate(candidate.membership_ends_at)}` : 'geen abonnement' }}
                                            </div>
                                        </div>
                                        <span v-if="existingBlockReason(candidate)" class="shrink-0 text-[10px] font-semibold text-slate-400">
                                            {{ existingBlockReason(candidate) }}
                                        </span>
                                    </button>
                                </div>
                            </template>

                            <!-- Bevestigen -->
                            <div v-else class="space-y-3">
                                <div class="flex items-center gap-3 rounded-xl border border-violet-500/25 bg-violet-500/10 px-3 py-2.5">
                                    <div class="flex h-9 w-9 shrink-0 items-center justify-center rounded-xl bg-violet-500/15 text-xs font-bold text-violet-300">
                                        {{ initials(selectedExisting.full_name) }}
                                    </div>
                                    <div class="min-w-0 flex-1">
                                        <div class="truncate text-sm font-semibold text-slate-100">{{ selectedExisting.full_name }}</div>
                                        <div class="truncate text-xs text-slate-400">
                                            {{ selectedExisting.membership_type === 'student' ? 'Kind / student' : 'Volwassene' }}
                                        </div>
                                    </div>
                                </div>

                                <p class="text-xs text-slate-400">
                                    <template v-if="selectedExisting.membership_ends_at">
                                        Het eigen abonnement (t/m {{ formatDate(selectedExisting.membership_ends_at) }}) wordt omgezet:
                                    </template>
                                    {{ selectedExisting.full_name }} volgt voortaan de geldigheid van het gezin
                                    (t/m {{ formatDate(family.membership_ends_at) }}).
                                </p>

                                <div
                                    v-if="endsLaterThanFamily(selectedExisting)"
                                    class="rounded-xl border border-amber-500/30 bg-amber-500/10 px-3 py-2 text-xs text-amber-200"
                                >
                                    Let op: het eigen abonnement loopt langer dan het gezinsabonnement. Na het omzetten vervalt het op {{ formatDate(family.membership_ends_at) }}.
                                </div>

                                <div class="flex gap-3">
                                    <button type="button" class="flex-1 rounded-xl border border-slate-700 bg-slate-800 px-3 py-2.5 text-sm font-semibold text-slate-200 transition hover:bg-slate-700" @click="selectedExisting = null">Terug</button>
                                    <button type="button" class="flex-1 rounded-xl bg-blue-600 px-3 py-2.5 text-sm font-semibold text-white transition hover:bg-blue-500 disabled:opacity-50" :disabled="busy" @click="submitExisting(false)">
                                        {{ busy ? 'Toevoegen…' : 'Toevoegen aan gezin' }}
                                    </button>
                                </div>
                            </div>

                            <button v-if="!selectedExisting" type="button" class="text-xs text-slate-400 hover:text-white" @click="closeExisting">Annuleren</button>
                        </div>

                        <!-- Formulier gezinslid -->
                        <div v-if="showForm" class="mt-3 space-y-4 rounded-xl border border-slate-800 bg-slate-900 p-4">
                            <div class="grid gap-4 md:grid-cols-2">
                                <label class="space-y-2 text-sm text-slate-300">
                                    <span>Voornaam</span>
                                    <input v-model="newMember.first_name" type="text" :class="fieldClass">
                                </label>
                                <label class="space-y-2 text-sm text-slate-300">
                                    <span>Achternaam</span>
                                    <input v-model="newMember.last_name" type="text" :class="fieldClass">
                                </label>
                                <label class="space-y-2 text-sm text-slate-300">
                                    <span>Geboortedatum</span>
                                    <input v-model="newMember.birth_date" type="date" :class="fieldClass">
                                </label>
                                <label class="space-y-2 text-sm text-slate-300">
                                    <span>Type</span>
                                    <select v-model="newMember.membership_type" :class="fieldClass">
                                        <option value="student">Kind / student</option>
                                        <option value="adult">Volwassene</option>
                                    </select>
                                </label>
                                <label class="space-y-2 text-sm text-slate-300">
                                    <span>E-mail <span class="text-slate-500">(optioneel)</span></span>
                                    <input v-model="newMember.email" type="email" placeholder="Leeg = beheerd door het hoofd" :class="fieldClass">
                                </label>
                                <div class="space-y-2 text-sm text-slate-300">
                                    <span>RFID-kaart <span class="text-slate-500">(optioneel)</span></span>
                                    <div class="flex gap-2">
                                        <input v-model="newMember.rfid_uid" type="text" placeholder="Later koppelen" :class="fieldClass + ' flex-1'">
                                        <ScanRfidButton
                                            :model-value="newMember.rfid_uid"
                                            label="Scan"
                                            title="RFID-kaart scannen"
                                            description="Scan de kaart voor dit gezinslid."
                                            confirm-label="Koppelen"
                                            @update:model-value="newMember.rfid_uid = $event"
                                        />
                                    </div>
                                </div>
                            </div>
                            <p class="text-xs text-slate-500">
                                Zonder e-mail wordt een beheerd account aangemaakt onder het hoofd (adres wordt overgenomen). Met e-mail wordt een bestaand Playdrive-account hergebruikt.
                            </p>
                            <div class="flex gap-3">
                                <button type="button" class="flex-1 rounded-xl border border-slate-700 bg-slate-800 px-3 py-2.5 text-sm font-semibold text-slate-200 transition hover:bg-slate-700" @click="closeForm">Annuleren</button>
                                <button type="button" class="flex-1 rounded-xl bg-blue-600 px-3 py-2.5 text-sm font-semibold text-white transition hover:bg-blue-500 disabled:opacity-50" :disabled="busy" @click="submitMember(false)">
                                    {{ busy ? 'Toevoegen…' : 'Toevoegen' }}
                                </button>
                            </div>
                        </div>
                    </div>
                </template>

                <div v-if="error" class="rounded-2xl border border-rose-500/25 bg-rose-500/10 px-4 py-3 text-sm text-rose-200">{{ error }}</div>
            </div>

            <!-- Footer -->
            <div class="flex justify-end border-t border-slate-800 px-6 py-4">
                <button type="button" class="rounded-2xl border border-slate-700 bg-slate-800 px-5 py-3 text-sm font-semibold text-slate-200 transition hover:bg-slate-700" @click="$emit('close')">Sluiten</button>
            </div>
        </div>
    </div>
</template>

<script setup>
import { PlusIcon, UserPlusIcon, XMarkIcon } from '@heroicons/vue/24/outline'
import { computed, nextTick, reactive, ref, watch } from 'vue'
import axios from '@/lib/http'
import ScanRfidButton from '@/shared/components/scanners/ScanRfidButton.vue'

const props = defineProps({
    open: { type: Boolean, default: false },
    membershipId: { type: Number, default: null },
})

const emit = defineEmits(['close', 'changed'])

const fieldClass = 'w-full rounded-xl border border-slate-700 bg-slate-950 px-3 py-2.5 text-sm text-slate-100 outline-none transition focus:border-blue-500'

const family = ref(null)
const loading = ref(false)
const busy = ref(false)
const error = ref('')
const limitMessage = ref('')
const showForm = ref(false)
const confirmRemoveId = ref(null)
const lastAction = ref('new')

// Bestaand lid toevoegen
const showExisting = ref(false)
const existingQuery = ref('')
const existingResults = ref([])
const existingSearching = ref(false)
const selectedExisting = ref(null)
const existingInput = ref(null)
let existingTimer = null
let existingSeq = 0

const newMember = reactive({
    first_name: '',
    last_name: '',
    birth_date: '',
    membership_type: 'student',
    email: '',
    rfid_uid: '',
})

const people = computed(() => family.value ? [family.value.head, ...family.value.members] : [])

watch(() => [props.open, props.membershipId], ([open]) => {
    if (!open) return
    family.value = null
    error.value = ''
    limitMessage.value = ''
    showForm.value = false
    closeExisting()
    load()
})

async function load() {
    if (!props.membershipId) return
    loading.value = true
    try {
        const { data } = await axios.get(`/api/frontdesk/members/${props.membershipId}/family`)
        family.value = data.data
    } catch (err) {
        error.value = err?.response?.data?.message ?? 'Gezin kon niet geladen worden.'
    } finally {
        loading.value = false
    }
}

function resetForm() {
    newMember.first_name = ''
    newMember.last_name = family.value?.head?.last_name ?? ''
    newMember.birth_date = ''
    newMember.membership_type = 'student'
    newMember.email = ''
    newMember.rfid_uid = ''
}

function openForm() {
    resetForm()
    error.value = ''
    limitMessage.value = ''
    closeExisting()
    showForm.value = true
}

async function openExisting() {
    error.value = ''
    limitMessage.value = ''
    showForm.value = false
    showExisting.value = true
    await nextTick()
    existingInput.value?.focus()
}

function closeExisting() {
    showExisting.value = false
    existingQuery.value = ''
    existingResults.value = []
    selectedExisting.value = null
    limitMessage.value = ''
}

function existingBlockReason(candidate) {
    if (!family.value) return ''
    if (candidate.tenant_membership_id === family.value.head_membership_id) return 'hoofd'
    if (candidate.family_membership_id === family.value.head_membership_id) return 'al in gezin'
    if (candidate.is_family_head) return 'eigen gezin'
    if (candidate.family_membership_id) return 'ander gezin'
    return ''
}

function selectExisting(candidate) {
    if (existingBlockReason(candidate)) return
    error.value = ''
    limitMessage.value = ''
    selectedExisting.value = candidate
}

function endsLaterThanFamily(candidate) {
    const own = candidate?.membership_ends_at
    const fam = family.value?.membership_ends_at
    return !!own && !!fam && own > fam
}

function typeLabel(type) {
    if (type === 'student') return 'Student'
    if (type === 'adult') return 'Volwassene'
    if (type === 'family') return 'Gezinshoofd'
    return 'Lid'
}

watch(existingQuery, (value) => {
    window.clearTimeout(existingTimer)
    const q = value.trim()

    if (q.length < 2) {
        existingResults.value = []
        existingSearching.value = false
        return
    }

    existingSearching.value = true
    const seq = ++existingSeq

    existingTimer = window.setTimeout(async () => {
        try {
            const { data } = await axios.get('/api/frontdesk/members/subscriber-lookup', { params: { q } })
            if (seq === existingSeq) existingResults.value = data?.data ?? []
        } catch {
            if (seq === existingSeq) existingResults.value = []
        } finally {
            if (seq === existingSeq) existingSearching.value = false
        }
    }, 250)
})

async function submitExisting(force) {
    if (!family.value || !selectedExisting.value) return
    error.value = ''
    lastAction.value = 'existing'
    busy.value = true

    try {
        const { data } = await axios.post(`/api/frontdesk/members/${family.value.head_membership_id}/family/members`, {
            tenant_membership_id: selectedExisting.value.tenant_membership_id,
            force,
        })
        family.value = data.data
        closeExisting()
        emit('changed')
    } catch (err) {
        handleSubmitError(err)
    } finally {
        busy.value = false
    }
}

function retryWithForce() {
    if (lastAction.value === 'existing') {
        submitExisting(true)
    } else {
        submitMember(true)
    }
}

function handleSubmitError(err) {
    const res = err?.response?.data
    if (res?.limit_reached) {
        limitMessage.value = res.message
    } else {
        error.value = res?.errors ? Object.values(res.errors).flat()[0] : (res?.message ?? 'Toevoegen mislukt.')
    }
}

function closeForm() {
    showForm.value = false
    limitMessage.value = ''
}

async function submitMember(force) {
    if (!family.value) return
    error.value = ''

    if (!newMember.first_name.trim() || !newMember.last_name.trim()) {
        error.value = 'Voornaam en achternaam zijn verplicht.'
        return
    }

    lastAction.value = 'new'
    busy.value = true
    try {
        const { data } = await axios.post(`/api/frontdesk/members/${family.value.head_membership_id}/family/members`, {
            ...newMember,
            birth_date: newMember.birth_date || null,
            email: newMember.email || null,
            rfid_uid: newMember.rfid_uid || null,
            force,
        })
        family.value = data.data
        limitMessage.value = ''
        showForm.value = false
        emit('changed')
    } catch (err) {
        handleSubmitError(err)
    } finally {
        busy.value = false
    }
}

async function removeMember(person) {
    if (!family.value || busy.value) return
    error.value = ''
    busy.value = true
    try {
        const { data } = await axios.delete(`/api/frontdesk/members/${family.value.head_membership_id}/family/members/${person.tenant_membership_id}`)
        family.value = data.data
        emit('changed')
    } catch (err) {
        error.value = err?.response?.data?.message ?? 'Verwijderen mislukt.'
    } finally {
        busy.value = false
        confirmRemoveId.value = null
    }
}

function limitClass(key) {
    const limit = family.value?.limits?.[key]
    const count = family.value?.counts?.[key] ?? 0
    if (limit !== null && limit !== undefined && count >= limit) return 'bg-amber-500/15 text-amber-300'
    return 'bg-slate-800 text-slate-400'
}

function initials(name) {
    return String(name || '?').split(' ').filter(Boolean).map(p => p[0]).slice(0, 2).join('').toUpperCase()
}

function formatDate(value) {
    if (!value) return '—'
    const [y, m, d] = String(value).slice(0, 10).split('-')
    return `${d}/${m}/${y}`
}
</script>
