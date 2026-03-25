<template>
    <div class="flex h-full min-h-0 flex-col rounded-3xl border border-slate-800 bg-slate-900 shadow-xl">
        <div class="border-b border-slate-800 bg-slate-900/80 p-4">
            <div>
                <h2 class="text-base font-semibold text-white">Producten</h2>
                <p class="mt-1 text-sm text-slate-400">
                    Kies een categorie en voeg producten toe aan de bestelling.
                </p>
            </div>
        </div>

        <div class="min-h-0 flex-1 p-4">
            <div class="flex h-full min-h-0 flex-col">
                <CategoryTabs />
                <ProductGrid />
            </div>
        </div>
    </div>
</template>

<script setup>
import CategoryTabs from './CategoryTabs.vue'
import ProductGrid from './ProductGrid.vue'
</script>
