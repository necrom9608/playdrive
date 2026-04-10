<template>
    <section class="flex min-h-0 flex-1 flex-col rounded-[2rem] border border-slate-800 bg-slate-900/70 px-5 py-5 shadow-2xl shadow-slate-950/40">
        <div class="flex items-center justify-between gap-3">
            <div class="text-xs uppercase tracking-[0.22em] text-blue-300/80">Producten</div>
            <div class="rounded-full bg-blue-500/10 px-3 py-1 text-sm font-semibold text-blue-200">
                {{ itemCount }} items
            </div>
        </div>

        <div class="mt-4 flex-1 space-y-3 overflow-y-auto pr-1">
            <div
                v-for="item in items"
                :key="item.name"
                class="flex items-center justify-between rounded-2xl border border-slate-800 bg-slate-950/60 px-4 py-4"
            >
                <div class="min-w-0 pr-4">
                    <div class="truncate text-lg font-semibold">{{ item.name }}</div>
                    <div class="mt-1 text-sm text-slate-400">{{ item.quantity }} × € {{ formatMoney(item.unit_price) }}</div>
                </div>
                <div class="text-xl font-semibold">{{ item.quantity }}×</div>
            </div>

            <div v-if="!items.length" class="rounded-2xl border border-dashed border-slate-800 px-4 py-8 text-center text-slate-400">
                Nog geen artikelen toegevoegd.
            </div>
        </div>

        <div class="mt-4 rounded-[2rem] border border-slate-700 bg-slate-900 px-5 py-5 shadow-2xl shadow-slate-950/40">
            <div class="text-xs uppercase tracking-[0.22em] text-slate-400">Totaal</div>
            <div class="mt-2 text-5xl font-bold leading-none text-white">€ {{ formatMoney(total) }}</div>
        </div>
    </section>
</template>

<script setup>
const props = defineProps({
    items: {
        type: Array,
        default: () => [],
    },
    itemCount: {
        type: Number,
        default: 0,
    },
    total: {
        type: Number,
        default: 0,
    },
})

function formatMoney(value) {
    return Number(value ?? 0).toFixed(2).replace('.', ',')
}
</script>
