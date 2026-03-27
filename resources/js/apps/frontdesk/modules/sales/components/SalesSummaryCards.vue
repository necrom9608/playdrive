<template>
    <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-5">
        <div
            v-for="card in cards"
            :key="card.label"
            class="rounded-3xl border border-slate-800 bg-slate-900 p-5 shadow-xl"
        >
            <div class="text-xs font-medium uppercase tracking-wide text-slate-400">
                {{ card.label }}
            </div>

            <div class="mt-3 text-3xl font-bold text-white">
                {{ card.value }}
            </div>

            <div v-if="card.meta" class="mt-2 text-sm text-slate-400">
                {{ card.meta }}
            </div>
        </div>
    </div>
</template>

<script setup>
import { computed } from 'vue'
import { useSalesStore } from '../stores/useSalesStore.js'

const store = useSalesStore()

function formatPrice(value) {
    return `€ ${new Intl.NumberFormat('nl-BE', {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2,
    }).format(Number(value ?? 0))}`
}

const cards = computed(() => {
    const summary = store.summary ?? {
        total_revenue: 0,
        total_orders: 0,
        cash_revenue: 0,
        bancontact_revenue: 0,
        invoice_revenue: 0,
        invoice_order_count: 0,
        non_invoice_revenue: 0,
        walk_in_order_count: 0,
        reservation_order_count: 0,
    }

    return [
        {
            label: 'Omzet',
            value: formatPrice(summary.total_revenue),
            meta: `${summary.total_orders} orders`,
        },
        {
            label: 'Cash',
            value: formatPrice(summary.cash_revenue),
            meta: 'Contante betalingen',
        },
        {
            label: 'Bancontact',
            value: formatPrice(summary.bancontact_revenue),
            meta: 'Elektronische betalingen',
        },
        {
            label: 'Factuur nodig',
            value: formatPrice(summary.invoice_revenue),
            meta: `${summary.invoice_order_count} orders`,
        },
        {
            label: 'Zonder factuur',
            value: formatPrice(summary.non_invoice_revenue),
            meta: `${summary.walk_in_order_count} losse / ${summary.reservation_order_count} reservaties`,
        },
    ]
})
</script>
