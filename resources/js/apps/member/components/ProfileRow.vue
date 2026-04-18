<template>
    <div class="flex justify-between items-center py-1">
        <span class="text-xs text-slate-400">{{ label }}</span>
        <span class="text-xs text-slate-200 font-medium">{{ value ?? '—' }}</span>
    </div>
</template>

<script setup>
defineProps({ label: String, value: String })
</script>
