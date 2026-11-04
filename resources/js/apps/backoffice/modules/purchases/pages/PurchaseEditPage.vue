<template>
    <section class="flex h-full min-h-0 flex-col gap-6 overflow-auto">
        <div class="rounded-3xl border border-slate-800 bg-slate-900/90 p-6 shadow-xl">
            <div class="flex flex-wrap items-end justify-between gap-4">
                <div>
                    <p class="text-sm text-slate-400">
                        <router-link to="/purchases" class="hover:text-slate-200">Aankopen</router-link> / {{ detail ? detail.number : 'Nieuw' }}
                    </p>
                    <div class="mt-1 flex flex-wrap items-center gap-3">
                        <h1 class="text-2xl font-semibold text-white">{{ detail ? `Aankoop ${detail.number}` : 'Nieuwe aankoop van particulier' }}</h1>
                        <span v-if="detail" class="inline-flex rounded-full border px-2.5 py-1 text-xs font-semibold" :class="statusClasses(detail.status)">{{ statusLabel(detail.status) }}</span>
                    </div>
                </div>

                <div v-if="!loading" class="flex flex-wrap gap-3">
                    <template v-if="!readonly">
                        <button type="button" class="rounded-xl border border-slate-700 px-4 py-3 text-sm font-semibold text-slate-200 transition hover:bg-slate-800 disabled:opacity-60" :disabled="saving" @click="save()">
                            {{ detail ? 'Opslaan' : 'Opslaan als concept' }}
                        </button>
                        <button v-if="detail?.status === 'sent' && detail.public_url" type="button" class="inline-flex items-center gap-2 rounded-xl bg-blue-600 px-4 py-3 text-sm font-semibold text-white transition hover:bg-blue-500 disabled:opacity-60" :disabled="saving" @click="saveAndShare(false)">
                            <QrCodeIcon class="h-5 w-5" /> QR / link tonen
                        </button>
                        <button v-else type="button" class="inline-flex items-center gap-2 rounded-xl bg-blue-600 px-4 py-3 text-sm font-semibold text-white transition hover:bg-blue-500 disabled:opacity-60" :disabled="saving" @click="saveAndShare(!!form.invite_email)">
                            <PaperAirplaneIcon class="h-5 w-5" /> {{ detail?.status === 'sent' ? 'Nieuwe link versturen' : 'Versturen naar verkoper' }}
                        </button>
                    </template>
                    <template v-else-if="detail">
                        <a v-if="detail.pdf_url" :href="detail.pdf_url" target="_blank" rel="noopener" class="inline-flex items-center gap-2 rounded-xl border border-slate-700 px-4 py-3 text-sm font-semibold text-slate-200 transition hover:bg-slate-800">
                            <DocumentArrowDownIcon class="h-5 w-5" /> PDF openen
                        </a>
                        <button v-if="detail.status === 'signed'" type="button" class="inline-flex items-center gap-2 rounded-xl bg-emerald-600 px-4 py-3 text-sm font-semibold text-white transition hover:bg-emerald-500 disabled:opacity-60" :disabled="saving" @click="run(markPurchasePaid)">
                            <BanknotesIcon class="h-5 w-5" /> Markeer betaald
                        </button>
                    </template>
                </div>
            </div>
        </div>

        <p v-if="feedback" class="rounded-2xl border px-4 py-3 text-sm" :class="feedback.type === 'error' ? 'border-rose-500/30 bg-rose-500/10 text-rose-200' : 'border-emerald-500/30 bg-emerald-500/10 text-emerald-200'">
            {{ feedback.text }}
        </p>

        <div v-if="loading" class="rounded-3xl border border-slate-800 bg-slate-900/90 p-6 text-sm text-slate-400">Laden...</div>

        <div v-else class="grid items-start gap-6 xl:grid-cols-[minmax(0,2fr)_minmax(320px,1fr)]">
            <div class="flex min-w-0 flex-col gap-6">
                <!-- Goederen -->
                <div class="rounded-3xl border border-slate-800 bg-slate-900/90 p-6 shadow-xl">
                    <h2 class="mb-4 text-lg font-semibold text-white">Goederen</h2>

                    <div class="overflow-x-auto">
                        <table class="w-full min-w-[560px] text-sm">
                            <thead>
                                <tr class="text-left text-xs uppercase tracking-wide text-slate-400">
                                    <th class="pb-2 pr-2 font-semibold">Omschrijving</th>
                                    <th class="w-24 px-2 pb-2 font-semibold">Aantal</th>
                                    <th class="w-36 px-2 pb-2 font-semibold">Prijs/stuk (€)</th>
                                    <th class="w-28 px-2 pb-2 text-right font-semibold">Totaal</th>
                                    <th v-if="!readonly" class="w-12 pb-2"></th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-for="(item, index) in form.items" :key="item.key" class="border-t border-slate-800">
                                    <td class="py-2 pr-2">
                                        <input v-model="item.description" :disabled="readonly" type="text" maxlength="255" placeholder="bv. Arcadekast Sega Rally 2" aria-label="Omschrijving" class="w-full rounded-xl border border-slate-700 bg-slate-950 px-3 py-2.5 text-sm text-white placeholder:text-slate-600 disabled:border-transparent disabled:bg-transparent disabled:px-0" />
                                    </td>
                                    <td class="px-2 py-2">
                                        <input v-model.number="item.quantity" :disabled="readonly" type="number" min="0.01" step="1" aria-label="Aantal" class="w-full rounded-xl border border-slate-700 bg-slate-950 px-3 py-2.5 text-sm text-white disabled:border-transparent disabled:bg-transparent" />
                                    </td>
                                    <td class="px-2 py-2">
                                        <input v-model.number="item.unit_price" :disabled="readonly" type="number" min="0" step="0.01" aria-label="Prijs per stuk" class="w-full rounded-xl border border-slate-700 bg-slate-950 px-3 py-2.5 text-sm text-white disabled:border-transparent disabled:bg-transparent" />
                                    </td>
                                    <td class="px-2 py-2 text-right font-semibold text-white">{{ formatCurrency(lineTotal(item)) }}</td>
                                    <td v-if="!readonly" class="py-2 text-right">
                                        <button type="button" class="inline-flex h-10 w-10 items-center justify-center rounded-xl text-slate-400 transition hover:bg-slate-800 hover:text-rose-300" aria-label="Regel verwijderen" @click="removeItem(index)">
                                            <TrashIcon class="h-5 w-5" />
                                        </button>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>

                    <div class="mt-4 flex flex-wrap items-center justify-between gap-3 border-t border-slate-800 pt-4">
                        <button v-if="!readonly" type="button" class="inline-flex items-center gap-2 rounded-xl border border-dashed border-slate-600 px-4 py-2.5 text-sm font-medium text-slate-300 transition hover:bg-slate-800" @click="addItem">
                            <PlusIcon class="h-4 w-4" /> Regel toevoegen
                        </button>
                        <span v-else />
                        <div class="flex items-baseline gap-4">
                            <span class="text-sm text-slate-400">Totaal aankoopprijs</span>
                            <span class="text-2xl font-bold text-white">{{ formatCurrency(total) }}</span>
                        </div>
                    </div>
                </div>

                <!-- Foto's -->
                <div class="rounded-3xl border border-slate-800 bg-slate-900/90 p-6 shadow-xl">
                    <h2 class="text-lg font-semibold text-white">Foto's van de goederen</h2>
                    <p class="mb-4 text-sm text-slate-400">Optioneel. De verkoper ziet ze in het formulier en ze komen als bijlage in de PDF.</p>
                    <div class="flex flex-wrap gap-3">
                        <div v-for="photo in detail?.photos || []" :key="photo.url" class="group relative h-24 w-32 overflow-hidden rounded-xl border border-slate-800 bg-slate-950">
                            <a :href="photo.url" target="_blank" rel="noopener"><img :src="photo.url" :alt="photo.name" class="h-full w-full object-cover" /></a>
                            <button v-if="!readonly" type="button" class="absolute right-1 top-1 inline-flex h-8 w-8 items-center justify-center rounded-lg bg-slate-950/80 text-slate-200 hover:text-rose-300" aria-label="Foto verwijderen" @click="removePhoto(photo.index)">
                                <XMarkIcon class="h-4 w-4" />
                            </button>
                        </div>
                        <label v-if="!readonly && (detail?.photos?.length || 0) < 8" class="flex h-24 w-32 cursor-pointer flex-col items-center justify-center gap-1 rounded-xl border border-dashed border-slate-600 text-sm text-slate-300 transition hover:bg-slate-800">
                            <CameraIcon class="h-6 w-6" />
                            {{ uploading ? 'Uploaden...' : 'Foto toevoegen' }}
                            <input type="file" accept="image/jpeg,image/png,image/webp" multiple class="hidden" :disabled="uploading" @change="addPhotos" />
                        </label>
                        <p v-if="readonly && !(detail?.photos?.length)" class="text-sm text-slate-500">Geen foto's.</p>
                    </div>
                </div>

                <!-- Betaling -->
                <div class="rounded-3xl border border-slate-800 bg-slate-900/90 p-6 shadow-xl">
                    <h2 class="mb-4 text-lg font-semibold text-white">Betaling</h2>
                    <div class="flex flex-wrap gap-3">
                        <label class="flex min-h-11 cursor-pointer items-center gap-3 rounded-xl border px-4 text-sm" :class="form.payment_method === 'transfer' ? 'border-blue-500 bg-blue-500/10 font-semibold text-white' : 'border-slate-700 text-slate-300'">
                            <input v-model="form.payment_method" :disabled="readonly" type="radio" value="transfer" class="accent-blue-500" /> Overschrijving
                        </label>
                        <label class="flex min-h-11 items-center gap-3 rounded-xl border px-4 text-sm" :class="[form.payment_method === 'cash' ? 'border-blue-500 bg-blue-500/10 font-semibold text-white' : 'border-slate-700 text-slate-300', cashBlocked ? 'opacity-50' : 'cursor-pointer']">
                            <input v-model="form.payment_method" :disabled="readonly || cashBlocked" type="radio" value="cash" class="accent-blue-500" /> Contant
                        </label>
                    </div>
                    <p v-if="!readonly" class="mt-3 flex items-start gap-2 rounded-2xl border px-4 py-3 text-sm" :class="cashOverLimit ? 'border-rose-500/30 bg-rose-500/10 text-rose-200' : 'border-amber-500/30 bg-amber-500/10 text-amber-200'">
                        <ExclamationTriangleIcon class="mt-0.5 h-5 w-5 shrink-0" />
                        <span v-if="cashOverLimit">Contant kan niet boven {{ formatCurrency(cashLimit) }}. Kies voor overschrijving.</span>
                        <span v-else>Contant kan enkel tot {{ formatCurrency(cashLimit) }}. Bij overschrijving vult de verkoper zelf zijn IBAN in.</span>
                    </p>

                    <div class="mt-5 grid gap-4 md:grid-cols-2">
                        <div>
                            <label class="mb-2 block text-sm font-medium text-slate-300" for="pp-date">Aankoopdatum</label>
                            <input id="pp-date" v-model="form.purchase_date" :disabled="readonly" type="date" class="w-full rounded-2xl border border-slate-700 bg-slate-950 px-4 py-3 text-sm text-white disabled:opacity-70" />
                        </div>
                        <div>
                            <label class="mb-2 block text-sm font-medium text-slate-300" for="pp-place">Plaats van ondertekening</label>
                            <input id="pp-place" v-model="form.sign_place" :disabled="readonly" type="text" maxlength="100" class="w-full rounded-2xl border border-slate-700 bg-slate-950 px-4 py-3 text-sm text-white disabled:opacity-70" />
                        </div>
                    </div>

                    <div class="mt-4">
                        <label class="mb-2 block text-sm font-medium text-slate-300" for="pp-note">Interne notitie <span class="font-normal text-slate-500">(niet op het borderel)</span></label>
                        <textarea id="pp-note" v-model="form.internal_note" :disabled="readonly" rows="2" maxlength="2000" class="w-full rounded-2xl border border-slate-700 bg-slate-950 px-4 py-3 text-sm text-white disabled:opacity-70" />
                    </div>
                </div>
            </div>

            <div class="flex min-w-0 flex-col gap-6">
                <!-- Status -->
                <div v-if="detail" class="rounded-3xl border border-slate-800 bg-slate-900/90 p-6 shadow-xl">
                    <h2 class="mb-4 text-lg font-semibold text-white">Status</h2>
                    <dl class="flex flex-col gap-2 text-sm">
                        <div class="flex justify-between gap-3"><dt class="text-slate-400">Aangemaakt</dt><dd class="text-right text-slate-200">{{ formatDateTime(detail.created_at) }}<template v-if="detail.created_by"> · {{ detail.created_by }}</template></dd></div>
                        <div v-if="detail.token_expires_at && detail.status === 'sent'" class="flex justify-between gap-3"><dt class="text-slate-400">Link geldig tot</dt><dd class="text-right" :class="detail.link_expired ? 'text-amber-300' : 'text-slate-200'">{{ formatDateTime(detail.token_expires_at) }}<template v-if="detail.link_expired"> (verlopen)</template></dd></div>
                        <div v-if="detail.invited_at" class="flex justify-between gap-3"><dt class="text-slate-400">Link gemaild</dt><dd class="text-right text-slate-200">{{ formatDateTime(detail.invited_at) }}</dd></div>
                        <div v-if="detail.opened_at" class="flex justify-between gap-3"><dt class="text-slate-400">Geopend</dt><dd class="text-right text-slate-200">{{ formatDateTime(detail.opened_at) }}</dd></div>
                        <div v-if="detail.signed_at" class="flex justify-between gap-3"><dt class="text-slate-400">Ondertekend</dt><dd class="text-right text-slate-200">{{ formatDateTime(detail.signed_at) }}</dd></div>
                        <div v-if="detail.mailed_at" class="flex justify-between gap-3"><dt class="text-slate-400">PDF gemaild</dt><dd class="text-right text-slate-200">{{ formatDateTime(detail.mailed_at) }}<br><span class="text-xs text-slate-400">{{ detail.mailed_to.join(', ') }}</span></dd></div>
                        <div v-if="detail.paid_at" class="flex justify-between gap-3"><dt class="text-slate-400">Betaald</dt><dd class="text-right text-slate-200">{{ formatDateTime(detail.paid_at) }}</dd></div>
                        <div v-if="detail.cancelled_at" class="flex justify-between gap-3"><dt class="text-slate-400">Geannuleerd</dt><dd class="text-right text-slate-200">{{ formatDateTime(detail.cancelled_at) }}</dd></div>
                    </dl>
                    <p v-if="detail.mail_error" class="mt-3 rounded-2xl border border-rose-500/30 bg-rose-500/10 px-4 py-3 text-sm text-rose-200">Mailen mislukt: {{ detail.mail_error }}</p>

                    <div class="mt-5 flex flex-wrap gap-2">
                        <button v-if="['signed', 'paid'].includes(detail.status)" type="button" class="rounded-xl border border-slate-700 px-3 py-2 text-sm font-medium text-slate-200 hover:bg-slate-800 disabled:opacity-60" :disabled="saving" @click="run(resendPurchasePdf)">PDF opnieuw mailen</button>
                        <button v-if="detail.status === 'paid'" type="button" class="rounded-xl border border-slate-700 px-3 py-2 text-sm font-medium text-slate-200 hover:bg-slate-800 disabled:opacity-60" :disabled="saving" @click="run(markPurchaseUnpaid)">Terug naar niet betaald</button>
                        <button v-if="detail.status === 'signed'" type="button" class="rounded-xl border border-rose-500/30 px-3 py-2 text-sm font-medium text-rose-300 hover:bg-rose-500/10 disabled:opacity-60" :disabled="saving" @click="confirmRun('Dit ondertekende borderel annuleren?', cancelPurchase)">Annuleren</button>
                        <button v-if="!detail.signed_at" type="button" class="rounded-xl border border-rose-500/30 px-3 py-2 text-sm font-medium text-rose-300 hover:bg-rose-500/10 disabled:opacity-60" :disabled="saving" @click="remove">Verwijderen</button>
                    </div>
                </div>

                <!-- Verkoper -->
                <div class="rounded-3xl border border-slate-800 bg-slate-900/90 p-6 shadow-xl">
                    <h2 class="text-lg font-semibold text-white">Verkoper</h2>
                    <template v-if="detail?.seller">
                        <div class="mt-4 flex flex-col gap-1 text-sm text-slate-200">
                            <p class="font-semibold text-white">{{ detail.seller.first_name }} {{ detail.seller.last_name }}</p>
                            <p>{{ detail.seller.street }}</p>
                            <p>{{ detail.seller.postal_code }} {{ detail.seller.city }}<template v-if="detail.seller.country && detail.seller.country !== 'België'">, {{ detail.seller.country }}</template></p>
                            <p class="mt-2">{{ detail.seller.email }}</p>
                            <p v-if="detail.seller.phone">{{ detail.seller.phone }}</p>
                            <p v-if="detail.seller.iban" class="mt-2 font-mono">{{ detail.seller.iban }}</p>
                            <p v-if="detail.seller.iban_holder" class="text-slate-400">op naam van {{ detail.seller.iban_holder }}</p>
                            <p class="mt-3 text-xs text-slate-500">Getekend vanaf IP {{ detail.signed_ip }} · controlecode {{ detail.document_hash?.slice(0, 16) }}</p>
                        </div>
                    </template>
                    <template v-else>
                        <p class="mt-1 text-sm text-slate-400">De verkoper vult zelf naam, adres en IBAN in. Geef een e-mailadres op om de link te mailen, of laat leeg en toon de QR-code aan de balie.</p>
                        <label class="mt-4 block text-sm font-medium text-slate-300" for="pp-email">E-mail verkoper (optioneel)</label>
                        <input id="pp-email" v-model="form.invite_email" :disabled="readonly" type="email" placeholder="naam@voorbeeld.be" class="mt-2 w-full rounded-2xl border border-slate-700 bg-slate-950 px-4 py-3 text-sm text-white placeholder:text-slate-600" />
                        <p class="mt-2 text-xs text-slate-500">De link blijft {{ defaults.link_days || 7 }} dagen geldig.</p>
                    </template>
                </div>

                <!-- Ontvangers -->
                <div class="rounded-3xl border border-slate-800 bg-slate-900/90 p-6 shadow-xl">
                    <h2 class="mb-4 text-lg font-semibold text-white">PDF na ondertekening naar</h2>
                    <div class="flex flex-col gap-3 text-sm">
                        <label class="flex items-start gap-3 text-slate-200">
                            <input v-model="form.mail_seller" :disabled="readonly" type="checkbox" class="mt-1 h-4 w-4 accent-blue-500" />
                            <span>Verkoper<br><span class="text-xs text-slate-500">het e-mailadres dat de verkoper invult</span></span>
                        </label>
                        <label class="flex items-start gap-3 text-slate-200">
                            <input v-model="form.mail_tenant" :disabled="readonly" type="checkbox" class="mt-1 h-4 w-4 accent-blue-500" />
                            <span>De zaak<br><span class="text-xs" :class="defaults.tenant_email ? 'text-slate-500' : 'text-amber-300'">{{ defaults.tenant_email || 'geen e-mailadres ingesteld bij de zaak' }}</span></span>
                        </label>
                        <label class="flex items-start gap-3 text-slate-200">
                            <input v-model="form.mail_accounting" :disabled="readonly" type="checkbox" class="mt-1 h-4 w-4 accent-blue-500" />
                            <span>Boekhouding<br>
                                <span v-if="defaults.accounting_recipients?.length" class="text-xs text-slate-500">{{ defaults.accounting_recipients.join(', ') }}</span>
                                <span v-else class="text-xs text-amber-300">nog geen adres · <router-link to="/accounting" class="underline">instellen bij Boekhouding</router-link></span>
                            </span>
                        </label>
                        <div>
                            <label class="mb-2 block font-medium text-slate-300" for="pp-extra">Extra ontvangers</label>
                            <input id="pp-extra" v-model="form.extra_recipients" :disabled="readonly" type="text" placeholder="naam@voorbeeld.be, ..." class="w-full rounded-2xl border border-slate-700 bg-slate-950 px-4 py-3 text-sm text-white placeholder:text-slate-600" />
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <PurchaseShareModal :open="shareOpen" :purchase="detail" @close="shareOpen = false" @updated="onShareUpdated" />
    </section>
</template>

<script setup>
import { computed, onMounted, reactive, ref, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import {
    BanknotesIcon,
    CameraIcon,
    DocumentArrowDownIcon,
    ExclamationTriangleIcon,
    PaperAirplaneIcon,
    PlusIcon,
    QrCodeIcon,
    TrashIcon,
    XMarkIcon,
} from '@heroicons/vue/24/outline'
import PurchaseShareModal from '../components/PurchaseShareModal.vue'
import {
    cancelPurchase,
    createPurchase,
    deletePurchase,
    deletePurchasePhoto,
    errorMessage,
    fetchPurchase,
    fetchPurchaseDefaults,
    markPurchasePaid,
    markPurchaseUnpaid,
    resendPurchasePdf,
    sharePurchase,
    updatePurchase,
    uploadPurchasePhoto,
} from '../services/purchaseApi'
import { formatCurrency, formatDateTime, statusClasses, statusLabel } from '../utils/format'

const route = useRoute()
const router = useRouter()

let keySeq = 0
const newItem = (data = {}) => ({ key: ++keySeq, description: '', quantity: 1, unit_price: null, ...data })

function today() {
    const d = new Date()
    return `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}-${String(d.getDate()).padStart(2, '0')}`
}

const form = reactive({
    items: [newItem()],
    payment_method: 'transfer',
    purchase_date: today(),
    sign_place: '',
    invite_email: '',
    mail_seller: true,
    mail_tenant: true,
    mail_accounting: true,
    extra_recipients: '',
    internal_note: '',
})

const defaults = reactive({})
const detail = ref(null)
const loading = ref(true)
const saving = ref(false)
const uploading = ref(false)
const feedback = ref(null)
const shareOpen = ref(false)

const readonly = computed(() => !!detail.value?.locked)
const cashLimit = computed(() => Number(defaults.cash_limit || 3000))
const total = computed(() => Math.round(form.items.reduce((sum, item) => sum + lineTotal(item), 0) * 100) / 100)
const cashBlocked = computed(() => total.value > cashLimit.value && form.payment_method !== 'cash')
const cashOverLimit = computed(() => total.value > cashLimit.value && form.payment_method === 'cash')

function lineTotal(item) {
    return Math.round((Number(item.quantity) || 0) * (Number(item.unit_price) || 0) * 100) / 100
}

function addItem() {
    form.items.push(newItem())
}

function removeItem(index) {
    form.items.splice(index, 1)
    if (!form.items.length) addItem()
}

function apply(data) {
    detail.value = data
    form.items = (data.items || []).map(item => newItem(item))
    if (!form.items.length) form.items = [newItem()]
    form.payment_method = data.payment_method
    form.purchase_date = data.purchase_date || today()
    form.sign_place = data.sign_place || ''
    form.invite_email = data.invite_email || ''
    form.mail_seller = data.mail_seller
    form.mail_tenant = data.mail_tenant
    form.mail_accounting = data.mail_accounting
    form.extra_recipients = (data.extra_recipients || []).join(', ')
    form.internal_note = data.internal_note || ''
}

function payload() {
    return {
        items: form.items
            .filter(item => String(item.description || '').trim() !== '')
            .map(item => ({
                description: String(item.description).trim(),
                quantity: Number(item.quantity) || 0,
                unit_price: Number(item.unit_price) || 0,
            })),
        payment_method: form.payment_method,
        purchase_date: form.purchase_date,
        sign_place: form.sign_place || null,
        invite_email: form.invite_email.trim() || null,
        mail_seller: form.mail_seller,
        mail_tenant: form.mail_tenant,
        mail_accounting: form.mail_accounting,
        extra_recipients: form.extra_recipients,
        internal_note: form.internal_note || null,
    }
}

async function save({ quiet = false } = {}) {
    saving.value = true
    if (!quiet) feedback.value = null
    try {
        const response = detail.value
            ? await updatePurchase(detail.value.id, payload())
            : await createPurchase(payload())
        const isNew = !detail.value
        apply(response.data)
        if (isNew) router.replace(`/purchases/${response.data.id}`)
        if (!quiet) feedback.value = { type: 'success', text: response.message || 'Opgeslagen.' }
        return true
    } catch (error) {
        feedback.value = { type: 'error', text: errorMessage(error, 'Opslaan mislukt.') }
        return false
    } finally {
        saving.value = false
    }
}

async function saveAndShare(sendMail) {
    if (!(await save({ quiet: true }))) return
    saving.value = true
    try {
        // Een actieve link opnieuw tonen hoeft niet opnieuw te mailen.
        const response = await sharePurchase(detail.value.id, sendMail)
        apply(response.data)
        feedback.value = { type: 'success', text: response.message }
        shareOpen.value = true
    } catch (error) {
        if (error?.response?.data?.data) apply(error.response.data.data)
        feedback.value = { type: 'error', text: errorMessage(error, 'Versturen mislukt.') }
        if (detail.value?.public_url) shareOpen.value = true
    } finally {
        saving.value = false
    }
}

async function run(action) {
    saving.value = true
    feedback.value = null
    try {
        const response = await action(detail.value.id)
        if (response.data) apply(response.data)
        feedback.value = { type: 'success', text: response.message }
    } catch (error) {
        if (error?.response?.data?.data) apply(error.response.data.data)
        feedback.value = { type: 'error', text: errorMessage(error) }
    } finally {
        saving.value = false
    }
}

function confirmRun(question, action) {
    if (window.confirm(question)) run(action)
}

async function remove() {
    if (!window.confirm(`Aankoop ${detail.value.number} verwijderen? De link werkt dan niet meer.`)) return
    saving.value = true
    try {
        await deletePurchase(detail.value.id)
        router.push('/purchases')
    } catch (error) {
        feedback.value = { type: 'error', text: errorMessage(error) }
    } finally {
        saving.value = false
    }
}

async function addPhotos(event) {
    const files = Array.from(event.target.files || [])
    event.target.value = ''
    if (!files.length) return

    if (!detail.value && !(await save({ quiet: true }))) return

    uploading.value = true
    feedback.value = null
    try {
        for (const file of files) {
            const response = await uploadPurchasePhoto(detail.value.id, file)
            detail.value = { ...detail.value, photos: response.data.photos }
        }
    } catch (error) {
        feedback.value = { type: 'error', text: errorMessage(error, 'Uploaden mislukt.') }
    } finally {
        uploading.value = false
    }
}

async function removePhoto(index) {
    try {
        const response = await deletePurchasePhoto(detail.value.id, index)
        detail.value = { ...detail.value, photos: response.data.photos }
    } catch (error) {
        feedback.value = { type: 'error', text: errorMessage(error) }
    }
}

function onShareUpdated(fresh) {
    apply(fresh)
}

async function load() {
    loading.value = true
    feedback.value = null
    try {
        Object.assign(defaults, await fetchPurchaseDefaults())
        const id = route.params.id
        if (id && id !== 'new') {
            apply(await fetchPurchase(id))
        } else {
            detail.value = null
            form.sign_place = defaults.sign_place || ''
        }
    } catch (error) {
        feedback.value = { type: 'error', text: errorMessage(error, 'Laden mislukt.') }
    } finally {
        loading.value = false
    }
}

// Zelfde component voor /purchases/new en /purchases/:id: herladen als we naar een ander borderel gaan.
watch(() => route.params.id, (id, old) => {
    if (id && id !== old && String(id) !== String(detail.value?.id)) load()
})

onMounted(load)
</script>
