<template>
    <button
        type="button"
        @click="store.addProduct(product)"
        class="group flex h-28 flex-col justify-between rounded-2xl border border-slate-800 bg-slate-950 p-3 text-left transition hover:-translate-y-0.5 hover:border-blue-500 hover:bg-slate-900"
    >
        <div>
            <div class="line-clamp-2 text-sm font-semibold text-white">
                {{ product.name }}
            </div>

            <div
                v-if="product.category_name"
                class="mt-1 text-xs text-slate-500"
            >
                {{ product.category_name }}
            </div>
        </div>

        <div class="text-lg font-bold text-blue-400">
            € {{ formatPrice(product.price_incl_vat) }}
        </div>
    </button>
</template>

<script setup>
import { usePosStore } from '../../stores/usePosStore.js'

const store = usePosStore()

defineProps({
    product: {
        type: Object,
        required: true,
    },
})

function formatPrice(value) {
    return Number(value).toFixed(2)
}
</script>
