<template>
    <div class="space-y-6">
        <div>
            <h1 class="text-3xl font-bold text-white">Products</h1>
            <p class="mt-2 text-slate-400">
                Beheer hier de producten van deze tenant.
            </p>
        </div>

        <div class="grid gap-6 lg:grid-cols-[420px,1fr]">
            <div class="rounded-2xl border border-slate-800 bg-slate-900 p-5 shadow-sm">
                <h2 class="text-lg font-semibold text-white">Nieuw product</h2>

                <form class="mt-4 space-y-4" @submit.prevent="submit">
                    <div>
                        <label class="mb-1 block text-sm font-medium text-slate-300">Categorie</label>
                        <select
                            v-model="form.product_category_id"
                            class="w-full rounded-xl border border-slate-700 bg-slate-950 px-4 py-3 text-white outline-none transition focus:border-blue-500"
                        >
                            <option :value="null">Geen categorie</option>
                            <option v-for="category in categories" :key="category.id" :value="category.id">
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

                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <label class="mb-1 block text-sm font-medium text-slate-300">Prijs excl. btw</label>
                            <input
                                v-model.number="form.price_excl_vat"
                                type="number"
                                step="0.01"
                                min="0"
                                class="w-full rounded-xl border border-slate-700 bg-slate-950 px-4 py-3 text-white outline-none transition focus:border-blue-500"
                            />
                        </div>

                        <div>
                            <label class="mb-1 block text-sm font-medium text-slate-300">BTW %</label>
                            <input
                                v-model.number="form.vat_rate"
                                type="number"
                                step="0.01"
                                min="0"
                                class="w-full rounded-xl border border-slate-700 bg-slate-950 px-4 py-3 text-white outline-none transition focus:border-blue-500"
                            />
                        </div>
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

                    <button
                        type="submit"
                        :disabled="saving"
                        class="rounded-xl bg-blue-600 px-4 py-3 text-sm font-semibold text-white transition hover:bg-blue-500 disabled:cursor-not-allowed disabled:opacity-60"
                    >
                        {{ saving ? 'Opslaan...' : 'Product toevoegen' }}
                    </button>
                </form>
            </div>

            <div class="rounded-2xl border border-slate-800 bg-slate-900 p-5 shadow-sm">
                <div class="flex items-center justify-between gap-4">
                    <h2 class="text-lg font-semibold text-white">Producten</h2>

                    <button
                        type="button"
                        @click="loadData"
                        :disabled="loading"
                        class="rounded-xl border border-slate-700 px-4 py-2 text-sm font-medium text-slate-300 transition hover:bg-slate-800 disabled:opacity-60"
                    >
                        Vernieuwen
                    </button>
                </div>

                <div v-if="loading" class="mt-4 text-sm text-slate-400">
                    Laden...
                </div>

                <div v-else-if="products.length === 0" class="mt-4 rounded-xl border border-slate-800 bg-slate-950 px-4 py-6 text-sm text-slate-400">
                    Nog geen producten gevonden.
                </div>

                <div v-else class="mt-4 overflow-hidden rounded-2xl border border-slate-800">
                    <table class="min-w-full divide-y divide-slate-800 text-sm">
                        <thead class="bg-slate-950">
                        <tr>
                            <th class="px-4 py-3 text-left font-semibold text-slate-300">Naam</th>
                            <th class="px-4 py-3 text-left font-semibold text-slate-300">Categorie</th>
                            <th class="px-4 py-3 text-left font-semibold text-slate-300">Prijs excl.</th>
                            <th class="px-4 py-3 text-left font-semibold text-slate-300">BTW</th>
                            <th class="px-4 py-3 text-left font-semibold text-slate-300">Prijs incl.</th>
                            <th class="px-4 py-3 text-left font-semibold text-slate-300">Status</th>
                        </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-800">
                        <tr v-for="product in products" :key="product.id" class="bg-slate-900">
                            <td class="px-4 py-3 text-white">{{ product.name }}</td>
                            <td class="px-4 py-3 text-slate-400">{{ product.category_name ?? '-' }}</td>
                            <td class="px-4 py-3 text-slate-400">{{ product.price_excl_vat }}</td>
                            <td class="px-4 py-3 text-slate-400">{{ product.vat_rate }}%</td>
                            <td class="px-4 py-3 text-slate-400">{{ product.price_incl_vat }}</td>
                            <td class="px-4 py-3">
                                    <span
                                        class="inline-flex rounded-full px-2.5 py-1 text-xs font-semibold"
                                        :class="product.is_active
                                            ? 'bg-emerald-500/15 text-emerald-300'
                                            : 'bg-slate-700 text-slate-300'"
                                    >
                                        {{ product.is_active ? 'Actief' : 'Inactief' }}
                                    </span>
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
import { fetchProductCategories } from '../../product-categories/services/productCategoryApi'
import { createProduct, fetchProducts } from '../services/productApi'

const categories = ref([])
const products = ref([])
const loading = ref(false)
const saving = ref(false)
const error = ref('')

const form = reactive({
    product_category_id: null,
    name: '',
    slug: '',
    description: '',
    price_excl_vat: 0,
    vat_rate: 21,
    sort_order: 0,
    is_active: true,
})

async function loadData() {
    loading.value = true
    error.value = ''

    try {
        const [categoryData, productData] = await Promise.all([
            fetchProductCategories(),
            fetchProducts(),
        ])

        categories.value = categoryData
        products.value = productData
    } catch (err) {
        error.value = 'Kon gegevens niet laden.'
        console.error(err)
    } finally {
        loading.value = false
    }
}

async function submit() {
    saving.value = true
    error.value = ''

    try {
        await createProduct({
            product_category_id: form.product_category_id,
            name: form.name,
            slug: form.slug,
            description: form.description,
            price_excl_vat: form.price_excl_vat,
            vat_rate: form.vat_rate,
            sort_order: form.sort_order,
            is_active: form.is_active,
        })

        form.product_category_id = null
        form.name = ''
        form.slug = ''
        form.description = ''
        form.price_excl_vat = 0
        form.vat_rate = 21
        form.sort_order = 0
        form.is_active = true

        await loadData()
    } catch (err) {
        error.value = 'Kon product niet opslaan.'
        console.error(err)
    } finally {
        saving.value = false
    }
}

onMounted(() => {
    loadData()
})
</script>
