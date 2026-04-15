<template>
    <div class="space-y-6">
        <div class="flex flex-wrap items-start justify-between gap-4">
            <div>
                <h1 class="text-3xl font-bold text-white">Displays & POS</h1>
                <p class="mt-2 text-slate-400">
                    Beheer customer displays, POS-terminals en de koppeling tussen beide toestellen.
                </p>
            </div>

            <button
                type="button"
                class="rounded-2xl border border-slate-700 bg-slate-900 px-4 py-3 text-sm font-semibold text-slate-200 transition hover:border-slate-600 hover:bg-slate-800"
                @click="loadDevices"
            >
                Vernieuwen
            </button>
        </div>

        <div v-if="error" class="rounded-2xl border border-red-500/30 bg-red-500/10 px-5 py-4 text-sm text-red-200">
            {{ error }}
        </div>

        <section class="rounded-3xl border border-slate-800 bg-slate-900/80 p-6 shadow-xl shadow-slate-950/20">
            <div class="flex flex-wrap items-center justify-between gap-4">
                <div>
                    <h2 class="text-xl font-semibold text-white">Koppeling beheren</h2>
                    <p class="mt-1 text-sm text-slate-400">Kies een POS-terminal en wijs daar een display aan toe op basis van naam en status.</p>
                </div>

                <div class="flex flex-wrap items-center gap-3">
                    <select v-model="selectedPosId" class="rounded-2xl border border-slate-700 bg-slate-950 px-4 py-3 text-sm text-white">
                        <option value="">Selecteer POS</option>
                        <option v-for="device in posDevices" :key="device.id" :value="String(device.id)">
                            {{ device.name || `POS #${device.id}` }}
                        </option>
                    </select>

                    <select v-model="selectedDisplayId" class="rounded-2xl border border-slate-700 bg-slate-950 px-4 py-3 text-sm text-white">
                        <option value="">Selecteer display</option>
                        <option v-for="device in selectableDisplays" :key="device.id" :value="String(device.id)">
                            {{ displayOptionLabel(device) }}
                        </option>
                    </select>

                    <button
                        type="button"
                        class="rounded-2xl bg-blue-600 px-4 py-3 text-sm font-semibold text-white transition hover:bg-blue-500 disabled:cursor-not-allowed disabled:bg-slate-700"
                        :disabled="saving || !selectedPosId || !selectedDisplayId"
                        @click="pairDevices"
                    >
                        Koppelen
                    </button>
                </div>
            </div>

            <div v-if="selectedPosDevice" class="mt-4 flex flex-wrap items-center gap-3 rounded-2xl border border-slate-800 bg-slate-950/60 px-4 py-3 text-sm text-slate-300">
                <span>
                    Huidige display voor <span class="font-semibold text-white">{{ selectedPosDevice.name || `POS #${selectedPosDevice.id}` }}</span>:
                    <span class="font-semibold text-white">{{ selectedPosDevice.display_device?.name || 'geen display gekoppeld' }}</span>
                </span>
                <span v-if="selectedPosDevice.display_device && selectedPosDevice.display_device.last_seen_at" class="text-slate-500">
                    Laatst gezien: {{ formatDate(selectedPosDevice.display_device.last_seen_at) }}
                </span>
            </div>
        </section>

        <div class="grid gap-6 xl:grid-cols-2">
            <section class="rounded-3xl border border-slate-800 bg-slate-900/80 p-6 shadow-xl shadow-slate-950/20">
                <div class="mb-4 flex items-center justify-between gap-4">
                    <div>
                        <h2 class="text-xl font-semibold text-white">POS-terminals</h2>
                        <p class="mt-1 text-sm text-slate-400">Automatisch geregistreerde kassa-apparaten.</p>
                    </div>
                    <div class="text-sm text-slate-500">{{ posDevices.length }} toestellen</div>
                </div>

                <div class="space-y-4">
                    <article v-for="device in posDevices" :key="device.id" class="rounded-3xl border border-slate-800 bg-slate-950/60 p-5">
                        <div class="flex flex-wrap items-start justify-between gap-4">
                            <div class="min-w-0 flex-1">
                                <div class="flex flex-wrap items-center gap-3">
                                    <h3 class="text-lg font-semibold text-white">{{ device.name || `POS #${device.id}` }}</h3>
                                    <span class="rounded-full border px-3 py-1 text-xs font-semibold" :class="isOnline(device.last_seen_at) ? 'border-emerald-500/40 bg-emerald-500/10 text-emerald-200' : 'border-slate-700 bg-slate-800 text-slate-300'">
                                        {{ isOnline(device.last_seen_at) ? 'Online' : 'Offline' }}
                                    </span>
                                </div>
                                <p class="mt-2 break-all text-xs text-slate-500">UUID: {{ device.device_uuid }}</p>
                                <p class="mt-2 text-sm text-slate-400">Gekoppeld aan: <span class="font-medium text-white">{{ device.display_device?.name || 'Geen display gekoppeld' }}</span></p>
                                <p class="mt-1 text-xs text-slate-500">Laatst gezien: {{ formatDate(device.last_seen_at) }}</p>
                            </div>

                            <div class="flex flex-wrap gap-2">
                                <button type="button" class="rounded-2xl border border-slate-700 bg-slate-900 px-3 py-2 text-sm text-slate-200" @click="renamePos(device)">Hernoemen</button>
                                <button v-if="device.display_device_id" type="button" class="rounded-2xl border border-amber-500/30 bg-amber-500/10 px-3 py-2 text-sm text-amber-200" @click="unpair(device)">Ontkoppelen</button>
                                <button type="button" class="rounded-2xl border border-red-500/30 bg-red-500/10 px-3 py-2 text-sm text-red-200" @click="deletePos(device)">Verwijderen</button>
                            </div>
                        </div>
                    </article>

                    <div v-if="!posDevices.length && !loading" class="rounded-2xl border border-dashed border-slate-800 px-4 py-8 text-center text-slate-400">
                        Nog geen POS-terminals geregistreerd. Open eerst de POS-module op een toestel.
                    </div>
                </div>
            </section>

            <section class="rounded-3xl border border-slate-800 bg-slate-900/80 p-6 shadow-xl shadow-slate-950/20">
                <div class="mb-4 flex items-center justify-between gap-4">
                    <div>
                        <h2 class="text-xl font-semibold text-white">Displays</h2>
                        <p class="mt-1 text-sm text-slate-400">Displays worden nu primair op naam beheerd. Technische codes blijven beschikbaar als reserve.</p>
                    </div>
                    <div class="text-sm text-slate-500">{{ displays.length }} toestellen</div>
                </div>

                <div class="space-y-4">
                    <article v-for="device in displays" :key="device.id" class="rounded-3xl border border-slate-800 bg-slate-950/60 p-5">
                        <div class="flex flex-wrap items-start justify-between gap-4">
                            <div class="min-w-0 flex-1">
                                <div class="flex flex-wrap items-center gap-3">
                                    <h3 class="text-lg font-semibold text-white">{{ device.name || `Display #${device.id}` }}</h3>
                                    <span class="rounded-full border px-3 py-1 text-xs font-semibold" :class="displayStatusClass(device)">
                                        {{ displayStatusLabel(device) }}
                                    </span>
                                </div>
                                <p class="mt-2 text-sm text-slate-400">
                                    Gekoppeld aan:
                                    <span class="font-medium text-white">{{ pairedPosLabel(device) }}</span>
                                </p>
                                <p class="mt-1 text-xs text-slate-500">Laatst gezien: {{ formatDate(device.last_seen_at) }}</p>
                                <details class="mt-3 rounded-2xl border border-slate-800 bg-slate-900/70 px-3 py-2 text-xs text-slate-400">
                                    <summary class="cursor-pointer select-none font-medium text-slate-300">Technische info</summary>
                                    <div class="mt-2 space-y-1 break-all">
                                        <p>Device UUID: {{ device.device_uuid }}</p>
                                        <p>Koppelcode: {{ device.pairing_uuid }}</p>
                                    </div>
                                </details>
                            </div>

                            <div class="flex flex-wrap gap-2">
                                <button type="button" class="rounded-2xl border border-slate-700 bg-slate-900 px-3 py-2 text-sm text-slate-200" @click="renameDisplay(device)">Hernoemen</button>
                                <button type="button" class="rounded-2xl border border-red-500/30 bg-red-500/10 px-3 py-2 text-sm text-red-200" @click="deleteDisplay(device)">Verwijderen</button>
                            </div>
                        </div>
                    </article>

                    <div v-if="!displays.length && !loading" class="rounded-2xl border border-dashed border-slate-800 px-4 py-8 text-center text-slate-400">
                        Nog geen displays geregistreerd. Open eerst /display op een toestel.
                    </div>
                </div>
            </section>
        </div>
    </div>
</template>

<script setup>
import axios from 'axios'
import { computed, onMounted, ref } from 'vue'

const loading = ref(false)
const saving = ref(false)
const error = ref('')
const displays = ref([])
const posDevices = ref([])
const selectedPosId = ref('')
const selectedDisplayId = ref('')

const selectedPosDevice = computed(() => posDevices.value.find((device) => String(device.id) === selectedPosId.value) ?? null)

const selectableDisplays = computed(() => {
    const currentDisplayId = selectedPosDevice.value?.display_device_id

    return displays.value.filter((device) => {
        if (!device.paired_pos_count) {
            return true
        }

        return Number(device.id) === Number(currentDisplayId)
    })
})

function formatDate(value) {
    if (!value) {
        return 'Nog nooit'
    }

    return new Date(value).toLocaleString('nl-BE')
}

function isOnline(value) {
    if (!value) {
        return false
    }

    return Date.now() - new Date(value).getTime() < 120000
}

function pairedPosLabel(device) {
    if (!device.pos_devices?.length) {
        return 'Niet gekoppeld'
    }

    return device.pos_devices.map((pos) => pos.name || `POS #${pos.id}`).join(', ')
}

function displayStatusLabel(device) {
    if (!isOnline(device.last_seen_at)) {
        return 'Offline'
    }

    if (device.paired_pos_count) {
        return 'Gekoppeld'
    }

    return 'Beschikbaar'
}

function displayStatusClass(device) {
    if (!isOnline(device.last_seen_at)) {
        return 'border-slate-700 bg-slate-800 text-slate-300'
    }

    if (device.paired_pos_count) {
        return 'border-blue-500/40 bg-blue-500/10 text-blue-200'
    }

    return 'border-emerald-500/40 bg-emerald-500/10 text-emerald-200'
}

function displayOptionLabel(device) {
    const name = device.name || `Display #${device.id}`
    const status = displayStatusLabel(device)
    const pairedLabel = device.paired_pos_count ? ` · ${pairedPosLabel(device)}` : ''

    return `${name} — ${status}${pairedLabel}`
}

async function loadDevices() {
    loading.value = true
    error.value = ''

    try {
        const response = await axios.get('/api/backoffice/devices')
        displays.value = response.data?.displays ?? []
        posDevices.value = response.data?.pos_devices ?? []

        if (selectedPosId.value && !posDevices.value.some((device) => String(device.id) === selectedPosId.value)) {
            selectedPosId.value = ''
        }

        if (selectedDisplayId.value && !displays.value.some((device) => String(device.id) === selectedDisplayId.value)) {
            selectedDisplayId.value = ''
        }
    } catch (err) {
        console.error(err)
        error.value = err?.response?.data?.message ?? 'Kon devices niet laden.'
    } finally {
        loading.value = false
    }
}

async function pairDevices() {
    const posDevice = selectedPosDevice.value
    const displayDevice = displays.value.find((device) => String(device.id) === selectedDisplayId.value)

    if (!posDevice || !displayDevice) {
        return
    }

    const previousPosLabel = displayDevice.pos_devices?.length
        ? displayDevice.pos_devices.map((pos) => pos.name || `POS #${pos.id}`).join(', ')
        : null

    if (previousPosLabel && Number(posDevice.display_device_id) !== Number(displayDevice.id)) {
        const confirmed = window.confirm(
            `${displayDevice.name || `Display #${displayDevice.id}`} is momenteel gekoppeld aan ${previousPosLabel}. ` +
            `Als je doorgaat, wordt die koppeling vervangen.`
        )

        if (!confirmed) {
            return
        }
    }

    saving.value = true
    error.value = ''

    try {
        await axios.post('/api/backoffice/devices/pair', {
            pos_device_id: Number(selectedPosId.value),
            display_device_id: Number(selectedDisplayId.value),
        })
        await loadDevices()
    } catch (err) {
        console.error(err)
        error.value = err?.response?.data?.message ?? 'Kon devices niet koppelen.'
    } finally {
        saving.value = false
    }
}

async function unpair(device) {
    if (!window.confirm(`Koppeling van ${device.name || `POS #${device.id}`} verwijderen?`)) {
        return
    }

    try {
        await axios.post(`/api/backoffice/devices/${device.id}/unpair`)
        await loadDevices()
    } catch (err) {
        console.error(err)
        error.value = err?.response?.data?.message ?? 'Kon koppeling niet verwijderen.'
    }
}

async function renamePos(device) {
    const name = window.prompt('Nieuwe naam voor deze POS-terminal:', device.name || '')

    if (!name) {
        return
    }

    try {
        await axios.put(`/api/backoffice/devices/pos/${device.id}`, { name })
        await loadDevices()
    } catch (err) {
        console.error(err)
        error.value = err?.response?.data?.message ?? 'Kon POS niet hernoemen.'
    }
}

async function renameDisplay(device) {
    const name = window.prompt('Nieuwe naam voor deze display:', device.name || '')

    if (!name) {
        return
    }

    try {
        await axios.put(`/api/backoffice/devices/display/${device.id}`, { name })
        await loadDevices()
    } catch (err) {
        console.error(err)
        error.value = err?.response?.data?.message ?? 'Kon display niet hernoemen.'
    }
}

async function deletePos(device) {
    if (!window.confirm(`POS-terminal ${device.name || `POS #${device.id}`} verwijderen?`)) {
        return
    }

    try {
        await axios.delete(`/api/backoffice/devices/pos/${device.id}`)
        await loadDevices()
    } catch (err) {
        console.error(err)
        error.value = err?.response?.data?.message ?? 'Kon POS-terminal niet verwijderen.'
    }
}

async function deleteDisplay(device) {
    if (!window.confirm(`Display ${device.name || `Display #${device.id}`} verwijderen? Gekoppelde POS-terminals worden eerst losgekoppeld.`)) {
        return
    }

    try {
        await axios.delete(`/api/backoffice/devices/display/${device.id}`)
        await loadDevices()
    } catch (err) {
        console.error(err)
        error.value = err?.response?.data?.message ?? 'Kon display niet verwijderen.'
    }
}

onMounted(loadDevices)
</script>
