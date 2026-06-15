<template>
    <teleport to="body">
        <div v-if="open" class="fixed inset-0 z-50 flex items-center justify-center p-4">
            <div class="absolute inset-0 bg-slate-950/80" @click="$emit('close')" />

            <div class="relative z-10 w-full max-w-md overflow-hidden rounded-3xl border border-slate-800 bg-slate-900 shadow-2xl">
                <div class="flex items-center justify-between border-b border-slate-800 px-6 py-4">
                    <h2 class="text-lg font-semibold text-white">{{ isEdit ? 'Rol bewerken' : 'Rol toevoegen' }}</h2>
                    <button type="button" class="inline-flex h-10 w-10 items-center justify-center rounded-xl border border-slate-700 text-slate-300 transition hover:bg-slate-800 hover:text-white" @click="$emit('close')">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z" clip-rule="evenodd" /></svg>
                    </button>
                </div>

                <div class="p-6">
                    <div v-if="error" class="mb-4 rounded-2xl border border-red-800 bg-red-950/40 px-4 py-3 text-sm text-red-300">{{ error }}</div>

                    <div class="space-y-4">
                        <div>
                            <label class="mb-1 block text-sm font-medium text-slate-300">Naam *</label>
                            <input v-model="form.name" type="text" placeholder="bijv. Bar, Onthaal, VR-zone" class="w-full rounded-xl border border-slate-700 bg-slate-950 px-4 py-3 text-sm text-white outline-none transition focus:border-sky-500" />
                        </div>

                        <div>
                            <label class="mb-2 block text-sm font-medium text-slate-300">Kleur</label>
                            <div class="flex flex-wrap gap-2">
                                <button
                                    v-for="c in palette"
                                    :key="c"
                                    type="button"
                                    class="h-8 w-8 rounded-lg border-2 transition"
                                    :style="{ backgroundColor: c }"
                                    :class="form.color === c ? 'border-white' : 'border-transparent'"
                                    @click="form.color = c"
                                />
                            </div>
                        </div>

                        <label class="flex items-center gap-3 pt-1">
                            <input v-model="form.is_active" type="checkbox" class="h-4 w-4 rounded border-slate-600 bg-slate-950" />
                            <span class="text-sm text-slate-300">Actief (zichtbaar in keuzelijsten)</span>
                        </label>

                        <div class="flex flex-wrap gap-3 pt-2">
                            <button type="button" :disabled="saving || !form.name" class="rounded-xl bg-blue-600 px-4 py-3 text-sm font-semibold text-white transition hover:bg-blue-500 disabled:opacity-60" @click="submitForm">
                                {{ saving ? 'Opslaan...' : 'Opslaan' }}
                            </button>
                            <button type="button" class="rounded-xl border border-slate-700 px-4 py-3 text-sm font-medium text-slate-300 transition hover:bg-slate-800" @click="$emit('close')">Annuleren</button>

                            <button v-if="isEdit" type="button" class="ml-auto rounded-xl border border-rose-500/30 bg-rose-500/10 px-4 py-3 text-sm font-semibold text-rose-200 transition hover:bg-rose-500/20" @click="$emit('delete')">
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
    open:   { type: Boolean, default: false },
    saving: { type: Boolean, default: false },
    error:  { type: String, default: '' },
    role:   { type: Object, default: null },
})

const emit = defineEmits(['close', 'submit', 'delete'])

const palette = ['#38bdf8', '#34d399', '#fbbf24', '#f472b6', '#a78bfa', '#f87171', '#60a5fa', '#2dd4bf', '#fb923c', '#94a3b8']

const form = reactive({ name: '', color: '#38bdf8', is_active: true })

const isEdit = computed(() => !!props.role?.id)

watch(() => props.open, (open) => {
    if (open) {
        form.name      = props.role?.name ?? ''
        form.color     = props.role?.color ?? '#38bdf8'
        form.is_active = props.role?.is_active ?? true
    }
})

function submitForm() {
    if (!form.name) return
    emit('submit', { name: form.name, color: form.color, is_active: form.is_active })
}
</script>
