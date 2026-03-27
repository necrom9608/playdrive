<template>
    <div
        v-if="open"
        class="fixed inset-0 z-50 flex items-center justify-center bg-slate-950/80 p-4"
        @click.self="$emit('close')"
    >
        <div class="w-full max-w-lg rounded-3xl border border-slate-800 bg-slate-900 p-6 shadow-2xl">
            <h3 class="text-xl font-semibold text-white">Terugbetaling registreren</h3>
            <p class="mt-2 text-sm text-slate-400">
                Order #{{ order?.id }} wordt volledig terugbetaald.
            </p>

            <div class="mt-5 rounded-2xl border border-slate-800 bg-slate-950 p-4">
                <div class="flex items-center justify-between text-sm text-slate-400">
                    <span>Bedrag</span>
                    <span class="text-base font-semibold text-white">€ {{ formatPrice(order?.total_incl_vat) }}</span>
                </div>
            </div>

            <div class="mt-5">
                <label class="mb-2 block text-xs font-medium uppercase tracking-wide text-slate-400">
                    Terugbetalingsmethode
                </label>

                <select
                    v-model="refundMethod"
                    class="w-full rounded-2xl border border-slate-700 bg-slate-800 px-4 py-3 text-sm text-slate-100 outline-none transition focus:border-blue-500 focus:ring-2 focus:ring-blue-900"
                >
                    <option value="cash">Cash</option>
                    <option value="bancontact">Bancontact</option>
                </select>
            </div>

            <div class="mt-5">
                <label class="mb-2 block text-xs font-medium uppercase tracking-wide text-slate-400">
                    Reden
                </label>

                <textarea
                    v-model="reason"
                    rows="4"
                    class="w-full rounded-2xl border border-slate-700 bg-slate-800 px-4 py-3 text-sm text-slate-100 outline-none transition focus:border-blue-500 focus:ring-2 focus:ring-blue-900"
                    placeholder="Optionele reden voor terugbetaling"
                />
            </div>

            <div class="mt-6 grid grid-cols-2 gap-3">
                <button
                    type="button"
                    class="rounded-2xl border border-slate-700 bg-slate-800 px-4 py-3 text-sm font-semibold text-slate-200 transition hover:bg-slate-700"
                    @click="$emit('close')"
                >
                    Sluiten
                </button>

                <button
                    type="button"
                    class="rounded-2xl bg-rose-600 px-4 py-3 text-sm font-semibold text-white transition hover:bg-rose-500"
                    @click="submit"
                >
                    Bevestig terugbetaling
                </button>
            </div>
        </div>
    </div>
</template>

<script setup>
import { ref, watch } from 'vue'
import { useSalesStore } from '../stores/useSalesStore.js'

const props = defineProps({
    open: Boolean,
    order: {
        type: Object,
        default: null,
    },
})

const emit = defineEmits(['close'])
const store = useSalesStore()

const refundMethod = ref('cash')
const reason = ref('')

watch(
    () => props.open,
    (value) => {
        if (value) {
            refundMethod.value = props.order?.payment_method ?? 'cash'
            reason.value = ''
        }
    }
)

function formatPrice(value) {
    return new Intl.NumberFormat('nl-BE', {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2,
    }).format(Number(value ?? 0))
}

async function submit() {
    const ok = await store.refundSelectedOrder({
        refund_method: refundMethod.value,
        reason: reason.value,
    })

    if (ok) {
        emit('close')
    }
}
</script>
