<template>
    <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-4">
        <article
            v-for="card in cards"
            :key="card.label"
            class="rounded-3xl border border-slate-800 bg-slate-900 p-5 shadow-xl"
        >
            <p class="text-sm font-medium text-slate-400">{{ card.label }}</p>
            <p class="mt-3 text-3xl font-semibold text-white">{{ card.value }}</p>
            <p class="mt-1 text-sm text-slate-500">{{ card.description }}</p>
        </article>
    </div>
</template>

<script setup>
import { computed } from 'vue'

const props = defineProps({
    summary: {
        type: Object,
        default: () => ({
            total: 0,
            active: 0,
            expiring_soon: 0,
            expired: 0,
        }),
    },
})

const cards = computed(() => [
    {
        label: 'Totaal',
        value: props.summary.total ?? 0,
        description: 'Alle geregistreerde abonnees.',
    },
    {
        label: 'Actief',
        value: props.summary.active ?? 0,
        description: 'Nog geldig en actief.',
    },
    {
        label: 'Vervalt binnenkort',
        value: props.summary.expiring_soon ?? 0,
        description: 'Binnen 30 dagen.',
    },
    {
        label: 'Vervallen',
        value: props.summary.expired ?? 0,
        description: 'Verlenging nodig of inactief.',
    },
])
</script>
