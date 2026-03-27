<template>
    <div
        v-if="open"
        class="fixed inset-0 z-50 flex items-center justify-center bg-slate-950/80 p-4"
        @click.self="$emit('close')"
    >
        <div class="flex max-h-[90vh] w-full max-w-3xl flex-col overflow-hidden rounded-3xl border border-slate-800 bg-slate-900 shadow-2xl">
            <div class="flex items-start justify-between gap-4 border-b border-slate-800 px-6 py-5">
                <div>
                    <h3 class="text-xl font-semibold text-white">Checkout</h3>
                    <p class="mt-1 text-sm text-slate-400">
                        {{ store.currentOrderLabel }}
                    </p>
                </div>

                <button
                    type="button"
                    class="rounded-xl border border-slate-700 bg-slate-800 px-3 py-2 text-sm font-semibold text-slate-200 transition hover:bg-slate-700"
                    @click="$emit('close')"
                >
                    Sluiten
                </button>
            </div>

            <div class="grid min-h-0 flex-1 gap-0 md:grid-cols-[1.15fr_0.85fr]">
                <div class="min-h-0 border-b border-slate-800 p-6 md:border-b-0 md:border-r">
                    <div class="mb-4 flex items-center justify-between text-sm text-slate-400">
                        <span>Bestellijnen</span>
                        <span>{{ store.orderCount }} items</span>
                    </div>

                    <div class="max-h-[45vh] space-y-3 overflow-auto pr-1">
                        <div
                            v-for="item in store.currentOrderItems"
                            :key="item.line_id"
                            class="rounded-2xl border border-slate-800 bg-slate-950 p-4"
                        >
                            <div class="flex items-start justify-between gap-3">
                                <div>
                                    <div class="text-sm font-semibold text-white">
                                        {{ item.name }}
                                    </div>
                                    <div class="mt-1 text-xs text-slate-500">
                                        € {{ formatPrice(item.price_incl_vat) }} / stuk
                                    </div>
                                </div>

                                <div class="text-right">
                                    <div class="text-xs text-slate-400">
                                        x{{ item.quantity }}
                                    </div>
                                    <div class="mt-1 text-sm font-bold text-white">
                                        € {{ formatPrice(item.price_incl_vat * item.quantity) }}
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <form class="flex min-h-0 flex-col p-6" @submit.prevent="submitCheckout">
                    <div class="space-y-5">
                        <div>
                            <label class="mb-2 block text-xs font-medium uppercase tracking-wide text-slate-400">
                                Betaalmethode
                            </label>

                            <div class="grid grid-cols-2 gap-3">
                                <button
                                    v-for="method in paymentMethods"
                                    :key="method.value"
                                    type="button"
                                    class="rounded-2xl border px-4 py-4 text-left transition"
                                    :class="form.payment_method === method.value
                                        ? 'border-blue-500 bg-blue-500/10 text-white'
                                        : 'border-slate-700 bg-slate-800 text-slate-200 hover:bg-slate-700'"
                                    @click="form.payment_method = method.value"
                                >
                                    <div class="text-sm font-semibold">{{ method.label }}</div>
                                    <div class="mt-1 text-xs text-slate-400">{{ method.description }}</div>
                                </button>
                            </div>
                        </div>

                        <div class="rounded-2xl border border-slate-800 bg-slate-950 px-4 py-3">
                            <label class="flex items-center justify-between gap-4">
                                <div>
                                    <div class="text-sm font-semibold text-white">Factuur gewenst</div>
                                    <div class="mt-1 text-xs text-slate-400">
                                        Duid aan of deze bestelling later gefactureerd moet worden.
                                    </div>
                                </div>

                                <input
                                    v-model="form.invoice_requested"
                                    type="checkbox"
                                    class="h-5 w-5 rounded border-slate-600 bg-slate-800 text-blue-600 focus:ring-blue-500"
                                >
                            </label>
                        </div>

                        <div>
                            <label for="checkout-notes" class="mb-2 block text-xs font-medium uppercase tracking-wide text-slate-400">
                                Opmerking
                            </label>

                            <textarea
                                id="checkout-notes"
                                v-model="form.notes"
                                rows="4"
                                class="w-full rounded-2xl border border-slate-700 bg-slate-800 px-4 py-3 text-sm text-slate-100 outline-none transition focus:border-blue-500 focus:ring-2 focus:ring-blue-900"
                                placeholder="Optionele opmerking bij deze bestelling"
                            />
                        </div>

                        <div v-if="store.checkoutError" class="rounded-2xl border border-rose-500/40 bg-rose-500/10 px-4 py-3 text-sm text-rose-200">
                            {{ store.checkoutError }}
                        </div>

                        <div v-if="store.lastCheckoutSummary" class="rounded-2xl border border-emerald-500/40 bg-emerald-500/10 px-4 py-3 text-sm text-emerald-200">
                            Checkout geslaagd. Order #{{ store.lastCheckoutSummary.id }} werd opgeslagen.
                        </div>
                    </div>

                    <div class="mt-6 rounded-2xl border border-slate-800 bg-slate-950 p-4">
                        <div class="flex items-center justify-between text-sm text-slate-400">
                            <span>Aantal</span>
                            <span class="font-semibold text-white">{{ store.orderCount }}</span>
                        </div>

                        <div class="mt-3 flex items-center justify-between text-sm text-slate-400">
                            <span>Betaalmethode</span>
                            <span class="font-semibold capitalize text-white">{{ paymentMethodLabel }}</span>
                        </div>

                        <div class="mt-3 flex items-center justify-between text-sm text-slate-400">
                            <span>Factuur</span>
                            <span class="font-semibold text-white">
                                {{ form.invoice_requested ? 'Ja' : 'Nee' }}
                            </span>
                        </div>

                        <div class="mt-4 flex items-center justify-between border-t border-slate-800 pt-4">
                            <span class="text-base font-medium text-slate-300">Te betalen</span>
                            <span class="text-3xl font-bold text-white">€ {{ formatPrice(store.orderSubtotal) }}</span>
                        </div>
                    </div>

                    <div class="mt-6 grid grid-cols-2 gap-3">
                        <button
                            type="button"
                            class="rounded-2xl border border-slate-700 bg-slate-800 px-4 py-3 text-sm font-semibold text-slate-200 transition hover:bg-slate-700"
                            :disabled="store.checkoutProcessing"
                            @click="$emit('close')"
                        >
                            Annuleren
                        </button>

                        <button
                            type="submit"
                            class="rounded-2xl bg-blue-600 px-4 py-3 text-sm font-semibold text-white transition hover:bg-blue-500 disabled:cursor-not-allowed disabled:opacity-50"
                            :disabled="store.checkoutProcessing || !store.currentOrderItems.length"
                        >
                            {{ store.checkoutProcessing ? 'Bezig met afrekenen...' : 'Betaling bevestigen' }}
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
</template>

<script setup>
import { computed, reactive, watch } from 'vue'
import { usePosStore } from '../../stores/usePosStore.js'

const props = defineProps({
    open: {
        type: Boolean,
        default: false,
    },
})

const emit = defineEmits(['close'])
const store = usePosStore()

const paymentMethods = [
    { value: 'cash', label: 'Cash', description: 'Contante betaling aan de kassa' },
    { value: 'bancontact', label: 'Bancontact', description: 'Elektronische betaling' },
]

const form = reactive({
    payment_method: 'cash',
    notes: '',
    invoice_requested: false,
})

const paymentMethodLabel = computed(() => {
    return paymentMethods.find(method => method.value === form.payment_method)?.label ?? form.payment_method
})

watch(
    () => props.open,
    (isOpen) => {
        if (isOpen) {
            store.checkoutError = null
            store.lastCheckoutSummary = null
            form.payment_method = 'cash'
            form.notes = ''
            form.invoice_requested = Boolean(store.selectedReservation?.invoice_requested ?? false)
            return
        }

        form.payment_method = 'cash'
        form.notes = ''
        form.invoice_requested = false
    }
)

function formatPrice(value) {
    return new Intl.NumberFormat('nl-BE', {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2,
    }).format(Number(value ?? 0))
}

async function submitCheckout() {
    const result = await store.checkoutCurrentOrder({
        payment_method: form.payment_method,
        notes: form.notes,
        invoice_requested: form.invoice_requested,
    })

    if (result) {
        emit('close')
    }
}
</script>
