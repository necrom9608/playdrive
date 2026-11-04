<template>
    <div class="space-y-6">
        <div v-if="error" class="rounded-2xl border border-red-500/30 bg-red-500/10 px-5 py-4 text-sm text-red-200">{{ error }}</div>

        <!-- Hubs -->
        <section class="space-y-3">
            <div class="flex items-center justify-between">
                <h2 class="text-sm font-semibold uppercase tracking-wider text-slate-500">DIRIGERA-hubs</h2>
            </div>
            <p class="text-sm text-slate-400">
                Voeg elke hub toe met zijn IP-adres (geef hem best een vaste DHCP-reservatie in de router).
                Koppelen gebeurt aan de kassa: <span class="text-slate-200">Frontdesk → Zaal → tandwiel</span>, want de hubs zijn enkel in het lokale netwerk bereikbaar.
                De kassa leest daarna de stekkers uit; ze verschijnen hieronder.
            </p>
            <div v-if="!hubs.length && !loading" class="rounded-3xl border border-dashed border-slate-800 px-6 py-10 text-center">
                <BoltIcon class="mx-auto h-10 w-10 text-slate-600" />
                <p class="mt-3 text-slate-300">Nog geen hubs.</p>
            </div>
            <div class="grid gap-4 md:grid-cols-2 2xl:grid-cols-3">
                <article v-for="hub in hubs" :key="hub.id" class="flex flex-col rounded-3xl border border-slate-800 bg-slate-900/80 p-5">
                    <div class="flex items-start gap-3">
                        <span class="mt-1.5 h-2.5 w-2.5 shrink-0 rounded-full" :class="hub.last_error ? 'bg-rose-400' : (hub.paired ? 'bg-emerald-400' : 'bg-slate-600')" />
                        <div class="min-w-0 flex-1">
                            <h3 class="truncate text-lg font-semibold text-white">{{ hub.name }}</h3>
                            <p class="font-mono text-xs text-slate-400">{{ hub.host }}</p>
                            <p class="mt-1 text-xs text-slate-500">
                                {{ hub.paired ? `Gekoppeld · ${hub.plug_count} stekker${hub.plug_count === 1 ? '' : 's'}` : 'Nog niet gekoppeld (doe dit aan de kassa)' }}
                                <span v-if="hub.last_seen_at"> · laatst gezien {{ formatDate(hub.last_seen_at) }}</span>
                            </p>
                            <p v-if="hub.last_error" class="mt-1 text-xs text-rose-300">{{ hub.last_error }}</p>
                        </div>
                    </div>
                    <div class="mt-auto flex flex-wrap gap-2 pt-4">
                        <button type="button" class="small-btn" @click="openHubForm(hub)"><PencilSquareIcon class="h-4 w-4" /> Bewerken</button>
                        <button v-if="hub.paired" type="button" class="small-btn" @click="unpair(hub)">Ontkoppelen</button>
                    </div>
                </article>
            </div>
        </section>

        <!-- Golven -->
        <section class="space-y-3">
            <div class="flex items-center justify-between">
                <h2 class="text-sm font-semibold uppercase tracking-wider text-slate-500">Opstartgolven</h2>
                <button type="button" class="small-btn" @click="addWave"><PlusIcon class="h-4 w-4" /> Golf</button>
            </div>
            <p class="text-sm text-slate-400">
                "Zaal openen" zet de stekkers golf per golf aan, met wat tijd tussen twee stekkers (geen piekstroom) en een wachttijd na elke golf.
                "Zaal sluiten" zet eerst de Playscope-pc's netjes uit en doet daarna de golven in omgekeerde volgorde. Stekkers zonder golf doen niet mee.
            </p>
            <div class="overflow-hidden rounded-3xl border border-slate-800 bg-slate-900/80">
                <div v-if="!waves.length" class="px-5 py-6 text-sm text-slate-500">Nog geen golven.</div>
                <div v-for="(wave, i) in waves" :key="wave.id" class="flex flex-wrap items-center gap-3 border-t border-slate-800 px-5 py-3 first:border-t-0">
                    <span class="w-6 text-sm font-semibold text-slate-500">{{ i + 1 }}</span>
                    <input v-model="wave.name" class="field w-48" maxlength="100" @change="saveWave(wave)">
                    <label class="flex items-center gap-2 text-sm text-slate-400">
                        <input v-model.number="wave.stagger_seconds" type="number" min="0" max="30" class="field w-20" @change="saveWave(wave)">
                        s tussen stekkers
                    </label>
                    <label class="flex items-center gap-2 text-sm text-slate-400">
                        <input v-model.number="wave.delay_after_seconds" type="number" min="0" max="600" class="field w-24" @change="saveWave(wave)">
                        s wachten na de golf
                    </label>
                    <span class="text-xs text-slate-500">{{ plugCountForWave(wave.id) }} stekkers</span>
                    <div class="ml-auto flex gap-1">
                        <button type="button" class="icon-btn" :disabled="i === 0" title="Hoger" @click="moveWave(i, -1)"><ChevronUpIcon class="h-4 w-4" /></button>
                        <button type="button" class="icon-btn" :disabled="i === waves.length - 1" title="Lager" @click="moveWave(i, 1)"><ChevronDownIcon class="h-4 w-4" /></button>
                        <button type="button" class="icon-btn hover:text-red-300" title="Verwijderen" @click="removeWave(wave)"><TrashIcon class="h-4 w-4" /></button>
                    </div>
                </div>
            </div>
        </section>

        <!-- Stekkers -->
        <section class="space-y-3">
            <div class="flex flex-wrap items-center justify-between gap-3">
                <h2 class="text-sm font-semibold uppercase tracking-wider text-slate-500">Stekkers</h2>
                <label class="flex items-center gap-2 text-sm text-slate-400">
                    <input v-model="onlyUnassigned" type="checkbox" class="h-4 w-4 rounded border-slate-600 bg-slate-950">
                    Enkel zonder speelplek
                </label>
            </div>
            <div v-if="!plugs.length && !loading" class="rounded-3xl border border-dashed border-slate-800 px-6 py-10 text-center text-sm text-slate-500">
                Nog geen stekkers. Koppel een hub aan de kassa; de stekkers verschijnen hier binnen een minuut.
            </div>
            <div v-else class="overflow-x-auto rounded-3xl border border-slate-800 bg-slate-900/80">
                <table class="w-full min-w-[900px] text-left text-sm">
                    <thead class="border-b border-slate-800 text-xs uppercase tracking-wider text-slate-500">
                        <tr>
                            <th class="px-5 py-3 font-semibold">Stekker</th>
                            <th class="px-5 py-3 font-semibold">Status</th>
                            <th class="px-5 py-3 font-semibold">Speelplek</th>
                            <th class="px-5 py-3 font-semibold">Golf</th>
                            <th class="px-5 py-3 font-semibold">Volgorde</th>
                            <th class="px-5 py-3" />
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-800">
                        <tr v-for="plug in visiblePlugs" :key="plug.id" :class="plug.missing ? 'opacity-60' : ''">
                            <td class="px-5 py-3">
                                <div class="font-semibold text-white">{{ plug.name }}</div>
                                <div class="text-xs text-slate-500">{{ [plug.room, plug.hub_name, plug.model].filter(Boolean).join(' · ') }}</div>
                            </td>
                            <td class="px-5 py-3">
                                <span v-if="plug.missing" class="rounded-full border border-amber-500/40 bg-amber-500/10 px-2 py-0.5 text-xs font-semibold text-amber-200">Niet meer op de hub</span>
                                <span v-else-if="plug.is_reachable === false" class="text-rose-300">Niet bereikbaar</span>
                                <span v-else-if="plug.is_on" class="text-amber-300">Aan<span v-if="plug.power_w !== null"> · {{ Math.round(plug.power_w) }} W</span></span>
                                <span v-else-if="plug.is_on === false" class="text-slate-400">Uit</span>
                                <span v-else class="text-slate-500">—</span>
                            </td>
                            <td class="px-5 py-3">
                                <select v-model="plug.setup_id" class="field w-56" @change="savePlug(plug)">
                                    <option :value="null">Geen</option>
                                    <option v-for="s in setups" :key="s.id" :value="s.id">{{ s.name }}{{ s.zone ? ` · ${s.zone}` : '' }}</option>
                                </select>
                            </td>
                            <td class="px-5 py-3">
                                <select v-model="plug.wave_id" class="field w-40" @change="savePlug(plug)">
                                    <option :value="null">Niet in sequentie</option>
                                    <option v-for="(w, i) in waves" :key="w.id" :value="w.id">{{ i + 1 }}. {{ w.name }}</option>
                                </select>
                            </td>
                            <td class="px-5 py-3">
                                <input v-model.number="plug.wave_order" type="number" min="0" max="999" class="field w-20" :disabled="!plug.wave_id" @change="savePlug(plug)">
                            </td>
                            <td class="px-5 py-3 text-right">
                                <button v-if="plug.missing" type="button" class="icon-btn hover:text-red-300" title="Verwijderen" @click="removePlug(plug)"><TrashIcon class="h-4 w-4" /></button>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </section>

        <ModalDialog :open="hubForm.open" :title="hubForm.hub ? 'Hub bewerken' : 'Hub toevoegen'" @close="hubForm.open = false">
            <div v-if="hubForm.error" class="mb-4 rounded-2xl border border-red-800 bg-red-950/40 px-4 py-3 text-sm text-red-300">{{ hubForm.error }}</div>
            <div class="space-y-4">
                <label class="block space-y-1">
                    <span class="block text-sm font-medium text-slate-300">Naam *</span>
                    <input v-model="hubForm.name" class="field w-full" placeholder="bv. Hub LAN-zaal" maxlength="100">
                </label>
                <label class="block space-y-1">
                    <span class="block text-sm font-medium text-slate-300">IP-adres *</span>
                    <input v-model="hubForm.host" class="field w-full font-mono" placeholder="192.168.1.50">
                    <p class="text-xs text-slate-500">Te vinden in de IKEA Home smart-app (Hub → Instellingen) of in je router.</p>
                </label>
            </div>
            <div class="mt-6 flex flex-wrap gap-3">
                <button type="button" :disabled="saving || !hubForm.name || !hubForm.host" class="rounded-xl bg-blue-600 px-4 py-3 text-sm font-semibold text-white transition hover:bg-blue-500 disabled:opacity-60" @click="saveHub">
                    {{ saving ? 'Bezig...' : 'Opslaan' }}
                </button>
                <button type="button" class="rounded-xl border border-slate-700 px-4 py-3 text-sm font-medium text-slate-300 transition hover:bg-slate-800" @click="hubForm.open = false">Annuleren</button>
                <button v-if="hubForm.hub" type="button" class="ml-auto rounded-xl border border-rose-500/30 bg-rose-500/10 px-4 py-3 text-sm font-semibold text-rose-200 transition hover:bg-rose-500/20" @click="removeHub">Verwijderen</button>
            </div>
        </ModalDialog>
    </div>
</template>

<script setup>
import { computed, onMounted, reactive, ref } from 'vue'
import {
    BoltIcon,
    ChevronDownIcon,
    ChevronUpIcon,
    PencilSquareIcon,
    PlusIcon,
    TrashIcon,
} from '@heroicons/vue/24/outline'
import ModalDialog from '../../../components/ModalDialog.vue'
import {
    createPowerHub,
    createPowerWave,
    deletePowerHub,
    deletePowerPlug,
    deletePowerWave,
    fetchPower,
    reorderPowerWaves,
    unpairPowerHub,
    updatePowerHub,
    updatePowerPlug,
    updatePowerWave,
} from '../services/setupApi'

const emit = defineEmits(['count'])

const loading = ref(false)
const saving = ref(false)
const error = ref('')
const hubs = ref([])
const waves = ref([])
const plugs = ref([])
const setups = ref([])
const onlyUnassigned = ref(false)
const hubForm = reactive({ open: false, hub: null, name: '', host: '', error: '' })

const visiblePlugs = computed(() => (onlyUnassigned.value ? plugs.value.filter(p => !p.setup_id) : plugs.value))

function plugCountForWave(waveId) {
    return plugs.value.filter(p => p.wave_id === waveId).length
}

function formatDate(value) {
    if (!value) return ''
    return new Date(value).toLocaleString('nl-BE', { dateStyle: 'short', timeStyle: 'short' })
}

async function load({ silent = false } = {}) {
    if (!silent) loading.value = true
    try {
        const data = await fetchPower()
        hubs.value = data.hubs ?? []
        waves.value = data.waves ?? []
        plugs.value = data.plugs ?? []
        setups.value = data.setups ?? []
        error.value = ''
        emit('count', plugs.value.filter(p => !p.missing).length)
    } catch (e) {
        if (!silent) error.value = e.message || 'Laden mislukt.'
    } finally {
        loading.value = false
    }
}

function openHubForm(hub) {
    hubForm.hub = hub
    hubForm.name = hub?.name ?? ''
    hubForm.host = hub?.host ?? ''
    hubForm.error = ''
    hubForm.open = true
}

async function saveHub() {
    saving.value = true
    hubForm.error = ''
    const payload = { name: hubForm.name.trim(), host: hubForm.host.trim() }
    try {
        if (hubForm.hub) await updatePowerHub(hubForm.hub.id, payload)
        else await createPowerHub(payload)
        hubForm.open = false
        await load({ silent: true })
    } catch (e) {
        hubForm.error = e.message || 'Opslaan mislukt.'
    } finally {
        saving.value = false
    }
}

async function removeHub() {
    const hub = hubForm.hub
    if (!hub) return
    if (!window.confirm(`"${hub.name}" verwijderen? De stekkers van deze hub en hun koppeling met speelplekken verdwijnen ook.`)) return
    saving.value = true
    try {
        await deletePowerHub(hub.id)
        hubForm.open = false
        await load({ silent: true })
    } catch (e) {
        hubForm.error = e.message || 'Verwijderen mislukt.'
    } finally {
        saving.value = false
    }
}

async function unpair(hub) {
    if (!window.confirm(`"${hub.name}" ontkoppelen? De kassa kan de stekkers dan niet meer bedienen tot je hem opnieuw koppelt.`)) return
    try {
        await unpairPowerHub(hub.id)
        await load({ silent: true })
    } catch (e) {
        error.value = e.message || 'Ontkoppelen mislukt.'
    }
}

async function savePlug(plug) {
    try {
        await updatePowerPlug(plug.id, {
            setup_id: plug.setup_id,
            power_wave_id: plug.wave_id,
            wave_order: Number.isFinite(plug.wave_order) ? plug.wave_order : 0,
        })
        error.value = ''
    } catch (e) {
        error.value = e.message || 'Opslaan mislukt.'
        await load({ silent: true })
    }
}

async function removePlug(plug) {
    if (!window.confirm(`"${plug.name}" verwijderen?`)) return
    try {
        await deletePowerPlug(plug.id)
        await load({ silent: true })
    } catch (e) {
        error.value = e.message || 'Verwijderen mislukt.'
    }
}

function wavePayload(wave) {
    return {
        name: (wave.name || '').trim() || 'Golf',
        stagger_seconds: Math.max(0, Math.min(30, Number(wave.stagger_seconds) || 0)),
        delay_after_seconds: Math.max(0, Math.min(600, Number(wave.delay_after_seconds) || 0)),
    }
}

async function addWave() {
    try {
        const res = await createPowerWave({ name: `Golf ${waves.value.length + 1}`, stagger_seconds: 2, delay_after_seconds: 20 })
        waves.value = res.waves ?? waves.value
    } catch (e) {
        error.value = e.message || 'Golf toevoegen mislukt.'
    }
}

async function saveWave(wave) {
    try {
        const res = await updatePowerWave(wave.id, wavePayload(wave))
        waves.value = res.waves ?? waves.value
        error.value = ''
    } catch (e) {
        error.value = e.message || 'Opslaan mislukt.'
    }
}

async function moveWave(index, delta) {
    const list = [...waves.value]
    const target = index + delta
    if (target < 0 || target >= list.length) return
    ;[list[index], list[target]] = [list[target], list[index]]
    waves.value = list
    try {
        const res = await reorderPowerWaves(list.map(w => w.id))
        waves.value = res.waves ?? waves.value
    } catch (e) {
        error.value = e.message || 'Volgorde opslaan mislukt.'
        await load({ silent: true })
    }
}

async function removeWave(wave) {
    const n = plugCountForWave(wave.id)
    if (!window.confirm(`"${wave.name}" verwijderen?${n ? ` ${n} stekker${n === 1 ? '' : 's'} doen daarna niet meer mee met openen/sluiten.` : ''}`)) return
    try {
        const res = await deletePowerWave(wave.id)
        waves.value = res.waves ?? []
        await load({ silent: true })
    } catch (e) {
        error.value = e.message || 'Verwijderen mislukt.'
    }
}

onMounted(() => load())

defineExpose({ load, openHubForm })
</script>

<style scoped>
.field {
    border-radius: 0.75rem;
    border: 1px solid rgb(51 65 85);
    background: rgb(2 6 23);
    padding: 0.5rem 0.75rem;
    font-size: 0.875rem;
    color: white;
    outline: none;
}
.field:focus {
    border-color: rgb(14 165 233);
}
.field:disabled {
    opacity: 0.4;
}
.small-btn {
    display: inline-flex;
    align-items: center;
    gap: 0.375rem;
    border-radius: 0.75rem;
    border: 1px solid rgb(51 65 85);
    background: rgb(2 6 23);
    padding: 0.5rem 0.75rem;
    font-size: 0.875rem;
    color: rgb(226 232 240);
    transition: background-color 0.15s;
}
.small-btn:hover {
    background: rgb(30 41 59);
}
.icon-btn {
    display: inline-flex;
    height: 2rem;
    width: 2rem;
    align-items: center;
    justify-content: center;
    border-radius: 0.5rem;
    border: 1px solid rgb(51 65 85);
    color: rgb(148 163 184);
}
.icon-btn:hover:not(:disabled) {
    background: rgb(30 41 59);
}
.icon-btn:disabled {
    opacity: 0.3;
}
</style>
