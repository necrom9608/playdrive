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
        class="group relative flex aspect-square overflow-hidden rounded-xl border border-slate-700 bg-slate-900 text-left transition duration-150 hover:border-blue-500 active:scale-[0.97] disabled:cursor-not-allowed disabled:opacity-90"
        :class="pressed ? 'scale-[0.96] border-blue-500 ring-2 ring-blue-500/40' : ''"
    >
        <div
            v-if="pressed"
            class="pointer-events-none absolute inset-0 z-20 rounded-xl bg-white/10"
        />

        <template v-if="imageUrl">
            <img
                :src="imageUrl"
                :alt="product.name"
                class="absolute inset-0 h-full w-full object-cover transition duration-150 group-hover:scale-[1.02]"
            />
        </template>

        <template v-else>
            <div class="absolute inset-0 bg-slate-800" />
            <div class="absolute inset-0 bg-[radial-gradient(circle_at_top,_rgba(59,130,246,0.18),_transparent_55%)]" />
            <div class="absolute inset-0 flex items-center justify-center px-3 text-center text-[11px] font-semibold tracking-[0.18em] text-slate-500">
                GEEN FOTO
            </div>
        </template>

        <div class="absolute inset-x-0 bottom-0 z-10 bg-gradient-to-t from-slate-950/95 via-slate-950/78 to-transparent px-3 pb-3 pt-8">
            <div class="line-clamp-2 text-sm font-semibold leading-tight text-white drop-shadow-sm">
                {{ product.name }}
            </div>

            <div class="mt-1 text-base font-bold text-blue-400 drop-shadow-sm">
                € {{ formatPrice(product.price_incl_vat ?? product.price ?? 0) }}
            </div>
        </div>
    </button>
</template>
