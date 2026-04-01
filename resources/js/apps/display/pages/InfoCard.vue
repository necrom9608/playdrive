<template>
    <div class="rounded-3xl border border-white/10 bg-slate-950/50 p-5">
        <p class="text-xs uppercase tracking-[0.22em] text-slate-400">{{ label }}</p>
        <p class="mt-3 text-2xl font-semibold text-white">{{ value }}</p>
    </div>
</template>

<script setup>
defineProps({
    label: {
        type: String,
        required: true,
    },
    value: {
        type: String,
        required: true,
    },
})
</script>
