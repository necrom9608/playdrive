<template>
    <div class="flex h-full min-h-0 w-full flex-col rounded-3xl border border-slate-800 bg-slate-900 shadow-xl">
        <div class="border-b border-slate-800 px-4 py-3">
            <h2 class="text-base font-semibold text-white">Bestelling</h2>

            <p class="mt-1 text-sm text-slate-400">
                {{ store.currentOrderLabel }}
            </p>
        </div>

        <div
            ref="listContainer"
            class="min-h-0 flex-1 overflow-auto px-3 py-3"
        >
            <div v-if="store.currentOrderItems.length" class="space-y-2">
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
                    @click="showCheckoutModal = true"
                >
                    Afrekenen
                </button>
            </div>
        </div>

        <CheckoutModal
            :open="showCheckoutModal"
            @close="showCheckoutModal = false"
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

function formatPrice(value) {
    return new Intl.NumberFormat('nl-BE', {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2,
    }).format(Number(value ?? 0))
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
