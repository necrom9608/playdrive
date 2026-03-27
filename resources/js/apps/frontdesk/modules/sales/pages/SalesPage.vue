<template>
    <div class="flex h-full min-h-0 flex-col gap-4">
        <SalesFilters />
        <SalesSummaryCards />

        <div class="grid min-h-0 flex-1 grid-cols-12 gap-4">
            <div class="col-span-7 min-h-0 flex flex-col">
                <SalesTable />
            </div>

            <div class="col-span-5 min-h-0">
                <SaleDetailPanel />
            </div>
        </div>
    </div>
</template>

<script setup>
import { onMounted } from 'vue'
import { useSalesStore } from '../stores/useSalesStore'
import SalesFilters from '../components/SalesFilters.vue'
import SalesSummaryCards from '../components/SalesSummaryCards.vue'
import SalesTable from '../components/SalesTable.vue'
import SaleDetailPanel from '../components/SaleDetailPanel.vue'

const store = useSalesStore()

onMounted(() => {
    store.fetchSales()
})
</script>
