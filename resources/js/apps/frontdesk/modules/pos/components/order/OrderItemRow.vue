<template>
    <div
        class="rounded-2xl border p-3 transition"
        :class="wrapperClass"
    >
        <div class="flex items-start justify-between gap-3">
            <div class="min-w-0">
                <div class="flex flex-wrap items-center gap-2">
                    <div class="truncate text-sm font-semibold text-white">
                        {{ item.name }}
                    </div>

                    <span
                        class="inline-flex items-center rounded-full px-2 py-0.5 text-[11px] font-semibold"
                        :class="badgeClass"
                    >
                        {{ sourceLabel }}
                    </span>
                </div>

                <div class="mt-1 text-xs text-slate-400">
                    € {{ formatPrice(item.price_incl_vat) }} / stuk
                </div>
            </div>

            <button
                type="button"
                @click="store.removeItem(item.line_id)"
                class="inline-flex h-8 w-8 items-center justify-center rounded-xl border transition"
                :class="deleteButtonClass"
                :title="`Verwijder ${item.name}`"
            >
                <TrashIcon class="h-4 w-4" />
            </button>
        </div>

        <div class="mt-3 flex items-center justify-between">
            <div class="flex items-center gap-2">
                <button
                    type="button"
                    @click="store.decreaseItem(item.line_id)"
                    class="flex h-8 w-8 items-center justify-center rounded-xl border text-sm font-bold text-white transition"
                    :class="controlButtonClass"
                >
                    −
                </button>

                <div class="min-w-[2rem] text-center text-sm font-semibold text-white">
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

            <div class="text-sm font-bold text-white">
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

const sourceLabel = computed(() => {
    return isAutomatic.value ? 'Automatisch' : 'Manueel'
})

const wrapperClass = computed(() => {
    if (isAutomatic.value) {
        return props.isLastAdded
            ? 'border-cyan-500/50 bg-cyan-500/10 ring-2 ring-cyan-400/20'
            : 'border-cyan-900/70 bg-cyan-950/30'
    }

    return props.isLastAdded
        ? 'border-blue-500/50 bg-slate-950 ring-2 ring-blue-500/30'
        : 'border-slate-800 bg-slate-950'
})

const badgeClass = computed(() => {
    return isAutomatic.value
        ? 'bg-cyan-500/15 text-cyan-200 ring-1 ring-inset ring-cyan-400/20'
        : 'bg-amber-500/15 text-amber-200 ring-1 ring-inset ring-amber-400/20'
})

const deleteButtonClass = computed(() => {
    return isAutomatic.value
        ? 'border-cyan-800/80 bg-cyan-950/40 text-cyan-200 hover:border-rose-500/40 hover:bg-rose-500/10 hover:text-rose-300'
        : 'border-slate-700 bg-slate-800 text-slate-300 hover:border-rose-500/40 hover:bg-rose-500/10 hover:text-rose-300'
})

const controlButtonClass = computed(() => {
    return isAutomatic.value
        ? 'border-cyan-800/80 bg-cyan-950/40 hover:bg-cyan-900/60'
        : 'border-slate-700 bg-slate-800 hover:bg-slate-700'
})

function formatPrice(value) {
    return new Intl.NumberFormat('nl-BE', {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2,
    }).format(Number(value ?? 0))
}
</script>
