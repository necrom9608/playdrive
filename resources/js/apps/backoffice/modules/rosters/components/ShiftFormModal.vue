<template>
    <teleport to="body">
        <div v-if="open" class="fixed inset-0 z-50 flex items-center justify-center p-4">
            <div class="absolute inset-0 bg-slate-950/80" @click="$emit('close')" />

            <div class="relative z-10 w-full max-w-md overflow-hidden rounded-3xl border border-slate-800 bg-slate-900 shadow-2xl">
                <div class="flex items-center justify-between border-b border-slate-800 px-6 py-4">
                    <div>
                        <h2 class="text-lg font-semibold text-white">
                            {{ isEdit ? 'Shift bewerken' : 'Shift toevoegen' }}
                        </h2>
                        <p class="mt-1 text-sm text-slate-400">
                            {{ contextLabel }}
                        </p>
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
                        <div class="grid gap-4 sm:grid-cols-2">
                            <div>
                                <label class="mb-1 block text-sm font-medium text-slate-300">Van *</label>
                                <input
                                    v-model="form.starts_at"
                                    type="time"
                                    class="w-full rounded-xl border border-slate-700 bg-slate-950 px-4 py-3 text-sm text-white outline-none transition focus:border-sky-500"
                                />
                            </div>
                            <div>
                                <label class="mb-1 block text-sm font-medium text-slate-300">Tot *</label>
                                <input
                                    v-model="form.ends_at"
                                    type="time"
                                    class="w-full rounded-xl border border-slate-700 bg-slate-950 px-4 py-3 text-sm text-white outline-none transition focus:border-sky-500"
                                />
                            </div>
                        </div>

                        <div>
                            <label class="mb-1 block text-sm font-medium text-slate-300">Notitie</label>
                            <input
                                v-model="form.note"
                                type="text"
                                placeholder="bijv. Onthaal, Bar, VR-zone"
                                class="w-full rounded-xl border border-slate-700 bg-slate-950 px-4 py-3 text-sm text-white outline-none transition focus:border-sky-500"
                            />
                        </div>

                        <p v-if="overnight" class="text-xs text-amber-300">
                            Eindtijd ligt vóór de starttijd — deze shift loopt over middernacht.
                        </p>

                        <div class="flex flex-wrap items-center gap-3 pt-2">
                            <button
                                type="button"
                                :disabled="saving || !valid"
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

                            <button
                                v-if="isEdit"
                                type="button"
                                class="ml-auto rounded-xl border border-rose-500/30 bg-rose-500/10 px-4 py-3 text-sm font-semibold text-rose-200 transition hover:bg-rose-500/20"
                                @click="$emit('delete')"
                            >
                                Verwijderen
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </teleport>
</template>

<script setup>
import { computed, reactive, watch } from 'vue'

const props = defineProps({
    open:        { type: Boolean, default: false },
    saving:      { type: Boolean, default: false },
    error:       { type: String, default: '' },
    shift:       { type: Object, default: null },   // bestaande shift bij bewerken
    staffName:   { type: String, default: '' },
    dateLabel:   { type: String, default: '' },
})

const emit = defineEmits(['close', 'submit', 'delete'])

const form = reactive({
    starts_at: '',
    ends_at:   '',
    note:      '',
})

const isEdit = computed(() => !!props.shift?.id)

const contextLabel = computed(() => {
    const parts = [props.staffName, props.dateLabel].filter(Boolean)
    return parts.join(' · ')
})

const valid = computed(() => !!form.starts_at && !!form.ends_at)

const overnight = computed(() =>
    valid.value && form.ends_at <= form.starts_at
)

watch(() => props.open, (open) => {
    if (open) {
        form.starts_at = props.shift?.starts_at ?? ''
        form.ends_at   = props.shift?.ends_at ?? ''
        form.note      = props.shift?.note ?? ''
    }
})

function submitForm() {
    if (!valid.value) return
    emit('submit', {
        starts_at: form.starts_at,
        ends_at:   form.ends_at,
        note:      form.note || null,
    })
}
</script>
