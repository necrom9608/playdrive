<template>
    <div
        v-if="open"
        class="fixed inset-0 z-[100] flex items-center justify-center bg-black/70 p-6 backdrop-blur-sm"
        @click.self="$emit('close')"
    >
        <div class="flex max-h-[90vh] w-full max-w-2xl flex-col overflow-hidden rounded-3xl border border-slate-800 bg-slate-900 shadow-2xl">
            <!-- Header -->
            <div class="flex items-start justify-between border-b border-slate-800 px-6 py-5">
                <div>
                    <h2 class="text-xl font-semibold text-slate-100">Membership bewerken</h2>
                    <p class="mt-0.5 text-sm text-slate-400">{{ member?.full_name }} · {{ member?.email }}</p>
                </div>
                <button type="button" class="inline-flex h-9 w-9 items-center justify-center rounded-xl text-slate-400 transition hover:bg-slate-800 hover:text-white" @click="$emit('close')">
                    <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12"/></svg>
                </button>
            </div>

            <!-- Body -->
            <div class="min-h-0 overflow-y-auto p-6 space-y-5">

                <!-- Type + actief -->
                <div class="grid gap-5 md:grid-cols-2">
                    <label class="space-y-2 text-sm text-slate-300">
                        <span>Type abonnement</span>
                        <select v-model="form.membership_type" :class="fieldClass">
                            <option value="adult">Volwassene</option>
                            <option value="student">Student</option>
                        </select>
                    </label>

                    <label class="flex items-center gap-3 rounded-2xl border border-slate-700 bg-slate-950 px-4 py-3 cursor-pointer">
                        <input type="checkbox" v-model="form.is_active" class="h-4 w-4 rounded border-slate-600 bg-slate-900 text-blue-600">
                        <div>
                            <div class="text-sm font-medium text-slate-200">Actief</div>
                            <div class="text-xs text-slate-500">Lid kan inloggen en bezoeken registreren</div>
                        </div>
                    </label>
                </div>

                <!-- Datums -->
                <div class="grid gap-5 md:grid-cols-2">
                    <label class="space-y-2 text-sm text-slate-300">
                        <span>Startdatum</span>
                        <input v-model="form.membership_starts_at" type="date" :class="fieldClass">
                    </label>
                    <label class="space-y-2 text-sm text-slate-300">
                        <span>Einddatum</span>
                        <input v-model="form.membership_ends_at" type="date" :class="fieldClass">
                    </label>
                </div>

                <!-- RFID -->
                <div class="space-y-2 text-sm text-slate-300">
                    <span>RFID kaart</span>
                    <div class="flex gap-3">
                        <input v-model="form.rfid_uid" type="text" placeholder="Scan of typ UID" :class="fieldClass + ' flex-1'">
                        <ScanRfidButton
                            :model-value="form.rfid_uid"
                            label="Scan"
                            title="RFID-kaart scannen"
                            description="Scan de RFID-kaart voor dit lid."
                            confirm-label="Koppelen"
                            :show-value="false"
                            @update:model-value="form.rfid_uid = $event"
                        />
                    </div>
                </div>

                <!-- Badge template -->
                <div class="space-y-3">
                    <div class="text-sm font-medium text-slate-300">Kaartdesign</div>
                    <div class="text-xs text-slate-500 -mt-1">
                        <span v-if="member?.member_card_id">Huidig: <span class="text-slate-300">{{ member?.member_card_badge_template_name || `Badge #${member?.member_card_id}` }}</span></span>
                        <span v-else>Er is nog geen badge aangemaakt. Bij opslaan wordt er automatisch één aangemaakt.</span>
                    </div>

                    <div v-if="badgeTemplates.length" class="grid gap-3 md:grid-cols-2">
                        <button
                            v-for="template in badgeTemplates"
                            :key="template.id"
                            type="button"
                            class="rounded-2xl border p-4 text-left transition"
                            :class="template.id === form.badge_template_id
                                ? 'border-sky-500 bg-sky-500/10 text-white shadow-lg shadow-sky-900/20'
                                : 'border-slate-800 bg-slate-950/70 text-slate-300 hover:border-slate-600 hover:bg-slate-900'"
                            @click="form.badge_template_id = template.id"
                        >
                            <div class="flex items-start justify-between gap-2">
                                <div>
                                    <div class="font-semibold text-sm">{{ template.name }}</div>
                                    <div class="mt-0.5 text-xs text-slate-400">{{ template.description || 'Geen beschrijving' }}</div>
                                </div>
                                <span v-if="template.is_default" class="shrink-0 inline-flex rounded-full bg-emerald-500/15 px-2.5 py-1 text-[11px] font-semibold text-emerald-300">Standaard</span>
                            </div>
                        </button>
                    </div>
                    <div v-else class="rounded-2xl border border-dashed border-slate-700 bg-slate-950/70 px-4 py-4 text-sm text-slate-400">
                        Geen badge designs beschikbaar.
                    </div>
                </div>

                <!-- Error -->
                <div v-if="error" class="rounded-2xl border border-rose-500/25 bg-rose-500/10 px-4 py-3 text-sm text-rose-200">{{ error }}</div>
            </div>

            <!-- Footer -->
            <div class="flex gap-3 border-t border-slate-800 px-6 py-4">
                <button type="button" class="flex-1 rounded-2xl border border-slate-700 bg-slate-800 px-4 py-3 text-sm font-semibold text-slate-200 transition hover:bg-slate-700" @click="$emit('close')">Annuleren</button>
                <button type="button" :disabled="saving" class="flex-1 rounded-2xl bg-blue-600 px-4 py-3 text-sm font-semibold text-white transition hover:bg-blue-500 disabled:opacity-50 disabled:cursor-not-allowed" @click="submit">
                    <span v-if="saving" class="flex items-center justify-center gap-2">
                        <span class="h-3.5 w-3.5 animate-spin rounded-full border border-white/30 border-t-white"></span> Opslaan…
                    </span>
                    <span v-else>Opslaan</span>
                </button>
            </div>
        </div>
    </div>
</template>

<script setup>
import { reactive, ref, watch } from 'vue'
import ScanRfidButton from '@/shared/components/scanners/ScanRfidButton.vue'

const props = defineProps({
    open: { type: Boolean, default: false },
    member: { type: Object, default: null },
    badgeTemplates: { type: Array, default: () => [] },
})

const emit = defineEmits(['close', 'submit'])

const saving = ref(false)
const error  = ref('')

const fieldClass = 'w-full rounded-2xl border border-slate-700 bg-slate-950 px-4 py-3 text-sm text-slate-100 outline-none transition focus:border-blue-500'

const form = reactive({
    id: null,
    membership_type: 'adult',
    is_active: true,
    membership_starts_at: '',
    membership_ends_at: '',
    rfid_uid: '',
    badge_template_id: null,
})

watch(() => props.open, (val) => {
    if (!val) return
    error.value = ''
    const m = props.member
    form.id                    = m?.id ?? null
    form.membership_type       = m?.membership_type ?? 'adult'
    form.is_active             = m?.is_active ?? true
    form.membership_starts_at  = m?.membership_starts_at ?? ''
    form.membership_ends_at    = m?.membership_ends_at ?? ''
    form.rfid_uid              = m?.rfid_uid ?? ''
    form.badge_template_id     = m?.member_badge_template_id ?? props.badgeTemplates.find(t => t.is_default)?.id ?? null
})

async function submit() {
    error.value = ''
    saving.value = true
    try {
        await emit('submit', { ...form })
    } catch (err) {
        error.value = err?.response?.data?.message ?? 'Opslaan mislukt.'
    } finally {
        saving.value = false
    }
}
</script>
