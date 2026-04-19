<template>
    <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-5">
        <article
            v-for="card in cards"
            :key="card.label"
            class="rounded-3xl border p-5 shadow-xl"
            :class="card.wrapperClass"
        >
            <p class="text-sm font-medium" :class="card.labelClass">{{ card.label }}</p>
            <p class="mt-3 text-3xl font-semibold" :class="card.valueClass">{{ card.value }}</p>
            <p class="mt-1 text-sm" :class="card.descriptionClass">{{ card.description }}</p>
        </article>
    </div>
</template>

<script setup>
import { computed } from 'vue'

const props = defineProps({
    summary: {
        type: Object,
        default: () => ({ total: 0, none: 0, active: 0, expiring_soon: 0, expired: 0 }),
    },
})

const cards = computed(() => [
    {
        label: 'Totaal',
        value: props.summary.total ?? 0,
        description: 'Alle gekoppelde leden.',
        wrapperClass: 'border-slate-800 bg-slate-900',
        labelClass: 'text-slate-400',
        valueClass: 'text-white',
        descriptionClass: 'text-slate-500',
    },
    {
        label: 'Geen abonnement',
        value: props.summary.none ?? 0,
        description: 'Geregistreerd, nog niet geactiveerd.',
        wrapperClass: 'border-cyan-500/20 bg-cyan-500/5',
        labelClass: 'text-cyan-400',
        valueClass: 'text-cyan-200',
        descriptionClass: 'text-cyan-400/70',
    },
    {
        label: 'Actief',
        value: props.summary.active ?? 0,
        description: 'Geldig en actief.',
        wrapperClass: 'border-emerald-500/20 bg-emerald-500/10',
        labelClass: 'text-emerald-300',
        valueClass: 'text-emerald-200',
        descriptionClass: 'text-emerald-300/70',
    },
    {
        label: 'Vervalt binnenkort',
        value: props.summary.expiring_soon ?? 0,
        description: 'Binnen 30 dagen.',
        wrapperClass: 'border-amber-500/20 bg-amber-500/10',
        labelClass: 'text-amber-300',
        valueClass: 'text-amber-200',
        descriptionClass: 'text-amber-300/70',
    },
    {
        label: 'Vervallen',
        value: props.summary.expired ?? 0,
        description: 'Verlenging nodig.',
        wrapperClass: 'border-rose-500/20 bg-rose-500/10',
        labelClass: 'text-rose-300',
        valueClass: 'text-rose-200',
        descriptionClass: 'text-rose-300/70',
    },
])
</script>
