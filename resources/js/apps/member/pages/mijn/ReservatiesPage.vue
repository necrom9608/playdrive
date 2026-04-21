<template>
    <div class="px-4 pt-3 pb-6">
        <h1 class="text-lg font-semibold text-white mb-5">Reservaties</h1>

        <!-- Laden -->
        <div v-if="loading" class="flex justify-center pt-16">
            <div class="flex gap-2">
                <span class="loader-dot" /><span class="loader-dot" /><span class="loader-dot" />
            </div>
        </div>

        <!-- Fout -->
        <div v-else-if="error" class="glass-card rounded-3xl p-6 text-center space-y-2">
            <p class="text-sm text-rose-400">{{ error }}</p>
            <button class="text-xs text-slate-400 underline underline-offset-2" @click="load">
                Opnieuw proberen
            </button>
        </div>

        <!-- Leeg -->
        <div v-else-if="reservations.length === 0" class="glass-card rounded-3xl p-8 text-center space-y-3">
            <div class="w-14 h-14 rounded-2xl bg-blue-500/15 border border-blue-500/25 flex items-center justify-center mx-auto">
                <CalendarDaysIcon class="w-7 h-7 text-blue-400" />
            </div>
            <div>
                <p class="text-base font-semibold text-white mb-1">Nog geen reservaties</p>
                <p class="text-sm text-slate-400 leading-relaxed">
                    Reservaties die je maakt met {{ auth.account?.email }} verschijnen hier automatisch.
                </p>
            </div>
        </div>

        <!-- Lijst -->
        <div v-else class="space-y-3">
            <button
                v-for="r in reservations"
                :key="r.id"
                class="w-full text-left glass-card rounded-2xl p-4 flex items-start gap-4 active:scale-[0.98] transition-transform"
                @click="openDetail(r)"
            >
                <!-- Datum blok -->
                <div class="shrink-0 w-12 flex flex-col items-center justify-center rounded-xl py-2 px-1"
                    :class="isPast(r.event_date) ? 'bg-slate-700/50' : 'bg-blue-500/15 border border-blue-500/20'">
                    <span class="text-[10px] font-semibold uppercase tracking-wider"
                        :class="isPast(r.event_date) ? 'text-slate-500' : 'text-blue-400'">
                        {{ monthShort(r.event_date) }}
                    </span>
                    <span class="text-xl font-bold leading-tight"
                        :class="isPast(r.event_date) ? 'text-slate-400' : 'text-white'">
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

                <!-- Status + chevron -->
                <div class="shrink-0 flex flex-col items-end gap-2">
                    <ReservationStatusBadge :status="r.status" />
                    <ChevronRightIcon class="w-4 h-4 text-slate-600" />
                </div>
            </button>
        </div>

        <!-- Detail bottom sheet -->
        <Transition name="sheet">
            <div v-if="selected" class="fixed inset-0 z-50 flex flex-col justify-end">
                <!-- Backdrop -->
                <div class="absolute inset-0 bg-black/60 backdrop-blur-sm" @click="selected = null" />

                <!-- Sheet -->
                <div class="relative glass-card rounded-t-3xl px-5 pt-5 pb-safe-or-6 space-y-5 max-h-[85vh] overflow-y-auto">

                    <!-- Greep + sluiten -->
                    <div class="flex items-center justify-between mb-1">
                        <div class="w-8 h-1 rounded-full bg-slate-600 mx-auto absolute left-1/2 -translate-x-1/2 top-3" />
                        <span class="text-sm font-semibold text-white">Reservatiedetail</span>
                        <button @click="selected = null" class="w-7 h-7 rounded-full bg-slate-700/60 flex items-center justify-center">
                            <XMarkIcon class="w-4 h-4 text-slate-300" />
                        </button>
                    </div>

                    <!-- Status -->
                    <div class="flex items-center gap-3">
                        <div class="w-10 h-10 rounded-xl flex items-center justify-center text-xl"
                            style="background: rgba(59,130,246,0.12); border: 1px solid rgba(59,130,246,0.20);">
                            {{ selected.event_emoji ?? '📅' }}
                        </div>
                        <div>
                            <p class="text-base font-semibold text-white">{{ selected.event_type ?? 'Reservatie' }}</p>
                            <p class="text-xs text-slate-400">{{ selected.tenant_name }}</p>
                        </div>
                        <div class="ml-auto">
                            <ReservationStatusBadge :status="selected.status" />
                        </div>
                    </div>

                    <div class="h-px bg-white/5" />

                    <!-- Detail laden -->
                    <div v-if="detailLoading" class="flex justify-center py-6">
                        <div class="flex gap-2">
                            <span class="loader-dot" /><span class="loader-dot" /><span class="loader-dot" />
                        </div>
                    </div>

                    <template v-else-if="detail">
                        <!-- Datum & tijd -->
                        <div class="space-y-2">
                            <DetailRow v-if="detail.event_date" label="Datum" :value="formatDate(detail.event_date)" />
                            <DetailRow v-if="detail.event_time" label="Startuur" :value="detail.event_time" />
                            <DetailRow v-if="detail.stay_option" label="Formule" :value="detail.stay_option" />
                            <DetailRow v-if="detail.catering_option" label="Catering" :value="detail.catering_option" />
                        </div>

                        <div class="h-px bg-white/5" />

                        <!-- Personen -->
                        <div class="space-y-2">
                            <p class="text-xs font-semibold uppercase tracking-wider text-slate-500">Personen</p>
                            <DetailRow v-if="detail.participants_children > 0" label="Kinderen" :value="String(detail.participants_children)" />
                            <DetailRow v-if="detail.participants_adults > 0" label="Volwassenen" :value="String(detail.participants_adults)" />
                            <DetailRow v-if="detail.participants_supervisors > 0" label="Begeleiders" :value="String(detail.participants_supervisors)" />
                            <DetailRow label="Totaal" :value="`${detail.total_count} personen`" />
                        </div>

                        <!-- Buiten uren melding -->
                        <div v-if="detail.outside_opening_hours"
                            class="flex items-start gap-2 rounded-2xl px-4 py-3"
                            style="background: rgba(245,158,11,0.10); border: 1px solid rgba(245,158,11,0.22);">
                            <ExclamationTriangleIcon class="w-4 h-4 text-amber-400 shrink-0 mt-0.5" />
                            <p class="text-xs text-amber-300 leading-relaxed">
                                Buiten openingsuren aangevraagd — we nemen contact op ter bevestiging.
                            </p>
                        </div>

                        <!-- Opmerking -->
                        <div v-if="detail.comment">
                            <p class="text-xs font-semibold uppercase tracking-wider text-slate-500 mb-1.5">Opmerking</p>
                            <p class="text-sm text-slate-300 leading-relaxed">{{ detail.comment }}</p>
                        </div>

                        <div class="h-px bg-white/5" />

                        <!-- Contact venue -->
                        <div v-if="detail.tenant_phone || detail.tenant_email" class="space-y-2">
                            <p class="text-xs font-semibold uppercase tracking-wider text-slate-500">Contact</p>
                            <a v-if="detail.tenant_phone" :href="`tel:${detail.tenant_phone}`"
                                class="flex items-center gap-2 text-sm text-slate-300">
                                <PhoneIcon class="w-4 h-4 text-slate-500 shrink-0" />
                                {{ detail.tenant_phone }}
                            </a>
                            <a v-if="detail.tenant_email" :href="`mailto:${detail.tenant_email}`"
                                class="flex items-center gap-2 text-sm text-slate-300">
                                <EnvelopeIcon class="w-4 h-4 text-slate-500 shrink-0" />
                                {{ detail.tenant_email }}
                            </a>
                        </div>
                    </template>
                </div>
            </div>
        </Transition>
    </div>
</template>

<script setup>
import { ref, onMounted } from 'vue'
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

const auth = useAuthStore()

const loading      = ref(true)
const error        = ref('')
const reservations = ref([])
const selected     = ref(null)
const detail       = ref(null)
const detailLoading = ref(false)

async function load() {
    loading.value = true
    error.value   = ''
    try {
        const res = await reservationApi.list()
        reservations.value = res.data
    } catch (e) {
        error.value = e.message ?? 'Kon reservaties niet laden.'
    } finally {
        loading.value = false
    }
}

async function openDetail(r) {
    selected.value      = r
    detail.value        = null
    detailLoading.value = true
    try {
        const res = await reservationApi.get(r.id)
        detail.value = res.data
    } catch {
        detail.value = r  // fallback op de lijstdata
    } finally {
        detailLoading.value = false
    }
}

// ── Datum helpers ──────────────────────────────────────────────────────────

const MONTHS = ['jan','feb','mrt','apr','mei','jun','jul','aug','sep','okt','nov','dec']

function parseDate(dateStr) {
    if (!dateStr) return null
    const [y, m, d] = dateStr.split('-').map(Number)
    return new Date(y, m - 1, d)
}

function isPast(dateStr) {
    const d = parseDate(dateStr)
    if (!d) return false
    const today = new Date(); today.setHours(0,0,0,0)
    return d < today
}

function monthShort(dateStr) {
    const d = parseDate(dateStr)
    return d ? MONTHS[d.getMonth()] : '—'
}

function dayNum(dateStr) {
    const d = parseDate(dateStr)
    return d ? d.getDate() : '—'
}

function formatDate(dateStr) {
    const d = parseDate(dateStr)
    if (!d) return dateStr
    return d.toLocaleDateString('nl-BE', { weekday: 'long', day: 'numeric', month: 'long', year: 'numeric' })
}

onMounted(load)
</script>

<!-- Detail row helper — lokaal gedefinieerd om import overhead te vermijden -->
<script>
const DetailRow = {
    props: { label: String, value: String },
    template: `
        <div class="flex items-baseline justify-between gap-3">
            <span class="text-xs text-slate-500 shrink-0">{{ label }}</span>
            <span class="text-sm text-slate-200 text-right">{{ value }}</span>
        </div>
    `,
}
</script>

<!-- ReservationStatusBadge — lokaal omdat registratie-statussen anders zijn dan membership-statussen -->
<script>
const ReservationStatusBadge = {
    props: { status: String },
    computed: {
        cfg() {
            const map = {
                new:          { label: 'Nieuw',         cls: 'bg-slate-700/60 border-slate-600/50 text-slate-300',     dot: 'bg-slate-400' },
                confirmed:    { label: 'Bevestigd',     cls: 'bg-emerald-500/15 border-emerald-500/25 text-emerald-300', dot: 'bg-emerald-400' },
                checked_in:   { label: 'Ingecheckt',    cls: 'bg-blue-500/15 border-blue-500/25 text-blue-300',        dot: 'bg-blue-400' },
                checked_out:  { label: 'Afgerond',      cls: 'bg-slate-700/60 border-slate-600/50 text-slate-400',     dot: 'bg-slate-500' },
                paid:         { label: 'Betaald',       cls: 'bg-emerald-500/15 border-emerald-500/25 text-emerald-300', dot: 'bg-emerald-400' },
            }
            return map[this.status] ?? { label: this.status, cls: 'bg-slate-700/60 border-slate-600/50 text-slate-400', dot: 'bg-slate-500' }
        },
    },
    template: `
        <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-medium border" :class="cfg.cls">
            <span class="w-1.5 h-1.5 rounded-full" :class="cfg.dot" />
            {{ cfg.label }}
        </span>
    `,
}
</script>

<style scoped>
.pb-safe-or-6 {
    padding-bottom: max(1.5rem, env(safe-area-inset-bottom));
}

.sheet-enter-active,
.sheet-leave-active {
    transition: opacity 0.25s ease;
}
.sheet-enter-active .relative,
.sheet-leave-active .relative {
    transition: transform 0.3s cubic-bezier(0.32, 0.72, 0, 1);
}
.sheet-enter-from,
.sheet-leave-to {
    opacity: 0;
}
.sheet-enter-from .relative,
.sheet-leave-to .relative {
    transform: translateY(100%);
}
</style>
