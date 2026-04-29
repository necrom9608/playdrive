<template>
    <div>
        <label class="mb-1.5 flex items-center gap-1 text-sm font-medium text-slate-300">
            {{ label }}
            <span v-if="required" class="text-rose-400">*</span>
        </label>
        <slot />
        <p v-if="hint" class="mt-1.5 text-xs text-slate-500">{{ hint }}</p>
    </div>
</template>

<script setup>
defineProps({
    label: { type: String, required: true },
    hint: { type: String, default: null },
    required: { type: Boolean, default: false },
})
</script>
