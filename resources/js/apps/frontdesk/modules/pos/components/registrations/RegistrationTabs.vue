<template>
    <div class="mb-4 shrink-0">
        <div class="grid grid-cols-3 gap-3">
            <button
                type="button"
                @click="$emit('update:modelValue', 'quick')"
                :class="modelValue === 'quick'
                    ? 'border-blue-600 bg-blue-600 text-white shadow-sm'
                    : 'border-slate-700 bg-slate-800 text-slate-300 hover:bg-slate-700'"
                class="rounded-xl border px-4 py-2.5 text-sm font-semibold transition"
            >
                1. Gegevens
            </button>

            <button
                type="button"
                @click="$emit('update:modelValue', 'details')"
                :class="modelValue === 'details'
                    ? 'border-blue-600 bg-blue-600 text-white shadow-sm'
                    : 'border-slate-700 bg-slate-800 text-slate-300 hover:bg-slate-700'"
                class="rounded-xl border px-4 py-2.5 text-sm font-semibold transition"
            >
                2. Reservatie
            </button>

            <button
                type="button"
                @click="$emit('update:modelValue', 'invoice')"
                :class="modelValue === 'invoice'
                    ? 'border-blue-600 bg-blue-600 text-white shadow-sm'
                    : 'border-slate-700 bg-slate-800 text-slate-300 hover:bg-slate-700'"
                class="rounded-xl border px-4 py-2.5 text-sm font-semibold transition"
            >
                3. Facturatie
            </button>
        </div>
    </div>
</template>

<script setup>
defineProps({
    modelValue: {
        type: String,
        required: true,
    },
})

defineEmits(['update:modelValue'])
</script>
