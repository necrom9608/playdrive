<template>
    <div class="space-y-6">
        <div>
            <h1 class="text-3xl font-bold text-white">Maandprestaties</h1>
            <p class="mt-2 text-slate-400">
                Gewerkte uren per medewerker volgens de check-ins, in dezelfde opbouw als Liantis om over te nemen.
            </p>
        </div>

        <!-- Toolbar -->
        <div class="flex flex-wrap items-center gap-3 rounded-2xl border border-slate-800 bg-slate-900 p-4 shadow-sm">
            <div class="flex items-center gap-2">
                <button type="button" :class="iconBtn" title="Vorige maand" @click="shiftMonth(-1)"><ChevronLeftIcon class="h-4 w-4" /></button>
                <div class="min-w-[140px] text-center text-base font-semibold text-white">{{ data.month_label || '…' }}</div>
                <button type="button" :class="iconBtn" title="Volgende maand" @click="shiftMonth(1)"><ChevronRightIcon class="h-4 w-4" /></button>
            </div>

            <div class="flex items-center gap-2 sm:ml-2">
                <button type="button" :class="iconBtn" title="Vorige medewerker" :disabled="!prevStaff" @click="selectStaff(prevStaff?.id)"><ChevronDoubleLeftIcon class="h-4 w-4" /></button>
                <select :value="staffId ? String(staffId) : ''" class="min-w-[220px] rounded-2xl border border-slate-700 bg-slate-950 px-4 py-2.5 text-sm text-white outline-none transition focus:border-blue-500" @change="selectStaff(Number($event.target.value))">
                    <option v-for="item in staffList" :key="item.id" :value="String(item.id)">{{ item.name }}</option>
                </select>
                <button type="button" :class="iconBtn" title="Volgende medewerker" :disabled="!nextStaff" @click="selectStaff(nextStaff?.id)"><ChevronDoubleRightIcon class="h-4 w-4" /></button>
            </div>

            <div class="flex-1" />

            <template v-if="summary">
                <template v-if="summary.status === 'submitted' || summary.status === 'changed'">
                    <span v-if="summary.status === 'changed'" class="inline-flex items-center gap-1.5 rounded-full bg-amber-500/15 px-3 py-1 text-xs font-semibold text-amber-300">
                        <ExclamationTriangleIcon class="h-4 w-4" /> Gewijzigd na invoer
                    </span>
                    <span v-else class="inline-flex items-center gap-1.5 rounded-full bg-emerald-500/15 px-3 py-1 text-xs font-semibold text-emerald-300">
                        <CheckCircleIcon class="h-4 w-4" /> Ingevoerd op {{ summary.submitted_at }}<template v-if="selected?.submitted_by_name"> door {{ selected.submitted_by_name }}</template>
                    </span>
                    <button v-if="summary.status === 'changed'" type="button" class="rounded-xl bg-blue-600 px-4 py-2.5 text-sm font-semibold text-white transition hover:bg-blue-500 disabled:opacity-50" :disabled="saving || summary.issues > 0" @click="toggleSubmitted(true)">Opnieuw afvinken</button>
                    <button type="button" class="rounded-xl border border-slate-700 px-4 py-2.5 text-sm font-medium text-slate-300 transition hover:bg-slate-800" :disabled="saving" @click="toggleSubmitted(false)">Ongedaan maken</button>
                </template>
                <button
                    v-else
                    type="button"
                    class="inline-flex items-center gap-2 rounded-xl bg-blue-600 px-4 py-2.5 text-sm font-semibold text-white transition hover:bg-blue-500 disabled:cursor-not-allowed disabled:opacity-50"
                    :disabled="saving || summary.issues > 0 || summary.status === 'empty'"
                    :title="summary.issues > 0 ? 'Los eerst de dagen op die nog te controleren zijn.' : ''"
                    @click="toggleSubmitted(true)"
                >
                    <CheckIcon class="h-4 w-4" /> Ingevoerd in Liantis
                </button>
            </template>
        </div>

        <div v-if="error" class="rounded-2xl border border-red-800 bg-red-950/40 px-4 py-3 text-sm text-red-300">{{ error }}</div>

        <div class="grid items-start gap-5 xl:grid-cols-[240px_minmax(0,1fr)] 2xl:grid-cols-[250px_minmax(0,1fr)_300px]">
            <!-- Medewerkers -->
            <div class="rounded-2xl border border-slate-800 bg-slate-900 shadow-sm">
                <div class="border-b border-slate-800 px-4 py-3">
                    <h2 class="text-base font-semibold text-white">Medewerkers</h2>
                    <p class="text-xs text-slate-400">{{ submittedCount }} van {{ countable }} ingevoerd</p>
                </div>
                <ul class="space-y-1 p-2">
                    <li
                        v-for="item in staffList"
                        :key="item.id"
                        class="flex cursor-pointer items-center justify-between gap-2 rounded-xl px-3 py-2.5 transition hover:bg-slate-950/60"
                        :class="item.id === staffId ? 'bg-slate-950 ring-1 ring-slate-700' : ''"
                        @click="selectStaff(item.id)"
                    >
                        <div class="min-w-0">
                            <div class="truncate font-medium text-white">{{ item.name }}</div>
                            <div class="text-xs text-slate-400">{{ fmt(item.worked_minutes) }} gewerkt</div>
                        </div>
                        <span class="shrink-0 rounded-full px-2 py-0.5 text-[11px] font-semibold" :class="statusMeta(item).cls">{{ statusMeta(item).label }}</span>
                    </li>
                </ul>
            </div>

            <!-- Kalender -->
            <div class="min-w-0 space-y-4">
                <div class="grid grid-cols-2 gap-3 lg:grid-cols-4">
                    <div class="rounded-2xl border border-slate-800 bg-slate-900 px-4 py-3">
                        <div class="text-xs text-slate-400">Gewerkt</div>
                        <div class="mt-0.5 text-xl font-semibold tabular-nums text-white">{{ fmt(summary?.worked_minutes || 0) }}</div>
                    </div>
                    <div class="rounded-2xl border border-slate-800 bg-slate-900 px-4 py-3">
                        <div class="text-xs text-slate-400">Werkdagen</div>
                        <div class="mt-0.5 text-xl font-semibold text-white">{{ summary?.worked_days || 0 }}</div>
                    </div>
                    <div class="rounded-2xl border border-slate-800 bg-slate-900 px-4 py-3">
                        <div class="text-xs text-slate-400">Afwezigheden</div>
                        <div class="mt-0.5 truncate text-xl font-semibold text-white">{{ absenceSummary || '—' }}</div>
                    </div>
                    <div class="rounded-2xl border border-slate-800 bg-slate-900 px-4 py-3">
                        <div class="text-xs text-slate-400">Te controleren</div>
                        <div class="mt-0.5 text-xl font-semibold" :class="summary?.issues ? 'text-amber-300' : 'text-white'">{{ summary?.issues || 0 }} {{ summary?.issues === 1 ? 'dag' : 'dagen' }}</div>
                    </div>
                </div>

                <div class="overflow-hidden rounded-2xl border border-slate-800 bg-slate-900 shadow-sm">
                    <div v-if="loading && !days.length" class="p-6 text-sm text-slate-400">Laden...</div>
                    <div v-else-if="!staffId" class="p-6 text-sm text-slate-400">Geen medewerkers gevonden.</div>
                    <template v-else>
                        <div class="grid grid-cols-[repeat(7,minmax(0,1fr))_76px]">
                            <div v-for="label in weekdayLabels" :key="label" class="border-b border-slate-800 py-2.5 text-center text-xs font-semibold uppercase tracking-wide text-slate-400">{{ label }}</div>
                            <div class="border-b border-slate-800 py-2.5 text-center text-xs font-semibold uppercase tracking-wide text-blue-300">Week</div>

                            <template v-for="(week, wi) in weeks" :key="wi">
                                <template v-for="(day, di) in week.days" :key="day ? day.date : `e-${wi}-${di}`">
                                    <div v-if="!day" class="min-h-[100px] border-b border-r border-slate-800 bg-slate-950/50" />
                                    <div
                                        v-else
                                        class="relative min-h-[100px] min-w-0 cursor-pointer overflow-hidden border-b border-r border-slate-800 p-2 transition hover:bg-slate-950/50"
                                        :class="[
                                            day.issue ? 'bg-amber-500/5 shadow-[inset_0_0_0_1px_rgba(245,158,11,.5)]' : '',
                                            day.date === selectedDate ? '!shadow-[inset_0_0_0_2px_#3b82f6]' : '',
                                        ]"
                                        @click="selectedDate = day.date"
                                    >
                                        <span class="absolute right-2 top-1.5 text-xs text-slate-500">{{ day.day }}</span>
                                        <span v-if="day.holiday" class="absolute left-2 top-1.5 max-w-[70%] truncate text-[10.5px] font-medium text-cyan-300">{{ day.holiday }}</span>

                                        <div class="mt-5 space-y-1.5">
                                            <div v-if="day.worked_minutes > 0" class="inline-block whitespace-nowrap rounded-lg border border-slate-700 bg-slate-950 px-2.5 py-1 text-[15px] font-semibold tabular-nums text-white">{{ fmt(day.worked_minutes) }}</div>
                                            <div v-else-if="day.issue" class="inline-block whitespace-nowrap rounded-lg border border-amber-500/50 bg-slate-950 px-2.5 py-1 text-[15px] font-semibold tabular-nums text-amber-300">--:--</div>

                                            <div v-for="s in day.sessions.slice(0, 2)" :key="`${s.id}-${s.in}`" class="truncate text-[11px] tabular-nums" :class="s.open ? (day.issue ? 'text-amber-300' : 'text-blue-300') : 'text-slate-400'">
                                                {{ s.in }}–{{ s.open ? (day.issue ? 'open' : 'bezig') : s.out }}
                                            </div>
                                            <div v-if="day.sessions.length > 2" class="text-[11px] text-slate-500">+{{ day.sessions.length - 2 }} sessies</div>

                                            <div v-if="day.absence" class="inline-flex items-center gap-1.5 whitespace-nowrap rounded-lg px-2 py-0.5 text-xs font-bold" :style="codeStyle(day.absence.code)">
                                                {{ day.absence.code }}<span v-if="day.absence.minutes" class="font-medium tabular-nums">{{ fmt(day.absence.minutes) }}</span>
                                            </div>
                                            <div v-else-if="day.leave" class="inline-block whitespace-nowrap rounded-lg border border-dashed border-indigo-400/60 px-2 py-0.5 text-[11px] text-indigo-300">Verlof goedgekeurd</div>
                                        </div>
                                    </div>
                                </template>
                                <div class="flex items-center justify-center border-b border-slate-800 bg-blue-500/5 text-sm font-semibold tabular-nums text-blue-300">{{ fmt(week.total) }}</div>
                            </template>
                        </div>

                        <div class="flex flex-wrap items-center gap-4 px-4 py-3">
                            <div class="flex flex-wrap items-center gap-3 text-xs text-slate-400">
                                <span class="inline-flex items-center gap-1.5"><span class="h-2.5 w-2.5 rounded-sm bg-slate-600" />Gewerkt (exact)</span>
                                <span class="inline-flex items-center gap-1.5"><span class="h-2.5 w-2.5 rounded-sm bg-amber-400" />Niet uitgecheckt</span>
                                <span class="inline-flex items-center gap-1.5"><span class="h-2.5 w-2.5 rounded-sm bg-cyan-300" />Feestdag</span>
                            </div>
                            <div class="ml-auto text-base font-bold tabular-nums text-white"><span class="mr-2 text-xs font-medium text-slate-400">Totaal maand</span>{{ fmt(summary?.worked_minutes || 0) }}</div>
                        </div>
                    </template>
                </div>
            </div>

            <!-- Dagdetail -->
            <div class="rounded-2xl border border-slate-800 bg-slate-900 shadow-sm xl:col-span-2 2xl:col-span-1">
                <div class="border-b border-slate-800 px-4 py-3">
                    <h2 class="text-base font-semibold text-white">{{ selectedDay ? dayTitle(selectedDay) : 'Dagdetail' }}</h2>
                    <p class="text-xs text-slate-400">{{ selectedDay ? `Check-ins van ${summary?.name}` : 'Klik op een dag in de kalender.' }}</p>
                </div>

                <div v-if="selectedDay" class="space-y-4 p-4">
                    <div v-if="selectedDay.issue" class="flex gap-2 rounded-xl bg-amber-500/15 px-3 py-2.5 text-xs text-amber-300">
                        <ExclamationTriangleIcon class="h-5 w-5 shrink-0" />
                        <span>Niet uitgecheckt. Vul een uitchecktijd in of neem het rooster over.</span>
                    </div>

                    <div class="space-y-2">
                        <div v-if="!selectedDay.sessions.length" class="text-sm text-slate-400">Geen check-ins op deze dag.</div>
                        <div
                            v-for="s in selectedDay.sessions"
                            :key="`${s.id}-${s.in}`"
                            class="flex items-center justify-between gap-2 rounded-xl border bg-slate-950/60 px-3 py-2 text-sm tabular-nums"
                            :class="s.open && selectedDay.issue ? 'border-amber-500/50' : 'border-slate-800'"
                        >
                            <div>
                                <span class="text-white">{{ s.in }} – </span>
                                <span :class="s.open ? 'text-amber-300' : 'text-white'">{{ s.open ? 'open' : s.out }}</span>
                                <span v-if="!s.open" class="ml-2 text-xs text-slate-400">{{ fmt(s.minutes) }}</span>
                            </div>
                            <button type="button" class="rounded-lg border border-slate-700 px-2.5 py-1 text-xs font-medium text-slate-300 transition hover:bg-slate-800" @click="openEdit(s)">Bewerken</button>
                        </div>
                    </div>

                    <div class="space-y-1.5 text-sm">
                        <div class="flex justify-between"><span class="text-slate-400">Rooster</span><span class="font-medium tabular-nums text-white">{{ rosterLabel(selectedDay) }}</span></div>
                        <div class="flex justify-between"><span class="text-slate-400">Gewerkt</span><span class="font-semibold tabular-nums" :class="selectedDay.issue ? 'text-amber-300' : 'text-white'">{{ selectedDay.issue ? '?' : fmt(selectedDay.worked_minutes) }}</span></div>
                    </div>

                    <div class="grid gap-2">
                        <button v-if="closeFromRoster" type="button" class="rounded-xl border border-slate-700 px-3 py-2 text-sm font-medium text-slate-200 transition hover:bg-slate-800 disabled:opacity-50" :disabled="saving" @click="applyRosterEnd">
                            Uitchecken op roostereinde ({{ closeFromRoster.end }})
                        </button>
                        <button type="button" class="inline-flex items-center justify-center gap-1.5 rounded-xl border border-slate-700 px-3 py-2 text-sm font-medium text-slate-300 transition hover:bg-slate-800" @click="openCreate">
                            <PlusIcon class="h-4 w-4" /> Check-in toevoegen
                        </button>
                    </div>

                    <div>
                        <div class="mb-2 flex items-center justify-between">
                            <span class="text-xs text-slate-400">Afwezigheid</span>
                            <button v-if="selectedDay.absence" type="button" class="text-xs text-red-300 hover:underline" :disabled="saving" @click="saveAbsence(null)">Verwijderen</button>
                        </div>
                        <div class="flex flex-wrap gap-1.5">
                            <button
                                v-for="c in absenceCodes"
                                :key="c.code"
                                type="button"
                                class="rounded-lg border px-2.5 py-1 text-xs font-bold transition disabled:opacity-50"
                                :class="selectedDay.absence?.code === c.code ? 'border-transparent' : 'border-slate-700 bg-slate-950 text-slate-300 hover:bg-slate-800'"
                                :style="selectedDay.absence?.code === c.code ? codeStyle(c.code) : null"
                                :title="c.label"
                                :disabled="saving"
                                @click="saveAbsence(c.code)"
                            >{{ c.code }}</button>
                        </div>
                        <div class="mt-2 flex items-center gap-2">
                            <label class="text-xs text-slate-400">Uren</label>
                            <input v-model="absenceHours" type="text" inputmode="numeric" placeholder="uu:mm" class="w-24 rounded-xl border border-slate-700 bg-slate-950 px-3 py-1.5 text-sm tabular-nums text-white outline-none focus:border-blue-500">
                            <span class="text-[11px] text-slate-500">optioneel, vóór je de code kiest</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <StaffAttendanceEditModal
            :open="modalOpen"
            :item="editingItem"
            :staff="staffList"
            :saving="saving"
            :error="modalError"
            @close="closeModal"
            @submit="handleModalSubmit"
        />
    </div>
</template>

<script setup>
import { computed, onMounted, ref, watch } from 'vue'
import {
    CheckCircleIcon,
    CheckIcon,
    ChevronDoubleLeftIcon,
    ChevronDoubleRightIcon,
    ChevronLeftIcon,
    ChevronRightIcon,
    ExclamationTriangleIcon,
    PlusIcon,
} from '@heroicons/vue/24/outline'
import StaffAttendanceEditModal from '../../staff-attendance/components/StaffAttendanceEditModal.vue'
import { updateStaffAttendance } from '../../staff-attendance/services/staffAttendanceApi'
import { createStaffCheckin, fetchStaffPayroll, setStaffAbsence, submitStaffPayroll } from '../services/staffPayrollApi'

const iconBtn = 'inline-flex h-10 w-10 items-center justify-center rounded-xl border border-slate-700 text-slate-300 transition hover:bg-slate-800 disabled:opacity-40'
const weekdayLabels = ['Ma', 'Di', 'Wo', 'Do', 'Vr', 'Za', 'Zo']
const weekdayNames = ['maandag', 'dinsdag', 'woensdag', 'donderdag', 'vrijdag', 'zaterdag', 'zondag']
const monthNames = ['januari', 'februari', 'maart', 'april', 'mei', 'juni', 'juli', 'augustus', 'september', 'oktober', 'november', 'december']

const data = ref({ staff: [], absence_codes: [], selected: null })
const month = ref('')
const staffId = ref(null)
const selectedDate = ref(null)
const loading = ref(false)
const saving = ref(false)
const error = ref('')
const absenceHours = ref('')

const modalOpen = ref(false)
const modalError = ref('')
const editingItem = ref(null)

const staffList = computed(() => data.value.staff || [])
const absenceCodes = computed(() => data.value.absence_codes || [])
const selected = computed(() => data.value.selected)
const summary = computed(() => selected.value?.summary || null)
const days = computed(() => selected.value?.days || [])
const selectedDay = computed(() => days.value.find(d => d.date === selectedDate.value) || null)

const staffIndex = computed(() => staffList.value.findIndex(s => s.id === staffId.value))
const prevStaff = computed(() => staffIndex.value > 0 ? staffList.value[staffIndex.value - 1] : null)
const nextStaff = computed(() => staffIndex.value >= 0 && staffIndex.value < staffList.value.length - 1 ? staffList.value[staffIndex.value + 1] : null)

const countable = computed(() => staffList.value.filter(s => s.status !== 'empty').length)
const submittedCount = computed(() => staffList.value.filter(s => s.status === 'submitted').length)

const weeks = computed(() => {
    if (!days.value.length) return []
    const cells = [...Array(days.value[0].weekday - 1).fill(null), ...days.value]
    while (cells.length % 7) cells.push(null)
    const result = []
    for (let i = 0; i < cells.length; i += 7) {
        const slice = cells.slice(i, i + 7)
        result.push({ days: slice, total: slice.reduce((sum, d) => sum + (d?.worked_minutes || 0), 0) })
    }
    return result
})

const absenceSummary = computed(() => {
    const counts = {}
    days.value.forEach(d => { if (d.absence) counts[d.absence.code] = (counts[d.absence.code] || 0) + 1 })
    return Object.entries(counts).map(([code, n]) => `${n} × ${code}`).join(', ')
})

const closeFromRoster = computed(() => {
    const day = selectedDay.value
    if (!day) return null
    const open = day.sessions.find(s => s.open)
    if (!open || !day.issue) return null
    const shift = [...day.roster].reverse().find(r => r.ends_at > open.in)
    return shift ? { session: open, end: shift.ends_at } : null
})

function fmt(minutes) {
    const m = Math.max(0, Math.round(minutes || 0))
    return `${String(Math.floor(m / 60)).padStart(2, '0')}:${String(m % 60).padStart(2, '0')}`
}

function parseHm(value) {
    const match = String(value || '').trim().match(/^(\d{1,2})(?::|\.|h)?(\d{2})?$/)
    if (!match) return null
    return Number(match[1]) * 60 + Number(match[2] || 0)
}

function codeStyle(code) {
    const color = absenceCodes.value.find(c => c.code === code)?.color || '#a78bfa'
    return { backgroundColor: `${color}2e`, color }
}

function statusMeta(item) {
    switch (item.status) {
        case 'submitted': return { label: 'Ingevoerd', cls: 'bg-emerald-500/15 text-emerald-300' }
        case 'changed': return { label: 'Gewijzigd', cls: 'bg-amber-500/15 text-amber-300' }
        case 'issues': return { label: `${item.issues} te controleren`, cls: 'bg-amber-500/15 text-amber-300' }
        case 'ready': return { label: 'Klaar', cls: 'bg-blue-500/15 text-blue-300' }
        default: return { label: 'Geen uren', cls: 'bg-slate-800 text-slate-400' }
    }
}

function dayTitle(day) {
    const d = new Date(`${day.date}T00:00:00`)
    const name = weekdayNames[day.weekday - 1]
    return `${name.charAt(0).toUpperCase()}${name.slice(1)} ${d.getDate()} ${monthNames[d.getMonth()]}`
}

function rosterLabel(day) {
    if (!day.roster.length) return '—'
    const total = day.roster.reduce((sum, r) => sum + r.minutes, 0)
    return `${day.roster.map(r => `${r.starts_at} – ${r.ends_at}`).join(', ')} · ${fmt(total)}`
}

async function load() {
    loading.value = true
    error.value = ''
    try {
        const response = await fetchStaffPayroll({ month: month.value, staffId: staffId.value })
        data.value = response
        month.value = response.month
        staffId.value = response.staff_id
        const list = response.selected?.days || []
        if (!list.some(d => d.date === selectedDate.value)) {
            selectedDate.value = list.find(d => d.issue)?.date || null
        }
    } catch (err) {
        console.error(err)
        error.value = err?.data?.message || 'Kon maandprestaties niet laden.'
    } finally {
        loading.value = false
    }
}

function shiftMonth(delta) {
    const [y, m] = (month.value || '').split('-').map(Number)
    if (!y) return
    const d = new Date(y, m - 1 + delta, 1)
    month.value = `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}`
    selectedDate.value = null
    load()
}

function selectStaff(id) {
    if (!id || id === staffId.value) return
    staffId.value = id
    selectedDate.value = null
    load()
}

async function run(action, fallback) {
    saving.value = true
    error.value = ''
    try {
        await action()
        await load()
    } catch (err) {
        console.error(err)
        error.value = err?.data?.message || fallback
    } finally {
        saving.value = false
    }
}

function saveAbsence(code) {
    const minutes = code ? parseHm(absenceHours.value) : null
    run(() => setStaffAbsence({ user_id: staffId.value, date: selectedDate.value, code, minutes }), 'Kon afwezigheid niet opslaan.')
}

function toggleSubmitted(submitted) {
    run(() => submitStaffPayroll({ user_id: staffId.value, month: month.value, submitted }), 'Kon status niet opslaan.')
}

function applyRosterEnd() {
    const target = closeFromRoster.value
    if (!target) return
    const s = target.session
    run(() => updateStaffAttendance(s.id, {
        user_id: s.user_id,
        checked_in_at: s.checked_in_at,
        checked_out_at: `${s.checked_in_at.slice(0, 10)}T${target.end}`,
        rfid_uid: s.rfid_uid || null,
    }), 'Kon uitchecktijd niet opslaan.')
}

function openEdit(session) {
    editingItem.value = { ...session }
    modalError.value = ''
    modalOpen.value = true
}

function openCreate() {
    const roster = selectedDay.value?.roster || []
    const from = roster[0]?.starts_at || '00:00'
    const to = roster[roster.length - 1]?.ends_at || '00:00'
    editingItem.value = { user_id: staffId.value, checked_in_at: `${selectedDate.value}T${from}`, checked_out_at: `${selectedDate.value}T${to}`, rfid_uid: '' }
    modalError.value = ''
    modalOpen.value = true
}

function closeModal() {
    modalOpen.value = false
    editingItem.value = null
    modalError.value = ''
}

async function handleModalSubmit(form) {
    saving.value = true
    modalError.value = ''
    try {
        const payload = {
            user_id: Number(form.user_id),
            checked_in_at: form.checked_in_at,
            checked_out_at: form.checked_out_at || null,
        }
        if (editingItem.value?.id) {
            await updateStaffAttendance(editingItem.value.id, { ...payload, rfid_uid: form.rfid_uid || null })
        } else {
            await createStaffCheckin(payload)
        }
        closeModal()
        await load()
    } catch (err) {
        console.error(err)
        modalError.value = err?.status === 422 && err?.data?.errors
            ? Object.values(err.data.errors)?.[0]?.[0] || 'Validatiefout.'
            : err?.data?.message || 'Kon check-in niet opslaan.'
    } finally {
        saving.value = false
    }
}

watch(selectedDate, () => {
    const m = selectedDay.value?.absence?.minutes
    absenceHours.value = m ? fmt(m) : ''
})

onMounted(load)
</script>
