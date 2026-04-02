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
        />
    </div>
</template>

<script setup>
import { nextTick, ref, watch } from 'vue'
import { usePosStore } from '../../stores/usePosStore.js'
import OrderItemRow from './OrderItemRow.vue'
import CheckoutModal from './CheckoutModal.vue'

const store = usePosStore()
const listContainer = ref(null)
const showCheckoutModal = ref(false)
const paymentMethod = ref('cash')
const invoiceRequested = ref(false)
const voucherCode = ref('')
const note = ref('')
const voucherValidationLoading = ref(false)
const voucherError = ref('')
const voucherSuccess = ref('')

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

async function handleConfirmCheckout(payload = {}) {
    try {
        const result = await store.checkoutCurrentOrder({
            payment_method: payload.payment_method ?? paymentMethod.value,
            invoice_requested: payload.invoice_requested ?? invoiceRequested.value,
            notes: payload.note ?? note.value,
            voucher_code: payload.voucher_code ?? voucherCode.value,
        })

        store.lastCheckoutSummary = {
            mode: 'checkout',
            order: result,
            registration: store.selectedReservation,
        }

        closeCheckoutModal()
        resetCheckoutState()
        store.clearReservationSelection()
    } catch (error) {
        console.error(error)
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
