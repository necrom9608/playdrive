<template>
    <section class="space-y-6">
        <div class="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
            <div>
                <h1 class="text-2xl font-semibold text-white">Voucher templates</h1>
                <p class="mt-1 text-sm text-slate-400">
                    Maak hier soorten vouchers aan en koppel ze aan een cadeaubon-product en optioneel een voucher badge.
                </p>
            </div>

            <button
                type="button"
                class="inline-flex items-center justify-center rounded-xl bg-blue-600 px-4 py-3 text-sm font-medium text-white transition hover:bg-blue-500"
                @click="openCreateModal"
            >
                Nieuw voucher type
            </button>
        </div>

        <div class="grid grid-cols-1 gap-4 lg:grid-cols-[minmax(0,1fr)_320px]">
            <div class="rounded-2xl border border-slate-800 bg-slate-900 p-4 shadow-sm">
                <div class="grid grid-cols-1 gap-4 md:grid-cols-3">
                    <div>
                        <label class="mb-1 block text-sm font-medium text-slate-300">Zoeken</label>
                        <input
                            v-model="filters.search"
                            type="text"
                            placeholder="Zoek op naam of product"
                            class="w-full rounded-xl border border-slate-700 bg-slate-950 px-4 py-3 text-white outline-none transition focus:border-blue-500"
                        />
                    </div>

                    <div>
                        <label class="mb-1 block text-sm font-medium text-slate-300">Status</label>
                        <select
                            v-model="filters.status"
                            class="w-full rounded-xl border border-slate-700 bg-slate-950 px-4 py-3 text-white outline-none transition focus:border-blue-500"
                        >
                            <option value="all">Alles</option>
                            <option value="active">Actief</option>
                            <option value="inactive">Inactief</option>
                        </select>
                    </div>

                    <div class="flex items-end">
                        <div class="w-full rounded-xl border border-slate-800 bg-slate-950 px-4 py-3 text-sm text-slate-300">
                            {{ filteredTemplates.length }} voucher{{ filteredTemplates.length === 1 ? '' : 's' }}
                        </div>
                    </div>
                </div>
            </div>

            <div class="rounded-2xl border border-slate-800 bg-slate-900 p-4 shadow-sm">
                <h2 class="text-sm font-semibold uppercase tracking-wide text-slate-300">Beschikbare voucher badges</h2>
                <div class="mt-3 space-y-2">
                    <div v-if="badgeTemplates.length === 0" class="rounded-xl border border-dashed border-slate-700 bg-slate-950/60 px-4 py-4 text-sm text-slate-400">
                        Nog geen voucher badge templates gevonden.
                    </div>
                    <div
                        v-for="badge in badgeTemplates"
                        :key="badge.id"
                        class="rounded-xl border border-slate-800 bg-slate-950/70 px-4 py-3"
                    >
                        <div class="flex items-center justify-between gap-3">
                            <span class="font-medium text-white">{{ badge.name }}</span>
                            <span
                                v-if="badge.is_default"
                                class="rounded-full border border-emerald-500/30 bg-emerald-500/10 px-2.5 py-1 text-xs font-semibold text-emerald-200"
                            >
                                Standaard
                            </span>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div v-if="error" class="rounded-2xl border border-red-800 bg-red-950/40 px-4 py-3 text-sm text-red-300">
            {{ error }}
        </div>

        <div class="rounded-2xl border border-slate-800 bg-slate-900 shadow-sm">
            <div v-if="loading" class="p-6 text-sm text-slate-400">Laden...</div>
            <div v-else-if="filteredTemplates.length === 0" class="p-6 text-sm text-slate-400">Nog geen voucher templates gevonden.</div>
            <div v-else class="overflow-x-auto">
                <table class="min-w-full text-sm">
                    <thead class="bg-slate-950 text-slate-300">
                        <tr>
                            <th class="px-4 py-3 text-left font-semibold">Naam</th>
                            <th class="px-4 py-3 text-left font-semibold">Product</th>
                            <th class="px-4 py-3 text-left font-semibold">Badge</th>
                            <th class="px-4 py-3 text-left font-semibold">Status</th>
                            <th class="px-4 py-3 text-right font-semibold">Acties</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="template in filteredTemplates" :key="template.id" class="border-t border-slate-800 bg-slate-900">
                            <td class="px-4 py-3 text-white">{{ template.name }}</td>
                            <td class="px-4 py-3 text-slate-300">{{ template.product_name || '-' }}</td>
                            <td class="px-4 py-3 text-slate-300">{{ template.badge_template_name || 'Geen badge gekoppeld' }}</td>
                            <td class="px-4 py-3">
                                <span
                                    class="inline-flex rounded-full px-2.5 py-1 text-xs font-semibold"
                                    :class="template.is_active ? 'bg-emerald-500/15 text-emerald-300' : 'bg-slate-700 text-slate-300'"
                                >
                                    {{ template.is_active ? 'Actief' : 'Inactief' }}
                                </span>
                            </td>
                            <td class="px-4 py-3">
                                <div class="flex justify-end gap-2">
                                    <button type="button" class="rounded-lg border border-slate-700 px-3 py-2 text-xs font-medium text-slate-300 transition hover:bg-slate-800" @click="openEditModal(template)">
                                        Bewerken
                                    </button>
                                    <button type="button" class="rounded-lg border border-red-800 px-3 py-2 text-xs font-medium text-red-300 transition hover:bg-red-950/40" @click="removeTemplate(template)">
                                        Verwijderen
                                    </button>
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <ModalDialog
            :open="modalOpen"
            :title="editingId ? 'Voucher template bewerken' : 'Nieuw voucher template'"
            description="Koppel een voucher type aan een product en optioneel aan een voucher badge template."
            @close="closeModal"
        >
            <form class="space-y-4" @submit.prevent="submit">
                <div>
                    <label class="mb-1 block text-sm font-medium text-slate-300">Naam</label>
                    <input
                        v-model="form.name"
                        type="text"
                        class="w-full rounded-xl border border-slate-700 bg-slate-950 px-4 py-3 text-white outline-none transition focus:border-blue-500"
                        placeholder="Bijvoorbeeld: Cadeaubon € 50"
                    />
                </div>

                <div>
                    <label class="mb-1 block text-sm font-medium text-slate-300">Product</label>
                    <select
                        v-model="form.product_id"
                        class="w-full rounded-xl border border-slate-700 bg-slate-950 px-4 py-3 text-white outline-none transition focus:border-blue-500"
                    >
                        <option :value="null">Selecteer een product</option>
                        <option v-for="product in products" :key="product.id" :value="product.id">
                            {{ product.name }}<span v-if="!product.is_active"> (inactief)</span>
                        </option>
                    </select>
                    <p class="mt-2 text-xs text-slate-500">Kies hier het product dat voor deze voucher gebruikt wordt, bijvoorbeeld je cadeaubon-product.</p>
                </div>

                <div>
                    <label class="mb-1 block text-sm font-medium text-slate-300">Voucher badge</label>
                    <select
                        v-model="form.badge_template_id"
                        class="w-full rounded-xl border border-slate-700 bg-slate-950 px-4 py-3 text-white outline-none transition focus:border-blue-500"
                    >
                        <option :value="null">Geen badge koppelen</option>
                        <option v-for="badge in badgeTemplates" :key="badge.id" :value="badge.id">
                            {{ badge.name }}
                        </option>
                    </select>
                    <p class="mt-2 text-xs text-slate-500">Hier koppel je een badge template van het type voucher aan dit voucher type.</p>
                </div>

                <label class="flex items-center gap-3 text-sm text-slate-300">
                    <input v-model="form.is_active" type="checkbox" class="h-4 w-4 rounded border-slate-600 bg-slate-950 text-blue-500 focus:ring-blue-500" />
                    Actief
                </label>

                <div class="flex justify-end gap-3 pt-2">
                    <button type="button" class="rounded-xl border border-slate-700 px-4 py-3 text-sm font-medium text-slate-300 transition hover:bg-slate-800" @click="closeModal">
                        Annuleren
                    </button>
                    <button type="submit" class="rounded-xl bg-blue-600 px-4 py-3 text-sm font-medium text-white transition hover:bg-blue-500" :disabled="saving">
                        {{ saving ? 'Opslaan...' : (editingId ? 'Opslaan' : 'Aanmaken') }}
                    </button>
                </div>
            </form>
        </ModalDialog>
    </section>
</template>

<script setup>
import { computed, onMounted, reactive, ref } from 'vue'
import axios from 'axios'
import ModalDialog from '../../../components/ModalDialog.vue'

const loading = ref(true)
const saving = ref(false)
const error = ref('')
const modalOpen = ref(false)
const editingId = ref(null)
const templates = ref([])
const products = ref([])
const badgeTemplates = ref([])

const filters = reactive({
    search: '',
    status: 'all',
})

const form = reactive({
    name: '',
    product_id: null,
    badge_template_id: null,
    is_active: true,
})

const filteredTemplates = computed(() => {
    const search = filters.search.trim().toLowerCase()

    return templates.value.filter(template => {
        if (filters.status === 'active' && !template.is_active) return false
        if (filters.status === 'inactive' && template.is_active) return false

        if (!search) return true

        return [template.name, template.product_name, template.badge_template_name]
            .filter(Boolean)
            .some(value => value.toLowerCase().includes(search))
    })
})

onMounted(() => {
    loadData()
})

async function loadData() {
    loading.value = true
    error.value = ''

    try {
        const { data } = await axios.get('/api/backoffice/voucher-templates')
        templates.value = data.data?.templates ?? []
        products.value = data.data?.products ?? []
        badgeTemplates.value = data.data?.badge_templates ?? []
    } catch (err) {
        error.value = err.response?.data?.message || 'Voucher templates laden is mislukt.'
    } finally {
        loading.value = false
    }
}

function openCreateModal() {
    editingId.value = null
    resetForm()
    modalOpen.value = true
}

function openEditModal(template) {
    editingId.value = template.id
    form.name = template.name || ''
    form.product_id = template.product_id || null
    form.badge_template_id = template.badge_template_id || null
    form.is_active = !!template.is_active
    modalOpen.value = true
}

function closeModal() {
    modalOpen.value = false
    editingId.value = null
    resetForm()
}

function resetForm() {
    form.name = ''
    form.product_id = null
    form.badge_template_id = null
    form.is_active = true
}

async function submit() {
    saving.value = true
    error.value = ''

    const payload = {
        name: form.name,
        product_id: form.product_id,
        badge_template_id: form.badge_template_id,
        is_active: form.is_active,
    }

    try {
        const response = editingId.value
            ? await axios.put(`/api/backoffice/voucher-templates/${editingId.value}`, payload)
            : await axios.post('/api/backoffice/voucher-templates', payload)

        const saved = response.data.data

        if (editingId.value) {
            templates.value = templates.value.map(item => item.id === saved.id ? saved : item)
        } else {
            templates.value = [...templates.value, saved].sort((a, b) => {
                if ((a.sort_order || 0) !== (b.sort_order || 0)) {
                    return (a.sort_order || 0) - (b.sort_order || 0)
                }
                return a.name.localeCompare(b.name)
            })
        }

        closeModal()
    } catch (err) {
        error.value = err.response?.data?.message || 'Voucher template opslaan is mislukt.'
    } finally {
        saving.value = false
    }
}

async function removeTemplate(template) {
    if (!window.confirm(`Wil je "${template.name}" verwijderen?`)) {
        return
    }

    error.value = ''

    try {
        await axios.delete(`/api/backoffice/voucher-templates/${template.id}`)
        templates.value = templates.value.filter(item => item.id !== template.id)
    } catch (err) {
        error.value = err.response?.data?.message || 'Voucher template verwijderen is mislukt.'
    }
}
</script>
