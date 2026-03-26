<template>
    <div class="border-t border-slate-800 bg-slate-900 p-4">
        <div class="flex gap-3">
            <div class="flex-1 space-y-3">
                <div class="grid grid-cols-3 gap-3">
                    <button
                        type="button"
                        class="inline-flex h-12 items-center justify-center gap-2 rounded-2xl px-4 text-sm font-semibold shadow-sm transition"
                        :class="hasSelection ? 'bg-blue-600 text-white hover:bg-blue-700' : 'cursor-not-allowed bg-slate-800 text-slate-500'"
                        :disabled="!hasSelection"
                        @click="$emit('check-in')"
                    >
                        Inchecken
                    </button>

                    <button
                        type="button"
                        class="inline-flex h-12 items-center justify-center gap-2 rounded-2xl px-4 text-sm font-semibold shadow-sm transition"
                        :class="hasSelection ? 'bg-emerald-600 text-white hover:bg-emerald-700' : 'cursor-not-allowed bg-slate-800 text-slate-500'"
                        :disabled="!hasSelection"
                        @click="$emit('check-out')"
                    >
                        Uitchecken
                    </button>

                    <button
                        type="button"
                        class="inline-flex h-12 items-center justify-center gap-2 rounded-2xl px-4 text-sm font-semibold shadow-sm transition"
                        :class="hasSelection ? 'bg-amber-500 text-white hover:bg-amber-600' : 'cursor-not-allowed bg-slate-800 text-slate-500'"
                        :disabled="!hasSelection"
                        @click="$emit('edit')"
                    >
                        Bewerken
                    </button>
                </div>

                <div class="grid grid-cols-3 gap-3">
                    <button
                        type="button"
                        class="inline-flex h-12 items-center justify-center gap-2 rounded-2xl px-4 text-sm font-semibold shadow-sm transition"
                        :class="hasSelection ? 'bg-slate-600 text-white hover:bg-slate-500' : 'cursor-not-allowed bg-slate-800 text-slate-500'"
                        :disabled="!hasSelection"
                        @click="$emit('cancel')"
                    >
                        Annuleren
                    </button>

                    <button
                        type="button"
                        class="inline-flex h-12 items-center justify-center gap-2 rounded-2xl px-4 text-sm font-semibold shadow-sm transition"
                        :class="hasSelection ? 'bg-rose-600 text-white hover:bg-rose-700' : 'cursor-not-allowed bg-slate-800 text-slate-500'"
                        :disabled="!hasSelection"
                        @click="$emit('no-show')"
                    >
                        No-show
                    </button>

                    <button
                        type="button"
                        class="inline-flex h-12 items-center justify-center gap-2 rounded-2xl px-4 text-sm font-semibold shadow-sm transition"
                        :class="hasSelection ? 'bg-red-600 text-white hover:bg-red-700' : 'cursor-not-allowed bg-slate-800 text-slate-500'"
                        :disabled="!hasSelection"
                        @click="$emit('delete')"
                    >
                        Verwijderen
                    </button>
                </div>
            </div>

            <div class="w-56 shrink-0">
                <button
                    type="button"
                    class="inline-flex h-full min-h-[108px] w-full flex-col items-center justify-center gap-2 rounded-2xl px-4 text-sm font-semibold shadow-sm transition"
                    :class="hasSelection ? 'bg-slate-700 text-white hover:bg-slate-600' : 'cursor-not-allowed bg-slate-800 text-slate-500'"
                    :disabled="!hasSelection"
                    @click="$emit('clear-selection')"
                >
                    <span>Selectie wissen</span>
                </button>
            </div>
        </div>
    </div>
</template>

<script setup>
defineProps({
    hasSelection: {
        type: Boolean,
        default: false,
    },
})

defineEmits([
    'check-in',
    'check-out',
    'edit',
    'cancel',
    'no-show',
    'delete',
    'clear-selection',
])
</script>
