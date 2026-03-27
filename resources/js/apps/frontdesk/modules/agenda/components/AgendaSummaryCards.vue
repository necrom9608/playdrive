<template>
    <div class="grid grid-cols-1 gap-4 md:grid-cols-2 xl:grid-cols-4">
        <div
            v-for="card in cards"
            :key="card.label"
            class="rounded-3xl border border-slate-800 bg-slate-900 p-4 shadow-xl"
        >
            <p class="text-sm text-slate-400">{{ card.label }}</p>
            <p class="mt-2 text-3xl font-bold text-white">{{ card.value }}</p>
        </div>
    </div>
</template>

<script setup>
import { computed } from 'vue'
import { useAgendaStore } from '../stores/useAgendaStore'

const store = useAgendaStore()

const cards = computed(() => {
    const summary = store.summary ?? {}

    return [
        { label: 'Reservaties', value: summary.reservations ?? 0 },
        { label: 'Totaal personen', value: summary.participants ?? 0 },
        { label: 'Kinderen', value: summary.children ?? 0 },
        { label: 'Volwassenen + begeleiders', value: (summary.adults ?? 0) + (summary.supervisors ?? 0) },
    ]
})
</script>
