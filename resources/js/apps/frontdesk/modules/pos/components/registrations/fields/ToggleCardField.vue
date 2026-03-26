<template>
    <button
        type="button"
        class="flex items-center justify-between rounded-xl border px-4 py-3 text-left transition"
        :class="modelValue
            ? 'border-blue-500/40 bg-blue-500/10'
            : 'border-slate-700 bg-slate-800 hover:bg-slate-700'"
        @click="$emit('update:modelValue', !modelValue)"
    >
        <div class="flex items-center gap-3">
            <span class="text-lg">{{ emoji }}</span>

            <div class="text-sm font-medium text-slate-100">
                {{ label }}
            </div>
        </div>

        <div
            class="flex h-5 w-5 items-center justify-center rounded-md border text-[11px] font-bold"
            :class="modelValue
                ? 'border-blue-300 bg-blue-500 text-white'
                : 'border-slate-600 bg-slate-900 text-transparent'"
        >
            ✓
        </div>
    </button>
</template>

<script setup>
defineProps({
    modelValue: {
        type: Boolean,
        required: true,
    },
    label: {
        type: String,
        required: true,
    },
    emoji: {
        type: String,
        default: '✓',
    },
})

defineEmits(['update:modelValue'])
</script>
