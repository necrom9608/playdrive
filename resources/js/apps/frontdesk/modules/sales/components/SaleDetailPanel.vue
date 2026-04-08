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

            <div class="grid gap-3 sm:grid-cols-2">
                <button
                    type="button"
                    class="inline-flex items-center justify-center rounded-2xl border border-sky-500/30 bg-sky-500/10 px-4 py-3 text-sm font-semibold text-sky-100 transition hover:bg-sky-500/20 disabled:cursor-not-allowed disabled:opacity-60"
                    :disabled="receiptActionLoading"
                    @click="handlePrintReceipt"
                >
                    DRUK BON
                </button>

                <button
                    type="button"
                    class="inline-flex items-center justify-center rounded-2xl border border-emerald-500/30 bg-emerald-500/10 px-4 py-3 text-sm font-semibold text-emerald-100 transition hover:bg-emerald-500/20 disabled:cursor-not-allowed disabled:opacity-60"
                    :disabled="receiptActionLoading"
                    @click="openReceiptEmailModal"
                >
                    MAIL BON
                </button>
            </div>

            <div
                v-if="receiptActionMessage"
                class="rounded-2xl border border-emerald-500/30 bg-emerald-500/10 px-4 py-3 text-sm text-emerald-100"
            >
                {{ receiptActionMessage }}
            </div>

            <div
                v-if="receiptActionError"
                class="rounded-2xl border border-rose-500/30 bg-rose-500/10 px-4 py-3 text-sm text-rose-100"
            >
                {{ receiptActionError }}
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

    <teleport to="body">
        <div
            v-if="receiptEmailModalOpen"
            class="fixed inset-0 z-[90] flex items-center justify-center bg-slate-950/80 px-4"
            @click.self="closeReceiptEmailModal"
        >
            <div class="w-full max-w-md rounded-3xl border border-slate-700 bg-slate-900 p-6 shadow-2xl">
                <div class="flex items-start justify-between gap-4">
                    <div>
                        <h3 class="text-lg font-semibold text-white">Bon mailen</h3>
                        <p class="mt-1 text-sm text-slate-400">Geef het e-mailadres in voor bon #{{ store.selectedOrder?.id }}.</p>
                    </div>

                    <button
                        type="button"
                        class="rounded-xl border border-slate-700 px-3 py-1.5 text-sm text-slate-300 transition hover:bg-slate-800"
                        @click="closeReceiptEmailModal"
                    >
                        Sluiten
                    </button>
                </div>

                <div class="mt-5 space-y-3">
                    <label class="block text-sm font-medium text-slate-200" for="sales-receipt-email">
                        E-mailadres
                    </label>
                    <input
                        id="sales-receipt-email"
                        v-model="receiptEmail"
                        type="email"
                        class="w-full rounded-2xl border border-slate-700 bg-slate-950 px-4 py-3 text-sm text-white outline-none transition focus:border-sky-500"
                        placeholder="naam@voorbeeld.be"
                        @keydown.enter.prevent="confirmReceiptEmail"
                    >

                    <p v-if="receiptEmailError" class="text-sm text-rose-300">
                        {{ receiptEmailError }}
                    </p>
                </div>

                <div class="mt-6 flex justify-end gap-3">
                    <button
                        type="button"
                        class="rounded-2xl border border-slate-700 px-4 py-2 text-sm font-medium text-slate-200 transition hover:bg-slate-800"
                        :disabled="receiptActionLoading"
                        @click="closeReceiptEmailModal"
                    >
                        Annuleren
                    </button>

                    <button
                        type="button"
                        class="rounded-2xl border border-emerald-500/30 bg-emerald-500/10 px-4 py-2 text-sm font-semibold text-emerald-100 transition hover:bg-emerald-500/20 disabled:cursor-not-allowed disabled:opacity-60"
                        :disabled="receiptActionLoading"
                        @click="confirmReceiptEmail"
                    >
                        {{ receiptActionLoading ? 'Verzenden...' : 'Verzenden' }}
                    </button>
                </div>
            </div>
        </div>
    </teleport>
</template>

<script setup>
import { ref } from 'vue'
import axios from '@/lib/http'
import { useSalesStore } from '../stores/useSalesStore.js'

const store = useSalesStore()

const receiptActionLoading = ref(false)
const receiptActionError = ref('')
const receiptActionMessage = ref('')
const receiptEmailModalOpen = ref(false)
const receiptEmail = ref('')
const receiptEmailError = ref('')

function formatPrice(value) {
    return new Intl.NumberFormat('nl-BE', {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2,
    }).format(Number(value ?? 0))
}

function resetReceiptFeedback() {
    receiptActionError.value = ''
    receiptActionMessage.value = ''
}

function openReceiptEmailModal() {
    resetReceiptFeedback()
    receiptEmailError.value = ''
    receiptEmail.value = ''
    receiptEmailModalOpen.value = true
}

function closeReceiptEmailModal() {
    if (receiptActionLoading.value) {
        return
    }

    receiptEmailModalOpen.value = false
    receiptEmailError.value = ''
}

function printInHiddenIframe(url) {
    const iframe = document.createElement('iframe')
    iframe.style.position = 'fixed'
    iframe.style.right = '0'
    iframe.style.bottom = '0'
    iframe.style.width = '0'
    iframe.style.height = '0'
    iframe.style.border = '0'
    iframe.setAttribute('aria-hidden', 'true')
    iframe.src = url

    const cleanup = () => {
        window.setTimeout(() => {
            iframe.remove()
        }, 1000)
    }

    iframe.onload = () => {
        const frameWindow = iframe.contentWindow

        if (!frameWindow) {
            cleanup()
            return
        }

        window.setTimeout(() => {
            frameWindow.focus()
            cleanup()
        }, 250)
    }

    document.body.appendChild(iframe)
}

function handlePrintReceipt() {
    const orderId = store.selectedOrder?.id

    if (!orderId || receiptActionLoading.value) {
        return
    }

    resetReceiptFeedback()
    printInHiddenIframe(`/api/frontdesk/orders/${orderId}/receipt?auto_print=1`)
}

async function confirmReceiptEmail() {
    const orderId = store.selectedOrder?.id
    const email = String(receiptEmail.value ?? '').trim()

    if (!orderId || receiptActionLoading.value) {
        return
    }

    if (!email) {
        receiptEmailError.value = 'Geef een e-mailadres in.'
        return
    }

    resetReceiptFeedback()
    receiptEmailError.value = ''
    receiptActionLoading.value = true

    try {
        await axios.post(`/api/frontdesk/orders/${orderId}/send-receipt`, {
            email,
        })

        receiptActionMessage.value = `Bon #${orderId} werd verzonden naar ${email}.`
        receiptEmailModalOpen.value = false
        receiptEmail.value = ''
    } catch (error) {
        console.error('Failed to send receipt email', error)
        receiptEmailError.value = error?.response?.data?.message ?? 'Verzenden van de bon mislukte.'
    } finally {
        receiptActionLoading.value = false
    }
}
</script>
