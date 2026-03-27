<template>
    <div class="rounded-3xl border border-slate-800 bg-slate-900 p-4 shadow-xl">
        <div class="grid items-end gap-4 xl:grid-cols-[1.2fr_1fr_1fr_1fr_auto_auto]">

            <!-- Datum -->
            <div>
                <label class="mb-1 block text-xs text-slate-400">Datum</label>
                <input
                    type="date"
                    :value="store.selectedDate"
                    @input="store.setSelectedDate($event.target.value)"
                    class="w-full rounded-xl border border-slate-700 bg-slate-800 px-3 py-2 text-sm text-white"
                />
            </div>

            <!-- Factuur -->
            <div>
                <label class="mb-1 block text-xs text-slate-400">Factuur</label>
                <select
                    :value="store.invoiceFilter"
                    @change="store.setInvoiceFilter($event.target.value)"
                    class="w-full rounded-xl border border-slate-700 bg-slate-800 px-3 py-2 text-sm text-white"
                >
                    <option value="all">Alles</option>
                    <option value="yes">Met factuur</option>
                    <option value="no">Zonder factuur</option>
                </select>
            </div>

            <!-- Betaalmethode -->
            <div>
                <label class="mb-1 block text-xs text-slate-400">Betaalmethode</label>
                <select
                    :value="store.paymentMethodFilter"
                    @change="store.setPaymentMethodFilter($event.target.value)"
                    class="w-full rounded-xl border border-slate-700 bg-slate-800 px-3 py-2 text-sm text-white"
                >
                    <option value="all">Alles</option>
                    <option value="cash">Cash</option>
                    <option value="bancontact">Bancontact</option>
                </select>
            </div>

            <!-- Type -->
            <div>
                <label class="mb-1 block text-xs text-slate-400">Type verkoop</label>
                <select
                    :value="store.sourceFilter"
                    @change="store.setSourceFilter($event.target.value)"
                    class="w-full rounded-xl border border-slate-700 bg-slate-800 px-3 py-2 text-sm text-white"
                >
                    <option value="all">Alles</option>
                    <option value="walk_in">Losse verkoop</option>
                    <option value="reservation">Reservatie</option>
                </select>
            </div>

            <!-- Vernieuwen -->
            <button
                class="rounded-2xl bg-blue-600 px-4 py-2 text-sm font-semibold text-white transition hover:bg-blue-500"
                @click="store.fetchSales()"
            >
                Vernieuwen
            </button>

            <!-- Reset -->
            <button
                class="rounded-2xl border border-slate-700 bg-slate-800 px-4 py-2 text-sm text-slate-300 transition hover:bg-slate-700"
                @click="resetAndReload"
            >
                Filters wissen
            </button>

        </div>
    </div>
</template>

<script setup>
import { useSalesStore } from '../stores/useSalesStore'

const store = useSalesStore()

function resetAndReload() {
    const now = new Date()
    const year = now.getFullYear()
    const month = String(now.getMonth() + 1).padStart(2, '0')
    const day = String(now.getDate()).padStart(2, '0')
    store.setSelectedDate(`${year}-${month}-${day}`)
    store.setInvoiceFilter('all')
    store.setPaymentMethodFilter('all')
    store.setSourceFilter('all')
    store.fetchSales()
}
</script>
