<template>
    <Teleport to="body">
        <div
            v-if="open"
            class="fixed inset-0 z-[140] flex items-center justify-center bg-slate-950/70 p-4"
            @click.self="handleClose"
        >
            <div class="flex h-[860px] w-full max-w-7xl flex-col overflow-hidden rounded-[30px] border border-slate-800 bg-[#091633] shadow-2xl">
                <div class="flex items-start justify-between border-b border-slate-800 px-6 py-5">
                    <div>
                        <h2 class="text-2xl font-semibold text-white">Checkout</h2>
                        <p class="mt-1 text-sm text-slate-400">
                            {{ activeReservationLabel }}
                        </p>
                    </div>

                    <button
                        type="button"
                        class="rounded-2xl border border-slate-700 bg-slate-800/90 px-5 py-3 text-sm font-semibold text-white transition hover:bg-slate-700"
                        @click="handleClose"
                    >
                        Sluiten
                    </button>
                </div>

                <div class="grid min-h-0 flex-1 grid-cols-[0.82fr_1fr]">
                    <div class="flex min-h-0 flex-col border-r border-slate-800">
                        <div class="flex items-center justify-between px-6 py-5">
                            <h3 class="text-lg font-medium text-slate-300">Bestellijnen</h3>
                            <span class="text-sm text-slate-400">
                                {{ itemCount }} items
                            </span>
                        </div>

                        <div class="px-6 pb-3">
                            <div class="rounded-2xl border border-slate-800 bg-slate-950/40 px-4 py-3 text-xs text-slate-400">
                                Bron: {{ sourceLabel }} · gevonden lijnen: {{ sourceItems.length }}
                            </div>
                        </div>

                        <div class="min-h-0 flex-1 px-6 pb-4">
                            <div class="flex h-full min-h-0 flex-col">
                                <div class="min-h-0 flex-1 overflow-y-auto pr-2">
                                    <div
                                        v-if="normalizedItems.length"
                                        class="space-y-4"
                                    >
                                        <article
                                            v-for="item in normalizedItems"
                                            :key="item.key"
                                            class="rounded-[24px] border border-slate-700 bg-[#020b24] px-5 py-4 shadow-[inset_0_1px_0_rgba(255,255,255,0.03)]"
                                        >
                                            <div class="flex items-start justify-between gap-4">
                                                <div class="min-w-0">
                                                    <h4 class="truncate text-xl font-semibold leading-tight text-white">
                                                        {{ item.name }}
                                                    </h4>

                                                    <p class="mt-1 text-sm text-slate-400">
                                                        € {{ item.unitPriceFormatted }} / stuk
                                                    </p>
                                                </div>

                                                <div class="shrink-0 text-right">
                                                    <div class="text-sm text-slate-300">
                                                        x{{ item.quantity }}
                                                    </div>
                                                    <div class="mt-1 text-2xl font-bold text-white">
                                                        € {{ item.totalFormatted }}
                                                    </div>
                                                </div>
                                            </div>
                                        </article>
                                    </div>

                                    <div
                                        v-else
                                        class="flex h-full items-center justify-center rounded-[24px] border border-dashed border-slate-700 bg-[#020b24] px-6 py-10 text-center text-sm text-slate-400"
                                    >
                                        Geen bestellijnen gevonden.
                                    </div>
                                </div>

                                <div class="mt-4 rounded-[26px] border border-slate-800 bg-[#020b24] px-5 py-5">
                                    <div class="flex items-center justify-between gap-4">
                                        <span class="text-lg font-medium text-slate-300">Te betalen</span>
                                        <span class="text-4xl font-bold leading-none text-white">
                                            € {{ resolvedTotalFormatted }}
                                        </span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="flex min-h-0 flex-col px-6 py-5">
                        <div class="min-h-0 flex-1 overflow-y-auto pr-2">
                            <div class="space-y-5">
                                <section>
                                    <h3 class="mb-3 text-sm font-semibold uppercase tracking-wide text-slate-400">
                                        Betaalmethode
                                    </h3>

                                    <div class="grid grid-cols-2 gap-3">
                                        <button
                                            type="button"
                                            class="overflow-hidden rounded-[24px] border text-left transition"
                                            :class="paymentMethod === 'cash'
                                                ? 'border-sky-500 bg-slate-800 ring-2 ring-sky-500/30'
                                                : 'border-slate-700 bg-slate-800/80 hover:border-slate-500'"
                                            @click="paymentMethod = 'cash'"
                                        >
                                            <div class="flex h-[110px] items-center justify-center bg-white">
                                                <svg
                                                    class="h-16 w-16 text-slate-700"
                                                    viewBox="0 0 24 24"
                                                    fill="none"
                                                    stroke="currentColor"
                                                    stroke-width="1.6"
                                                    stroke-linecap="round"
                                                    stroke-linejoin="round"
                                                    aria-hidden="true"
                                                >
                                                    <path d="M3 7.5h12a2 2 0 0 1 2 2V13a2 2 0 0 1-2 2H8.5a2 2 0 0 1-2-2V9.5a2 2 0 0 1 2-2Z" />
                                                    <path d="M6.5 10.5h10l3 2.5-3 2.5h-10" />
                                                    <path d="M18 9l3.5 2.5L18 14" />
                                                    <path d="M2.5 17.5h7" />
                                                    <path d="M4.5 5.5h8" />
                                                </svg>
                                            </div>
                                            <div class="px-4 py-4">
                                                <div class="text-lg font-semibold text-white">Cash</div>
                                                <div class="mt-1 text-sm leading-snug text-slate-400">
                                                    Contante betaling aan de kassa
                                                </div>
                                            </div>
                                        </button>

                                        <button
                                            type="button"
                                            class="overflow-hidden rounded-[24px] border text-left transition"
                                            :class="paymentMethod === 'bancontact'
                                                ? 'border-sky-500 bg-slate-800 ring-2 ring-sky-500/30'
                                                : 'border-slate-700 bg-slate-800/80 hover:border-slate-500'"
                                            @click="paymentMethod = 'bancontact'"
                                        >
                                            <div class="flex h-[110px] items-center justify-center bg-white">
                                                <div class="rounded-xl border border-slate-300 bg-white px-4 py-3 shadow-sm">
                                                    <div class="text-center text-[18px] font-black leading-none text-sky-700">
                                                        Bancontact
                                                    </div>
                                                    <div class="mt-1 flex items-center justify-center gap-1">
                                                        <div class="h-2.5 w-10 -skew-x-12 bg-sky-600" />
                                                        <div class="h-2.5 w-10 -skew-x-12 bg-yellow-400" />
                                                    </div>
                                                    <div class="mt-1 text-center text-[12px] font-semibold text-sky-700">
                                                        Mister Cash
                                                    </div>
                                                </div>
                                            </div>
                                            <div class="px-4 py-4">
                                                <div class="text-lg font-semibold text-white">Bancontact</div>
                                                <div class="mt-1 text-sm leading-snug text-slate-400">
                                                    Elektronische betaling
                                                </div>
                                            </div>
                                        </button>
                                    </div>
                                </section>

                                <section class="rounded-[24px] border border-slate-800 bg-[#020b24] p-5">
                                    <label class="flex items-start justify-between gap-4">
                                        <div>
                                            <div class="text-lg font-semibold text-white">Factuur gewenst</div>
                                            <p class="mt-2 max-w-xl text-sm leading-relaxed text-slate-400">
                                                Duid aan of deze bestelling later gefactureerd moet worden.
                                            </p>
                                        </div>

                                        <input
                                            v-model="invoiceRequested"
                                            type="checkbox"
                                            class="mt-1 h-5 w-5 rounded border-slate-600 bg-slate-900 text-sky-500"
                                        />
                                    </label>
                                </section>

                                <section class="rounded-[24px] border border-slate-800 bg-[#020b24] p-5">
                                    <div class="text-lg font-semibold text-white">Cadeaubon scannen / ingeven</div>

                                    <div class="mt-4 flex flex-wrap items-stretch gap-3">
                                        <input
                                            v-model="voucherCode"
                                            type="text"
                                            class="min-w-[260px] flex-1 rounded-2xl border border-slate-700 bg-slate-800/90 px-4 py-3 text-sm text-white outline-none transition placeholder:text-slate-400 focus:border-sky-500"
                                            placeholder="Scan QR, RFID of geef de code in"
                                            @keydown.enter.prevent="handleValidateVoucher"
                                        />

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

                                <section>
                                    <h3 class="mb-3 text-sm font-semibold uppercase tracking-wide text-slate-400">
                                        Opmerking
                                    </h3>

                                    <textarea
                                        v-model="note"
                                        rows="5"
                                        class="w-full resize-none rounded-[24px] border border-slate-700 bg-slate-800/90 px-4 py-4 text-sm text-white outline-none transition placeholder:text-slate-400 focus:border-sky-500"
                                        placeholder="Optionele opmerking bij deze bestelling"
                                    />
                                </section>
                            </div>
                        </div>

                        <div class="mt-5 grid grid-cols-2 gap-4">
                            <button
                                type="button"
                                class="rounded-[22px] border border-slate-700 bg-slate-800/90 px-6 py-4 text-base font-semibold text-white transition hover:bg-slate-700"
                                @click="handleClose"
                            >
                                Annuleren
                            </button>

                            <button
                                type="button"
                                class="rounded-[22px] bg-blue-600 px-6 py-4 text-base font-semibold text-white transition hover:bg-blue-500 disabled:cursor-not-allowed disabled:opacity-50"
                                :disabled="confirmLoading || normalizedItems.length === 0"
                                @click="handleConfirm"
                            >
                                {{ confirmLoading ? 'Betaling verwerken...' : 'Betaling bevestigen' }}
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
import { CheckBadgeIcon } from '@heroicons/vue/24/outline'
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
        default: 'cash',
    },
    invoiceRequestedModel: {
        type: Boolean,
        default: false,
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
    'update:voucherCodeModel',
    'update:noteModel',
    'close',
    'confirm',
    'validate-voucher',
])

const paymentMethod = ref(props.paymentMethodModel)
const invoiceRequested = ref(props.invoiceRequestedModel)
const voucherCode = ref(props.voucherCodeModel)
const note = ref(props.noteModel)

watch(() => props.paymentMethodModel, (value) => {
    paymentMethod.value = value
})

watch(() => props.invoiceRequestedModel, (value) => {
    invoiceRequested.value = value
})

watch(() => props.voucherCodeModel, (value) => {
    voucherCode.value = value
})

watch(() => props.noteModel, (value) => {
    note.value = value
})

watch(paymentMethod, (value) => emit('update:paymentMethodModel', value))
watch(invoiceRequested, (value) => emit('update:invoiceRequestedModel', value))
watch(voucherCode, (value) => emit('update:voucherCodeModel', value))
watch(note, (value) => emit('update:noteModel', value))

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

const sourceLabel = computed(() => {
    if (Array.isArray(props.items) && props.items.length) return 'props.items'
    if (Array.isArray(props.lines) && props.lines.length) return 'props.lines'
    if (Array.isArray(props.order?.items) && props.order.items.length) return 'props.order.items'
    if (Array.isArray(props.order?.order_items) && props.order.order_items.length) return 'props.order.order_items'
    if (Array.isArray(props.order?.lines) && props.order.lines.length) return 'props.order.lines'
    return 'geen'
})

const normalizedItems = computed(() =>
    sourceItems.value.map((item, index) => {
        const quantity = Number(
            item.quantity
            ?? item.qty
            ?? item.amount
            ?? 1
        )

        const unitPrice = Number(
            item.unit_price_incl_vat
            ?? item.unit_price
            ?? item.unitPrice
            ?? item.price_incl_vat
            ?? item.price
            ?? 0
        )

        const lineTotal = Number(
            item.line_total_incl_vat
            ?? item.line_total
            ?? item.total_incl_vat
            ?? item.total
            ?? item.lineTotal
            ?? (unitPrice * quantity)
        )

        return {
            key: item.id ?? item.product_id ?? item.uuid ?? `${item.name ?? 'item'}-${index}`,
            name: item.name ?? item.product_name ?? item.title ?? 'Product',
            quantity,
            unitPriceFormatted: formatMoney(unitPrice),
            totalFormatted: formatMoney(lineTotal),
        }
    })
)

const itemCount = computed(() =>
    normalizedItems.value.reduce((sum, item) => sum + Number(item.quantity || 0), 0)
)

const resolvedTotalFormatted = computed(() => {
    if (props.totalFormatted) {
        return props.totalFormatted
    }

    const total = sourceItems.value.reduce((sum, item) => {
        const quantity = Number(item.quantity ?? item.qty ?? item.amount ?? 1)
        const lineTotal = Number(
            item.line_total_incl_vat
            ?? item.line_total
            ?? item.total_incl_vat
            ?? item.total
            ?? item.lineTotal
            ?? (
                Number(
                    item.unit_price_incl_vat
                    ?? item.unit_price
                    ?? item.unitPrice
                    ?? item.price_incl_vat
                    ?? item.price
                    ?? 0
                ) * quantity
            )
        )

        return sum + lineTotal
    }, 0)

    return formatMoney(total)
})

const activeReservationLabel = computed(() => {
    if (!props.registration) {
        return 'Losse verkoop'
    }

    return `Actieve reservatie: ${props.registration.name ?? props.registration.customer_name ?? `#${props.registration.id}`}`
})

function handleClose() {
    emit('update:open', false)
    emit('close')
}

function handleConfirm() {
    emit('confirm', {
        payment_method: paymentMethod.value,
        invoice_requested: invoiceRequested.value,
        voucher_code: voucherCode.value,
        note: note.value,
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

function formatMoney(value) {
    const number = Number(value ?? 0)

    return number.toLocaleString('nl-BE', {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2,
    })
}
</script>
