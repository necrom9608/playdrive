<template>
    <ModalDialog :open="open" :title="isEdit ? 'Speelplek bewerken' : 'Speelplek toevoegen'" @close="$emit('close')">
        <div v-if="error" class="mb-4 rounded-2xl border border-red-800 bg-red-950/40 px-4 py-3 text-sm text-red-300">{{ error }}</div>

        <div class="grid gap-4 md:grid-cols-2">
            <label class="space-y-1 md:col-span-2">
                <span class="block text-sm font-medium text-slate-300">Naam *</span>
                <input v-model="form.name" type="text" placeholder="bijv. Daytona USA, LAN-PC 01, Couch 1" class="input" />
            </label>

            <label class="space-y-1">
                <span class="block text-sm font-medium text-slate-300">Type *</span>
                <select v-model="form.type" class="input">
                    <option v-for="t in types" :key="t.key" :value="t.key">{{ t.label }}</option>
                </select>
            </label>

            <label class="space-y-1">
                <span class="block text-sm font-medium text-slate-300">Zone</span>
                <input v-model="form.zone" type="text" list="setup-zones" placeholder="bijv. LAN-room, Arcadehal" class="input" />
                <datalist id="setup-zones">
                    <option v-for="z in zones" :key="z" :value="z" />
                </datalist>
            </label>

            <label class="space-y-1">
                <span class="block text-sm font-medium text-slate-300">Max. spelers</span>
                <input v-model.number="form.max_players" type="number" min="1" max="64" placeholder="bijv. 2" class="input" />
            </label>

            <label class="space-y-1">
                <span class="block text-sm font-medium text-slate-300">Status</span>
                <select v-model="form.status" class="input">
                    <option v-for="s in statuses" :key="s.key" :value="s.key">{{ s.label }}</option>
                </select>
            </label>

            <div class="space-y-1 md:col-span-2">
                <span class="block text-sm font-medium text-slate-300">Spellen</span>
                <div class="flex gap-2">
                    <input
                        v-model="gameInput"
                        type="text"
                        placeholder="Spel toevoegen en Enter drukken"
                        class="input"
                        @keydown.enter.prevent="addGame"
                    />
                    <button type="button" class="shrink-0 rounded-xl border border-slate-700 px-4 text-sm text-slate-200 transition hover:bg-slate-800" @click="addGame">Toevoegen</button>
                </div>
                <p class="text-xs text-slate-500">Bij Playscope-PC's vult de lijst zich later automatisch aan.</p>
                <div v-if="form.games.length" class="flex flex-wrap gap-2 pt-2">
                    <span v-for="(g, i) in form.games" :key="g" class="inline-flex items-center gap-1 rounded-lg border border-slate-700 bg-slate-950 py-1 pl-3 pr-1 text-sm text-slate-200">
                        {{ g }}
                        <button type="button" class="rounded p-1 text-slate-500 hover:text-white" :aria-label="`${g} verwijderen`" @click="form.games.splice(i, 1)">
                            <XMarkIcon class="h-4 w-4" />
                        </button>
                    </span>
                </div>
            </div>

            <label class="space-y-1 md:col-span-2">
                <span class="block text-sm font-medium text-slate-300">Omschrijving</span>
                <textarea v-model="form.description" rows="3" placeholder="Zichtbaar voor klanten die de QR-code scannen" class="input" />
            </label>
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
import { computed, reactive, ref, watch } from 'vue'
import { XMarkIcon } from '@heroicons/vue/24/outline'
import ModalDialog from '../../../components/ModalDialog.vue'

const props = defineProps({
    open: { type: Boolean, default: false },
    saving: { type: Boolean, default: false },
    error: { type: String, default: '' },
    setup: { type: Object, default: null },
    types: { type: Array, default: () => [] },
    statuses: { type: Array, default: () => [] },
    zones: { type: Array, default: () => [] },
})

const emit = defineEmits(['close', 'submit', 'delete'])

const form = reactive({ name: '', type: 'arcade', zone: '', max_players: null, status: 'active', games: [], description: '' })
const gameInput = ref('')

const isEdit = computed(() => !!props.setup?.id)

watch(() => props.open, (open) => {
    if (!open) return
    form.name = props.setup?.name ?? ''
    form.type = props.setup?.type ?? 'arcade'
    form.zone = props.setup?.zone ?? ''
    form.max_players = props.setup?.max_players ?? null
    form.status = props.setup?.status ?? 'active'
    form.games = [...(props.setup?.games ?? [])]
    form.description = props.setup?.description ?? ''
    gameInput.value = ''
})

function addGame() {
    const value = gameInput.value.trim()
    if (value && !form.games.includes(value)) {
        form.games.push(value)
    }
    gameInput.value = ''
}

function submit() {
    if (!form.name) return
    addGame()
    emit('submit', {
        name: form.name,
        type: form.type,
        zone: form.zone || null,
        max_players: form.max_players || null,
        status: form.status,
        games: form.games,
        description: form.description || null,
    })
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
</style>
