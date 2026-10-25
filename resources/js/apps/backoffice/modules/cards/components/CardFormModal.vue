<template>
    <div v-if="open" class="fixed inset-0 z-50 flex items-center justify-center bg-slate-950/80 p-4" @click.self="$emit('close')">
        <form class="flex max-h-[92vh] w-full max-w-5xl flex-col overflow-hidden rounded-3xl border border-slate-800 bg-slate-900 shadow-2xl" @submit.prevent="submit(false)">
            <!-- Header -->
            <div class="flex items-start justify-between gap-4 border-b border-slate-800 px-6 py-5">
                <div>
                    <h2 class="text-xl font-semibold text-white">{{ form.id ? 'Kaart bewerken' : 'Nieuwe kaart' }}</h2>
                    <p class="mt-1 text-sm text-slate-400">Abonnementskaart, personeelsbadge of cadeaubonkaart</p>
                </div>
                <button type="button" class="rounded-2xl border border-slate-700 bg-slate-800 p-2 text-slate-200 transition hover:border-slate-500 hover:bg-slate-700" aria-label="Sluiten" @click="$emit('close')">
                    <XMarkIcon class="h-5 w-5" />
                </button>
            </div>

            <div class="min-h-0 overflow-y-auto px-6 py-5">
                <div v-if="store.formErrorMessage || Object.keys(store.formErrors || {}).length" class="mb-5 rounded-2xl border border-rose-500/30 bg-rose-500/10 p-4 text-sm text-rose-100">
                    <div class="font-semibold">Opslaan mislukt</div>
                    <p class="mt-1">{{ firstError }}</p>
                </div>

                <div class="grid gap-6 lg:grid-cols-[minmax(0,1.25fr)_minmax(0,0.75fr)]">
                    <!-- Links: gegevens -->
                    <div>
                        <!-- Type -->
                        <div class="inline-flex gap-1 rounded-2xl border border-slate-700 bg-slate-950 p-1">
                            <button
                                v-for="type in types"
                                :key="type.value"
                                type="button"
                                class="inline-flex items-center gap-2 rounded-xl px-4 py-2 text-sm font-semibold transition"
                                :class="form.card_type === type.value ? 'bg-blue-600 text-white' : 'text-slate-300 hover:bg-slate-800'"
                                @click="setType(type.value)"
                            >
                                <component :is="type.icon" class="h-4 w-4" />
                                {{ type.label }}
                            </button>
                        </div>

                        <!-- Lid -->
                        <template v-if="form.card_type === 'member'">
                            <div class="mb-2 mt-5 flex items-center justify-between">
                                <span class="text-sm text-slate-300">Lid</span>
                                <label v-if="!selectedMember" class="flex cursor-pointer items-center gap-2 text-xs text-slate-400">
                                    <input v-model="onlyWithoutCard" type="checkbox" class="h-4 w-4 rounded border-slate-600 bg-slate-900 text-blue-600">
                                    enkel actieve leden zonder kaart
                                </label>
                            </div>

                            <div v-if="selectedMember" class="space-y-2">
                                <div class="flex items-center gap-3 rounded-2xl border border-blue-500/50 bg-blue-500/10 px-3 py-2.5">
                                    <div class="flex h-9 w-9 shrink-0 items-center justify-center rounded-xl bg-violet-500/15 text-xs font-bold text-violet-300">{{ initials(selectedMember.name) }}</div>
                                    <div class="min-w-0 flex-1">
                                        <div class="truncate font-semibold text-white">
                                            {{ selectedMember.name }} <span class="text-xs font-normal text-slate-400">#{{ selectedMember.id }}</span>
                                        </div>
                                        <div class="truncate text-xs text-slate-400">{{ memberMeta(selectedMember) }}</div>
                                    </div>
                                    <span class="shrink-0 rounded-full px-2.5 py-0.5 text-[11px] font-semibold" :class="statusClass(selectedMember.status)">{{ statusLabel(selectedMember.status) }}</span>
                                    <button type="button" class="shrink-0 rounded-xl border border-slate-700 bg-slate-800 px-3 py-1.5 text-xs font-semibold text-slate-200 hover:bg-slate-700" @click="clearMember">Wijzigen</button>
                                </div>

                                <div
                                    v-if="selectedMember.card && selectedMember.card.id !== form.id"
                                    class="flex items-start gap-2 rounded-xl border border-amber-500/30 bg-amber-500/10 px-3 py-2 text-xs text-amber-200"
                                >
                                    <ExclamationTriangleIcon class="mt-0.5 h-4 w-4 shrink-0" />
                                    <span>Dit lid heeft al kaart #{{ selectedMember.card.id }} ({{ selectedMember.card.status_label }}). Maak je een nieuwe kaart, zet de oude dan op „Geblokkeerd”.</span>
                                </div>
                                <div
                                    v-if="['expired', 'none'].includes(selectedMember.status)"
                                    class="flex items-start gap-2 rounded-xl border border-amber-500/30 bg-amber-500/10 px-3 py-2 text-xs text-amber-200"
                                >
                                    <ExclamationTriangleIcon class="mt-0.5 h-4 w-4 shrink-0" />
                                    <span>Dit lid heeft geen actief abonnement.</span>
                                </div>
                            </div>

                            <div v-else>
                                <div class="relative">
                                    <MagnifyingGlassIcon class="pointer-events-none absolute left-3.5 top-1/2 h-4 w-4 -translate-y-1/2 text-slate-500" />
                                    <input
                                        v-model="memberQuery"
                                        type="text"
                                        autocomplete="off"
                                        placeholder="Zoek op naam, e-mail of lidnummer…"
                                        :class="fieldClass + ' pl-10'"
                                    >
                                </div>
                                <div class="mt-2 max-h-72 overflow-y-auto rounded-2xl border border-slate-700 bg-slate-950/60">
                                    <button
                                        v-for="member in filteredMembers"
                                        :key="member.id"
                                        type="button"
                                        class="flex w-full items-center gap-3 border-b border-slate-800 px-3 py-2.5 text-left transition last:border-b-0 hover:bg-slate-800"
                                        @click="selectMember(member)"
                                    >
                                        <div class="flex h-9 w-9 shrink-0 items-center justify-center rounded-xl bg-violet-500/15 text-xs font-bold text-violet-300">{{ initials(member.name) }}</div>
                                        <div class="min-w-0 flex-1">
                                            <div class="truncate font-semibold text-white">
                                                {{ member.name }} <span class="text-xs font-normal text-slate-500">#{{ member.id }}</span>
                                            </div>
                                            <div class="flex items-center gap-1 truncate text-xs text-slate-400">
                                                <UserGroupIcon v-if="member.family_name" class="h-3.5 w-3.5 shrink-0 text-violet-300" />
                                                {{ memberMeta(member) }}
                                            </div>
                                        </div>
                                        <div class="flex shrink-0 flex-col items-end gap-1">
                                            <span class="rounded-full px-2.5 py-0.5 text-[11px] font-semibold" :class="statusClass(member.status)">{{ statusLabel(member.status) }}</span>
                                            <span v-if="member.card" class="rounded-full bg-sky-500/15 px-2.5 py-0.5 text-[11px] font-semibold text-sky-300">kaart #{{ member.card.id }}</span>
                                        </div>
                                    </button>
                                    <div v-if="!filteredMembers.length" class="px-3 py-4 text-center text-sm text-slate-500">
                                        Geen leden gevonden<span v-if="onlyWithoutCard"> — zet het vinkje uit om alle leden te zien</span>.
                                    </div>
                                </div>
                                <div v-if="totalMatches > filteredMembers.length" class="mt-1.5 text-xs text-slate-500">
                                    Eerste {{ filteredMembers.length }} van {{ totalMatches }} — zoek verder om te verfijnen.
                                </div>
                            </div>
                        </template>

                        <!-- Personeel -->
                        <label v-if="form.card_type === 'staff'" class="mt-5 block space-y-2 text-sm text-slate-300">
                            <span>Medewerker</span>
                            <select v-model="form.holder_id" :class="fieldClass" required>
                                <option value="">Selecteer medewerker</option>
                                <option v-for="staff in sortedStaff" :key="staff.id" :value="staff.id">{{ staff.name }}</option>
                            </select>
                        </label>

                        <!-- Cadeaubon -->
                        <label v-if="form.card_type === 'voucher'" class="mt-5 block space-y-2 text-sm text-slate-300">
                            <span>Cadeaubon-type</span>
                            <select v-model="form.voucher_template_id" :class="fieldClass" required>
                                <option value="">Selecteer type</option>
                                <option v-for="template in store.voucherTemplates" :key="template.id" :value="template.id">{{ template.name }}</option>
                            </select>
                        </label>

                        <!-- RFID -->
                        <div class="mb-2 mt-5 flex items-center justify-between">
                            <span class="text-sm text-slate-300">RFID-chip</span>
                            <span
                                class="rounded-full px-2.5 py-0.5 text-[11px] font-semibold"
                                :class="hasRealRfid ? 'bg-emerald-500/15 text-emerald-300' : 'bg-slate-800 text-slate-400'"
                            >
                                {{ hasRealRfid ? 'chip gekoppeld' : 'nog niet gescand' }}
                            </span>
                        </div>
                        <div class="flex gap-2">
                            <input
                                v-model="form.rfid_uid"
                                type="text"
                                autocomplete="off"
                                placeholder="Klik hier en leg de kaart op de lezer…"
                                :class="fieldClass + ' flex-1 font-mono'"
                            >
                            <ScanRfidButton
                                :model-value="form.rfid_uid"
                                label="Scan"
                                title="RFID-kaart scannen"
                                description="Scan de chip van deze kaart."
                                confirm-label="Koppelen"
                                @confirmed="form.rfid_uid = $event"
                            />
                        </div>
                        <p class="mt-1.5 text-xs text-slate-500">
                            Een USB-lezer typt het nummer zelf in dit veld.
                            <template v-if="form.card_type === 'member'">Leeg laten mag: de kaart krijgt dan een tijdelijk nummer en je scant de chip na het printen.</template>
                        </p>

                        <!-- Meer opties -->
                        <details class="mt-5 rounded-2xl border border-slate-800 px-4 py-3" :open="!!form.id">
                            <summary class="cursor-pointer text-sm font-semibold text-slate-300">Meer opties</summary>
                            <div class="mt-3 grid gap-3 md:grid-cols-2">
                                <label class="space-y-1.5 text-xs text-slate-400">
                                    <span>Label</span>
                                    <input v-model="form.label" :class="fieldClass" :placeholder="labelPlaceholder">
                                </label>
                                <label class="space-y-1.5 text-xs text-slate-400">
                                    <span>Interne referentie</span>
                                    <input v-model="form.internal_reference" :class="fieldClass" placeholder="Batch of kaartnummer">
                                </label>
                                <label class="space-y-1.5 text-xs text-slate-400">
                                    <span>Status</span>
                                    <select v-model="form.status" :class="fieldClass">
                                        <option v-for="option in statusOptions" :key="option.value" :value="option.value">{{ option.label }}</option>
                                    </select>
                                </label>
                                <label class="space-y-1.5 text-xs text-slate-400">
                                    <span>Uitgegeven op</span>
                                    <input v-model="form.issued_at" type="date" :class="fieldClass">
                                </label>
                                <label class="space-y-1.5 text-xs text-slate-400">
                                    <span>Teruggebracht op</span>
                                    <input v-model="form.returned_at" type="date" :class="fieldClass">
                                </label>
                                <div class="space-y-1.5 text-xs text-slate-400">
                                    <span>Gedrukt op</span>
                                    <div class="rounded-2xl border border-slate-800 bg-slate-950 px-4 py-3 text-sm text-slate-300">{{ form.printed_at || 'Nog niet gedrukt' }}</div>
                                </div>
                            </div>
                            <label class="mt-3 block space-y-1.5 text-xs text-slate-400">
                                <span>Notities</span>
                                <textarea v-model="form.notes" rows="2" :class="fieldClass" placeholder="Extra info over deze kaart"></textarea>
                            </label>
                        </details>
                    </div>

                    <!-- Rechts: voorbeeld + ontwerp -->
                    <div v-if="form.card_type !== 'voucher'" class="rounded-2xl border border-slate-800 bg-slate-950 p-4">
                        <div class="mb-3 text-xs text-slate-400">Voorbeeld</div>
                        <div ref="previewBox" class="w-full">
                            <BadgeTemplateThumbnail
                                v-if="selectedTemplate"
                                :template="selectedTemplate"
                                :width="previewWidth"
                                :data="previewData"
                            />
                            <div v-else class="flex aspect-[1011/638] items-center justify-center rounded-[20px] border border-dashed border-slate-700 text-sm text-slate-500">
                                Nog geen ontwerp voor dit type
                            </div>
                        </div>

                        <div class="mb-2 mt-4 text-xs text-slate-400">Ontwerp</div>
                        <div class="flex flex-wrap gap-2">
                            <button
                                v-for="template in typeTemplates"
                                :key="template.id"
                                type="button"
                                class="inline-flex items-center gap-1.5 rounded-xl border px-3 py-1.5 text-xs font-semibold transition"
                                :class="String(form.badge_template_id) === String(template.id)
                                    ? 'border-sky-500 bg-sky-500/15 text-sky-100'
                                    : 'border-slate-700 bg-slate-900 text-slate-300 hover:border-slate-500'"
                                @click="form.badge_template_id = template.id"
                            >
                                <StarIcon v-if="template.is_default" class="h-3.5 w-3.5" />
                                {{ template.name }}
                            </button>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Footer -->
            <div class="flex flex-wrap items-center justify-between gap-3 border-t border-slate-800 px-6 py-4">
                <div class="flex items-center gap-3">
                    <button
                        v-if="form.id"
                        type="button"
                        class="rounded-2xl border border-rose-700/70 bg-rose-950/40 px-4 py-3 text-sm font-semibold text-rose-200 disabled:opacity-50"
                        :disabled="store.deleting"
                        @click="$emit('delete')"
                    >
                        Verwijderen
                    </button>
                    <span class="text-xs text-slate-500">{{ footerInfo }}</span>
                </div>
                <div class="flex gap-3">
                    <button type="button" class="rounded-2xl border border-slate-700 bg-slate-800 px-4 py-3 text-sm font-semibold text-slate-200" @click="$emit('close')">Annuleren</button>
                    <button type="submit" class="rounded-2xl border border-slate-700 bg-slate-800 px-4 py-3 text-sm font-semibold text-slate-200 disabled:opacity-50" :disabled="store.saving || !canSave">
                        {{ store.saving ? 'Opslaan…' : 'Opslaan' }}
                    </button>
                    <button
                        v-if="form.card_type !== 'voucher'"
                        type="button"
                        class="inline-flex items-center gap-2 rounded-2xl bg-blue-600 px-4 py-3 text-sm font-semibold text-white disabled:opacity-50"
                        :disabled="store.saving || store.printing || !canSave"
                        @click="submit(true)"
                    >
                        <ArrowDownTrayIcon class="h-4 w-4" />
                        Opslaan &amp; PNG downloaden
                    </button>
                </div>
            </div>
        </form>
    </div>
</template>

<script setup>
import { computed, nextTick, onBeforeUnmount, reactive, ref, watch } from 'vue'
import {
    ArrowDownTrayIcon,
    ExclamationTriangleIcon,
    GiftIcon,
    IdentificationIcon,
    MagnifyingGlassIcon,
    StarIcon,
    UserGroupIcon,
    UserIcon,
    XMarkIcon,
} from '@heroicons/vue/24/outline'
import ScanRfidButton from '@/shared/components/scanners/ScanRfidButton.vue'
import BadgeTemplateThumbnail from '../../badges/components/BadgeTemplateThumbnail.vue'
import { useCardsStore } from '../stores/useCardsStore'

const props = defineProps({
    open: { type: Boolean, default: false },
    card: { type: Object, default: null }, // null = nieuwe kaart
    statusOptions: { type: Array, default: () => [] },
})

const emit = defineEmits(['close', 'saved', 'delete'])

const store = useCardsStore()

const fieldClass = 'w-full rounded-2xl border border-slate-700 bg-slate-950 px-4 py-3 text-sm text-white outline-none transition focus:border-blue-500'

const types = [
    { value: 'member', label: 'Lid', icon: IdentificationIcon },
    { value: 'staff', label: 'Personeel', icon: UserIcon },
    { value: 'voucher', label: 'Cadeaubon', icon: GiftIcon },
]

const form = reactive(emptyForm())
const memberQuery = ref('')
const onlyWithoutCard = ref(true)
const previewBox = ref(null)
const previewWidth = ref(320)
let resizeObserver = null

function todayIso() {
    const now = new Date()
    return `${now.getFullYear()}-${String(now.getMonth() + 1).padStart(2, '0')}-${String(now.getDate()).padStart(2, '0')}`
}

function emptyForm() {
    return {
        id: null,
        card_type: 'member',
        voucher_template_id: '',
        badge_template_id: '',
        holder_id: '',
        label: '',
        internal_reference: '',
        rfid_uid: '',
        status: 'in_circulation',
        notes: '',
        printed_at: '',
        issued_at: todayIso(),
        returned_at: '',
    }
}

const typeTemplates = computed(() => store.badgeTemplates.filter(t => t.template_type === form.card_type))
const selectedTemplate = computed(() => typeTemplates.value.find(t => String(t.id) === String(form.badge_template_id)) ?? null)
const selectedMember = computed(() => form.card_type === 'member' && form.holder_id
    ? store.memberOptions.find(m => String(m.id) === String(form.holder_id)) ?? null
    : null)
const selectedStaff = computed(() => form.card_type === 'staff' && form.holder_id
    ? store.staffOptions.find(s => String(s.id) === String(form.holder_id)) ?? null
    : null)
const sortedStaff = computed(() => [...store.staffOptions].sort((a, b) => String(a.name).localeCompare(String(b.name), 'nl', { sensitivity: 'base' })))

const hasRealRfid = computed(() => {
    const uid = String(form.rfid_uid || '').trim()
    return uid !== '' && !uid.startsWith('TMP-')
})

const matchingMembers = computed(() => {
    const q = memberQuery.value.trim().toLowerCase()
    return store.memberOptions.filter((member) => {
        if (onlyWithoutCard.value && (member.card || !['active', 'expiring'].includes(member.status))) return false
        if (!q) return true
        return `${member.name} ${member.email ?? ''} ${member.id} ${member.family_name ?? ''}`.toLowerCase().includes(q)
    })
})
const totalMatches = computed(() => matchingMembers.value.length)
const filteredMembers = computed(() => matchingMembers.value.slice(0, 60))

const previewData = computed(() => {
    if (form.card_type === 'member' && selectedMember.value) {
        const m = selectedMember.value
        return {
            full_name: m.name,
            first_name: m.first_name,
            last_name: m.last_name,
            membership_type: m.type_label,
            valid_until: m.valid_until ?? '',
            badge_number: form.id ? `M-${String(form.id).padStart(5, '0')}` : 'M-·····',
            rfid_uid: hasRealRfid.value ? form.rfid_uid : '',
        }
    }
    if (form.card_type === 'staff' && selectedStaff.value) {
        return {
            full_name: selectedStaff.value.name,
            first_name: String(selectedStaff.value.name).split(' ')[0],
            last_name: String(selectedStaff.value.name).split(' ').slice(1).join(' '),
            rfid_uid: hasRealRfid.value ? form.rfid_uid : '',
        }
    }
    return null
})

const canSave = computed(() => {
    if (form.card_type === 'voucher') return !!form.voucher_template_id && hasRealRfid.value
    if (form.card_type === 'staff') return !!form.holder_id && !!form.badge_template_id
    return !!form.holder_id && !!form.badge_template_id
})

const footerInfo = computed(() => {
    if (form.card_type === 'member') {
        if (!selectedMember.value) return 'Kies een lid.'
        return hasRealRfid.value ? 'Chip wordt meteen aan het lid gekoppeld.' : 'Chip kan na het printen gescand worden.'
    }
    if (form.card_type === 'voucher' && !hasRealRfid.value) return 'Een cadeaubonkaart heeft een chip nodig.'
    return ''
})

const labelPlaceholder = computed(() => {
    if (form.card_type === 'staff') return 'Automatisch: STAFF #id'
    if (form.card_type === 'member') return 'Automatisch: LID #id - naam'
    return 'Automatisch: CARD #id'
})

const firstError = computed(() => {
    const errors = Object.values(store.formErrors || {}).flat()
    return errors[0] || store.formErrorMessage || 'Controleer de ingevulde gegevens.'
})

watch(() => props.open, async (open) => {
    if (!open) return
    store.clearFormErrors()
    memberQuery.value = ''
    onlyWithoutCard.value = true
    Object.assign(form, emptyForm(), props.card ? { ...props.card } : {})
    if (!form.badge_template_id) selectDefaultTemplate()
    await nextTick()
    observePreview()
})

async function setType(type) {
    if (form.card_type === type) return
    form.card_type = type
    form.holder_id = ''
    form.voucher_template_id = ''
    form.badge_template_id = ''
    if (!form.id) form.status = type === 'voucher' ? 'stock' : 'in_circulation'
    if (type !== 'voucher') selectDefaultTemplate()
    await nextTick()
    observePreview()
}

function selectDefaultTemplate() {
    const def = typeTemplates.value.find(t => t.is_default) ?? typeTemplates.value[0]
    form.badge_template_id = def?.id ?? ''
}

function selectMember(member) {
    form.holder_id = member.id
    if (!form.rfid_uid && member.rfid_uid) form.rfid_uid = member.rfid_uid
}

function clearMember() {
    form.holder_id = ''
    if (!form.id) form.rfid_uid = ''
}

watch(() => form.holder_id, (holderId) => {
    if (form.card_type !== 'staff' || !holderId || form.rfid_uid) return
    const staff = store.staffOptions.find(s => String(s.id) === String(holderId))
    if (staff?.rfid_uid) form.rfid_uid = staff.rfid_uid
})

function memberMeta(member) {
    return [
        member.email,
        member.type_label,
        member.family_name,
        member.valid_until ? `t/m ${member.valid_until}` : null,
    ].filter(Boolean).join(' · ')
}

function statusLabel(status) {
    return { active: 'Actief', expiring: 'Vervalt binnenkort', expired: 'Vervallen', none: 'Geen abonnement' }[status] ?? status
}

function statusClass(status) {
    return {
        active: 'bg-emerald-500/15 text-emerald-300',
        expiring: 'bg-amber-500/15 text-amber-300',
        expired: 'bg-rose-500/15 text-rose-300',
        none: 'bg-slate-800 text-slate-400',
    }[status] ?? 'bg-slate-800 text-slate-400'
}

function initials(name) {
    return String(name || '?').split(' ').filter(Boolean).map(p => p[0]).slice(0, 2).join('').toUpperCase()
}

function observePreview() {
    resizeObserver?.disconnect()
    if (!previewBox.value || typeof ResizeObserver === 'undefined') return
    resizeObserver = new ResizeObserver(([entry]) => {
        previewWidth.value = Math.max(200, Math.floor(entry.contentRect.width))
    })
    resizeObserver.observe(previewBox.value)
}

onBeforeUnmount(() => resizeObserver?.disconnect())

async function submit(andPrint) {
    if (!canSave.value) return
    try {
        const payload = {
            ...form,
            voucher_template_id: form.voucher_template_id || null,
            badge_template_id: form.badge_template_id || null,
            holder_id: form.holder_id || null,
            rfid_uid: String(form.rfid_uid || '').trim(),
        }
        await store.saveCard(payload)
        emit('saved', { print: andPrint })
    } catch {
        // validatiefouten blijven zichtbaar in de modal
    }
}
</script>
