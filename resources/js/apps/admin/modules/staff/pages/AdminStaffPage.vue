<template>
    <div class="space-y-6">
        <div class="flex flex-wrap items-start justify-between gap-4">
            <div>
                <h1 class="text-3xl font-bold text-white">Medewerkers</h1>
                <p class="mt-2 text-slate-400">Beheer de admin-medewerkers die kunnen inloggen in PlayDrive.</p>
            </div>
        </div>

        <div class="flex gap-3">
            <button
                type="button"
                :disabled="loading"
                class="rounded-xl border border-slate-700 px-4 py-3 text-sm font-medium text-slate-300 transition hover:bg-slate-800 disabled:opacity-60"
                @click="loadStaff"
            >
                Vernieuwen
            </button>
            <button
                type="button"
                class="rounded-xl bg-cyan-600 px-4 py-3 text-sm font-semibold text-white transition hover:bg-cyan-500"
                @click="openCreateModal"
            >
                Nieuwe medewerker
            </button>
        </div>

        <div v-if="error" class="rounded-2xl border border-red-800 bg-red-950/40 px-4 py-3 text-sm text-red-300">
            {{ error }}
        </div>

        <div class="rounded-2xl border border-slate-800 bg-slate-900">
            <div v-if="loading" class="p-6 text-sm text-slate-400">Laden...</div>
            <div v-else-if="staff.length === 0" class="p-6 text-sm text-slate-400">Nog geen medewerkers gevonden.</div>
            <div v-else class="overflow-x-auto">
                <table class="min-w-full text-sm">
                    <thead class="bg-slate-950 text-slate-300">
                        <tr>
                            <th class="px-4 py-3 text-left font-semibold">Naam</th>
                            <th class="px-4 py-3 text-left font-semibold">Gebruikersnaam</th>
                            <th class="px-4 py-3 text-left font-semibold">E-mail</th>
                            <th class="px-4 py-3 text-left font-semibold">Status</th>
                            <th class="px-4 py-3 text-right font-semibold">Acties</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="member in staff" :key="member.id" class="border-t border-slate-800">
                            <td class="px-4 py-3 font-medium text-white">{{ member.name }}</td>
                            <td class="px-4 py-3 text-slate-400">{{ member.username }}</td>
                            <td class="px-4 py-3 text-slate-400">{{ member.email || '—' }}</td>
                            <td class="px-4 py-3">
                                <span
                                    class="inline-flex rounded-full px-2.5 py-1 text-xs font-semibold"
                                    :class="member.is_active ? 'bg-emerald-500/15 text-emerald-300' : 'bg-slate-700 text-slate-300'"
                                >
                                    {{ member.is_active ? 'Actief' : 'Inactief' }}
                                </span>
                            </td>
                            <td class="px-4 py-3 text-right">
                                <div class="flex justify-end gap-2">
                                    <button
                                        type="button"
                                        class="rounded-xl border border-slate-700 px-3 py-2 text-xs font-medium text-slate-300 transition hover:bg-slate-800"
                                        @click="openEditModal(member)"
                                    >
                                        Bewerken
                                    </button>
                                    <button
                                        type="button"
                                        class="rounded-xl border border-rose-500/30 bg-rose-500/10 px-3 py-2 text-xs font-semibold text-rose-200 transition hover:bg-rose-500/20"
                                        @click="handleDelete(member)"
                                    >
                                        Verwijderen
                                    </button>
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <AdminStaffFormModal
            :open="modalOpen"
            :staff="editingStaff"
            :saving="saving"
            :error="modalError"
            @close="closeModal"
            @submit="handleSubmit"
        />
    </div>
</template>

<script setup>
import { onMounted, ref } from 'vue'
import AdminStaffFormModal from '../components/AdminStaffFormModal.vue'
import { createAdminStaff, deleteAdminStaff, fetchAdminStaff, updateAdminStaff } from '../services/adminStaffApi'

const staff = ref([])
const loading = ref(false)
const saving = ref(false)
const error = ref('')
const modalError = ref('')
const modalOpen = ref(false)
const editingStaff = ref(null)

async function loadStaff() {
    loading.value = true
    error.value = ''
    try {
        const response = await fetchAdminStaff()
        staff.value = response.staff ?? response ?? []
    } catch (err) {
        error.value = 'Kon medewerkers niet laden.'
    } finally {
        loading.value = false
    }
}

function openCreateModal() {
    editingStaff.value = null
    modalError.value = ''
    modalOpen.value = true
}

function openEditModal(member) {
    editingStaff.value = member
    modalError.value = ''
    modalOpen.value = true
}

function closeModal() {
    modalOpen.value = false
    editingStaff.value = null
    modalError.value = ''
}

async function handleSubmit(payload) {
    saving.value = true
    modalError.value = ''
    try {
        if (editingStaff.value?.id) {
            await updateAdminStaff(editingStaff.value.id, payload)
        } else {
            await createAdminStaff(payload)
        }
        closeModal()
        await loadStaff()
    } catch (err) {
        modalError.value = err?.data?.message || 'Opslaan mislukt.'
    } finally {
        saving.value = false
    }
}

async function handleDelete(member) {
    if (!confirm(`Medewerker "${member.name}" verwijderen?`)) return
    try {
        await deleteAdminStaff(member.id)
        await loadStaff()
    } catch (err) {
        error.value = 'Verwijderen mislukt.'
    }
}

onMounted(loadStaff)
</script>
