
<template>
    <section class="flex h-full min-h-0 flex-col gap-6">
        <div class="rounded-3xl border border-slate-800 bg-slate-900/90 p-6 shadow-xl">
            <div class="mb-6 flex justify-between">
                <h1 class="text-2xl text-white">Dagtotalen</h1>
                <a :href="exportUrl" class="text-emerald-300">Export Excel</a>
            </div>

            <select v-model="filters.mode">
                <option value="month">Maand</option>
                <option value="quarter">Kwartaal</option>
                <option value="range">Range</option>
            </select>

            <button @click="loadData">Load</button>

            <div>{{ period.start }} → {{ period.end }}</div>
        </div>

        <table>
            <tr v-for="row in rows" :key="row.date">
                <td>{{ row.date }}</td>
                <td>{{ row.total_incl_vat }}</td>
            </tr>
        </table>
    </section>
</template>

<script setup>
import { ref, reactive, computed, onMounted } from 'vue'
import { fetchDayTotals, getDayTotalsExportUrl } from '../services/dayTotalsApi'

const rows = ref([])
const period = reactive({ start: null, end: null })

const filters = reactive({
    mode: 'month',
    month: new Date().toISOString().slice(0,7),
    quarter: '2026-Q1',
})

const exportUrl = computed(() => getDayTotalsExportUrl(filters))

async function loadData(){
    const data = await fetchDayTotals(filters)
    rows.value = data.rows
    period.start = data.period.start
    period.end = data.period.end
}

onMounted(loadData)
</script>
