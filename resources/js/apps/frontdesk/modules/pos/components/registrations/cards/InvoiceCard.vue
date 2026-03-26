<template>
    <div class="h-full">
        <FormCard title="Factuur" class="h-full">
            <div class="space-y-5">
                <div
                    class="flex items-center justify-between rounded-xl border px-4 py-3 transition"
                    :class="model.invoice_requested
                        ? 'border-blue-500/40 bg-blue-500/10'
                        : 'border-slate-700 bg-slate-800'"
                >
                    <div class="flex items-center gap-3">
                        <span class="text-lg">🧾</span>

                        <div>
                            <div class="text-sm font-medium text-slate-100">
                                Factuur gewenst
                            </div>

                            <div class="text-xs text-slate-400">
                                Vul enkel in indien nodig
                            </div>
                        </div>
                    </div>

                    <input
                        v-model="model.invoice_requested"
                        type="checkbox"
                        class="h-5 w-5 rounded border-slate-600 bg-slate-900 text-blue-500 focus:ring-2 focus:ring-blue-500"
                    >
                </div>

                <div class="space-y-4">
                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="mb-2 block text-xs font-medium text-slate-400">
                                Bedrijfsnaam
                            </label>

                            <input
                                v-model="model.invoice_company_name"
                                type="text"
                                :disabled="!model.invoice_requested"
                                class="w-full rounded-xl border border-slate-700 bg-slate-800 px-4 py-3 text-sm text-slate-100 shadow-sm transition focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-900 disabled:cursor-not-allowed disabled:opacity-50"
                            >
                        </div>

                        <div>
                            <label class="mb-2 block text-xs font-medium text-slate-400">
                                BTW-nummer
                            </label>

                            <input
                                v-model="model.invoice_vat_number"
                                type="text"
                                :disabled="!model.invoice_requested"
                                placeholder="BE0123.456.789"
                                class="w-full rounded-xl border border-slate-700 bg-slate-800 px-4 py-3 text-sm text-slate-100 shadow-sm transition focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-900 disabled:cursor-not-allowed disabled:opacity-50"
                            >
                        </div>
                    </div>

                    <div>
                        <label class="mb-2 block text-xs font-medium text-slate-400">
                            Factuur e-mail
                        </label>

                        <input
                            v-model="model.invoice_email"
                            type="email"
                            :disabled="!model.invoice_requested"
                            class="w-full rounded-xl border border-slate-700 bg-slate-800 px-4 py-3 text-sm text-slate-100 shadow-sm transition focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-900 disabled:cursor-not-allowed disabled:opacity-50"
                        >
                    </div>

                    <div class="grid grid-cols-[minmax(0,1fr)_120px] gap-3">
                        <div>
                            <label class="mb-2 block text-xs font-medium text-slate-400">
                                Straat
                            </label>

                            <input
                                v-model="model.invoice_street"
                                type="text"
                                :disabled="!model.invoice_requested"
                                class="w-full rounded-xl border border-slate-700 bg-slate-800 px-4 py-3 text-sm text-slate-100 shadow-sm transition focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-900 disabled:cursor-not-allowed disabled:opacity-50"
                            >
                        </div>

                        <div>
                            <label class="mb-2 block text-xs font-medium text-slate-400">
                                Nr
                            </label>

                            <input
                                v-model="model.invoice_house_number"
                                type="text"
                                :disabled="!model.invoice_requested"
                                class="w-full rounded-xl border border-slate-700 bg-slate-800 px-4 py-3 text-sm text-slate-100 shadow-sm transition focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-900 disabled:cursor-not-allowed disabled:opacity-50"
                            >
                        </div>
                    </div>

                    <div class="grid grid-cols-[160px_minmax(0,1fr)] gap-3">
                        <div>
                            <label class="mb-2 block text-xs font-medium text-slate-400">
                                Postcode
                            </label>

                            <input
                                v-model="model.invoice_postal_code"
                                type="text"
                                :disabled="!model.invoice_requested"
                                class="w-full rounded-xl border border-slate-700 bg-slate-800 px-4 py-3 text-sm text-slate-100 shadow-sm transition focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-900 disabled:cursor-not-allowed disabled:opacity-50"
                            >
                        </div>

                        <div>
                            <label class="mb-2 block text-xs font-medium text-slate-400">
                                Gemeente
                            </label>

                            <input
                                v-model="model.invoice_city"
                                type="text"
                                :disabled="!model.invoice_requested"
                                class="w-full rounded-xl border border-slate-700 bg-slate-800 px-4 py-3 text-sm text-slate-100 shadow-sm transition focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-900 disabled:cursor-not-allowed disabled:opacity-50"
                            >
                        </div>
                    </div>
                </div>
            </div>
        </FormCard>
    </div>
</template>

<script setup>
import FormCard from '../fields/FormCard.vue'

const model = defineModel({ type: Object, required: true })
</script>
