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

            <DisplayDisconnected
                v-else-if="!isPaired"
                :tenant-name="tenantName"
                :tenant-logo-url="tenantLogoUrl"
                :pairing-code="pairingCode"
            />

            <DisplayStandby
                v-else-if="mode !== 'reservation'"
                :tenant-name="tenantName"
                :tenant-logo-url="tenantLogoUrl"
            />

            <DisplayOverview
                v-else
                :tenant-name="tenantName"
                :tenant-logo-url="tenantLogoUrl"
                :reservation="reservation"
                :total-persons-label="totalPersonsLabel"
                :played-time-label="playedTimeLabel"
                :start-time-label="startTimeLabel"
                :end-time-label="endTimeLabel"
                :grouped-order-items="groupedOrderItems"
                :grouped-order-count="groupedOrderCount"
                :order-total="orderTotal"
            />
        </div>
    </div>
</template>

<script setup>
import axios from 'axios'
import Echo from 'laravel-echo'
import Pusher from 'pusher-js'
import { computed, onBeforeUnmount, onMounted, ref } from 'vue'
import DisplayDisconnected from '../components/DisplayDisconnected.vue'
import DisplayOverview from '../components/DisplayOverview.vue'
import DisplayStandby from '../components/DisplayStandby.vue'
import { getDisplayToken, getOrCreateDisplayUuid, storeDisplayToken } from '../shared/device'

window.Pusher = Pusher

const tenantName = window.PlayDrive?.tenantName || 'PlayDrive'
const tenantLogoUrl = window.PlayDrive?.tenantLogoUrl || ''

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
        const unitPrice = Number(item?.price_incl_vat ?? item?.unit_price_incl_vat ?? item?.unit_price ?? 0)

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
    const directTotal = reservation.value?.total_count ?? reservation.value?.total_participants ?? reservation.value?.participants_total
    if (directTotal != null && Number(directTotal) > 0) {
        return String(Number(directTotal))
    }

    const total = Number(reservation.value?.participants_children ?? 0)
        + Number(reservation.value?.participants_adults ?? 0)
        + Number(reservation.value?.participants_supervisors ?? 0)

    return total > 0 ? String(total) : '-'
})

const playedTimeLabel = computed(() => {
    const playedMinutes = Number(reservation.value?.played_minutes ?? 0)

    if (playedMinutes > 0) {
        return minutesToLabel(playedMinutes)
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
    return minutesToLabel(diffMinutes)
})

const startTimeLabel = computed(() => normalizeTimeString(reservation.value?.event_time) || '-')

const endTimeLabel = computed(() => {
    const startTime = normalizeTimeString(reservation.value?.event_time)
    const durationMinutes = Number(reservation.value?.stay_duration_minutes ?? reservation.value?.duration_minutes ?? 0)

    if (!startTime || durationMinutes <= 0) {
        return '-'
    }

    const [hours, minutes] = startTime.split(':').map(Number)
    if (Number.isNaN(hours) || Number.isNaN(minutes)) {
        return '-'
    }

    const total = (hours * 60) + minutes + durationMinutes
    const endHours = Math.floor((total % (24 * 60)) / 60)
    const endMinutes = total % 60

    return `${String(endHours).padStart(2, '0')}:${String(endMinutes).padStart(2, '0')}`
})

function minutesToLabel(totalMinutes) {
    const hours = Math.floor(totalMinutes / 60)
    const minutes = totalMinutes % 60

    return `${String(hours).padStart(2, '0')}:${String(minutes).padStart(2, '0')}`
}

function normalizeTimeString(value) {
    if (!value) {
        return ''
    }

    const match = String(value).match(/^(\d{1,2}):(\d{2})/)
    if (!match) {
        return ''
    }

    return `${String(Number(match[1])).padStart(2, '0')}:${match[2]}`
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
    mode.value = data?.current_mode ?? data?.mode ?? mode.value ?? 'standby'
    isPaired.value = Boolean(data?.is_paired ?? data?.paired_pos_count ?? isPaired.value)
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
