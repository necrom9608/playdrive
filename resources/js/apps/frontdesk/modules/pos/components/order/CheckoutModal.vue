<template>
    <Teleport to="body">
        <div
            v-if="open"
            class="fixed inset-0 z-[140] flex items-center justify-center bg-slate-950/70 p-4"
            @click.self="handleClose"
        >
            <div class="flex h-[800px] w-full max-w-[1200px] flex-col overflow-hidden rounded-[28px] border border-slate-800 bg-[#091633] shadow-2xl">
                <div class="flex items-start justify-between border-b border-slate-800 px-5 py-4">
                    <div>
                        <h2 class="text-xl font-semibold text-white">Checkout</h2>
                        <p class="mt-1 text-sm text-slate-400">
                            {{ activeReservationLabel }}
                        </p>
                    </div>

                    <button
                        type="button"
                        class="inline-flex h-11 w-11 items-center justify-center rounded-2xl border border-slate-700 bg-slate-800/90 text-white transition hover:bg-slate-700"
                        @click="handleClose"
                    >
                        <XMarkIcon class="h-5 w-5" />
                    </button>
                </div>

                <div class="grid min-h-0 flex-1 grid-cols-[0.8fr_1.2fr]">
                    <div class="flex min-h-0 flex-col border-r border-slate-800">
                        <div class="flex items-center justify-between px-5 py-4">
                            <h3 class="text-base font-medium text-slate-300">Bestellijnen</h3>
                            <span class="text-xs text-slate-400">
                                {{ itemCount }} items
                            </span>
                        </div>

                        <div class="min-h-0 flex-1 px-5 pb-4">
                            <div class="flex h-full min-h-0 flex-col">
                                <div class="min-h-0 flex-1 overflow-y-auto pr-2">
                                    <div
                                        v-if="normalizedItems.length"
                                        class="rounded-[20px] border border-slate-800 bg-[#020b24] px-4 py-3"
                                    >
                                        <article
                                            v-for="(item, index) in normalizedItems"
                                            :key="item.key"
                                            class="py-3"
                                            :class="index !== normalizedItems.length - 1 ? 'border-b border-dashed border-slate-700' : ''"
                                        >
                                            <div class="flex items-start justify-between gap-4">
                                                <div class="min-w-0">
                                                    <h4 class="truncate text-base font-semibold leading-tight text-white">
                                                        {{ item.name }}
                                                    </h4>

                                                    <p class="mt-1 text-xs text-slate-400">
                                                        € {{ item.unitPriceFormatted }} / stuk
                                                    </p>
                                                </div>

                                                <div class="shrink-0 text-right">
                                                    <div class="text-xs text-slate-300">
                                                        x{{ item.quantity }}
                                                    </div>
                                                    <div class="mt-1 text-lg font-bold text-white">
                                                        € {{ item.totalFormatted }}
                                                    </div>
                                                </div>
                                            </div>
                                        </article>
                                    </div>

                                    <div
                                        v-else
                                        class="flex h-full items-center justify-center rounded-[20px] border border-dashed border-slate-700 bg-[#020b24] px-6 py-10 text-center text-sm text-slate-400"
                                    >
                                        Geen bestellijnen gevonden.
                                    </div>
                                </div>

                                <div class="mt-4 rounded-[22px] border border-slate-800 bg-[#020b24] px-4 py-4">
                                    <div class="space-y-2">
                                        <div class="flex items-center justify-between gap-4 text-sm">
                                            <span class="font-medium text-slate-300">Producten</span>
                                            <span class="font-semibold text-white">€ {{ itemsTotalFormatted }}</span>
                                        </div>

                                        <div v-if="manualDiscountAmountValue > 0" class="flex items-center justify-between gap-4 text-sm">
                                            <span class="font-medium text-emerald-200">{{ manualDiscountLabel || 'Manuele korting' }}</span>
                                            <span class="font-semibold text-emerald-200">- € {{ manualDiscountAmountFormatted }}</span>
                                        </div>

                                        <div class="flex items-center justify-between gap-4 border-t border-slate-800 pt-2">
                                            <span class="text-base font-medium text-slate-300">Totaal</span>
                                            <span class="text-3xl font-bold leading-none text-white">
                                                € {{ resolvedTotalFormatted }}
                                            </span>
                                        </div>
                                    </div>

                                    <div class="mt-3 grid grid-cols-2 gap-3 text-sm">
                                        <div class="rounded-xl border border-slate-800 bg-slate-900/40 px-3 py-2">
                                            <div class="text-xs uppercase tracking-wide text-slate-400">Excl. btw</div>
                                            <div class="mt-1 font-semibold text-white">€ {{ subtotalFormatted }}</div>
                                        </div>

                                        <div class="rounded-xl border border-slate-800 bg-slate-900/40 px-3 py-2">
                                            <div class="text-xs uppercase tracking-wide text-slate-400">Btw</div>
                                            <div class="mt-1 font-semibold text-white">€ {{ vatFormatted }}</div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="flex min-h-0 flex-col px-5 py-4">
                        <div class="min-h-0 flex-1 overflow-y-auto pr-2">
                            <div class="space-y-4">
                                <section>
                                    <div class="grid grid-cols-3 gap-3">
                                        <button
                                            type="button"
                                            class="overflow-hidden rounded-[20px] border transition"
                                            :class="paymentMethodCardClass('cash')"
                                            @click="paymentMethod = 'cash'"
                                        >
                                            <div class="flex h-[140px] items-center justify-center bg-white px-3">
                                                <img
                                                    :src="'/images/payments/cash.png'"
                                                    alt="Cash"
                                                    class="max-h-[110px] max-w-full object-contain"
                                                >
                                            </div>
                                        </button>

                                        <button
                                            type="button"
                                            class="overflow-hidden rounded-[20px] border transition"
                                            :class="paymentMethodCardClass('bancontact')"
                                            @click="paymentMethod = 'bancontact'"
                                        >
                                            <div class="flex h-[140px] items-center justify-center bg-white px-3">
                                                <img
                                                    :src="'/images/payments/bancontact.png'"
                                                    alt="Bancontact"
                                                    class="max-h-[110px] max-w-full object-contain"
                                                >
                                            </div>
                                        </button>

                                        <button
                                            type="button"
                                            class="rounded-[20px] border p-4 text-left transition"
                                            :class="invoiceRequested
                                                ? 'border-sky-500 bg-slate-800 ring-2 ring-sky-500/30'
                                                : 'border-slate-700 bg-slate-800/80 hover:border-slate-500'"
                                            @click="invoiceRequested = !invoiceRequested"
                                        >
                                            <div class="flex h-full flex-col justify-between gap-4">
                                                <div class="flex items-start justify-between gap-3">
                                                    <DocumentTextIcon class="h-7 w-7 text-white" />
                                                    <div
                                                        class="mt-0.5 h-6 w-11 rounded-full border transition"
                                                        :class="invoiceRequested
                                                            ? 'border-sky-400 bg-sky-500/20'
                                                            : 'border-slate-600 bg-slate-900/70'"
                                                    >
                                                        <div
                                                            class="h-5 w-5 rounded-full bg-white shadow-sm transition"
                                                            :class="invoiceRequested ? 'translate-x-5 mt-[1px] ml-[1px]' : 'translate-x-0 mt-[1px] ml-[1px]'"
                                                        />
                                                    </div>
                                                </div>

                                                <div>
                                                    <div class="text-base font-semibold text-white">
                                                        Facturatie
                                                    </div>
                                                    <p class="mt-1 text-sm leading-relaxed text-slate-400">
                                                        Later mee op factuur zetten.
                                                    </p>
                                                </div>
                                            </div>
                                        </button>
                                    </div>
                                </section>

                                <section
                                    v-if="paymentMethod === 'cash'"
                                    class="rounded-[20px] border border-slate-800 bg-[#020b24] p-4"
                                >
                                    <div class="grid grid-cols-3 gap-3">
                                        <div class="rounded-2xl border border-slate-800 bg-slate-900/60 px-4 py-4 text-center">
                                            <div class="text-xs uppercase tracking-wide text-slate-400">
                                                Te betalen
                                            </div>
                                            <div class="mt-2 text-3xl font-bold text-white">
                                                € {{ resolvedTotalFormatted }}
                                            </div>
                                        </div>

                                        <div class="rounded-2xl border border-slate-800 bg-slate-900/60 px-4 py-4">
                                            <div class="text-center">
                                                <div class="text-xs uppercase tracking-wide text-slate-400">
                                                    Ontvangen
                                                </div>
                                            </div>

                                            <input
                                                v-model="cashReceivedInput"
                                                type="text"
                                                inputmode="decimal"
                                                autocomplete="off"
                                                class="mt-2 w-full rounded-2xl border border-slate-700 bg-slate-800/90 px-4 py-3 text-center text-2xl font-bold text-white outline-none transition placeholder:text-slate-400 focus:border-sky-500"
                                                placeholder="0,00"
                                                @input="handleCashInput"
                                            >
                                        </div>

                                        <div class="rounded-2xl border border-slate-800 bg-slate-900/60 px-4 py-4 text-center">
                                            <div class="text-xs uppercase tracking-wide text-slate-400">
                                                {{ cashReceived >= resolvedTotal ? 'Terug te geven' : 'Nog te ontvangen' }}
                                            </div>
                                            <div
                                                class="mt-2 text-3xl font-bold"
                                                :class="cashReceived >= resolvedTotal ? 'text-emerald-300' : 'text-white'"
                                            >
                                                € {{ cashReceived >= resolvedTotal ? cashChangeFormatted : cashRemainingFormatted }}
                                            </div>
                                        </div>
                                    </div>

                                    <div
                                        v-if="cashSuggestions.length"
                                        class="mt-4"
                                    >
                                        <div class="grid grid-cols-5 gap-2">
                                            <button
                                                v-for="amount in cashSuggestions"
                                                :key="amount"
                                                type="button"
                                                class="inline-flex items-center justify-center rounded-2xl border border-slate-700 bg-slate-800/90 px-3 py-3 text-base font-semibold text-white transition hover:border-sky-500 hover:bg-slate-700"
                                                @click="setCashReceived(amount)"
                                            >
                                                € {{ formatMoney(amount) }}
                                            </button>
                                        </div>
                                    </div>
                                </section>

                                <section class="rounded-[20px] border border-slate-800 bg-[#020b24] p-4">
                                    <div class="flex items-stretch gap-3">
                                        <input
                                            v-model="manualDiscountLabel"
                                            type="text"
                                            class="h-[50px] min-w-0 flex-1 rounded-2xl border border-slate-700 bg-slate-800/90 px-4 text-sm text-white outline-none transition placeholder:text-slate-400 focus:border-sky-500"
                                            placeholder="Bijv. Legacy kortingsbon"
                                        >

                                        <div class="relative w-[150px] shrink-0">
                                            <span class="pointer-events-none absolute left-4 top-1/2 -translate-y-1/2 text-sm text-slate-400">€</span>
                                            <input
                                                :value="manualDiscountAmount"
                                                type="text"
                                                inputmode="decimal"
                                                class="h-[50px] w-full rounded-2xl border border-slate-700 bg-slate-800/90 pl-8 pr-4 text-sm text-white outline-none transition placeholder:text-slate-400 focus:border-sky-500"
                                                placeholder="0,00"
                                                @input="handleManualDiscountInput"
                                            >
                                        </div>

                                        <button
                                            type="button"
                                            class="h-[50px] shrink-0 whitespace-nowrap rounded-2xl border border-slate-700 bg-slate-800/90 px-4 text-sm font-medium text-slate-200 transition hover:bg-slate-700"
                                            @click="setManualDiscountAmount(itemsTotal)"
                                        >
                                            Volledige bestelling
                                        </button>

                                        <button
                                            type="button"
                                            class="inline-flex h-[50px] w-[50px] items-center justify-center rounded-2xl border border-slate-700 bg-slate-800/90 text-slate-200 transition hover:bg-slate-700"
                                            @click="clearManualDiscount"
                                        >
                                            <TrashIcon class="h-5 w-5" />
                                        </button>
                                    </div>
                                </section>

                                <section class="rounded-[20px] border border-slate-800 bg-[#020b24] p-4">
                                    <div class="text-base font-semibold text-white">Cadeaubon</div>

                                    <div class="mt-4 flex flex-wrap items-stretch gap-3">
                                        <input
                                            v-model="voucherCode"
                                            type="text"
                                            class="min-w-[220px] flex-1 rounded-2xl border border-slate-700 bg-slate-800/90 px-4 py-3 text-sm text-white outline-none transition placeholder:text-slate-400 focus:border-sky-500"
                                            placeholder="Scan QR, RFID of geef de code in"
                                            @keydown.enter.prevent="handleValidateVoucher"
                                        >

                                        <ScanQrButton
                                            :show-value="false"
                                            label="Scan QR"
                                            @confirmed="handleQrScanned"
                                        />

                                        <ScanRfidButton
                                            :show-value="false"
                                            label="Scan RFID"
                                            @confirmed="handleRfidScanned"
                                        />

                                        <button
                                            type="button"
                                            class="inline-flex items-center justify-center gap-2 rounded-2xl border border-slate-700 bg-slate-800/90 px-4 py-3 text-sm font-semibold text-white transition hover:border-sky-500 hover:bg-slate-700/90 disabled:cursor-not-allowed disabled:opacity-50"
                                            :disabled="voucherValidationLoading || !voucherCode.trim()"
                                            @click="handleValidateVoucher"
                                        >
                                            <CheckBadgeIcon class="h-5 w-5" />
                                            <span>{{ voucherValidationLoading ? 'Valideren...' : 'Valideren' }}</span>
                                        </button>
                                    </div>

                                    <p
                                        v-if="voucherError"
                                        class="mt-4 rounded-2xl border border-rose-800/60 bg-rose-950/40 px-4 py-3 text-sm text-rose-300"
                                    >
                                        {{ voucherError }}
                                    </p>

                                    <p
                                        v-if="voucherSuccess"
                                        class="mt-4 rounded-2xl border border-emerald-800/60 bg-emerald-950/40 px-4 py-3 text-sm text-emerald-300"
                                    >
                                        {{ voucherSuccess }}
                                    </p>
                                </section>
                            </div>
                        </div>

                        <div class="mt-4 grid grid-cols-1 gap-4 md:grid-cols-3">

                            <!-- Print -->
                            <button
                                type="button"
                                class="inline-flex items-center justify-center gap-2 rounded-[20px] bg-blue-600 px-5 py-4 text-sm font-semibold text-white transition hover:bg-blue-500 disabled:cursor-not-allowed disabled:opacity-50"
                                :disabled="confirmLoading || normalizedItems.length === 0 || cashPaymentInvalid"
                                @click="handleConfirm(true)"
                            >
                                <PrinterIcon class="h-5 w-5" />
                                <span>{{ confirmLoading ? 'Betaling verwerken...' : 'Druk bon' }}</span>
                            </button>

                            <!-- Email -->
                            <button
                                type="button"
                                class="inline-flex items-center justify-center gap-2 rounded-[20px] bg-blue-600 px-5 py-4 text-sm font-semibold text-white transition hover:bg-blue-500 disabled:cursor-not-allowed disabled:opacity-50"
                                :disabled="confirmLoading || normalizedItems.length === 0 || cashPaymentInvalid"
                                @click="emit('email-receipt')"
                            >
                                <DocumentTextIcon class="h-5 w-5" />
                                <span>{{ confirmLoading ? 'Betaling verwerken...' : 'Verzend bon via e-mail' }}</span>
                            </button>

                            <!-- Confirm -->
                            <button
                                type="button"
                                class="inline-flex items-center justify-center gap-2 rounded-[20px] bg-gradient-to-r from-emerald-500 to-green-600 px-5 py-4 text-sm font-bold text-white shadow-lg shadow-emerald-900/40 transition hover:scale-[1.02] hover:from-emerald-400 hover:to-green-500 disabled:cursor-not-allowed disabled:opacity-50"
                                :disabled="confirmLoading || normalizedItems.length === 0 || cashPaymentInvalid"
                                @click="handleConfirm(false)"
                            >
                                <CheckIcon class="h-5 w-5" />
                                <span>{{ confirmLoading ? 'Betaling verwerken...' : 'Betaling bevestigen' }}</span>
                            </button>

                        </div>
                    </div>
                </div>
            </div>
        </div>
    </Teleport>
</template>

<script setup>
import { computed, ref, watch } from 'vue'
import {
    CheckBadgeIcon,
    CheckIcon,
    DocumentTextIcon,
    PrinterIcon,
    TrashIcon,
    XMarkIcon,
} from '@heroicons/vue/24/outline'
import ScanQrButton from '../../../../../../shared/components/scanners/ScanQrButton.vue'
import ScanRfidButton from '../../../../../../shared/components/scanners/ScanRfidButton.vue'

const props = defineProps({
    open: {
        type: Boolean,
        default: false,
    },
    items: {
        type: Array,
        default: () => [],
    },
    lines: {
        type: Array,
        default: () => [],
    },
    order: {
        type: Object,
        default: null,
    },
    totalFormatted: {
        type: String,
        default: '',
    },
    registration: {
        type: Object,
        default: null,
    },
    paymentMethodModel: {
        type: String,
        default: 'bancontact',
    },
    invoiceRequestedModel: {
        type: Boolean,
        default: false,
    },
    manualDiscountAmountModel: {
        type: [String, Number],
        default: '',
    },
    manualDiscountLabelModel: {
        type: String,
        default: 'Manuele korting',
    },
    voucherCodeModel: {
        type: String,
        default: '',
    },
    noteModel: {
        type: String,
        default: '',
    },
    confirmLoading: {
        type: Boolean,
        default: false,
    },
    voucherValidationLoading: {
        type: Boolean,
        default: false,
    },
    voucherError: {
        type: String,
        default: '',
    },
    voucherSuccess: {
        type: String,
        default: '',
    },
})

const emit = defineEmits([
    'update:open',
    'update:paymentMethodModel',
    'update:invoiceRequestedModel',
    'update:manualDiscountAmountModel',
    'update:manualDiscountLabelModel',
    'update:voucherCodeModel',
    'update:noteModel',
    'close',
    'confirm',
    'validate-voucher',
    'email-receipt',
])

const paymentMethod = ref(props.paymentMethodModel)
const invoiceRequested = ref(props.invoiceRequestedModel)
const manualDiscountAmount = ref(props.manualDiscountAmountModel)
const manualDiscountLabel = ref(props.manualDiscountLabelModel || 'Manuele korting')
const voucherCode = ref(props.voucherCodeModel)
const note = ref(props.noteModel)
const cashReceivedInput = ref('')

watch(() => props.paymentMethodModel, (value) => {
    paymentMethod.value = value
})

watch(() => props.invoiceRequestedModel, (value) => {
    invoiceRequested.value = value
})

watch(() => props.manualDiscountAmountModel, (value) => {
    manualDiscountAmount.value = value
})

watch(() => props.manualDiscountLabelModel, (value) => {
    manualDiscountLabel.value = value || 'Manuele korting'
})

watch(() => props.voucherCodeModel, (value) => {
    voucherCode.value = value
})

watch(() => props.noteModel, (value) => {
    note.value = value
})

watch(paymentMethod, (value) => {
    emit('update:paymentMethodModel', value)

    if (value !== 'cash') {
        cashReceivedInput.value = ''
    }
})

watch(invoiceRequested, (value) => emit('update:invoiceRequestedModel', value))
watch(manualDiscountAmount, (value) => emit('update:manualDiscountAmountModel', value))
watch(manualDiscountLabel, (value) => emit('update:manualDiscountLabelModel', value || 'Manuele korting'))
watch(voucherCode, (value) => emit('update:voucherCodeModel', value))
watch(note, (value) => emit('update:noteModel', value))

watch(
    () => props.open,
    (value) => {
        if (!value) {
            cashReceivedInput.value = ''
        }
    }
)

const sourceItems = computed(() => {
    if (Array.isArray(props.items) && props.items.length) {
        return props.items
    }

    if (Array.isArray(props.lines) && props.lines.length) {
        return props.lines
    }

    if (Array.isArray(props.order?.items) && props.order.items.length) {
        return props.order.items
    }

    if (Array.isArray(props.order?.order_items) && props.order.order_items.length) {
        return props.order.order_items
    }

    if (Array.isArray(props.order?.lines) && props.order.lines.length) {
        return props.order.lines
    }

    return []
})

const normalizedItems = computed(() =>
    sourceItems.value.map((item, index) => {
        const quantity = Number(item.quantity ?? item.qty ?? item.amount ?? 1)

        const unitPriceIncl = Number(
            item.unit_price_incl_vat
            ?? item.unit_price
            ?? item.unitPrice
            ?? item.price_incl_vat
            ?? item.price
            ?? 0
        )

        const lineTotalIncl = Number(
            item.line_total_incl_vat
            ?? item.line_total
            ?? item.total_incl_vat
            ?? item.total
            ?? item.lineTotal
            ?? (unitPriceIncl * quantity)
        )

        const vatRate = Number(item.vat_rate ?? item.vatRate ?? 0)
        const lineTotalExcl = vatRate > 0
            ? roundToTwo(lineTotalIncl / (1 + (vatRate / 100)))
            : lineTotalIncl
        const lineVat = roundToTwo(lineTotalIncl - lineTotalExcl)

        return {
            key: item.id ?? item.product_id ?? item.uuid ?? `${item.name ?? 'item'}-${index}`,
            name: item.name ?? item.product_name ?? item.title ?? 'Product',
            quantity,
            unitPriceFormatted: formatMoney(unitPriceIncl),
            totalFormatted: formatMoney(lineTotalIncl),
            lineTotalIncl,
            lineTotalExcl,
            lineVat,
        }
    })
)

const itemCount = computed(() =>
    normalizedItems.value.reduce((sum, item) => sum + Number(item.quantity || 0), 0)
)

const itemsTotal = computed(() =>
    roundToTwo(normalizedItems.value.reduce((sum, item) => sum + item.lineTotalIncl, 0))
)

const manualDiscountAmountValue = computed(() => {
    const parsed = normalizeMoneyInput(manualDiscountAmount.value)

    if (itemsTotal.value <= 0) {
        return 0
    }

    return Math.min(parsed, itemsTotal.value)
})

const resolvedTotal = computed(() =>
    roundToTwo(Math.max(0, itemsTotal.value - manualDiscountAmountValue.value))
)

const subtotal = computed(() =>
    roundToTwo(normalizedItems.value.reduce((sum, item) => sum + item.lineTotalExcl, 0))
)

const vatTotal = computed(() =>
    roundToTwo(normalizedItems.value.reduce((sum, item) => sum + item.lineVat, 0))
)

const resolvedTotalFormatted = computed(() => formatMoney(resolvedTotal.value))
const itemsTotalFormatted = computed(() => formatMoney(itemsTotal.value))
const manualDiscountAmountFormatted = computed(() => formatMoney(manualDiscountAmountValue.value))
const subtotalFormatted = computed(() => formatMoney(subtotal.value))
const vatFormatted = computed(() => formatMoney(vatTotal.value))

const activeReservationLabel = computed(() => {
    if (!props.registration) {
        return 'Losse verkoop'
    }

    return `Actieve reservatie: ${props.registration.name ?? props.registration.customer_name ?? `#${props.registration.id}`}`
})

const cashReceived = computed(() => normalizeMoneyInput(cashReceivedInput.value))
const cashChange = computed(() => Math.max(0, roundToTwo(cashReceived.value - resolvedTotal.value)))
const cashRemaining = computed(() => Math.max(0, roundToTwo(resolvedTotal.value - cashReceived.value)))

const cashChangeFormatted = computed(() => formatMoney(cashChange.value))
const cashRemainingFormatted = computed(() => formatMoney(cashRemaining.value))

const cashPaymentInvalid = computed(() =>
    paymentMethod.value === 'cash'
    && normalizedItems.value.length > 0
    && cashReceived.value > 0
    && cashReceived.value < resolvedTotal.value
)

const cashSuggestions = computed(() => {
    const total = roundToTwo(resolvedTotal.value)

    if (total <= 0) {
        return []
    }

    const suggestions = new Set()

    if (Number.isInteger(total)) {
        suggestions.add(total)
    }

    const ceilEuro = Math.ceil(total)
    const nextFive = Math.ceil(total / 5) * 5
    const nextTen = Math.ceil(total / 10) * 10
    const nextTwenty = Math.ceil(total / 20) * 20
    const nextFifty = Math.ceil(total / 50) * 50
    const nextHundred = Math.ceil(total / 100) * 100

    if (total < 10) {
        suggestions.add(ceilEuro)
        suggestions.add(10)
        suggestions.add(20)
        suggestions.add(50)
    } else if (total < 20) {
        suggestions.add(ceilEuro)
        suggestions.add(nextFive)
        suggestions.add(nextTen)
        suggestions.add(20)
        suggestions.add(50)
    } else if (total < 50) {
        suggestions.add(nextFive)
        suggestions.add(nextTen)
        suggestions.add(nextTwenty)
        suggestions.add(50)
        suggestions.add(100)
    } else {
        suggestions.add(nextFive)
        suggestions.add(nextTen)
        suggestions.add(nextTwenty)
        suggestions.add(nextFifty)
        suggestions.add(nextHundred)
    }

    return Array.from(suggestions)
        .map((value) => roundToTwo(value))
        .filter((value) => value >= total)
        .sort((a, b) => a - b)
        .slice(0, 5)
})

function handleClose() {
    emit('update:open', false)
    emit('close')
}

function handleConfirm(printReceipt = false) {
    emit('confirm', {
        payment_method: paymentMethod.value,
        invoice_requested: invoiceRequested.value,
        voucher_code: voucherCode.value,
        manual_discount_amount: roundToTwo(manualDiscountAmountValue.value),
        manual_discount_label: (manualDiscountLabel.value || 'Manuele korting').trim() || 'Manuele korting',
        note: note.value,
        print_receipt: printReceipt,
        cash_received: paymentMethod.value === 'cash' ? roundToTwo(cashReceived.value) : null,
        cash_change: paymentMethod.value === 'cash' ? roundToTwo(cashChange.value) : null,
    })
}

function handleValidateVoucher() {
    emit('validate-voucher', voucherCode.value)
}

function handleQrScanned(value) {
    voucherCode.value = value
}

function handleRfidScanned(value) {
    voucherCode.value = value
}

function setCashReceived(amount) {
    cashReceivedInput.value = formatPlainMoney(amount)
}

function setManualDiscountAmount(amount) {
    manualDiscountAmount.value = formatPlainMoney(amount)
}

function clearManualDiscount() {
    manualDiscountAmount.value = ''
}

function handleManualDiscountInput(event) {
    const cleaned = String(event.target.value ?? '')
        .replace(',', '.')
        .replace(/[^0-9.]/g, '')
        .replace(/(\..*)\./g, '$1')

    manualDiscountAmount.value = cleaned
}

function paymentMethodCardClass(method) {
    return paymentMethod.value === method
        ? 'border-sky-500 bg-slate-800 ring-2 ring-sky-500/30 shadow-[0_0_0_1px_rgba(14,165,233,0.25)]'
        : 'border-slate-700 bg-slate-800/80 hover:border-slate-500'
}

function normalizeMoneyInput(value) {
    if (value === null || value === undefined) {
        return 0
    }

    const normalized = String(value)
        .replace(/\s/g, '')
        .replace(',', '.')
        .replace(/[^0-9.]/g, '')

    const parsed = Number.parseFloat(normalized)

    if (!Number.isFinite(parsed)) {
        return 0
    }

    return roundToTwo(parsed)
}

function roundToTwo(value) {
    return Math.round((Number(value) + Number.EPSILON) * 100) / 100
}

function formatPlainMoney(value) {
    return roundToTwo(value).toFixed(2)
}

function formatMoney(value) {
    const number = Number(value ?? 0)

    return number.toLocaleString('nl-BE', {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2,
    })
}

function handleCashInput(event) {
    const cleaned = String(event.target.value ?? '')
        .replace(',', '.')
        .replace(/[^0-9.]/g, '')
        .replace(/(\..*)\./g, '$1')

    cashReceivedInput.value = cleaned
}
</script>
