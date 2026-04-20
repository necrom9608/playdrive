<template>
    <teleport to="body">
        <div v-if="open" class="fixed inset-0 z-50 flex items-center justify-center p-4">
            <div class="absolute inset-0 bg-slate-950/80" @click="$emit('close')" />

            <div class="relative z-10 w-full max-w-lg overflow-hidden rounded-3xl border border-slate-800 bg-slate-900 shadow-2xl">
                <div class="flex items-center justify-between border-b border-slate-800 px-6 py-4">
                    <div>
                        <h2 class="text-lg font-semibold text-white">{{ isEditing ? 'Periode bewerken' : 'Nieuwe periode' }}</h2>
                        <p class="mt-1 text-sm text-slate-400">Vakantieperiode voor {{ regionName }}</p>
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
                                <label class="mb-1 block text-sm font-medium text-slate-300">Season key *</label>
                                <select
                                    v-model="form.season_key"
                                    class="w-full rounded-xl border border-slate-700 bg-slate-950 px-4 py-3 text-sm text-white outline-none transition focus:border-cyan-500"
                                >
                                    <option value="school_vac">school_vac</option>
                                    <option value="summer">summer</option>
                                    <option value="regular">regular</option>
                                </select>
                            </div>
                            <div>
                                <label class="mb-1 block text-sm font-medium text-slate-300">Prioriteit</label>
                                <input
                                    v-model.number="form.priority"
                                    type="number"
                                    min="0"
                                    max="100"
                                    class="w-full rounded-xl border border-slate-700 bg-slate-950 px-4 py-3 text-sm text-white outline-none transition focus:border-cyan-500"
                                />
                            </div>
                        </div>

                        <div>
                            <label class="mb-1 block text-sm font-medium text-slate-300">Naam *</label>
                            <input
                                v-model="form.season_name"
                                type="text"
                                placeholder="bijv. Krokusvakantie"
                                class="w-full rounded-xl border border-slate-700 bg-slate-950 px-4 py-3 text-sm text-white outline-none transition focus:border-cyan-500"
                            />
                        </div>

                        <div class="grid gap-4 sm:grid-cols-2">
                            <div>
                                <label class="mb-1 block text-sm font-medium text-slate-300">Van *</label>
                                <input
                                    v-model="form.date_from"
                                    type="date"
                                    class="w-full rounded-xl border border-slate-700 bg-slate-950 px-4 py-3 text-sm text-white outline-none transition focus:border-cyan-500"
                                />
                            </div>
                            <div>
                                <label class="mb-1 block text-sm font-medium text-slate-300">Tot en met *</label>
                                <input
                                    v-model="form.date_until"
                                    type="date"
                                    class="w-full rounded-xl border border-slate-700 bg-slate-950 px-4 py-3 text-sm text-white outline-none transition focus:border-cyan-500"
                                />
                            </div>
                        </div>

                        <div class="flex flex-wrap gap-3 pt-2">
                            <button
                                type="button"
                                :disabled="saving"
                                class="rounded-xl bg-cyan-600 px-4 py-3 text-sm font-semibold text-white transition hover:bg-cyan-500 disabled:opacity-60"
                                @click="submitForm"
                            >
                                {{ saving ? 'Opslaan...' : isEditing ? 'Opslaan' : 'Toevoegen' }}
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
import { computed, reactive, watch } from 'vue'

const props = defineProps({
    open:       { type: Boolean, default: false },
    season:     { type: Object, default: null },
    regionName: { type: String, default: '' },
    saving:     { type: Boolean, default: false },
    error:      { type: String, default: '' },
})

const emit = defineEmits(['close', 'submit'])

const form = reactive({
    season_key:  'school_vac',
    season_name: '',
    date_from:   '',
    date_until:  '',
    priority:    20,
})

const isEditing = computed(() => !!props.season?.id)

watch(
    () => [props.open, props.season],
    ([open]) => {
        if (open) {
            form.season_key  = props.season?.season_key  ?? 'school_vac'
            form.season_name = props.season?.season_name ?? ''
            form.date_from   = props.season?.date_from   ?? ''
            form.date_until  = props.season?.date_until  ?? ''
            form.priority    = props.season?.priority    ?? 20
        } else {
            form.season_key  = 'school_vac'
            form.season_name = ''
            form.date_from   = ''
            form.date_until  = ''
            form.priority    = 20
        }
    },
    { immediate: true, deep: true },
)

function submitForm() {
    emit('submit', { ...form })
}
</script>
