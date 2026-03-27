<template>
    <div class="space-y-6">
        <div class="flex flex-wrap items-start justify-between gap-4">
            <div>
                <h1 class="text-3xl font-bold text-white">Product Categories</h1>
                <p class="mt-2 text-slate-400">Beheer hier de productcategorieën. Versleep rijen om de volgorde aan te passen.</p>
            </div>

            <div class="flex gap-3">
                <button type="button" @click="loadCategories" :disabled="loading" class="rounded-xl border border-slate-700 px-4 py-3 text-sm font-medium text-slate-300 transition hover:bg-slate-800 disabled:opacity-60">Vernieuwen</button>
                <button type="button" @click="openCreateModal" class="rounded-xl bg-blue-600 px-4 py-3 text-sm font-semibold text-white transition hover:bg-blue-500">Nieuwe categorie</button>
            </div>
        </div>

        <div v-if="error" class="rounded-2xl border border-red-800 bg-red-950/40 px-4 py-3 text-sm text-red-300">{{ error }}</div>

        <div class="rounded-2xl border border-slate-800 bg-slate-900 shadow-sm">
            <div v-if="loading" class="p-6 text-sm text-slate-400">Laden...</div>
            <div v-else-if="categories.length === 0" class="p-6 text-sm text-slate-400">Nog geen categorieën gevonden.</div>
            <div v-else class="overflow-x-auto">
                <table class="min-w-full text-sm">
                    <thead class="bg-slate-950 text-slate-300">
                        <tr>
                            <th class="px-4 py-3 text-left font-semibold">Volgorde</th>
                            <th class="px-4 py-3 text-left font-semibold">Naam</th>
                            <th class="px-4 py-3 text-left font-semibold">Slug</th>
                            <th class="px-4 py-3 text-left font-semibold">Status</th>
                            <th class="px-4 py-3 text-right font-semibold">Acties</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr
                            v-for="category in categories"
                            :key="category.id"
                            draggable="true"
                            class="border-t border-slate-800 bg-slate-900"
                            :class="draggingId === category.id ? 'opacity-40' : ''"
                            @dragstart="onDragStart(category.id)"
                            @dragover.prevent
                            @drop="onDrop(category.id)"
                        >
                            <td class="px-4 py-3 text-slate-400">↕ {{ category.sort_order }}</td>
                            <td class="px-4 py-3 text-white">{{ category.name }}</td>
                            <td class="px-4 py-3 text-slate-400">{{ category.slug }}</td>
                            <td class="px-4 py-3">
                                <span class="inline-flex rounded-full px-2.5 py-1 text-xs font-semibold" :class="category.is_active ? 'bg-emerald-500/15 text-emerald-300' : 'bg-slate-700 text-slate-300'">{{ category.is_active ? 'Actief' : 'Inactief' }}</span>
                            </td>
                            <td class="px-4 py-3">
                                <div class="flex justify-end gap-2">
                                    <button type="button" @click="openEditModal(category)" class="rounded-lg border border-slate-700 px-3 py-2 text-xs font-medium text-slate-300 transition hover:bg-slate-800">Bewerken</button>
                                    <button type="button" @click="removeCategory(category)" class="rounded-lg border border-red-800 px-3 py-2 text-xs font-medium text-red-300 transition hover:bg-red-950/40">Verwijderen</button>
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <ModalDialog :open="modalOpen" :title="editingId ? 'Categorie bewerken' : 'Nieuwe categorie'" description="Toevoegen en bewerken gebeurt hier via een modal.">
            <form class="space-y-4" @submit.prevent="submit">
                <div>
                    <label class="mb-1 block text-sm font-medium text-slate-300">Naam</label>
                    <input v-model="form.name" type="text" class="w-full rounded-xl border border-slate-700 bg-slate-950 px-4 py-3 text-white outline-none transition focus:border-blue-500" placeholder="Bijvoorbeeld: Dranken" />
                </div>
                <div>
                    <label class="mb-1 block text-sm font-medium text-slate-300">Slug</label>
                    <input v-model="form.slug" type="text" class="w-full rounded-xl border border-slate-700 bg-slate-950 px-4 py-3 text-white outline-none transition focus:border-blue-500" placeholder="Laat leeg om automatisch te genereren" />
                </div>
                <label class="flex items-center gap-3 text-sm text-slate-300">
                    <input v-model="form.is_active" type="checkbox" class="h-4 w-4 rounded border-slate-600 bg-slate-950" />
                    Actief
                </label>
                <div class="flex flex-wrap gap-3 pt-2">
                    <button type="submit" :disabled="saving" class="rounded-xl bg-blue-600 px-4 py-3 text-sm font-semibold text-white transition hover:bg-blue-500 disabled:opacity-60">{{ saving ? 'Opslaan...' : editingId ? 'Categorie opslaan' : 'Categorie toevoegen' }}</button>
                    <button type="button" @click="closeModal" class="rounded-xl border border-slate-700 px-4 py-3 text-sm font-medium text-slate-300 transition hover:bg-slate-800">Annuleren</button>
                </div>
            </form>
        </ModalDialog>
    </div>
</template>

<script setup>
import { onMounted, reactive, ref } from 'vue'
import ModalDialog from '../../../components/ModalDialog.vue'
import { createProductCategory, deleteProductCategory, fetchProductCategories, reorderProductCategories, updateProductCategory } from '../services/productCategoryApi'

const categories = ref([])
const loading = ref(false)
const saving = ref(false)
const error = ref('')
const editingId = ref(null)
const modalOpen = ref(false)
const draggingId = ref(null)

const form = reactive({ name: '', slug: '', is_active: true })

function resetForm() {
    editingId.value = null
    form.name = ''
    form.slug = ''
    form.is_active = true
}

function openCreateModal() {
    resetForm()
    modalOpen.value = true
}

function openEditModal(category) {
    editingId.value = category.id
    form.name = category.name
    form.slug = category.slug
    form.is_active = category.is_active
    modalOpen.value = true
}

function closeModal() {
    modalOpen.value = false
    resetForm()
}

async function loadCategories() {
    loading.value = true
    error.value = ''
    try {
        categories.value = await fetchProductCategories()
    } catch (err) {
        error.value = 'Kon categorieën niet laden.'
        console.error(err)
    } finally {
        loading.value = false
    }
}

async function submit() {
    saving.value = true
    error.value = ''
    try {
        const payload = { name: form.name, slug: form.slug, is_active: form.is_active }
        if (editingId.value) {
            await updateProductCategory(editingId.value, payload)
        } else {
            await createProductCategory(payload)
        }
        closeModal()
        await loadCategories()
    } catch (err) {
        error.value = 'Kon categorie niet opslaan.'
        console.error(err)
    } finally {
        saving.value = false
    }
}

async function removeCategory(category) {
    if (!window.confirm(`Categorie "${category.name}" verwijderen?`)) return
    try {
        await deleteProductCategory(category.id)
        await loadCategories()
    } catch (err) {
        error.value = 'Kon categorie niet verwijderen.'
        console.error(err)
    }
}

function onDragStart(id) {
    draggingId.value = id
}

async function onDrop(targetId) {
    if (!draggingId.value || draggingId.value === targetId) return
    const fromIndex = categories.value.findIndex((item) => item.id === draggingId.value)
    const toIndex = categories.value.findIndex((item) => item.id === targetId)
    const reordered = [...categories.value]
    const [movedItem] = reordered.splice(fromIndex, 1)
    reordered.splice(toIndex, 0, movedItem)
    categories.value = reordered.map((item, index) => ({ ...item, sort_order: index + 1 }))
    const moved = categories.value.map((item) => ({ id: item.id }))
    draggingId.value = null
    try {
        await reorderProductCategories(moved)
        await loadCategories()
    } catch (err) {
        error.value = 'Kon volgorde niet opslaan.'
        console.error(err)
    }
}

onMounted(loadCategories)
</script>
