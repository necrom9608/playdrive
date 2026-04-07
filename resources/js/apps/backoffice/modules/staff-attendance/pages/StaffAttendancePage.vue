<template>
    <div class="space-y-6">
        <div class="flex flex-wrap items-start justify-between gap-4">
            <div>
                <h1 class="text-3xl font-bold text-white">Personeelsaanwezigheden</h1>
                <p class="mt-2 text-slate-400">
                    Beheer check-ins, corrigeer fouten en bekijk per medewerker op welke dagen en uren er gewerkt werd.
                </p>
            </div>
        </div>

        <div class="rounded-2xl border border-slate-800 bg-slate-900 p-4 shadow-sm">
            <div class="flex flex-col gap-4">
                <div class="flex flex-wrap items-center gap-2">
                    <button
                        v-for="option in rangeOptions"
                        :key="option.value"
                        type="button"
                        class="rounded-xl border px-4 py-2 text-sm font-semibold transition"
                        :class="filters.range_key === option.value ? 'border-blue-500 bg-blue-500/15 text-blue-200' : 'border-slate-700 bg-slate-950 text-slate-300 hover:bg-slate-800'"
                        @click="applyRangePreset(option.value)"
                    >
                        {{ option.label }}
                    </button>

                    <div class="ml-auto flex items-center gap-2">
                        <button type="button" class="rounded-xl border border-slate-700 bg-slate-950 px-3 py-2 text-sm font-medium text-slate-300 transition hover:bg-slate-800" :disabled="loading" @click="shiftRange(-1)">Vorige</button>
                        <button type="button" class="rounded-xl border border-slate-700 bg-slate-950 px-3 py-2 text-sm font-medium text-slate-300 transition hover:bg-slate-800" :disabled="loading" @click="shiftRange(1)">Volgende</button>
                    </div>
                </div>

                <div class="flex flex-wrap items-center gap-3 text-sm">
                    <div class="rounded-xl border border-slate-800 bg-slate-950 px-4 py-2 text-slate-300">
                        <span class="font-medium text-white">Periode:</span> {{ activeRangeLabel }}
                    </div>
                    <div class="rounded-xl border border-slate-800 bg-slate-950 px-4 py-2 text-slate-300">
                        <span class="font-medium text-white">Van:</span> {{ displayDate(filters.date_from) }}
                    </div>
                    <div class="rounded-xl border border-slate-800 bg-slate-950 px-4 py-2 text-slate-300">
                        <span class="font-medium text-white">Tot:</span> {{ displayDate(filters.date_to) }}
                    </div>
                </div>

                <div class="grid gap-4 xl:grid-cols-[1.2fr_0.9fr_auto]">
                    <div>
                        <label class="mb-2 block text-sm font-medium text-slate-300">Medewerker</label>
                        <select v-model="filters.staff_id" class="w-full rounded-2xl border border-slate-700 bg-slate-950 px-4 py-3 text-sm text-white outline-none transition focus:border-blue-500">
                            <option value="">Alle medewerkers</option>
                            <option v-for="item in staff" :key="item.id" :value="String(item.id)">{{ item.name }}</option>
                        </select>
                    </div>

                    <div>
                        <label class="mb-2 block text-sm font-medium text-slate-300">Status</label>
                        <select v-model="filters.status" class="w-full rounded-2xl border border-slate-700 bg-slate-950 px-4 py-3 text-sm text-white outline-none transition focus:border-blue-500">
                            <option value="all">Alles</option>
                            <option value="open">Open</option>
                            <option value="closed">Afgesloten</option>
                        </select>
                    </div>

                    <div class="flex items-end gap-3">
                        <button type="button" class="rounded-xl border border-slate-700 px-4 py-3 text-sm font-medium text-slate-300 transition hover:bg-slate-800" :disabled="loading" @click="loadData">Vernieuwen</button>
                    </div>
                </div>

                <div v-if="filters.range_key === 'custom'" class="grid gap-4 md:grid-cols-2 xl:max-w-3xl">
                    <div>
                        <label class="mb-2 block text-sm font-medium text-slate-300">Van</label>
                        <input v-model="filters.date_from" type="date" class="w-full rounded-2xl border border-slate-700 bg-slate-950 px-4 py-3 text-sm text-white outline-none transition focus:border-blue-500">
                    </div>
                    <div>
                        <label class="mb-2 block text-sm font-medium text-slate-300">Tot</label>
                        <input v-model="filters.date_to" type="date" class="w-full rounded-2xl border border-slate-700 bg-slate-950 px-4 py-3 text-sm text-white outline-none transition focus:border-blue-500">
                    </div>
                </div>
            </div>
        </div>

        <div v-if="error" class="rounded-2xl border border-red-800 bg-red-950/40 px-4 py-3 text-sm text-red-300">
            {{ error }}
        </div>

        <div class="grid gap-4 lg:grid-cols-4">
            <div v-for="stat in statsCards" :key="stat.label" class="rounded-2xl border border-slate-800 bg-slate-900 p-5 shadow-sm">
                <div class="text-sm text-slate-400">{{ stat.label }}</div>
                <div class="mt-2 text-2xl font-semibold text-white">{{ stat.value }}</div>
            </div>
        </div>

        <div class="rounded-2xl border border-slate-800 bg-slate-900 shadow-sm">
            <div class="flex items-center justify-between border-b border-slate-800 px-5 py-4">
                <div>
                    <h2 class="text-lg font-semibold text-white">Momenteel ingecheckt</h2>
                    <p class="text-sm text-slate-400">Open sessies die nog niet werden afgesloten.</p>
                </div>
            </div>

            <div v-if="loading" class="p-6 text-sm text-slate-400">Laden...</div>
            <div v-else-if="openSessions.length === 0" class="p-6 text-sm text-slate-400">Geen open sessies gevonden.</div>
            <div v-else class="grid gap-3 p-4 md:grid-cols-2 xl:grid-cols-3">
                <div v-for="session in openSessions" :key="session.id" class="rounded-2xl border border-slate-800 bg-slate-950/60 px-4 py-3">
                    <div class="flex items-center gap-3">
                        <div class="h-14 w-14 overflow-hidden rounded-xl border border-slate-800 bg-slate-900">
                            <img v-if="session.badge_preview_url" :src="session.badge_preview_url" alt="Badge preview" class="h-full w-full object-cover">
                            <div v-else class="flex h-full w-full items-center justify-center text-sm font-semibold text-slate-400">{{ initials(session.user_name) }}</div>
                        </div>

                        <div class="min-w-0 flex-1">
                            <div class="flex items-center gap-2">
                                <div class="truncate text-sm font-semibold text-white">{{ session.user_name }}</div>
                                <span class="inline-flex rounded-full bg-emerald-500/15 px-2 py-0.5 text-[11px] font-semibold text-emerald-300">Open</span>
                            </div>
                            <div class="mt-1 text-xs text-slate-400">Sinds {{ session.checked_in_at_full_label }}</div>
                            <div class="mt-2 flex flex-wrap gap-x-4 gap-y-1 text-xs text-slate-300">
                                <span><span class="text-slate-500">Duur:</span> <span class="text-white">{{ session.duration_label }}</span></span>
                                <span><span class="text-slate-500">RFID:</span> <span class="text-white">{{ session.rfid_uid || '—' }}</span></span>
                            </div>
                        </div>

                        <button type="button" class="rounded-lg border border-slate-700 px-3 py-2 text-xs font-medium text-slate-300 transition hover:bg-slate-800" @click="openEditModal(session)">Bewerken</button>
                    </div>
                </div>
            </div>
        </div>

        <div class="grid gap-6 xl:grid-cols-[1.05fr_1.45fr]">
            <div class="rounded-2xl border border-slate-800 bg-slate-900 shadow-sm">
                <div class="flex items-center justify-between border-b border-slate-800 px-5 py-4">
                    <div>
                        <h2 class="text-lg font-semibold text-white">Overzicht per medewerker</h2>
                        <p class="text-sm text-slate-400">Totale uren en werkdagen binnen de gekozen periode.</p>
                    </div>
                </div>

                <div v-if="loading" class="p-6 text-sm text-slate-400">Laden...</div>
                <div v-else-if="staffSummaries.length === 0" class="p-6 text-sm text-slate-400">Geen uren gevonden voor deze periode.</div>
                <div v-else class="overflow-x-auto">
                    <table class="min-w-full text-sm">
                        <thead class="bg-slate-950 text-slate-300">
                            <tr>
                                <th class="px-4 py-3 text-left font-semibold">Medewerker</th>
                                <th class="px-4 py-3 text-left font-semibold">Dagen</th>
                                <th class="px-4 py-3 text-left font-semibold">Sessies</th>
                                <th class="px-4 py-3 text-left font-semibold">Totaal</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="item in staffSummaries" :key="item.user_id" class="cursor-pointer border-t border-slate-800 transition hover:bg-slate-950/60" :class="String(item.user_id) === filters.staff_id ? 'bg-slate-950/80' : 'bg-slate-900'" @click="selectStaff(item.user_id)">
                                <td class="px-4 py-3 text-white">
                                    <div class="flex items-center gap-3">
                                        <div class="h-10 w-10 overflow-hidden rounded-xl border border-slate-800 bg-slate-950">
                                            <img v-if="item.badge_preview_url" :src="item.badge_preview_url" alt="Badge preview" class="h-full w-full object-cover">
                                            <div v-else class="flex h-full w-full items-center justify-center text-xs font-semibold text-slate-400">{{ initials(item.user_name) }}</div>
                                        </div>
                                        <div class="min-w-0">
                                            <div class="flex items-center gap-2">
                                                <span class="truncate">{{ item.user_name }}</span>
                                                <span v-if="item.is_active" class="inline-flex rounded-full bg-emerald-500/15 px-2 py-0.5 text-[11px] font-semibold text-emerald-300">Open</span>
                                            </div>
                                            <div class="text-xs text-slate-500">{{ item.first_check_in_label || '—' }} · {{ item.last_check_out_label || '—' }}</div>
                                        </div>
                                    </div>
                                </td>
                                <td class="px-4 py-3 text-slate-300">{{ item.worked_days }}</td>
                                <td class="px-4 py-3 text-slate-300">{{ item.session_count }}</td>
                                <td class="px-4 py-3 font-semibold text-white">{{ item.worked_time_label }}</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="rounded-2xl border border-slate-800 bg-slate-900 shadow-sm">
                <div class="flex items-center justify-between border-b border-slate-800 px-5 py-4">
                    <div>
                        <h2 class="text-lg font-semibold text-white">Werkdagen</h2>
                        <p class="text-sm text-slate-400">Dagoverzicht voor de geselecteerde medewerker.</p>
                    </div>
                </div>

                <div v-if="!filters.staff_id" class="p-6 text-sm text-slate-400">Selecteer links een medewerker om de dagtotalen te bekijken.</div>
                <div v-else-if="loading" class="p-6 text-sm text-slate-400">Laden...</div>
                <div v-else-if="selectedStaffDays.length === 0" class="p-6 text-sm text-slate-400">Geen werkdagen gevonden voor deze medewerker.</div>
                <div v-else class="overflow-x-auto">
                    <table class="min-w-full text-sm">
                        <thead class="bg-slate-950 text-slate-300">
                            <tr>
                                <th class="px-4 py-3 text-left font-semibold">Datum</th>
                                <th class="px-4 py-3 text-left font-semibold">Eerste in</th>
                                <th class="px-4 py-3 text-left font-semibold">Laatste uit</th>
                                <th class="px-4 py-3 text-left font-semibold">Sessies</th>
                                <th class="px-4 py-3 text-left font-semibold">Totaal</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="day in selectedStaffDays" :key="day.date" class="border-t border-slate-800 bg-slate-900 align-top">
                                <td class="px-4 py-3 font-medium text-white">{{ day.date_label }}</td>
                                <td class="px-4 py-3 text-slate-300">{{ day.first_check_in_label || '—' }}</td>
                                <td class="px-4 py-3 text-slate-300">{{ day.last_check_out_label || '—' }}</td>
                                <td class="px-4 py-3 text-slate-300">
                                    <div class="flex flex-wrap gap-2">
                                        <span v-for="session in day.sessions" :key="`${day.date}-${session.id}-${session.checked_in_at_label}`" class="inline-flex rounded-xl border border-slate-700 bg-slate-950 px-2.5 py-1 text-xs text-slate-300">
                                            {{ session.checked_in_at_label }} - {{ session.checked_out_at_label }} · {{ session.duration_label }}
                                        </span>
                                    </div>
                                </td>
                                <td class="px-4 py-3 font-semibold text-white">{{ day.total_time_label }}</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <div class="rounded-2xl border border-slate-800 bg-slate-900 shadow-sm">
            <div class="flex items-center justify-between border-b border-slate-800 px-5 py-4">
                <div>
                    <h2 class="text-lg font-semibold text-white">Sessies</h2>
                    <p class="text-sm text-slate-400">Volledige lijst van check-ins binnen de gekozen filters.</p>
                </div>
            </div>

            <div v-if="loading" class="p-6 text-sm text-slate-400">Laden...</div>
            <div v-else-if="sessions.length === 0" class="p-6 text-sm text-slate-400">Geen sessies gevonden.</div>
            <div v-else class="overflow-x-auto">
                <table class="min-w-full text-sm">
                    <thead class="bg-slate-950 text-slate-300">
                        <tr>
                            <th class="px-4 py-3 text-left font-semibold">Medewerker</th>
                            <th class="px-4 py-3 text-left font-semibold">In</th>
                            <th class="px-4 py-3 text-left font-semibold">Uit</th>
                            <th class="px-4 py-3 text-left font-semibold">Duur</th>
                            <th class="px-4 py-3 text-left font-semibold">RFID</th>
                            <th class="px-4 py-3 text-left font-semibold">Verwerkt door</th>
                            <th class="px-4 py-3 text-right font-semibold">Acties</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="session in sessions" :key="session.id" class="border-t border-slate-800 bg-slate-900">
                            <td class="px-4 py-3 text-white">
                                <div class="flex items-center gap-3">
                                    <div class="h-10 w-10 overflow-hidden rounded-xl border border-slate-800 bg-slate-950">
                                        <img v-if="session.badge_preview_url" :src="session.badge_preview_url" alt="Badge preview" class="h-full w-full object-cover">
                                        <div v-else class="flex h-full w-full items-center justify-center text-xs font-semibold text-slate-400">{{ initials(session.user_name) }}</div>
                                    </div>
                                    <div class="min-w-0">
                                        <div class="flex items-center gap-2">
                                            <span>{{ session.user_name }}</span>
                                            <span v-if="session.is_active" class="inline-flex rounded-full bg-emerald-500/15 px-2 py-0.5 text-[11px] font-semibold text-emerald-300">Open</span>
                                        </div>
                                    </div>
                                </div>
                            </td>
                            <td class="px-4 py-3 text-slate-300">{{ session.checked_in_at_full_label }}</td>
                            <td class="px-4 py-3 text-slate-300">{{ session.checked_out_at_full_label }}</td>
                            <td class="px-4 py-3 font-medium text-white">{{ session.duration_label }}</td>
                            <td class="px-4 py-3 text-slate-300">{{ session.rfid_uid || '—' }}</td>
                            <td class="px-4 py-3 text-slate-300">{{ processedByLabel(session) }}</td>
                            <td class="px-4 py-3">
                                <div class="flex justify-end gap-2">
                                    <button type="button" class="rounded-lg border border-slate-700 px-3 py-2 text-xs font-medium text-slate-300 transition hover:bg-slate-800" @click="openEditModal(session)">Bewerken</button>
                                    <button type="button" class="rounded-lg border border-red-800 px-3 py-2 text-xs font-medium text-red-300 transition hover:bg-red-950/40" @click="handleDelete(session)">Verwijderen</button>
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <StaffAttendanceEditModal
            :open="modalOpen"
            :item="editingItem"
            :staff="staff"
            :saving="saving"
            :error="modalError"
            @close="closeModal"
            @submit="handleSubmit"
        />
    </div>
</template>

<script setup>
import { computed, onMounted, reactive, ref } from 'vue'
import StaffAttendanceEditModal from '../components/StaffAttendanceEditModal.vue'
import { deleteStaffAttendance, fetchStaffAttendance, updateStaffAttendance } from '../services/staffAttendanceApi'

const loading = ref(false)
const saving = ref(false)
const error = ref('')
const modalError = ref('')
const modalOpen = ref(false)
const editingItem = ref(null)
const data = ref({
    staff: [],
    stats: {},
    open_sessions: [],
    sessions: [],
    staff_summaries: [],
    selected_staff_days: [],
})

const filters = reactive({
    staff_id: '',
    date_from: '',
    date_to: '',
    status: 'all',
    range_key: 'today',
})

const rangeOptions = [
    { value: 'today', label: 'Vandaag' },
    { value: 'week', label: 'Week' },
    { value: 'month', label: 'Maand' },
    { value: 'custom', label: 'Periode' },
]

const staff = computed(() => data.value.staff || [])
const openSessions = computed(() => data.value.open_sessions || [])
const sessions = computed(() => data.value.sessions || [])
const staffSummaries = computed(() => data.value.staff_summaries || [])
const selectedStaffDays = computed(() => data.value.selected_staff_days || [])
const statsCards = computed(() => ([
    { label: 'Open sessies', value: data.value.stats?.open_sessions ?? 0 },
    { label: 'Sessies in periode', value: data.value.stats?.session_count ?? 0 },
    { label: 'Totaal gewerkte uren', value: data.value.stats?.worked_time_label ?? '0 min' },
    { label: 'Medewerkers met uren', value: data.value.stats?.staff_with_hours ?? 0 },
]))
const activeRangeLabel = computed(() => {
    const found = rangeOptions.find((option) => option.value === filters.range_key)
    return found?.label || 'Periode'
})

function formatDateInput(date) {
    const year = date.getFullYear()
    const month = String(date.getMonth() + 1).padStart(2, '0')
    const day = String(date.getDate()).padStart(2, '0')
    return `${year}-${month}-${day}`
}

function dateFromInput(value) {
    const [year, month, day] = String(value || '').split('-').map(Number)
    return new Date(year, (month || 1) - 1, day || 1)
}

function getRangeDates(rangeKey, anchor = new Date()) {
    const base = new Date(anchor)
    base.setHours(12, 0, 0, 0)

    if (rangeKey === 'month') {
        const start = new Date(base.getFullYear(), base.getMonth(), 1)
        const end = new Date(base.getFullYear(), base.getMonth() + 1, 0)
        return { start, end }
    }

    if (rangeKey === 'week') {
        const day = base.getDay()
        const mondayOffset = day === 0 ? -6 : 1 - day
        const start = new Date(base)
        start.setDate(base.getDate() + mondayOffset)
        const end = new Date(start)
        end.setDate(start.getDate() + 6)
        return { start, end }
    }

    return { start: base, end: base }
}

function applyRangePreset(rangeKey, anchor = null) {
    filters.range_key = rangeKey

    if (rangeKey === 'custom') {
        if (!filters.date_from || !filters.date_to) {
            const current = getRangeDates('month', new Date())
            filters.date_from = formatDateInput(current.start)
            filters.date_to = formatDateInput(current.end)
        }
        return
    }

    const { start, end } = getRangeDates(rangeKey, anchor || new Date())
    filters.date_from = formatDateInput(start)
    filters.date_to = formatDateInput(end)
}

function shiftRange(direction) {
    if (filters.range_key === 'custom') {
        const start = dateFromInput(filters.date_from)
        const end = dateFromInput(filters.date_to)
        const diff = Math.max(1, Math.round((end - start) / 86400000) + 1)
        start.setDate(start.getDate() + (diff * direction))
        end.setDate(end.getDate() + (diff * direction))
        filters.date_from = formatDateInput(start)
        filters.date_to = formatDateInput(end)
        loadData()
        return
    }

    const anchor = dateFromInput(filters.date_from || formatDateInput(new Date()))

    if (filters.range_key === 'month') {
        anchor.setMonth(anchor.getMonth() + direction)
    } else if (filters.range_key === 'week') {
        anchor.setDate(anchor.getDate() + (7 * direction))
    } else {
        anchor.setDate(anchor.getDate() + direction)
    }

    applyRangePreset(filters.range_key, anchor)
    loadData()
}

function displayDate(value) {
    if (!value) {
        return '—'
    }

    return dateFromInput(value).toLocaleDateString('nl-BE')
}

function initials(name) {
    return String(name || '—')
        .split(' ')
        .filter(Boolean)
        .slice(0, 2)
        .map((part) => part[0])
        .join('')
        .toUpperCase() || '—'
}

async function loadData() {
    loading.value = true
    error.value = ''

    try {
        const response = await fetchStaffAttendance({
            staff_id: filters.staff_id,
            date_from: filters.date_from,
            date_to: filters.date_to,
            status: filters.status,
        })

        data.value = response
        filters.staff_id = response.filters?.staff_id ? String(response.filters.staff_id) : ''
        filters.date_from = response.filters?.date_from || ''
        filters.date_to = response.filters?.date_to || ''
        filters.status = response.filters?.status || 'all'
    } catch (err) {
        console.error(err)
        error.value = err?.data?.message || 'Kon aanwezigheden niet laden.'
    } finally {
        loading.value = false
    }
}

function selectStaff(userId) {
    filters.staff_id = String(userId)
    loadData()
}

function openEditModal(item) {
    editingItem.value = item
    modalError.value = ''
    modalOpen.value = true
}

function closeModal() {
    modalOpen.value = false
    editingItem.value = null
    modalError.value = ''
}

function processedByLabel(session) {
    if (session.checked_out_by_name) {
        return `${session.checked_in_by_name || '—'} / ${session.checked_out_by_name}`
    }

    return session.checked_in_by_name || '—'
}

async function handleSubmit(payload) {
    if (!editingItem.value?.id) {
        return
    }

    saving.value = true
    modalError.value = ''

    try {
        await updateStaffAttendance(editingItem.value.id, {
            user_id: payload.user_id ? Number(payload.user_id) : null,
            checked_in_at: payload.checked_in_at,
            checked_out_at: payload.checked_out_at || null,
            rfid_uid: payload.rfid_uid || null,
        })

        closeModal()
        await loadData()
    } catch (err) {
        console.error(err)
        if (err?.status === 422 && err?.data?.errors) {
            modalError.value = Object.values(err.data.errors)?.[0]?.[0] || 'Validatiefout bij opslaan van aanwezigheid.'
        } else {
            modalError.value = err?.data?.message || 'Kon aanwezigheid niet opslaan.'
        }
    } finally {
        saving.value = false
    }
}

async function handleDelete(item) {
    if (!window.confirm(`Sessie van ${item.user_name} verwijderen?`)) {
        return
    }

    error.value = ''

    try {
        await deleteStaffAttendance(item.id)
        await loadData()
    } catch (err) {
        console.error(err)
        error.value = err?.data?.message || 'Kon sessie niet verwijderen.'
    }
}

onMounted(() => {
    applyRangePreset('today')
    loadData()
})
</script>
