<template>
    <Teleport to="body">
        <div
            v-if="open"
            class="fixed inset-0 z-[130] flex items-center justify-center bg-slate-950/70 p-4"
        >
            <div class="flex h-[800px] w-full max-w-[1200px] flex-col overflow-hidden rounded-[28px] border border-slate-800 bg-[#091633] shadow-2xl">
                <!-- Header -->
                <div class="flex items-start justify-between border-b border-slate-800 px-5 py-4">
                    <div>
                        <h2 class="text-xl font-semibold text-white">
                            {{ phase === 'build' ? 'Bon splitsen' : 'Checkout · gesplitst' }}
                        </h2>
                        <p class="mt-1 text-sm text-slate-400">{{ contextLabel }}</p>
                    </div>
                    <button
                        type="button"
                        class="inline-flex h-11 w-11 items-center justify-center rounded-2xl border border-slate-700 bg-slate-800/90 text-white transition hover:bg-slate-700"
                        @click="handleClose"
                    >
                        <XMarkIcon class="h-5 w-5" />
                    </button>
                </div>

                <!-- ============ 1. Bonnen samenstellen ============ -->
                <template v-if="phase === 'build'">
                    <div class="flex flex-wrap items-center gap-2 border-b border-slate-800 px-5 py-3">
                        <span class="mr-1 text-sm text-slate-400">Verplaats naar:</span>
                        <button
                            v-for="(bon, index) in allBons"
                            :key="'tgt-' + index"
                            type="button"
                            class="flex items-center gap-2 rounded-2xl border px-4 py-2 text-sm font-semibold transition"
                            :class="index === active
                                ? 'border-sky-500 bg-sky-500/15 text-sky-100'
                                : 'border-slate-700 bg-[#020b24] text-slate-200 hover:border-slate-500'"
                            @click="active = index"
                        >
                            {{ bon.label }}
                            <span class="text-xs font-medium text-slate-400">€ {{ formatMoney(bonTotal(bon.lines)) }}</span>
                        </button>
                        <button
                            type="button"
                            class="rounded-2xl px-3 py-2 text-xs font-semibold text-slate-300 transition hover:bg-slate-800"
                            @click="addBon"
                        >
                            <span class="inline-flex items-center gap-1.5"><PlusIcon class="h-4 w-4" /> Nieuwe bon</span>
                        </button>
                    </div>

                    <div class="grid min-h-0 flex-1 auto-cols-[minmax(300px,1fr)] grid-flow-col gap-4 overflow-x-auto px-5 py-4">
                        <div
                            v-for="(bon, index) in allBons"
                            :key="'bon-' + index"
                            class="flex min-h-0 flex-col rounded-[20px] border bg-[#020b24]"
                            :class="index === active ? 'border-sky-500/60 ring-1 ring-sky-500/30' : 'border-slate-800'"
                        >
                            <div class="flex items-center justify-between border-b border-slate-800 px-4 py-3">
                                <div class="flex items-center gap-2 font-semibold text-white">
                                    {{ bon.label }}
                                    <span v-if="index === 0" class="rounded-full bg-slate-800 px-2 py-0.5 text-[10px] font-semibold text-slate-400">rest</span>
                                </div>
                                <button
                                    v-if="index > 0 && !bon.lines.length"
                                    type="button"
                                    class="text-xs text-slate-400 hover:text-white"
                                    @click="removeBon(index)"
                                >
                                    Verwijder
                                </button>
                            </div>

                            <div class="min-h-0 flex-1 overflow-y-auto px-4 py-1">
                                <div
                                    v-for="line in bon.lines"
                                    :key="'l-' + index + '-' + line.item_id"
                                    class="flex cursor-pointer select-none items-center gap-3 border-b border-dashed border-slate-700 py-3 last:border-b-0"
                                    @click="tapLine(index, line.item_id, false)"
                                >
                                    <span class="rounded-lg bg-slate-800 px-2 py-0.5 text-sm font-bold text-white">{{ line.quantity }}×</span>
                                    <div class="min-w-0 flex-1">
                                        <div class="truncate font-semibold text-white">{{ line.name }}</div>
                                        <div class="text-xs text-slate-400">€ {{ formatMoney(line.unit) }} / stuk</div>
                                    </div>
                                    <span class="font-bold text-white">€ {{ formatMoney(line.unit * line.quantity) }}</span>
                                    <button
                                        v-if="index !== active"
                                        type="button"
                                        class="rounded-lg border border-slate-700 bg-slate-800 px-2 py-1 text-xs font-semibold text-slate-200 hover:bg-slate-700"
                                        title="Hele lijn verplaatsen"
                                        @click.stop="tapLine(index, line.item_id, true)"
                                    >
                                        <ChevronDoubleRightIcon class="h-4 w-4" />
                                    </button>
                                </div>

                                <div v-if="!bon.lines.length" class="my-3 rounded-xl border border-dashed border-slate-700 px-3 py-6 text-center text-sm text-slate-500">
                                    {{ index === active ? 'Tik op lijnen in een andere bon om ze hierheen te verplaatsen' : 'Leeg' }}
                                </div>
                            </div>

                            <div class="flex items-center justify-between border-t border-slate-800 px-4 py-3">
                                <span class="text-sm text-slate-400">{{ countItems(bon.lines) }} items</span>
                                <span class="text-xl font-bold text-white">€ {{ formatMoney(bonTotal(bon.lines)) }}</span>
                            </div>
                        </div>
                    </div>

                    <div class="flex flex-wrap items-center justify-between gap-3 border-t border-slate-800 px-5 py-4">
                        <div class="text-sm text-slate-400">
                            Tik op een lijn om 1 stuk naar <span class="font-semibold text-slate-200">{{ allBons[active]?.label }}</span> te verplaatsen.
                            Totaal blijft € {{ formatMoney(orderTotal) }}.
                        </div>
                        <div class="flex gap-2">
                            <button type="button" class="rounded-2xl border border-slate-700 bg-slate-800 px-4 py-3 text-sm font-semibold text-slate-200 hover:bg-slate-700" @click="handleClose">
                                Annuleren
                            </button>
                            <button
                                type="button"
                                class="rounded-2xl bg-blue-600 px-4 py-3 text-sm font-semibold text-white hover:bg-blue-500 disabled:cursor-not-allowed disabled:opacity-50"
                                :disabled="filledBonCount < 2"
                                @click="confirmSplit"
                            >
                                Splitsen bevestigen
                            </button>
                        </div>
                    </div>
                </template>

                <!-- ============ 2. Bonnen afrekenen ============ -->
                <template v-else>
                    <div class="min-h-0 flex-1 space-y-3 overflow-y-auto px-5 py-5">
                        <div
                            v-for="row in afterRows"
                            :key="'row-' + row.key"
                            class="flex items-center gap-4 rounded-[20px] border border-slate-800 bg-[#020b24] px-4 py-4"
                        >
                            <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl bg-slate-800 font-bold text-white">{{ row.number }}</div>
                            <div class="min-w-0 flex-1">
                                <div class="font-semibold text-white">{{ row.label }}</div>
                                <div class="truncate text-xs text-slate-400">
                                    {{ row.lines.map(l => `${l.quantity}× ${l.name}`).join(' · ') || '—' }}
                                </div>
                            </div>

                            <span
                                v-if="row.paid"
                                class="shrink-0 rounded-full bg-emerald-500/15 px-3 py-1 text-xs font-semibold text-emerald-300"
                            >
                                Betaald{{ row.paymentMethod ? ' · ' + paymentLabel(row.paymentMethod) : '' }}
                            </span>
                            <span v-else class="shrink-0 rounded-full bg-amber-500/15 px-3 py-1 text-xs font-semibold text-amber-300">Open</span>

                            <div class="w-28 shrink-0 text-right text-xl font-bold text-white">€ {{ formatMoney(row.total) }}</div>

                            <button
                                v-if="row.paid && row.orderId"
                                type="button"
                                class="shrink-0 rounded-xl border border-slate-700 bg-slate-800 px-3 py-2 text-xs font-semibold text-slate-200 hover:bg-slate-700"
                                @click="$emit('print', row.orderId)"
                            >
                                <span class="inline-flex items-center gap-1.5"><PrinterIcon class="h-4 w-4" /> Ticket</span>
                            </button>
                            <button
                                v-else-if="!row.paid"
                                type="button"
                                class="shrink-0 rounded-xl bg-blue-600 px-4 py-2 text-sm font-semibold text-white hover:bg-blue-500 disabled:cursor-not-allowed disabled:opacity-50"
                                :disabled="row.isRest && unpaidBonCount > 0"
                                :title="row.isRest && unpaidBonCount > 0 ? 'Reken eerst de andere bonnen af' : ''"
                                @click="payRow(row)"
                            >
                                Afrekenen
                            </button>
                        </div>
                    </div>

                    <div class="flex flex-wrap items-center justify-between gap-3 border-t border-slate-800 px-5 py-4">
                        <div class="text-sm text-slate-400">
                            <template v-if="unpaidBonCount > 0">
                                Reken eerst de afgesplitste bonnen af; {{ allBons[0].label }} (de rest) komt als laatste.
                            </template>
                            <template v-else>
                                Alle afgesplitste bonnen zijn betaald. Reken nu {{ allBons[0].label }} af.
                            </template>
                        </div>
                        <button type="button" class="rounded-2xl border border-slate-700 bg-slate-800 px-4 py-3 text-sm font-semibold text-slate-200 hover:bg-slate-700" @click="handleClose">
                            Sluiten
                        </button>
                    </div>
                </template>
            </div>
        </div>
    </Teleport>
</template>

<script setup>
import { ChevronDoubleRightIcon, PlusIcon, PrinterIcon, XMarkIcon } from '@heroicons/vue/24/outline'
import { computed, ref, watch } from 'vue'

const props = defineProps({
    open: { type: Boolean, default: false },
    // huidige (open) order: items met id, name, quantity, unit_price_incl_vat
    items: { type: Array, default: () => [] },
    registration: { type: Object, default: null },
})

const emit = defineEmits(['close', 'pay-bon', 'pay-rest', 'done', 'print'])

const phase = ref('build')
const active = ref(1)
// afgesplitste bonnen (Bon 2, Bon 3, …): { label, alloc: { [itemId]: qty }, paid, orderId, paymentMethod, paidLines }
const bons = ref([])

const contextLabel = computed(() => props.registration
    ? `Actieve reservatie: ${props.registration.name ?? `#${props.registration.id}`}`
    : 'Losse verkoop')

function itemId(item) {
    if (item.id) return Number(item.id)
    const match = String(item.line_id ?? '').match(/(\d+)$/)
    return match ? Number(match[1]) : null
}

const orderItems = computed(() => props.items
    .map(item => ({
        item_id: itemId(item),
        name: item.name ?? 'Product',
        quantity: Number(item.quantity ?? 0),
        unit: Number(item.unit_price_incl_vat ?? item.price_incl_vat ?? item.unit_price ?? 0),
    }))
    .filter(item => item.item_id && item.quantity > 0))

function linesFromAlloc(alloc) {
    return orderItems.value
        .filter(item => (alloc[item.item_id] ?? 0) > 0)
        .map(item => ({ ...item, quantity: alloc[item.item_id] }))
}

const unpaidBons = computed(() => bons.value.filter(bon => !bon.paid))

// Bon 1 = rest van het order na aftrek van alle nog niet betaalde bonnen
const restLines = computed(() => orderItems.value
    .map(item => {
        const allocated = unpaidBons.value.reduce((sum, bon) => sum + (bon.alloc[item.item_id] ?? 0), 0)
        return { ...item, quantity: item.quantity - allocated }
    })
    .filter(item => item.quantity > 0))

const allBons = computed(() => [
    { label: 'Bon 1', lines: restLines.value },
    ...bons.value.map(bon => ({ label: bon.label, lines: bon.paid ? (bon.paidLines ?? []) : linesFromAlloc(bon.alloc) })),
])

const orderTotal = computed(() => bonTotal(orderItems.value))
const filledBonCount = computed(() => allBons.value.filter(bon => bon.lines.length).length)
const unpaidBonCount = computed(() => unpaidBons.value.filter(bon => Object.values(bon.alloc).some(q => q > 0)).length)

const afterRows = computed(() => [
    ...bons.value
        .map((bon, i) => ({ bon, i }))
        .filter(({ bon }) => bon.paid || Object.values(bon.alloc).some(q => q > 0))
        .map(({ bon, i }) => {
            const lines = bon.paid ? (bon.paidLines ?? []) : linesFromAlloc(bon.alloc)
            return {
                key: 'b' + i,
                index: i,
                number: i + 2,
                label: bon.label,
                lines,
                total: bon.paid ? Number(bon.paidTotal ?? bonTotal(lines)) : bonTotal(lines),
                paid: bon.paid,
                orderId: bon.orderId,
                paymentMethod: bon.paymentMethod,
                isRest: false,
            }
        }),
    {
        key: 'rest',
        index: -1,
        number: 1,
        label: 'Bon 1',
        lines: restLines.value,
        total: bonTotal(restLines.value),
        paid: false,
        isRest: true,
    },
].filter(row => !row.isRest || row.lines.length))

watch(() => props.open, (open) => {
    if (!open) return
    phase.value = 'build'
    bons.value = [{ label: 'Bon 2', alloc: {}, paid: false }]
    active.value = 1
}, { immediate: true })

// Alles betaald en niets meer over → klaar
watch(() => [restLines.value.length, unpaidBonCount.value, phase.value], () => {
    if (props.open && phase.value === 'after' && !restLines.value.length && unpaidBonCount.value === 0) {
        emit('done')
    }
})

function bonTotal(lines) {
    return Math.round(lines.reduce((sum, line) => sum + line.unit * line.quantity, 0) * 100) / 100
}

function countItems(lines) {
    return lines.reduce((sum, line) => sum + line.quantity, 0)
}

function addBon() {
    bons.value.push({ label: `Bon ${bons.value.length + 2}`, alloc: {}, paid: false })
    active.value = bons.value.length
}

function removeBon(index) {
    bons.value.splice(index - 1, 1)
    bons.value.forEach((bon, i) => { bon.label = `Bon ${i + 2}` })
    active.value = Math.min(active.value, bons.value.length)
}

/**
 * Tik op een lijn in bon `from`:
 * - andere bon dan de actieve → 1 stuk (of alles) naar de actieve bon
 * - de actieve bon zelf → 1 stuk terug naar Bon 1 (of naar Bon 2 als Bon 1 actief is)
 */
function tapLine(from, id, all) {
    const to = from === active.value ? (from === 0 ? 1 : 0) : active.value
    if (to > bons.value.length) return

    const source = allBons.value[from].lines.find(line => line.item_id === id)
    if (!source) return
    const qty = all ? source.quantity : 1

    if (from > 0) {
        const alloc = bons.value[from - 1].alloc
        alloc[id] = Math.max(0, (alloc[id] ?? 0) - qty)
    }

    if (to > 0) {
        const alloc = bons.value[to - 1].alloc
        alloc[id] = (alloc[id] ?? 0) + qty
    }
}

function confirmSplit() {
    phase.value = 'after'
}

function payRow(row) {
    if (row.isRest) {
        emit('pay-rest')
        return
    }

    const bon = bons.value[row.index]
    bon.pendingLines = row.lines.map(line => ({ ...line }))
    emit('pay-bon', {
        index: row.index,
        label: bon.label,
        lines: Object.entries(bon.alloc)
            .filter(([, qty]) => qty > 0)
            .map(([id, qty]) => ({ item_id: Number(id), quantity: qty })),
        items: row.lines.map(line => ({
            id: line.item_id,
            name: line.name,
            quantity: line.quantity,
            unit_price_incl_vat: line.unit,
            line_total_incl_vat: Math.round(line.unit * line.quantity * 100) / 100,
        })),
    })
}

/** Door OrderPanel aangeroepen na een geslaagde betaling van een afgesplitste bon. */
function markPaid(index, order = null) {
    const bon = bons.value[index]
    if (!bon) return
    // de store is op dit moment al ververst: gebruik de lijnen van vóór de betaling
    bon.paidLines = bon.pendingLines ?? linesFromAlloc(bon.alloc)
    bon.paidTotal = order?.total_incl_vat ?? bonTotal(bon.paidLines)
    bon.paid = true
    bon.orderId = order?.id ?? null
    bon.paymentMethod = order?.payment_method ?? null
    bon.alloc = {}
}

function paymentLabel(method) {
    return { cash: 'Cash', bancontact: 'Bancontact', voucher: 'Cadeaubon' }[method] ?? method
}

function handleClose() {
    emit('close')
}

function formatMoney(value) {
    return new Intl.NumberFormat('nl-BE', { minimumFractionDigits: 2, maximumFractionDigits: 2 }).format(Number(value ?? 0))
}

defineExpose({ markPaid })
</script>
