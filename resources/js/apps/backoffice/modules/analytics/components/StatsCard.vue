<template>
    <section class="rounded-3xl border border-slate-800 bg-slate-900/90 p-5 shadow-xl">
        <div class="flex items-start justify-between gap-3">
            <div>
                <p class="text-sm font-medium text-slate-400">{{ label }}</p>
                <p class="mt-3 text-3xl font-bold text-white">{{ value }}</p>
                <p v-if="subtext" class="mt-2 text-sm text-slate-400">{{ subtext }}</p>
            </div>
            <div v-if="badge" class="rounded-2xl border border-emerald-500/20 bg-emerald-500/10 px-3 py-2 text-xs font-semibold text-emerald-200">
                {{ badge }}
            </div>
        </div>
    </section>
</template>

<script setup>
defineProps({
    label: { type: String, required: true },
    value: { type: String, required: true },
    subtext: { type: String, default: '' },
    badge: { type: String, default: '' },
})
</script>
