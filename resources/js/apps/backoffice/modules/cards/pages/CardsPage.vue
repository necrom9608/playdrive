<template>
    <div class="flex h-full min-h-0 flex-col gap-4">
        <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-4">
            <article v-for="card in summaryCards" :key="card.label" class="rounded-3xl border p-5 shadow-xl" :class="card.class">
                <p class="text-sm font-medium" :class="card.labelClass">{{ card.label }}</p>
                <p class="mt-3 text-3xl font-semibold" :class="card.valueClass">{{ card.value }}</p>
            </article>
        </div>

        <div class="rounded-3xl border border-slate-800 bg-slate-900 p-4 shadow-xl space-y-4">
            <div class="grid gap-4 xl:grid-cols-[minmax(0,1fr)_240px_240px_auto] xl:items-end">
                <label class="space-y-2 text-sm text-slate-300">
                    <span>Zoeken</span>
                    <input v-model="store.search" class="w-full rounded-2xl border border-slate-700 bg-slate-950 px-4 py-3 text-white" placeholder="RFID, label, medewerker, lid of template" @keyup.enter="store.fetchCards()">
                </label>

                <label class="space-y-2 text-sm text-slate-300">
                    <span>Kaarttype</span>
                    <select v-model="store.cardType" class="w-full rounded-2xl border border-slate-700 bg-slate-950 px-4 py-3 text-white">
                        <option value="">Alle types</option>
                        <option v-for="type in store.cardTypes" :key="type.value" :value="type.value">{{ type.label }}</option>
                    </select>
                </label>

                <label class="space-y-2 text-sm text-slate-300">
                    <span>Template</span>
                    <select v-model="activeTemplateFilter" class="w-full rounded-2xl border border-slate-700 bg-slate-950 px-4 py-3 text-white">
                        <option value="">Alle templates</option>
                        <option v-for="template in availableFilterTemplates" :key="template.key" :value="template.key">{{ template.label }}</option>
                    </select>
                </label>

                <div class="flex flex-wrap items-center gap-3 xl:justify-end">
                    <button type="button" class="rounded-2xl border border-slate-700 bg-slate-800 px-4 py-3 text-sm font-semibold text-slate-200" @click="store.fetchCards()">Zoeken</button>
                    <button type="button" class="rounded-2xl bg-blue-600 px-4 py-3 text-sm font-semibold text-white" @click="openCreate">Nieuwe kaart</button>
                </div>
            </div>

            <div class="flex flex-wrap items-center gap-3">
                <div class="flex flex-wrap gap-2 rounded-2xl border border-slate-700 bg-slate-950 p-2">
                    <button v-for="option in statusOptions" :key="option.value" type="button" class="rounded-full px-3 py-2 text-xs font-semibold transition" :class="store.statuses.includes(option.value) ? option.activeClass : 'bg-slate-800 text-slate-300'" @click="toggleStatus(option.value)">
                        {{ option.label }}
                    </button>
                </div>
            </div>
        </div>

        <div v-if="store.error" class="rounded-3xl border border-rose-500/30 bg-rose-500/10 px-5 py-4 text-sm text-rose-200">
            {{ store.error }}
        </div>

        <div class="grid min-h-0 flex-1 grid-cols-12 gap-4">
            <div class="col-span-7 min-h-0">
                <div class="flex h-full min-h-0 flex-col rounded-3xl border border-slate-800 bg-slate-900 shadow-xl">
                    <div class="min-h-0 flex-1 overflow-auto">
                        <table class="w-full min-w-[1080px] text-sm">
                            <thead class="sticky top-0 bg-slate-900 text-slate-400">
                                <tr>
                                    <th class="px-5 py-3 text-left font-medium">RFID</th>
                                    <th class="px-5 py-3 text-left font-medium">Type</th>
                                    <th class="px-5 py-3 text-left font-medium">Eigenaar</th>
                                    <th class="px-5 py-3 text-left font-medium">Template / type</th>
                                    <th class="px-5 py-3 text-left font-medium">Status</th>
                                    <th class="px-5 py-3 text-left font-medium">Laatst aangepast</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-if="!store.cards.length">
                                    <td colspan="6" class="px-5 py-10 text-center text-slate-500">Nog geen fysieke kaarten gevonden.</td>
                                </tr>
                                <tr v-for="card in store.cards" :key="card.id" class="cursor-pointer border-t border-slate-800 transition hover:bg-slate-800/30" :class="card.id === store.selectedCardId ? 'bg-slate-800/60' : ''" @click="store.selectCard(card.id)">
                                    <td class="px-5 py-4 align-top">
                                        <div class="font-semibold text-white">{{ card.rfid_uid }}</div>
                                        <div class="mt-1 text-xs text-slate-500">{{ card.label || defaultLabel(card) }}</div>
                                    </td>
                                    <td class="px-5 py-4 align-top text-slate-200">
                                        <span class="rounded-full px-3 py-1 text-xs font-semibold" :class="typeClass(card.card_type)">{{ card.card_type_label }}</span>
                                    </td>
                                    <td class="px-5 py-4 align-top text-slate-200">
                                        {{ card.holder_name || '—' }}
                                        <div class="mt-1 text-xs text-slate-500">{{ card.internal_reference || 'Geen interne referentie' }}</div>
                                    </td>
                                    <td class="px-5 py-4 align-top text-slate-200">
                                        {{ card.card_type === 'voucher' ? (card.voucher_template_name || 'Niet gekoppeld') : (card.badge_template_name || 'Niet gekoppeld') }}
                                        <div class="mt-1 text-xs text-slate-500">{{ card.product_name || card.badge_template_name || '—' }}</div>
                                    </td>
                                    <td class="px-5 py-4 align-top">
                                        <span class="rounded-full px-3 py-1 text-xs font-semibold" :class="statusClass(card.status)">{{ card.status_label }}</span>
                                    </td>
                                    <td class="px-5 py-4 align-top text-slate-400">{{ card.updated_at_label || '—' }}</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <div class="col-span-5 min-h-0">
                <div class="flex h-full min-h-0 flex-col rounded-3xl border border-slate-800 bg-slate-900 shadow-xl">
                    <div v-if="store.selectedCard" class="min-h-0 flex-1 space-y-4 overflow-auto p-4">
                        <div class="rounded-2xl border border-slate-800 bg-slate-950 p-4">
                            <div class="mb-3 flex items-center justify-between gap-3">
                                <div>
                                    <div class="text-sm font-semibold text-white">Kaart preview</div>
                                    <div class="mt-1 text-xs text-slate-400">De PNG hieronder wordt gebruikt voor PDF en afdruk.</div>
                                </div>
                                <span class="rounded-full border border-slate-700 bg-slate-900 px-3 py-1 text-xs text-slate-300">{{ store.selectedCard.label || defaultLabel(store.selectedCard) }}</span>
                            </div>

                            <div class="flex min-h-[260px] items-center justify-center rounded-[1.4rem] border border-slate-800 bg-slate-900/80 p-4">
                                <img v-if="store.selectedCard.preview_image_url" :src="store.selectedCard.preview_image_url" alt="Kaart preview" class="h-auto w-full max-w-[360px] rounded-2xl shadow-2xl">
                                <div v-else class="space-y-3 text-center text-sm text-slate-400">
                                    <div>{{ previewBusy ? 'Preview wordt opgebouwd…' : 'Nog geen preview beschikbaar voor deze kaart.' }}</div>
                                    <button type="button" class="rounded-2xl border border-slate-700 bg-slate-800 px-4 py-2 text-sm font-semibold text-slate-200" :disabled="previewBusy" @click="generatePreviewForSelected">
                                        {{ previewBusy ? 'Bezig...' : 'Preview genereren' }}
                                    </button>
                                </div>
                            </div>
                        </div>

                        <div class="rounded-2xl border border-slate-800 bg-slate-950 p-4 space-y-3">
                            <div class="flex justify-between gap-4 text-sm"><span class="text-slate-400">Kaarttype</span><span class="text-right text-white">{{ store.selectedCard.card_type_label }}</span></div>
                            <div class="flex justify-between gap-4 text-sm"><span class="text-slate-400">Eigenaar</span><span class="text-right text-white">{{ store.selectedCard.holder_name || '—' }}</span></div>
                            <div class="flex justify-between gap-4 text-sm"><span class="text-slate-400">Voucher type</span><span class="text-right text-white">{{ store.selectedCard.voucher_template_name || '—' }}</span></div>
                            <div class="flex justify-between gap-4 text-sm"><span class="text-slate-400">Badge template</span><span class="text-right text-white">{{ store.selectedCard.badge_template_name || '—' }}</span></div>
                            <div class="flex justify-between gap-4 text-sm"><span class="text-slate-400">Waarde</span><span class="text-right text-white">{{ store.selectedCard.product_price_incl_vat ? money(store.selectedCard.product_price_incl_vat) : '—' }}</span></div>
                            <div class="flex justify-between gap-4 text-sm"><span class="text-slate-400">Interne referentie</span><span class="text-right text-white">{{ store.selectedCard.internal_reference || '—' }}</span></div>
                        </div>

                        <div class="rounded-2xl border border-slate-800 bg-slate-950 p-4 space-y-3">
                            <div class="text-sm font-semibold text-white">Gebruik</div>
                            <div class="flex justify-between gap-4 text-sm"><span class="text-slate-400">Actieve voucher</span><span class="text-right text-white">{{ store.selectedCard.current_voucher_code || 'Geen actieve voucher' }}</span></div>
                            <div class="flex justify-between gap-4 text-sm"><span class="text-slate-400">Gedrukt op</span><span class="text-right text-white">{{ store.selectedCard.printed_at_label || 'Nog niet gedrukt' }}</span></div>
                            <div class="flex justify-between gap-4 text-sm"><span class="text-slate-400">Uitgegeven op</span><span class="text-right text-white">{{ store.selectedCard.issued_at_label || '—' }}</span></div>
                            <div class="flex justify-between gap-4 text-sm"><span class="text-slate-400">Teruggebracht op</span><span class="text-right text-white">{{ store.selectedCard.returned_at_label || '—' }}</span></div>
                        </div>

                        <div class="rounded-2xl border border-slate-800 bg-slate-950 p-4">
                            <div class="text-sm font-semibold text-white">Notities</div>
                            <div class="mt-2 whitespace-pre-wrap text-sm text-slate-300">{{ store.selectedCard.notes || 'Geen notities toegevoegd.' }}</div>
                        </div>
                    </div>

                    <div v-else class="flex flex-1 items-center justify-center p-6 text-sm text-slate-400">Selecteer een kaart om details te bekijken.</div>

                    <div class="border-t border-slate-800 p-4">
                        <div class="flex flex-wrap justify-end gap-3">
                            <button type="button" class="rounded-2xl border border-slate-700 bg-slate-800 px-4 py-3 text-sm font-semibold text-slate-200 disabled:opacity-50" :disabled="!store.selectedCard || store.printing" @click="handlePrint">{{ store.printing ? 'PDF openen...' : 'Afdrukken' }}</button>
                            <button type="button" class="rounded-2xl border border-slate-700 bg-slate-800 px-4 py-3 text-sm font-semibold text-slate-200 disabled:opacity-50" :disabled="!store.selectedCard" @click="openEdit">Bewerken</button>
                            <button type="button" class="rounded-2xl border border-rose-700/70 bg-rose-950/40 px-4 py-3 text-sm font-semibold text-rose-200 disabled:opacity-50" :disabled="!store.selectedCard || store.deleting" @click="removeSelected">{{ store.deleting ? 'Verwijderen...' : 'Verwijderen' }}</button>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div v-if="showModal" class="fixed inset-0 z-50 flex items-center justify-center bg-slate-950/80 p-4" @click.self="closeModal">
            <form class="w-full max-w-5xl rounded-3xl border border-slate-800 bg-slate-900 p-6 shadow-2xl" @submit.prevent="submit">
                <div class="flex items-start justify-between gap-4">
                    <div>
                        <h2 class="text-xl font-semibold text-white">{{ form.id ? 'Fysieke kaart bewerken' : 'Nieuwe fysieke kaart' }}</h2>
                        <p class="mt-1 text-sm text-slate-400">Beheer voucher-, staff- en memberkaarten in dezelfde module. Bij opslaan wordt automatisch een vaste PNG-preview aangemaakt.</p>
                    </div>
                    <button type="button" class="rounded-2xl border border-slate-700 bg-slate-800 p-2 text-slate-200 transition hover:border-slate-500 hover:bg-slate-700" aria-label="Sluiten" @click="closeModal">
                        <XMarkIcon class="h-5 w-5" />
                    </button>
                </div>

                <div v-if="store.formErrorMessage || Object.keys(store.formErrors || {}).length" class="mt-6 rounded-2xl border border-rose-500/30 bg-rose-500/10 p-4 text-sm text-rose-100">
                    <div class="font-semibold">Opslaan mislukt</div>
                    <p class="mt-1">{{ store.formErrorMessage || 'Controleer de ingevulde gegevens.' }}</p>
                </div>

                <div class="mt-6 grid gap-4 md:grid-cols-2 xl:grid-cols-3">
                    <label class="space-y-2 text-sm text-slate-300">
                        <span>Kaarttype</span>
                        <select v-model="form.card_type" class="w-full rounded-2xl border border-slate-700 bg-slate-950 px-4 py-3 text-white" required>
                            <option v-for="type in store.cardTypes" :key="type.value" :value="type.value">{{ type.label }}</option>
                        </select>
                        <p v-if="store.formErrors.card_type?.[0]" class="text-xs text-rose-300">{{ store.formErrors.card_type[0] }}</p>
                    </label>

                    <label v-if="form.card_type === 'voucher'" class="space-y-2 text-sm text-slate-300">
                        <span>Voucher type</span>
                        <select v-model="form.voucher_template_id" class="w-full rounded-2xl border border-slate-700 bg-slate-950 px-4 py-3 text-white" required>
                            <option value="">Selecteer voucher type</option>
                            <option v-for="template in store.voucherTemplates" :key="template.id" :value="template.id">{{ template.name }}</option>
                        </select>
                        <p v-if="store.formErrors.voucher_template_id?.[0]" class="text-xs text-rose-300">{{ store.formErrors.voucher_template_id[0] }}</p>
                    </label>

                    <label v-else class="space-y-2 text-sm text-slate-300">
                        <span>Badge template</span>
                        <select v-model="form.badge_template_id" class="w-full rounded-2xl border border-slate-700 bg-slate-950 px-4 py-3 text-white" required>
                            <option value="">Selecteer badge template</option>
                            <option v-for="template in modalBadgeTemplates" :key="template.id" :value="template.id">{{ template.name }}</option>
                        </select>
                        <p v-if="store.formErrors.badge_template_id?.[0]" class="text-xs text-rose-300">{{ store.formErrors.badge_template_id[0] }}</p>
                    </label>

                    <label v-if="form.card_type === 'staff'" class="space-y-2 text-sm text-slate-300">
                        <span>Medewerker</span>
                        <select v-model="form.holder_id" class="w-full rounded-2xl border border-slate-700 bg-slate-950 px-4 py-3 text-white" required>
                            <option value="">Selecteer medewerker</option>
                            <option v-for="staff in store.staffOptions" :key="staff.id" :value="staff.id">{{ staff.name }}</option>
                        </select>
                        <p v-if="store.formErrors.holder_id?.[0]" class="text-xs text-rose-300">{{ store.formErrors.holder_id[0] }}</p>
                    </label>

                    <label v-if="form.card_type === 'member'" class="space-y-2 text-sm text-slate-300">
                        <span>Lid</span>
                        <select v-model="form.holder_id" class="w-full rounded-2xl border border-slate-700 bg-slate-950 px-4 py-3 text-white" required>
                            <option value="">Selecteer lid</option>
                            <option v-for="member in store.memberOptions" :key="member.id" :value="member.id">{{ member.name }}</option>
                        </select>
                        <p v-if="store.formErrors.holder_id?.[0]" class="text-xs text-rose-300">{{ store.formErrors.holder_id[0] }}</p>
                    </label>

                    <div class="space-y-2 text-sm text-slate-300">
                        <span>RFID UID</span>
                        <ScanRfidButton v-model="form.rfid_uid" label="Scan RFID" title="RFID-kaart scannen" description="Scan de RFID-kaart die je aan deze fysieke kaart wilt koppelen." confirm-label="RFID koppelen" :show-value="true" />
                        <p v-if="store.formErrors.rfid_uid?.[0]" class="text-xs text-rose-300">{{ store.formErrors.rfid_uid[0] }}</p>
                    </div>

                    <label class="space-y-2 text-sm text-slate-300">
                        <span>Label</span>
                        <input v-model="form.label" class="w-full rounded-2xl border border-slate-700 bg-slate-950 px-4 py-3 text-white" :placeholder="labelPlaceholder">
                    </label>

                    <label class="space-y-2 text-sm text-slate-300">
                        <span>Interne referentie</span>
                        <input v-model="form.internal_reference" class="w-full rounded-2xl border border-slate-700 bg-slate-950 px-4 py-3 text-white" placeholder="Intern kaartnummer of batchreferentie">
                    </label>

                    <label class="space-y-2 text-sm text-slate-300">
                        <span>Status</span>
                        <select v-model="form.status" class="w-full rounded-2xl border border-slate-700 bg-slate-950 px-4 py-3 text-white">
                            <option v-for="option in statusOptions" :key="option.value" :value="option.value">{{ option.label }}</option>
                        </select>
                    </label>

                    <div class="space-y-2 text-sm text-slate-300">
                        <span>Gedrukt op</span>
                        <div class="rounded-2xl border border-slate-700 bg-slate-950 px-4 py-3 text-white">{{ form.printed_at || 'Nog niet gedrukt' }}</div>
                    </div>

                    <label class="space-y-2 text-sm text-slate-300">
                        <span>Uitgegeven op</span>
                        <input v-model="form.issued_at" type="date" class="w-full rounded-2xl border border-slate-700 bg-slate-950 px-4 py-3 text-white">
                    </label>

                    <label class="space-y-2 text-sm text-slate-300">
                        <span>Teruggebracht op</span>
                        <input v-model="form.returned_at" type="date" class="w-full rounded-2xl border border-slate-700 bg-slate-950 px-4 py-3 text-white">
                    </label>
                </div>

                <label class="mt-4 block space-y-2 text-sm text-slate-300">
                    <span>Notities</span>
                    <textarea v-model="form.notes" rows="4" class="w-full rounded-2xl border border-slate-700 bg-slate-950 px-4 py-3 text-white" placeholder="Extra info over deze kaart, batch of fysieke staat"></textarea>
                </label>

                <div class="mt-6 flex items-center justify-between gap-3">
                    <button v-if="form.id" type="button" class="rounded-2xl border border-rose-700/70 bg-rose-950/40 px-4 py-3 text-sm font-semibold text-rose-200 disabled:opacity-50" :disabled="store.deleting" @click="removeFromModal">{{ store.deleting ? 'Verwijderen...' : 'Verwijderen' }}</button>
                    <div class="flex justify-end gap-3">
                    <button type="button" class="rounded-2xl border border-slate-700 bg-slate-800 px-4 py-3 text-sm font-semibold text-slate-200" @click="closeModal">Annuleren</button>
                    <button type="submit" class="rounded-2xl bg-blue-600 px-4 py-3 text-sm font-semibold text-white" :disabled="store.saving">{{ store.saving ? 'Opslaan...' : 'Opslaan' }}</button>
                    </div>
                </div>
            </form>
        </div>
    </div>
</template>

<script setup>
import { computed, onMounted, reactive, ref, watch } from 'vue'
import { XMarkIcon } from '@heroicons/vue/24/outline'
import ScanRfidButton from '@/shared/components/scanners/ScanRfidButton.vue'
import { useCardsStore } from '../stores/useCardsStore'

const store = useCardsStore()
const showModal = ref(false)
const previewBusy = ref(false)
const activeTemplateFilter = ref('')
const form = reactive(emptyForm())

const statusOptions = [
    { value: 'stock', label: 'Op stock', activeClass: 'bg-slate-500/15 text-slate-200' },
    { value: 'in_circulation', label: 'In omloop', activeClass: 'bg-sky-500/15 text-sky-300' },
    { value: 'returned', label: 'Teruggebracht', activeClass: 'bg-amber-500/15 text-amber-300' },
    { value: 'blocked', label: 'Geblokkeerd', activeClass: 'bg-rose-500/15 text-rose-300' },
    { value: 'retired', label: 'Buiten gebruik', activeClass: 'bg-purple-500/15 text-purple-300' },
]

const summaryCards = computed(() => [
    { label: 'Totaal', value: store.summary.total ?? 0, class: 'border-slate-800 bg-slate-900', labelClass: 'text-slate-400', valueClass: 'text-white' },
    { label: 'Voucher', value: store.summary.voucher ?? 0, class: 'border-amber-500/20 bg-amber-500/10', labelClass: 'text-amber-300', valueClass: 'text-amber-200' },
    { label: 'Staff', value: store.summary.staff ?? 0, class: 'border-sky-500/20 bg-sky-500/10', labelClass: 'text-sky-300', valueClass: 'text-sky-200' },
    { label: 'Members', value: store.summary.member ?? 0, class: 'border-violet-500/20 bg-violet-500/10', labelClass: 'text-violet-300', valueClass: 'text-violet-200' },
])

const availableFilterTemplates = computed(() => {
    const voucherTemplates = store.cardType === 'staff' || store.cardType === 'member'
        ? []
        : store.voucherTemplates.map(template => ({ key: `voucher:${template.id}`, label: template.name }))

    const badgeTemplates = store.cardType === 'voucher'
        ? []
        : store.badgeTemplates
            .filter(template => !store.cardType || template.template_type === store.cardType)
            .map(template => ({ key: `badge:${template.id}`, label: `${template.template_type_label} · ${template.name}` }))

    return [...voucherTemplates, ...badgeTemplates]
})

const modalBadgeTemplates = computed(() => store.badgeTemplates.filter(template => template.template_type === form.card_type))

const labelPlaceholder = computed(() => {
    if (form.id) {
        return defaultLabel(form)
    }

    if (form.card_type === 'staff') return 'Wordt automatisch STAFF #ID'
    if (form.card_type === 'member') return 'Wordt automatisch MEMBER #ID'
    return 'Wordt automatisch CARD #ID'
})

onMounted(() => store.fetchCards())

watch(() => activeTemplateFilter.value, (value) => {
    if (!value) {
        store.voucherTemplateId = ''
        store.badgeTemplateId = ''
        return
    }

    const [kind, id] = value.split(':')
    store.voucherTemplateId = kind === 'voucher' ? id : ''
    store.badgeTemplateId = kind === 'badge' ? id : ''
})

watch(() => store.cardType, () => {
    activeTemplateFilter.value = ''
    store.voucherTemplateId = ''
    store.badgeTemplateId = ''
})

watch(() => form.card_type, (newType) => {
    form.voucher_template_id = newType === 'voucher' ? form.voucher_template_id : ''
    form.badge_template_id = newType === 'staff' || newType === 'member' ? form.badge_template_id : ''
    form.holder_id = newType === 'staff' || newType === 'member' ? form.holder_id : ''
})

watch(() => form.holder_id, (holderId) => {
    if (!holderId || form.rfid_uid) return

    if (form.card_type === 'staff') {
        const selected = store.staffOptions.find(option => String(option.id) === String(holderId))
        if (selected?.rfid_uid) {
            form.rfid_uid = selected.rfid_uid
        }
        return
    }

    if (form.card_type === 'member') {
        const selected = store.memberOptions.find(option => String(option.id) === String(holderId))
        if (selected?.rfid_uid) {
            form.rfid_uid = selected.rfid_uid
        }
    }
})

watch(() => store.selectedCard?.id, async () => {
    if (store.selectedCard && !store.selectedCard.preview_image_url && !previewBusy.value) {
        await generatePreviewForSelected()
    }
})

function todayIso() {
    return new Date().toISOString().slice(0, 10)
}

function emptyForm() {
    return {
        id: null,
        card_type: 'voucher',
        voucher_template_id: '',
        badge_template_id: '',
        holder_id: '',
        label: '',
        internal_reference: '',
        rfid_uid: '',
        status: 'stock',
        notes: '',
        printed_at: '',
        issued_at: todayIso(),
        returned_at: '',
    }
}

function openCreate() {
    store.clearFormErrors()
    Object.assign(form, emptyForm())
    showModal.value = true
}

function openEdit() {
    if (!store.selectedCard) return
    store.clearFormErrors()
    Object.assign(form, { ...emptyForm(), ...store.selectedCard })
    showModal.value = true
}

function closeModal() {
    store.clearFormErrors()
    showModal.value = false
}

async function submit() {
    try {
        const payload = { ...form }
        payload.voucher_template_id = payload.voucher_template_id || null
        payload.badge_template_id = payload.badge_template_id || null
        payload.holder_id = payload.holder_id || null
        await store.saveCard(payload)
        closeModal()
    } catch (error) {
        // Validatiefouten blijven in de modal zichtbaar.
    }
}

async function removeSelected() {
    if (!store.selectedCard) {
        return
    }

    const label = store.selectedCard.label || `kaart #${store.selectedCard.id}`
    if (!window.confirm(`Weet je zeker dat je ${label} wilt verwijderen?`)) {
        return
    }

    try {
        await store.deleteCard(store.selectedCard.id)
    } catch (error) {
        // Algemene foutmelding staat in de module.
    }
}

async function removeFromModal() {
    if (!form.id) {
        return
    }

    const label = form.label || `kaart #${form.id}`
    if (!window.confirm(`Weet je zeker dat je ${label} wilt verwijderen?`)) {
        return
    }

    try {
        await store.deleteCard(form.id)
        closeModal()
    } catch (error) {
        // Algemene foutmelding staat in de module.
    }
}

async function generatePreviewForSelected() {
    if (!store.selectedCard || previewBusy.value) return
    previewBusy.value = true
    try {
        await store.ensureRenderImage(store.selectedCard)
        await store.fetchCards()
    } finally {
        previewBusy.value = false
    }
}

async function handlePrint() {
    if (!store.selectedCard) return
    await store.printCard(store.selectedCard.id)
}

function toggleStatus(value) {
    store.statuses = store.statuses.includes(value)
        ? store.statuses.filter(item => item !== value)
        : [...store.statuses, value]
}

function statusClass(status) {
    return {
        stock: 'bg-slate-500/15 text-slate-200',
        in_circulation: 'bg-sky-500/15 text-sky-300',
        returned: 'bg-amber-500/15 text-amber-300',
        blocked: 'bg-rose-500/15 text-rose-300',
        retired: 'bg-purple-500/15 text-purple-300',
    }[status] ?? 'bg-slate-500/15 text-slate-300'
}

function typeClass(type) {
    return {
        voucher: 'bg-amber-500/15 text-amber-300',
        staff: 'bg-sky-500/15 text-sky-300',
        member: 'bg-violet-500/15 text-violet-300',
    }[type] ?? 'bg-slate-500/15 text-slate-300'
}

function money(value) {
    return new Intl.NumberFormat('nl-BE', { minimumFractionDigits: 2, maximumFractionDigits: 2 }).format(Number(value ?? 0))
}

function defaultLabel(card) {
    if (card.card_type === 'staff') return `STAFF #${card.id}`
    if (card.card_type === 'member') return `MEMBER #${card.id}`
    return `CARD #${card.id}`
}
</script>
