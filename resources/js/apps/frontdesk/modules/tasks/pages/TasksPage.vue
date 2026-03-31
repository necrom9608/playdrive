<template>
    <div class="flex h-full min-h-0 flex-col gap-6">
        <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-4">
            <article v-for="card in cards" :key="card.label" class="rounded-3xl border p-5 shadow-xl" :class="card.class">
                <p class="text-sm font-medium" :class="card.labelClass">{{ card.label }}</p>
                <p class="mt-3 text-3xl font-semibold" :class="card.valueClass">{{ card.value }}</p>
            </article>
        </div>

        <div class="rounded-3xl border border-slate-800 bg-slate-900 p-4 shadow-xl">
            <div class="flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
                <div class="grid flex-1 gap-4 md:grid-cols-3">
                    <label class="space-y-2 text-sm text-slate-300">
                        <span>Zoeken</span>
                        <input v-model="store.search" class="w-full rounded-2xl border border-slate-700 bg-slate-950 px-4 py-3 text-white outline-none" placeholder="Titel of omschrijving" @keyup.enter="store.fetchTasks()">
                    </label>

                    <label class="space-y-2 text-sm text-slate-300">
                        <span>Medewerker</span>
                        <select v-model="store.assignedUserId" class="w-full rounded-2xl border border-slate-700 bg-slate-950 px-4 py-3 text-white outline-none">
                            <option value="">Iedereen</option>
                            <option value="-1">Algemene taken</option>
                            <option v-for="user in store.staff" :key="user.id" :value="String(user.id)">{{ user.name }}</option>
                        </select>
                    </label>

                    <div class="space-y-2 text-sm text-slate-300">
                        <span>Status</span>
                        <div class="flex flex-wrap gap-2 rounded-2xl border border-slate-700 bg-slate-950 p-2">
                            <button v-for="option in statusOptions" :key="option.value" type="button" class="rounded-full px-3 py-2 text-xs font-semibold transition" :class="store.statuses.includes(option.value) ? option.activeClass : 'bg-slate-800 text-slate-300'" @click="toggleStatus(option.value)">{{ option.label }}</button>
                        </div>
                    </div>
                </div>

                <div class="flex gap-3">
                    <button type="button" class="rounded-2xl border border-slate-700 bg-slate-800 px-4 py-3 text-sm font-semibold text-slate-200" @click="store.fetchTasks()">Zoeken</button>
                    <button type="button" class="rounded-2xl bg-blue-600 px-4 py-3 text-sm font-semibold text-white" @click="openCreate">Nieuwe taak</button>
                </div>
            </div>
        </div>

        <div v-if="store.error" class="rounded-3xl border border-rose-500/30 bg-rose-500/10 px-5 py-4 text-sm text-rose-200">{{ store.error }}</div>

        <div class="flex h-full min-h-0 flex-col rounded-3xl border border-slate-800 bg-slate-900 shadow-xl">
            <div class="min-h-0 flex-1 overflow-auto">
                <table class="w-full min-w-[1100px] text-sm">
                    <thead class="sticky top-0 bg-slate-900 text-slate-400">
                        <tr>
                            <th class="px-5 py-3 text-left font-medium">Taak</th>
                            <th class="px-5 py-3 text-left font-medium">Type</th>
                            <th class="px-5 py-3 text-left font-medium">Moment</th>
                            <th class="px-5 py-3 text-left font-medium">Toegewezen aan</th>
                            <th class="px-5 py-3 text-left font-medium">Ingepland door</th>
                            <th class="px-5 py-3 text-left font-medium">Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-if="!store.tasks.length"><td colspan="6" class="px-5 py-10 text-center text-slate-500">Geen taken gevonden.</td></tr>
                        <tr v-for="task in store.tasks" :key="task.id" class="cursor-pointer border-t border-slate-800 transition hover:bg-slate-800/30" :class="task.id === store.selectedTaskId ? 'bg-slate-800/60' : ''" @click="store.selectedTaskId = task.id">
                            <td class="px-5 py-4 align-top">
                                <div class="font-semibold text-white">{{ task.title }}</div>
                                <div class="mt-1 text-xs text-slate-500">{{ task.description || 'Geen omschrijving' }}</div>
                            </td>
                            <td class="px-5 py-4 align-top text-slate-200">{{ task.task_type_label }}<div class="mt-1 text-xs text-slate-500">{{ task.recurrence_label || '—' }}</div></td>
                            <td class="px-5 py-4 align-top text-slate-200">{{ taskMoment(task) }}</td>
                            <td class="px-5 py-4 align-top text-slate-200">{{ task.assigned_user_name || 'Algemeen' }}</td>
                            <td class="px-5 py-4 align-top text-slate-200">{{ task.scheduled_by || 'Onbekend' }}</td>
                            <td class="px-5 py-4 align-top"><span class="inline-flex rounded-full px-3 py-1 text-xs font-semibold" :class="statusClass(task.status)">{{ task.status_label }}</span></td>
                        </tr>
                    </tbody>
                </table>
            </div>
            <div class="border-t border-slate-800 px-5 py-4">
                <div class="flex flex-wrap items-center justify-between gap-3">
                    <div class="text-sm text-slate-400"><span v-if="store.selectedTask">Geselecteerd: <span class="font-semibold text-white">{{ store.selectedTask.title }}</span></span><span v-else>Geen taak geselecteerd.</span></div>
                    <div class="flex gap-3">
                        <button type="button" class="rounded-2xl border border-slate-700 bg-slate-800 px-4 py-3 text-sm font-semibold text-slate-200 disabled:opacity-50" :disabled="!store.selectedTask" @click="openEdit">Bewerken</button>
                        <button type="button" class="rounded-2xl bg-emerald-600 px-4 py-3 text-sm font-semibold text-white disabled:opacity-50" :disabled="!store.selectedTask" @click="quickStatus('completed')">Markeer afgewerkt</button>
                        <button type="button" class="rounded-2xl border border-slate-700 bg-slate-800 px-4 py-3 text-sm font-semibold text-slate-200 disabled:opacity-50" :disabled="!store.selectedTask" @click="quickStatus('open')">Heropenen</button>
                    </div>
                </div>
            </div>
        </div>

        <div v-if="showModal" class="fixed inset-0 z-50 flex items-center justify-center bg-slate-950/80 p-4" @click.self="closeModal">
            <form class="w-full max-w-3xl rounded-3xl border border-slate-800 bg-slate-900 p-6 shadow-2xl" @submit.prevent="submit">
                <div class="flex items-start justify-between gap-4">
                    <div>
                        <h2 class="text-xl font-semibold text-white">{{ form.id ? 'Taak bewerken' : 'Nieuwe taak' }}</h2>
                        <p class="mt-1 text-sm text-slate-400">Algemene taken of taken voor een specifieke medewerker.</p>
                    </div>
                    <button type="button" class="rounded-xl border border-slate-700 bg-slate-800 px-3 py-2 text-sm text-slate-200" @click="closeModal">Sluiten</button>
                </div>
                <div class="mt-6 grid gap-4 md:grid-cols-2">
                    <label class="space-y-2 text-sm text-slate-300 md:col-span-2"><span>Titel</span><input v-model="form.title" class="w-full rounded-2xl border border-slate-700 bg-slate-950 px-4 py-3 text-white"></label>
                    <label class="space-y-2 text-sm text-slate-300"><span>Type</span><select v-model="form.task_type" class="w-full rounded-2xl border border-slate-700 bg-slate-950 px-4 py-3 text-white"><option value="single">Eenmalig</option><option value="recurring">Herhalend</option></select></label>
                    <label class="space-y-2 text-sm text-slate-300"><span>Status</span><select v-model="form.status" class="w-full rounded-2xl border border-slate-700 bg-slate-950 px-4 py-3 text-white"><option value="open">Open</option><option value="completed">Afgewerkt</option><option value="cancelled">Geannuleerd</option></select></label>
                    <label class="space-y-2 text-sm text-slate-300"><span>Toegewezen aan</span><select v-model="form.assigned_user_id" class="w-full rounded-2xl border border-slate-700 bg-slate-950 px-4 py-3 text-white"><option :value="null">Algemeen</option><option v-for="user in store.staff" :key="user.id" :value="user.id">{{ user.name }}</option></select></label>
                    <label v-if="form.task_type === 'single'" class="space-y-2 text-sm text-slate-300"><span>Dag</span><input v-model="form.due_date" type="date" class="w-full rounded-2xl border border-slate-700 bg-slate-950 px-4 py-3 text-white"></label>
                    <template v-else>
                        <label class="space-y-2 text-sm text-slate-300"><span>Herhaling</span><select v-model="form.recurrence_pattern" class="w-full rounded-2xl border border-slate-700 bg-slate-950 px-4 py-3 text-white"><option value="daily">Dagelijks</option><option value="weekly">Wekelijks</option><option value="monthly">Maandelijks</option></select></label>
                        <label class="space-y-2 text-sm text-slate-300"><span>Van</span><input v-model="form.start_date" type="date" class="w-full rounded-2xl border border-slate-700 bg-slate-950 px-4 py-3 text-white"></label>
                        <label class="space-y-2 text-sm text-slate-300"><span>Tot</span><input v-model="form.end_date" type="date" class="w-full rounded-2xl border border-slate-700 bg-slate-950 px-4 py-3 text-white"></label>
                    </template>
                    <label class="space-y-2 text-sm text-slate-300 md:col-span-2"><span>Omschrijving</span><textarea v-model="form.description" rows="4" class="w-full rounded-2xl border border-slate-700 bg-slate-950 px-4 py-3 text-white"></textarea></label>
                </div>
                <div class="mt-6 flex justify-end gap-3"><button type="button" class="rounded-2xl border border-slate-700 bg-slate-800 px-4 py-3 text-sm font-semibold text-slate-200" @click="closeModal">Annuleren</button><button type="submit" class="rounded-2xl bg-blue-600 px-4 py-3 text-sm font-semibold text-white">{{ store.saving ? 'Opslaan...' : 'Opslaan' }}</button></div>
            </form>
        </div>
    </div>
</template>

<script setup>
import { computed, onMounted, reactive, ref } from 'vue'
import { useTasksStore } from '../stores/useTasksStore'

const store = useTasksStore()
const showModal = ref(false)
const form = reactive(emptyForm())

const statusOptions = [
    { value: 'open', label: 'Open', activeClass: 'bg-pink-500/15 text-pink-300' },
    { value: 'completed', label: 'Afgewerkt', activeClass: 'bg-emerald-500/15 text-emerald-300' },
    { value: 'cancelled', label: 'Geannuleerd', activeClass: 'bg-slate-500/15 text-slate-300' },
]

const cards = computed(() => [
    { label: 'Totaal', value: store.summary.total ?? 0, class: 'border-slate-800 bg-slate-900', labelClass: 'text-slate-400', valueClass: 'text-white' },
    { label: 'Open', value: store.summary.open ?? 0, class: 'border-pink-500/20 bg-pink-500/10', labelClass: 'text-pink-300', valueClass: 'text-pink-200' },
    { label: 'Afgewerkt', value: store.summary.completed ?? 0, class: 'border-emerald-500/20 bg-emerald-500/10', labelClass: 'text-emerald-300', valueClass: 'text-emerald-200' },
    { label: 'Geannuleerd', value: store.summary.cancelled ?? 0, class: 'border-slate-500/20 bg-slate-500/10', labelClass: 'text-slate-300', valueClass: 'text-slate-200' },
])

onMounted(() => store.fetchTasks())

function emptyForm() {
    const today = new Date().toISOString().slice(0, 10)
    return { id: null, title: '', description: '', status: 'open', task_type: 'single', recurrence_pattern: 'weekly', due_date: today, start_date: today, end_date: '', assigned_user_id: null }
}

function openCreate() {
    Object.assign(form, emptyForm())
    showModal.value = true
}

function openEdit() {
    if (!store.selectedTask) return
    Object.assign(form, { ...emptyForm(), ...store.selectedTask })
    showModal.value = true
}

function closeModal() { showModal.value = false }

async function submit() {
    await store.saveTask({ ...form, assigned_user_id: form.assigned_user_id || null })
    closeModal()
}

async function quickStatus(status) {
    if (!store.selectedTask) return
    await store.saveTask({ ...store.selectedTask, status })
}

function toggleStatus(value) {
    store.statuses = store.statuses.includes(value) ? store.statuses.filter(item => item !== value) : [...store.statuses, value]
}

function taskMoment(task) {
    if (task.task_type === 'recurring') {
        return `${task.start_date_label || task.start_date || '—'} → ${task.end_date_label || task.end_date || 'zonder einddatum'}`
    }
    return task.due_date_label || task.due_date || '—'
}

function statusClass(status) {
    return {
        open: 'bg-pink-500/15 text-pink-300',
        completed: 'bg-emerald-500/15 text-emerald-300',
        cancelled: 'bg-slate-500/15 text-slate-300',
    }[status] ?? 'bg-slate-500/15 text-slate-300'
}
</script>
