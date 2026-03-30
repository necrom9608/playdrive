<template>
    <div class="space-y-6">
        <div>
            <h1 class="text-3xl font-bold text-white">Productbeheer</h1>
            <p class="mt-2 text-slate-400">Beheer categorieën, producten en automatische prijsregels vanuit één centrale plek.</p>
        </div>

        <div class="flex flex-wrap gap-2 rounded-2xl border border-slate-800 bg-slate-900 p-2">
            <button
                v-for="tab in tabs"
                :key="tab.key"
                type="button"
                class="rounded-xl px-4 py-3 text-sm font-semibold transition"
                :class="activeTab === tab.key
                    ? 'bg-blue-600 text-white'
                    : 'text-slate-300 hover:bg-slate-800 hover:text-white'"
                @click="selectTab(tab.key)"
            >
                {{ tab.label }}
            </button>
        </div>

        <ProductCategoriesPage v-if="activeTab === 'categories'" embedded />
        <ProductsPage v-else-if="activeTab === 'products'" embedded />
        <PricingEnginePage v-else embedded />
    </div>
</template>

<script setup>
import { computed } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import PricingEnginePage from '../../pricing-engine/pages/PricingEnginePage.vue'
import ProductCategoriesPage from '../../product-categories/pages/ProductCategoriesPage.vue'
import ProductsPage from '../../products/pages/ProductsPage.vue'

const route = useRoute()
const router = useRouter()

const tabs = [
    { key: 'categories', label: 'Categorieën' },
    { key: 'products', label: 'Producten' },
    { key: 'pricing-rules', label: 'Automatische prijsregels' },
]

const validTabs = tabs.map(tab => tab.key)

const activeTab = computed(() => {
    const tab = route.query.tab
    return validTabs.includes(tab) ? tab : 'categories'
})

function selectTab(tab) {
    router.replace({
        name: 'backoffice.product-management',
        query: tab === 'categories' ? {} : { tab },
    })
}
</script>
