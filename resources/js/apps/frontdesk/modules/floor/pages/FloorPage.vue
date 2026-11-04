<template>
    <div class="flex h-full min-h-0 gap-4">
        <div class="flex min-h-0 min-w-0 flex-1 flex-col gap-4">
            <div class="flex flex-wrap items-end justify-between gap-4">
                <div>
                    <h1 class="text-2xl font-bold text-white">Zaal</h1>
                    <p class="text-sm text-slate-400">Live status van de speelplekken. Tik op een plek om te bedienen.</p>
                </div>
                <div class="flex flex-wrap items-center gap-2">
                    <div class="inline-flex rounded-2xl border border-slate-800 bg-slate-900 p-1">
                        <button
                            v-for="option in zoneOptions"
                            :key="option.key"
                            type="button"
                            class="rounded-xl px-3 py-2 text-sm font-semibold transition"
                            :class="floor.zone === option.key ? 'bg-slate-700 text-white' : 'text-slate-400 hover:text-white'"
                            @click="floor.zone = option.key"
                        >
                            {{ option.label }}
                        </button>
                    </div>
                    <template v-if="power.hasHubs">
                        <button type="button" class="head-btn" :disabled="!power.canControl || power.sequenceRunning" :title="powerHint" @click="openSequence('open')">
                            <BoltIcon class="h-5 w-5 text-amber-300" /> Zaal openen
                        </button>
                        <button type="button" class="head-btn" :disabled="!power.canControl || power.sequenceRunning" :title="powerHint" @click="openSequence('close')">
                            <PowerIcon class="h-5 w-5 text-slate-400" /> Zaal sluiten
                        </button>
                    </template>
                    <button type="button" class="head-btn" title="Stroom: hubs koppelen" @click="hubsOpen = true">
                        <Cog6ToothIcon class="h-5 w-5" />
                    </button>
                    <button type="button" class="head-btn" title="Vernieuwen" @click="refresh">
                        <ArrowPathIcon class="h-5 w-5" :class="floor.loading ? 'animate-spin' : ''" />
                    </button>
                </div>
            </div>

            <div v-if="floor.error" class="rounded-2xl border border-rose-500/30 bg-rose-500/10 px-5 py-3 text-sm text-rose-200">{{ floor.error }}</div>

            <button
                v-if="power.sequence && !sequenceOpen"
                type="button"
                class="flex items-center gap-3 rounded-2xl border px-5 py-3 text-left text-sm"
                :class="power.sequenceRunning ? 'border-sky-400/30 bg-sky-400/10 text-sky-100' : 'border-slate-700 bg-slate-900 text-slate-300'"
                @click="sequenceOpen = true"
            >
                <ArrowPathIcon v-if="power.sequenceRunning" class="h-5 w-5 animate-spin" />
                <BoltIcon v-else class="h-5 w-5" />
                <span class="flex-1">
                    {{ power.sequence.kind === 'open' ? 'Zaal openen' : 'Zaal sluiten' }}: {{ power.sequence.phase }}
                    <span v-if="power.sequence.waitUntil"> · {{ power.sequence.waitLabel }}</span>
                </span>
                <span class="text-xs opacity-70">Details</span>
            </button>

            <div class="min-h-0 flex-1 space-y-6 overflow-y-auto pr-1">
                <div v-if="!floor.loaded && floor.loading" class="py-16 text-center text-slate-500">Laden…</div>
                <div v-else-if="floor.loaded && !floor.setups.length && !floor.groups.length" class="rounded-3xl border border-dashed border-slate-800 px-6 py-12 text-center">
                    <Squares2X2Icon class="mx-auto h-10 w-10 text-slate-600" />
                    <p class="mt-3 text-slate-300">Nog geen speelplekken.</p>
                    <p class="mt-1 text-sm text-slate-500">Voeg ze toe in de backoffice (Speelplekken).</p>
                </div>

                <template v-if="showGroups">
                    <FloorGroupCard
                        v-for="group in floor.groups"
                        :key="group.id"
                        :group="group"
                        :now="serverNow"
                        :selected-setup-id="floor.selectedSetupId"
                        @switch="(game) => floor.groupSwitch(group, game)"
                        @pause="(paused) => floor.groupPause(group, paused)"
                        @select-seat="(id) => floor.select(id)"
                    />
                </template>

                <section v-for="zone in visibleZones" :key="zone.zone">
                    <h2 class="mb-3 text-xs font-semibold uppercase tracking-wider text-slate-500">{{ zone.zone }}</h2>
                    <div class="grid grid-cols-[repeat(auto-fill,minmax(210px,1fr))] gap-3">
                        <FloorSetupTile
                            v-for="setup in zone.items"
                            :key="setup.id"
                            :setup="setup"
                            :now="serverNow"
                            :power="tilePower(setup)"
                            :selected="floor.selectedSetupId === setup.id"
                            @select="floor.select(setup.id)"
                        />
                    </div>
                </section>
            </div>
        </div>

        <FloorSetupPanel
            v-if="floor.selectedSetup"
            :setup="floor.selectedSetup"
            :group="floor.selectedSetup.group_id ? floor.groupOf(floor.selectedSetup.group_id) : null"
            :now="serverNow"
            @close="floor.selectedSetupId = null"
        />

        <PowerSequenceDialog
            :open="sequenceOpen"
            :mode="sequenceMode"
            :now="localNow"
            @close="sequenceOpen = false"
            @start="startSequence"
        />
        <PowerHubsDialog :open="hubsOpen" :now="localNow" @close="hubsOpen = false" />

        <teleport to="body">
            <div class="pointer-events-none fixed inset-x-0 bottom-16 z-[60] flex flex-col items-center gap-2 px-4">
                <div
                    v-for="toast in floor.toasts"
                    :key="toast.id"
                    class="rounded-2xl border bg-slate-900 px-4 py-2.5 text-sm shadow-2xl"
                    :class="{
                        ok: 'border-emerald-500/40 text-emerald-200',
                        info: 'border-slate-700 text-slate-200',
                        error: 'border-rose-500/40 text-rose-200',
                    }[toast.tone]"
                >
                    {{ toast.text }}
                </div>
            </div>
        </teleport>
    </div>
</template>

<script setup>
import { computed, onBeforeUnmount, onMounted, ref } from 'vue'
import {
    ArrowPathIcon,
    BoltIcon,
    Cog6ToothIcon,
    PowerIcon,
    Squares2X2Icon,
} from '@heroicons/vue/24/outline'
import FloorGroupCard from '../components/FloorGroupCard.vue'
import FloorSetupTile from '../components/FloorSetupTile.vue'
import FloorSetupPanel from '../components/FloorSetupPanel.vue'
import PowerSequenceDialog from '../components/PowerSequenceDialog.vue'
import PowerHubsDialog from '../components/PowerHubsDialog.vue'
import { useFloorStore } from '../stores/useFloorStore'
import { usePowerStore } from '../stores/usePowerStore'
import { formatWatts } from '../utils/floorState'

const floor = useFloorStore()
const power = usePowerStore()

const localNow = ref(Date.now())
const serverNow = computed(() => localNow.value + floor.clockOffset)
const sequenceOpen = ref(false)
const sequenceMode = ref('open')
const hubsOpen = ref(false)
let clock = null

const zoneOptions = computed(() => [
    { key: 'all', label: 'Alles' },
    ...floor.groups.map(g => ({ key: `group:${g.id}`, label: g.name })),
    ...floor.zones.map(z => ({ key: `zone:${z.zone}`, label: z.zone })),
])

const showGroups = computed(() => floor.zone === 'all' || floor.zone.startsWith('group:'))
const visibleZones = computed(() => {
    if (floor.zone === 'all') return floor.zones
    if (!floor.zone.startsWith('zone:')) return []
    const name = floor.zone.slice(5)
    return floor.zones.filter(z => z.zone === name)
})

const powerHint = computed(() => {
    if (!power.available) return 'Enkel in de kassa-app'
    if (power.appOutdated) return 'Werk de kassa-app bij'
    if (!power.pairedHubs.length) return 'Koppel eerst een hub (tandwiel)'
    return ''
})

/** Bliksem op de tegel: { on, watts, title } of null. */
function tilePower(setup) {
    if (!power.available || power.appOutdated) return null
    const plugs = power.plugsForSetup(setup.id)
    if (!plugs.length) return null
    const outlets = plugs.map(p => power.outlet(p)).filter(Boolean)
    if (!outlets.length) return null
    const on = outlets.some(o => o.isOn)
    const watts = outlets.reduce((sum, o) => sum + (o.isOn && o.powerW ? o.powerW : 0), 0)
    return {
        on,
        watts: on && watts > 0 ? formatWatts(watts) : '',
        title: plugs.map(p => `${p.name}: ${power.outlet(p)?.isOn ? 'aan' : 'uit'}`).join('\n'),
    }
}

function openSequence(mode) {
    if (!power.sequenceRunning) power.clearSequence()
    sequenceMode.value = mode
    sequenceOpen.value = true
}

function startSequence() {
    if (sequenceMode.value === 'open') {
        power.openFloor()
    } else {
        power.closeFloor(() => floor.shutdownAllPcs())
    }
}

function refresh() {
    floor.fetch()
    power.load().then(() => power.poll())
}

onMounted(() => {
    floor.fetch()
    floor.startPolling()
    power.start()
    clock = window.setInterval(() => { localNow.value = Date.now() }, 1000)
})

onBeforeUnmount(() => {
    floor.stopPolling()
    power.stop()
    window.clearInterval(clock)
})
</script>

<style scoped>
.head-btn {
    display: inline-flex;
    align-items: center;
    gap: 0.5rem;
    border-radius: 1rem;
    border: 1px solid rgb(51 65 85);
    background: rgb(15 23 42);
    padding: 0.625rem 0.875rem;
    font-size: 0.875rem;
    font-weight: 600;
    color: rgb(226 232 240);
    transition: background-color 0.15s;
}
.head-btn:hover:not(:disabled) {
    background: rgb(30 41 59);
}
.head-btn:disabled {
    opacity: 0.45;
    cursor: not-allowed;
}
</style>
