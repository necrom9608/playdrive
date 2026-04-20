<template>
    <teleport to="body">
        <div v-if="open" class="fixed inset-0 z-50 flex items-center justify-center p-4">
            <div class="absolute inset-0 bg-slate-950/80" @click="$emit('close')" />

            <div class="relative z-10 w-full max-w-md overflow-hidden rounded-3xl border border-slate-800 bg-slate-900 shadow-2xl">
                <div class="flex items-center justify-between border-b border-slate-800 px-6 py-4">
                    <div>
                        <h2 class="text-lg font-semibold text-white">Uitzondering toevoegen</h2>
                        <p class="mt-1 text-sm text-slate-400">Specifieke dag afwijkend open of gesloten.</p>
                    </div>
                    <button
                        type="button"
                        class="inline-flex h-10 w-10 items-center justify-center rounded-xl border border-slate-700 text-slate-300 transition hover:bg-slate-800 hover:text-white"
                        @click="$emit('close')"
                    >
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor">
                            <path fill-rule="evenodd" d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z" clip-rule="evenodd" />
                        </svg>
                    </button>
                </div>

                <div class="p-6">
                    <div v-if="error" class="mb-4 rounded-2xl border border-red-800 bg-red-950/40 px-4 py-3 text-sm text-red-300">
                        {{ error }}
                    </div>

                    <div class="space-y-4">
                        <div>
                            <label class="mb-1 block text-sm font-medium text-slate-300">Datum *</label>
                            <input
                                v-model="form.date"
                                type="date"
                                class="w-full rounded-xl border border-slate-700 bg-slate-950 px-4 py-3 text-sm text-white outline-none transition focus:border-sky-500"
                            />
                        </div>

                        <div>
                            <label class="mb-1 block text-sm font-medium text-slate-300">Label</label>
                            <input
                                v-model="form.label"
                                type="text"
                                placeholder="bijv. Feestdag, Extra dag open"
                                class="w-full rounded-xl border border-slate-700 bg-slate-950 px-4 py-3 text-sm text-white outline-none transition focus:border-sky-500"
                            />
                        </div>

                        <div>
                            <label class="mb-2 block text-sm font-medium text-slate-300">Status</label>
                            <div class="flex gap-3">
                                <button
                                    type="button"
                                    class="flex-1 rounded-xl border py-3 text-sm font-semibold transition"
                                    :class="form.is_open
                                        ? 'border-emerald-500 bg-emerald-500/15 text-emerald-300'
                                        : 'border-slate-700 text-slate-400 hover:bg-slate-800'"
                                    @click="form.is_open = true"
                                >
                                    Open
                                </button>
                                <button
                                    type="button"
                                    class="flex-1 rounded-xl border py-3 text-sm font-semibold transition"
                                    :class="!form.is_open
                                        ? 'border-rose-500 bg-rose-500/15 text-rose-300'
                                        : 'border-slate-700 text-slate-400 hover:bg-slate-800'"
                                    @click="form.is_open = false"
                                >
                                    Gesloten
                                </button>
                            </div>
                        </div>

                        <div v-if="form.is_open" class="grid gap-4 sm:grid-cols-2">
                            <div>
                                <label class="mb-1 block text-sm font-medium text-slate-300">Open van</label>
                                <input
                                    v-model="form.open_from"
                                    type="time"
                                    class="w-full rounded-xl border border-slate-700 bg-slate-950 px-4 py-3 text-sm text-white outline-none transition focus:border-sky-500"
                                />
                            </div>
                            <div>
                                <label class="mb-1 block text-sm font-medium text-slate-300">Open tot</label>
                                <input
                                    v-model="form.open_until"
                                    type="time"
                                    class="w-full rounded-xl border border-slate-700 bg-slate-950 px-4 py-3 text-sm text-white outline-none transition focus:border-sky-500"
                                />
                            </div>
                        </div>

                        <div class="flex flex-wrap gap-3 pt-2">
                            <button
                                type="button"
                                :disabled="saving"
                                class="rounded-xl bg-blue-600 px-4 py-3 text-sm font-semibold text-white transition hover:bg-blue-500 disabled:opacity-60"
                                @click="submitForm"
                            >
                                {{ saving ? 'Opslaan...' : 'Opslaan' }}
                            </button>
                            <button
                                type="button"
                                class="rounded-xl border border-slate-700 px-4 py-3 text-sm font-medium text-slate-300 transition hover:bg-slate-800"
                                @click="$emit('close')"
                            >
                                Annuleren
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </teleport>
</template>

<script setup>
import { reactive, watch } from 'vue'

const props = defineProps({
    open:   { type: Boolean, default: false },
    saving: { type: Boolean, default: false },
    error:  { type: String, default: '' },
})

const emit = defineEmits(['close', 'submit'])

const form = reactive({
    date:       '',
    label:      '',
    is_open:    false,
    open_from:  '',
    open_until: '',
})

watch(() => props.open, (open) => {
    if (!open) {
        form.date       = ''
        form.label      = ''
        form.is_open    = false
        form.open_from  = ''
        form.open_until = ''
    }
})

function submitForm() {
    emit('submit', { ...form })
}
</script>
