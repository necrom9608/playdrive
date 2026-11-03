<template>
    <ModalDialog :open="open" :title="isEdit ? 'Groep bewerken' : 'Groep toevoegen'" description="Toestellen in een groep draaien samen hetzelfde spel en wisselen samen." @close="$emit('close')">
        <div v-if="error" class="mb-4 rounded-2xl border border-red-800 bg-red-950/40 px-4 py-3 text-sm text-red-300">{{ error }}</div>

        <div class="grid gap-4 md:grid-cols-3">
            <label class="space-y-1 md:col-span-3">
                <span class="block text-sm font-medium text-slate-300">Naam *</span>
                <input v-model="form.name" type="text" maxlength="100" placeholder="bijv. Racers, Vliegtuigen" class="input">
            </label>

            <label class="space-y-1">
                <span class="block text-sm font-medium text-slate-300">Wisselen na</span>
                <div class="flex items-center gap-2">
                    <input v-model.number="form.rotation_minutes" type="number" :min="limits.rotation_minutes[0]" :max="limits.rotation_minutes[1]" class="input w-24">
                    <span class="text-sm text-slate-400">min</span>
                </div>
            </label>
            <label class="space-y-1">
                <span class="block text-sm font-medium text-slate-300">Wachten op stilte</span>
                <div class="flex items-center gap-2">
                    <input v-model.number="form.quiet_seconds" type="number" :min="limits.quiet_seconds[0]" :max="limits.quiet_seconds[1]" class="input w-24">
                    <span class="text-sm text-slate-400">s</span>
                </div>
            </label>
            <label class="space-y-1">
                <span class="block text-sm font-medium text-slate-300">Ten laatste na</span>
                <div class="flex items-center gap-2">
                    <span class="text-sm text-slate-400">+</span>
                    <input v-model.number="form.grace_minutes" type="number" :min="limits.grace_minutes[0]" :max="limits.grace_minutes[1]" class="input w-24">
                    <span class="text-sm text-slate-400">min</span>
                </div>
            </label>
            <p class="-mt-2 text-xs text-slate-500 md:col-span-3">
                Na {{ form.rotation_minutes || '…' }} min wisselt de groep zodra op geen enkel toestel nog iemand iets doet
                ({{ form.quiet_seconds || '…' }} s stil, bv. na een race), en ten laatste {{ form.grace_minutes ?? '…' }} min later.
            </p>

            <!-- Toestellen -->
            <div class="space-y-2 md:col-span-3">
                <span class="block text-sm font-medium text-slate-300">Toestellen</span>
                <div v-if="!devices.length" class="rounded-xl border border-dashed border-slate-800 px-4 py-3 text-sm text-slate-500">
                    Nog geen Playscope-toestellen gekoppeld.
                </div>
                <div v-else class="grid gap-2 sm:grid-cols-2">
                    <label
                        v-for="d in devices"
                        :key="d.id"
                        class="flex cursor-pointer items-center gap-3 rounded-xl border px-3 py-2 text-sm transition"
                        :class="form.device_ids.includes(d.id) ? 'border-sky-500/50 bg-sky-500/10 text-white' : 'border-slate-700 bg-slate-950 text-slate-300 hover:bg-slate-800'"
                    >
                        <input v-model="form.device_ids" type="checkbox" :value="d.id" class="accent-sky-500">
                        <span class="h-2 w-2 shrink-0 rounded-full" :class="d.online ? 'bg-emerald-400' : 'bg-slate-600'" />
                        <span class="min-w-0 flex-1 truncate">{{ d.name || `Toestel #${d.id}` }}</span>
                        <span v-if="otherGroup(d)" class="shrink-0 text-xs text-amber-300">in {{ otherGroup(d) }}</span>
                    </label>
                </div>
            </div>

            <!-- Afspeellijst -->
            <div class="space-y-2 md:col-span-3">
                <div class="flex items-baseline justify-between gap-2">
                    <span class="block text-sm font-medium text-slate-300">Afspeellijst <span class="text-slate-500">({{ form.game_ids.length }})</span></span>
                    <span class="text-xs text-slate-500">{{ form.device_ids.length ? 'Enkel games die op alle gekozen toestellen staan' : 'Kies eerst de toestellen' }}</span>
                </div>

                <ol v-if="form.game_ids.length" class="divide-y divide-slate-800 rounded-2xl border border-slate-800">
                    <li v-for="(id, i) in form.game_ids" :key="id" class="flex items-center gap-2 px-3 py-2 text-sm">
                        <span class="w-6 text-right text-slate-500">{{ i + 1 }}.</span>
                        <span class="min-w-0 flex-1 truncate text-slate-200">{{ gameName(id) }}</span>
                        <span v-if="!available.has(id)" class="shrink-0 text-xs text-amber-300" title="Staat niet op alle gekozen toestellen">ontbreekt</span>
                        <span class="shrink-0 text-xs text-slate-500">{{ gameSource(id) }}</span>
                        <button type="button" class="icon-btn" :disabled="i === 0" aria-label="Omhoog" @click="moveGame(i, -1)"><ChevronUpIcon class="h-4 w-4" /></button>
                        <button type="button" class="icon-btn" :disabled="i === form.game_ids.length - 1" aria-label="Omlaag" @click="moveGame(i, 1)"><ChevronDownIcon class="h-4 w-4" /></button>
                        <button type="button" class="icon-btn hover:text-rose-300" aria-label="Verwijderen" @click="form.game_ids.splice(i, 1)"><XMarkIcon class="h-4 w-4" /></button>
                    </li>
                </ol>

                <div v-if="addable.length" class="flex flex-wrap gap-2 pt-1">
                    <button
                        v-for="g in addable"
                        :key="g.id"
                        type="button"
                        class="inline-flex items-center gap-1 rounded-lg border border-slate-700 bg-slate-950 px-2.5 py-1 text-sm text-slate-300 transition hover:bg-slate-800 hover:text-white"
                        @click="form.game_ids.push(g.id)"
                    >
                        <PlusIcon class="h-3.5 w-3.5" /> {{ g.name }}
                    </button>
                </div>
                <p v-else-if="form.device_ids.length && !form.game_ids.length" class="text-xs text-slate-500">
                    Geen gemeenschappelijke games. Games verschijnen hier zodra Playscope ze op de toestellen meldt.
                </p>
            </div>
        </div>

        <div class="mt-6 flex flex-wrap gap-3">
            <button type="button" :disabled="saving || !form.name" class="rounded-xl bg-blue-600 px-4 py-3 text-sm font-semibold text-white transition hover:bg-blue-500 disabled:opacity-60" @click="submit">
                {{ saving ? 'Opslaan...' : 'Opslaan' }}
            </button>
            <button type="button" class="rounded-xl border border-slate-700 px-4 py-3 text-sm font-medium text-slate-300 transition hover:bg-slate-800" @click="$emit('close')">Annuleren</button>
            <button v-if="isEdit" type="button" class="ml-auto rounded-xl border border-rose-500/30 bg-rose-500/10 px-4 py-3 text-sm font-semibold text-rose-200 transition hover:bg-rose-500/20" @click="$emit('delete')">
                Verwijderen
            </button>
        </div>
    </ModalDialog>
</template>

<script setup>
import { computed, reactive, watch } from 'vue'
import { ChevronDownIcon, ChevronUpIcon, PlusIcon, XMarkIcon } from '@heroicons/vue/24/outline'
import ModalDialog from '../../../components/ModalDialog.vue'

const props = defineProps({
    open: { type: Boolean, default: false },
    saving: { type: Boolean, default: false },
    error: { type: String, default: '' },
    group: { type: Object, default: null },
    groups: { type: Array, default: () => [] },
    devices: { type: Array, default: () => [] },
    games: { type: Array, default: () => [] },
    limits: {
        type: Object,
        default: () => ({ rotation_minutes: [5, 240], quiet_seconds: [5, 300], grace_minutes: [0, 60] }),
    },
})
const emit = defineEmits(['close', 'submit', 'delete'])

const isEdit = computed(() => Boolean(props.group?.id))

const form = reactive({
    name: '',
    rotation_minutes: 30,
    quiet_seconds: 20,
    grace_minutes: 5,
    device_ids: [],
    game_ids: [],
})

watch(() => props.open, (open) => {
    if (!open) return
    const g = props.group
    form.name = g?.name ?? ''
    form.rotation_minutes = g?.rotation_minutes ?? 30
    form.quiet_seconds = g?.quiet_seconds ?? 20
    form.grace_minutes = g?.grace_minutes ?? 5
    form.device_ids = [...(g?.device_ids ?? [])]
    form.game_ids = [...(g?.game_ids ?? [])]
})

const gamesById = computed(() => new Map(props.games.map((g) => [g.id, g])))
const gameName = (id) => gamesById.value.get(id)?.name ?? `Game #${id}`
const gameSource = (id) => gamesById.value.get(id)?.source_label ?? ''

// Games die op ALLE gekozen toestellen staan.
const available = computed(() => {
    const chosen = props.devices.filter((d) => form.device_ids.includes(d.id))
    if (!chosen.length) return new Set()
    let set = new Set(chosen[0].game_ids)
    for (const d of chosen.slice(1)) set = new Set(d.game_ids.filter((id) => set.has(id)))
    return set
})

const addable = computed(() =>
    props.games
        .filter((g) => available.value.has(g.id) && !form.game_ids.includes(g.id))
        .sort((a, b) => a.name.localeCompare(b.name, 'nl')),
)

function otherGroup(d) {
    if (!d.device_group_id || d.device_group_id === props.group?.id) return ''
    return props.groups.find((g) => g.id === d.device_group_id)?.name ?? 'andere groep'
}

function moveGame(i, delta) {
    const list = form.game_ids
    const j = i + delta
    if (j < 0 || j >= list.length) return
    ;[list[i], list[j]] = [list[j], list[i]]
}

function submit() {
    emit('submit', {
        name: form.name.trim(),
        rotation_minutes: Number(form.rotation_minutes) || 30,
        quiet_seconds: Number(form.quiet_seconds) || 20,
        grace_minutes: Number.isFinite(Number(form.grace_minutes)) ? Number(form.grace_minutes) : 5,
        device_ids: [...form.device_ids],
        game_ids: [...form.game_ids],
    })
}
</script>

<style scoped>
.input {
    width: 100%;
    border-radius: 0.75rem;
    border: 1px solid rgb(51 65 85);
    background: rgb(2 6 23);
    padding: 0.6rem 0.9rem;
    font-size: 0.875rem;
    color: white;
    outline: none;
}
.input.w-24 {
    width: 6rem;
}
.input:focus {
    border-color: rgb(14 165 233);
}
.icon-btn {
    display: inline-flex;
    height: 1.75rem;
    width: 1.75rem;
    align-items: center;
    justify-content: center;
    border-radius: 0.5rem;
    color: rgb(148 163 184);
}
.icon-btn:hover:not(:disabled) {
    background: rgb(30 41 59);
    color: white;
}
.icon-btn:disabled {
    opacity: 0.3;
}
</style>
