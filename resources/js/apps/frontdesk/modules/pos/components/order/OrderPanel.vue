<template>
    <div class="flex h-full min-h-0 flex-col rounded-3xl border border-slate-800 bg-slate-900/90 shadow-xl">
        <div class="border-b border-slate-800 px-3 py-3">
            <h2 class="text-lg font-semibold text-white">Bestelling</h2>
            <p class="text-sm text-slate-400">
                {{ store.currentOrderLabel }}
            </p>
        </div>

        <div
            ref="listContainer"
            class="flex-1 space-y-2 overflow-y-auto px-3 py-3"
        >
            <div
                v-if="store.currentOrderItems.length"
                class="space-y-2"
            >
                <OrderItemRow
                    v-for="item in store.currentOrderItems"
                    :key="item.line_id"
                    :item="item"
                    :is-last-added="item.line_id === store.lastAddedLineId"
                />
            </div>

            <div
                v-else
                class="rounded-2xl border border-dashed border-slate-700 bg-slate-950/40 px-4 py-6 text-center"
            >
                <p class="text-sm text-slate-400">
                    Nog geen producten toegevoegd.
                </p>
            </div>
        </div>

        <div class="border-t border-slate-800 px-3 py-3">
            <div class="flex items-center justify-between text-sm text-slate-400">
                <span>Aantal items</span>
                <span class="font-semibold text-white">{{ store.orderCount }}</span>
            </div>

            <div class="mt-3 flex items-end justify-between gap-3">
                <span class="text-sm font-medium text-slate-300">Totaal</span>
                <span class="text-3xl font-bold leading-none text-white">
                    € {{ formatPrice(store.orderSubtotal) }}
                </span>
            </div>

            <div
                v-if="store.checkoutError"
                class="mt-3 rounded-xl border border-rose-500/40 bg-rose-500/10 px-3 py-2 text-xs text-rose-200"
            >
                {{ store.checkoutError }}
            </div>

            <div
                v-if="store.lastCheckoutSummary"
                class="mt-3 rounded-xl border border-emerald-500/40 bg-emerald-500/10 px-3 py-2 text-xs text-emerald-200"
            >
                Checkout geslaagd.
                <span v-if="store.lastCheckoutSummary.order?.id">
                    Order #{{ store.lastCheckoutSummary.order.id }} werd bijgewerkt.
                </span>
            </div>

            <div class="mt-4 grid grid-cols-2 gap-2">
                <button
                    type="button"
                    @click="store.clearOrder()"
                    class="rounded-2xl border border-slate-700 bg-slate-800 px-3 py-3 text-sm font-semibold text-slate-200 transition hover:bg-slate-700"
                >
                    Wissen
                </button>

                <button
                    type="button"
                    class="rounded-2xl bg-blue-600 px-3 py-3 text-sm font-semibold text-white transition hover:bg-blue-500 disabled:cursor-not-allowed disabled:opacity-50"
                    :disabled="!store.currentOrderItems.length"
                    @click="openCheckoutModal"
                >
                    Afrekenen
                </button>
            </div>
        </div>

        <CheckoutModal
            :open="showCheckoutModal"
            :items="store.currentOrderItems"
            :order="store.currentOrder"
            :registration="store.selectedReservation"
            :total-formatted="formatPrice(store.orderSubtotal)"
            :payment-method-model="paymentMethod"
            :invoice-requested-model="invoiceRequested"
            :voucher-code-model="voucherCode"
            :note-model="note"
            :confirm-loading="store.checkoutProcessing"
            :voucher-validation-loading="voucherValidationLoading"
            :voucher-error="voucherError"
            :voucher-success="voucherSuccess"
            @close="closeCheckoutModal"
            @update:payment-method-model="paymentMethod = $event"
            @update:invoice-requested-model="invoiceRequested = $event"
            @update:voucher-code-model="voucherCode = $event"
            @update:note-model="note = $event"
            @validate-voucher="handleValidateVoucher"
            @confirm="handleConfirmCheckout"
            @email-receipt="openReceiptEmailModal"
        />

        <Teleport to="body">
            <div v-if="showReceiptEmailModal" class="fixed inset-0 z-[70] flex items-center justify-center p-4">
                <div class="absolute inset-0 bg-slate-950/80" @click="closeReceiptEmailModal" />

                <div class="relative z-10 w-full max-w-md rounded-3xl border border-slate-800 bg-slate-900 p-6 shadow-2xl">
                    <div class="flex items-start justify-between gap-4">
                        <div>
                            <h3 class="text-lg font-semibold text-white">Verzend bon via e-mail</h3>
                            <p class="mt-1 text-sm text-slate-400">Geef het e-mailadres in waarnaar de bon verstuurd moet worden.</p>
                        </div>

                        <button type="button" class="rounded-xl border border-slate-700 p-2 text-slate-300 transition hover:bg-slate-800 hover:text-white" @click="closeReceiptEmailModal">✕</button>
                    </div>

                    <input
                        v-model="receiptEmail"
                        type="email"
                        class="mt-5 w-full rounded-2xl border border-slate-700 bg-slate-950 px-4 py-3 text-sm text-white outline-none transition focus:border-sky-500"
                        placeholder="naam@voorbeeld.be"
                        @keydown.enter.prevent="confirmReceiptEmail"
                    >

                    <p v-if="receiptEmailError" class="mt-3 text-sm text-rose-300">{{ receiptEmailError }}</p>

                    <div class="mt-5 grid grid-cols-2 gap-3">
                        <button type="button" class="rounded-2xl border border-slate-700 bg-slate-800 px-4 py-3 text-sm font-semibold text-slate-200 transition hover:bg-slate-700" @click="closeReceiptEmailModal">
                            Annuleren
                        </button>
                        <button type="button" class="rounded-2xl bg-blue-600 px-4 py-3 text-sm font-semibold text-white transition hover:bg-blue-500 disabled:cursor-not-allowed disabled:opacity-50" :disabled="store.checkoutProcessing" @click="confirmReceiptEmail">
                            {{ store.checkoutProcessing ? 'Verwerken...' : 'Verzenden' }}
                        </button>
                    </div>
                </div>
            </div>
        </Teleport>
    </div>
</template>

<script setup>
import axios from '@/lib/http'
import { nextTick, ref, watch } from 'vue'
import { usePosStore } from '../../stores/usePosStore.js'
import OrderItemRow from './OrderItemRow.vue'
import CheckoutModal from './CheckoutModal.vue'

const store = usePosStore()
const listContainer = ref(null)
const showCheckoutModal = ref(false)
const showReceiptEmailModal = ref(false)
const paymentMethod = ref('cash')
const invoiceRequested = ref(false)
const voucherCode = ref('')
const note = ref('')
const receiptEmail = ref('')
const receiptEmailError = ref('')
const voucherValidationLoading = ref(false)
const voucherError = ref('')
const voucherSuccess = ref('')
const receiptPrintFrameId = 'receipt-print-frame'

function formatPrice(value) {
    return new Intl.NumberFormat('nl-BE', {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2,
    }).format(Number(value ?? 0))
}

function resetCheckoutState() {
    paymentMethod.value = 'cash'
    invoiceRequested.value = Boolean(store.selectedReservation?.invoice_requested ?? false)
    voucherCode.value = ''
    note.value = ''
    receiptEmail.value = store.selectedReservation?.email ?? ''
    receiptEmailError.value = ''
    voucherValidationLoading.value = false
    voucherError.value = ''
    voucherSuccess.value = ''
}

function openCheckoutModal() {
    resetCheckoutState()
    showCheckoutModal.value = true
}

function closeCheckoutModal() {
    showCheckoutModal.value = false
}

function openReceiptEmailModal() {
    receiptEmail.value = receiptEmail.value || store.selectedReservation?.email || ''
    receiptEmailError.value = ''
    showReceiptEmailModal.value = true
}

function closeReceiptEmailModal() {
    showReceiptEmailModal.value = false
    receiptEmailError.value = ''
}

async function handleValidateVoucher(code) {
    const normalizedCode = String(code ?? '').trim()
    voucherCode.value = normalizedCode
    voucherError.value = ''
    voucherSuccess.value = ''

    if (!normalizedCode) {
        return
    }

    voucherValidationLoading.value = true

    try {
        const voucher = await store.applyVoucher(normalizedCode)

        if (voucher) {
            voucherSuccess.value = `Cadeaubon ${voucher.code ?? normalizedCode} werd toegevoegd.`
            voucherCode.value = ''
            return
        }

        voucherError.value = store.checkoutError || 'Cadeaubon valideren mislukt.'
    } finally {
        voucherValidationLoading.value = false
    }
}

async function finalizeCheckoutResult(result) {
    store.lastCheckoutSummary = {
        mode: 'checkout',
        order: result,
        registration: store.selectedReservation,
    }

    closeReceiptEmailModal()
    closeCheckoutModal()
    resetCheckoutState()
    store.clearReservationSelection()
}

function printReceipt(orderId) {
    if (!orderId || typeof document === 'undefined') {
        return
    }

    let frame = document.getElementById(receiptPrintFrameId)

    if (!frame) {
        frame = document.createElement('iframe')
        frame.id = receiptPrintFrameId
        frame.setAttribute('aria-hidden', 'true')
        frame.style.position = 'fixed'
        frame.style.right = '0'
        frame.style.bottom = '0'
        frame.style.width = '0'
        frame.style.height = '0'
        frame.style.border = '0'
        frame.style.opacity = '0'
        frame.style.pointerEvents = 'none'
        document.body.appendChild(frame)
    }

    frame.src = `/api/frontdesk/orders/${orderId}/receipt?auto_print=1&ts=${Date.now()}`
}

async function executeCheckout(extraPayload = {}) {
    const result = await store.checkoutCurrentOrder({
        payment_method: extraPayload.payment_method ?? paymentMethod.value,
        invoice_requested: extraPayload.invoice_requested ?? invoiceRequested.value,
        notes: extraPayload.note ?? note.value,
        voucher_code: extraPayload.voucher_code ?? voucherCode.value,
    })

    if (extraPayload.print_receipt && result?.id) {
        printReceipt(result.id)
    }

    if (extraPayload.email_receipt && result?.id && extraPayload.receipt_email) {
        await axios.post(`/api/frontdesk/orders/${result.id}/send-receipt`, {
            email: extraPayload.receipt_email,
        })
    }

    await finalizeCheckoutResult(result)
}

async function handleConfirmCheckout(payload = {}) {
    try {
        await executeCheckout(payload)
    } catch (error) {
        console.error(error)
    }
}

async function confirmReceiptEmail() {
    const email = String(receiptEmail.value ?? '').trim()

    if (!email) {
        receiptEmailError.value = 'Geef een e-mailadres in.'
        return
    }

    receiptEmailError.value = ''

    try {
        await executeCheckout({
            payment_method: paymentMethod.value,
            invoice_requested: invoiceRequested.value,
            note: note.value,
            voucher_code: voucherCode.value,
            email_receipt: true,
            receipt_email: email,
        })
    } catch (error) {
        console.error(error)
        receiptEmailError.value = error?.response?.data?.message ?? store.checkoutError ?? 'Verzenden van de bon mislukte.'
    }
}

async function scrollToBottom() {
    await nextTick()

    const el = listContainer.value

    if (!el) {
        return
    }

    el.scrollTo({
        top: el.scrollHeight,
        behavior: 'smooth',
    })
}

watch(
    () => store.lastAddedLineId,
    async (value) => {
        if (!value) {
            return
        }

        await scrollToBottom()
    }
)
</script>
