<template>
    <div
        class="border-b border-dashed border-white/10 py-3 transition duration-200"
        :class="isLastAdded ? 'checkout-row-new' : ''"
    >
        <div class="flex items-center justify-between gap-3">
            <div class="flex min-w-0 items-center gap-3">
                <div class="relative h-11 w-11 shrink-0 overflow-hidden rounded-xl border border-white/10 bg-slate-900/70">
                    <template v-if="imageUrl">
                        <img
                            :src="imageUrl"
                            :alt="item.name"
                            class="h-full w-full object-cover"
                        >
                    </template>
                    <template v-else>
                        <div class="flex h-full w-full items-center justify-center bg-[radial-gradient(circle_at_top,_rgba(59,130,246,0.16),_transparent_60%)] text-[9px] font-semibold uppercase tracking-[0.18em] text-slate-500">
                            Logo
                        </div>
                    </template>
                </div>

                <div class="min-w-0">
                    <div class="truncate text-sm font-semibold text-white">
                        {{ item.name }}
                    </div>
                    <div class="mt-0.5 text-xs" :class="metaTextClass">
                        € {{ formatPrice(item.price_incl_vat) }} / stuk
                    </div>
                </div>
            </div>

            <button
                type="button"
                @click="store.removeItem(item.line_id)"
                class="inline-flex h-8 w-8 shrink-0 items-center justify-center rounded-xl border text-slate-300 transition hover:border-rose-500/40 hover:bg-rose-500/10 hover:text-rose-300"
                :class="deleteButtonClass"
                :title="`Verwijder ${item.name}`"
            >
                <TrashIcon class="h-4 w-4" />
            </button>
        </div>

        <div class="mt-3 flex items-center justify-between gap-3">
            <div class="flex items-center gap-2">
                <button
                    type="button"
                    @click="store.decreaseItem(item.line_id)"
                    class="flex h-8 w-8 items-center justify-center rounded-xl border text-sm font-bold text-white transition"
                    :class="controlButtonClass"
                >
                    −
                </button>

                <div
                    class="min-w-[2rem] text-center text-sm font-semibold"
                    :class="quantityClass"
                >
                    {{ item.quantity }}
                </div>

                <button
                    type="button"
                    @click="store.increaseItem(item.line_id)"
                    class="flex h-8 w-8 items-center justify-center rounded-xl border text-sm font-bold text-white transition"
                    :class="controlButtonClass"
                >
                    +
                </button>
            </div>

            <div
                class="rounded-xl px-2.5 py-1 text-sm font-bold"
                :class="totalClass"
            >
                € {{ formatPrice(item.price_incl_vat * item.quantity) }}
            </div>
        </div>
    </div>
</template>

<script setup>
import { computed } from 'vue'
import { TrashIcon } from '@heroicons/vue/24/outline'
import { usePosStore } from '../../stores/usePosStore.js'

const store = usePosStore()

const props = defineProps({
    item: {
        type: Object,
        required: true,
    },
    isLastAdded: {
        type: Boolean,
        default: false,
    },
})

const isAutomatic = computed(() => props.item?.source === 'pricing_engine')

const matchedProduct = computed(() => {
    const productId = Number(props.item?.product_id ?? 0)
    if (!productId) {
        return null
    }

    return store.products.find(product => Number(product.id) === productId) ?? null
})

const imageUrl = computed(() => {
    const imagePath = matchedProduct.value?.image_path ?? null

    if (!imagePath) return null
    if (String(imagePath).startsWith('http')) return imagePath
    return `/storage/${imagePath}`
})

const metaTextClass = computed(() => {
    return isAutomatic.value ? 'text-cyan-300/75' : 'text-slate-400'
})

const deleteButtonClass = computed(() => {
    return isAutomatic.value
        ? 'border-cyan-900/70 bg-cyan-950/25 hover:bg-rose-500/10'
        : 'border-slate-700 bg-slate-800/80 hover:bg-rose-500/10'
})

const controlButtonClass = computed(() => {
    return isAutomatic.value
        ? 'border-cyan-900/70 bg-cyan-950/25 hover:bg-cyan-900/45'
        : 'border-slate-700 bg-slate-800/80 hover:bg-slate-700'
})

const quantityClass = computed(() => {
    return isAutomatic.value ? 'text-cyan-100' : 'text-white'
})

const totalClass = computed(() => {
    return isAutomatic.value
        ? 'bg-cyan-500/10 text-cyan-100 ring-1 ring-inset ring-cyan-400/15'
        : 'bg-slate-800/80 text-white ring-1 ring-inset ring-white/5'
})

function formatPrice(value) {
    return new Intl.NumberFormat('nl-BE', {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2,
    }).format(Number(value ?? 0))
}
</script>

<style scoped>
.checkout-row-new {
    animation: checkout-row-in 0.26s ease-out;
}

@keyframes checkout-row-in {
    from {
        opacity: 0;
        transform: translateY(6px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}
</style>
