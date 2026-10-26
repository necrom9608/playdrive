<template>
    <ModalDialog
        :open="open"
        :title="isEdit ? 'Toestel bewerken' : 'Toestel koppelen'"
        :description="isEdit ? '' : 'Open de kiosk of Playscope op het toestel. Het scherm toont een koppelcode van 6 tekens.'"
        @close="$emit('close')"
    >
        <div v-if="error" class="mb-4 rounded-2xl border border-red-800 bg-red-950/40 px-4 py-3 text-sm text-red-300">{{ error }}</div>

        <div class="space-y-4">
            <label v-if="!isEdit" class="block space-y-1">
                <span class="block text-sm font-medium text-slate-300">Koppelcode *</span>
                <input
                    v-model="form.pairing_code"
                    type="text"
                    maxlength="7"
                    autocomplete="off"
                    placeholder="ABC123"
                    class="input text-center font-mono text-2xl uppercase tracking-[0.4em]"
                />
            </label>

            <label class="block space-y-1">
                <span class="block text-sm font-medium text-slate-300">Naam *</span>
                <input v-model="form.name" type="text" placeholder="bijv. Kiosk inkom, LAN-PC 01" class="input" />
            </label>

            <label class="block space-y-1">
                <span class="block text-sm font-medium text-slate-300">Speelplek</span>
                <select v-model="form.setup_id" class="input">
                    <option :value="null">Geen (bv. kioskscherm)</option>
                    <option v-for="s in setups" :key="s.id" :value="s.id">{{ s.name }}{{ s.zone ? ` · ${s.zone}` : '' }}</option>
                </select>
                <p class="text-xs text-slate-500">Een Playscope-PC koppel je aan zijn speelplek, zo komen speelstatistieken op de juiste plek terecht.</p>
            </label>

            <label v-if="isEdit" class="flex items-center gap-3 pt-1">
                <ToggleSwitch v-model="form.is_active" />
                <span class="text-sm text-slate-300">Actief (uitschakelen blokkeert het toestel zonder het te verwijderen)</span>
            </label>
        </div>

        <div class="mt-6 flex flex-wrap gap-3">
            <button type="button" :disabled="saving || !canSubmit" class="rounded-xl bg-blue-600 px-4 py-3 text-sm font-semibold text-white transition hover:bg-blue-500 disabled:opacity-60" @click="submit">
                {{ saving ? 'Bezig...' : (isEdit ? 'Opslaan' : 'Koppelen') }}
            </button>
            <button type="button" class="rounded-xl border border-slate-700 px-4 py-3 text-sm font-medium text-slate-300 transition hover:bg-slate-800" @click="$emit('close')">Annuleren</button>
            <button v-if="isEdit" type="button" class="ml-auto rounded-xl border border-rose-500/30 bg-rose-500/10 px-4 py-3 text-sm font-semibold text-rose-200 transition hover:bg-rose-500/20" @click="$emit('delete')">
                Ontkoppelen
            </button>
        </div>
    </ModalDialog>
</template>

<script setup>
import { computed, reactive, watch } from 'vue'
import ModalDialog from '../../../components/ModalDialog.vue'
import ToggleSwitch from '../../../components/ToggleSwitch.vue'

const props = defineProps({
    open: { type: Boolean, default: false },
    saving: { type: Boolean, default: false },
    error: { type: String, default: '' },
    device: { type: Object, default: null },
    setups: { type: Array, default: () => [] },
})

const emit = defineEmits(['close', 'submit', 'delete'])

const form = reactive({ pairing_code: '', name: '', setup_id: null, is_active: true })

const isEdit = computed(() => !!props.device?.id)
const normalizedCode = computed(() => form.pairing_code.toUpperCase().replace(/[^A-Z0-9]/g, ''))
const canSubmit = computed(() => form.name && (isEdit.value || normalizedCode.value.length >= 6))

watch(() => props.open, (open) => {
    if (!open) return
    form.pairing_code = ''
    form.name = props.device?.name ?? ''
    form.setup_id = props.device?.setup_id ?? null
    form.is_active = props.device?.is_active ?? true
})

function submit() {
    if (!canSubmit.value) return
    const payload = { name: form.name, setup_id: form.setup_id }
    if (isEdit.value) {
        payload.is_active = form.is_active
    } else {
        payload.pairing_code = normalizedCode.value
    }
    emit('submit', payload)
}
</script>

<style scoped>
.input {
    width: 100%;
    border-radius: 0.75rem;
    border: 1px solid rgb(51 65 85);
    background: rgb(2 6 23);
    padding: 0.75rem 1rem;
    font-size: 0.875rem;
    color: white;
    outline: none;
}
.input:focus {
    border-color: rgb(14 165 233);
}
.input.text-2xl {
    font-size: 1.5rem;
}
</style>
