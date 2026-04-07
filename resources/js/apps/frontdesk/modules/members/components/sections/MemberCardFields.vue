<template>
    <div class="space-y-5">
        <div class="grid gap-5 lg:grid-cols-[minmax(0,1fr)_280px]">
            <div class="space-y-5">
                <label class="space-y-2 text-sm text-slate-300">
                    <span>RFID kaart</span>
                    <input :value="form.rfid_uid" type="text" :class="fieldClass" readonly>
                </label>

                <ScanRfidButton
                    :model-value="form.rfid_uid"
                    label="Scan RFID"
                    title="RFID-kaart scannen"
                    description="Scan de RFID-kaart die je aan dit lid wilt koppelen."
                    confirm-label="RFID koppelen"
                    :show-value="true"
                    @update:model-value="update('rfid_uid', $event)"
                />

                <div class="rounded-2xl border border-slate-800 bg-slate-900/60 p-4 text-sm text-slate-300">
                    <div class="text-xs uppercase tracking-[0.2em] text-slate-500">Kaartstatus</div>
                    <div class="mt-2 text-white">
                        <span v-if="form.member_card_id">Bestaande badge: <span class="font-semibold">{{ form.member_card_label || `Badge #${form.member_card_id}` }}</span></span>
                        <span v-else>Bij opslaan wordt automatisch een member badge aangemaakt.</span>
                    </div>
                    <div v-if="form.member_card_badge_template_name" class="mt-1 text-slate-400">
                        Huidig design: {{ form.member_card_badge_template_name }}
                    </div>
                </div>
            </div>

            <div class="rounded-3xl border border-slate-800 bg-slate-950/70 p-4">
                <div class="text-xs uppercase tracking-[0.2em] text-slate-500">Geselecteerd design</div>
                <div class="mt-3 rounded-2xl border border-sky-500/30 bg-sky-500/10 p-4">
                    <div class="text-sm font-semibold text-white">{{ selectedTemplate?.name || 'Geen design geselecteerd' }}</div>
                    <div class="mt-1 text-xs text-slate-300">{{ selectedTemplate?.description || 'Kies hieronder welk memberkaart design gebruikt moet worden.' }}</div>
                </div>
            </div>
        </div>

        <div>
            <div class="mb-3 text-sm font-medium text-slate-300">Beschikbare kaartdesigns</div>

            <div v-if="templates.length" class="grid gap-3 md:grid-cols-2 xl:grid-cols-3">
                <button
                    v-for="template in templates"
                    :key="template.id"
                    type="button"
                    class="rounded-2xl border p-4 text-left transition"
                    :class="template.id === form.badge_template_id ? 'border-sky-500 bg-sky-500/10 text-white shadow-lg shadow-sky-900/20' : 'border-slate-800 bg-slate-950/70 text-slate-300 hover:border-slate-600 hover:bg-slate-900'"
                    @click="update('badge_template_id', template.id)"
                >
                    <div class="flex items-start justify-between gap-3">
                        <div>
                            <div class="font-semibold">{{ template.name }}</div>
                            <div class="mt-1 text-xs text-slate-400">{{ template.description || 'Geen beschrijving' }}</div>
                        </div>
                        <span v-if="template.is_default" class="inline-flex rounded-full bg-emerald-500/15 px-2.5 py-1 text-[11px] font-semibold text-emerald-300">Standaard</span>
                    </div>
                </button>
            </div>

            <div v-else class="rounded-2xl border border-dashed border-slate-700 bg-slate-950/70 px-4 py-5 text-sm text-slate-400">
                Er zijn nog geen member badge designs beschikbaar.
            </div>
        </div>
    </div>
</template>

<script setup>
import { computed } from 'vue'
import ScanRfidButton from '@/shared/components/scanners/ScanRfidButton.vue'

const props = defineProps({
    form: { type: Object, required: true },
    templates: { type: Array, default: () => [] },
    fieldClass: { type: String, required: true },
})

const emit = defineEmits(['update'])

const selectedTemplate = computed(() => props.templates.find(template => template.id === props.form.badge_template_id) ?? null)

function update(field, value) {
    emit('update', { field, value })
}
</script>
