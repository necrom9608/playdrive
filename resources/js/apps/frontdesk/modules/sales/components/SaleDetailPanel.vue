<template>
    <div class="flex h-full min-h-0 flex-col rounded-3xl border border-slate-800 bg-slate-900 shadow-xl">
        <div class="border-b border-slate-800 p-4">
            <h2 class="text-base font-semibold text-white">Details</h2>
        </div>

        <div v-if="store.selectedOrder" class="min-h-0 flex-1 overflow-auto p-4 space-y-4">
            <div class="rounded-2xl border border-slate-800 bg-slate-950 p-4">
                <div class="text-sm text-slate-400">Order</div>
                <div class="mt-1 text-lg font-bold text-white">#{{ store.selectedOrder.id }}</div>
            </div>

            <div class="rounded-2xl border border-slate-800 bg-slate-950 p-4 space-y-2">
                <div class="flex justify-between text-sm">
                    <span class="text-slate-400">Type</span>
                    <span class="text-white">{{ store.selectedOrder.source === 'reservation' ? 'Reservatie' : 'Losse verkoop' }}</span>
                </div>

                <div class="flex justify-between text-sm">
                    <span class="text-slate-400">Betaalmethode</span>
                    <span class="text-white">{{ store.selectedOrder.payment_method }}</span>
                </div>

                <div class="flex justify-between text-sm">
                    <span class="text-slate-400">Factuur</span>
                    <span class="text-white">{{ store.selectedOrder.invoice_requested ? 'Ja' : 'Nee' }}</span>
                </div>

                <div class="flex justify-between text-sm">
                    <span class="text-slate-400">Totaal</span>
                    <span class="text-white">€ {{ formatPrice(store.selectedOrder.total_incl_vat) }}</span>
                </div>
            </div>

            <div v-if="store.selectedOrder.cancelled_at" class="rounded-2xl border border-amber-500/30 bg-amber-500/10 p-4">
                <div class="text-sm font-semibold text-amber-300">Geannuleerd</div>
                <div v-if="store.selectedOrder.cancellation_reason" class="mt-2 text-sm text-amber-100">
                    {{ store.selectedOrder.cancellation_reason }}
                </div>
            </div>

            <div v-if="store.selectedOrder.refunded_at" class="rounded-2xl border border-rose-500/30 bg-rose-500/10 p-4">
                <div class="text-sm font-semibold text-rose-300">Terugbetaald</div>
                <div class="mt-2 text-sm text-rose-100">
                    € {{ formatPrice(store.selectedOrder.refund_amount) }} via {{ store.selectedOrder.refund_method }}
                </div>
                <div v-if="store.selectedOrder.refund_reason" class="mt-2 text-sm text-rose-100">
                    {{ store.selectedOrder.refund_reason }}
                </div>
            </div>

            <div class="rounded-2xl border border-slate-800 bg-slate-950 p-4">
                <div class="mb-3 text-sm font-semibold text-white">Orderlijnen</div>

                <div class="space-y-2">
                    <div
                        v-for="item in store.selectedOrder.items"
                        :key="item.id"
                        class="flex items-center justify-between rounded-xl border border-slate-800 bg-slate-900 px-3 py-2"
                    >
                        <div>
                            <div class="text-sm font-medium text-white">{{ item.name }}</div>
                            <div class="text-xs text-slate-400">x{{ item.quantity }}</div>
                        </div>

                        <div class="text-sm font-semibold text-white">
                            € {{ formatPrice(item.line_total_incl_vat) }}
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div v-else class="flex flex-1 items-center justify-center p-6 text-sm text-slate-400">
            Selecteer een order om details te bekijken.
        </div>
    </div>
</template>

<script setup>
import { useSalesStore } from '../stores/useSalesStore.js'

const store = useSalesStore()

function formatPrice(value) {
    return new Intl.NumberFormat('nl-BE', {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2,
    }).format(Number(value ?? 0))
}
</script>
