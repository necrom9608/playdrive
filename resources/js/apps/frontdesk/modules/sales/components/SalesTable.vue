<template>
    <div class="flex h-full min-h-0 flex-col rounded-3xl border border-slate-800 bg-slate-900 shadow-xl">
        <div class="border-b border-slate-800 p-4">
            <h2 class="text-base font-semibold text-white">Verkopen</h2>
            <p class="text-sm text-slate-400">Selecteer een order om details te bekijken.</p>
        </div>

        <div class="min-h-0 flex-1 overflow-auto">
            <table class="w-full text-sm">
                <thead class="text-slate-400">
                <tr>
                    <th class="p-3 text-left">Tijd</th>
                    <th class="p-3 text-left">Order</th>
                    <th class="p-3 text-left">Type</th>
                    <th class="p-3 text-left">Betaling</th>
                    <th class="p-3 text-left">Factuur</th>
                    <th class="p-3 text-left">Status</th>
                    <th class="p-3 text-right">Totaal</th>
                </tr>
                </thead>

                <tbody>
                <tr
                    v-for="order in store.orders"
                    :key="order.id"
                    @click="store.selectOrder(order.id)"
                    class="cursor-pointer border-t border-slate-800 transition hover:bg-slate-800/50"
                    :class="store.selectedOrder && store.selectedOrder.id === order.id ? 'bg-slate-800' : ''"
                >
                    <td class="p-3">{{ order.paid_time || '-' }}</td>

                    <td class="p-3">
                        <div class="font-semibold text-white">#{{ order.id }}</div>
                        <div class="mt-1 text-xs text-slate-500">
                            {{ order.registration && order.registration.name ? order.registration.name : 'Losse verkoop' }}
                        </div>
                    </td>

                    <td class="p-3">
                        {{ order.source === 'reservation' ? 'Reservatie' : 'Losse verkoop' }}
                    </td>

                    <td class="p-3">
                            <span class="inline-flex rounded-full border border-emerald-500/30 bg-emerald-500/10 px-2.5 py-1 text-xs font-semibold text-emerald-300">
                                {{ order.payment_method === 'bancontact' ? 'Bancontact' : 'Cash' }}
                            </span>
                    </td>

                    <td class="p-3">
                            <span
                                class="inline-flex rounded-full border px-2.5 py-1 text-xs font-semibold"
                                :class="order.invoice_requested
                                    ? 'border-amber-500/30 bg-amber-500/10 text-amber-300'
                                    : 'border-slate-600 bg-slate-700/40 text-slate-300'"
                            >
                                {{ order.invoice_requested ? 'Factuur' : 'Geen factuur' }}
                            </span>
                    </td>

                    <td class="p-3">
                            <span
                                v-if="order.status === 'cancelled' || order.cancelled_at"
                                class="inline-flex rounded-full border border-amber-500/30 bg-amber-500/10 px-2.5 py-1 text-xs font-semibold text-amber-300"
                            >
                                Geannuleerd
                            </span>

                        <span
                            v-else-if="order.refunded_at"
                            class="inline-flex rounded-full border border-rose-500/30 bg-rose-500/10 px-2.5 py-1 text-xs font-semibold text-rose-300"
                        >
                                Terugbetaald
                            </span>

                        <span
                            v-else
                            class="inline-flex rounded-full border border-sky-500/30 bg-sky-500/10 px-2.5 py-1 text-xs font-semibold text-sky-300"
                        >
                                Betaald
                            </span>
                    </td>

                    <td class="p-3 text-right font-semibold text-white">
                        € {{ formatPrice(order.total_incl_vat) }}
                    </td>
                </tr>

                <tr v-if="!store.orders || !store.orders.length">
                    <td colspan="7" class="p-6 text-center text-sm text-slate-400">
                        Geen verkopen gevonden voor deze filters.
                    </td>
                </tr>
                </tbody>
            </table>
        </div>

        <div class="border-t border-slate-800 p-4">
            <div class="grid grid-cols-2 gap-3">
                <button
                    class="rounded-2xl border border-slate-700 bg-slate-800 px-4 py-3 text-sm font-semibold text-white transition hover:bg-slate-700 disabled:cursor-not-allowed disabled:opacity-50"
                    :disabled="!canCancel"
                    @click="showCancelModal = true"
                >
                    Order annuleren
                </button>

                <button
                    class="rounded-2xl border border-rose-500/40 bg-rose-500/10 px-4 py-3 text-sm font-semibold text-rose-200 transition hover:bg-rose-500/20 disabled:cursor-not-allowed disabled:opacity-50"
                    :disabled="!canRefund"
                    @click="showRefundModal = true"
                >
                    Terugbetaling
                </button>
            </div>
        </div>

        <CancelOrderModal
            :open="showCancelModal"
            :order="store.selectedOrder"
            @close="showCancelModal = false"
        />

        <RefundOrderModal
            :open="showRefundModal"
            :order="store.selectedOrder"
            @close="showRefundModal = false"
        />
    </div>
</template>

<script setup>
import { computed, ref } from 'vue'
import { useSalesStore } from '../stores/useSalesStore.js'
import CancelOrderModal from './CancelOrderModal.vue'
import RefundOrderModal from './RefundOrderModal.vue'

const store = useSalesStore()

const showCancelModal = ref(false)
const showRefundModal = ref(false)

const canCancel = computed(() => {
    const order = store.selectedOrder
    return !!order && !(order.status === 'cancelled' || order.cancelled_at) && !order.refunded_at
})

const canRefund = computed(() => {
    const order = store.selectedOrder
    return !!order && !(order.status === 'cancelled' || order.cancelled_at) && !order.refunded_at && order.status === 'paid'
})

function formatPrice(value) {
    return new Intl.NumberFormat('nl-BE', {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2,
    }).format(Number(value ?? 0))
}
</script>
