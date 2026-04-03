<template>
    <section class="rounded-3xl border border-slate-800 bg-slate-900/90 p-5 shadow-xl">
        <div class="mb-4 flex items-center justify-between gap-3">
            <div>
                <h3 class="text-lg font-semibold text-white">{{ title }}</h3>
                <p v-if="subtitle" class="mt-1 text-sm text-slate-400">{{ subtitle }}</p>
            </div>
            <span class="rounded-full border border-slate-700 bg-slate-950/70 px-3 py-1 text-xs text-slate-300">{{ rows.length }} rijen</span>
        </div>

        <div v-if="rows.length" class="overflow-hidden rounded-3xl border border-slate-800 bg-slate-950/70">
            <table class="min-w-full divide-y divide-slate-800 text-sm">
                <thead class="bg-slate-900/80 text-slate-400">
                    <tr>
                        <th class="px-4 py-3 text-left font-semibold">{{ firstColumnLabel }}</th>
                        <th v-for="column in columns" :key="column.key" class="px-4 py-3 text-right font-semibold">{{ column.label }}</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-800 text-slate-200">
                    <tr v-for="row in rows" :key="row.label">
                        <td class="px-4 py-3 font-medium text-white">{{ row.label }}</td>
                        <td v-for="column in columns" :key="column.key" class="px-4 py-3 text-right">
                            {{ formatCell(row[column.key], column.type) }}
                        </td>
                    </tr>
                </tbody>
            </table>
        </div>

        <div v-else class="rounded-3xl border border-dashed border-slate-700 bg-slate-950/60 px-4 py-10 text-center text-sm text-slate-400">
            Geen data beschikbaar voor deze selectie.
        </div>
    </section>
</template>

<script setup>
const props = defineProps({
    title: { type: String, required: true },
    subtitle: { type: String, default: '' },
    rows: { type: Array, required: true },
    columns: { type: Array, required: true },
    firstColumnLabel: { type: String, default: 'Naam' },
})

function formatCell(value, type = 'text') {
    if (type === 'currency') {
        return new Intl.NumberFormat('nl-BE', { style: 'currency', currency: 'EUR' }).format(Number(value || 0))
    }

    if (type === 'number') {
        return new Intl.NumberFormat('nl-BE').format(Number(value || 0))
    }

    return value ?? '—'
}
</script>
