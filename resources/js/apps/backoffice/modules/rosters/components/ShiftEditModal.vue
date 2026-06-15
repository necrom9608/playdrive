<template>
    <teleport to="body">
        <div v-if="open" class="fixed inset-0 z-50 flex items-center justify-center p-4">
            <div class="absolute inset-0 bg-slate-950/80" @click="$emit('close')" />

            <div class="relative z-10 max-h-[90vh] w-full max-w-lg overflow-hidden rounded-3xl border border-slate-800 bg-slate-900 shadow-2xl">
                <div class="flex items-center justify-between border-b border-slate-800 px-6 py-4">
                    <div>
                        <h2 class="text-lg font-semibold text-white">{{ isEdit ? 'Shift' : 'Shift toevoegen' }}</h2>
                        <p class="mt-1 text-sm text-slate-400">{{ dateLabel }}</p>
                    </div>
                    <button type="button" class="inline-flex h-10 w-10 items-center justify-center rounded-xl border border-slate-700 text-slate-300 transition hover:bg-slate-800 hover:text-white" @click="$emit('close')">
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z" clip-rule="evenodd" /></svg>
                    </button>
                </div>

                <div class="max-h-[calc(90vh-80px)] overflow-y-auto p-6">
                    <div v-if="error" class="mb-4 rounded-2xl border border-red-800 bg-red-950/40 px-4 py-3 text-sm text-red-300">{{ error }}</div>

                    <!-- Toewijzingen (enkel bij bestaande shift) -->
                    <div v-if="isEdit" class="mb-6">
                        <div class="mb-2 flex items-center justify-between">
                            <label class="text-sm font-medium text-slate-300">Ingevuld</label>
                            <span class="text-xs font-semibold" :class="fillClass">{{ fillLabel }}</span>
                        </div>

                        <div v-if="(shift.assignments || []).length" class="mb-2 flex flex-wrap gap-2">
                            <span v-for="a in shift.assignments" :key="a.id" class="inline-flex items-center gap-1.5 rounded-full bg-slate-800 px-3 py-1 text-sm text-slate-100">
                                {{ a.name }}
                                <button type="button" class="text-slate-400 transition hover:text-rose-300" :disabled="assignmentBusy" @click="$emit('remove-assignment', a.id)">✕</button>
                            </span>
                        </div>
                        <p v-else class="mb-2 text-xs text-slate-500">Nog niemand toegewezen.</p>

                        <div class="flex gap-2">
                            <select v-model="addUserId" class="flex-1 rounded-xl border border-slate-700 bg-slate-950 px-3 py-2.5 text-sm text-white outline-none focus:border-sky-500">
                                <option :value="null">Persoon toevoegen…</option>
                                <option v-for="s in availableStaff" :key="s.id" :value="s.id">{{ s.name }}</option>
                            </select>
                            <button type="button" :disabled="!addUserId || assignmentBusy" class="rounded-xl bg-slate-700 px-4 py-2.5 text-sm font-semibold text-white transition hover:bg-slate-600 disabled:opacity-50" @click="doAdd">Toevoegen</button>
                        </div>
                    </div>

                    <!-- Blokvelden -->
                    <div class="space-y-4 border-t border-slate-800 pt-5">
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
                            <label class="mb-1 block text-sm font-medium text-slate-300">Dagnotitie</label>
                            <input v-model="form.note" type="text" placeholder="eenmalig, enkel voor deze dag" class="w-full rounded-xl border border-slate-700 bg-slate-950 px-4 py-3 text-sm text-white outline-none transition focus:border-sky-500" />
                        </div>

                        <p v-if="overnight" class="text-xs text-amber-300">Eindtijd ligt vóór de starttijd — loopt over middernacht.</p>

                        <div class="flex flex-wrap items-center gap-3 pt-2">
                            <button type="button" :disabled="saving || !valid" class="rounded-xl bg-blue-600 px-4 py-3 text-sm font-semibold text-white transition hover:bg-blue-500 disabled:opacity-60" @click="submitForm">
                                {{ saving ? 'Opslaan...' : 'Opslaan' }}
                            </button>
                            <button type="button" class="rounded-xl border border-slate-700 px-4 py-3 text-sm font-medium text-slate-300 transition hover:bg-slate-800" @click="$emit('close')">Sluiten</button>

                            <button v-if="isEdit" type="button" class="ml-auto rounded-xl border border-rose-500/30 bg-rose-500/10 px-4 py-3 text-sm font-semibold text-rose-200 transition hover:bg-rose-500/20" @click="$emit('delete')">Blok verwijderen</button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </teleport>
</template>

<script setup>
import { computed, reactive, ref, watch } from 'vue'

const props = defineProps({
    open:          { type: Boolean, default: false },
    saving:        { type: Boolean, default: false },
    assignmentBusy:{ type: Boolean, default: false },
    error:         { type: String, default: '' },
    shift:         { type: Object, default: null },
    roles:         { type: Array, default: () => [] },
    staff:         { type: Array, default: () => [] },
    dateLabel:     { type: String, default: '' },
})

const emit = defineEmits(['close', 'submit', 'delete', 'add-assignment', 'remove-assignment'])

const form = reactive({
    starts_at: '09:00',
    ends_at:   '17:00',
    role_id:   null,
    desired_count: null,
    note:      '',
})

const addUserId = ref(null)

const isEdit = computed(() => !!props.shift?.id)
const valid = computed(() => !!form.starts_at && !!form.ends_at)
const overnight = computed(() => valid.value && form.ends_at <= form.starts_at)

const activeRoles = computed(() => {
    const active = props.roles.filter(r => r.is_active)
    const current = props.shift?.role_id
    if (current && !active.some(r => r.id === current)) {
        const found = props.roles.find(r => r.id === current)
        if (found) return [found, ...active]
    }
    return active
})

const assignedIds = computed(() => (props.shift?.assignments || []).map(a => a.user_id))
const availableStaff = computed(() => props.staff.filter(s => !assignedIds.value.includes(s.id)))

const fillLabel = computed(() => {
    const filled = (props.shift?.assignments || []).length
    const desired = props.shift?.desired_count
    return desired ? `${filled} van ${desired}` : `${filled} ingevuld`
})

const fillClass = computed(() => {
    const filled = (props.shift?.assignments || []).length
    const desired = props.shift?.desired_count
    if (!desired) return 'text-slate-400'
    if (filled < desired) return 'text-amber-300'
    if (filled > desired) return 'text-violet-300'
    return 'text-emerald-300'
})

watch(() => props.open, (open) => {
    if (open) {
        form.starts_at = props.shift?.starts_at ?? '09:00'
        form.ends_at   = props.shift?.ends_at ?? '17:00'
        form.role_id   = props.shift?.role_id ?? null
        form.desired_count = props.shift?.desired_count ?? null
        form.note      = props.shift?.note ?? ''
        addUserId.value = null
    }
})

function doAdd() {
    if (!addUserId.value) return
    emit('add-assignment', addUserId.value)
    addUserId.value = null
}

function submitForm() {
    if (!valid.value) return
    emit('submit', {
        starts_at: form.starts_at,
        ends_at:   form.ends_at,
        role_id:   form.role_id || null,
        desired_count: form.desired_count || null,
        note:      form.note || null,
    })
}
</script>
