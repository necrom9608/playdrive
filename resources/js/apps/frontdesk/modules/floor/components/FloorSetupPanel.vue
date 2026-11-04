<template>
    <aside class="flex min-h-0 w-[400px] shrink-0 flex-col rounded-3xl border border-slate-800 bg-slate-900 shadow-xl">
        <div class="flex items-start gap-3 border-b border-slate-800 p-5">
            <div class="flex h-11 w-11 shrink-0 items-center justify-center rounded-2xl border border-slate-700 bg-slate-950">
                <component :is="typeIcon(setup.type)" class="h-6 w-6 text-sky-300" />
            </div>
            <div class="min-w-0 flex-1">
                <h2 class="truncate text-lg font-semibold text-white">{{ setup.name }}</h2>
                <div class="mt-1 flex flex-wrap items-center gap-2 text-xs">
                    <span class="rounded-full border px-2 py-0.5 font-semibold" :class="meta.pill">{{ meta.label }}</span>
                    <span class="text-slate-500">{{ [setup.zone, setup.type_label].filter(Boolean).join(' · ') }}</span>
                </div>
            </div>
            <button type="button" class="rounded-xl p-2 text-slate-400 transition hover:bg-slate-800 hover:text-white" aria-label="Sluiten" @click="$emit('close')">
                <XMarkIcon class="h-5 w-5" />
            </button>
        </div>

        <div class="min-h-0 flex-1 space-y-6 overflow-y-auto p-5">
            <div v-if="group" class="rounded-2xl border border-sky-400/30 bg-sky-400/10 px-4 py-3 text-sm text-sky-100">
                Deze pc volgt groep <span class="font-semibold">{{ group.name }}</span>. Van spel wisselen gebeurt voor de hele groep.
            </div>
            <div v-else-if="!device" class="rounded-2xl border border-slate-800 bg-slate-950 px-4 py-3 text-sm text-slate-400">
                Geen Playscope op deze speelplek: enkel de status{{ plugs.length ? ' en de stroom' : '' }} zijn hier te bedienen.
            </div>
            <div v-else-if="!device.online" class="rounded-2xl border border-slate-800 bg-slate-950 px-4 py-3 text-sm text-slate-400">
                Playscope meldt zich niet<span v-if="device.last_seen_at"> (laatst gezien {{ formatTime(device.last_seen_at) }})</span>. Opdrachten vervallen na 2 minuten als de pc niet online komt.
            </div>

            <!-- Nu -->
            <section v-if="device">
                <h3 class="section-title">Nu</h3>
                <div v-if="setup.in_use" class="rounded-2xl border border-sky-400/30 bg-sky-400/10 px-4 py-3 text-sm text-sky-100">
                    Speelt <span class="font-semibold">{{ setup.current_game || 'een game' }}</span><span v-if="minutes !== null"> · {{ minutes }} min</span>
                </div>
                <div v-else class="rounded-2xl border border-slate-800 bg-slate-950 px-4 py-3 text-sm text-slate-400">
                    {{ device.online ? (device.state?.status === 'attract' ? 'Attract mode, niemand speelt' : 'Geen game bezig, staat in het menu') : 'Onbekend' }}
                </div>
            </section>

            <!-- Spel starten -->
            <section v-if="device && device.online && !group">
                <h3 class="section-title">Spel starten</h3>
                <div v-if="pendingGame" class="mb-2 space-y-2 rounded-2xl border border-amber-500/30 bg-amber-500/10 px-4 py-3 text-sm text-amber-100">
                    <p>Er wordt gespeeld. <span class="font-semibold">{{ pendingGame.name }}</span> starten sluit {{ setup.current_game || 'de huidige game' }} af.</p>
                    <div class="flex gap-2">
                        <button type="button" class="rounded-xl bg-amber-500 px-3 py-2 font-semibold text-slate-950" @click="launch(pendingGame)">Starten</button>
                        <button type="button" class="rounded-xl border border-amber-500/40 px-3 py-2" @click="pendingGame = null">Annuleren</button>
                    </div>
                </div>
                <label class="flex items-center gap-2 rounded-2xl border border-slate-700 bg-slate-950 px-3 py-2.5 text-slate-400 focus-within:border-blue-500">
                    <MagnifyingGlassIcon class="h-5 w-5 shrink-0" />
                    <input v-model="search" class="w-full bg-transparent text-sm text-white outline-none placeholder:text-slate-500" :placeholder="gamesState?.items ? `Zoek in ${gamesState.items.length} games` : 'Games laden…'">
                </label>
                <p v-if="gamesState?.error" class="mt-2 text-sm text-rose-300">{{ gamesState.error }}</p>
                <div class="mt-2 max-h-72 space-y-1.5 overflow-y-auto pr-1">
                    <button
                        v-for="game in filteredGames"
                        :key="game.id"
                        type="button"
                        class="flex w-full items-center gap-3 rounded-2xl border bg-slate-950 px-3 py-2.5 text-left text-sm transition hover:border-slate-500"
                        :class="setup.in_use && game.name === setup.current_game ? 'border-emerald-500/40' : 'border-slate-800'"
                        @click="pick(game)"
                    >
                        <PlayIcon class="h-4 w-4 shrink-0 text-slate-400" />
                        <span class="min-w-0 flex-1 truncate text-slate-100">{{ game.name }}</span>
                        <span class="text-xs text-slate-500">{{ game.source_label }}</span>
                    </button>
                    <p v-if="gamesState?.items && !filteredGames.length" class="px-1 py-2 text-sm text-slate-500">Geen games gevonden.</p>
                </div>
            </section>

            <!-- Bediening -->
            <section>
                <h3 class="section-title">Bediening</h3>
                <div class="flex flex-wrap gap-2">
                    <template v-if="device">
                        <button v-if="setup.in_use && !group" type="button" class="btn" @click="command('stop_game', 'Game stoppen')">
                            <StopIcon class="h-5 w-5" /> Game stoppen
                        </button>
                        <button type="button" class="btn" :class="messageOpen ? 'bg-slate-800!' : ''" @click="messageOpen = !messageOpen">
                            <ChatBubbleOvalLeftEllipsisIcon class="h-5 w-5" /> Bericht
                        </button>
                        <button type="button" class="btn" @click="command('restart_app', 'Playscope herstarten')">
                            <ArrowPathIcon class="h-5 w-5" /> Playscope herstarten
                        </button>
                    </template>
                    <button v-if="setup.status === 'maintenance'" type="button" class="btn border-amber-500/50! bg-amber-500/10! text-amber-200!" @click="floor.setStatus(setup, 'active')">
                        <WrenchScrewdriverIcon class="h-5 w-5" /> Onderhoud beëindigen
                    </button>
                    <button v-else type="button" class="btn text-amber-200!" @click="floor.setStatus(setup, 'maintenance')">
                        <WrenchScrewdriverIcon class="h-5 w-5" /> In onderhoud
                    </button>
                </div>
                <form v-if="messageOpen && device" class="mt-3 space-y-2 rounded-2xl border border-slate-800 bg-slate-950 p-3" @submit.prevent="sendMessage">
                    <input v-model="message.text" maxlength="200" class="w-full rounded-xl border border-slate-700 bg-slate-900 px-3 py-2.5 text-sm text-white outline-none focus:border-blue-500" placeholder="bv. Nog 5 minuten, dan sluiten we">
                    <div class="flex items-center gap-2">
                        <select v-model.number="message.seconds" class="rounded-xl border border-slate-700 bg-slate-900 px-3 py-2.5 text-sm text-white outline-none">
                            <option :value="10">10 s</option>
                            <option :value="15">15 s</option>
                            <option :value="30">30 s</option>
                            <option :value="60">1 min</option>
                        </select>
                        <button type="submit" :disabled="!message.text.trim()" class="ml-auto rounded-xl bg-blue-600 px-4 py-2.5 text-sm font-semibold text-white disabled:opacity-50">Versturen</button>
                    </div>
                </form>
            </section>

            <!-- Stroom -->
            <section v-if="plugs.length">
                <h3 class="section-title">Stroom</h3>
                <p v-if="!power.available" class="mb-2 text-sm text-slate-500">Stekkers bedienen kan enkel in de kassa-app (lokaal netwerk).</p>
                <p v-else-if="power.appOutdated" class="mb-2 text-sm text-amber-300">Werk de kassa-app bij om de stekkers te bedienen.</p>
                <div class="space-y-2">
                    <div v-for="plug in plugs" :key="plug.id" class="rounded-2xl border border-slate-800 bg-slate-950 px-4 py-3">
                        <div class="flex items-center gap-3">
                            <BoltIcon class="h-5 w-5 shrink-0" :class="plugInfo(plug).on ? 'text-amber-300' : 'text-slate-600'" />
                            <div class="min-w-0 flex-1">
                                <div class="truncate text-sm font-semibold text-white">{{ plug.name }}</div>
                                <div class="text-xs" :class="plugInfo(plug).error ? 'text-rose-300' : 'text-slate-500'">{{ plugInfo(plug).text }}</div>
                            </div>
                            <button
                                v-if="power.canControl && plugInfo(plug).known"
                                type="button"
                                :disabled="power.isPending(plug)"
                                class="rounded-xl border px-3 py-2 text-sm font-semibold transition disabled:opacity-50"
                                :class="plugInfo(plug).on ? 'border-slate-700 text-slate-200 hover:bg-slate-800' : 'border-amber-500/40 bg-amber-500/10 text-amber-200 hover:bg-amber-500/20'"
                                @click="togglePlug(plug)"
                            >
                                {{ power.isPending(plug) ? '…' : (plugInfo(plug).on ? 'Uit' : 'Aan') }}
                            </button>
                        </div>
                        <div v-if="offConfirm === plug.id" class="mt-3 space-y-2 border-t border-slate-800 pt-3 text-sm text-amber-100">
                            <p>De pc staat nog aan. Stroom eraf zonder afsluiten kan de installatie beschadigen.</p>
                            <div class="flex flex-wrap gap-2">
                                <button type="button" class="rounded-xl bg-amber-500 px-3 py-2 font-semibold text-slate-950" @click="shutdownThenOff(plug)">Pc uitzetten, dan stroom uit</button>
                                <button type="button" class="rounded-xl border border-rose-500/40 px-3 py-2 text-rose-200" @click="plugOff(plug)">Toch meteen uit</button>
                                <button type="button" class="rounded-xl border border-slate-700 px-3 py-2 text-slate-300" @click="offConfirm = null">Annuleren</button>
                            </div>
                        </div>
                    </div>
                </div>
            </section>

            <!-- Pc -->
            <section v-if="device">
                <h3 class="section-title">Pc</h3>
                <div class="flex flex-wrap gap-2">
                    <button type="button" class="btn border-rose-500/40! text-rose-200!" @click="withPin('Pc herstarten', (pin) => command('reboot', 'Pc herstarten', { pin }))">
                        <ArrowPathIcon class="h-5 w-5" /> Herstarten
                    </button>
                    <button type="button" class="btn border-rose-500/40! text-rose-200!" @click="withPin('Pc uitzetten', (pin) => command('shutdown', 'Pc uitzetten', { pin }))">
                        <PowerIcon class="h-5 w-5" /> Uitzetten
                    </button>
                </div>
                <p v-if="floor.adminPinSet" class="mt-2 text-xs text-slate-500">Vraagt de beheerpincode.</p>
            </section>
        </div>

        <FloorPinDialog :open="Boolean(pinAction)" :title="pinAction?.title" @close="pinAction = null" @confirm="confirmPin" />
    </aside>
</template>

<script setup>
import { computed, reactive, ref, watch } from 'vue'
import {
    ArrowPathIcon,
    BoltIcon,
    ChatBubbleOvalLeftEllipsisIcon,
    MagnifyingGlassIcon,
    PlayIcon,
    PowerIcon,
    StopIcon,
    WrenchScrewdriverIcon,
    XMarkIcon,
} from '@heroicons/vue/24/outline'
import FloorPinDialog from './FloorPinDialog.vue'
import { useFloorStore } from '../stores/useFloorStore'
import { usePowerStore, SHUTDOWN_GRACE_SECONDS } from '../stores/usePowerStore'
import { STATE_META, formatTime, formatWatts, minutesSince, playscopeDevice, setupState, typeIcon } from '../utils/floorState'

const props = defineProps({
    setup: { type: Object, required: true },
    group: { type: Object, default: null },
    now: { type: Number, required: true },
})
defineEmits(['close'])

const floor = useFloorStore()
const power = usePowerStore()

const search = ref('')
const pendingGame = ref(null)
const messageOpen = ref(false)
const message = reactive({ text: '', seconds: 15 })
const offConfirm = ref(null)
const pinAction = ref(null)

const meta = computed(() => STATE_META[setupState(props.setup)])
const device = computed(() => playscopeDevice(props.setup))
const minutes = computed(() => minutesSince(props.setup.playing_since, props.now))
const plugs = computed(() => power.plugsForSetup(props.setup.id))
const gamesState = computed(() => (device.value ? floor.games[device.value.id] : null))

const filteredGames = computed(() => {
    const items = gamesState.value?.items ?? []
    const q = search.value.trim().toLowerCase()
    return q ? items.filter(g => g.name.toLowerCase().includes(q)) : items
})

watch(() => [props.setup.id, device.value?.id, device.value?.online], () => {
    if (device.value && device.value.online && !props.group) floor.loadGames(device.value.id)
}, { immediate: true })

watch(() => props.setup.id, () => {
    search.value = ''
    pendingGame.value = null
    messageOpen.value = false
    message.text = ''
    offConfirm.value = null
})

function plugInfo(plug) {
    const outlet = power.outlet(plug)
    const hubError = power.hubError(plug.hub_id)
    if (!power.available || power.appOutdated) return { known: false, on: false, text: 'Status enkel zichtbaar in de kassa-app' }
    const hub = power.hubs.find(h => h.id === plug.hub_id)
    if (!hub?.paired) return { known: false, on: false, text: 'Hub niet gekoppeld', error: true }
    if (hubError && !outlet) return { known: false, on: false, text: hubError, error: true }
    if (!outlet) return { known: false, on: false, text: 'Status ophalen…' }
    if (outlet.reachable === false) return { known: true, on: Boolean(outlet.isOn), text: 'Stekker niet bereikbaar', error: true }
    const watts = outlet.isOn && outlet.powerW !== null && outlet.powerW !== undefined ? ` · ${formatWatts(outlet.powerW)}` : ''
    return { known: true, on: Boolean(outlet.isOn), text: outlet.isOn ? `Aan${watts}` : 'Uit' }
}

function command(name, label, extra = {}) {
    if (!device.value) return Promise.resolve(false)
    return floor.sendCommand(device.value, name, { label, ...extra })
}

function pick(game) {
    if (props.setup.in_use) {
        pendingGame.value = game
        return
    }
    launch(game)
}

function launch(game) {
    pendingGame.value = null
    command('launch_game', `${game.name} starten`, { gameId: game.id })
}

async function sendMessage() {
    const ok = await command('message', 'Bericht', { payload: { text: message.text.trim(), seconds: message.seconds } })
    if (ok) {
        message.text = ''
        messageOpen.value = false
    }
}

/** Pincode vragen (als die ingesteld is) en dan de actie uitvoeren. */
function withPin(title, action) {
    if (!floor.adminPinSet) {
        action(null)
        return
    }
    pinAction.value = { title, action }
}

function confirmPin(pin) {
    const action = pinAction.value?.action
    pinAction.value = null
    action?.(pin)
}

async function togglePlug(plug) {
    const info = plugInfo(plug)
    if (info.on && device.value?.online) {
        offConfirm.value = plug.id
        return
    }
    try {
        await power.setPlug(plug, !info.on)
        floor.toast(`${plug.name}: stroom ${info.on ? 'uit' : 'aan'}`)
    } catch (e) {
        floor.toast(e.message, 'error')
    }
}

async function plugOff(plug) {
    offConfirm.value = null
    try {
        await power.setPlug(plug, false)
        floor.toast(`${plug.name}: stroom uit`)
    } catch (e) {
        floor.toast(e.message, 'error')
    }
}

function shutdownThenOff(plug) {
    offConfirm.value = null
    withPin('Pc uitzetten', async (pin) => {
        const ok = await command('shutdown', 'Pc uitzetten', { pin })
        if (!ok) return
        floor.toast(`${plug.name}: stroom gaat uit over ${SHUTDOWN_GRACE_SECONDS} s`, 'info')
        window.setTimeout(() => plugOff(plug), SHUTDOWN_GRACE_SECONDS * 1000)
    })
}
</script>

<style scoped>
.section-title {
    margin-bottom: 0.5rem;
    font-size: 0.75rem;
    font-weight: 600;
    letter-spacing: 0.08em;
    text-transform: uppercase;
    color: rgb(100 116 139);
}
.btn {
    display: inline-flex;
    align-items: center;
    gap: 0.5rem;
    border-radius: 1rem;
    border: 1px solid rgb(51 65 85);
    background: rgb(2 6 23);
    padding: 0.625rem 0.875rem;
    font-size: 0.875rem;
    font-weight: 600;
    color: rgb(226 232 240);
    transition: background-color 0.15s;
}
.btn:hover {
    background: rgb(30 41 59);
}
</style>
