<template>
    <div class="space-y-6">
        <div>
            <h1 class="text-3xl font-bold text-white">Uurroosters</h1>
            <p class="mt-2 text-slate-400">
                Beheer rollen, stel per seizoen het algemene rooster met shift-slots in, en plan daarna per week wie welke shift doet.
            </p>
        </div>

        <div v-if="error" class="rounded-2xl border border-rose-500/30 bg-rose-500/10 px-5 py-4 text-sm text-rose-200">{{ error }}</div>
        <div v-if="success" class="rounded-2xl border border-emerald-500/30 bg-emerald-500/10 px-5 py-4 text-sm text-emerald-200">{{ success }}</div>

        <!-- Tabs -->
        <div class="flex gap-1 rounded-2xl border border-slate-800 bg-slate-900 p-1 w-fit">
            <button
                v-for="tab in tabs"
                :key="tab.key"
                type="button"
                class="rounded-xl px-4 py-2 text-sm font-medium transition"
                :class="activeTab === tab.key ? 'bg-slate-700 text-white' : 'text-slate-400 hover:text-slate-200'"
                @click="activeTab = tab.key"
            >
                {{ tab.label }}
                <span
                    v-if="tab.key === 'leave' && leavePendingCount > 0"
                    class="ml-1.5 inline-flex min-w-[18px] items-center justify-center rounded-full bg-amber-500/90 px-1.5 text-[11px] font-bold text-slate-950"
                >{{ leavePendingCount }}</span>
            </button>
        </div>

        <!-- ============================================================ -->
        <!--  TAB: Rollen                                                  -->
        <!-- ============================================================ -->
        <div v-if="activeTab === 'roles'" class="space-y-4">
            <div class="flex justify-end">
                <button type="button" class="rounded-xl bg-blue-600 px-4 py-2.5 text-sm font-semibold text-white transition hover:bg-blue-500" @click="openNewRole">Rol toevoegen</button>
            </div>

            <div v-if="loading" class="py-8 text-center text-sm text-slate-400">Laden...</div>

            <div v-else-if="roles.length === 0" class="rounded-2xl border border-slate-800 bg-slate-900 p-8 text-center text-sm text-slate-400">
                Nog geen rollen. Voeg er een toe (bv. Bar, Onthaal, VR-zone).
            </div>

            <div v-else class="grid gap-2 sm:grid-cols-2 lg:grid-cols-3">
                <button
                    v-for="role in roles"
                    :key="role.id"
                    type="button"
                    class="flex items-center gap-3 rounded-2xl border border-slate-800 bg-slate-900 px-4 py-3 text-left transition hover:border-slate-600"
                    @click="openEditRole(role)"
                >
                    <span class="h-4 w-4 flex-shrink-0 rounded-full" :style="{ backgroundColor: role.color || '#64748b' }" />
                    <span class="font-medium text-white">{{ role.name }}</span>
                    <span v-if="!role.is_active" class="ml-auto rounded-full bg-slate-800 px-2 py-0.5 text-xs text-slate-400">inactief</span>
                </button>
            </div>
        </div>

        <!-- ============================================================ -->
        <!--  TAB: Algemeen rooster (slots)                                -->
        <!-- ============================================================ -->
        <div v-if="activeTab === 'template'" class="space-y-5">
            <div class="flex flex-wrap items-center gap-3">
                <label class="text-sm font-medium text-slate-300">Seizoen</label>
                <select v-model="selectedSeason" class="rounded-xl border border-slate-700 bg-slate-950 px-4 py-2.5 text-sm text-white outline-none focus:border-sky-500" @change="loadSlots">
                    <option v-for="sk in seasonOptions" :key="sk" :value="sk">{{ seasonLabel(sk) }}</option>
                </select>
                <span class="text-xs text-slate-500">{{ seasonRange(selectedSeason) }}</span>
            </div>

            <div v-if="slotsLoading" class="py-8 text-center text-sm text-slate-400">Laden...</div>

            <div v-else class="grid gap-4 lg:grid-cols-2 xl:grid-cols-3">
                <section v-for="wd in weekdays" :key="wd.value" class="rounded-3xl border border-slate-800 bg-slate-900/80 p-5">
                    <div class="mb-3 flex items-center justify-between">
                        <h2 class="text-base font-semibold text-white">{{ wd.label }}</h2>
                        <button type="button" class="rounded-lg border border-slate-700 px-2.5 py-1 text-xs font-semibold text-slate-300 transition hover:bg-slate-800" @click="openNewSlot(wd.value)">+ Slot</button>
                    </div>

                    <p v-if="slotsForDay(wd.value).length === 0" class="py-2 text-xs text-slate-500">Vrij — geen slots.</p>

                    <button
                        v-for="slot in slotsForDay(wd.value)"
                        :key="slot.id"
                        type="button"
                        class="mb-2 block w-full rounded-xl border border-slate-800 bg-slate-950/60 px-3 py-2 text-left transition hover:border-slate-600"
                        @click="openEditSlot(wd.value, slot)"
                    >
                        <div class="flex items-center gap-2">
                            <span class="h-2.5 w-2.5 rounded-full" :style="{ backgroundColor: roleColor(slot.role_id) }" />
                            <span class="text-sm font-semibold text-white">{{ slot.starts_at }}–{{ slot.ends_at }}</span>
                            <span v-if="slot.desired_count" class="rounded-full bg-slate-800 px-2 py-0.5 text-[11px] text-slate-300">{{ slot.desired_count }}×</span>
                            <span class="ml-auto text-xs text-slate-400">{{ roleName(slot.role_id) }}</span>
                        </div>
                        <div v-if="slot.comment || slot.default_user_ids.length" class="mt-1 text-[11px] text-slate-500">
                            <span v-if="slot.default_user_ids.length" class="text-slate-300">Vast: {{ defaultUserNames(slot.default_user_ids) }}</span>
                            <span v-if="slot.comment && slot.default_user_ids.length"> · </span>
                            <span v-if="slot.comment">{{ slot.comment }}</span>
                        </div>
                    </button>
                </section>
            </div>

            <!-- Overzicht: uren per medewerker (per week, volgens vaste invullers) -->
            <section v-if="!slotsLoading" class="rounded-3xl border border-slate-800 bg-slate-900/80 p-5">
                <div class="mb-3 flex items-center justify-between">
                    <h2 class="text-base font-semibold text-white">Uren per medewerker <span class="text-sm font-normal text-slate-500">· per week volgens dit rooster</span></h2>
                    <span class="text-xs text-slate-400">Totaal {{ hoursLabel(templateHoursTotal) }}</span>
                </div>
                <div v-if="templateHours.length" class="grid gap-2 sm:grid-cols-2 lg:grid-cols-3">
                    <div v-for="row in templateHours" :key="row.id" class="flex items-center justify-between rounded-xl border border-slate-800 bg-slate-950/60 px-3 py-2">
                        <span class="truncate text-sm text-slate-200">{{ row.name }}</span>
                        <span class="text-sm font-semibold text-white">{{ hoursLabel(row.minutes) }}</span>
                    </div>
                </div>
                <p v-else class="text-xs text-slate-500">Nog geen vaste invullers ingesteld in dit seizoen — voeg standaard-invullers toe aan de slots.</p>
            </section>
        </div>

        <!-- ============================================================ -->
        <!--  TAB: Weekplanning                                            -->
        <!-- ============================================================ -->
        <div v-if="activeTab === 'week'" class="space-y-4">
            <div class="flex flex-wrap items-center gap-3">
                <div class="flex items-center gap-1">
                    <button type="button" class="rounded-xl border border-slate-700 px-3 py-2.5 text-sm font-medium text-slate-300 transition hover:bg-slate-800" @click="shiftWeek(-1)">‹</button>
                    <button type="button" class="rounded-xl border border-slate-700 px-3 py-2.5 text-sm font-medium text-slate-300 transition hover:bg-slate-800" @click="goToday">Deze week</button>
                    <button type="button" class="rounded-xl border border-slate-700 px-3 py-2.5 text-sm font-medium text-slate-300 transition hover:bg-slate-800" @click="shiftWeek(1)">›</button>
                </div>
                <span class="text-sm font-medium text-white">{{ weekRangeLabel }}</span>

                <div class="ml-auto flex flex-wrap gap-2">
                    <button type="button" :disabled="weekBusy" class="rounded-xl bg-blue-600 px-4 py-2.5 text-sm font-semibold text-white transition hover:bg-blue-500 disabled:opacity-50" @click="doGenerate">Genereer uit rooster</button>
                    <button type="button" :disabled="weekBusy" class="rounded-xl border border-slate-700 px-4 py-2.5 text-sm font-medium text-slate-300 transition hover:bg-slate-800 disabled:opacity-50" @click="doReset">Reset week</button>
                </div>
            </div>

            <div v-if="weekLoading" class="py-8 text-center text-sm text-slate-400">Laden...</div>

            <div v-else class="grid gap-3 md:grid-cols-2 xl:grid-cols-4 2xl:grid-cols-7">
                <section v-for="day in weekDays" :key="day.date" class="rounded-2xl border border-slate-800 bg-slate-900/70 p-3">
                    <div class="mb-2 flex items-baseline justify-between">
                        <div>
                            <div class="text-sm font-semibold" :class="isToday(day.date) ? 'text-sky-300' : 'text-white'">{{ weekdayLabel(day.date) }}</div>
                            <div class="text-xs text-slate-500">{{ dayMonth(day.date) }}</div>
                        </div>
                        <span class="rounded-full bg-slate-800 px-2 py-0.5 text-[10px] text-slate-400">{{ seasonLabel(day.season_key) }}</span>
                    </div>

                    <div class="space-y-2">
                        <button
                            v-for="shift in shiftsForDay(day.date)"
                            :key="shift.id"
                            type="button"
                            class="block w-full rounded-xl border px-2.5 py-2 text-left transition hover:brightness-110"
                            :style="shiftStyle(shift)"
                            @click="openEditShift(day.date, shift)"
                        >
                            <div class="flex items-center gap-1.5">
                                <span class="text-xs font-semibold text-white">{{ shift.starts_at }}–{{ shift.ends_at }}</span>
                                <span class="ml-auto text-[11px] font-semibold" :class="fillTextClass(shift)">{{ fillLabel(shift) }}</span>
                            </div>
                            <div v-if="shift.role_id" class="text-[11px] text-slate-300">{{ roleName(shift.role_id) }}</div>
                            <div v-if="shift.assignments.length" class="mt-1 flex flex-wrap gap-1">
                                <span
                                    v-for="a in shift.assignments"
                                    :key="a.id"
                                    class="rounded px-1.5 py-0.5 text-[10px]"
                                    :class="userHasLeave(a.user_id, day.date)
                                        ? 'bg-rose-500/25 text-rose-200 ring-1 ring-rose-400/50 line-through'
                                        : 'bg-slate-950/60 text-slate-200'"
                                    :title="userHasLeave(a.user_id, day.date) ? 'Verlof aangevraagd of goedgekeurd in deze periode' : ''"
                                >{{ a.name }}</span>
                            </div>
                            <div v-if="shift.note" class="mt-1 truncate text-[10px] italic text-amber-300/80">{{ shift.note }}</div>
                        </button>

                        <button type="button" class="w-full rounded-xl border border-dashed border-slate-700 px-2 py-1.5 text-xs text-slate-500 transition hover:border-slate-500 hover:text-slate-300" @click="openNewShift(day.date)">+ blok</button>
                    </div>
                </section>
            </div>

            <!-- Overzicht: ingeplande uren per medewerker (deze week) -->
            <section v-if="!weekLoading" class="rounded-3xl border border-slate-800 bg-slate-900/80 p-5">
                <div class="mb-3 flex items-center justify-between">
                    <h2 class="text-base font-semibold text-white">Uren per medewerker <span class="text-sm font-normal text-slate-500">· ingepland deze week</span></h2>
                    <span class="text-xs text-slate-400">Totaal {{ hoursLabel(weekHoursTotal) }}</span>
                </div>
                <div v-if="weekHours.length" class="grid gap-2 sm:grid-cols-2 lg:grid-cols-3 2xl:grid-cols-4">
                    <div v-for="row in weekHours" :key="row.id" class="flex items-center justify-between rounded-xl border border-slate-800 bg-slate-950/60 px-3 py-2">
                        <span class="truncate text-sm text-slate-200">{{ row.name }}</span>
                        <span class="text-sm font-semibold text-white">{{ hoursLabel(row.minutes) }}</span>
                    </div>
                </div>
                <p v-else class="text-xs text-slate-500">Nog niemand ingepland deze week.</p>
            </section>

            <p class="text-xs text-slate-500">Klik een blok om personen toe te wijzen of het aan te passen. Een gewenst aantal toont “x van y”. <span class="text-rose-300">Een rood doorstreepte naam</span> betekent dat die persoon verlof heeft aangevraagd of gekregen in die periode.</p>
        </div>

        <!-- ============================================================ -->
        <!--  TAB: Verlof                                                  -->
        <!-- ============================================================ -->
        <div v-if="activeTab === 'leave'" class="space-y-4">
            <div v-if="leaveLoading" class="py-8 text-center text-sm text-slate-400">Laden...</div>

            <div v-else-if="leaveRequests.length === 0" class="rounded-2xl border border-slate-800 bg-slate-900 p-8 text-center text-sm text-slate-400">
                Nog geen verlofaanvragen.
            </div>

            <div v-else class="space-y-3">
                <article
                    v-for="req in leaveRequests"
                    :key="req.id"
                    class="rounded-2xl border bg-slate-900/70 p-4"
                    :class="req.status === 'pending' && req.conflict_count > 0 ? 'border-rose-500/40' : 'border-slate-800'"
                >
                    <div class="flex flex-wrap items-start justify-between gap-3">
                        <div class="min-w-0">
                            <div class="flex items-center gap-2">
                                <span class="font-semibold text-white">{{ req.staff_name }}</span>
                                <span
                                    class="rounded-full px-2 py-0.5 text-[11px] font-medium"
                                    :class="{
                                        'bg-amber-500/15 text-amber-300': req.status === 'pending',
                                        'bg-emerald-500/15 text-emerald-300': req.status === 'approved',
                                        'bg-rose-500/15 text-rose-300': req.status === 'rejected',
                                        'bg-slate-700/40 text-slate-400': req.status === 'cancelled',
                                    }"
                                >{{ req.status_label }}</span>
                            </div>
                            <div class="mt-1 text-sm text-slate-300">{{ req.period_label }} <span class="text-slate-500">· {{ req.days }} dag(en)</span></div>
                            <div v-if="req.reason" class="mt-1 text-sm text-slate-400">“{{ req.reason }}”</div>
                            <div v-if="req.reviewed_at_label" class="mt-1 text-[11px] text-slate-500">Beoordeeld op {{ req.reviewed_at_label }}</div>
                        </div>

                        <div v-if="req.status === 'pending'" class="flex shrink-0 gap-2">
                            <button
                                type="button"
                                :disabled="leaveBusyId === req.id"
                                class="rounded-xl bg-emerald-600 px-3.5 py-2 text-sm font-semibold text-white transition hover:bg-emerald-500 disabled:opacity-50"
                                @click="reviewLeave(req, 'approve')"
                            >Goedkeuren</button>
                            <button
                                type="button"
                                :disabled="leaveBusyId === req.id"
                                class="rounded-xl border border-slate-700 px-3.5 py-2 text-sm font-medium text-slate-300 transition hover:bg-slate-800 disabled:opacity-50"
                                @click="reviewLeave(req, 'reject')"
                            >Afwijzen</button>
                        </div>
                    </div>

                    <!-- Visuele conflictmelding: valt samen met ingeplande shiften -->
                    <div v-if="req.conflict_count > 0" class="mt-3 rounded-xl border border-rose-500/30 bg-rose-500/10 px-3.5 py-3">
                        <div class="flex items-center gap-2 text-sm font-semibold text-rose-200">
                            <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126zM12 15.75h.007v.008H12v-.008z" /></svg>
                            Valt samen met {{ req.conflict_count }} ingeplande shift(en)
                        </div>
                        <ul class="mt-2 space-y-1">
                            <li v-for="c in req.conflicts" :key="c.id" class="text-[13px] text-rose-100/90">
                                {{ c.date_label }} · {{ c.time_label }}<span v-if="c.role_name"> · {{ c.role_name }}</span>
                            </li>
                        </ul>
                    </div>
                </article>
            </div>
        </div>

        <!-- Modals -->
        <RoleFormModal :open="roleModalOpen" :saving="roleSaving" :error="roleError" :role="activeRole" @close="roleModalOpen = false" @submit="handleRoleSubmit" @delete="handleRoleDelete" />

        <SlotFormModal
            :open="slotModalOpen" :saving="slotSaving" :error="slotError" :slot="activeSlot"
            :roles="roles" :staff="staff"
            :weekday-label="activeWeekdayLabel" :season-label="seasonLabel(selectedSeason)"
            @close="slotModalOpen = false" @submit="handleSlotSubmit" @delete="handleSlotDelete"
        />

        <ShiftEditModal
            :open="shiftModalOpen" :saving="shiftSaving" :assignment-busy="assignmentBusy" :error="shiftError"
            :shift="activeShift" :roles="roles" :staff="staff" :date-label="activeShiftDate ? fullDate(activeShiftDate) : ''"
            @close="shiftModalOpen = false" @submit="handleShiftSubmit" @delete="handleShiftDelete"
            @add-assignment="handleAddAssignment" @remove-assignment="handleRemoveAssignment"
        />
    </div>
</template>

<script setup>
import { computed, onMounted, ref, watch } from 'vue'
import RoleFormModal from '../components/RoleFormModal.vue'
import SlotFormModal from '../components/SlotFormModal.vue'
import ShiftEditModal from '../components/ShiftEditModal.vue'
import {
    addAssignment, createRole, createShift, createSlot,
    deleteRole, deleteShift, deleteSlot, fetchRosterBase, fetchSlots, fetchWeek,
    generateWeek, removeAssignment, resetWeek, updateRole, updateShift, updateSlot,
    fetchLeaveRequests, approveLeave, rejectLeave,
} from '../services/rosterApi'

// ─── Constanten ─────────────────────────────────────────────────────────────
const weekdays = [
    { value: 1, label: 'Maandag',   short: 'Ma' },
    { value: 2, label: 'Dinsdag',   short: 'Di' },
    { value: 3, label: 'Woensdag',  short: 'Wo' },
    { value: 4, label: 'Donderdag', short: 'Do' },
    { value: 5, label: 'Vrijdag',   short: 'Vr' },
    { value: 6, label: 'Zaterdag',  short: 'Za' },
    { value: 7, label: 'Zondag',    short: 'Zo' },
]
const tabs = [
    { key: 'roles',    label: 'Rollen' },
    { key: 'template', label: 'Algemeen rooster' },
    { key: 'week',     label: 'Weekplanning' },
    { key: 'leave',    label: 'Verlof' },
]
const SEASON_LABELS = { regular: 'Normale weken', school_vac: 'Schoolvakanties', summer: 'Zomervakantie' }

// ─── State ──────────────────────────────────────────────────────────────────
const activeTab = ref('roles')
const loading   = ref(false)
const error     = ref('')
const success   = ref('')

const roles   = ref([])
const staff   = ref([])
const seasons = ref([])

// Rollen
const roleModalOpen = ref(false)
const roleSaving    = ref(false)
const roleError     = ref('')
const activeRole    = ref(null)

// Slots
const selectedSeason = ref('regular')
const slots          = ref([])
const slotsLoading   = ref(false)
const slotModalOpen  = ref(false)
const slotSaving     = ref(false)
const slotError      = ref('')
const activeSlot     = ref(null)
const activeWeekday  = ref(null)

// Week
const weekStart   = ref(mondayOf(new Date()))
const weekDays    = ref([])
const weekShifts  = ref([])
const weekLeaves  = ref([])
const weekLoading = ref(false)
const weekBusy    = ref(false)
const shiftModalOpen = ref(false)
const shiftSaving    = ref(false)
const shiftError     = ref('')
const assignmentBusy = ref(false)
const activeShift    = ref(null)
const activeShiftDate= ref(null)

// Verlof
const leaveRequests   = ref([])
const leaveLoading    = ref(false)
const leaveBusyId     = ref(null)
const leavePendingCount = ref(0)

// ─── Datum-helpers ──────────────────────────────────────────────────────────
function toISO(d) {
    return `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}-${String(d.getDate()).padStart(2, '0')}`
}
function mondayOf(date) {
    const d = new Date(date)
    d.setDate(d.getDate() - ((d.getDay() + 6) % 7))
    return toISO(d)
}
function addDaysISO(iso, n) {
    const d = new Date(iso + 'T00:00:00'); d.setDate(d.getDate() + n); return toISO(d)
}
function dayMonth(iso) {
    return new Date(iso + 'T00:00:00').toLocaleDateString('nl-BE', { day: '2-digit', month: '2-digit' })
}
function fullDate(iso) {
    return new Date(iso + 'T00:00:00').toLocaleDateString('nl-BE', { weekday: 'long', day: 'numeric', month: 'long' })
}
function weekdayLabel(iso) {
    const wd = (new Date(iso + 'T00:00:00').getDay() + 6) % 7
    return weekdays[wd].label
}
function isToday(iso) { return iso === toISO(new Date()) }

const weekRangeLabel = computed(() => {
    if (weekDays.value.length < 7) return ''
    const a = new Date(weekDays.value[0].date + 'T00:00:00')
    const b = new Date(weekDays.value[6].date + 'T00:00:00')
    const fmt = (d) => d.toLocaleDateString('nl-BE', { day: 'numeric', month: 'short' })
    return `${fmt(a)} – ${fmt(b)} ${b.getFullYear()}`
})

// ─── Seizoen-helpers ────────────────────────────────────────────────────────
const seasonOptions = computed(() => {
    const keys = ['regular', ...seasons.value.map(s => s.season_key)]
    return [...new Set(keys)]
})
function seasonLabel(key) {
    return seasons.value.find(s => s.season_key === key)?.season_name ?? SEASON_LABELS[key] ?? key
}
function seasonRange(key) {
    const s = seasons.value.find(x => x.season_key === key)
    if (!s) return ''
    return `${s.date_from} → ${s.date_until}`
}

// ─── Rol-helpers ────────────────────────────────────────────────────────────
const rolesById = computed(() => Object.fromEntries(roles.value.map(r => [r.id, r])))
function roleColor(id) { return rolesById.value[id]?.color || '#64748b' }
function roleName(id) { return rolesById.value[id]?.name || 'Geen rol' }

// ─── Medewerker-helpers ─────────────────────────────────────────────────────
const staffById = computed(() => Object.fromEntries(staff.value.map(s => [s.id, s])))
function staffName(id) { return staffById.value[id]?.name || ('#' + id) }
function defaultUserNames(ids) { return (ids || []).map(staffName).join(', ') }

// ─── Rollen ─────────────────────────────────────────────────────────────────
function openNewRole() { activeRole.value = null; roleError.value = ''; roleModalOpen.value = true }
function openEditRole(role) { activeRole.value = role; roleError.value = ''; roleModalOpen.value = true }

async function handleRoleSubmit(payload) {
    roleSaving.value = true; roleError.value = ''
    try {
        if (activeRole.value?.id) {
            const updated = await updateRole(activeRole.value.id, payload)
            roles.value = roles.value.map(r => (r.id === updated.id ? updated : r))
        } else {
            roles.value.push(await createRole(payload))
        }
        roleModalOpen.value = false
    } catch (err) {
        roleError.value = err?.data?.message || 'Opslaan mislukt.'
    } finally {
        roleSaving.value = false
    }
}
async function handleRoleDelete() {
    if (!activeRole.value?.id) return
    roleSaving.value = true; roleError.value = ''
    try {
        await deleteRole(activeRole.value.id)
        roles.value = roles.value.filter(r => r.id !== activeRole.value.id)
        roleModalOpen.value = false
    } catch (err) {
        roleError.value = err?.data?.message || 'Verwijderen mislukt.'
    } finally {
        roleSaving.value = false
    }
}

// ─── Slots ──────────────────────────────────────────────────────────────────
const activeWeekdayLabel = computed(() => weekdays.find(w => w.value === activeWeekday.value)?.label || '')
function slotsForDay(weekday) { return slots.value.filter(s => s.weekday === weekday) }

async function loadSlots() {
    slotsLoading.value = true; error.value = ''
    try {
        const res = await fetchSlots(selectedSeason.value)
        slots.value = res.slots || []
    } catch (err) {
        error.value = err?.data?.message || 'Kon slots niet laden.'
    } finally {
        slotsLoading.value = false
    }
}

function openNewSlot(weekday) { activeWeekday.value = weekday; activeSlot.value = null; slotError.value = ''; slotModalOpen.value = true }
function openEditSlot(weekday, slot) { activeWeekday.value = weekday; activeSlot.value = slot; slotError.value = ''; slotModalOpen.value = true }

async function handleSlotSubmit(payload) {
    slotSaving.value = true; slotError.value = ''
    const body = { ...payload, season_key: selectedSeason.value, weekday: activeWeekday.value }
    try {
        if (activeSlot.value?.id) {
            const res = await updateSlot(activeSlot.value.id, body)
            slots.value = slots.value.map(s => (s.id === res.slot.id ? res.slot : s))
        } else {
            const res = await createSlot(body)
            slots.value.push(res.slot)
        }
        slotModalOpen.value = false
    } catch (err) {
        slotError.value = err?.data?.message || 'Opslaan mislukt.'
    } finally {
        slotSaving.value = false
    }
}
async function handleSlotDelete() {
    if (!activeSlot.value?.id) return
    slotSaving.value = true; slotError.value = ''
    try {
        await deleteSlot(activeSlot.value.id)
        slots.value = slots.value.filter(s => s.id !== activeSlot.value.id)
        slotModalOpen.value = false
    } catch (err) {
        slotError.value = err?.data?.message || 'Verwijderen mislukt.'
    } finally {
        slotSaving.value = false
    }
}

// ─── Week ───────────────────────────────────────────────────────────────────
function shiftsForDay(date) { return weekShifts.value.filter(s => s.date === date) }

function shiftStyle(shift) {
    const c = roleColor(shift.role_id)
    return { borderColor: c + '66', backgroundColor: c + '1a' }
}
function fillLabel(shift) {
    return shift.desired_count ? `${shift.filled_count}/${shift.desired_count}` : `${shift.filled_count}`
}
function fillTextClass(shift) {
    if (!shift.desired_count) return 'text-slate-400'
    if (shift.filled_count < shift.desired_count) return 'text-amber-300'
    if (shift.filled_count > shift.desired_count) return 'text-violet-300'
    return 'text-emerald-300'
}

function applyWeek(res) {
    weekStart.value = res.week_start
    weekDays.value  = res.days || []
    weekShifts.value = res.shifts || []
    weekLeaves.value = res.leaves || []
    if (res.roles) roles.value = res.roles
    if (res.staff) staff.value = res.staff
}

// Heeft deze persoon (aangevraagd/goedgekeurd) verlof op deze dag?
function userHasLeave(userId, date) {
    return weekLeaves.value.some(l =>
        l.user_id === userId && date >= l.start_date && date <= l.end_date)
}

// ─── Uren-overzicht per medewerker ──────────────────────────────────────────
function parseHM(t) {
    if (!t) return 0
    const [h, m] = String(t).split(':')
    return (parseInt(h, 10) || 0) * 60 + (parseInt(m, 10) || 0)
}
function durationMinutes(start, end) {
    let s = parseHM(start), e = parseHM(end)
    if (e < s) e += 1440 // overnacht
    return Math.max(0, e - s)
}
function hoursLabel(min) {
    const h = Math.floor(min / 60), m = min % 60
    if (h && m) return `${h}u${String(m).padStart(2, '0')}`
    if (h) return `${h}u`
    return `${m}m`
}

// Algemeen rooster: uren per week per medewerker volgens de vaste invullers.
const templateHours = computed(() => {
    const map = new Map()
    for (const slot of slots.value) {
        const mins = durationMinutes(slot.starts_at, slot.ends_at)
        for (const uid of (slot.default_user_ids || [])) {
            map.set(uid, (map.get(uid) || 0) + mins)
        }
    }
    return [...map.entries()]
        .map(([id, minutes]) => ({ id, name: staffName(id), minutes }))
        .sort((a, b) => b.minutes - a.minutes)
})
const templateHoursTotal = computed(() => templateHours.value.reduce((s, x) => s + x.minutes, 0))

// Weekplanning: ingeplande uren per medewerker voor de getoonde week.
const weekHours = computed(() => {
    const map = new Map()
    for (const shift of weekShifts.value) {
        const mins = durationMinutes(shift.starts_at, shift.ends_at)
        for (const a of (shift.assignments || [])) {
            const cur = map.get(a.user_id)
            map.set(a.user_id, { name: a.name, minutes: (cur?.minutes || 0) + mins })
        }
    }
    return [...map.entries()]
        .map(([id, v]) => ({ id, name: v.name, minutes: v.minutes }))
        .sort((a, b) => b.minutes - a.minutes)
})
const weekHoursTotal = computed(() => weekHours.value.reduce((s, x) => s + x.minutes, 0))

async function loadWeek() {
    weekLoading.value = true; error.value = ''
    try {
        applyWeek(await fetchWeek(weekStart.value))
    } catch (err) {
        error.value = err?.data?.message || 'Kon weekplanning niet laden.'
    } finally {
        weekLoading.value = false
    }
}
function shiftWeek(delta) { weekStart.value = addDaysISO(weekStart.value, delta * 7); loadWeek() }
function goToday() { weekStart.value = mondayOf(new Date()); loadWeek() }

async function doGenerate() {
    weekBusy.value = true; error.value = ''; success.value = ''
    try {
        const res = await generateWeek(weekStart.value)
        applyWeek(res)
        success.value = res.created > 0 ? `${res.created} blok(ken) gegenereerd.` : 'Geen nieuwe blokken — alle dagen hadden al planning.'
        setTimeout(() => (success.value = ''), 3500)
    } catch (err) {
        error.value = err?.data?.message || 'Genereren mislukt.'
    } finally {
        weekBusy.value = false
    }
}
async function doReset() {
    if (!confirm('De volledige week terugzetten naar het algemene rooster? Aanpassingen van deze week gaan verloren.')) return
    weekBusy.value = true; error.value = ''
    try {
        applyWeek(await resetWeek(weekStart.value))
        success.value = 'Week teruggezet.'
        setTimeout(() => (success.value = ''), 3000)
    } catch (err) {
        error.value = err?.data?.message || 'Reset mislukt.'
    } finally {
        weekBusy.value = false
    }
}

// ─── Shift-modal ────────────────────────────────────────────────────────────
function openNewShift(date) { activeShiftDate.value = date; activeShift.value = null; shiftError.value = ''; shiftModalOpen.value = true }
function openEditShift(date, shift) { activeShiftDate.value = date; activeShift.value = shift; shiftError.value = ''; shiftModalOpen.value = true }

function syncActiveShift() {
    // Houd de geopende shift in de modal gelijk met de herladen weekdata.
    if (activeShift.value?.id) {
        activeShift.value = weekShifts.value.find(s => s.id === activeShift.value.id) || null
        if (!activeShift.value) shiftModalOpen.value = false
    }
}

async function handleShiftSubmit(payload) {
    shiftSaving.value = true; shiftError.value = ''
    try {
        if (activeShift.value?.id) {
            await updateShift(activeShift.value.id, payload)
        } else {
            await createShift({ date: activeShiftDate.value, ...payload })
        }
        await loadWeek()
        if (activeShift.value?.id) syncActiveShift()
        else shiftModalOpen.value = false
    } catch (err) {
        shiftError.value = err?.data?.message || 'Opslaan mislukt.'
    } finally {
        shiftSaving.value = false
    }
}
async function handleShiftDelete() {
    if (!activeShift.value?.id) return
    if (!confirm('Dit blok verwijderen?')) return
    shiftSaving.value = true; shiftError.value = ''
    try {
        await deleteShift(activeShift.value.id)
        shiftModalOpen.value = false
        await loadWeek()
    } catch (err) {
        shiftError.value = err?.data?.message || 'Verwijderen mislukt.'
    } finally {
        shiftSaving.value = false
    }
}
async function handleAddAssignment(userId) {
    if (!activeShift.value?.id) return
    assignmentBusy.value = true; shiftError.value = ''
    try {
        await addAssignment(activeShift.value.id, userId)
        await loadWeek(); syncActiveShift()
    } catch (err) {
        shiftError.value = err?.data?.message || 'Toewijzen mislukt.'
    } finally {
        assignmentBusy.value = false
    }
}
async function handleRemoveAssignment(assignmentId) {
    assignmentBusy.value = true; shiftError.value = ''
    try {
        await removeAssignment(assignmentId)
        await loadWeek(); syncActiveShift()
    } catch (err) {
        shiftError.value = err?.data?.message || 'Verwijderen mislukt.'
    } finally {
        assignmentBusy.value = false
    }
}

// ─── Verlof ─────────────────────────────────────────────────────────────────
async function loadLeave() {
    leaveLoading.value = true; error.value = ''
    try {
        const res = await fetchLeaveRequests()
        leaveRequests.value = res.data || []
        leavePendingCount.value = res.pending_count || 0
    } catch (err) {
        error.value = err?.data?.message || 'Kon verlofaanvragen niet laden.'
    } finally {
        leaveLoading.value = false
    }
}

async function reviewLeave(req, action) {
    if (action === 'approve' && req.conflict_count > 0) {
        if (!confirm(`Let op: deze periode valt samen met ${req.conflict_count} ingeplande shift(en) van ${req.staff_name}. Toch goedkeuren?`)) return
    }
    leaveBusyId.value = req.id; error.value = ''; success.value = ''
    try {
        const fn = action === 'approve' ? approveLeave : rejectLeave
        const res = await fn(req.id)
        leaveRequests.value = leaveRequests.value.map(r => (r.id === res.data.id ? res.data : r))
        leavePendingCount.value = leaveRequests.value.filter(r => r.status === 'pending').length
        success.value = action === 'approve' ? 'Verlof goedgekeurd.' : 'Verlof afgewezen.'
        setTimeout(() => (success.value = ''), 3000)
        // Het planningsbord toont verlofmarkeringen → herlaad indien geladen.
        if (weekDays.value.length) loadWeek()
    } catch (err) {
        error.value = err?.data?.message || 'Bijwerken mislukt.'
    } finally {
        leaveBusyId.value = null
    }
}

// ─── Init ───────────────────────────────────────────────────────────────────
async function loadBase() {
    loading.value = true; error.value = ''
    try {
        const res = await fetchRosterBase()
        roles.value = res.roles || []
        staff.value = res.staff || []
        seasons.value = res.seasons || []
    } catch (err) {
        error.value = err?.data?.message || 'Kon roostergegevens niet laden.'
    } finally {
        loading.value = false
    }
}

watch(activeTab, (tab) => {
    if (tab === 'template' && slots.value.length === 0) loadSlots()
    if (tab === 'week' && weekDays.value.length === 0) loadWeek()
    if (tab === 'leave') loadLeave()
})

onMounted(async () => {
    await loadBase()
    // Laad het verlof-aantal zodat de tab-badge meteen klopt.
    loadLeave()
})
</script>
