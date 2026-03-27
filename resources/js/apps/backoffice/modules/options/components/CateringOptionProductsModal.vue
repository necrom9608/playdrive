<template>
    <ModalDialog
        :open="open"
        :title="option ? `Producten voor ${option.name}` : 'Gekoppelde producten'"
        description="Koppel producten aan deze cateringoptie."
        @close="$emit('close')"
    >
        <div class="space-y-4">
            <div
                v-if="error"
                class="rounded-2xl border border-red-800 bg-red-950/40 px-4 py-3 text-sm text-red-300"
            >
                {{ error }}
            </div>

            <form class="grid gap-4 rounded-2xl border border-slate-800 bg-slate-950/60 p-4 md:grid-cols-4" @submit.prevent="submit">
                <div class="md:col-span-4">
                    <label class="mb-1 block text-sm font-medium text-slate-300">Product</label>
                    <select
                        v-model="form.product_id"
                        class="w-full rounded-xl border border-slate-700 bg-slate-950 px-4 py-3 text-white outline-none transition focus:border-blue-500"
                    >
                        <option value="">Selecteer product</option>
                        <option v-for="product in products" :key="product.id" :value="product.id">
                            {{ product.name }}
                        </option>
                    </select>
                </div>

                <div>
                    <label class="flex items-center gap-3 text-sm text-slate-300">
                        <input v-model="form.applies_to_children" type="checkbox" class="h-4 w-4 rounded border-slate-600 bg-slate-950" />
                        Kinderen
                    </label>
                </div>

                <div>
                    <label class="flex items-center gap-3 text-sm text-slate-300">
                        <input v-model="form.applies_to_adults" type="checkbox" class="h-4 w-4 rounded border-slate-600 bg-slate-950" />
                        Volwassenen
                    </label>
                </div>

                <div class="md:col-span-2">
                    <label class="mb-1 block text-sm font-medium text-slate-300">Aantal per persoon</label>
                    <input
                        v-model="form.quantity_per_person"
                        type="number"
                        min="0.01"
                        step="0.01"
                        class="w-full rounded-xl border border-slate-700 bg-slate-950 px-4 py-3 text-white outline-none transition focus:border-blue-500"
                    />
                </div>

                <div class="md:col-span-4 flex gap-3">
                    <button
                        type="submit"
                        :disabled="saving"
                        class="rounded-xl bg-blue-600 px-4 py-3 text-sm font-semibold text-white transition hover:bg-blue-500 disabled:opacity-60"
                    >
                        {{ saving ? 'Opslaan...' : editingId ? 'Koppeling opslaan' : 'Koppeling toevoegen' }}
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

            <div class="rounded-2xl border border-slate-800 bg-slate-900 shadow-sm">
                <div v-if="loading" class="p-6 text-sm text-slate-400">
                    Laden...
                </div>

                <div v-else-if="links.length === 0" class="p-6 text-sm text-slate-400">
                    Nog geen producten gekoppeld.
                </div>

                <div v-else class="overflow-x-auto">
                    <table class="min-w-full text-sm">
                        <thead class="bg-slate-950 text-slate-300">
                        <tr>
                            <th class="px-4 py-3 text-left font-semibold">Volgorde</th>
                            <th class="px-4 py-3 text-left font-semibold">Product</th>
                            <th class="px-4 py-3 text-left font-semibold">Kinderen</th>
                            <th class="px-4 py-3 text-left font-semibold">Volwassenen</th>
                            <th class="px-4 py-3 text-left font-semibold">Aantal</th>
                            <th class="px-4 py-3 text-right font-semibold">Acties</th>
                        </tr>
                        </thead>

                        <tbody>
                        <tr
                            v-for="link in localLinks"
                            :key="link.id"
                            draggable="true"
                            class="border-t border-slate-800 bg-slate-900"
                            :class="draggingId === link.id ? 'opacity-40' : ''"
                            @dragstart="onDragStart(link.id)"
                            @dragover.prevent
                            @drop="onDrop(link.id)"
                            @dragend="draggingId = null"
                        >
                            <td class="px-4 py-3 text-slate-400">↕ {{ link.sort_order }}</td>
                            <td class="px-4 py-3 text-white">{{ link.product_name }}</td>
                            <td class="px-4 py-3 text-slate-400">{{ link.applies_to_children ? 'Ja' : 'Nee' }}</td>
                            <td class="px-4 py-3 text-slate-400">{{ link.applies_to_adults ? 'Ja' : 'Nee' }}</td>
                            <td class="px-4 py-3 text-slate-400">{{ link.quantity_per_person }}</td>
                            <td class="px-4 py-3">
                                <div class="flex justify-end gap-2">
                                    <button
                                        type="button"
                                        @click="editLink(link)"
                                        class="rounded-lg border border-slate-700 px-3 py-2 text-xs font-medium text-slate-300 transition hover:bg-slate-800"
                                    >
                                        Bewerken
                                    </button>

                                    <button
                                        type="button"
                                        @click="removeLink(link)"
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
    </ModalDialog>
</template>

<script setup>
import { reactive, ref, watch } from 'vue'
import ModalDialog from '../../../components/ModalDialog.vue'
import {
    createCateringOptionProduct,
    deleteCateringOptionProduct,
    fetchCateringOptionProducts,
    reorderCateringOptionProducts,
    updateCateringOptionProduct,
} from '../services/cateringOptionProductApi'

const props = defineProps({
    open: { type: Boolean, default: false },
    option: { type: Object, default: null },
    products: { type: Array, default: () => [] },
})

const loading = ref(false)
const saving = ref(false)
const error = ref('')
const links = ref([])
const localLinks = ref([])
const editingId = ref(null)
const draggingId = ref(null)

const form = reactive({
    product_id: '',
    applies_to_children: false,
    applies_to_adults: false,
    quantity_per_person: 1,
})

watch(
    () => props.open,
    async (open) => {
        if (open && props.option?.id) {
            resetForm()
            await loadLinks()
        }
    },
)

watch(
    links,
    (value) => {
        localLinks.value = [...value]
    },
    { deep: true },
)

async function loadLinks() {
    loading.value = true
    error.value = ''

    try {
        links.value = await fetchCateringOptionProducts(props.option.id)
    } catch (err) {
        console.error(err)
        error.value = 'Kon gekoppelde producten niet laden.'
    } finally {
        loading.value = false
    }
}

function resetForm() {
    editingId.value = null
    form.product_id = ''
    form.applies_to_children = false
    form.applies_to_adults = false
    form.quantity_per_person = 1
}

async function submit() {
    saving.value = true
    error.value = ''

    try {
        const payload = {
            product_id: Number(form.product_id),
            applies_to_children: form.applies_to_children,
            applies_to_adults: form.applies_to_adults,
            quantity_per_person: Number(form.quantity_per_person),
        }

        if (editingId.value) {
            await updateCateringOptionProduct(editingId.value, payload)
        } else {
            await createCateringOptionProduct(props.option.id, payload)
        }

        resetForm()
        await loadLinks()
    } catch (err) {
        console.error(err)

        if (err?.status === 422 && err?.data?.errors) {
            const firstError = Object.values(err.data.errors)?.[0]?.[0]
            error.value = firstError || 'Validatiefout bij opslaan.'
        } else {
            error.value = err?.data?.message || err?.message || 'Kon koppeling niet opslaan.'
        }
    } finally {
        saving.value = false
    }
}

function editLink(link) {
    editingId.value = link.id
    form.product_id = link.product_id
    form.applies_to_children = link.applies_to_children
    form.applies_to_adults = link.applies_to_adults
    form.quantity_per_person = link.quantity_per_person
}

async function removeLink(link) {
    if (!window.confirm(`Koppeling met "${link.product_name}" verwijderen?`)) {
        return
    }

    try {
        await deleteCateringOptionProduct(link.id)
        await loadLinks()
    } catch (err) {
        console.error(err)
        error.value = 'Kon koppeling niet verwijderen.'
    }
}

function onDragStart(id) {
    draggingId.value = id
}

async function onDrop(targetId) {
    if (!draggingId.value || draggingId.value === targetId) {
        draggingId.value = null
        return
    }

    const fromIndex = localLinks.value.findIndex((item) => item.id === draggingId.value)
    const toIndex = localLinks.value.findIndex((item) => item.id === targetId)

    if (fromIndex === -1 || toIndex === -1) {
        draggingId.value = null
        return
    }

    const reordered = [...localLinks.value]
    const [movedItem] = reordered.splice(fromIndex, 1)
    reordered.splice(toIndex, 0, movedItem)

    localLinks.value = reordered.map((item, index) => ({
        ...item,
        sort_order: index + 1,
    }))

    draggingId.value = null

    try {
        await reorderCateringOptionProducts(props.option.id, localLinks.value.map((item) => ({ id: item.id })))
        await loadLinks()
    } catch (err) {
        console.error(err)
        error.value = 'Kon volgorde niet opslaan.'
    }
}
</script>
