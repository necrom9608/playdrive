<template>
    <section class="rounded-2xl border border-slate-800 bg-slate-900/40 p-6">
        <div class="mb-4">
            <h2 class="text-lg font-semibold text-white">{{ title }}</h2>
            <p v-if="subtitle" class="mt-1 text-sm text-slate-400">{{ subtitle }}</p>
        </div>
        <div class="space-y-4">
            <slot />
        </div>
    </section>
</template>

<script setup>
defineProps({
    title: { type: String, required: true },
    subtitle: { type: String, default: null },
})
</script>
