<template>
    <teleport to="body">
        <div v-if="open" class="fixed inset-0 z-50 flex items-center justify-center p-4">
            <div class="absolute inset-0 bg-slate-950/80" @click="$emit('close')" />

            <div class="relative z-10 w-full max-w-md overflow-hidden rounded-3xl border border-slate-800 bg-slate-900 shadow-2xl">
                <div class="flex items-center justify-between border-b border-slate-800 px-6 py-4">
                    <div>
                        <h2 class="text-lg font-semibold text-white">{{ isEditing ? 'Regio bewerken' : 'Nieuwe regio' }}</h2>
                        <p class="mt-1 text-sm text-slate-400">Regio's worden gebruikt om schoolvakanties te koppelen aan tenants.</p>
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
                            <label class="mb-1 block text-sm font-medium text-slate-300">Code *</label>
                            <input
                                v-model="form.code"
                                type="text"
                                placeholder="bijv. BE-VL"
                                class="w-full rounded-xl border border-slate-700 bg-slate-950 px-4 py-3 font-mono text-sm text-white outline-none transition focus:border-cyan-500"
                            />
                            <p class="mt-1 text-xs text-slate-500">Korte unieke code, max. 10 tekens.</p>
                        </div>

                        <div>
                            <label class="mb-1 block text-sm font-medium text-slate-300">Naam *</label>
                            <input
                                v-model="form.name"
                                type="text"
                                placeholder="bijv. Vlaanderen"
                                class="w-full rounded-xl border border-slate-700 bg-slate-950 px-4 py-3 text-sm text-white outline-none transition focus:border-cyan-500"
                            />
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
    open:   { type: Boolean, default: false },
    region: { type: Object, default: null },
    saving: { type: Boolean, default: false },
    error:  { type: String, default: '' },
})

const emit = defineEmits(['close', 'submit'])

const form = reactive({ code: '', name: '' })

const isEditing = computed(() => !!props.region?.id)

watch(
    () => [props.open, props.region],
    ([open]) => {
        if (open) {
            form.code = props.region?.code ?? ''
            form.name = props.region?.name ?? ''
        } else {
            form.code = ''
            form.name = ''
        }
    },
    { immediate: true, deep: true },
)

function submitForm() {
    emit('submit', { ...form })
}
</script>
