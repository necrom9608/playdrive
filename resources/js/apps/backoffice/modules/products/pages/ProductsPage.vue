<template>
    <div class="space-y-6">
        <div class="flex flex-wrap items-start justify-between gap-4">
            <div>
                <h1 v-if="!embedded" class="text-3xl font-bold text-white">Producten</h1>
                <h2 v-else class="text-2xl font-bold text-white">Producten</h2>
                <p class="mt-2 text-slate-400">
                    Beheer producten via een modal. Versleep rijen om de volgorde te bepalen.
                </p>
            </div>

            <div class="flex gap-3">
                <button
                    type="button"
                    @click="loadProducts"
                    :disabled="loading"
                    class="rounded-xl border border-slate-700 px-4 py-3 text-sm font-medium text-slate-300 transition hover:bg-slate-800 disabled:opacity-60"
                >
                    Vernieuwen
                </button>

                <button
                    type="button"
                    @click="openCreateModal"
                    class="rounded-xl bg-blue-600 px-4 py-3 text-sm font-semibold text-white transition hover:bg-blue-500"
                >
                    Nieuw product
                </button>
            </div>
        </div>

        <div class="rounded-2xl border border-slate-800 bg-slate-900 p-4">
            <div class="grid gap-4 md:grid-cols-[minmax(0,260px)_1fr] md:items-end">
                <div>
                    <label class="mb-1 block text-sm font-medium text-slate-300">Filter op categorie</label>
                    <select
                        v-model="selectedCategoryFilter"
                        class="w-full rounded-xl border border-slate-700 bg-slate-950 px-4 py-3 text-white outline-none transition focus:border-blue-500"
                    >
                        <option value="">Alle categorieën</option>
                        <option v-for="category in categories" :key="category.id" :value="String(category.id)">
                            {{ category.name }}
                        </option>
                    </select>
                </div>

                <p class="text-sm text-slate-400">
                    {{ dragDisabled ? 'Sorteerfunctie is tijdelijk uitgeschakeld zolang een categoriefilter actief is.' : 'Je kan producten verslepen om de volgorde te wijzigen.' }}
                </p>
            </div>
        </div>

        <div
            v-if="error"
            class="rounded-2xl border border-red-800 bg-red-950/40 px-4 py-3 text-sm text-red-300"
        >
            {{ error }}
        </div>

        <div class="rounded-2xl border border-slate-800 bg-slate-900 shadow-sm">
            <div v-if="loading" class="p-6 text-sm text-slate-400">Laden...</div>

            <div v-else-if="filteredProducts.length === 0" class="p-6 text-sm text-slate-400">
                Nog geen producten gevonden.
            </div>

            <div v-else class="overflow-x-auto">
                <table class="min-w-full text-sm">
                    <thead class="bg-slate-950 text-slate-300">
                    <tr>
                        <th class="px-4 py-3 text-left font-semibold">Volgorde</th>
                        <th class="px-4 py-3 text-left font-semibold">Afbeelding</th>
                        <th class="px-4 py-3 text-left font-semibold">Naam</th>
                        <th class="px-4 py-3 text-left font-semibold">Categorie</th>
                        <th class="px-4 py-3 text-left font-semibold">Prijs excl.</th>
                        <th class="px-4 py-3 text-left font-semibold">BTW</th>
                        <th class="px-4 py-3 text-left font-semibold">Prijs incl.</th>
                        <th class="px-4 py-3 text-left font-semibold">Status</th>
                        <th class="px-4 py-3 text-right font-semibold">Acties</th>
                    </tr>
                    </thead>

                    <tbody>
                    <tr
                        v-for="product in filteredProducts"
                        :key="product.id"
                        :draggable="!dragDisabled"
                        class="border-t border-slate-800 bg-slate-900"
                        :class="[
                            draggingId === product.id ? 'opacity-40' : '',
                            dragDisabled ? 'cursor-default' : 'cursor-move',
                        ]"
                        @dragstart="onDragStart(product.id)"
                        @dragover.prevent="!dragDisabled"
                        @drop="onDrop(product.id)"
                    >
                        <td class="px-4 py-3 text-slate-400">↕ {{ product.sort_order }}</td>
                        <td class="px-4 py-3">
                            <div class="flex h-14 w-14 items-center justify-center overflow-hidden rounded-xl border border-slate-800 bg-slate-950">
                                <img v-if="product.image_url" :src="product.image_url" :alt="product.name" class="h-full w-full object-cover" />
                                <span v-else class="text-xs text-slate-500">Geen</span>
                            </div>
                        </td>
                        <td class="px-4 py-3 text-white">{{ product.name }}</td>
                        <td class="px-4 py-3 text-slate-400">{{ product.category_name ?? '-' }}</td>
                        <td class="px-4 py-3 text-slate-400">
                            € {{ formatMoney(product.price_excl_vat) }}
                        </td>
                        <td class="px-4 py-3 text-slate-400">{{ formatNumber(product.vat_rate) }}%</td>
                        <td class="px-4 py-3 text-slate-400">
                            € {{ formatMoney(product.price_incl_vat) }}
                        </td>
                        <td class="px-4 py-3">
                                <span
                                    class="inline-flex rounded-full px-2.5 py-1 text-xs font-semibold"
                                    :class="product.is_active ? 'bg-emerald-500/15 text-emerald-300' : 'bg-slate-700 text-slate-300'"
                                >
                                    {{ product.is_active ? 'Actief' : 'Inactief' }}
                                </span>
                        </td>
                        <td class="px-4 py-3">
                            <div class="flex justify-end gap-2">
                                <button
                                    type="button"
                                    @click="openEditModal(product)"
                                    class="rounded-lg border border-slate-700 px-3 py-2 text-xs font-medium text-slate-300 transition hover:bg-slate-800"
                                >
                                    Bewerken
                                </button>

                                <button
                                    type="button"
                                    @click="removeProduct(product)"
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

        <ModalDialog
            :open="modalOpen"
            :title="editingId ? 'Product bewerken' : 'Nieuw product'"
            description="Producten toevoegen en bewerken gebeurt via deze modal."
            @close="closeModal"
        >
            <form class="space-y-4" @submit.prevent="submit">
                <div>
                    <label class="mb-1 block text-sm font-medium text-slate-300">Categorie</label>
                    <select
                        v-model="form.product_category_id"
                        class="w-full rounded-xl border border-slate-700 bg-slate-950 px-4 py-3 text-white outline-none transition focus:border-blue-500"
                    >
                        <option :value="null">Geen categorie</option>
                        <option
                            v-for="category in categories"
                            :key="category.id"
                            :value="category.id"
                        >
                            {{ category.name }}
                        </option>
                    </select>
                </div>

                <div>
                    <label class="mb-1 block text-sm font-medium text-slate-300">Naam</label>
                    <input
                        v-model="form.name"
                        type="text"
                        class="w-full rounded-xl border border-slate-700 bg-slate-950 px-4 py-3 text-white outline-none transition focus:border-blue-500"
                        placeholder="Bijvoorbeeld: Coca-Cola"
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
                    <label class="mb-1 block text-sm font-medium text-slate-300">Beschrijving</label>
                    <textarea
                        v-model="form.description"
                        rows="3"
                        class="w-full rounded-xl border border-slate-700 bg-slate-950 px-4 py-3 text-white outline-none transition focus:border-blue-500"
                    />
                </div>

                <div>
                    <label class="mb-1 block text-sm font-medium text-slate-300">Afbeelding</label>
                    <input
                        type="file"
                        accept="image/*"
                        class="w-full rounded-xl border border-slate-700 bg-slate-950 px-4 py-3 text-white outline-none transition file:mr-4 file:rounded-lg file:border-0 file:bg-slate-800 file:px-3 file:py-2 file:text-sm file:font-medium file:text-slate-200 hover:file:bg-slate-700"
                        @change="onImageChange"
                    />

                    <div v-if="imagePreviewUrl || form.existing_image_url" class="mt-3 flex items-start gap-4">
                        <img :src="imagePreviewUrl || form.existing_image_url" alt="Preview" class="h-24 w-24 rounded-xl border border-slate-800 object-cover" />
                        <p class="text-sm text-slate-400">De afbeelding wordt gebruikt in het productbeheer en later ook in andere modules.</p>
                    </div>
                </div>

                <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
                    <div>
                        <label class="mb-1 block text-sm font-medium text-slate-300">Prijs incl. btw</label>
                        <input
                            v-model="form.price_incl_vat"
                            type="text"
                            inputmode="decimal"
                            class="w-full rounded-xl border border-slate-700 bg-slate-950 px-4 py-3 text-white outline-none transition focus:border-blue-500"
                            placeholder="Bijvoorbeeld: 3,50"
                        />
                    </div>

                    <div>
                        <label class="mb-1 block text-sm font-medium text-slate-300">BTW %</label>
                        <input
                            v-model="form.vat_rate"
                            type="text"
                            inputmode="decimal"
                            class="w-full rounded-xl border border-slate-700 bg-slate-950 px-4 py-3 text-white outline-none transition focus:border-blue-500"
                            placeholder="Bijvoorbeeld: 21"
                        />
                    </div>
                </div>

                <label class="flex items-center gap-3 text-sm text-slate-300">
                    <input
                        v-model="form.is_active"
                        type="checkbox"
                        class="h-4 w-4 rounded border-slate-600 bg-slate-950"
                    />
                    Actief
                </label>

                <div class="flex flex-wrap gap-3 pt-2">
                    <button
                        type="submit"
                        :disabled="saving"
                        class="rounded-xl bg-blue-600 px-4 py-3 text-sm font-semibold text-white transition hover:bg-blue-500 disabled:opacity-60"
                    >
                        {{ saving ? 'Opslaan...' : editingId ? 'Product opslaan' : 'Product toevoegen' }}
                    </button>

                    <button
                        type="button"
                        @click="closeModal"
                        class="rounded-xl border border-slate-700 px-4 py-3 text-sm font-medium text-slate-300 transition hover:bg-slate-800"
                    >
                        Annuleren
                    </button>
                </div>
            </form>
        </ModalDialog>
    </div>
</template>

<script setup>
import { computed, onMounted, onBeforeUnmount, reactive, ref } from 'vue'
import ModalDialog from '../../../components/ModalDialog.vue'
import { fetchProductCategories } from '../../product-categories/services/productCategoryApi'
import {
    createProduct,
    deleteProduct,
    fetchProducts,
    reorderProducts,
    updateProduct,
} from '../services/productApi'

const props = defineProps({
    embedded: {
        type: Boolean,
        default: false,
    },
})

const categories = ref([])
const categoriesLoaded = ref(false)
const products = ref([])
const loading = ref(false)
const saving = ref(false)
const error = ref('')
const editingId = ref(null)
const modalOpen = ref(false)
const draggingId = ref(null)
const selectedCategoryFilter = ref('')
const imagePreviewUrl = ref('')

const form = reactive({
    product_category_id: null,
    name: '',
    slug: '',
    description: '',
    price_incl_vat: '0,00',
    vat_rate: '21',
    is_active: true,
    image: null,
    existing_image_url: '',
})

const filteredProducts = computed(() => {
    if (!selectedCategoryFilter.value) {
        return products.value
    }

    return products.value.filter(product => String(product.product_category_id ?? '') === selectedCategoryFilter.value)
})

const dragDisabled = computed(() => selectedCategoryFilter.value !== '')

function resetForm() {
    editingId.value = null
    form.product_category_id = null
    form.name = ''
    form.slug = ''
    form.description = ''
    form.price_incl_vat = '0,00'
    form.vat_rate = '21'
    form.is_active = true
    form.image = null
    form.existing_image_url = ''
    clearImagePreview()
}

function clearImagePreview() {
    if (imagePreviewUrl.value) {
        URL.revokeObjectURL(imagePreviewUrl.value)
        imagePreviewUrl.value = ''
    }
}

function formatNumber(value) {
    return Number(value ?? 0).toFixed(2)
}

function formatMoney(value) {
    return Number(value ?? 0).toFixed(2)
}

function parseDecimal(value) {
    if (typeof value === 'number') {
        return value
    }

    const normalized = String(value ?? '')
        .trim()
        .replace(/\s+/g, '')
        .replace(',', '.')

    const parsed = Number.parseFloat(normalized)
    return Number.isFinite(parsed) ? parsed : 0
}

async function loadProducts() {
    loading.value = true
    error.value = ''

    try {
        const productData = await fetchProducts()
        products.value = Array.isArray(productData) ? productData : []
    } catch (err) {
        error.value = err?.data?.message ?? 'Kon producten niet laden.'
        console.error(err)
    } finally {
        loading.value = false
    }
}

async function ensureCategoriesLoaded() {
    if (categoriesLoaded.value) {
        return
    }

    try {
        const categoryData = await fetchProductCategories()
        categories.value = Array.isArray(categoryData) ? categoryData : []
        categoriesLoaded.value = true
    } catch (err) {
        error.value = err?.data?.message ?? 'Kon productcategorieën niet laden.'
        console.error(err)
    }
}

async function openCreateModal() {
    resetForm()
    await ensureCategoriesLoaded()
    modalOpen.value = true
}

async function openEditModal(product) {
    resetForm()
    editingId.value = product.id
    form.product_category_id = product.product_category_id
    form.name = product.name
    form.slug = product.slug
    form.description = product.description ?? ''
    form.price_incl_vat = formatMoney(product.price_incl_vat)
    form.vat_rate = formatNumber(product.vat_rate ?? 21)
    form.is_active = Boolean(product.is_active)
    form.existing_image_url = product.image_url ?? ''

    await ensureCategoriesLoaded()
    modalOpen.value = true
}

function closeModal() {
    modalOpen.value = false
    resetForm()
}

function onImageChange(event) {
    const [file] = event.target.files ?? []
    form.image = file ?? null
    clearImagePreview()

    if (form.image) {
        imagePreviewUrl.value = URL.createObjectURL(form.image)
    }
}

async function submit() {
    saving.value = true
    error.value = ''

    try {
        const payload = {
            product_category_id: form.product_category_id,
            name: form.name,
            slug: form.slug,
            description: form.description,
            price_incl_vat: parseDecimal(form.price_incl_vat),
            vat_rate: parseDecimal(form.vat_rate),
            is_active: form.is_active,
            image: form.image,
        }

        if (editingId.value) {
            await updateProduct(editingId.value, payload)
        } else {
            await createProduct(payload)
        }

        closeModal()
        await loadProducts()
    } catch (err) {
        error.value = err?.data?.message ?? 'Kon product niet opslaan.'
        console.error(err)
    } finally {
        saving.value = false
    }
}

async function removeProduct(product) {
    if (!window.confirm(`Product "${product.name}" verwijderen?`)) {
        return
    }

    try {
        await deleteProduct(product.id)
        await loadProducts()
    } catch (err) {
        error.value = err?.data?.message ?? 'Kon product niet verwijderen.'
        console.error(err)
    }
}

function onDragStart(id) {
    if (dragDisabled.value) {
        return
    }

    draggingId.value = id
}

async function onDrop(targetId) {
    if (dragDisabled.value || !draggingId.value || draggingId.value === targetId) {
        return
    }

    const fromIndex = products.value.findIndex((item) => item.id === draggingId.value)
    const toIndex = products.value.findIndex((item) => item.id === targetId)

    if (fromIndex === -1 || toIndex === -1) {
        draggingId.value = null
        return
    }

    const reordered = [...products.value]
    const [movedItem] = reordered.splice(fromIndex, 1)
    reordered.splice(toIndex, 0, movedItem)

    products.value = reordered.map((item, index) => ({
        ...item,
        sort_order: index + 1,
    }))

    draggingId.value = null

    try {
        await reorderProducts(products.value.map((item) => ({ id: item.id })))
        await loadProducts()
    } catch (err) {
        error.value = err?.data?.message ?? 'Kon volgorde niet opslaan.'
        console.error(err)
    }
}

onMounted(async () => {
    await Promise.all([loadProducts(), ensureCategoriesLoaded()])
})

onBeforeUnmount(() => {
    clearImagePreview()
})
</script>
