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
                    <p class="mt-1 text-sm text-slate-400">Kies een POS-terminal en wijs daar een display aan toe.</p>
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
                        <option v-for="device in displays" :key="device.id" :value="String(device.id)">
                            {{ device.name || `Display #${device.id}` }}
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
                        <p class="mt-1 text-sm text-slate-400">Customer displays met hun koppelcode.</p>
                    </div>
                    <div class="text-sm text-slate-500">{{ displays.length }} toestellen</div>
                </div>

                <div class="space-y-4">
                    <article v-for="device in displays" :key="device.id" class="rounded-3xl border border-slate-800 bg-slate-950/60 p-5">
                        <div class="flex flex-wrap items-start justify-between gap-4">
                            <div class="min-w-0 flex-1">
                                <div class="flex flex-wrap items-center gap-3">
                                    <h3 class="text-lg font-semibold text-white">{{ device.name || `Display #${device.id}` }}</h3>
                                    <span class="rounded-full border px-3 py-1 text-xs font-semibold" :class="isOnline(device.last_seen_at) ? 'border-emerald-500/40 bg-emerald-500/10 text-emerald-200' : 'border-slate-700 bg-slate-800 text-slate-300'">
                                        {{ isOnline(device.last_seen_at) ? 'Online' : 'Offline' }}
                                    </span>
                                </div>
                                <p class="mt-2 break-all text-xs text-slate-500">Device UUID: {{ device.device_uuid }}</p>
                                <p class="mt-1 break-all text-sm text-slate-300">Koppelcode: {{ device.pairing_uuid }}</p>
                                <p class="mt-1 text-xs text-slate-500">Laatst gezien: {{ formatDate(device.last_seen_at) }}</p>
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
import { onMounted, ref } from 'vue'

const loading = ref(false)
const saving = ref(false)
const error = ref('')
const displays = ref([])
const posDevices = ref([])
const selectedPosId = ref('')
const selectedDisplayId = ref('')

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

async function loadDevices() {
    loading.value = true
    error.value = ''

    try {
        const response = await axios.get('/api/backoffice/devices')
        displays.value = response.data?.displays ?? []
        posDevices.value = response.data?.pos_devices ?? []
    } catch (err) {
        console.error(err)
        error.value = err?.response?.data?.message ?? 'Kon devices niet laden.'
    } finally {
        loading.value = false
    }
}

async function pairDevices() {
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
