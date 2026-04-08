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

            <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
                <button
                    type="button"
                    class="rounded-2xl border border-slate-700 bg-slate-800 px-4 py-3 text-sm font-semibold text-white transition hover:bg-slate-700 disabled:cursor-not-allowed disabled:opacity-50"
                    :disabled="!store.selectedOrder?.id"
                    @click="printReceipt"
                >
                    DRUK BON
                </button>

                <button
                    type="button"
                    class="rounded-2xl border border-blue-500/40 bg-blue-500/10 px-4 py-3 text-sm font-semibold text-blue-100 transition hover:bg-blue-500/20 disabled:cursor-not-allowed disabled:opacity-50"
                    :disabled="!store.selectedOrder?.id || sendingReceipt"
                    @click="openEmailModal"
                >
                    MAIL BON
                </button>
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

    <div
        v-if="showEmailModal"
        class="fixed inset-0 z-50 flex items-center justify-center bg-slate-950/80 p-4 backdrop-blur-sm"
        @click.self="closeEmailModal"
    >
        <div class="w-full max-w-md rounded-3xl border border-slate-800 bg-slate-900 p-6 shadow-2xl">
            <div class="flex items-start justify-between gap-4">
                <div>
                    <h3 class="text-lg font-semibold text-white">Bon via e-mail verzenden</h3>
                    <p class="mt-1 text-sm text-slate-400">Geef het e-mailadres in voor order #{{ store.selectedOrder?.id }}.</p>
                </div>

                <button
                    type="button"
                    class="rounded-xl border border-slate-700 px-3 py-2 text-sm font-medium text-slate-300 transition hover:bg-slate-800"
                    @click="closeEmailModal"
                >
                    Sluiten
                </button>
            </div>

            <div class="mt-5">
                <label class="mb-2 block text-sm font-medium text-slate-200">E-mailadres</label>
                <input
                    v-model="receiptEmail"
                    type="email"
                    class="w-full rounded-2xl border border-slate-700 bg-slate-950 px-4 py-3 text-sm text-white outline-none transition placeholder:text-slate-500 focus:border-blue-500"
                    placeholder="naam@voorbeeld.be"
                    @keydown.enter.prevent="sendReceiptEmail"
                >
                <p v-if="receiptEmailError" class="mt-3 text-sm text-rose-300">{{ receiptEmailError }}</p>
            </div>

            <div class="mt-6 flex justify-end gap-3">
                <button
                    type="button"
                    class="rounded-2xl border border-slate-700 px-4 py-3 text-sm font-semibold text-slate-300 transition hover:bg-slate-800"
                    :disabled="sendingReceipt"
                    @click="closeEmailModal"
                >
                    Annuleren
                </button>
                <button
                    type="button"
                    class="rounded-2xl bg-blue-600 px-4 py-3 text-sm font-semibold text-white transition hover:bg-blue-500 disabled:cursor-not-allowed disabled:opacity-50"
                    :disabled="sendingReceipt"
                    @click="sendReceiptEmail"
                >
                    {{ sendingReceipt ? 'Verzenden...' : 'Verzenden' }}
                </button>
            </div>
        </div>
    </div>
</template>

<script setup>
import { ref, watch } from 'vue'
import { useSalesStore } from '../stores/useSalesStore.js'

const store = useSalesStore()

const showEmailModal = ref(false)
const receiptEmail = ref('')
const receiptEmailError = ref('')
const sendingReceipt = ref(false)

watch(() => store.selectedOrder?.id, () => {
    receiptEmail.value = store.selectedOrder?.registration?.email ?? ''
    receiptEmailError.value = ''
    showEmailModal.value = false
    sendingReceipt.value = false
})

function formatPrice(value) {
    return new Intl.NumberFormat('nl-BE', {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2,
    }).format(Number(value ?? 0))
}

function printReceipt() {
    if (!store.selectedOrder?.id) {
        return
    }

    window.open(`/api/frontdesk/orders/${store.selectedOrder.id}/receipt`, '_blank', 'noopener,noreferrer')
}

function openEmailModal() {
    receiptEmail.value = store.selectedOrder?.registration?.email ?? receiptEmail.value ?? ''
    receiptEmailError.value = ''
    showEmailModal.value = true
}

function closeEmailModal() {
    if (sendingReceipt.value) {
        return
    }

    showEmailModal.value = false
    receiptEmailError.value = ''
}

async function sendReceiptEmail() {
    const email = String(receiptEmail.value ?? '').trim()

    if (!email) {
        receiptEmailError.value = 'Geef een e-mailadres in.'
        return
    }

    sendingReceipt.value = true
    receiptEmailError.value = ''

    try {
        await store.sendReceiptForSelectedOrder(email)
        showEmailModal.value = false
    } catch (error) {
        receiptEmailError.value = error?.response?.data?.message ?? store.error ?? 'Verzenden van de bon mislukte.'
    } finally {
        sendingReceipt.value = false
    }
}
</script>
