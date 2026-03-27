<template>
    <div class="space-y-6">
        <div class="flex flex-wrap items-start justify-between gap-4">
            <div>
                <h1 class="text-3xl font-bold text-white">Staff</h1>
                <p class="mt-2 text-slate-400">Beperk de gegevens tot naam, login, paswoord, e-mail en adresgegevens. Volgorde kan ook hier met drag and drop.</p>
            </div>
            <div class="flex gap-3">
                <button type="button" @click="loadStaff" :disabled="loading" class="rounded-xl border border-slate-700 px-4 py-3 text-sm font-medium text-slate-300 transition hover:bg-slate-800 disabled:opacity-60">Vernieuwen</button>
                <button type="button" @click="openCreateModal" class="rounded-xl bg-blue-600 px-4 py-3 text-sm font-semibold text-white transition hover:bg-blue-500">Nieuwe medewerker</button>
            </div>
        </div>

        <div v-if="error" class="rounded-2xl border border-red-800 bg-red-950/40 px-4 py-3 text-sm text-red-300">{{ error }}</div>

        <div class="rounded-2xl border border-slate-800 bg-slate-900 shadow-sm">
            <div v-if="loading" class="p-6 text-sm text-slate-400">Laden...</div>
            <div v-else-if="staff.length === 0" class="p-6 text-sm text-slate-400">Nog geen medewerkers gevonden.</div>
            <div v-else class="overflow-x-auto">
                <table class="min-w-full text-sm">
                    <thead class="bg-slate-950 text-slate-300">
                    <tr>
                        <th class="px-4 py-3 text-left font-semibold">Volgorde</th>
                        <th class="px-4 py-3 text-left font-semibold">Naam</th>
                        <th class="px-4 py-3 text-left font-semibold">Login</th>
                        <th class="px-4 py-3 text-left font-semibold">E-mail</th>
                        <th class="px-4 py-3 text-left font-semibold">Adres</th>
                        <th class="px-4 py-3 text-left font-semibold">Status</th>
                        <th class="px-4 py-3 text-right font-semibold">Acties</th>
                    </tr>
                    </thead>
                    <tbody>
                    <tr
                        v-for="member in staff"
                        :key="member.id"
                        draggable="true"
                        class="border-t border-slate-800 bg-slate-900"
                        :class="draggingId === member.id ? 'opacity-40' : ''"
                        @dragstart="onDragStart(member.id)"
                        @dragover.prevent
                        @drop="onDrop(member.id)"
                    >
                        <td class="px-4 py-3 text-slate-400">↕ {{ member.sort_order }}</td>
                        <td class="px-4 py-3 text-white">{{ member.name }}</td>
                        <td class="px-4 py-3 text-slate-400">{{ member.username }}</td>
                        <td class="px-4 py-3 text-slate-400">{{ member.email }}</td>
                        <td class="px-4 py-3 text-slate-400">{{ member.full_address || '—' }}</td>
                        <td class="px-4 py-3"><span class="inline-flex rounded-full px-2.5 py-1 text-xs font-semibold" :class="member.is_active ? 'bg-emerald-500/15 text-emerald-300' : 'bg-slate-700 text-slate-300'">{{ member.is_active ? 'Actief' : 'Inactief' }}</span></td>
                        <td class="px-4 py-3">
                            <div class="flex justify-end gap-2">
                                <button type="button" @click="openEditModal(member)" class="rounded-lg border border-slate-700 px-3 py-2 text-xs font-medium text-slate-300 transition hover:bg-slate-800">Bewerken</button>
                                <button type="button" @click="removeStaff(member)" class="rounded-lg border border-red-800 px-3 py-2 text-xs font-medium text-red-300 transition hover:bg-red-950/40">Verwijderen</button>
                            </div>
                        </td>
                    </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <ModalDialog :open="modalOpen" :title="editingId ? 'Medewerker bewerken' : 'Nieuwe medewerker'" description="Staff toevoegen en bewerken gebeurt hier via een modal.">
            <form class="space-y-4" @submit.prevent="submit">
                <div class="grid gap-4 md:grid-cols-2">
                    <div>
                        <label class="mb-1 block text-sm font-medium text-slate-300">Naam</label>
                        <input v-model="form.name" type="text" class="w-full rounded-xl border border-slate-700 bg-slate-950 px-4 py-3 text-white outline-none transition focus:border-blue-500" />
                    </div>
                    <div>
                        <label class="mb-1 block text-sm font-medium text-slate-300">Login</label>
                        <input v-model="form.username" type="text" class="w-full rounded-xl border border-slate-700 bg-slate-950 px-4 py-3 text-white outline-none transition focus:border-blue-500" />
                    </div>
                </div>
                <div class="grid gap-4 md:grid-cols-2">
                    <div>
                        <label class="mb-1 block text-sm font-medium text-slate-300">E-mail</label>
                        <input v-model="form.email" type="email" class="w-full rounded-xl border border-slate-700 bg-slate-950 px-4 py-3 text-white outline-none transition focus:border-blue-500" />
                    </div>
                    <div>
                        <label class="mb-1 block text-sm font-medium text-slate-300">Paswoord</label>
                        <input v-model="form.password" type="password" class="w-full rounded-xl border border-slate-700 bg-slate-950 px-4 py-3 text-white outline-none transition focus:border-blue-500" :placeholder="editingId ? 'Leeg laten om niet te wijzigen' : ''" />
                    </div>
                </div>
                <div class="grid gap-4 md:grid-cols-3">
                    <div class="md:col-span-2">
                        <label class="mb-1 block text-sm font-medium text-slate-300">Straat</label>
                        <input v-model="form.street" type="text" class="w-full rounded-xl border border-slate-700 bg-slate-950 px-4 py-3 text-white outline-none transition focus:border-blue-500" />
                    </div>
                    <div>
                        <label class="mb-1 block text-sm font-medium text-slate-300">Nummer</label>
                        <input v-model="form.house_number" type="text" class="w-full rounded-xl border border-slate-700 bg-slate-950 px-4 py-3 text-white outline-none transition focus:border-blue-500" />
                    </div>
                </div>
                <div class="grid gap-4 md:grid-cols-3">
                    <div>
                        <label class="mb-1 block text-sm font-medium text-slate-300">Bus</label>
                        <input v-model="form.bus" type="text" class="w-full rounded-xl border border-slate-700 bg-slate-950 px-4 py-3 text-white outline-none transition focus:border-blue-500" />
                    </div>
                    <div>
                        <label class="mb-1 block text-sm font-medium text-slate-300">Postcode</label>
                        <input v-model="form.postal_code" type="text" class="w-full rounded-xl border border-slate-700 bg-slate-950 px-4 py-3 text-white outline-none transition focus:border-blue-500" />
                    </div>
                    <div>
                        <label class="mb-1 block text-sm font-medium text-slate-300">Gemeente</label>
                        <input v-model="form.city" type="text" class="w-full rounded-xl border border-slate-700 bg-slate-950 px-4 py-3 text-white outline-none transition focus:border-blue-500" />
                    </div>
                </div>
                <label class="flex items-center gap-3 text-sm text-slate-300">
                    <input v-model="form.is_active" type="checkbox" class="h-4 w-4 rounded border-slate-600 bg-slate-950" />
                    Actief
                </label>
                <div class="flex flex-wrap gap-3 pt-2">
                    <button type="submit" :disabled="saving" class="rounded-xl bg-blue-600 px-4 py-3 text-sm font-semibold text-white transition hover:bg-blue-500 disabled:opacity-60">{{ saving ? 'Opslaan...' : editingId ? 'Medewerker opslaan' : 'Medewerker toevoegen' }}</button>
                    <button type="button" @click="closeModal" class="rounded-xl border border-slate-700 px-4 py-3 text-sm font-medium text-slate-300 transition hover:bg-slate-800">Annuleren</button>
                </div>
            </form>
        </ModalDialog>
    </div>
</template>

<script setup>
import { onMounted, reactive, ref } from 'vue'
import ModalDialog from '../../../components/ModalDialog.vue'
import { createStaff, deleteStaff, fetchStaff, reorderStaff, updateStaff } from '../services/staffApi'

const staff = ref([])
const loading = ref(false)
const saving = ref(false)
const error = ref('')
const modalOpen = ref(false)
const editingId = ref(null)
const draggingId = ref(null)

const form = reactive({
    name: '',
    username: '',
    email: '',
    password: '',
    street: '',
    house_number: '',
    bus: '',
    postal_code: '',
    city: '',
    is_active: true,
})

function resetForm() {
    editingId.value = null
    form.name = ''
    form.username = ''
    form.email = ''
    form.password = ''
    form.street = ''
    form.house_number = ''
    form.bus = ''
    form.postal_code = ''
    form.city = ''
    form.is_active = true
}

function openCreateModal() {
    resetForm()
    modalOpen.value = true
}

function openEditModal(member) {
    editingId.value = member.id
    form.name = member.name
    form.username = member.username
    form.email = member.email
    form.password = ''
    form.street = member.street ?? ''
    form.house_number = member.house_number ?? ''
    form.bus = member.bus ?? ''
    form.postal_code = member.postal_code ?? ''
    form.city = member.city ?? ''
    form.is_active = member.is_active
    modalOpen.value = true
}

function closeModal() {
    modalOpen.value = false
    resetForm()
}

async function loadStaff() {
    loading.value = true
    error.value = ''
    try {
        staff.value = await fetchStaff()
    } catch (err) {
        error.value = 'Kon medewerkers niet laden.'
        console.error(err)
    } finally {
        loading.value = false
    }
}

async function submit() {
    saving.value = true
    error.value = ''

    try {
        const payload = { ...form }

        if (editingId.value) {
            await updateStaff(editingId.value, payload)
        } else {
            await createStaff(payload)
        }

        closeModal()
        await loadStaff()
    } catch (err) {
        console.error(err)

        if (err?.status === 422 && err?.data?.errors) {
            const firstError = Object.values(err.data.errors)?.[0]?.[0]
            error.value = firstError || 'Validatiefout bij opslaan van medewerker.'
        } else if (err?.data?.message) {
            error.value = err.data.message
        } else if (err?.message) {
            error.value = err.message
        } else {
            error.value = 'Kon medewerker niet opslaan.'
        }
    } finally {
        saving.value = false
    }
}


async function removeStaff(member) {
    if (!window.confirm(`Medewerker "${member.name}" verwijderen?`)) return
    try {
        await deleteStaff(member.id)
        await loadStaff()
    } catch (err) {
        error.value = 'Kon medewerker niet verwijderen.'
        console.error(err)
    }
}

function onDragStart(id) {
    draggingId.value = id
}

async function onDrop(targetId) {
    if (!draggingId.value || draggingId.value === targetId) return
    const fromIndex = staff.value.findIndex((item) => item.id === draggingId.value)
    const toIndex = staff.value.findIndex((item) => item.id === targetId)
    const reordered = [...staff.value]
    const [movedItem] = reordered.splice(fromIndex, 1)
    reordered.splice(toIndex, 0, movedItem)
    staff.value = reordered.map((item, index) => ({ ...item, sort_order: index + 1 }))
    draggingId.value = null
    try {
        await reorderStaff(staff.value.map((item) => ({ id: item.id })))
        await loadStaff()
    } catch (err) {
        error.value = 'Kon volgorde niet opslaan.'
        console.error(err)
    }
}

onMounted(loadStaff)
</script>
