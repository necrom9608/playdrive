<template>
    <div class="min-h-screen bg-slate-950 text-white">
        <div class="mx-auto flex min-h-screen w-full max-w-md flex-col px-5 py-6">
            <div v-if="loading" class="flex flex-1 items-center justify-center text-center text-xl text-slate-300">
                Display initialiseren...
            </div>

            <div v-else-if="error" class="flex flex-1 flex-col items-center justify-center text-center">
                <div class="w-full rounded-[2rem] border border-red-500/30 bg-red-500/10 px-6 py-8 shadow-2xl shadow-slate-950/50">
                    <h2 class="text-2xl font-semibold text-red-200">Display initialiseren mislukt</h2>
                    <p class="mt-3 text-base text-red-100/80">{{ error }}</p>
                </div>
            </div>

            <div v-else-if="!isPaired" class="flex flex-1 flex-col items-center justify-center text-center">
                <div class="w-full rounded-[2rem] border border-slate-800 bg-slate-900/70 px-6 py-8 shadow-2xl shadow-slate-950/50">
                    <TenantLogo class="mx-auto h-24 w-full" />
                    <p class="mt-6 text-sm uppercase tracking-[0.28em] text-blue-300/80">Customer display</p>
                    <h1 class="mt-3 text-3xl font-bold leading-tight">{{ tenantName }}</h1>
                    <p class="mt-4 text-base text-slate-400">
                        Dit toestel is nog niet gekoppeld aan een POS-terminal.
                    </p>

                    <div class="mt-8 rounded-[1.75rem] border border-slate-700 bg-slate-950/70 px-6 py-6">
                        <div class="text-xs uppercase tracking-[0.22em] text-slate-400">Koppelcode</div>
                        <div class="mt-3 break-all text-2xl font-semibold tracking-wide text-white">{{ pairingCode || '...' }}</div>
                        <div class="mt-4 text-sm text-slate-500">
                            Gebruik deze code in backoffice of via de eenmalige POS-koppeling.
                        </div>
                    </div>
                </div>
            </div>

            <div v-else-if="mode !== 'reservation'" class="flex flex-1 flex-col items-center justify-center text-center">
                <div class="w-full rounded-[2rem] border border-slate-800 bg-slate-900/60 px-8 py-10 shadow-2xl shadow-slate-950/50">
                    <TenantLogo class="mx-auto h-32 w-full" />
                </div>
            </div>

            <div v-else class="flex flex-1 flex-col gap-4">
                <header class="rounded-[2rem] border border-slate-800 bg-slate-900/70 px-5 py-5 shadow-2xl shadow-slate-950/40">
                    <TenantLogo class="mx-auto h-16 w-full" />
                </header>

                <section class="rounded-[2rem] border border-slate-800 bg-slate-900/70 px-5 py-5 shadow-2xl shadow-slate-950/40">
                    <div class="text-xs uppercase tracking-[0.22em] text-blue-300/80">Reservatie</div>
                    <h1 class="mt-2 text-3xl font-bold leading-tight">{{ reservation.name || 'Reservatie' }}</h1>

                    <div class="mt-5 grid grid-cols-2 gap-3">
                        <InfoCard label="Spelers" :value="totalPersonsLabel" />
                        <InfoCard label="Tijd gespeeld" :value="playedTimeLabel" />
                        <InfoCard label="Startuur" :value="reservation.event_time || '-'" />
                        <InfoCard label="Status" :value="statusLabel" />
                    </div>
                </section>

                <section class="flex min-h-0 flex-1 flex-col rounded-[2rem] border border-slate-800 bg-slate-900/70 px-5 py-5 shadow-2xl shadow-slate-950/40">
                    <div class="flex items-center justify-between gap-3">
                        <div class="text-xs uppercase tracking-[0.22em] text-blue-300/80">Producten</div>
                        <div class="rounded-full bg-blue-500/10 px-3 py-1 text-sm font-semibold text-blue-200">
                            {{ groupedOrderCount }} items
                        </div>
                    </div>

                    <div class="mt-4 flex-1 space-y-3 overflow-y-auto pr-1">
                        <div
                            v-for="item in groupedOrderItems"
                            :key="item.name"
                            class="flex items-center justify-between rounded-2xl border border-slate-800 bg-slate-950/60 px-4 py-4"
                        >
                            <div class="min-w-0 pr-4">
                                <div class="truncate text-lg font-semibold">{{ item.name }}</div>
                                <div class="mt-1 text-sm text-slate-400">{{ item.quantity }} × € {{ formatMoney(item.unit_price) }}</div>
                            </div>
                            <div class="text-xl font-semibold">{{ item.quantity }}×</div>
                        </div>

                        <div v-if="!groupedOrderItems.length" class="rounded-2xl border border-dashed border-slate-800 px-4 py-8 text-center text-slate-400">
                            Nog geen artikelen toegevoegd.
                        </div>
                    </div>
                </section>

                <footer class="rounded-[2rem] border border-slate-700 bg-slate-900 px-5 py-5 shadow-2xl shadow-slate-950/40">
                    <div class="text-xs uppercase tracking-[0.22em] text-slate-400">Totaal</div>
                    <div class="mt-2 text-5xl font-bold leading-none text-white">€ {{ formatMoney(orderTotal) }}</div>
                </footer>
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
        <div class="rounded-2xl border border-slate-800 bg-slate-950/60 px-4 py-4 text-left">
            <div class="text-[11px] uppercase tracking-[0.18em] text-slate-400">{{ label }}</div>
            <div class="mt-2 text-xl font-semibold text-white">{{ value }}</div>
        </div>
    `,
}

const TenantLogo = {
    computed: {
        src() {
            return window.PlayDrive?.tenantLogoUrl || ''
        },
        tenantName() {
            return window.PlayDrive?.tenantName || 'PlayDrive'
        },
    },
    template: `
        <div class="flex items-center justify-center">
            <img v-if="src" :src="src" :alt="tenantName" class="max-h-full max-w-full object-contain" />
            <div v-else class="text-center">
                <div class="text-5xl">🎮</div>
                <div class="mt-3 text-2xl font-bold text-white">{{ tenantName }}</div>
            </div>
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
const isPaired = ref(false)

let intervalId = null
let echo = null
let channel = null

const reservation = computed(() => payload.value?.reservation ?? {})
const order = computed(() => payload.value?.order ?? {})
const orderItems = computed(() => Array.isArray(order.value?.items) ? order.value.items : [])
const orderTotal = computed(() => Number(order.value?.total_incl_vat ?? 0))

const groupedOrderItems = computed(() => {
    const grouped = new Map()

    for (const item of orderItems.value) {
        const name = item?.name || 'Product'
        const quantity = Number(item?.quantity ?? 0)
        const unitPrice = Number(item?.price_incl_vat ?? item?.unit_price_incl_vat ?? 0)

        if (!grouped.has(name)) {
            grouped.set(name, {
                name,
                quantity: 0,
                unit_price: unitPrice,
            })
        }

        const row = grouped.get(name)
        row.quantity += quantity
        if (!row.unit_price && unitPrice) {
            row.unit_price = unitPrice
        }
    }

    return [...grouped.values()].sort((a, b) => a.name.localeCompare(b.name, 'nl'))
})

const groupedOrderCount = computed(() => groupedOrderItems.value.reduce((sum, item) => sum + Number(item.quantity ?? 0), 0))

const totalPersonsLabel = computed(() => {
    if (reservation.value?.total_count != null) {
        return String(reservation.value.total_count)
    }

    const total = Number(reservation.value?.participants_children ?? 0)
        + Number(reservation.value?.participants_adults ?? 0)
        + Number(reservation.value?.participants_supervisors ?? 0)

    return total > 0 ? String(total) : '-'
})

const playedTimeLabel = computed(() => {
    const playedMinutes = Number(reservation.value?.played_minutes ?? 0)

    if (playedMinutes > 0) {
        const hours = Math.floor(playedMinutes / 60)
        const minutes = playedMinutes % 60

        return `${String(hours).padStart(2, '0')}:${String(minutes).padStart(2, '0')}`
    }

    const checkedInAt = reservation.value?.checked_in_at
    if (!checkedInAt) {
        return '00:00'
    }

    const start = new Date(checkedInAt)
    if (Number.isNaN(start.getTime())) {
        return '00:00'
    }

    const diffMinutes = Math.max(0, Math.floor((Date.now() - start.getTime()) / 60000))
    const hours = Math.floor(diffMinutes / 60)
    const minutes = diffMinutes % 60

    return `${String(hours).padStart(2, '0')}:${String(minutes).padStart(2, '0')}`
})

const statusLabel = computed(() => {
    const labels = {
        new: 'Nieuw',
        confirmed: 'Bevestigd',
        checked_in: 'Ingecheckt',
        checked_out: 'Uitgecheckt',
        paid: 'Betaald',
        cancelled: 'Geannuleerd',
        no_show: 'No-show',
    }

    return labels[reservation.value?.status] || '-'
})

function formatMoney(value) {
    return Number(value ?? 0).toFixed(2).replace('.', ',')
}

function normalizePayload(source) {
    const value = source?.current_payload ?? source?.payload ?? source ?? {}

    return {
        ...value,
        reservation: value?.reservation ?? value?.registration ?? null,
        order: value?.order ?? null,
    }
}

function applyState(data) {
    displayId.value = data?.id ?? data?.display_id ?? displayId.value
    pairingCode.value = data?.pairing_uuid ?? pairingCode.value
    mode.value = data?.current_mode ?? data?.mode ?? 'standby'
    isPaired.value = Boolean(data?.is_paired ?? data?.paired_pos_count)
    payload.value = normalizePayload(data)
}

function createEcho() {
    if (echo) {
        return echo
    }

    const realtime = window.PlayDrive?.realtime ?? {}
    const scheme = realtime.scheme ?? window.location.protocol.replace(':', '')
    const isHttps = scheme === 'https'

    echo = new Echo({
        broadcaster: 'reverb',
        key: realtime.appKey ?? 'playdrive',
        wsHost: realtime.host ?? window.location.hostname,
        wsPort: Number(realtime.port ?? (isHttps ? 443 : 8080)),
        wssPort: Number(realtime.port ?? (isHttps ? 443 : 8080)),
        forceTLS: isHttps,
        enabledTransports: ['ws', 'wss'],
        disableStats: true,
    })

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
        .listen('.display.state.updated', (event) => {
            applyState({
                display_id: event?.display_id ?? displayId.value,
                mode: event?.mode ?? 'standby',
                payload: event?.payload ?? {},
                is_paired: true,
            })

            error.value = ''
            loading.value = false
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

        intervalId = window.setInterval(loadState, 3000)
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
