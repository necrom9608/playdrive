<template>
    <ModalDialog
        :open="open"
        title="Aanwezigheid bewerken"
        description="Pas handmatig de medewerker of de in- en uitchecktijden aan."
        @close="$emit('close')"
    >
        <form class="space-y-4" @submit.prevent="$emit('submit', form)">
            <div v-if="error" class="rounded-2xl border border-red-800 bg-red-950/40 px-4 py-3 text-sm text-red-300">
                {{ error }}
            </div>

            <div>
                <label class="mb-2 block text-sm font-medium text-slate-300">Medewerker</label>
                <select v-model="form.user_id" class="w-full rounded-2xl border border-slate-700 bg-slate-950 px-4 py-3 text-sm text-white outline-none transition focus:border-blue-500">
                    <option value="">Selecteer medewerker</option>
                    <option v-for="item in staff" :key="item.id" :value="String(item.id)">{{ item.name }}</option>
                </select>
            </div>

            <div class="grid gap-4 md:grid-cols-2">
                <div>
                    <label class="mb-2 block text-sm font-medium text-slate-300">Ingecheckt op</label>
                    <input v-model="form.checked_in_at" type="datetime-local" class="w-full rounded-2xl border border-slate-700 bg-slate-950 px-4 py-3 text-sm text-white outline-none transition focus:border-blue-500">
                </div>

                <div>
                    <label class="mb-2 block text-sm font-medium text-slate-300">Uitgecheckt op</label>
                    <input v-model="form.checked_out_at" type="datetime-local" class="w-full rounded-2xl border border-slate-700 bg-slate-950 px-4 py-3 text-sm text-white outline-none transition focus:border-blue-500">
                </div>
            </div>

            <div>
                <label class="mb-2 block text-sm font-medium text-slate-300">RFID UID</label>
                <input v-model="form.rfid_uid" type="text" class="w-full rounded-2xl border border-slate-700 bg-slate-950 px-4 py-3 text-sm text-white outline-none transition focus:border-blue-500" placeholder="Optioneel">
            </div>

            <div class="flex justify-end gap-3 pt-2">
                <button type="button" class="rounded-xl border border-slate-700 px-4 py-3 text-sm font-medium text-slate-300 transition hover:bg-slate-800" @click="$emit('close')">Annuleren</button>
                <button type="submit" :disabled="saving" class="rounded-xl bg-blue-600 px-4 py-3 text-sm font-semibold text-white transition hover:bg-blue-500 disabled:opacity-60">
                    {{ saving ? 'Opslaan...' : 'Opslaan' }}
                </button>
            </div>
        </form>
    </ModalDialog>
</template>

<script setup>
import { reactive, watch } from 'vue'
import ModalDialog from '../../../components/ModalDialog.vue'

const props = defineProps({
    open: { type: Boolean, default: false },
    item: { type: Object, default: null },
    staff: { type: Array, default: () => [] },
    saving: { type: Boolean, default: false },
    error: { type: String, default: '' },
})

defineEmits(['close', 'submit'])

const form = reactive({
    user_id: '',
    checked_in_at: '',
    checked_out_at: '',
    rfid_uid: '',
})

watch(
    () => props.item,
    (value) => {
        form.user_id = value?.user_id ? String(value.user_id) : ''
        form.checked_in_at = value?.checked_in_at || ''
        form.checked_out_at = value?.checked_out_at || ''
        form.rfid_uid = value?.rfid_uid || ''
    },
    { immediate: true },
)
</script>
