<template>
    <div>
        <label class="mb-2 block text-xs font-medium text-slate-400">
            {{ label }}
        </label>

        <div class="flex items-center overflow-hidden rounded-xl border border-slate-700 bg-slate-800">
            <button
                type="button"
                class="inline-flex h-12 w-12 items-center justify-center text-lg font-semibold text-slate-300 transition hover:bg-slate-700"
                @click="decrease"
            >
                −
            </button>

            <div class="flex-1 text-center text-base font-semibold text-slate-100">
                {{ modelValue }}
            </div>

            <button
                type="button"
                class="inline-flex h-12 w-12 items-center justify-center text-lg font-semibold text-slate-300 transition hover:bg-slate-700"
                @click="increase"
            >
                +
            </button>
        </div>
    </div>
</template>

<script setup>
const props = defineProps({
    modelValue: {
        type: Number,
        required: true,
    },
    label: {
        type: String,
        required: true,
    },
    min: {
        type: Number,
        default: 0,
    },
})

const emit = defineEmits(['update:modelValue'])

function increase() {
    emit('update:modelValue', Number(props.modelValue || 0) + 1)
}

function decrease() {
    const next = Number(props.modelValue || 0) - 1
    emit('update:modelValue', Math.max(props.min, next))
}
</script>
