<template>
    <div class="space-y-6">
        <div class="flex flex-wrap items-start justify-between gap-4">
            <div>
                <h1 class="text-3xl font-bold text-white">Staff</h1>
                <p class="mt-2 text-slate-400">
                    Beheer medewerkers, logingegevens, adresgegevens en gekoppelde RFID-kaarten.
                </p>
            </div>
        </div>

        <StaffTable
            :staff="staff"
            :loading="loading"
            :error="error"
            @refresh="loadStaff"
            @create="openCreateModal"
            @edit="openEditModal"
            @delete="handleDelete"
            @reorder="handleReorder"
        />

        <StaffFormModal
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
import StaffTable from '../components/StaffTable.vue'
import StaffFormModal from '../components/StaffFormModal.vue'
import { createStaff, deleteStaff, fetchStaff, reorderStaff, updateStaff } from '../services/staffApi'

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
        staff.value = await fetchStaff()
    } catch (err) {
        console.error(err)
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
            await updateStaff(editingStaff.value.id, payload)
        } else {
            await createStaff(payload)
        }

        closeModal()
        await loadStaff()
    } catch (err) {
        console.error(err)

        if (err?.status === 422 && err?.data?.errors) {
            const firstError = Object.values(err.data.errors)?.[0]?.[0]
            modalError.value = firstError || 'Validatiefout bij opslaan van medewerker.'
        } else if (err?.data?.message) {
            modalError.value = err.data.message
        } else if (err?.message) {
            modalError.value = err.message
        } else {
            modalError.value = 'Kon medewerker niet opslaan.'
        }
    } finally {
        saving.value = false
    }
}

async function handleDelete(member) {
    if (!window.confirm(`Medewerker "${member.name}" verwijderen?`)) {
        return
    }

    error.value = ''

    try {
        await deleteStaff(member.id)
        await loadStaff()
    } catch (err) {
        console.error(err)
        error.value = 'Kon medewerker niet verwijderen.'
    }
}

async function handleReorder(items) {
    error.value = ''

    try {
        await reorderStaff(items.map((item) => ({ id: item.id })))
        await loadStaff()
    } catch (err) {
        console.error(err)
        error.value = 'Kon volgorde niet opslaan.'
    }
}

onMounted(loadStaff)
</script>
