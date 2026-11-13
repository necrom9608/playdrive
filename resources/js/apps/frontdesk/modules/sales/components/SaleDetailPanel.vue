<template>
    <div class="flex h-full min-h-0 flex-col rounded-3xl border border-slate-800 bg-slate-900 shadow-xl">
        <div class="border-b border-slate-800 p-4">
            <h2 class="text-base font-semibold text-white">Details</h2>
        </div>

        <div v-if="store.selectedOrder" class="min-h-0 flex-1 overflow-auto p-4 space-y-4">
            <div class="rounded-2xl border border-slate-800 bg-slate-950 p-4">
                <div class="text-sm text-slate-400">Order</div>
                <div class="mt-1 text-lg font-bold text-white">#{{ store.selectedOrder.id }}</div>
            </div>

            <div class="grid gap-3 sm:grid-cols-2">
                <button
                    type="button"
                    class="inline-flex items-center justify-center rounded-2xl border border-sky-500/30 bg-sky-500/10 px-4 py-3 text-sm font-semibold text-sky-100 transition hover:bg-sky-500/20 disabled:cursor-not-allowed disabled:opacity-60"
                    :disabled="receiptActionLoading"
                    @click="handlePrintReceipt"
                >
                    DRUK BON
                </button>

                <button
                    type="button"
                    class="inline-flex items-center justify-center rounded-2xl border border-emerald-500/30 bg-emerald-500/10 px-4 py-3 text-sm font-semibold text-emerald-100 transition hover:bg-emerald-500/20 disabled:cursor-not-allowed disabled:opacity-60"
                    :disabled="receiptActionLoading"
                    @click="openReceiptEmailModal"
                >
                    MAIL BON
                </button>
            </div>

            <div
                v-if="receiptActionMessage"
                class="rounded-2xl border border-emerald-500/30 bg-emerald-500/10 px-4 py-3 text-sm text-emerald-100"
            >
                {{ receiptActionMessage }}
            </div>

            <div
                v-if="receiptActionError"
                class="rounded-2xl border border-rose-500/30 bg-rose-500/10 px-4 py-3 text-sm text-rose-100"
            >
                {{ receiptActionError }}
            </div>

            <div class="rounded-2xl border border-slate-800 bg-slate-950 p-4 space-y-2">
                <div class="flex justify-between text-sm">
                    <span class="text-slate-400">Type</span>
                    <span class="text-white">{{ store.selectedOrder.source === 'reservation' ? 'Reservatie' : 'Losse verkoop' }}</span>
                </div>

                <div class="flex justify-between text-sm">
                    <span class="text-slate-400">Betaalmethode</span>
                    <span class="text-white">{{ store.selectedOrder.payment_method }}</span>
                </div>

                <div class="flex justify-between text-sm">
                    <span class="text-slate-400">Factuur</span>
                    <span class="text-white">{{ store.selectedOrder.invoice_requested ? 'Ja' : 'Nee' }}</span>
                </div>

                <div class="flex justify-between text-sm">
                    <span class="text-slate-400">Totaal</span>
                    <span class="text-white">€ {{ formatPrice(store.selectedOrder.total_incl_vat) }}</span>
                </div>
            </div>

            <!-- Factuur: gegevens + WeFact -->
            <div v-if="invoice || canInvoice" class="rounded-2xl border border-slate-800 bg-slate-950 p-4 space-y-3">
                <div class="flex items-center justify-between gap-3">
                    <div class="text-sm font-semibold text-white">Factuur</div>
                    <span class="rounded-full border px-2.5 py-0.5 text-xs font-semibold" :class="invoiceBadge.class">{{ invoiceBadge.label }}</span>
                </div>

                <!-- Factuur gevraagd aan/uit (zolang er nog niets in WeFact staat) -->
                <label v-if="!invoice?.in_wefact" class="flex cursor-pointer items-center justify-between gap-3 text-sm">
                    <span class="text-slate-300">Factuur gevraagd</span>
                    <input
                        type="checkbox"
                        class="h-5 w-5 accent-sky-500"
                        :checked="!!store.selectedOrder.invoice_requested"
                        :disabled="invoiceBusy"
                        @change="toggleRequested($event)"
                    >
                </label>

                <!-- Factuurgegevens -->
                <div v-if="store.selectedOrder.invoice_requested || invoice" class="rounded-xl border border-slate-800 bg-slate-900 px-3 py-2.5 text-sm">
                    <div class="mb-1.5 flex items-center justify-between gap-3">
                        <span class="text-xs font-semibold uppercase tracking-wide text-slate-500">Factuurgegevens</span>
                        <button v-if="!invoice?.in_wefact" type="button" class="text-xs font-semibold text-sky-300 hover:text-sky-200" @click="openInvoiceModal(details)">Aanpassen</button>
                    </div>
                    <template v-if="hasDetails">
                        <div class="font-medium text-white">{{ details.company_name || details.contact_name }}</div>
                        <div v-if="details.company_name && details.contact_name" class="text-slate-400">t.a.v. {{ details.contact_name }}</div>
                        <div v-if="details.vat_number" class="text-slate-400">{{ details.vat_number }}</div>
                        <div v-if="details.address || details.city" class="text-slate-400">{{ [details.address, [details.postal_code, details.city].filter(Boolean).join(' ')].filter(Boolean).join(', ') }}</div>
                        <div v-if="details.email" class="text-slate-400">{{ details.email }}</div>
                        <div v-if="details.po_number" class="text-slate-400">Bestelbon: {{ details.po_number }}</div>
                        <div v-if="details.comment" class="whitespace-pre-line text-xs text-slate-500">{{ details.comment }}</div>
                    </template>
                    <p v-else class="text-slate-500">Nog geen gegevens.</p>
                </div>

                <p v-if="!store.summary?.invoicing_enabled" class="rounded-xl border border-amber-500/30 bg-amber-500/10 px-3 py-2 text-sm text-amber-100">
                    WeFact is nog niet ingesteld: vul de API-sleutel in bij Backoffice → Zaakgegevens → WeFact.
                </p>

                <template v-else>
                    <div v-if="invoice?.status === 'done'" class="text-sm">
                        <div class="font-semibold text-white">{{ invoice.invoice_code }}</div>
                        <div class="text-xs text-slate-500">{{ invoice.channel === 'peppol' ? 'Verstuurd via Peppol' : `Verstuurd per e-mail${invoice.email ? ` naar ${invoice.email}` : ''}` }}</div>
                    </div>

                    <p v-if="invoice?.status === 'pending'" class="text-sm text-sky-200">Wordt naar WeFact gestuurd…</p>
                    <p v-if="invoice?.error && invoice.status !== 'done'" class="rounded-xl border px-3 py-2 text-sm" :class="invoice.status === 'missing_details' ? 'border-amber-500/30 bg-amber-500/10 text-amber-100' : 'border-rose-500/30 bg-rose-500/10 text-rose-100'">
                        {{ invoice.error }}
                    </p>

                    <div v-if="invoice?.status !== 'done'" class="grid gap-2">
                        <button v-if="invoice?.status === 'pending'" type="button" class="rounded-2xl border border-slate-700 bg-slate-800 px-4 py-2.5 text-sm font-semibold text-slate-200 transition hover:bg-slate-700" @click="store.fetchSales()">
                            Vernieuwen
                        </button>
                        <button v-if="!invoice?.in_wefact && invoice?.status !== 'pending'" type="button" class="rounded-2xl bg-sky-600 px-4 py-2.5 text-sm font-semibold text-white transition hover:bg-sky-500" @click="openInvoiceModal(details)">
                            Factuur maken
                        </button>
                        <button v-if="invoice?.status === 'error'" type="button" class="rounded-2xl border border-slate-700 bg-slate-800 px-4 py-2.5 text-sm font-semibold text-slate-200 transition hover:bg-slate-700 disabled:opacity-50" :disabled="invoiceBusy" @click="retryInvoice(false)">
                            {{ invoiceBusy ? 'Bezig…' : 'Opnieuw proberen' }}
                        </button>
                        <button v-if="invoice?.status === 'error' && invoice.channel === 'peppol' && invoice.email" type="button" class="rounded-2xl border border-slate-700 bg-slate-800 px-4 py-2.5 text-sm font-semibold text-slate-200 transition hover:bg-slate-700 disabled:opacity-50" :disabled="invoiceBusy" @click="retryInvoice(true)">
                            Toch per e-mail versturen
                        </button>
                    </div>
                </template>

                <p v-if="invoiceMessage" class="text-sm" :class="invoiceOk ? 'text-emerald-300' : 'text-rose-300'">{{ invoiceMessage }}</p>
            </div>

            <div v-if="store.selectedOrder.cancelled_at" class="rounded-2xl border border-amber-500/30 bg-amber-500/10 p-4">
                <div class="text-sm font-semibold text-amber-300">Geannuleerd</div>
                <div v-if="store.selectedOrder.cancellation_reason" class="mt-2 text-sm text-amber-100">
                    {{ store.selectedOrder.cancellation_reason }}
                </div>
            </div>

            <div v-if="store.selectedOrder.refunded_at" class="rounded-2xl border border-rose-500/30 bg-rose-500/10 p-4">
                <div class="text-sm font-semibold text-rose-300">Terugbetaald</div>
                <div class="mt-2 text-sm text-rose-100">
                    € {{ formatPrice(store.selectedOrder.refund_amount) }} via {{ store.selectedOrder.refund_method }}
                </div>
                <div v-if="store.selectedOrder.refund_reason" class="mt-2 text-sm text-rose-100">
                    {{ store.selectedOrder.refund_reason }}
                </div>
            </div>

            <div class="rounded-2xl border border-slate-800 bg-slate-950 p-4">
                <div class="mb-3 text-sm font-semibold text-white">Orderlijnen</div>

                <div class="space-y-2">
                    <div
                        v-for="item in store.selectedOrder.items"
                        :key="item.id"
                        class="flex items-center justify-between rounded-xl border border-slate-800 bg-slate-900 px-3 py-2"
                    >
                        <div>
                            <div class="text-sm font-medium text-white">{{ item.name }}</div>
                            <div class="text-xs text-slate-400">x{{ item.quantity }}</div>
                        </div>

                        <div class="text-sm font-semibold text-white">
                            € {{ formatPrice(item.line_total_incl_vat) }}
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div v-else class="flex flex-1 items-center justify-center p-6 text-sm text-slate-400">
            Selecteer een order om details te bekijken.
        </div>
    </div>

    <teleport to="body">
        <div
            v-if="receiptEmailModalOpen"
            class="fixed inset-0 z-[90] flex items-center justify-center bg-slate-950/80 px-4"
            @click.self="closeReceiptEmailModal"
        >
            <div class="w-full max-w-md rounded-3xl border border-slate-700 bg-slate-900 p-6 shadow-2xl">
                <div class="flex items-start justify-between gap-4">
                    <div>
                        <h3 class="text-lg font-semibold text-white">Bon mailen</h3>
                        <p class="mt-1 text-sm text-slate-400">Geef het e-mailadres in voor bon #{{ store.selectedOrder?.id }}.</p>
                    </div>

                    <button
                        type="button"
                        class="rounded-xl border border-slate-700 px-3 py-1.5 text-sm text-slate-300 transition hover:bg-slate-800"
                        @click="closeReceiptEmailModal"
                    >
                        Sluiten
                    </button>
                </div>

                <div class="mt-5 space-y-3">
                    <label class="block text-sm font-medium text-slate-200" for="sales-receipt-email">
                        E-mailadres
                    </label>
                    <input
                        id="sales-receipt-email"
                        v-model="receiptEmail"
                        type="email"
                        class="w-full rounded-2xl border border-slate-700 bg-slate-950 px-4 py-3 text-sm text-white outline-none transition focus:border-sky-500"
                        placeholder="naam@voorbeeld.be"
                        @keydown.enter.prevent="confirmReceiptEmail"
                    >

                    <p v-if="receiptEmailError" class="text-sm text-rose-300">
                        {{ receiptEmailError }}
                    </p>
                </div>

                <div class="mt-6 flex justify-end gap-3">
                    <button
                        type="button"
                        class="rounded-2xl border border-slate-700 px-4 py-2 text-sm font-medium text-slate-200 transition hover:bg-slate-800"
                        :disabled="receiptActionLoading"
                        @click="closeReceiptEmailModal"
                    >
                        Annuleren
                    </button>

                    <button
                        type="button"
                        class="rounded-2xl border border-emerald-500/30 bg-emerald-500/10 px-4 py-2 text-sm font-semibold text-emerald-100 transition hover:bg-emerald-500/20 disabled:cursor-not-allowed disabled:opacity-60"
                        :disabled="receiptActionLoading"
                        @click="confirmReceiptEmail"
                    >
                        {{ receiptActionLoading ? 'Verzenden...' : 'Verzenden' }}
                    </button>
                </div>
            </div>
        </div>
    </teleport>

    <teleport to="body">
        <div
            v-if="invoiceModalOpen"
            class="fixed inset-0 z-[90] flex items-center justify-center bg-slate-950/80 px-4"
            @click.self="closeInvoiceModal"
        >
            <form class="w-full max-w-lg rounded-3xl border border-slate-700 bg-slate-900 p-6 shadow-2xl" @submit.prevent="submitInvoice">
                <div class="flex items-start justify-between gap-4">
                    <div>
                        <h3 class="text-lg font-semibold text-white">Factuurgegevens</h3>
                        <p class="mt-1 text-sm text-slate-400">Bon #{{ store.selectedOrder?.id }} · € {{ formatPrice(store.selectedOrder?.total_incl_vat) }}. "Opslaan" bewaart enkel de gegevens; "Maken en versturen" maakt de factuur definitief en als betaald in WeFact.</p>
                    </div>
                    <button type="button" class="rounded-xl border border-slate-700 px-3 py-1.5 text-sm text-slate-300 transition hover:bg-slate-800" @click="closeInvoiceModal">Sluiten</button>
                </div>

                <div class="mt-5 grid gap-3 sm:grid-cols-2">
                    <label class="space-y-1 sm:col-span-2">
                        <span class="text-sm text-slate-300">Bedrijfsnaam</span>
                        <input v-model="invoiceForm.company_name" type="text" class="inv-input">
                    </label>
                    <label class="space-y-1">
                        <span class="text-sm text-slate-300">Naam contactpersoon</span>
                        <input v-model="invoiceForm.contact_name" type="text" class="inv-input">
                    </label>
                    <label class="space-y-1">
                        <span class="text-sm text-slate-300">Btw-nummer</span>
                        <input v-model="invoiceForm.vat_number" type="text" placeholder="BE0123.456.789" class="inv-input">
                    </label>
                    <label class="space-y-1 sm:col-span-2">
                        <span class="text-sm text-slate-300">E-mail</span>
                        <input v-model="invoiceForm.email" type="email" class="inv-input">
                    </label>
                    <label class="space-y-1 sm:col-span-2">
                        <span class="text-sm text-slate-300">Adres</span>
                        <input v-model="invoiceForm.address" type="text" class="inv-input">
                    </label>
                    <label class="space-y-1">
                        <span class="text-sm text-slate-300">Postcode</span>
                        <input v-model="invoiceForm.postal_code" type="text" class="inv-input">
                    </label>
                    <label class="space-y-1">
                        <span class="text-sm text-slate-300">Gemeente</span>
                        <input v-model="invoiceForm.city" type="text" class="inv-input">
                    </label>
                    <label class="space-y-1 sm:col-span-2">
                        <span class="text-sm text-slate-300">Bestelbonnummer <span class="text-slate-500">(optioneel)</span></span>
                        <input v-model="invoiceForm.po_number" type="text" maxlength="100" placeholder="PO-nummer of referentie van de klant" class="inv-input">
                    </label>
                    <label class="space-y-1 sm:col-span-2">
                        <span class="text-sm text-slate-300">Extra commentaar op de factuur <span class="text-slate-500">(optioneel)</span></span>
                        <textarea v-model="invoiceForm.comment" rows="2" maxlength="1000" class="inv-input resize-y"></textarea>
                    </label>
                </div>

                <p class="mt-4 rounded-2xl border px-4 py-3 text-sm" :class="viaPeppol ? 'border-sky-500/30 bg-sky-500/10 text-sky-100' : 'border-slate-700 bg-slate-950 text-slate-300'">
                    {{ viaPeppol ? 'Belgisch btw-nummer: de factuur gaat via Peppol.' : 'Geen Belgisch btw-nummer: de factuur gaat per e-mail.' }}
                </p>
                <p v-if="invoiceFormError" class="mt-3 text-sm text-rose-300">{{ invoiceFormError }}</p>

                <div class="mt-6 flex justify-end gap-3">
                    <button type="button" class="rounded-2xl border border-slate-700 px-4 py-2 text-sm font-medium text-slate-200 transition hover:bg-slate-800" :disabled="invoiceBusy" @click="closeInvoiceModal">Annuleren</button>
                    <button type="button" class="rounded-2xl border border-sky-500/30 bg-sky-500/10 px-4 py-2 text-sm font-semibold text-sky-100 transition hover:bg-sky-500/20 disabled:opacity-60" :disabled="invoiceBusy" @click="saveInvoiceDetails">
                        Opslaan
                    </button>
                    <button v-if="store.summary?.invoicing_enabled" type="submit" class="rounded-2xl bg-sky-600 px-4 py-2 text-sm font-semibold text-white transition hover:bg-sky-500 disabled:opacity-60" :disabled="invoiceBusy">
                        {{ invoiceBusy ? 'Bezig met WeFact…' : 'Maken en versturen' }}
                    </button>
                </div>
            </form>
        </div>
    </teleport>
</template>

<script setup>
import { computed, reactive, ref, watch } from 'vue'
import axios from '@/lib/http'
import { useSalesStore } from '../stores/useSalesStore.js'

const store = useSalesStore()

const receiptActionLoading = ref(false)
const receiptActionError = ref('')
const receiptActionMessage = ref('')
const receiptEmailModalOpen = ref(false)
const receiptEmail = ref('')
const receiptEmailError = ref('')

// ── Factuur (WeFact) ─────────────────────────────────────────────────
const invoiceModalOpen = ref(false)
const invoiceBusy = ref(false)
const invoiceMessage = ref('')
const invoiceOk = ref(false)
const invoiceFormError = ref('')
const invoiceForm = reactive({ company_name: '', contact_name: '', vat_number: '', email: '', address: '', postal_code: '', city: '', po_number: '', comment: '' })

const invoice = computed(() => store.selectedOrder?.invoice ?? null)
// Gegevens: van de factuur, anders voorgevuld uit de reservatie
const details = computed(() => invoice.value ?? store.selectedOrder?.invoice_defaults ?? {})
const hasDetails = computed(() => !!(details.value.company_name || details.value.contact_name || details.value.vat_number || details.value.email))
const canInvoice = computed(() => {
    const order = store.selectedOrder
    return !!order && order.status === 'paid' && !order.cancelled_at && !order.refunded_at
})
const viaPeppol = computed(() => /^(BE)?[01]\d{9}$/.test(String(invoiceForm.vat_number || '').toUpperCase().replace(/[^A-Z0-9]/g, '')))
const invoiceBadge = computed(() => ({
    done: { label: 'Verstuurd', class: 'border-emerald-500/30 bg-emerald-500/10 text-emerald-200' },
    draft: { label: 'Klaar om te maken', class: 'border-sky-500/30 bg-sky-500/10 text-sky-200' },
    pending: { label: 'Bezig', class: 'border-sky-500/30 bg-sky-500/10 text-sky-200' },
    missing_details: { label: 'Gegevens ontbreken', class: 'border-amber-500/30 bg-amber-500/10 text-amber-200' },
    error: { label: 'Fout', class: 'border-rose-500/30 bg-rose-500/10 text-rose-200' },
}[invoice.value?.status] ?? { label: 'Geen', class: 'border-slate-700 bg-slate-800 text-slate-300' }))

watch(() => store.selectedOrder?.id, () => { invoiceMessage.value = '' })

function openInvoiceModal(details) {
    Object.keys(invoiceForm).forEach(key => { invoiceForm[key] = details?.[key] ?? '' })
    invoiceFormError.value = ''
    invoiceModalOpen.value = true
}

function closeInvoiceModal() {
    if (!invoiceBusy.value) invoiceModalOpen.value = false
}

async function submitInvoice() {
    const orderId = store.selectedOrder?.id
    if (!orderId || invoiceBusy.value) return
    if (!invoiceForm.company_name && !invoiceForm.contact_name) {
        invoiceFormError.value = 'Vul een bedrijfsnaam of naam in.'
        return
    }
    if (!viaPeppol.value && !invoiceForm.email) {
        invoiceFormError.value = 'Zonder Belgisch btw-nummer is een e-mailadres nodig.'
        return
    }
    invoiceBusy.value = true
    invoiceFormError.value = ''
    const result = await store.createInvoice(orderId, { ...invoiceForm, country: 'BE' })
    invoiceBusy.value = false
    invoiceOk.value = result.ok
    invoiceMessage.value = result.message
    if (result.ok || store.selectedOrder?.invoice) {
        invoiceModalOpen.value = false
    } else {
        invoiceFormError.value = result.message
    }
}

async function saveInvoiceDetails() {
    const orderId = store.selectedOrder?.id
    if (!orderId || invoiceBusy.value) return
    invoiceBusy.value = true
    invoiceFormError.value = ''
    const result = await store.saveInvoiceDetails(orderId, { ...invoiceForm, country: 'BE', invoice_requested: true })
    invoiceBusy.value = false
    invoiceOk.value = result.ok
    invoiceMessage.value = result.message
    if (result.ok) invoiceModalOpen.value = false
    else invoiceFormError.value = result.message
}

async function toggleRequested(event) {
    const requested = event.target.checked
    // Vinkje volgt de opgeslagen toestand; die wijzigt pas na opslaan.
    event.target.checked = !!store.selectedOrder?.invoice_requested
    const orderId = store.selectedOrder?.id
    if (!orderId || invoiceBusy.value) return
    if (requested) {
        openInvoiceModal(details.value)
        return
    }
    if (invoice.value && !window.confirm('Factuurgegevens van deze bon verwijderen?')) return
    invoiceBusy.value = true
    const result = await store.saveInvoiceDetails(orderId, { invoice_requested: false })
    invoiceBusy.value = false
    invoiceOk.value = result.ok
    invoiceMessage.value = result.message
}

async function retryInvoice(viaEmail) {
    const orderId = store.selectedOrder?.id
    if (!orderId || invoiceBusy.value) return
    invoiceBusy.value = true
    const result = await store.retryInvoice(orderId, viaEmail)
    invoiceBusy.value = false
    invoiceOk.value = result.ok
    invoiceMessage.value = result.message
}

function formatPrice(value) {
    return new Intl.NumberFormat('nl-BE', {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2,
    }).format(Number(value ?? 0))
}

function resetReceiptFeedback() {
    receiptActionError.value = ''
    receiptActionMessage.value = ''
}

function openReceiptEmailModal() {
    resetReceiptFeedback()
    receiptEmailError.value = ''
    receiptEmail.value = ''
    receiptEmailModalOpen.value = true
}

function closeReceiptEmailModal() {
    if (receiptActionLoading.value) {
        return
    }

    receiptEmailModalOpen.value = false
    receiptEmailError.value = ''
}

function printInHiddenIframe(url) {
    const iframe = document.createElement('iframe')
    iframe.style.position = 'fixed'
    iframe.style.right = '0'
    iframe.style.bottom = '0'
    iframe.style.width = '0'
    iframe.style.height = '0'
    iframe.style.border = '0'
    iframe.setAttribute('aria-hidden', 'true')
    iframe.src = url

    const cleanup = () => {
        window.setTimeout(() => {
            iframe.remove()
        }, 1000)
    }

    iframe.onload = () => {
        const frameWindow = iframe.contentWindow

        if (!frameWindow) {
            cleanup()
            return
        }

        window.setTimeout(() => {
            frameWindow.focus()
            cleanup()
        }, 250)
    }

    document.body.appendChild(iframe)
}

function handlePrintReceipt() {
    const orderId = store.selectedOrder?.id

    if (!orderId || receiptActionLoading.value) {
        return
    }

    resetReceiptFeedback()
    printInHiddenIframe(`/api/frontdesk/orders/${orderId}/receipt?auto_print=1`)
}

async function confirmReceiptEmail() {
    const orderId = store.selectedOrder?.id
    const email = String(receiptEmail.value ?? '').trim()

    if (!orderId || receiptActionLoading.value) {
        return
    }

    if (!email) {
        receiptEmailError.value = 'Geef een e-mailadres in.'
        return
    }

    resetReceiptFeedback()
    receiptEmailError.value = ''
    receiptActionLoading.value = true

    try {
        await axios.post(`/api/frontdesk/orders/${orderId}/send-receipt`, {
            email,
        })

        receiptActionMessage.value = `Bon #${orderId} werd verzonden naar ${email}.`
        receiptEmailModalOpen.value = false
        receiptEmail.value = ''
    } catch (error) {
        console.error('Failed to send receipt email', error)
        receiptEmailError.value = error?.response?.data?.message ?? 'Verzenden van de bon mislukte.'
    } finally {
        receiptActionLoading.value = false
    }
}
</script>

<style scoped>
.inv-input {
    width: 100%;
    border-radius: 1rem;
    border: 1px solid rgb(51 65 85);
    background: rgb(2 6 23);
    padding: 0.65rem 0.9rem;
    font-size: 0.875rem;
    color: white;
    outline: none;
}
.inv-input:focus { border-color: rgb(14 165 233); }
</style>
