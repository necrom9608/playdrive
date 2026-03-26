<template>
    <div
        v-if="open"
        class="absolute right-0 z-30 mt-3 w-[520px] rounded-3xl border border-slate-700 bg-slate-900 p-5 shadow-2xl"
    >
        <div class="mb-3 text-xs font-semibold uppercase tracking-wide text-slate-400">
            Status
        </div>

        <div class="flex flex-wrap gap-3">
            <button
                v-for="option in statusOptions"
                :key="option.value"
                type="button"
                class="inline-flex items-center gap-2 rounded-full border px-4 py-2 text-sm font-semibold transition"
                :class="statusFilters[option.value]
                    ? 'border-blue-500 bg-blue-600 text-white'
                    : 'border-slate-700 bg-slate-950 text-slate-300 hover:bg-slate-800'"
                @click="$emit('toggle-status-filter', option.value)"
            >
                <span
                    class="flex h-5 w-5 items-center justify-center rounded-md border text-[11px]"
                    :class="statusFilters[option.value]
                        ? 'border-blue-200 bg-blue-500 text-white'
                        : 'border-slate-600 bg-slate-900 text-transparent'"
                >
                    ✓
                </span>

                <span>{{ option.label }}</span>
            </button>
        </div>

        <div class="mt-6 flex items-center justify-between">
            <button
                type="button"
                class="rounded-2xl border border-slate-700 bg-slate-950 px-4 py-3 text-sm font-semibold text-slate-200 transition hover:bg-slate-800"
                @click="$emit('reset-filters')"
            >
                Reset filters
            </button>

            <button
                type="button"
                class="rounded-2xl bg-slate-700 px-4 py-3 text-sm font-semibold text-white transition hover:bg-slate-600"
                @click="$emit('close')"
            >
                Sluiten
            </button>
        </div>
    </div>
</template>

<script setup>
defineProps({
    open: {
        type: Boolean,
        default: false,
    },
    statusFilters: {
        type: Object,
        required: true,
    },
    statusOptions: {
        type: Array,
        required: true,
    },
})

defineEmits(['toggle-status-filter', 'reset-filters', 'close'])
</script>
