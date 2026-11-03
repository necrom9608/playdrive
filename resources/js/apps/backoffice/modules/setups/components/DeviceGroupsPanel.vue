<template>
    <div class="space-y-4">
        <div v-if="error" class="rounded-2xl border border-red-500/30 bg-red-500/10 px-5 py-4 text-sm text-red-200">{{ error }}</div>

        <div v-if="!groups.length && loaded" class="rounded-3xl border border-dashed border-slate-800 px-6 py-12 text-center">
            <ArrowPathRoundedSquareIcon class="mx-auto h-10 w-10 text-slate-600" />
            <p class="mt-3 text-slate-300">Nog geen groepen.</p>
            <p class="mt-1 text-sm text-slate-500">
                Zet Playscope-toestellen (bv. de racers) in een groep: ze draaien samen hetzelfde spel en wisselen automatisch na de ingestelde tijd.
            </p>
        </div>

        <div class="grid gap-4 xl:grid-cols-2">
            <article v-for="g in groups" :key="g.id" class="flex flex-col rounded-3xl border border-slate-800 bg-slate-900/80 p-5 shadow-xl shadow-slate-950/20">
                <div class="flex items-start gap-4">
                    <div class="flex h-12 w-12 shrink-0 items-center justify-center rounded-2xl border border-slate-700 bg-slate-950">
                        <ArrowPathRoundedSquareIcon class="h-6 w-6 text-sky-300" />
                    </div>
                    <div class="min-w-0 flex-1">
                        <h3 class="truncate text-lg font-semibold text-white">{{ g.name }}</h3>
                        <div class="mt-1 flex flex-wrap items-center gap-2 text-xs">
                            <span class="rounded-full border px-2 py-0.5 font-semibold" :class="stateClass(g.status.state)">{{ stateLabel(g.status.state) }}</span>
                            <span class="text-slate-400">elke {{ g.rotation_minutes }} min · {{ g.members.length }} toestel{{ g.members.length === 1 ? '' : 'len' }} · {{ g.game_ids.length }} games</span>
                        </div>
                    </div>
                </div>

                <!-- Nu -->
                <div v-if="g.status.game" class="mt-4 rounded-2xl border border-slate-800 bg-slate-950/60 px-4 py-3">
                    <div class="text-xs uppercase tracking-wider text-slate-500">Nu</div>
                    <div class="mt-0.5 truncate text-lg font-semibold text-white">{{ g.status.game.name }}</div>
                    <div class="mt-1 text-sm text-slate-400">
                        <template v-if="g.status.state === 'paused'">Gepauzeerd: blijft staan tot je hervat.</template>
                        <template v-else-if="g.status.state === 'waiting'">
                            Wacht tot niemand nog speelt · ten laatste om {{ clock(g.status.switch_by) }}
                        </template>
                        <template v-else-if="g.status.due_at">
                            Wisselt over {{ remaining(g.status.due_at) }} <span class="text-slate-500">(of later, als er nog gespeeld wordt)</span>
                        </template>
                    </div>
                    <div v-if="g.status.next" class="mt-1 text-sm text-slate-500">Daarna: {{ g.status.next.name }}</div>
                </div>
                <p v-else class="mt-4 text-sm text-slate-500">
                    {{ g.game_ids.length ? 'Start bij de volgende melding van een toestel.' : 'Nog geen games in de afspeellijst.' }}
                </p>

                <!-- Toestellen -->
                <ul v-if="g.members.length" class="mt-4 grid gap-1.5 sm:grid-cols-2">
                    <li v-for="m in g.members" :key="m.id" class="flex items-center gap-2 rounded-xl border border-slate-800 px-3 py-1.5 text-sm">
                        <span class="h-2 w-2 shrink-0 rounded-full" :class="m.online ? 'bg-emerald-400' : 'bg-slate-600'" />
                        <span class="min-w-0 flex-1 truncate text-slate-200">{{ m.name || `Toestel #${m.id}` }}</span>
                        <span class="shrink-0 text-xs" :class="memberClass(g, m)">{{ memberLabel(g, m) }}</span>
                    </li>
                </ul>

                <p v-if="g.screens?.length" class="mt-3 flex flex-wrap items-center gap-x-3 gap-y-1 text-xs text-slate-400">
                    <span>Scherm:</span>
                    <span v-for="s in g.screens" :key="s.id" class="inline-flex items-center gap-1.5">
                        <span class="h-1.5 w-1.5 rounded-full" :class="s.online ? 'bg-emerald-400' : 'bg-slate-600'" />{{ s.name || `Scherm #${s.id}` }}
                    </span>
                </p>

                <div class="mt-auto flex flex-wrap gap-2 pt-5">
                    <button type="button" :disabled="busy === g.id || !g.game_ids.length" class="btn" @click="next(g)">
                        <ForwardIcon class="h-4 w-4" /> Nu wisselen
                    </button>
                    <select v-if="g.game_ids.length > 1" class="btn appearance-none pr-3" :disabled="busy === g.id" @change="jump(g, $event)">
                        <option value="">Wissel naar…</option>
                        <option v-for="id in g.game_ids" :key="id" :value="id">{{ gameName(id) }}</option>
                    </select>
                    <button type="button" :disabled="busy === g.id" class="btn" @click="togglePause(g)">
                        <component :is="g.is_paused ? PlayIcon : PauseIcon" class="h-4 w-4" /> {{ g.is_paused ? 'Hervatten' : 'Pauzeren' }}
                    </button>
                    <button type="button" class="btn" @click="openForm(g)">
                        <PencilSquareIcon class="h-4 w-4" /> Bewerken
                    </button>
                </div>
            </article>
        </div>

        <DeviceGroupFormModal
            :open="form.open"
            :saving="form.saving"
            :error="form.error"
            :group="form.group"
            :groups="groups"
            :devices="devices"
            :games="games"
            :limits="limits"
            @close="form.open = false"
            @submit="save"
            @delete="remove"
        />
    </div>
</template>

<script setup>
import { onBeforeUnmount, onMounted, reactive, ref } from 'vue'
import {
    ArrowPathRoundedSquareIcon,
    ForwardIcon,
    PauseIcon,
    PencilSquareIcon,
    PlayIcon,
} from '@heroicons/vue/24/outline'
import DeviceGroupFormModal from './DeviceGroupFormModal.vue'
import {
    createDeviceGroup,
    deleteDeviceGroup,
    fetchDeviceGroups,
    nextDeviceGroupGame,
    pauseDeviceGroup,
    updateDeviceGroup,
} from '../services/setupApi'

const emit = defineEmits(['count'])

const groups = ref([])
const devices = ref([])
const games = ref([])
const limits = ref(undefined)
const loaded = ref(false)
const error = ref('')
const busy = ref(null)
const now = ref(Date.now())
const form = reactive({ open: false, group: null, saving: false, error: '' })
let timer = null
let tick = null

async function load() {
    try {
        const res = await fetchDeviceGroups()
        groups.value = res.groups ?? []
        devices.value = res.devices ?? []
        games.value = res.games ?? []
        limits.value = res.limits ?? undefined
        error.value = ''
        emit('count', groups.value.length)
    } catch (e) {
        error.value = e.message || 'Laden mislukt.'
    } finally {
        loaded.value = true
    }
}

function openForm(group = null) {
    form.group = group
    form.error = ''
    form.open = true
}

async function save(payload) {
    form.saving = true
    form.error = ''
    try {
        if (form.group?.id) await updateDeviceGroup(form.group.id, payload)
        else await createDeviceGroup(payload)
        form.open = false
        await load()
    } catch (e) {
        form.error = e.message || 'Opslaan mislukt.'
    } finally {
        form.saving = false
    }
}

async function remove() {
    const g = form.group
    if (!g?.id) return
    if (!window.confirm(`Groep "${g.name}" verwijderen? De toestellen worden weer gewone Playscope-pc's met een menu.`)) return
    form.saving = true
    try {
        await deleteDeviceGroup(g.id)
        form.open = false
        await load()
    } catch (e) {
        form.error = e.message || 'Verwijderen mislukt.'
    } finally {
        form.saving = false
    }
}

async function act(g, fn) {
    busy.value = g.id
    try {
        await fn()
        await load()
    } catch (e) {
        error.value = e.message || 'Mislukt.'
    } finally {
        busy.value = null
    }
}

function next(g) {
    if (!window.confirm(`"${g.name}" nu wisselen? Lopende games stoppen meteen.`)) return
    act(g, () => nextDeviceGroupGame(g.id))
}

function jump(g, event) {
    const id = Number(event.target.value)
    event.target.value = ''
    if (!id) return
    if (!window.confirm(`"${g.name}" nu wisselen naar ${gameName(id)}? Lopende games stoppen meteen.`)) return
    act(g, () => nextDeviceGroupGame(g.id, id))
}

function togglePause(g) {
    act(g, () => pauseDeviceGroup(g.id, !g.is_paused))
}

const gameName = (id) => games.value.find((x) => x.id === id)?.name ?? `Game #${id}`

function stateLabel(s) {
    return { playing: 'Draait', waiting: 'Wacht op pauze', paused: 'Gepauzeerd', empty: 'Lege lijst' }[s] || s
}
function stateClass(s) {
    return {
        playing: 'border-emerald-500/40 bg-emerald-500/10 text-emerald-200',
        waiting: 'border-sky-500/40 bg-sky-500/10 text-sky-200',
        paused: 'border-amber-500/40 bg-amber-500/10 text-amber-200',
        empty: 'border-slate-700 bg-slate-800 text-slate-300',
    }[s] || 'border-slate-700 bg-slate-800 text-slate-300'
}

// Wat doet een toestel van de groep nu?
function memberLabel(g, m) {
    if (!m.report) return m.online ? 'geen rotatie-melding' : 'offline'
    if (m.report.error) return m.report.error
    if (m.report.phase === 'starting' || m.report.phase === 'switching') return 'start…'
    if (m.report.phase !== 'running') return 'in menu'
    if (m.idle_s !== null && m.idle_s < g.quiet_seconds) return 'wordt gespeeld'
    return m.idle_s !== null ? `stil ${duration(m.idle_s)}` : 'speelt'
}
function memberClass(g, m) {
    if (!m.report) return 'text-slate-500'
    if (m.report.error) return 'text-rose-300'
    if (m.report.phase === 'running' && m.idle_s !== null && m.idle_s < g.quiet_seconds) return 'text-emerald-300'
    return 'text-slate-400'
}

function duration(s) {
    if (s < 60) return `${s} s`
    if (s < 3600) return `${Math.floor(s / 60)} min`
    return `${Math.floor(s / 3600)} u`
}
function remaining(iso) {
    const s = Math.max(0, Math.round((Date.parse(iso) - now.value) / 1000))
    return `${Math.floor(s / 60)}:${String(s % 60).padStart(2, '0')}`
}
function clock(iso) {
    return iso ? new Date(iso).toLocaleTimeString('nl-BE', { hour: '2-digit', minute: '2-digit' }) : ''
}

defineExpose({ openForm, load })

onMounted(() => {
    load()
    timer = window.setInterval(() => !form.open && load(), 5000)
    tick = window.setInterval(() => (now.value = Date.now()), 1000)
})
onBeforeUnmount(() => {
    window.clearInterval(timer)
    window.clearInterval(tick)
})
</script>

<style scoped>
.btn {
    display: inline-flex;
    align-items: center;
    gap: 0.375rem;
    border-radius: 0.75rem;
    border: 1px solid rgb(51 65 85);
    background: rgb(2 6 23);
    padding: 0.5rem 0.75rem;
    font-size: 0.875rem;
    color: rgb(226 232 240);
    transition: background 0.15s;
}
.btn:hover:not(:disabled) {
    background: rgb(30 41 59);
}
.btn:disabled {
    opacity: 0.5;
}
</style>
