<template>
    <div class="rounded-2xl border border-slate-800 bg-slate-950 p-3">
        <div class="flex items-start justify-between gap-3">
            <div class="min-w-0">
                <div class="truncate text-sm font-semibold text-white">
                    {{ item.name }}
                </div>
                <div class="mt-1 text-xs text-slate-500">
                    € {{ formatPrice(item.price_incl_vat) }} / stuk
                </div>
            </div>

            <button
                type="button"
                @click="store.removeItem(item.id)"
                class="text-xs font-semibold text-rose-400 transition hover:text-rose-300"
            >
                Verwijder
            </button>
        </div>

        <div class="mt-3 flex items-center justify-between">
            <div class="flex items-center gap-2">
                <button
                    type="button"
                    @click="store.decreaseItem(item.id)"
                    class="flex h-8 w-8 items-center justify-center rounded-xl border border-slate-700 bg-slate-800 text-sm font-bold text-white hover:bg-slate-700"
                >
                    −
                </button>

                <div class="min-w-[2rem] text-center text-sm font-semibold text-white">
                    {{ item.quantity }}
                </div>

                <button
                    type="button"
                    @click="store.increaseItem(item.id)"
                    class="flex h-8 w-8 items-center justify-center rounded-xl border border-slate-700 bg-slate-800 text-sm font-bold text-white hover:bg-slate-700"
                >
                    +
                </button>
            </div>

            <div class="text-sm font-bold text-white">
                € {{ formatPrice(item.price_incl_vat * item.quantity) }}
            </div>
        </div>
    </div>
</template>

<script setup>
import { usePosStore } from '../../stores/usePosStore.js'

const store = usePosStore()

defineProps({
    item: {
        type: Object,
        required: true,
    },
})

function formatPrice(value) {
    return Number(value).toFixed(2)
}
</script>
