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
            <div class="grid gap-4 xl:grid-cols-[1.2fr_1fr_1fr_0.9fr_auto]">
                <div>
                    <label class="mb-2 block text-sm font-medium text-slate-300">Medewerker</label>
                    <select v-model="filters.staff_id" class="w-full rounded-2xl border border-slate-700 bg-slate-950 px-4 py-3 text-sm text-white outline-none transition focus:border-blue-500">
                        <option value="">Alle medewerkers</option>
                        <option v-for="item in staff" :key="item.id" :value="String(item.id)">{{ item.name }}</option>
                    </select>
                </div>

                <div>
                    <label class="mb-2 block text-sm font-medium text-slate-300">Van</label>
                    <input v-model="filters.date_from" type="date" class="w-full rounded-2xl border border-slate-700 bg-slate-950 px-4 py-3 text-sm text-white outline-none transition focus:border-blue-500">
                </div>

                <div>
                    <label class="mb-2 block text-sm font-medium text-slate-300">Tot</label>
                    <input v-model="filters.date_to" type="date" class="w-full rounded-2xl border border-slate-700 bg-slate-950 px-4 py-3 text-sm text-white outline-none transition focus:border-blue-500">
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
            <div v-else class="grid gap-4 p-5 md:grid-cols-2 xl:grid-cols-3">
                <div v-for="session in openSessions" :key="session.id" class="rounded-2xl border border-slate-800 bg-slate-950/60 p-4">
                    <div class="flex items-start justify-between gap-3">
                        <div>
                            <div class="text-base font-semibold text-white">{{ session.user_name }}</div>
                            <div class="mt-1 text-sm text-slate-400">Sinds {{ session.checked_in_at_full_label }}</div>
                        </div>
                        <span class="inline-flex rounded-full bg-emerald-500/15 px-2.5 py-1 text-xs font-semibold text-emerald-300">Open</span>
                    </div>
                    <div class="mt-4 grid gap-2 text-sm text-slate-300">
                        <div>Duur: <span class="text-white">{{ session.duration_label }}</span></div>
                        <div>RFID: <span class="text-white">{{ session.rfid_uid || '—' }}</span></div>
                        <div>Ingecheckt door: <span class="text-white">{{ session.checked_in_by_name || '—' }}</span></div>
                    </div>
                    <div class="mt-4 flex gap-2">
                        <button type="button" class="rounded-lg border border-slate-700 px-3 py-2 text-xs font-medium text-slate-300 transition hover:bg-slate-800" @click="openEditModal(session)">Bewerken</button>
                    </div>
                </div>
            </div>
        </div>

        <div class="grid gap-6 xl:grid-cols-[1.1fr_1.4fr]">
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
                                    <div class="flex items-center gap-2">
                                        <span>{{ item.user_name }}</span>
                                        <span v-if="item.is_active" class="inline-flex rounded-full bg-emerald-500/15 px-2 py-0.5 text-[11px] font-semibold text-emerald-300">Open</span>
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
                <div v-else class="space-y-4 p-5">
                    <div v-for="day in selectedStaffDays" :key="day.date" class="rounded-2xl border border-slate-800 bg-slate-950/60 p-4">
                        <div class="flex flex-wrap items-center justify-between gap-3">
                            <div>
                                <div class="text-base font-semibold text-white">{{ day.date_label }}</div>
                                <div class="mt-1 text-sm text-slate-400">{{ day.first_check_in_label || '—' }} → {{ day.last_check_out_label || '—' }}</div>
                            </div>
                            <div class="rounded-xl bg-blue-500/10 px-3 py-2 text-sm font-semibold text-blue-300">{{ day.total_time_label }}</div>
                        </div>
                        <div class="mt-4 space-y-2">
                            <div v-for="session in day.sessions" :key="`${day.date}-${session.id}-${session.checked_in_at_label}`" class="flex items-center justify-between gap-3 rounded-xl border border-slate-800 bg-slate-900/70 px-3 py-2 text-sm">
                                <div class="text-slate-300">{{ session.checked_in_at_label }} - {{ session.checked_out_at_label }}</div>
                                <div class="font-medium text-white">{{ session.duration_label }}</div>
                            </div>
                        </div>
                    </div>
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
                                <div class="flex items-center gap-2">
                                    <span>{{ session.user_name }}</span>
                                    <span v-if="session.is_active" class="inline-flex rounded-full bg-emerald-500/15 px-2 py-0.5 text-[11px] font-semibold text-emerald-300">Open</span>
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
})

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

onMounted(loadData)
</script>
