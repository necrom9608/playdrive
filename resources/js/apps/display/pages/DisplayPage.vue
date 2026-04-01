<template>
    <div class="flex min-h-screen items-stretch bg-slate-950 text-white">
        <div class="flex w-full flex-col p-8">
            <div class="mb-8 flex items-start justify-between gap-6">
                <div>
                    <p class="text-sm uppercase tracking-[0.28em] text-blue-300/80">Customer display</p>
                    <h1 class="mt-2 text-4xl font-bold">{{ tenantName }}</h1>
                    <p class="mt-3 text-slate-400">
                        {{ stateLabel }}
                    </p>
                </div>

                <div class="rounded-3xl border border-slate-800 bg-slate-900/80 px-6 py-5 text-right shadow-2xl shadow-slate-950/40">
                    <div class="text-xs uppercase tracking-[0.22em] text-slate-400">Koppelcode</div>
                    <div class="mt-2 text-2xl font-semibold tracking-wide text-white">{{ pairingCode || '...' }}</div>
                    <div class="mt-3 text-xs text-slate-500">Gebruik deze code in backoffice of bij eenmalige POS-koppeling.</div>
                </div>
            </div>

            <div class="flex-1 rounded-[2rem] border border-slate-800 bg-slate-900/60 p-10 shadow-2xl shadow-slate-950/40">
                <div v-if="loading" class="flex h-full items-center justify-center text-2xl text-slate-300">
                    Display initialiseren...
                </div>

                <div v-else-if="error" class="flex h-full flex-col items-center justify-center text-center">
                    <div class="rounded-3xl border border-red-500/30 bg-red-500/10 px-8 py-6">
                        <h2 class="text-2xl font-semibold text-red-200">Display initialiseren mislukt</h2>
                        <p class="mt-3 max-w-2xl text-base text-red-100/80">{{ error }}</p>
                    </div>
                </div>

                <div v-else-if="mode === 'reservation'" class="grid h-full grid-cols-[minmax(0,1.2fr)_420px] gap-8">
                    <section class="rounded-[1.75rem] border border-slate-800 bg-slate-950/60 p-8">
                        <div class="text-sm uppercase tracking-[0.24em] text-blue-300/80">Actieve reservatie</div>
                        <h2 class="mt-4 text-5xl font-bold leading-tight">{{ reservation.name || 'Reservatie' }}</h2>

                        <div class="mt-8 grid gap-4 md:grid-cols-2">
                            <InfoCard label="Datum" :value="reservation.event_date || '-'" />
                            <InfoCard label="Startuur" :value="reservation.event_time || '-'" />
                            <InfoCard label="Aantal personen" :value="totalPersonsLabel" />
                            <InfoCard label="Gemeente" :value="reservation.municipality || '-'" />
                            <InfoCard label="Event type" :value="reservation.event_type?.name || reservation.event_type_name || reservation.event_type || '-'" />
                            <InfoCard label="Catering" :value="reservation.catering_option?.name || reservation.catering_option_name || reservation.catering_option || '-'" />
                        </div>

                        <div class="mt-8 rounded-3xl border border-slate-800 bg-slate-900/70 px-6 py-5">
                            <div class="text-xs uppercase tracking-[0.22em] text-slate-400">Status</div>
                            <div class="mt-2 text-2xl font-semibold">{{ reservation.status || 'nieuw' }}</div>
                        </div>
                    </section>

                    <aside class="rounded-[1.75rem] border border-slate-800 bg-slate-950/60 p-8">
                        <div class="flex items-center justify-between gap-4">
                            <div>
                                <div class="text-sm uppercase tracking-[0.24em] text-blue-300/80">Kassa</div>
                                <h3 class="mt-2 text-3xl font-bold">Bestelling</h3>
                            </div>
                            <div class="rounded-2xl bg-blue-500/10 px-4 py-2 text-sm font-semibold text-blue-200">
                                {{ orderCount }} items
                            </div>
                        </div>

                        <div class="mt-6 space-y-3">
                            <div
                                v-for="item in orderItems"
                                :key="item.line_id || item.id || item.name"
                                class="flex items-center justify-between rounded-2xl border border-slate-800 bg-slate-900/80 px-4 py-4"
                            >
                                <div>
                                    <div class="text-lg font-semibold">{{ item.name }}</div>
                                    <div class="mt-1 text-sm text-slate-400">{{ item.quantity }} × € {{ formatMoney(item.price_incl_vat ?? item.unit_price_incl_vat ?? 0) }}</div>
                                </div>
                                <div class="text-xl font-semibold">€ {{ formatMoney(item.line_total_incl_vat ?? (Number(item.quantity || 0) * Number(item.price_incl_vat ?? item.unit_price_incl_vat ?? 0))) }}</div>
                            </div>

                            <div v-if="!orderItems.length" class="rounded-2xl border border-dashed border-slate-800 px-4 py-8 text-center text-slate-400">
                                Nog geen artikelen toegevoegd.
                            </div>
                        </div>

                        <div class="mt-6 rounded-3xl border border-slate-700 bg-slate-900 px-5 py-5">
                            <div class="flex items-center justify-between text-slate-300">
                                <span>Totaal</span>
                                <span class="text-3xl font-bold text-white">€ {{ formatMoney(orderTotal) }}</span>
                            </div>
                        </div>
                    </aside>
                </div>

                <div v-else class="flex h-full flex-col items-center justify-center text-center">
                    <div class="rounded-[2rem] border border-slate-800 bg-slate-950/50 px-12 py-12 shadow-2xl shadow-slate-950/50">
                        <div class="mx-auto flex h-24 w-24 items-center justify-center rounded-full border border-blue-500/30 bg-blue-500/10 text-5xl">🎮</div>
                        <h2 class="mt-8 text-5xl font-bold">Welkom bij {{ tenantName }}</h2>
                        <p class="mx-auto mt-4 max-w-2xl text-xl text-slate-400">
                            Selecteer een reservatie in de kassa om de klantgegevens en bestelling hier te tonen.
                        </p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>

<script setup>
import axios from 'axios'
import Echo from 'laravel-echo'
import Pusher from 'pusher-js'
import { computed, onBeforeUnmount, onMounted, ref } from 'vue'
import { getDisplayToken, getOrCreateDisplayUuid, storeDisplayToken } from '../shared/device'

window.Pusher = Pusher

const InfoCard = {
    props: ['label', 'value'],
    template: `
        <div class="rounded-3xl border border-slate-800 bg-slate-900/70 px-5 py-5">
            <div class="text-xs uppercase tracking-[0.22em] text-slate-400">{{ label }}</div>
            <div class="mt-2 text-2xl font-semibold text-white">{{ value }}</div>
        </div>
    `,
}

const tenantName = window.PlayDrive?.tenantName || 'PlayDrive'
const loading = ref(true)
const error = ref('')
const pairingCode = ref('')
const mode = ref('standby')
const payload = ref({})
const displayId = ref(null)

let intervalId = null
let echo = null
let channel = null

const reservation = computed(() => payload.value?.reservation ?? {})
const order = computed(() => payload.value?.order ?? {})
const orderItems = computed(() => Array.isArray(order.value?.items) ? order.value.items : [])
const orderTotal = computed(() => Number(order.value?.total_incl_vat ?? 0))
const orderCount = computed(() => orderItems.value.reduce((sum, item) => sum + Number(item.quantity ?? 0), 0))

const totalPersonsLabel = computed(() => {
    if (reservation.value?.total_count != null) {
        return String(reservation.value.total_count)
    }

    const total = Number(reservation.value?.participants_children ?? 0)
        + Number(reservation.value?.participants_adults ?? 0)
        + Number(reservation.value?.participants_supervisors ?? 0)

    return total > 0 ? String(total) : '-'
})

const stateLabel = computed(() => {
    return mode.value === 'reservation'
        ? 'Display toont live de geselecteerde reservatie en huidige kassagegevens.'
        : 'Display staat in stand-by en wacht op een selectie vanuit de kassa.'
})

function formatMoney(value) {
    return Number(value ?? 0).toFixed(2).replace('.', ',')
}

function applyState(data) {
    displayId.value = data?.id ?? displayId.value
    pairingCode.value = data?.pairing_uuid ?? pairingCode.value
    mode.value = data?.current_mode ?? data?.mode ?? 'standby'
    payload.value = data?.current_payload ?? data?.payload ?? {}
}

function createEcho() {
    if (echo) {
        return echo
    }

    echo = new Echo({
        broadcaster: 'reverb',
        key: import.meta.env.VITE_REVERB_APP_KEY,
        wsHost: import.meta.env.VITE_REVERB_HOST,
        wsPort: Number(import.meta.env.VITE_REVERB_PORT ?? 9090),
        wssPort: Number(import.meta.env.VITE_REVERB_PORT ?? 9090),
        forceTLS: (import.meta.env.VITE_REVERB_SCHEME ?? 'http') === 'https',
        enabledTransports: ['ws', 'wss'],
    })

    const connection = echo.connector?.pusher?.connection

    if (connection) {
        connection.bind('connected', () => {
            console.log('[Display] Reverb connected')
        })

        connection.bind('disconnected', () => {
            console.warn('[Display] Reverb disconnected')
        })

        connection.bind('error', (event) => {
            console.error('[Display] Reverb connection error', event)
        })
    }

    return echo
}

function subscribeToChannel() {
    if (!displayId.value) {
        return
    }

    const echoInstance = createEcho()

    if (channel) {
        echoInstance.leave(`display.${displayId.value}`)
        channel = null
    }

    channel = echoInstance
        .channel(`display.${displayId.value}`)
        .subscribed(() => {
            console.log(`[Display] subscribed to display.${displayId.value}`)
        })
        .error((event) => {
            console.error('[Display] channel error', event)
        })
        .listen('.display.state.updated', (event) => {
            console.log('[Display] live event ontvangen', event)

            mode.value = event?.mode ?? 'standby'
            payload.value = event?.payload?.current_payload ?? event?.payload ?? {}
            error.value = ''
        })
}

async function loadState() {
    try {
        const response = await axios.get('/api/display/state', {
            params: {
                device_uuid: getOrCreateDisplayUuid(),
                device_token: getDisplayToken(),
            },
        })

        applyState(response.data?.data ?? {})
        error.value = ''

        if (displayId.value && !channel) {
            subscribeToChannel()
        }
    } catch (err) {
        error.value = err?.response?.data?.message ?? 'Kon displaystatus niet ophalen.'
    } finally {
        loading.value = false
    }
}

async function bootstrap() {
    loading.value = true
    error.value = ''

    try {
        const response = await axios.post('/api/display/bootstrap', {
            role: 'display',
            device_uuid: getOrCreateDisplayUuid(),
            device_token: getDisplayToken(),
            name: 'Customer Display',
        })

        if (response.data?.data?.device_token) {
            storeDisplayToken(response.data.data.device_token)
        }

        applyState(response.data?.data ?? {})
        await loadState()
        subscribeToChannel()

        intervalId = window.setInterval(loadState, 15000)
    } catch (err) {
        loading.value = false
        error.value = err?.response?.data?.message ?? 'Kon de display niet initialiseren.'
    }
}

onMounted(bootstrap)

onBeforeUnmount(() => {
    if (intervalId) {
        clearInterval(intervalId)
    }

    if (echo && displayId.value) {
        echo.leave(`display.${displayId.value}`)
    }
})
</script>
