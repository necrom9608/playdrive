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
                                Wordt bij het afrekenen meteen gemaakt en verstuurd
                            </div>
                        </div>
                    </div>

                    <input
                        v-model="model.invoice_requested"
                        type="checkbox"
                        class="h-5 w-5 rounded border-slate-600 bg-slate-900 text-blue-500 focus:ring-2 focus:ring-blue-500"
                    >
                </div>

                <div v-if="model.invoice_requested" class="space-y-4">
                    <div>
                        <label class="mb-2 block text-xs font-medium text-slate-400">
                            BTW-nummer
                        </label>

                        <div class="flex gap-2">
                            <input
                                v-model="model.invoice_vat_number"
                                type="text"
                                placeholder="BE0123.456.789"
                                :class="inputClass"
                                @keydown.enter.prevent="lookupVat"
                            >

                            <button
                                type="button"
                                class="shrink-0 rounded-xl border border-sky-500/30 bg-sky-500/10 px-4 text-sm font-semibold text-sky-100 transition hover:bg-sky-500/20 disabled:cursor-not-allowed disabled:opacity-50"
                                :disabled="vatBusy || !model.invoice_vat_number"
                                title="Bedrijfsgegevens ophalen via VIES"
                                @click="lookupVat"
                            >
                                {{ vatBusy ? 'Ophalen…' : 'Ophalen' }}
                            </button>
                        </div>

                        <p v-if="vatMessage" class="mt-2 text-xs" :class="vatOk ? 'text-emerald-300' : 'text-amber-300'">
                            {{ vatMessage }}
                        </p>
                        <p v-else-if="isBelgianVat" class="mt-2 text-xs text-slate-500">
                            Belgisch btw-nummer: de factuur gaat via Peppol.
                        </p>
                    </div>

                    <div class="grid grid-cols-2 gap-3">
                        <div>
                            <label class="mb-2 block text-xs font-medium text-slate-400">
                                Bedrijfsnaam
                            </label>

                            <input v-model="model.invoice_company_name" type="text" :class="inputClass">
                        </div>

                        <div>
                            <label class="mb-2 block text-xs font-medium text-slate-400">
                                Factuur e-mail
                            </label>

                            <input v-model="model.invoice_email" type="email" :class="inputClass">
                        </div>
                    </div>

                    <div>
                        <label class="mb-2 block text-xs font-medium text-slate-400">
                            Straat en nr
                        </label>

                        <input v-model="model.invoice_address" type="text" :class="inputClass">
                    </div>

                    <div class="grid grid-cols-[160px_minmax(0,1fr)] gap-3">
                        <div>
                            <label class="mb-2 block text-xs font-medium text-slate-400">
                                Postcode
                            </label>

                            <input v-model="model.invoice_postal_code" type="text" :class="inputClass">
                        </div>

                        <div>
                            <label class="mb-2 block text-xs font-medium text-slate-400">
                                Gemeente
                            </label>

                            <input v-model="model.invoice_city" type="text" :class="inputClass">
                        </div>
                    </div>

                    <div class="grid grid-cols-[220px_minmax(0,1fr)] gap-3">
                        <div>
                            <label class="mb-2 block text-xs font-medium text-slate-400">
                                Bestelbonnummer
                            </label>

                            <input v-model="model.invoice_po_number" type="text" placeholder="Optioneel" :class="inputClass">
                        </div>

                        <div>
                            <label class="mb-2 block text-xs font-medium text-slate-400">
                                Opmerking op de factuur
                            </label>

                            <input v-model="model.invoice_comment" type="text" placeholder="Optioneel" :class="inputClass">
                        </div>
                    </div>
                </div>
            </div>
        </FormCard>
    </div>
</template>

<script setup>
import { computed, ref } from 'vue'
import axios from '@/lib/http'
import FormCard from '../fields/FormCard.vue'

const model = defineModel({ type: Object, required: true })

const inputClass = 'w-full rounded-xl border border-slate-700 bg-slate-800 px-4 py-3 text-sm text-slate-100 shadow-sm transition focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-900'

const vatBusy = ref(false)
const vatOk = ref(false)
const vatMessage = ref('')

const isBelgianVat = computed(() => /^BE\s*[01]/i.test(String(model.value.invoice_vat_number || '').replace(/[.\s]/g, '')))

async function lookupVat() {
    const vat = String(model.value.invoice_vat_number || '').trim()
    if (!vat || vatBusy.value) return

    vatBusy.value = true
    vatMessage.value = ''

    try {
        const { data } = await axios.get('/api/frontdesk/vat-lookup', { params: { vat } })
        const found = data?.data ?? {}

        model.value.invoice_vat_number = found.vat_number || model.value.invoice_vat_number
        if (found.company_name) model.value.invoice_company_name = found.company_name
        if (found.address) model.value.invoice_address = found.address
        if (found.postal_code) model.value.invoice_postal_code = found.postal_code
        if (found.city) model.value.invoice_city = found.city

        vatOk.value = true
        vatMessage.value = found.company_name ? `Gevonden: ${found.company_name}` : 'Geldig btw-nummer (geen naam in VIES).'
    } catch (error) {
        vatOk.value = false
        vatMessage.value = error?.response?.data?.message ?? 'Ophalen mislukt: vul de gegevens zelf in.'
    } finally {
        vatBusy.value = false
    }
}
</script>
