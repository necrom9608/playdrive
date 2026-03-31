<template>
    <div class="flex h-full min-h-0 flex-col gap-6">
        <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-4">
            <article v-for="card in cards" :key="card.label" class="rounded-3xl border p-5 shadow-xl" :class="card.class"><p class="text-sm font-medium" :class="card.labelClass">{{ card.label }}</p><p class="mt-3 text-3xl font-semibold" :class="card.valueClass">{{ card.value }}</p></article>
        </div>

        <div class="rounded-3xl border border-slate-800 bg-slate-900 p-4 shadow-xl">
            <div class="flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
                <div class="grid flex-1 gap-4 md:grid-cols-2">
                    <label class="space-y-2 text-sm text-slate-300"><span>Zoeken</span><input v-model="store.search" class="w-full rounded-2xl border border-slate-700 bg-slate-950 px-4 py-3 text-white" placeholder="Code, QR, NFC of klant" @keyup.enter="store.fetchVouchers()"></label>
                    <div class="space-y-2 text-sm text-slate-300"><span>Status</span><div class="flex flex-wrap gap-2 rounded-2xl border border-slate-700 bg-slate-950 p-2"><button v-for="option in statusOptions" :key="option.value" type="button" class="rounded-full px-3 py-2 text-xs font-semibold transition" :class="store.statuses.includes(option.value) ? option.activeClass : 'bg-slate-800 text-slate-300'" @click="toggleStatus(option.value)">{{ option.label }}</button></div></div>
                </div>
                <div class="flex gap-3"><button type="button" class="rounded-2xl border border-slate-700 bg-slate-800 px-4 py-3 text-sm font-semibold text-slate-200" @click="store.fetchVouchers()">Zoeken</button><button type="button" class="rounded-2xl bg-blue-600 px-4 py-3 text-sm font-semibold text-white" @click="openCreate">Nieuwe cadeaubon</button></div>
            </div>
        </div>

        <div v-if="store.error" class="rounded-3xl border border-rose-500/30 bg-rose-500/10 px-5 py-4 text-sm text-rose-200">{{ store.error }}</div>

        <div class="flex h-full min-h-0 flex-col rounded-3xl border border-slate-800 bg-slate-900 shadow-xl">
            <div class="min-h-0 flex-1 overflow-auto">
                <table class="w-full min-w-[1100px] text-sm">
                    <thead class="sticky top-0 bg-slate-900 text-slate-400"><tr><th class="px-5 py-3 text-left font-medium">Code</th><th class="px-5 py-3 text-left font-medium">Klant</th><th class="px-5 py-3 text-left font-medium">Kanaal</th><th class="px-5 py-3 text-left font-medium">Bedrag</th><th class="px-5 py-3 text-left font-medium">Vervalt</th><th class="px-5 py-3 text-left font-medium">Status</th></tr></thead>
                    <tbody>
                        <tr v-if="!store.vouchers.length"><td colspan="6" class="px-5 py-10 text-center text-slate-500">Geen cadeaubonnen gevonden.</td></tr>
                        <tr v-for="voucher in store.vouchers" :key="voucher.id" class="cursor-pointer border-t border-slate-800 transition hover:bg-slate-800/30" :class="voucher.id === store.selectedVoucherId ? 'bg-slate-800/60' : ''" @click="store.selectedVoucherId = voucher.id">
                            <td class="px-5 py-4 align-top"><div class="font-semibold text-white">{{ voucher.code }}</div><div class="mt-1 text-xs text-slate-500">NFC: {{ voucher.nfc_uid || '—' }} · QR: {{ voucher.qr_token || '—' }}</div></td>
                            <td class="px-5 py-4 align-top text-slate-200">{{ voucher.customer_name || 'Onbekend' }}<div class="mt-1 text-xs text-slate-500">{{ voucher.customer_email || 'Geen e-mail' }}</div></td>
                            <td class="px-5 py-4 align-top text-slate-200">{{ voucher.source_channel_label }}</td>
                            <td class="px-5 py-4 align-top text-slate-200">€ {{ money(voucher.amount_remaining) }}<div class="mt-1 text-xs text-slate-500">Start: € {{ money(voucher.amount_initial) }}</div></td>
                            <td class="px-5 py-4 align-top text-slate-200">{{ voucher.expires_at_label || 'Geen einddatum' }}</td>
                            <td class="px-5 py-4 align-top"><span class="inline-flex rounded-full px-3 py-1 text-xs font-semibold" :class="statusClass(voucher.status)">{{ voucher.status_label }}</span></td>
                        </tr>
                    </tbody>
                </table>
            </div>
            <div class="border-t border-slate-800 px-5 py-4"><div class="flex flex-wrap items-center justify-between gap-3"><div class="text-sm text-slate-400"><span v-if="store.selectedVoucher">Geselecteerd: <span class="font-semibold text-white">{{ store.selectedVoucher.code }}</span></span><span v-else>Geen cadeaubon geselecteerd.</span></div><div class="flex gap-3"><button type="button" class="rounded-2xl border border-slate-700 bg-slate-800 px-4 py-3 text-sm font-semibold text-slate-200 disabled:opacity-50" :disabled="!store.selectedVoucher" @click="openEdit">Bewerken</button></div></div></div>
        </div>

        <div v-if="showModal" class="fixed inset-0 z-50 flex items-center justify-center bg-slate-950/80 p-4" @click.self="closeModal">
            <form class="w-full max-w-3xl rounded-3xl border border-slate-800 bg-slate-900 p-6 shadow-2xl" @submit.prevent="submit">
                <div class="flex items-start justify-between gap-4"><div><h2 class="text-xl font-semibold text-white">{{ form.id ? 'Cadeaubon bewerken' : 'Nieuwe cadeaubon' }}</h2><p class="mt-1 text-sm text-slate-400">Ondersteunt NFC-kaartjes en QR-codes van de website.</p></div><button type="button" class="rounded-xl border border-slate-700 bg-slate-800 px-3 py-2 text-sm text-slate-200" @click="closeModal">Sluiten</button></div>
                <div class="mt-6 grid gap-4 md:grid-cols-2">
                    <label class="space-y-2 text-sm text-slate-300"><span>Code</span><input v-model="form.code" class="w-full rounded-2xl border border-slate-700 bg-slate-950 px-4 py-3 text-white" placeholder="Bijv. BON-ABC123"></label>
                    <label class="space-y-2 text-sm text-slate-300"><span>Kanaal</span><select v-model="form.source_channel" class="w-full rounded-2xl border border-slate-700 bg-slate-950 px-4 py-3 text-white"><option value="frontdesk">Frontdesk / NFC</option><option value="website">Website / QR</option></select></label>
                    <label class="space-y-2 text-sm text-slate-300"><span>NFC code</span><input v-model="form.nfc_uid" class="w-full rounded-2xl border border-slate-700 bg-slate-950 px-4 py-3 text-white" placeholder="Scan of plak UID"></label>
                    <label class="space-y-2 text-sm text-slate-300"><span>QR code token</span><input v-model="form.qr_token" class="w-full rounded-2xl border border-slate-700 bg-slate-950 px-4 py-3 text-white" placeholder="Token van de website"></label>
                    <label class="space-y-2 text-sm text-slate-300"><span>Klant naam</span><input v-model="form.customer_name" class="w-full rounded-2xl border border-slate-700 bg-slate-950 px-4 py-3 text-white"></label>
                    <label class="space-y-2 text-sm text-slate-300"><span>Klant e-mail</span><input v-model="form.customer_email" class="w-full rounded-2xl border border-slate-700 bg-slate-950 px-4 py-3 text-white"></label>
                    <label class="space-y-2 text-sm text-slate-300"><span>Bedrag</span><input v-model.number="form.amount_initial" type="number" min="0.01" step="0.01" class="w-full rounded-2xl border border-slate-700 bg-slate-950 px-4 py-3 text-white"></label>
                    <label class="space-y-2 text-sm text-slate-300"><span>Resterend saldo</span><input v-model.number="form.amount_remaining" type="number" min="0" step="0.01" class="w-full rounded-2xl border border-slate-700 bg-slate-950 px-4 py-3 text-white"></label>
                    <label class="space-y-2 text-sm text-slate-300"><span>Status</span><select v-model="form.status" class="w-full rounded-2xl border border-slate-700 bg-slate-950 px-4 py-3 text-white"><option value="active">Actief</option><option value="validated">Gevalideerd</option><option value="redeemed">Ingewisseld</option><option value="cancelled">Geannuleerd</option><option value="expired">Vervallen</option></select></label>
                    <label class="space-y-2 text-sm text-slate-300"><span>Vervaldatum</span><input v-model="form.expires_at" type="date" class="w-full rounded-2xl border border-slate-700 bg-slate-950 px-4 py-3 text-white"></label>
                </div>
                <div class="mt-6 flex justify-end gap-3"><button type="button" class="rounded-2xl border border-slate-700 bg-slate-800 px-4 py-3 text-sm font-semibold text-slate-200" @click="closeModal">Annuleren</button><button type="submit" class="rounded-2xl bg-blue-600 px-4 py-3 text-sm font-semibold text-white">{{ store.saving ? 'Opslaan...' : 'Opslaan' }}</button></div>
            </form>
        </div>
    </div>
</template>

<script setup>
import { computed, onMounted, reactive, ref } from 'vue'
import { useVouchersStore } from '../stores/useVouchersStore'

const store = useVouchersStore()
const showModal = ref(false)
const form = reactive(emptyForm())

const statusOptions = [
    { value: 'active', label: 'Actief', activeClass: 'bg-emerald-500/15 text-emerald-300' },
    { value: 'validated', label: 'Gevalideerd', activeClass: 'bg-sky-500/15 text-sky-300' },
    { value: 'redeemed', label: 'Ingewisseld', activeClass: 'bg-purple-500/15 text-purple-300' },
    { value: 'cancelled', label: 'Geannuleerd', activeClass: 'bg-slate-500/15 text-slate-300' },
    { value: 'expired', label: 'Vervallen', activeClass: 'bg-rose-500/15 text-rose-300' },
]

const cards = computed(() => [
    { label: 'Totaal', value: store.summary.total ?? 0, class: 'border-slate-800 bg-slate-900', labelClass: 'text-slate-400', valueClass: 'text-white' },
    { label: 'Actief', value: store.summary.active ?? 0, class: 'border-emerald-500/20 bg-emerald-500/10', labelClass: 'text-emerald-300', valueClass: 'text-emerald-200' },
    { label: 'Gevalideerd', value: store.summary.validated ?? 0, class: 'border-sky-500/20 bg-sky-500/10', labelClass: 'text-sky-300', valueClass: 'text-sky-200' },
    { label: 'Ingewisseld', value: store.summary.redeemed ?? 0, class: 'border-purple-500/20 bg-purple-500/10', labelClass: 'text-purple-300', valueClass: 'text-purple-200' },
])

onMounted(() => store.fetchVouchers())

function emptyForm() { return { id: null, code: '', qr_token: '', nfc_uid: '', customer_name: '', customer_email: '', source_channel: 'frontdesk', amount_initial: 25, amount_remaining: 25, status: 'active', expires_at: '' } }
function openCreate() { Object.assign(form, emptyForm()); showModal.value = true }
function openEdit() { if (!store.selectedVoucher) return; Object.assign(form, { ...emptyForm(), ...store.selectedVoucher }); showModal.value = true }
function closeModal() { showModal.value = false }
async function submit() { await store.saveVoucher({ ...form }); closeModal() }
function toggleStatus(value) { store.statuses = store.statuses.includes(value) ? store.statuses.filter(item => item !== value) : [...store.statuses, value] }
function money(value) { return new Intl.NumberFormat('nl-BE', { minimumFractionDigits: 2, maximumFractionDigits: 2 }).format(Number(value ?? 0)) }
function statusClass(status) { return { active: 'bg-emerald-500/15 text-emerald-300', validated: 'bg-sky-500/15 text-sky-300', redeemed: 'bg-purple-500/15 text-purple-300', cancelled: 'bg-slate-500/15 text-slate-300', expired: 'bg-rose-500/15 text-rose-300' }[status] ?? 'bg-slate-500/15 text-slate-300' }
</script>
