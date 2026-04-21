<template>
    <div class="px-4 pt-3 pb-6">
        <h1 class="text-lg font-semibold text-white mb-4">Reservaties</h1>

        <!-- Statusfilter -->
        <div class="flex gap-2 overflow-x-auto pb-3 scrollbar-none mb-2">
            <button
                v-for="f in filters"
                :key="f.value"
                class="shrink-0 px-3 py-1.5 rounded-xl text-xs font-medium transition-colors border"
                :class="activeFilter === f.value
                    ? 'bg-blue-500/20 border-blue-500/30 text-blue-300'
                    : 'bg-slate-800/50 border-slate-700/40 text-slate-400'"
                @click="setFilter(f.value)"
            >
                {{ f.label }}
            </button>
        </div>

        <!-- Laden -->
        <div v-if="loading" class="flex justify-center pt-16">
            <div class="flex gap-2">
                <span class="loader-dot" /><span class="loader-dot" /><span class="loader-dot" />
            </div>
        </div>

        <!-- Fout -->
        <div v-else-if="error" class="glass-card rounded-3xl p-6 text-center space-y-2">
            <p class="text-sm text-rose-400">{{ error }}</p>
            <button class="text-xs text-slate-400 underline underline-offset-2" @click="load">Opnieuw proberen</button>
        </div>

        <!-- Leeg -->
        <div v-else-if="filtered.length === 0" class="glass-card rounded-3xl p-8 text-center space-y-3">
            <div class="w-14 h-14 rounded-2xl bg-blue-500/15 border border-blue-500/25 flex items-center justify-center mx-auto">
                <CalendarDaysIcon class="w-7 h-7 text-blue-400" />
            </div>
            <div>
                <p class="text-base font-semibold text-white mb-1">Geen reservaties gevonden</p>
                <p class="text-sm text-slate-400 leading-relaxed">
                    {{ activeFilter === 'all'
                        ? `Reservaties gemaakt met ${auth.account?.email} verschijnen hier automatisch.`
                        : 'Geen reservaties met deze status.' }}
                </p>
            </div>
        </div>

        <!-- Lijst -->
        <div v-else class="space-y-3">
            <button
                v-for="r in filtered"
                :key="r.id"
                class="w-full text-left glass-card rounded-2xl p-4 flex items-start gap-4 active:scale-[0.98] transition-transform"
                @click="openDetail(r)"
            >
                <!-- Datum blok -->
                <div
                    class="shrink-0 w-12 flex flex-col items-center justify-center rounded-xl py-2 px-1 relative"
                    :class="datumBlokKleur(r)"
                >
                    <!-- Pulserende dot voor nieuw/pending -->
                    <span
                        v-if="r.status === 'new' || r.status === 'pending'"
                        class="absolute -top-1 -right-1 w-2.5 h-2.5 rounded-full border-2 border-[#030814]"
                        :class="r.status === 'pending' ? 'bg-amber-400 animate-pulse' : 'bg-blue-400 animate-pulse'"
                    />
                    <span class="text-[10px] font-semibold uppercase tracking-wider" :class="datumTekstKleur(r)">
                        {{ monthShort(r.event_date) }}
                    </span>
                    <span class="text-xl font-bold leading-tight" :class="dagNumKleur(r)">
                        {{ dayNum(r.event_date) }}
                    </span>
                </div>

                <!-- Info -->
                <div class="flex-1 min-w-0">
                    <div class="flex items-center gap-1.5 mb-0.5">
                        <span v-if="r.event_emoji" class="text-sm">{{ r.event_emoji }}</span>
                        <span class="text-sm font-semibold text-white truncate">{{ r.event_type ?? 'Reservatie' }}</span>
                    </div>
                    <p class="text-xs text-slate-400 truncate">
                        {{ r.tenant_name }}
                        <span v-if="r.event_time"> · {{ r.event_time }}</span>
                        <span v-if="r.stay_option"> · {{ r.stay_option }}</span>
                    </p>
                    <p class="text-xs text-slate-500 mt-0.5">{{ r.total_count }} personen</p>
                </div>

                <!-- Status badge + chevron -->
                <div class="shrink-0 flex flex-col items-end gap-2">
                    <ReservationStatusBadge :status="r.status" />
                    <ChevronRightIcon class="w-4 h-4 text-slate-600" />
                </div>
            </button>
        </div>

        <!-- ── Detail bottom sheet ── -->
        <Transition name="sheet">
            <div v-if="selected" class="fixed inset-0 z-50 flex flex-col justify-end">
                <div class="absolute inset-0 bg-black/60 backdrop-blur-sm" @click="closeSheet" />

                <div class="relative glass-card rounded-t-3xl px-5 pt-5 pb-safe-or-6 max-h-[90vh] overflow-y-auto">

                    <!-- Greep + sluiten -->
                    <div class="flex items-center justify-between mb-4">
                        <div class="w-8 h-1 rounded-full bg-slate-600 absolute left-1/2 -translate-x-1/2 top-3" />
                        <span class="text-sm font-semibold text-white">
                            {{ sheetMode === 'detail' ? 'Reservatiedetail' : sheetMode === 'edit' ? 'Aanpassen' : 'Annuleren' }}
                        </span>
                        <button class="w-7 h-7 rounded-full bg-slate-700/60 flex items-center justify-center" @click="closeSheet">
                            <XMarkIcon class="w-4 h-4 text-slate-300" />
                        </button>
                    </div>

                    <!-- ── DETAIL MODE ── -->
                    <template v-if="sheetMode === 'detail'">
                        <!-- Header -->
                        <div class="flex items-center gap-3 mb-5">
                            <div class="w-10 h-10 rounded-xl flex items-center justify-center text-xl shrink-0"
                                style="background: rgba(59,130,246,0.12); border: 1px solid rgba(59,130,246,0.20);">
                                {{ selected.event_emoji ?? '📅' }}
                            </div>
                            <div class="flex-1 min-w-0">
                                <p class="text-base font-semibold text-white truncate">{{ selected.event_type ?? 'Reservatie' }}</p>
                                <p class="text-xs text-slate-400 truncate">{{ selected.tenant_name }}</p>
                            </div>
                            <ReservationStatusBadge :status="selected.status" />
                        </div>

                        <div v-if="detailLoading" class="flex justify-center py-8">
                            <div class="flex gap-2"><span class="loader-dot" /><span class="loader-dot" /><span class="loader-dot" /></div>
                        </div>

                        <template v-else-if="detail">
                            <div class="space-y-2.5 mb-4">
                                <DetailRow v-if="detail.event_date"      label="Datum"    :value="formatDate(detail.event_date)" />
                                <DetailRow v-if="detail.event_time"      label="Startuur" :value="detail.event_time" />
                                <DetailRow v-if="detail.stay_option"     label="Formule"  :value="detail.stay_option" />
                                <DetailRow v-if="detail.catering_option" label="Catering" :value="detail.catering_option" />
                            </div>
                            <div class="h-px bg-white/5 my-4" />
                            <div class="space-y-2.5 mb-4">
                                <p class="text-xs font-semibold uppercase tracking-wider text-slate-500">Personen</p>
                                <DetailRow v-if="detail.participants_children    > 0" label="Kinderen"    :value="String(detail.participants_children)" />
                                <DetailRow v-if="detail.participants_adults      > 0" label="Volwassenen" :value="String(detail.participants_adults)" />
                                <DetailRow v-if="detail.participants_supervisors > 0" label="Begeleiders" :value="String(detail.participants_supervisors)" />
                                <DetailRow label="Totaal" :value="`${detail.total_count} personen`" />
                            </div>

                            <div v-if="detail.outside_opening_hours"
                                class="flex items-start gap-2 rounded-2xl px-4 py-3 mb-4"
                                style="background: rgba(245,158,11,0.10); border: 1px solid rgba(245,158,11,0.22);">
                                <ExclamationTriangleIcon class="w-4 h-4 text-amber-400 shrink-0 mt-0.5" />
                                <p class="text-xs text-amber-300 leading-relaxed">Buiten openingsuren — we nemen contact op ter bevestiging.</p>
                            </div>

                            <div v-if="detail.comment" class="mb-4">
                                <p class="text-xs font-semibold uppercase tracking-wider text-slate-500 mb-1.5">Opmerking</p>
                                <p class="text-sm text-slate-300 leading-relaxed">{{ detail.comment }}</p>
                            </div>

                            <div v-if="detail.tenant_phone || detail.tenant_email" class="mb-5">
                                <div class="h-px bg-white/5 mb-4" />
                                <p class="text-xs font-semibold uppercase tracking-wider text-slate-500 mb-2">Contact</p>
                                <a v-if="detail.tenant_phone" :href="`tel:${detail.tenant_phone}`" class="flex items-center gap-2 text-sm text-slate-300 mb-1.5">
                                    <PhoneIcon class="w-4 h-4 text-slate-500 shrink-0" />{{ detail.tenant_phone }}
                                </a>
                                <a v-if="detail.tenant_email" :href="`mailto:${detail.tenant_email}`" class="flex items-center gap-2 text-sm text-slate-300">
                                    <EnvelopeIcon class="w-4 h-4 text-slate-500 shrink-0" />{{ detail.tenant_email }}
                                </a>
                            </div>

                            <!-- Acties (alleen als can_edit) -->
                            <template v-if="detail.can_edit">
                                <div class="h-px bg-white/5 mb-4" />
                                <div class="flex gap-3">
                                    <button
                                        class="flex-1 py-2.5 rounded-xl text-sm font-medium border border-blue-500/30 text-blue-300 bg-blue-500/10 transition-colors active:bg-blue-500/20"
                                        @click="sheetMode = 'edit'; initEditForm()"
                                    >
                                        Aanpassen
                                    </button>
                                    <button
                                        class="flex-1 py-2.5 rounded-xl text-sm font-medium border border-rose-500/30 text-rose-300 bg-rose-500/10 transition-colors active:bg-rose-500/20"
                                        @click="sheetMode = 'cancel'"
                                    >
                                        Annuleren
                                    </button>
                                </div>
                            </template>
                        </template>
                    </template>

                    <!-- ── EDIT MODE ── -->
                    <template v-else-if="sheetMode === 'edit'">
                        <p class="text-xs text-slate-400 mb-5">Pas de aantallen en/of je opmerking aan.</p>

                        <div class="space-y-4 mb-6">
                            <div v-if="detail.participants_children !== undefined">
                                <label class="block text-xs uppercase tracking-wider text-slate-500 mb-2">Kinderen</label>
                                <CounterRow v-model="editForm.participants_children" />
                            </div>
                            <div v-if="detail.participants_adults !== undefined">
                                <label class="block text-xs uppercase tracking-wider text-slate-500 mb-2">Volwassenen</label>
                                <CounterRow v-model="editForm.participants_adults" />
                            </div>
                            <div v-if="detail.participants_supervisors !== undefined">
                                <label class="block text-xs uppercase tracking-wider text-slate-500 mb-2">Begeleiders</label>
                                <CounterRow v-model="editForm.participants_supervisors" />
                            </div>
                            <div>
                                <label class="block text-xs uppercase tracking-wider text-slate-500 mb-2">Opmerking</label>
                                <textarea
                                    v-model="editForm.comment"
                                    class="member-input resize-none"
                                    rows="3"
                                    placeholder="Eventuele opmerkingen..."
                                />
                            </div>
                        </div>

                        <p v-if="editError" class="text-xs text-rose-400 mb-3">{{ editError }}</p>

                        <div class="flex gap-3">
                            <button class="flex-1 py-2.5 rounded-xl text-sm font-medium border border-slate-600/50 text-slate-400" @click="sheetMode = 'detail'">
                                Terug
                            </button>
                            <button
                                class="flex-1 py-2.5 rounded-xl text-sm font-medium bg-blue-600 text-white disabled:opacity-50"
                                :disabled="saving"
                                @click="submitEdit"
                            >
                                {{ saving ? 'Opslaan...' : 'Opslaan' }}
                            </button>
                        </div>
                    </template>

                    <!-- ── CANCEL MODE ── -->
                    <template v-else-if="sheetMode === 'cancel'">
                        <div class="flex items-center justify-center mb-5">
                            <div class="w-14 h-14 rounded-full flex items-center justify-center"
                                style="background: rgba(239,68,68,0.10); border: 1px solid rgba(239,68,68,0.22);">
                                <ExclamationTriangleIcon class="w-7 h-7 text-rose-400" />
                            </div>
                        </div>
                        <p class="text-base font-semibold text-white text-center mb-2">Reservatie annuleren?</p>
                        <p class="text-sm text-slate-400 text-center leading-relaxed mb-6">
                            Je reservatie voor <strong class="text-white">{{ selected.event_type }}</strong>
                            op <strong class="text-white">{{ formatDate(selected.event_date) }}</strong> wordt geannuleerd.
                            Je ontvangt een bevestigingsmail.
                        </p>

                        <p v-if="cancelError" class="text-xs text-rose-400 text-center mb-3">{{ cancelError }}</p>

                        <div class="flex gap-3">
                            <button class="flex-1 py-2.5 rounded-xl text-sm font-medium border border-slate-600/50 text-slate-400" @click="sheetMode = 'detail'">
                                Terug
                            </button>
                            <button
                                class="flex-1 py-2.5 rounded-xl text-sm font-medium bg-rose-600 text-white disabled:opacity-50"
                                :disabled="saving"
                                @click="submitCancel"
                            >
                                {{ saving ? 'Bezig...' : 'Ja, annuleer' }}
                            </button>
                        </div>
                    </template>

                </div>
            </div>
        </Transition>
    </div>
</template>

<script setup>
import { ref, computed, onMounted, defineComponent, h } from 'vue'
import {
    CalendarDaysIcon,
    ChevronRightIcon,
    XMarkIcon,
    ExclamationTriangleIcon,
    PhoneIcon,
    EnvelopeIcon,
} from '@heroicons/vue/24/outline'
import { useAuthStore } from '../../stores/useAuthStore'
import { reservationApi } from '../../services/api'

// ── Lokale sub-componenten ────────────────────────────────────────────────────

const DetailRow = defineComponent({
    props: { label: String, value: String },
    setup(props) {
        return () => h('div', { class: 'flex items-baseline justify-between gap-3' }, [
            h('span', { class: 'text-xs text-slate-500 shrink-0' }, props.label),
            h('span', { class: 'text-sm text-slate-200 text-right' }, props.value),
        ])
    },
})

const CounterRow = defineComponent({
    props: { modelValue: Number },
    emits: ['update:modelValue'],
    setup(props, { emit }) {
        return () => h('div', { class: 'flex items-center gap-4' }, [
            h('button', {
                type: 'button',
                class: 'w-8 h-8 rounded-full bg-slate-700/60 border border-slate-600/50 text-white flex items-center justify-center text-lg leading-none',
                onClick: () => emit('update:modelValue', Math.max(0, props.modelValue - 1)),
            }, '−'),
            h('span', { class: 'w-6 text-center text-white font-semibold' }, String(props.modelValue)),
            h('button', {
                type: 'button',
                class: 'w-8 h-8 rounded-full bg-slate-700/60 border border-slate-600/50 text-white flex items-center justify-center text-lg leading-none',
                onClick: () => emit('update:modelValue', props.modelValue + 1),
            }, '+'),
        ])
    },
})

const STATUS_CFG = {
    new:         { label: 'Nieuw',          cls: 'bg-blue-500/15 border-blue-500/25 text-blue-300',          dot: 'bg-blue-400' },
    pending:     { label: 'In behandeling', cls: 'bg-amber-500/15 border-amber-500/25 text-amber-300',        dot: 'bg-amber-400' },
    confirmed:   { label: 'Bevestigd',      cls: 'bg-emerald-500/15 border-emerald-500/25 text-emerald-300',  dot: 'bg-emerald-400' },
    checked_in:  { label: 'Ingecheckt',     cls: 'bg-blue-500/15 border-blue-500/25 text-blue-300',           dot: 'bg-blue-400' },
    checked_out: { label: 'Afgerond',       cls: 'bg-slate-700/60 border-slate-600/50 text-slate-400',        dot: 'bg-slate-500' },
    paid:        { label: 'Betaald',        cls: 'bg-emerald-500/15 border-emerald-500/25 text-emerald-300',  dot: 'bg-emerald-400' },
    cancelled:   { label: 'Geannuleerd',    cls: 'bg-rose-500/15 border-rose-500/25 text-rose-300',           dot: 'bg-rose-400' },
}

const ReservationStatusBadge = defineComponent({
    props: { status: String },
    setup(props) {
        return () => {
            const cfg = STATUS_CFG[props.status] ?? { label: props.status, cls: 'bg-slate-700/60 border-slate-600/50 text-slate-400', dot: 'bg-slate-500' }
            return h('span', { class: `inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-medium border ${cfg.cls}` }, [
                h('span', { class: `w-1.5 h-1.5 rounded-full ${cfg.dot}` }),
                cfg.label,
            ])
        }
    },
})

// ── State ─────────────────────────────────────────────────────────────────────

const auth          = useAuthStore()
const loading       = ref(true)
const error         = ref('')
const reservations  = ref([])

const selected      = ref(null)
const detail        = ref(null)
const detailLoading = ref(false)
const sheetMode     = ref('detail') // 'detail' | 'edit' | 'cancel'

const editForm  = ref({ participants_children: 0, participants_adults: 0, participants_supervisors: 0, comment: '' })
const editError  = ref('')
const cancelError = ref('')
const saving    = ref(false)

const activeFilter = ref('all')

const filters = [
    { value: 'all',       label: 'Alle' },
    { value: 'new',       label: 'Nieuw' },
    { value: 'pending',   label: 'In behandeling' },
    { value: 'confirmed', label: 'Bevestigd' },
    { value: 'done',      label: 'Afgerond' },
    { value: 'cancelled', label: 'Geannuleerd' },
]

const filtered = computed(() => {
    if (activeFilter.value === 'all') return reservations.value
    if (activeFilter.value === 'done') {
        return reservations.value.filter(r => ['checked_in','checked_out','paid'].includes(r.status))
    }
    return reservations.value.filter(r => r.status === activeFilter.value)
})

// ── Laden ─────────────────────────────────────────────────────────────────────

async function load() {
    loading.value = true
    error.value   = ''
    try {
        const res = await reservationApi.list({ include_cancelled: true })
        reservations.value = res.data
    } catch (e) {
        error.value = e.message ?? 'Kon reservaties niet laden.'
    } finally {
        loading.value = false
    }
}

function setFilter(val) {
    activeFilter.value = val
}

// ── Sheet ─────────────────────────────────────────────────────────────────────

async function openDetail(r) {
    selected.value      = r
    detail.value        = null
    detailLoading.value = true
    sheetMode.value     = 'detail'
    editError.value     = ''
    cancelError.value   = ''
    try {
        const res = await reservationApi.get(r.id)
        detail.value = res.data
    } catch {
        detail.value = r
    } finally {
        detailLoading.value = false
    }
}

function closeSheet() {
    selected.value  = null
    detail.value    = null
    sheetMode.value = 'detail'
}

function initEditForm() {
    editForm.value = {
        participants_children:    detail.value?.participants_children    ?? 0,
        participants_adults:      detail.value?.participants_adults      ?? 0,
        participants_supervisors: detail.value?.participants_supervisors ?? 0,
        comment:                  detail.value?.comment                  ?? '',
    }
    editError.value = ''
}

// ── Opslaan ───────────────────────────────────────────────────────────────────

async function submitEdit() {
    const total = editForm.value.participants_children
                + editForm.value.participants_adults
                + editForm.value.participants_supervisors
    if (total < 1) {
        editError.value = 'Er moet minstens 1 deelnemer zijn.'
        return
    }
    saving.value    = true
    editError.value = ''
    try {
        const res = await reservationApi.update(selected.value.id, editForm.value)
        detail.value = res.data
        // Update in lijst
        const idx = reservations.value.findIndex(r => r.id === selected.value.id)
        if (idx !== -1) reservations.value[idx] = { ...reservations.value[idx], total_count: res.data.total_count }
        sheetMode.value = 'detail'
    } catch (e) {
        editError.value = e.message ?? 'Opslaan mislukt.'
    } finally {
        saving.value = false
    }
}

async function submitCancel() {
    saving.value      = true
    cancelError.value = ''
    try {
        await reservationApi.cancel(selected.value.id)
        // Update status in lijst
        const idx = reservations.value.findIndex(r => r.id === selected.value.id)
        if (idx !== -1) reservations.value[idx] = { ...reservations.value[idx], status: 'cancelled', can_edit: false }
        closeSheet()
    } catch (e) {
        cancelError.value = e.message ?? 'Annuleren mislukt.'
    } finally {
        saving.value = false
    }
}

// ── Datum helpers ─────────────────────────────────────────────────────────────

const MONTHS = ['jan','feb','mrt','apr','mei','jun','jul','aug','sep','okt','nov','dec']

function parseDate(s) {
    if (!s) return null
    const [y, m, d] = s.split('-').map(Number)
    return new Date(y, m - 1, d)
}

function isPast(s) {
    const d = parseDate(s)
    if (!d) return false
    const t = new Date(); t.setHours(0,0,0,0)
    return d < t
}

function monthShort(s) { const d = parseDate(s); return d ? MONTHS[d.getMonth()] : '—' }
function dayNum(s)     { const d = parseDate(s); return d ? d.getDate() : '—' }

function formatDate(s) {
    const d = parseDate(s)
    if (!d) return s
    return d.toLocaleDateString('nl-BE', { weekday: 'long', day: 'numeric', month: 'long', year: 'numeric' })
}

// ── Datum blok kleuren ────────────────────────────────────────────────────────

function datumBlokKleur(r) {
    if (r.status === 'cancelled') return 'bg-slate-800/60'
    if (r.status === 'pending')   return 'bg-amber-500/10 border border-amber-500/20'
    if (r.status === 'new')       return 'bg-blue-500/15 border border-blue-500/20'
    if (isPast(r.event_date))     return 'bg-slate-700/50'
    return 'bg-blue-500/15 border border-blue-500/20'
}

function datumTekstKleur(r) {
    if (r.status === 'cancelled') return 'text-slate-600'
    if (r.status === 'pending')   return 'text-amber-400'
    if (r.status === 'new')       return 'text-blue-400'
    if (isPast(r.event_date))     return 'text-slate-500'
    return 'text-blue-400'
}

function dagNumKleur(r) {
    if (r.status === 'cancelled') return 'text-slate-600'
    if (isPast(r.event_date))     return 'text-slate-400'
    return 'text-white'
}

onMounted(load)
</script>

<style scoped>
.pb-safe-or-6 { padding-bottom: max(1.5rem, env(safe-area-inset-bottom)); }

.sheet-enter-active, .sheet-leave-active { transition: opacity 0.25s ease; }
.sheet-enter-active .relative, .sheet-leave-active .relative {
    transition: transform 0.3s cubic-bezier(0.32, 0.72, 0, 1);
}
.sheet-enter-from, .sheet-leave-to { opacity: 0; }
.sheet-enter-from .relative, .sheet-leave-to .relative { transform: translateY(100%); }
</style>
