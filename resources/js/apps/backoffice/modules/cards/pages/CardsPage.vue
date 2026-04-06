<template>
    <div class="flex h-full min-h-0 flex-col gap-4">
        <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-4">
            <article v-for="card in summaryCards" :key="card.label" class="rounded-3xl border p-5 shadow-xl" :class="card.class">
                <p class="text-sm font-medium" :class="card.labelClass">{{ card.label }}</p>
                <p class="mt-3 text-3xl font-semibold" :class="card.valueClass">{{ card.value }}</p>
            </article>
        </div>

        <div class="rounded-3xl border border-slate-800 bg-slate-900 p-4 shadow-xl">
            <div class="grid gap-4 xl:grid-cols-[minmax(0,1fr)_auto] xl:items-end">
                <div class="grid gap-4 lg:grid-cols-[minmax(0,1fr)_240px]">
                    <label class="space-y-2 text-sm text-slate-300">
                        <span>Zoeken</span>
                        <input
                            v-model="store.search"
                            class="w-full rounded-2xl border border-slate-700 bg-slate-950 px-4 py-3 text-white"
                            placeholder="RFID, intern nummer of voucher type"
                            @keyup.enter="store.fetchCards()"
                        >
                    </label>

                    <label class="space-y-2 text-sm text-slate-300">
                        <span>Voucher type</span>
                        <select v-model="store.voucherTemplateId" class="w-full rounded-2xl border border-slate-700 bg-slate-950 px-4 py-3 text-white">
                            <option value="">Alle voucher types</option>
                            <option v-for="template in store.voucherTemplates" :key="template.id" :value="String(template.id)">{{ template.name }}</option>
                        </select>
                    </label>
                </div>

                <div class="flex flex-wrap items-center gap-3 xl:justify-end">
                    <div class="flex flex-wrap gap-2 rounded-2xl border border-slate-700 bg-slate-950 p-2">
                        <button
                            v-for="option in statusOptions"
                            :key="option.value"
                            type="button"
                            class="rounded-full px-3 py-2 text-xs font-semibold transition"
                            :class="store.statuses.includes(option.value) ? option.activeClass : 'bg-slate-800 text-slate-300'"
                            @click="toggleStatus(option.value)"
                        >
                            {{ option.label }}
                        </button>
                    </div>

                    <button type="button" class="rounded-2xl border border-slate-700 bg-slate-800 px-4 py-3 text-sm font-semibold text-slate-200" @click="store.fetchCards()">Zoeken</button>
                    <button type="button" class="rounded-2xl bg-blue-600 px-4 py-3 text-sm font-semibold text-white" @click="openCreate">Nieuwe kaart</button>
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
                        <table class="w-full min-w-[980px] text-sm">
                            <thead class="sticky top-0 bg-slate-900 text-slate-400">
                                <tr>
                                    <th class="px-5 py-3 text-left font-medium">RFID</th>
                                    <th class="px-5 py-3 text-left font-medium">Voucher type</th>
                                    <th class="px-5 py-3 text-left font-medium">Referentie</th>
                                    <th class="px-5 py-3 text-left font-medium">Actieve voucher</th>
                                    <th class="px-5 py-3 text-left font-medium">Status</th>
                                    <th class="px-5 py-3 text-left font-medium">Laatst aangepast</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr v-if="!store.cards.length">
                                    <td colspan="6" class="px-5 py-10 text-center text-slate-500">Nog geen fysieke kaarten gevonden.</td>
                                </tr>
                                <tr
                                    v-for="card in store.cards"
                                    :key="card.id"
                                    class="cursor-pointer border-t border-slate-800 transition hover:bg-slate-800/30"
                                    :class="card.id === store.selectedCardId ? 'bg-slate-800/60' : ''"
                                    @click="store.selectCard(card.id)"
                                >
                                    <td class="px-5 py-4 align-top">
                                        <div class="font-semibold text-white">{{ card.rfid_uid }}</div>
                                        <div class="mt-1 text-xs text-slate-500">{{ card.label || 'Geen label' }}</div>
                                    </td>
                                    <td class="px-5 py-4 align-top text-slate-200">
                                        {{ card.voucher_template_name || 'Niet gekoppeld' }}
                                        <div class="mt-1 text-xs text-slate-500">{{ card.product_name || 'Geen product' }}</div>
                                    </td>
                                    <td class="px-5 py-4 align-top text-slate-200">
                                        {{ card.internal_reference || '—' }}
                                        <div class="mt-1 text-xs text-slate-500">{{ card.badge_template_name || 'Geen template' }}</div>
                                    </td>
                                    <td class="px-5 py-4 align-top text-slate-200">{{ card.current_voucher_code || 'Geen actieve voucher' }}</td>
                                    <td class="px-5 py-4 align-top">
                                        <span class="inline-flex rounded-full px-3 py-1 text-xs font-semibold" :class="statusClass(card.status)">{{ card.status_label }}</span>
                                    </td>
                                    <td class="px-5 py-4 align-top text-slate-200">{{ card.updated_at_label || '—' }}</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <div class="col-span-5 min-h-0">
                <div class="flex h-full min-h-0 flex-col rounded-3xl border border-slate-800 bg-slate-900 shadow-xl">
                    <div class="border-b border-slate-800 p-4">
                        <h2 class="text-base font-semibold text-white">Kaartdetails</h2>
                    </div>

                    <div v-if="store.selectedCard" class="min-h-0 flex-1 space-y-4 overflow-auto p-4">
                        <div class="rounded-2xl border border-slate-800 bg-slate-950 p-4">
                            <div class="text-sm text-slate-400">RFID UID</div>
                            <div class="mt-1 text-lg font-bold text-white">{{ store.selectedCard.rfid_uid }}</div>
                            <div class="mt-2 flex flex-wrap items-center gap-2">
                                <span class="inline-flex rounded-full px-3 py-1 text-xs font-semibold" :class="statusClass(store.selectedCard.status)">{{ store.selectedCard.status_label }}</span>
                                <span v-if="store.selectedCard.printed_at_label" class="inline-flex rounded-full bg-slate-800 px-3 py-1 text-xs font-semibold text-slate-200">Gedrukt op {{ store.selectedCard.printed_at_label }}</span>
                            </div>
                        </div>

                        <div class="rounded-2xl border border-slate-800 bg-slate-950 p-4 space-y-3">
                            <div class="flex justify-between gap-4 text-sm">
                                <span class="text-slate-400">Voucher type</span>
                                <span class="text-right text-white">{{ store.selectedCard.voucher_template_name || 'Niet gekoppeld' }}</span>
                            </div>
                            <div class="flex justify-between gap-4 text-sm">
                                <span class="text-slate-400">Product</span>
                                <span class="text-right text-white">{{ store.selectedCard.product_name || '—' }}</span>
                            </div>
                            <div class="flex justify-between gap-4 text-sm">
                                <span class="text-slate-400">Waarde</span>
                                <span class="text-right text-white">{{ store.selectedCard.product_price_incl_vat ? money(store.selectedCard.product_price_incl_vat) : '—' }}</span>
                            </div>
                            <div class="flex justify-between gap-4 text-sm">
                                <span class="text-slate-400">Template</span>
                                <span class="text-right text-white">{{ store.selectedCard.badge_template_name || '—' }}</span>
                            </div>
                            <div class="flex justify-between gap-4 text-sm">
                                <span class="text-slate-400">Label</span>
                                <span class="text-right text-white">{{ store.selectedCard.label || '—' }}</span>
                            </div>
                            <div class="flex justify-between gap-4 text-sm">
                                <span class="text-slate-400">Interne referentie</span>
                                <span class="text-right text-white">{{ store.selectedCard.internal_reference || '—' }}</span>
                            </div>
                        </div>

                        <div class="rounded-2xl border border-slate-800 bg-slate-950 p-4 space-y-3">
                            <div class="text-sm font-semibold text-white">Gebruik</div>
                            <div class="flex justify-between gap-4 text-sm">
                                <span class="text-slate-400">Actieve voucher</span>
                                <span class="text-right text-white">{{ store.selectedCard.current_voucher_code || 'Geen actieve voucher' }}</span>
                            </div>
                            <div class="flex justify-between gap-4 text-sm">
                                <span class="text-slate-400">Laatste voucher</span>
                                <span class="text-right text-white">{{ store.selectedCard.last_voucher_code || '—' }}</span>
                            </div>
                            <div class="flex justify-between gap-4 text-sm">
                                <span class="text-slate-400">Gedrukt op</span>
                                <span class="text-right text-white">{{ store.selectedCard.printed_at_label || '—' }}</span>
                            </div>
                            <div class="flex justify-between gap-4 text-sm">
                                <span class="text-slate-400">Uitgegeven op</span>
                                <span class="text-right text-white">{{ store.selectedCard.issued_at_label || '—' }}</span>
                            </div>
                            <div class="flex justify-between gap-4 text-sm">
                                <span class="text-slate-400">Teruggebracht op</span>
                                <span class="text-right text-white">{{ store.selectedCard.returned_at_label || '—' }}</span>
                            </div>
                        </div>

                        <div class="rounded-2xl border border-slate-800 bg-slate-950 p-4">
                            <div class="text-sm font-semibold text-white">Notities</div>
                            <div class="mt-2 whitespace-pre-wrap text-sm text-slate-300">{{ store.selectedCard.notes || 'Geen notities toegevoegd.' }}</div>
                        </div>
                    </div>

                    <div v-else class="flex flex-1 items-center justify-center p-6 text-sm text-slate-400">
                        Selecteer een kaart om details te bekijken.
                    </div>

                    <div class="border-t border-slate-800 p-4">
                        <div class="flex flex-wrap justify-end gap-3">
                            <button type="button" class="rounded-2xl border border-slate-700 bg-slate-800 px-4 py-3 text-sm font-semibold text-slate-200 disabled:opacity-50" :disabled="!store.selectedCard || !store.selectedCard.badge_template_name || store.printing" @click="handlePrint">
                                {{ store.printing ? 'Printen...' : 'Afdrukken' }}
                            </button>
                            <button type="button" class="rounded-2xl border border-slate-700 bg-slate-800 px-4 py-3 text-sm font-semibold text-slate-200 disabled:opacity-50" :disabled="!store.selectedCard" @click="openEdit">Bewerken</button>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div v-if="showModal" class="fixed inset-0 z-50 flex items-center justify-center bg-slate-950/80 p-4" @click.self="closeModal">
            <form class="w-full max-w-4xl rounded-3xl border border-slate-800 bg-slate-900 p-6 shadow-2xl" @submit.prevent="submit">
                <div class="flex items-start justify-between gap-4">
                    <div>
                        <h2 class="text-xl font-semibold text-white">{{ form.id ? 'Fysieke kaart bewerken' : 'Nieuwe fysieke kaart' }}</h2>
                        <p class="mt-1 text-sm text-slate-400">Maak een fysieke RFID-kaart aan en koppel ze aan een voucher type.</p>
                    </div>
                    <button type="button" class="rounded-xl border border-slate-700 bg-slate-800 px-3 py-2 text-sm text-slate-200" @click="closeModal">Sluiten</button>
                </div>

                <div class="mt-6 grid gap-4 md:grid-cols-2">
                    <label class="space-y-2 text-sm text-slate-300">
                        <span>Voucher type</span>
                        <select v-model="form.voucher_template_id" class="w-full rounded-2xl border border-slate-700 bg-slate-950 px-4 py-3 text-white" required>
                            <option value="">Selecteer voucher type</option>
                            <option v-for="template in store.voucherTemplates" :key="template.id" :value="template.id">{{ template.name }}</option>
                        </select>
                    </label>

                    <label class="space-y-2 text-sm text-slate-300">
                        <span>RFID UID</span>
                        <input v-model="form.rfid_uid" class="w-full rounded-2xl border border-slate-700 bg-slate-950 px-4 py-3 text-white" placeholder="Scan of plak RFID UID" required>
                    </label>

                    <label class="space-y-2 text-sm text-slate-300">
                        <span>Label</span>
                        <input v-model="form.label" class="w-full rounded-2xl border border-slate-700 bg-slate-950 px-4 py-3 text-white" placeholder="Bijv. Kaart 001 of 25 euro rek 1">
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

                    <label class="space-y-2 text-sm text-slate-300">
                        <span>Gedrukt op</span>
                        <input v-model="form.printed_at" type="date" class="w-full rounded-2xl border border-slate-700 bg-slate-950 px-4 py-3 text-white">
                    </label>

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

                <div class="mt-6 flex justify-end gap-3">
                    <button type="button" class="rounded-2xl border border-slate-700 bg-slate-800 px-4 py-3 text-sm font-semibold text-slate-200" @click="closeModal">Annuleren</button>
                    <button type="submit" class="rounded-2xl bg-blue-600 px-4 py-3 text-sm font-semibold text-white">{{ store.saving ? 'Opslaan...' : 'Opslaan' }}</button>
                </div>
            </form>
        </div>
    </div>
</template>

<script setup>
import { computed, onMounted, reactive, ref } from 'vue'
import { useCardsStore } from '../stores/useCardsStore'

const store = useCardsStore()
const showModal = ref(false)
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
    { label: 'Op stock', value: store.summary.stock ?? 0, class: 'border-slate-500/20 bg-slate-800/70', labelClass: 'text-slate-300', valueClass: 'text-white' },
    { label: 'In omloop', value: store.summary.in_circulation ?? 0, class: 'border-sky-500/20 bg-sky-500/10', labelClass: 'text-sky-300', valueClass: 'text-sky-200' },
    { label: 'Teruggebracht', value: store.summary.returned ?? 0, class: 'border-amber-500/20 bg-amber-500/10', labelClass: 'text-amber-300', valueClass: 'text-amber-200' },
])

onMounted(() => store.fetchCards())

function emptyForm() {
    return {
        id: null,
        voucher_template_id: '',
        label: '',
        internal_reference: '',
        rfid_uid: '',
        status: 'stock',
        notes: '',
        printed_at: '',
        issued_at: '',
        returned_at: '',
    }
}

function openCreate() {
    Object.assign(form, emptyForm())
    showModal.value = true
}

function openEdit() {
    if (!store.selectedCard) return
    Object.assign(form, { ...emptyForm(), ...store.selectedCard })
    showModal.value = true
}

function closeModal() {
    showModal.value = false
}

async function submit() {
    await store.saveCard({ ...form })
    closeModal()
}

async function handlePrint() {
    if (!store.selectedCard) {
        return
    }

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

function money(value) {
    return new Intl.NumberFormat('nl-BE', { minimumFractionDigits: 2, maximumFractionDigits: 2 }).format(Number(value ?? 0))
}
</script>
