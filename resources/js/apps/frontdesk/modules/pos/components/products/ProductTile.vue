<script setup>
import { computed, ref } from 'vue'

const props = defineProps({
    product: {
        type: Object,
        required: true,
    },
})

const emit = defineEmits(['select'])

const pressed = ref(false)

const imageUrl = computed(() => {
    if (!props.product.image_path) return null

    if (props.product.image_path.startsWith('http')) {
        return props.product.image_path
    }

    return `/storage/${props.product.image_path}`
})

function handleClick() {
    pressed.value = true

    window.setTimeout(() => {
        pressed.value = false
    }, 180)

    emit('select', props.product)
}

function formatPrice(value) {
    const number = Number(value ?? 0)

    return new Intl.NumberFormat('nl-BE', {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2,
    }).format(number)
}
</script>

<template>
    <button
        type="button"
        @click="handleClick"
        class="group relative flex aspect-square flex-col overflow-hidden rounded-xl border border-slate-700 bg-slate-800 text-left transition duration-150 hover:border-blue-500 hover:bg-slate-700 active:scale-[0.97] disabled:cursor-not-allowed disabled:opacity-90"
        :class="pressed ? 'scale-[0.96] border-blue-500 ring-2 ring-blue-500/40 bg-slate-700' : ''"
    >
        <div
            v-if="pressed"
            class="pointer-events-none absolute inset-0 z-10 rounded-xl bg-white/10"
        />

        <div class="flex flex-1 items-center justify-center bg-slate-900 p-2">
            <img
                v-if="imageUrl"
                :src="imageUrl"
                :alt="product.name"
                class="max-h-16 max-w-full object-contain opacity-90 transition group-hover:opacity-100"
            />

            <div
                v-else
                class="flex h-12 w-12 items-center justify-center rounded-md border border-slate-700 bg-slate-800 px-1 text-center text-[9px] leading-tight text-slate-500"
            >
                GEEN FOTO
            </div>
        </div>

        <div class="border-t border-slate-700 p-2">
            <div class="truncate text-xs font-semibold text-white">
                {{ product.name }}
            </div>

            <div class="mt-1 text-sm font-bold text-blue-400">
                € {{ formatPrice(product.price_incl_vat ?? product.price ?? 0) }}
            </div>
        </div>
    </button>
</template>
