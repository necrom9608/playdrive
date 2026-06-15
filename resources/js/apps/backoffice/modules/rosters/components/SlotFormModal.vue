<template>
    <teleport to="body">
        <div v-if="open" class="fixed inset-0 z-50 flex items-center justify-center p-4">
            <div class="absolute inset-0 bg-slate-950/80" @click="$emit('close')" />

            <div class="relative z-10 max-h-[90vh] w-full max-w-lg overflow-hidden rounded-3xl border border-slate-800 bg-slate-900 shadow-2xl">
                <div class="flex items-center justify-between border-b border-slate-800 px-6 py-4">
                    <div>
                        <h2 class="text-lg font-semibold text-white">{{ isEdit ? 'Slot bewerken' : 'Slot toevoegen' }}</h2>
                        <p class="mt-1 text-sm text-slate-400">{{ contextLabel }}</p>
                    </div>
                    <button type="button" class="inline-flex h-10 w-10 items-center justify-center rounded-xl border border-slate-700 text-slate-300 transition hover:bg-slate-800 hover:text-white" @click="$emit('close')">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z" clip-rule="evenodd" /></svg>
                    </button>
                </div>

                <div class="max-h-[calc(90vh-80px)] overflow-y-auto p-6">
                    <div v-if="error" class="mb-4 rounded-2xl border border-red-800 bg-red-950/40 px-4 py-3 text-sm text-red-300">{{ error }}</div>

                    <div class="space-y-4">
                        <div class="grid gap-4 sm:grid-cols-2">
                            <div>
                                <label class="mb-1 block text-sm font-medium text-slate-300">Van *</label>
                                <input v-model="form.starts_at" type="time" class="w-full rounded-xl border border-slate-700 bg-slate-950 px-4 py-3 text-sm text-white outline-none transition focus:border-sky-500" />
                            </div>
                            <div>
                                <label class="mb-1 block text-sm font-medium text-slate-300">Tot *</label>
                                <input v-model="form.ends_at" type="time" class="w-full rounded-xl border border-slate-700 bg-slate-950 px-4 py-3 text-sm text-white outline-none transition focus:border-sky-500" />
                            </div>
                        </div>

                        <div class="grid gap-4 sm:grid-cols-2">
                            <div>
                                <label class="mb-1 block text-sm font-medium text-slate-300">Rol</label>
                                <select v-model="form.role_id" class="w-full rounded-xl border border-slate-700 bg-slate-950 px-4 py-3 text-sm text-white outline-none focus:border-sky-500">
                                    <option :value="null">— geen rol —</option>
                                    <option v-for="r in activeRoles" :key="r.id" :value="r.id">{{ r.name }}</option>
                                </select>
                            </div>
                            <div>
                                <label class="mb-1 block text-sm font-medium text-slate-300">Gewenst aantal</label>
                                <input v-model.number="form.desired_count" type="number" min="1" max="99" placeholder="optioneel" class="w-full rounded-xl border border-slate-700 bg-slate-950 px-4 py-3 text-sm text-white outline-none transition focus:border-sky-500" />
                            </div>
                        </div>

                        <div>
                            <label class="mb-1 block text-sm font-medium text-slate-300">Staand commentaar</label>
                            <input v-model="form.comment" type="text" placeholder="komt elke week mee" class="w-full rounded-xl border border-slate-700 bg-slate-950 px-4 py-3 text-sm text-white outline-none transition focus:border-sky-500" />
                        </div>

                        <div>
                            <label class="mb-2 block text-sm font-medium text-slate-300">Standaard-invullers</label>
                            <p v-if="staff.length === 0" class="text-xs text-slate-500">Nog geen medewerkers.</p>
                            <div v-else class="max-h-40 space-y-1 overflow-y-auto rounded-xl border border-slate-800 bg-slate-950/60 p-2">
                                <label v-for="s in staff" :key="s.id" class="flex items-center gap-2 rounded-lg px-2 py-1 hover:bg-slate-800/60">
                                    <input type="checkbox" :value="s.id" v-model="form.default_user_ids" class="h-4 w-4 rounded border-slate-600 bg-slate-950" />
                                    <span class="text-sm text-slate-200">{{ s.name }}</span>
                                </label>
                            </div>
                            <p class="mt-1 text-xs text-slate-500">Worden bij genereren automatisch toegewezen (per week aanpasbaar).</p>
                        </div>

                        <p v-if="overnight" class="text-xs text-amber-300">Eindtijd ligt vóór de starttijd — loopt over middernacht.</p>

                        <div class="flex flex-wrap items-center gap-3 pt-2">
                            <button type="button" :disabled="saving || !valid" class="rounded-xl bg-blue-600 px-4 py-3 text-sm font-semibold text-white transition hover:bg-blue-500 disabled:opacity-60" @click="submitForm">
                                {{ saving ? 'Opslaan...' : 'Opslaan' }}
                            </button>
                            <button type="button" class="rounded-xl border border-slate-700 px-4 py-3 text-sm font-medium text-slate-300 transition hover:bg-slate-800" @click="$emit('close')">Annuleren</button>

                            <button v-if="isEdit" type="button" class="ml-auto rounded-xl border border-rose-500/30 bg-rose-500/10 px-4 py-3 text-sm font-semibold text-rose-200 transition hover:bg-rose-500/20" @click="$emit('delete')">Verwijderen</button>
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
    slot:        { type: Object, default: null },
    roles:       { type: Array, default: () => [] },
    staff:       { type: Array, default: () => [] },
    weekdayLabel:{ type: String, default: '' },
    seasonLabel: { type: String, default: '' },
})

const emit = defineEmits(['close', 'submit', 'delete'])

const form = reactive({
    starts_at: '09:00',
    ends_at:   '17:00',
    role_id:   null,
    desired_count: null,
    comment:   '',
    default_user_ids: [],
})

const isEdit = computed(() => !!props.slot?.id)
const valid = computed(() => !!form.starts_at && !!form.ends_at)
const overnight = computed(() => valid.value && form.ends_at <= form.starts_at)

// Toon actieve rollen + (bij bewerken) de eventueel inactieve huidige rol.
const activeRoles = computed(() => {
    const active = props.roles.filter(r => r.is_active)
    const current = props.slot?.role_id
    if (current && !active.some(r => r.id === current)) {
        const found = props.roles.find(r => r.id === current)
        if (found) return [found, ...active]
    }
    return active
})

const contextLabel = computed(() => [props.weekdayLabel, props.seasonLabel].filter(Boolean).join(' · '))

watch(() => props.open, (open) => {
    if (open) {
        form.starts_at = props.slot?.starts_at ?? '09:00'
        form.ends_at   = props.slot?.ends_at ?? '17:00'
        form.role_id   = props.slot?.role_id ?? null
        form.desired_count = props.slot?.desired_count ?? null
        form.comment   = props.slot?.comment ?? ''
        form.default_user_ids = Array.isArray(props.slot?.default_user_ids) ? [...props.slot.default_user_ids] : []
    }
})

function submitForm() {
    if (!valid.value) return
    emit('submit', {
        starts_at: form.starts_at,
        ends_at:   form.ends_at,
        role_id:   form.role_id || null,
        desired_count: form.desired_count || null,
        comment:   form.comment || null,
        default_user_ids: form.default_user_ids,
    })
}
</script>
