<template>
    <div class="mt-4 flex shrink-0 items-center justify-between border-t border-slate-800 pt-3">
        <button
            type="button"
            class="inline-flex h-10 items-center gap-2 rounded-xl bg-slate-800 px-4 text-sm font-semibold text-slate-300 transition hover:bg-slate-700"
            @click="$emit('cancel')"
        >
            <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8">
                <path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12" />
            </svg>
            Annuleren
        </button>

        <div class="flex items-center gap-3">
            <button
                v-if="tab === 'details'"
                type="button"
                class="inline-flex h-10 items-center gap-2 rounded-xl bg-slate-800 px-4 text-sm font-semibold text-slate-300 transition hover:bg-slate-700"
                @click="$emit('previous')"
            >
                <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8">
                    <path stroke-linecap="round" stroke-linejoin="round" d="m15 19-7-7 7-7" />
                </svg>
                Vorige
            </button>

            <button
                v-if="tab === 'invoice'"
                type="button"
                class="inline-flex h-10 items-center gap-2 rounded-xl bg-slate-800 px-4 text-sm font-semibold text-slate-300 transition hover:bg-slate-700"
                @click="$emit('previous')"
            >
                <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8">
                    <path stroke-linecap="round" stroke-linejoin="round" d="m15 19-7-7 7-7" />
                </svg>
                Vorige
            </button>

            <button
                v-if="tab === 'quick'"
                type="button"
                class="inline-flex h-10 items-center gap-2 rounded-xl bg-blue-500/15 px-4 text-sm font-semibold text-blue-300 transition hover:bg-blue-500/25"
                @click="$emit('next')"
            >
                Volgende
                <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8">
                    <path stroke-linecap="round" stroke-linejoin="round" d="m9 5 7 7-7 7" />
                </svg>
            </button>

            <button
                v-if="tab === 'details'"
                type="button"
                class="inline-flex h-10 items-center gap-2 rounded-xl bg-blue-500/15 px-4 text-sm font-semibold text-blue-300 transition hover:bg-blue-500/25"
                @click="$emit('next')"
            >
                Volgende
                <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8">
                    <path stroke-linecap="round" stroke-linejoin="round" d="m9 5 7 7-7 7" />
                </svg>
            </button>

            <button
                type="button"
                class="inline-flex h-10 items-center gap-2 rounded-xl bg-blue-600 px-5 text-sm font-semibold text-white transition hover:bg-blue-700"
                @click="$emit('submit')"
            >
                <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8">
                    <path stroke-linecap="round" stroke-linejoin="round" d="m5 13 4 4L19 7" />
                </svg>
                Opslaan
            </button>
        </div>
    </div>
</template>

<script setup>
defineProps({
    tab: {
        type: String,
        required: true,
    },
})

defineEmits(['cancel', 'previous', 'next', 'submit'])
</script>
