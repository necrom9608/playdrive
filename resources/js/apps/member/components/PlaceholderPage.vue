<template>
    <div class="px-4 pt-3 pb-6">
        <h1 class="text-lg font-semibold text-white mb-6">{{ title }}</h1>
        <div class="glass-card rounded-3xl p-8 text-center space-y-4">
            <div
                class="w-16 h-16 rounded-2xl flex items-center justify-center mx-auto"
                :class="iconBg"
            >
                <component :is="icon" class="w-8 h-8" :class="iconColor" />
            </div>
            <div>
                <p class="text-base font-semibold text-white mb-1">Binnenkort beschikbaar</p>
                <p class="text-sm text-slate-400 leading-relaxed">{{ description }}</p>
            </div>
        </div>
    </div>
</template>

<script setup>
defineProps({
    title: String,
    icon: Object,
    iconBg: { type: String, default: 'bg-slate-700/50 border border-slate-600/40' },
    iconColor: { type: String, default: 'text-slate-400' },
    description: { type: String, default: 'Deze functie is nog in ontwikkeling.' },
})
</script>
