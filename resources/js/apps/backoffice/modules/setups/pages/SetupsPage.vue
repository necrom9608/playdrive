<template>
    <div class="space-y-6">
        <div class="flex flex-wrap items-start justify-between gap-4">
            <div>
                <h1 class="text-3xl font-bold text-white">Speelplekken</h1>
                <p class="mt-2 text-slate-400">
                    Arcadekasten, LAN-PC's en couch-setups met hun QR-code, plus de toestellen (kiosk, Playscope) die eraan hangen.
                </p>
            </div>

            <div class="flex flex-wrap gap-3">
                <button type="button" class="inline-flex items-center gap-2 rounded-2xl border border-slate-700 bg-slate-900 px-4 py-3 text-sm font-semibold text-slate-200 transition hover:bg-slate-800" @click="loadAll">
                    <ArrowPathIcon class="h-5 w-5" /> Vernieuwen
                </button>
                <button v-if="tab === 'setups'" type="button" class="inline-flex items-center gap-2 rounded-2xl bg-blue-600 px-4 py-3 text-sm font-semibold text-white transition hover:bg-blue-500" @click="openSetupForm(null)">
                    <PlusIcon class="h-5 w-5" /> Speelplek
                </button>
                <button v-else type="button" class="inline-flex items-center gap-2 rounded-2xl bg-blue-600 px-4 py-3 text-sm font-semibold text-white transition hover:bg-blue-500" @click="openDeviceForm(null)">
                    <LinkIcon class="h-5 w-5" /> Toestel koppelen
                </button>
            </div>
        </div>

        <div class="inline-flex rounded-2xl border border-slate-800 bg-slate-900 p-1">
            <button
                v-for="t in tabs"
                :key="t.key"
                type="button"
                class="rounded-xl px-4 py-2 text-sm font-semibold transition"
                :class="tab === t.key ? 'bg-slate-700 text-white' : 'text-slate-400 hover:text-white'"
                @click="tab = t.key"
            >
                {{ t.label }} <span class="ml-1 text-slate-500">{{ t.count }}</span>
            </button>
        </div>

        <div v-if="error" class="rounded-2xl border border-red-500/30 bg-red-500/10 px-5 py-4 text-sm text-red-200">{{ error }}</div>

        <!-- Speelplekken -->
        <template v-if="tab === 'setups'">
            <div v-if="!setups.length && !loading" class="rounded-3xl border border-dashed border-slate-800 px-6 py-12 text-center">
                <QrCodeIcon class="mx-auto h-10 w-10 text-slate-600" />
                <p class="mt-3 text-slate-300">Nog geen speelplekken.</p>
                <p class="mt-1 text-sm text-slate-500">Voeg je arcadekasten, LAN-PC's en couch-setups toe. Elke speelplek krijgt een eigen QR-code.</p>
            </div>

            <section v-for="group in groupedSetups" :key="group.zone" class="space-y-3">
                <h2 class="text-sm font-semibold uppercase tracking-wider text-slate-500">{{ group.zone }}</h2>

                <div class="grid gap-4 md:grid-cols-2 2xl:grid-cols-3">
                    <article
                        v-for="setup in group.items"
                        :key="setup.id"
                        class="flex flex-col rounded-3xl border bg-slate-900/80 p-5 shadow-xl shadow-slate-950/20"
                        :class="setup.status === 'archived' ? 'border-slate-800 opacity-60' : 'border-slate-800'"
                    >
                        <div class="flex items-start gap-4">
                            <div class="flex h-12 w-12 shrink-0 items-center justify-center rounded-2xl border border-slate-700 bg-slate-950">
                                <component :is="typeIcon(setup.type)" class="h-6 w-6 text-sky-300" />
                            </div>
                            <div class="min-w-0 flex-1">
                                <h3 class="truncate text-lg font-semibold text-white">{{ setup.name }}</h3>
                                <div class="mt-1 flex flex-wrap items-center gap-2 text-xs">
                                    <span class="text-slate-400">{{ setup.type_label }}</span>
                                    <span v-if="setup.max_players" class="text-slate-500">· max {{ setup.max_players }} spelers</span>
                                    <span v-if="setup.status === 'maintenance'" class="rounded-full border border-amber-500/40 bg-amber-500/10 px-2 py-0.5 font-semibold text-amber-200">Onderhoud</span>
                                    <span v-else-if="setup.status === 'archived'" class="rounded-full border border-slate-700 bg-slate-800 px-2 py-0.5 font-semibold text-slate-300">Gearchiveerd</span>
                                    <span v-else-if="setup.in_use" class="rounded-full border border-emerald-500/40 bg-emerald-500/10 px-2 py-0.5 font-semibold text-emerald-200">Bezet</span>
                                </div>
                            </div>
                        </div>

                        <p v-if="setup.in_use && setup.current_game" class="mt-3 text-sm text-emerald-200/80">Speelt nu: {{ setup.current_game }}</p>

                        <div v-if="setup.games.length" class="mt-4 flex flex-wrap gap-1.5">
                            <span v-for="g in setup.games.slice(0, 6)" :key="g" class="rounded-lg border border-slate-700 bg-slate-950 px-2 py-1 text-xs text-slate-300">{{ g }}</span>
                            <span v-if="setup.games.length > 6" class="px-1 py-1 text-xs text-slate-500">+{{ setup.games.length - 6 }}</span>
                        </div>

                        <div v-if="setup.devices.length" class="mt-4 space-y-1">
                            <div v-for="d in setup.devices" :key="d.id" class="flex items-center gap-2 text-xs text-slate-400">
                                <span class="h-2 w-2 rounded-full" :class="d.online ? 'bg-emerald-400' : 'bg-slate-600'" />
                                {{ d.name || 'Toestel' }}
                            </div>
                        </div>

                        <div class="mt-auto flex flex-wrap gap-2 pt-5">
                            <button type="button" class="inline-flex items-center gap-1.5 rounded-xl border border-slate-700 bg-slate-950 px-3 py-2 text-sm text-slate-200 transition hover:bg-slate-800" @click="openQr(setup)">
                                <QrCodeIcon class="h-4 w-4" /> QR-code
                            </button>
                            <button type="button" class="inline-flex items-center gap-1.5 rounded-xl border border-slate-700 bg-slate-950 px-3 py-2 text-sm text-slate-200 transition hover:bg-slate-800" @click="openSetupForm(setup)">
                                <PencilSquareIcon class="h-4 w-4" /> Bewerken
                            </button>
                        </div>
                    </article>
                </div>
            </section>
        </template>

        <!-- Toestellen -->
        <template v-else>
            <div v-if="!devices.length && !loading" class="rounded-3xl border border-dashed border-slate-800 px-6 py-12 text-center">
                <ComputerDesktopIcon class="mx-auto h-10 w-10 text-slate-600" />
                <p class="mt-3 text-slate-300">Nog geen toestellen gekoppeld.</p>
                <p class="mt-1 text-sm text-slate-500">Open <span class="font-mono text-slate-300">/kiosk</span> of Playscope op het toestel en geef hier de code in die op het scherm verschijnt.</p>
            </div>

            <div v-else class="overflow-hidden rounded-3xl border border-slate-800 bg-slate-900/80">
                <table class="w-full text-left text-sm">
                    <thead class="border-b border-slate-800 text-xs uppercase tracking-wider text-slate-500">
                        <tr>
                            <th class="px-5 py-3 font-semibold">Toestel</th>
                            <th class="px-5 py-3 font-semibold">Type</th>
                            <th class="hidden px-5 py-3 font-semibold md:table-cell">Speelplek</th>
                            <th class="hidden px-5 py-3 font-semibold lg:table-cell">Status</th>
                            <th class="hidden px-5 py-3 font-semibold lg:table-cell">Laatst gezien</th>
                            <th class="px-5 py-3" />
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-800">
                        <tr v-for="d in devices" :key="d.id" :class="!d.is_active ? 'opacity-50' : ''">
                            <td class="px-5 py-4">
                                <div class="flex items-center gap-3">
                                    <span class="h-2.5 w-2.5 shrink-0 rounded-full" :class="d.online ? 'bg-emerald-400' : 'bg-slate-600'" :title="d.online ? 'Online' : 'Offline'" />
                                    <div class="min-w-0">
                                        <div class="font-semibold text-white">{{ d.name || `Toestel #${d.id}` }}</div>
                                        <div class="truncate text-xs text-slate-500">{{ [d.hostname, d.platform, d.app_version && `v${d.app_version}`].filter(Boolean).join(' · ') || '—' }}</div>
                                    </div>
                                </div>
                            </td>
                            <td class="px-5 py-4 text-slate-300">{{ d.type_label }}</td>
                            <td class="hidden px-5 py-4 text-slate-300 md:table-cell">{{ d.setup_name || '—' }}</td>
                            <td class="hidden px-5 py-4 lg:table-cell">
                                <span v-if="!d.is_active" class="text-slate-400">Uitgeschakeld</span>
                                <span v-else-if="d.state?.status === 'playing'" class="text-emerald-300">Speelt{{ d.state.game ? `: ${d.state.game}` : '' }}</span>
                                <span v-else-if="d.state?.status === 'attract'" class="text-slate-300">Attract mode</span>
                                <span v-else-if="d.state?.status" class="text-slate-300">{{ d.state.status }}</span>
                                <span v-else class="text-slate-500">—</span>
                            </td>
                            <td class="hidden px-5 py-4 text-slate-400 lg:table-cell">{{ formatDate(d.last_seen_at) }}</td>
                            <td class="px-5 py-4 text-right">
                                <button type="button" class="inline-flex items-center gap-1.5 rounded-xl border border-slate-700 bg-slate-950 px-3 py-2 text-sm text-slate-200 transition hover:bg-slate-800" @click="openDeviceForm(d)">
                                    <PencilSquareIcon class="h-4 w-4" /> Bewerken
                                </button>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </template>

        <SetupFormModal
            :open="setupForm.open"
            :saving="saving"
            :error="setupForm.error"
            :setup="setupForm.setup"
            :types="types"
            :statuses="statuses"
            :zones="zones"
            @close="setupForm.open = false"
            @submit="saveSetup"
            @delete="removeSetup"
        />

        <DeviceFormModal
            :open="deviceForm.open"
            :saving="saving"
            :error="deviceForm.error"
            :device="deviceForm.device"
            :setups="activeSetups"
            @close="deviceForm.open = false"
            @submit="saveDevice"
            @delete="removeDevice"
        />

        <SetupQrModal
            :open="qr.open"
            :setup="qr.setup"
            :tenant-name="tenantName"
            @close="qr.open = false"
            @regenerate="regenerateQr"
        />
    </div>
</template>

<script setup>
import { computed, markRaw, onBeforeUnmount, onMounted, reactive, ref } from 'vue'
import {
    ArrowPathIcon,
    ComputerDesktopIcon,
    CubeTransparentIcon,
    LinkIcon,
    PencilSquareIcon,
    PlusIcon,
    PuzzlePieceIcon,
    QrCodeIcon,
    RocketLaunchIcon,
    TvIcon,
} from '@heroicons/vue/24/outline'
import SetupFormModal from '../components/SetupFormModal.vue'
import DeviceFormModal from '../components/DeviceFormModal.vue'
import SetupQrModal from '../components/SetupQrModal.vue'
import {
    claimVenueDevice,
    createSetup,
    deleteSetup,
    deleteVenueDevice,
    fetchSetups,
    fetchVenueDevices,
    regenerateSetupQr,
    updateSetup,
    updateVenueDevice,
} from '../services/setupApi'

const TYPE_ICONS = {
    arcade: markRaw(RocketLaunchIcon),
    pc: markRaw(ComputerDesktopIcon),
    console: markRaw(TvIcon),
    vr: markRaw(CubeTransparentIcon),
    pinball: markRaw(PuzzlePieceIcon),
    other: markRaw(PuzzlePieceIcon),
}

const tenantName = window.PlayDrive?.tenantName || ''

const tab = ref('setups')
const loading = ref(false)
const saving = ref(false)
const error = ref('')
const setups = ref([])
const devices = ref([])
const types = ref([])
const statuses = ref([])

const setupForm = reactive({ open: false, setup: null, error: '' })
const deviceForm = reactive({ open: false, device: null, error: '' })
const qr = reactive({ open: false, setup: null })

let refreshTimer = null

const tabs = computed(() => [
    { key: 'setups', label: 'Speelplekken', count: setups.value.filter(s => s.status !== 'archived').length },
    { key: 'devices', label: 'Toestellen', count: devices.value.length },
])

const zones = computed(() => [...new Set(setups.value.map(s => s.zone).filter(Boolean))].sort())

const activeSetups = computed(() => setups.value.filter(s => s.status !== 'archived'))

const groupedSetups = computed(() => {
    const groups = new Map()
    for (const s of setups.value) {
        const zone = s.zone || 'Zonder zone'
        if (!groups.has(zone)) groups.set(zone, [])
        groups.get(zone).push(s)
    }
    return [...groups.entries()]
        .sort(([a], [b]) => (a === 'Zonder zone') - (b === 'Zonder zone') || a.localeCompare(b, 'nl'))
        .map(([zone, items]) => ({ zone, items }))
})

function typeIcon(type) {
    return TYPE_ICONS[type] || TYPE_ICONS.other
}

function formatDate(value) {
    if (!value) return 'Nog nooit'
    return new Date(value).toLocaleString('nl-BE', { dateStyle: 'short', timeStyle: 'short' })
}

async function loadAll({ silent = false } = {}) {
    if (!silent) loading.value = true
    error.value = ''
    try {
        const [setupData, deviceData] = await Promise.all([fetchSetups(), fetchVenueDevices()])
        setups.value = setupData.setups ?? []
        types.value = setupData.types ?? []
        statuses.value = setupData.statuses ?? []
        devices.value = deviceData.devices ?? []
        if (qr.setup) {
            qr.setup = setups.value.find(s => s.id === qr.setup.id) ?? qr.setup
        }
    } catch (e) {
        if (!silent) error.value = e.message || 'Laden mislukt.'
    } finally {
        loading.value = false
    }
}

function openSetupForm(setup) {
    setupForm.setup = setup
    setupForm.error = ''
    setupForm.open = true
}

async function saveSetup(payload) {
    saving.value = true
    setupForm.error = ''
    try {
        if (setupForm.setup?.id) {
            await updateSetup(setupForm.setup.id, payload)
        } else {
            await createSetup(payload)
        }
        setupForm.open = false
        await loadAll({ silent: true })
    } catch (e) {
        setupForm.error = e.message || 'Opslaan mislukt.'
    } finally {
        saving.value = false
    }
}

async function removeSetup() {
    const setup = setupForm.setup
    if (!setup?.id) return
    if (!window.confirm(`"${setup.name}" verwijderen? De QR-code werkt daarna niet meer. Tip: zet hem op "Gearchiveerd" als je de historiek wil bewaren.`)) return
    saving.value = true
    try {
        await deleteSetup(setup.id)
        setupForm.open = false
        await loadAll({ silent: true })
    } catch (e) {
        setupForm.error = e.message || 'Verwijderen mislukt.'
    } finally {
        saving.value = false
    }
}

function openQr(setup) {
    qr.setup = setup
    qr.open = true
}

async function regenerateQr() {
    if (!qr.setup) return
    if (!window.confirm('Nieuwe QR-code maken? De huidige (geprinte) code werkt daarna niet meer.')) return
    try {
        qr.setup = await regenerateSetupQr(qr.setup.id)
        await loadAll({ silent: true })
    } catch (e) {
        error.value = e.message || 'Nieuwe code maken mislukt.'
    }
}

function openDeviceForm(device) {
    deviceForm.device = device
    deviceForm.error = ''
    deviceForm.open = true
}

async function saveDevice(payload) {
    saving.value = true
    deviceForm.error = ''
    try {
        if (deviceForm.device?.id) {
            await updateVenueDevice(deviceForm.device.id, payload)
        } else {
            await claimVenueDevice(payload)
        }
        deviceForm.open = false
        tab.value = 'devices'
        await loadAll({ silent: true })
    } catch (e) {
        deviceForm.error = e.message || 'Opslaan mislukt.'
    } finally {
        saving.value = false
    }
}

async function removeDevice() {
    const device = deviceForm.device
    if (!device?.id) return
    if (!window.confirm(`"${device.name}" ontkoppelen? Het toestel toont daarna opnieuw een koppelcode.`)) return
    saving.value = true
    try {
        await deleteVenueDevice(device.id)
        deviceForm.open = false
        await loadAll({ silent: true })
    } catch (e) {
        deviceForm.error = e.message || 'Ontkoppelen mislukt.'
    } finally {
        saving.value = false
    }
}

onMounted(() => {
    loadAll()
    // Online-status en "bezet" up-to-date houden.
    refreshTimer = window.setInterval(() => {
        if (!setupForm.open && !deviceForm.open) loadAll({ silent: true })
    }, 30000)
})

onBeforeUnmount(() => {
    window.clearInterval(refreshTimer)
})
</script>
