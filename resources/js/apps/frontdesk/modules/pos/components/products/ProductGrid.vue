<template>
    <div class="min-h-0 flex-1 overflow-auto">
        <div
            v-if="store.loadingCatalog"
            class="grid grid-cols-4 gap-3"
        >
            <div
                v-for="n in 8"
                :key="n"
                class="h-28 animate-pulse rounded-2xl border border-slate-800 bg-slate-800"
            />
        </div>

        <div
            v-else-if="store.filteredProducts.length"
            class="grid grid-cols-4 gap-3"
        >
            <ProductTile
                v-for="product in store.filteredProducts"
                :key="product.id"
                :product="product"
            />
        </div>

        <div
            v-else
            class="rounded-2xl border border-dashed border-slate-700 bg-slate-950/50 p-8 text-center"
        >
            <p class="text-sm text-slate-400">
                Geen producten gevonden in deze categorie.
            </p>
        </div>
    </div>
</template>

<script setup>
import { usePosStore } from '../../stores/usePosStore.js'
import ProductTile from './ProductTile.vue'

const store = usePosStore()
</script>
