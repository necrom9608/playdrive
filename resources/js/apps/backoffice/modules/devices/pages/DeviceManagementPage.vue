<template>
    <div class="space-y-6">
        <div class="flex flex-wrap items-start justify-between gap-4">
            <div>
                <h1 class="text-3xl font-bold text-white">Displays &amp; POS-terminals</h1>
                <p class="mt-2 max-w-3xl text-slate-400">
                    Beheer welke customer display aan welke kassa gekoppeld is. Nieuwe toestellen verschijnen hier automatisch zodra
                    ze één keer geopend zijn op <span class="font-semibold text-slate-200">/display</span> of <span class="font-semibold text-slate-200">/frontdesk/pos</span>.
                </p>
            </div>

            <button
                type="button"
                class="rounded-xl border border-slate-700 bg-slate-900 px-4 py-3 text-sm font-semibold text-slate-100 transition hover:border-slate-600 hover:bg-slate-800"
                @click="loadDevices"
            >
                Vernieuwen
            </button>
        </div>

        <div v-if="error" class="rounded-2xl border border-rose-500/30 bg-rose-500/10 px-4 py-3 text-sm text-rose-200">
            {{ error }}
        </div>

        <section class="grid gap-6 xl:grid-cols-[1.15fr_0.85fr]">
            <div class="rounded-3xl border border-slate-800 bg-slate-900/90 p-5 shadow-xl shadow-slate-950/30">
                <div class="mb-5 flex items-start justify-between gap-4">
                    <div>
                        <h2 class="text-xl font-semibold text-white">Koppelingen</h2>
                        <p class="mt-1 text-sm text-slate-400">Koppel een POS-terminal aan één specifieke customer display.</p>
                    </div>
                </div>

                <div class="grid gap-4 md:grid-cols-2">
                    <label class="space-y-2 text-sm">
                        <span class="font-medium text-slate-200">POS-terminal</span>
                        <select v-model="pairForm.pos_device_id" class="w-full rounded-2xl border border-slate-700 bg-slate-950 px-4 py-3 text-slate-100 outline-none transition focus:border-blue-500">
                            <option value="">Selecteer een POS-terminal</option>
                            <option v-for="item in posDevices" :key="item.id" :value="String(item.id)">
                                {{ item.name }}<span v-if="item.display_name"> · gekoppeld aan {{ item.display_name }}</span>
                            </option>
                        </select>
                    </label>

                    <label class="space-y-2 text-sm">
                        <span class="font-medium text-slate-200">Display</span>
                        <select v-model="pairForm.display_device_id" class="w-full rounded-2xl border border-slate-700 bg-slate-950 px-4 py-3 text-slate-100 outline-none transition focus:border-blue-500">
                            <option value="">Selecteer een display</option>
                            <option v-for="item in displays" :key="item.id" :value="String(item.id)">
                                {{ item.name }} · {{ item.pairing_uuid }}
                            </option>
                        </select>
                    </label>
                </div>

                <div class="mt-4 flex flex-wrap gap-3">
                    <button
                        type="button"
                        class="rounded-xl bg-blue-600 px-4 py-3 text-sm font-semibold text-white transition hover:bg-blue-500 disabled:cursor-not-allowed disabled:opacity-60"
                        :disabled="savingPairing || !pairForm.pos_device_id || !pairForm.display_device_id"
                        @click="handlePair"
                    >
                        {{ savingPairing ? 'Koppelen...' : 'Koppel display' }}
                    </button>

                    <div class="rounded-xl border border-slate-800 bg-slate-950/70 px-4 py-3 text-sm text-slate-300">
                        Tip: open de display eerst op het toestel zelf zodat de koppelcode hier verschijnt.
                    </div>
                </div>
            </div>

            <div class="rounded-3xl border border-slate-800 bg-slate-900/90 p-5 shadow-xl shadow-slate-950/30">
                <div class="mb-4">
                    <h2 class="text-xl font-semibold text-white">Snelle status</h2>
                    <p class="mt-1 text-sm text-slate-400">Overzicht van alle geregistreerde toestellen.</p>
                </div>

                <div class="grid gap-4 sm:grid-cols-3 xl:grid-cols-1">
                    <div class="rounded-2xl border border-slate-800 bg-slate-950/60 p-4">
                        <div class="text-sm text-slate-400">Displays</div>
                        <div class="mt-2 text-3xl font-bold text-white">{{ displays.length }}</div>
                    </div>
                    <div class="rounded-2xl border border-slate-800 bg-slate-950/60 p-4">
                        <div class="text-sm text-slate-400">POS-terminals</div>
                        <div class="mt-2 text-3xl font-bold text-white">{{ posDevices.length }}</div>
                    </div>
                    <div class="rounded-2xl border border-slate-800 bg-slate-950/60 p-4">
                        <div class="text-sm text-slate-400">Actieve koppelingen</div>
                        <div class="mt-2 text-3xl font-bold text-white">{{ pairedCount }}</div>
                    </div>
                </div>
            </div>
        </section>

        <section class="grid gap-6 xl:grid-cols-2">
            <div class="rounded-3xl border border-slate-800 bg-slate-900/90 p-5 shadow-xl shadow-slate-950/30">
                <div class="mb-4 flex items-start justify-between gap-4">
                    <div>
                        <h2 class="text-xl font-semibold text-white">Displays</h2>
                        <p class="mt-1 text-sm text-slate-400">Naam, koppelcode en laatste activiteit van elke display.</p>
                    </div>
                </div>

                <div v-if="loading" class="rounded-2xl border border-slate-800 bg-slate-950/50 px-4 py-8 text-center text-sm text-slate-400">
                    Toestellen laden...
                </div>

                <div v-else-if="!displays.length" class="rounded-2xl border border-dashed border-slate-700 bg-slate-950/40 px-4 py-8 text-center text-sm text-slate-400">
                    Nog geen displays geregistreerd. Open eerst <span class="font-semibold text-slate-200">/display</span> op een toestel.
                </div>

                <div v-else class="space-y-4">
                    <article v-for="display in displays" :key="display.id" class="rounded-2xl border border-slate-800 bg-slate-950/60 p-4">
                        <div class="flex flex-wrap items-start justify-between gap-4">
                            <div>
                                <div class="flex flex-wrap items-center gap-2">
                                    <h3 class="text-lg font-semibold text-white">{{ display.name }}</h3>
                                    <span class="rounded-full px-3 py-1 text-xs font-semibold" :class="display.is_active ? 'bg-emerald-500/15 text-emerald-300 ring-1 ring-emerald-500/30' : 'bg-slate-500/15 text-slate-300 ring-1 ring-slate-500/30'">
                                        {{ display.is_active ? 'Actief' : 'Inactief' }}
                                    </span>
                                </div>
                                <div class="mt-2 space-y-1 text-sm text-slate-400">
                                    <div><span class="text-slate-500">Koppelcode:</span> <span class="font-mono text-slate-200">{{ display.pairing_uuid }}</span></div>
                                    <div><span class="text-slate-500">Mode:</span> {{ display.current_mode || 'standby' }}</div>
                                    <div><span class="text-slate-500">Laatst gezien:</span> {{ formatDateTime(display.last_seen_at) }}</div>
                                    <div><span class="text-slate-500">Laatst gesynchroniseerd:</span> {{ formatDateTime(display.last_synced_at) }}</div>
                                    <div><span class="text-slate-500">Gekoppelde POS:</span> {{ display.paired_pos_names.length ? display.paired_pos_names.join(', ') : 'Geen' }}</div>
                                </div>
                            </div>

                            <div class="flex flex-wrap gap-2">
                                <button
                                    type="button"
                                    class="rounded-xl border border-slate-700 bg-slate-900 px-4 py-2 text-sm font-semibold text-slate-200 transition hover:bg-slate-800"
                                    @click="renameDisplay(display)"
                                >
                                    Hernoemen
                                </button>
                                <button
                                    type="button"
                                    class="rounded-xl border border-rose-500/40 bg-rose-500/10 px-4 py-2 text-sm font-semibold text-rose-200 transition hover:bg-rose-500/20"
                                    @click="handleDeleteDisplay(display)"
                                >
                                    Verwijderen
                                </button>
                            </div>
                        </div>
                    </article>
                </div>
            </div>

            <div class="rounded-3xl border border-slate-800 bg-slate-900/90 p-5 shadow-xl shadow-slate-950/30">
                <div class="mb-4 flex items-start justify-between gap-4">
                    <div>
                        <h2 class="text-xl font-semibold text-white">POS-terminals</h2>
                        <p class="mt-1 text-sm text-slate-400">Beheer kassatoestellen en hun huidige displaykoppeling.</p>
                    </div>
                </div>

                <div v-if="loading" class="rounded-2xl border border-slate-800 bg-slate-950/50 px-4 py-8 text-center text-sm text-slate-400">
                    Toestellen laden...
                </div>

                <div v-else-if="!posDevices.length" class="rounded-2xl border border-dashed border-slate-700 bg-slate-950/40 px-4 py-8 text-center text-sm text-slate-400">
                    Nog geen POS-terminals geregistreerd. Open eerst <span class="font-semibold text-slate-200">/frontdesk/pos</span> op een kassatoestel.
                </div>

                <div v-else class="space-y-4">
                    <article v-for="pos in posDevices" :key="pos.id" class="rounded-2xl border border-slate-800 bg-slate-950/60 p-4">
                        <div class="flex flex-wrap items-start justify-between gap-4">
                            <div>
                                <div class="flex flex-wrap items-center gap-2">
                                    <h3 class="text-lg font-semibold text-white">{{ pos.name }}</h3>
                                    <span class="rounded-full px-3 py-1 text-xs font-semibold" :class="pos.is_active ? 'bg-emerald-500/15 text-emerald-300 ring-1 ring-emerald-500/30' : 'bg-slate-500/15 text-slate-300 ring-1 ring-slate-500/30'">
                                        {{ pos.is_active ? 'Actief' : 'Inactief' }}
                                    </span>
                                </div>
                                <div class="mt-2 space-y-1 text-sm text-slate-400">
                                    <div><span class="text-slate-500">Display:</span> {{ pos.display_name || 'Niet gekoppeld' }}</div>
                                    <div><span class="text-slate-500">Displaycode:</span> <span class="font-mono text-slate-200">{{ pos.display_pairing_uuid || '—' }}</span></div>
                                    <div><span class="text-slate-500">Laatst gezien:</span> {{ formatDateTime(pos.last_seen_at) }}</div>
                                </div>
                            </div>

                            <div class="flex flex-wrap gap-2">
                                <button
                                    type="button"
                                    class="rounded-xl border border-slate-700 bg-slate-900 px-4 py-2 text-sm font-semibold text-slate-200 transition hover:bg-slate-800"
                                    @click="renamePos(pos)"
                                >
                                    Hernoemen
                                </button>
                                <button
                                    type="button"
                                    class="rounded-xl border border-rose-500/40 bg-rose-500/10 px-4 py-2 text-sm font-semibold text-rose-200 transition hover:bg-rose-500/20 disabled:cursor-not-allowed disabled:opacity-50"
                                    :disabled="!pos.display_device_id"
                                    @click="handleUnpair(pos)"
                                >
                                    Ontkoppelen
                                </button>
                                <button
                                    type="button"
                                    class="rounded-xl border border-rose-500/40 bg-rose-500/10 px-4 py-2 text-sm font-semibold text-rose-200 transition hover:bg-rose-500/20"
                                    @click="handleDeletePos(pos)"
                                >
                                    Verwijderen
                                </button>
                            </div>
                        </div>
                    </article>
                </div>
            </div>
        </section>
    </div>
</template>

<script setup>
import { computed, onMounted, reactive, ref } from 'vue'
import { deleteDisplayDevice, deletePosDevice, fetchDevices, pairDevice, unpairPosDevice, updateDisplayDevice, updatePosDevice } from '../services/deviceApi'

const displays = ref([])
const posDevices = ref([])
const loading = ref(false)
const error = ref('')
const savingPairing = ref(false)

const pairForm = reactive({
    pos_device_id: '',
    display_device_id: '',
})

const pairedCount = computed(() => posDevices.value.filter(item => item.display_device_id).length)

async function loadDevices() {
    loading.value = true
    error.value = ''

    try {
        const response = await fetchDevices()
        displays.value = response.displays ?? []
        posDevices.value = response.pos_devices ?? []
    } catch (err) {
        console.error(err)
        error.value = err?.data?.message ?? err?.message ?? 'Kon devices niet laden.'
    } finally {
        loading.value = false
    }
}

async function handlePair() {
    savingPairing.value = true
    error.value = ''

    try {
        await pairDevice({
            pos_device_id: Number(pairForm.pos_device_id),
            display_device_id: Number(pairForm.display_device_id),
        })

        await loadDevices()
    } catch (err) {
        console.error(err)
        error.value = err?.data?.message ?? err?.message ?? 'Koppelen mislukt.'
    } finally {
        savingPairing.value = false
    }
}

async function handleUnpair(pos) {
    if (!window.confirm(`Display loskoppelen van ${pos.name}?`)) {
        return
    }

    error.value = ''

    try {
        await unpairPosDevice(pos.id)
        await loadDevices()
    } catch (err) {
        console.error(err)
        error.value = err?.data?.message ?? err?.message ?? 'Ontkoppelen mislukt.'
    }
}


async function handleDeleteDisplay(display) {
    if (!window.confirm(`Display ${display.name} verwijderen? Eventuele koppelingen met POS worden ook losgemaakt.`)) {
        return
    }

    error.value = ''

    try {
        await deleteDisplayDevice(display.id)
        await loadDevices()
    } catch (err) {
        console.error(err)
        error.value = err?.data?.message ?? err?.message ?? 'Display verwijderen mislukt.'
    }
}

async function handleDeletePos(pos) {
    if (!window.confirm(`POS-terminal ${pos.name} verwijderen?`)) {
        return
    }

    error.value = ''

    try {
        await deletePosDevice(pos.id)
        await loadDevices()
    } catch (err) {
        console.error(err)
        error.value = err?.data?.message ?? err?.message ?? 'POS-terminal verwijderen mislukt.'
    }
}

async function renameDisplay(display) {
    const nextName = window.prompt('Nieuwe naam voor deze display:', display.name || '')

    if (nextName === null) {
        return
    }

    const trimmed = nextName.trim()

    if (!trimmed) {
        return
    }

    error.value = ''

    try {
        await updateDisplayDevice(display.id, {
            name: trimmed,
            is_active: display.is_active,
        })
        await loadDevices()
    } catch (err) {
        console.error(err)
        error.value = err?.data?.message ?? err?.message ?? 'Display hernoemen mislukt.'
    }
}

async function renamePos(pos) {
    const nextName = window.prompt('Nieuwe naam voor deze POS-terminal:', pos.name || '')

    if (nextName === null) {
        return
    }

    const trimmed = nextName.trim()

    if (!trimmed) {
        return
    }

    error.value = ''

    try {
        await updatePosDevice(pos.id, {
            name: trimmed,
            is_active: pos.is_active,
        })
        await loadDevices()
    } catch (err) {
        console.error(err)
        error.value = err?.data?.message ?? err?.message ?? 'POS-terminal hernoemen mislukt.'
    }
}

function formatDateTime(value) {
    if (!value) {
        return 'Nog niet gezien'
    }

    try {
        return new Intl.DateTimeFormat('nl-BE', {
            dateStyle: 'short',
            timeStyle: 'short',
        }).format(new Date(value))
    } catch {
        return value
    }
}

onMounted(loadDevices)
</script>
