<template>
    <span class="rounded-full border border-white/10 bg-black/20 px-2.5 py-1 text-xs font-semibold text-white/90">
        {{ label }}
    </span>
</template>

<script setup>
defineProps({
    label: {
        type: String,
        required: true,
    },
})
</script>
