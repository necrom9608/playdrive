<template>
    <button
        type="button"
        role="switch"
        :aria-checked="modelValue"
        class="relative inline-flex h-6 w-11 shrink-0 cursor-pointer rounded-full border-2 border-transparent transition-colors duration-200 ease-in-out focus:outline-none focus-visible:ring-2 focus-visible:ring-blue-500"
        :class="modelValue ? 'bg-blue-600' : 'bg-slate-700'"
        @click="$emit('update:modelValue', !modelValue)"
    >
        <span
            class="pointer-events-none inline-block h-5 w-5 rounded-full bg-white shadow-lg ring-0 transition duration-200 ease-in-out"
            :class="modelValue ? 'translate-x-5' : 'translate-x-0'"
        />
    </button>
</template>

<script setup>
defineProps({
    modelValue: { type: Boolean, default: false },
})
defineEmits(['update:modelValue'])
</script>
