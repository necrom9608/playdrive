<template>
    <ModalDialog
        :open="open"
        :title="signed ? 'Ondertekend' : 'Laat de verkoper scannen'"
        :description="purchase ? `${purchase.number} · ${formatCurrency(purchase.total_amount)}` : ''"
        @close="$emit('close')"
    >
        <div v-if="purchase" class="flex flex-col gap-5">
            <template v-if="signed">
                <div class="flex flex-col items-center gap-3 py-4 text-center">
                    <div class="flex h-16 w-16 items-center justify-center rounded-full bg-emerald-500/15 text-emerald-300">
                        <CheckIcon class="h-8 w-8" />
                    </div>
                    <p class="text-lg font-semibold text-white">{{ purchase.seller_name }} heeft getekend</p>
                    <p class="text-sm text-slate-400">
                        {{ formatDateTime(purchase.signed_at) }}
                        <template v-if="purchase.mailed_to?.length"> · PDF gemaild naar {{ purchase.mailed_to.join(', ') }}</template>
                    </p>
                    <p v-if="purchase.mail_error" class="rounded-2xl border border-rose-500/30 bg-rose-500/10 px-4 py-3 text-sm text-rose-200">
                        Mailen mislukt: {{ purchase.mail_error }}
                    </p>
                </div>
                <div class="flex flex-wrap justify-center gap-3">
                    <a :href="purchase.pdf_url" target="_blank" rel="noopener" class="inline-flex items-center gap-2 rounded-xl bg-blue-600 px-4 py-3 text-sm font-semibold text-white transition hover:bg-blue-500">
                        <DocumentArrowDownIcon class="h-5 w-5" /> PDF openen
                    </a>
                    <button type="button" class="inline-flex items-center gap-2 rounded-xl border border-slate-700 px-4 py-3 text-sm font-medium text-slate-200 transition hover:bg-slate-800" @click="$emit('close')">
                        Sluiten
                    </button>
                </div>
            </template>

            <template v-else-if="purchase.public_url">
                <div class="self-center rounded-3xl bg-white p-4">
                    <img v-if="qr" :src="qr" alt="QR-code naar het formulier" class="h-60 w-60" />
                    <div v-else class="flex h-60 w-60 items-center justify-center text-sm text-slate-500">QR maken...</div>
                </div>

                <div class="flex gap-2">
                    <div class="min-w-0 flex-1 truncate rounded-xl border border-slate-700 bg-slate-950 px-4 py-3 font-mono text-sm text-sky-300">{{ purchase.public_url }}</div>
                    <button type="button" class="inline-flex items-center gap-2 rounded-xl border border-slate-700 px-4 py-3 text-sm font-medium text-slate-200 transition hover:bg-slate-800" @click="copy">
                        <ClipboardDocumentIcon class="h-5 w-5" /> {{ copied ? 'Gekopieerd' : 'Kopieer' }}
                    </button>
                </div>

                <div v-if="purchase.sent_via === 'email' && purchase.invite_email" class="flex items-center gap-3 rounded-2xl border border-emerald-500/30 bg-emerald-500/10 px-4 py-3 text-sm text-emerald-200">
                    <EnvelopeIcon class="h-5 w-5 shrink-0" />
                    Link gemaild naar {{ purchase.invite_email }}<template v-if="purchase.invited_at"> · {{ formatDateTime(purchase.invited_at) }}</template>
                </div>
                <button
                    v-else-if="purchase.invite_email"
                    type="button"
                    class="inline-flex items-center justify-center gap-2 rounded-xl border border-slate-700 px-4 py-3 text-sm font-medium text-slate-200 transition hover:bg-slate-800 disabled:opacity-60"
                    :disabled="mailing"
                    @click="mailLink"
                >
                    <EnvelopeIcon class="h-5 w-5" /> {{ mailing ? 'Versturen...' : `Link ook mailen naar ${purchase.invite_email}` }}
                </button>

                <ol class="flex flex-col gap-3 text-sm">
                    <li class="flex items-center gap-3 text-slate-200">
                        <span class="flex h-6 w-6 items-center justify-center rounded-full bg-blue-600 text-white"><CheckIcon class="h-3.5 w-3.5" /></span>
                        Link actief tot {{ formatDateTime(purchase.token_expires_at) }}
                    </li>
                    <li class="flex items-center gap-3" :class="purchase.opened_at ? 'text-slate-200' : 'font-semibold text-white'">
                        <span v-if="purchase.opened_at" class="flex h-6 w-6 items-center justify-center rounded-full bg-blue-600 text-white"><CheckIcon class="h-3.5 w-3.5" /></span>
                        <span v-else class="h-6 w-6 animate-pulse rounded-full border-2 border-blue-500" />
                        {{ purchase.opened_at ? `Formulier geopend · ${formatDateTime(purchase.opened_at)}` : 'Wacht tot de verkoper het formulier opent...' }}
                    </li>
                    <li class="flex items-center gap-3" :class="purchase.opened_at ? 'font-semibold text-white' : 'text-slate-500'">
                        <span class="h-6 w-6 rounded-full border-2" :class="purchase.opened_at ? 'animate-pulse border-blue-500' : 'border-slate-700'" />
                        Wacht op gegevens en handtekening
                    </li>
                    <li class="flex items-center gap-3 text-slate-500">
                        <span class="h-6 w-6 rounded-full border-2 border-slate-700" />
                        PDF naar {{ recipientsLabel }}
                    </li>
                </ol>

                <p class="text-xs text-slate-500">Dit venster werkt live bij. Je mag het sluiten: de aankoop blijft op Verstuurd tot de verkoper tekent.</p>
            </template>

            <p v-else class="text-sm text-slate-400">Er is geen actieve link. Verstuur het borderel opnieuw.</p>

            <p v-if="error" class="rounded-2xl border border-rose-500/30 bg-rose-500/10 px-4 py-3 text-sm text-rose-200">{{ error }}</p>
        </div>
    </ModalDialog>
</template>

<script setup>
import { computed, onBeforeUnmount, ref, watch } from 'vue'
import QRCode from 'qrcode'
import { CheckIcon, ClipboardDocumentIcon, DocumentArrowDownIcon, EnvelopeIcon } from '@heroicons/vue/24/outline'
import ModalDialog from '../../../components/ModalDialog.vue'
import { errorMessage, fetchPurchase, sharePurchase } from '../services/purchaseApi'
import { formatCurrency, formatDateTime } from '../utils/format'

const props = defineProps({
    open: { type: Boolean, default: false },
    purchase: { type: Object, default: null },
})

const emit = defineEmits(['close', 'updated'])

const qr = ref('')
const copied = ref(false)
const mailing = ref(false)
const error = ref('')
let timer = null

const signed = computed(() => ['signed', 'paid'].includes(props.purchase?.status))

const recipientsLabel = computed(() => {
    const p = props.purchase
    if (!p) return ''
    const parts = []
    if (p.mail_seller) parts.push('verkoper')
    if (p.mail_tenant) parts.push('zaak')
    if (p.mail_accounting) parts.push('boekhouding')
    if (p.extra_recipients?.length) parts.push('extra ontvangers')
    return parts.length ? parts.join(', ') : 'niemand (enkel bewaard)'
})

watch(() => [props.open, props.purchase?.public_url], async ([open, url]) => {
    qr.value = ''
    error.value = ''
    if (!open || !url) return
    qr.value = await QRCode.toDataURL(url, { width: 720, margin: 1, errorCorrectionLevel: 'M' })
}, { immediate: true })

watch(() => [props.open, props.purchase?.status], ([open, status]) => {
    stopPolling()
    if (open && status === 'sent') {
        timer = window.setInterval(poll, 3000)
    }
}, { immediate: true })

async function poll() {
    if (!props.purchase?.id) return
    try {
        const fresh = await fetchPurchase(props.purchase.id)
        if (fresh.status !== props.purchase.status || fresh.opened_at !== props.purchase.opened_at) {
            emit('updated', fresh)
        }
    } catch {
        // Volgende poging.
    }
}

function stopPolling() {
    if (timer) {
        window.clearInterval(timer)
        timer = null
    }
}

async function copy() {
    try {
        await navigator.clipboard.writeText(props.purchase.public_url)
        copied.value = true
        window.setTimeout(() => { copied.value = false }, 2000)
    } catch {
        error.value = 'Kopiëren lukte niet. Selecteer de link manueel.'
    }
}

async function mailLink() {
    mailing.value = true
    error.value = ''
    try {
        const response = await sharePurchase(props.purchase.id, true)
        emit('updated', response.data)
    } catch (e) {
        error.value = errorMessage(e, 'Mailen mislukt.')
    } finally {
        mailing.value = false
    }
}

onBeforeUnmount(stopPolling)
</script>
