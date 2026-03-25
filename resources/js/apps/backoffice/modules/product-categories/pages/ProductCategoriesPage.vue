<template>
    <div class="space-y-6">
        <div>
            <h1 class="text-3xl font-bold text-white">Product Categories</h1>
            <p class="mt-2 text-slate-400">
                Beheer hier de productcategorieën van deze tenant.
            </p>
        </div>

        <div class="grid gap-6 lg:grid-cols-[420px,1fr]">
            <div class="rounded-2xl border border-slate-800 bg-slate-900 p-5 shadow-sm">
                <h2 class="text-lg font-semibold text-white">
                    {{ editingId ? 'Categorie bewerken' : 'Nieuwe categorie' }}
                </h2>

                <form class="mt-4 space-y-4" @submit.prevent="submit">
                    <div>
                        <label class="mb-1 block text-sm font-medium text-slate-300">Naam</label>
                        <input
                            v-model="form.name"
                            type="text"
                            class="w-full rounded-xl border border-slate-700 bg-slate-950 px-4 py-3 text-white outline-none transition focus:border-blue-500"
                            placeholder="Bijvoorbeeld: Dranken"
                        />
                    </div>

                    <div>
                        <label class="mb-1 block text-sm font-medium text-slate-300">Slug</label>
                        <input
                            v-model="form.slug"
                            type="text"
                            class="w-full rounded-xl border border-slate-700 bg-slate-950 px-4 py-3 text-white outline-none transition focus:border-blue-500"
                            placeholder="Laat leeg om automatisch te genereren"
                        />
                    </div>

                    <div>
                        <label class="mb-1 block text-sm font-medium text-slate-300">Sort order</label>
                        <input
                            v-model.number="form.sort_order"
                            type="number"
                            class="w-full rounded-xl border border-slate-700 bg-slate-950 px-4 py-3 text-white outline-none transition focus:border-blue-500"
                        />
                    </div>

                    <label class="flex items-center gap-3 text-sm text-slate-300">
                        <input v-model="form.is_active" type="checkbox" class="h-4 w-4 rounded border-slate-600 bg-slate-950" />
                        Actief
                    </label>

                    <div v-if="error" class="rounded-xl border border-red-800 bg-red-950/40 px-4 py-3 text-sm text-red-300">
                        {{ error }}
                    </div>

                    <div class="flex flex-wrap gap-3">
                        <button
                            type="submit"
                            :disabled="saving"
                            class="rounded-xl bg-blue-600 px-4 py-3 text-sm font-semibold text-white transition hover:bg-blue-500 disabled:cursor-not-allowed disabled:opacity-60"
                        >
                            {{ saving ? 'Opslaan...' : editingId ? 'Categorie opslaan' : 'Categorie toevoegen' }}
                        </button>

                        <button
                            v-if="editingId"
                            type="button"
                            @click="resetForm"
                            class="rounded-xl border border-slate-700 px-4 py-3 text-sm font-medium text-slate-300 transition hover:bg-slate-800"
                        >
                            Annuleren
                        </button>
                    </div>
                </form>
            </div>

            <div class="rounded-2xl border border-slate-800 bg-slate-900 p-5 shadow-sm">
                <div class="flex items-center justify-between gap-4">
                    <h2 class="text-lg font-semibold text-white">Categorieën</h2>

                    <button
                        type="button"
                        @click="loadCategories"
                        :disabled="loading"
                        class="rounded-xl border border-slate-700 px-4 py-2 text-sm font-medium text-slate-300 transition hover:bg-slate-800 disabled:opacity-60"
                    >
                        Vernieuwen
                    </button>
                </div>

                <div v-if="loading" class="mt-4 text-sm text-slate-400">
                    Laden...
                </div>

                <div v-else-if="categories.length === 0" class="mt-4 rounded-xl border border-slate-800 bg-slate-950 px-4 py-6 text-sm text-slate-400">
                    Nog geen categorieën gevonden.
                </div>

                <div v-else class="mt-4 overflow-hidden rounded-2xl border border-slate-800">
                    <table class="min-w-full divide-y divide-slate-800 text-sm">
                        <thead class="bg-slate-950">
                        <tr>
                            <th class="px-4 py-3 text-left font-semibold text-slate-300">Naam</th>
                            <th class="px-4 py-3 text-left font-semibold text-slate-300">Slug</th>
                            <th class="px-4 py-3 text-left font-semibold text-slate-300">Sort</th>
                            <th class="px-4 py-3 text-left font-semibold text-slate-300">Status</th>
                            <th class="px-4 py-3 text-right font-semibold text-slate-300">Acties</th>
                        </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-800">
                        <tr v-for="category in categories" :key="category.id" class="bg-slate-900">
                            <td class="px-4 py-3 text-white">{{ category.name }}</td>
                            <td class="px-4 py-3 text-slate-400">{{ category.slug }}</td>
                            <td class="px-4 py-3 text-slate-400">{{ category.sort_order }}</td>
                            <td class="px-4 py-3">
                                    <span
                                        class="inline-flex rounded-full px-2.5 py-1 text-xs font-semibold"
                                        :class="category.is_active
                                            ? 'bg-emerald-500/15 text-emerald-300'
                                            : 'bg-slate-700 text-slate-300'"
                                    >
                                        {{ category.is_active ? 'Actief' : 'Inactief' }}
                                    </span>
                            </td>
                            <td class="px-4 py-3">
                                <div class="flex justify-end gap-2">
                                    <button
                                        type="button"
                                        @click="editCategory(category)"
                                        class="rounded-lg border border-slate-700 px-3 py-2 text-xs font-medium text-slate-300 transition hover:bg-slate-800"
                                    >
                                        Bewerken
                                    </button>

                                    <button
                                        type="button"
                                        @click="removeCategory(category)"
                                        class="rounded-lg border border-red-800 px-3 py-2 text-xs font-medium text-red-300 transition hover:bg-red-950/40"
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
        </div>
    </div>
</template>

<script setup>
import { onMounted, reactive, ref } from 'vue'
import {
    createProductCategory,
    deleteProductCategory,
    fetchProductCategories,
    updateProductCategory,
} from '../services/productCategoryApi'

const categories = ref([])
const loading = ref(false)
const saving = ref(false)
const error = ref('')
const editingId = ref(null)

const form = reactive({
    name: '',
    slug: '',
    sort_order: 0,
    is_active: true,
})

function resetForm() {
    editingId.value = null
    form.name = ''
    form.slug = ''
    form.sort_order = 0
    form.is_active = true
    error.value = ''
}

function editCategory(category) {
    editingId.value = category.id
    form.name = category.name
    form.slug = category.slug
    form.sort_order = category.sort_order
    form.is_active = category.is_active
    error.value = ''
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
        const payload = {
            name: form.name,
            slug: form.slug,
            sort_order: form.sort_order,
            is_active: form.is_active,
        }

        if (editingId.value) {
            await updateProductCategory(editingId.value, payload)
        } else {
            await createProductCategory(payload)
        }

        resetForm()
        await loadCategories()
    } catch (err) {
        error.value = err.message
        console.error(err)
    } finally {
        saving.value = false
    }
}

async function removeCategory(category) {
    const confirmed = window.confirm(`Categorie "${category.name}" verwijderen?`)

    if (!confirmed) {
        return
    }

    try {
        await deleteProductCategory(category.id)

        if (editingId.value === category.id) {
            resetForm()
        }

        await loadCategories()
    } catch (err) {
        error.value = 'Kon categorie niet verwijderen.'
        console.error(err)
    }
}

onMounted(() => {
    loadCategories()
})
</script>
