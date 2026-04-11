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
                :pairing-code="pairingCode"
            />

            <DisplayStandby
                v-else-if="mode !== 'reservation'"
                :tenant-name="tenantName"
            />

            <DisplayOverview
                v-else
                :tenant-name="tenantName"
                :reservation="reservation"
                :grouped-order-items="groupedOrderItems"
                :grouped-order-count="groupedOrderCount"
                :order-total="orderTotal"
                :total-persons-label="totalPersonsLabel"
                :played-time-label="playedTimeLabel"
                :start-time-label="startTimeLabel"
                :end-time-label="endTimeLabel"
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
        const imageUrl =
            item?.image_url
            ?? item?.image
            ?? item?.product_image_url
            ?? item?.thumbnail_url
            ?? null

        if (!grouped.has(name)) {
            grouped.set(name, {
                name,
                quantity: 0,
                unit_price: unitPrice,
                image_url: imageUrl,
            })
        }

        const row = grouped.get(name)
        row.quantity += quantity

        if (!row.unit_price && unitPrice) {
            row.unit_price = unitPrice
        }

        if (!row.image_url && imageUrl) {
            row.image_url = imageUrl
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

    const total =
        Number(reservation.value?.participants_children ?? 0) +
        Number(reservation.value?.participants_adults ?? 0) +
        Number(reservation.value?.participants_supervisors ?? 0)

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

const startTimeLabel = computed(() => {
    return normalizeTimeString(reservation.value?.event_time) || '-'
})

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
    console.log('[display] applyState raw', data)

    displayId.value = data?.id ?? data?.display_id ?? displayId.value
    pairingCode.value = data?.pairing_uuid ?? pairingCode.value
    mode.value = data?.current_mode ?? data?.mode ?? mode.value ?? 'standby'
    isPaired.value = Boolean(data?.is_paired ?? data?.paired_pos_count ?? isPaired.value)
    payload.value = normalizePayload(data)

    console.log('[display] state after apply', {
        displayId: displayId.value,
        pairingCode: pairingCode.value,
        mode: mode.value,
        isPaired: isPaired.value,
        payload: payload.value,
    })
}

function createEcho() {
    if (echo) {
        console.log('[display] createEcho reuse existing')
        return echo
    }

    const realtime = window.PlayDrive?.realtime ?? {}
    const scheme = realtime.scheme ?? window.location.protocol.replace(':', '')
    const isHttps = scheme === 'https'

    console.log('[display] createEcho config', {
        realtime,
        scheme,
        isHttps,
        host: realtime.host ?? window.location.hostname,
        port: Number(realtime.port ?? (isHttps ? 443 : 8080)),
    })

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

    window.__displayEcho = echo
    console.log('[display] Echo instance created', echo)

    return echo
}

function subscribeToChannel() {
    console.log('[display] subscribeToChannel start', {
        displayId: displayId.value,
        hasChannel: !!channel,
    })

    if (!displayId.value) {
        console.log('[display] subscribeToChannel aborted: no displayId')
        return
    }

    const echoInstance = createEcho()

    if (channel) {
        console.log('[display] leaving previous channel', `display.${displayId.value}`)
        echoInstance.leave(`display.${displayId.value}`)
        channel = null
    }

    console.log('[display] subscribing to', `display.${displayId.value}`)

    channel = echoInstance
        .channel(`display.${displayId.value}`)
        .listen('.display.state.updated', (event) => {
            console.log('[display] realtime event received', event)

            applyState({
                display_id: event?.display_id ?? displayId.value,
                mode: event?.mode ?? mode.value ?? 'standby',
                payload: event?.payload ?? {},
                is_paired: true,
            })

            error.value = ''
            loading.value = false
        })

    console.log('[display] channel object', channel)
}

async function loadState() {
    console.log('[display] loadState start')

    try {
        const response = await axios.get('/api/display/state', {
            params: {
                device_uuid: getOrCreateDisplayUuid(),
                device_token: getDisplayToken(),
            },
        })

        console.log('[display] loadState response', response.data)

        applyState(response.data?.data ?? {})
        error.value = ''

        if (displayId.value && !channel) {
            console.log('[display] loadState will subscribe to realtime channel')
            subscribeToChannel()
        } else {
            console.log('[display] loadState skip subscribe', {
                displayId: displayId.value,
                hasChannel: !!channel,
            })
        }
    } catch (err) {
        console.error('[display] loadState error', err)
        error.value = err?.response?.data?.message ?? 'Kon displaystatus niet ophalen.'
    } finally {
        loading.value = false
        console.log('[display] loadState end')
    }
}

async function bootstrap() {
    console.log('[display] bootstrap start')

    loading.value = true
    error.value = ''

    try {
        const response = await axios.post('/api/display/bootstrap', {
            role: 'display',
            device_uuid: getOrCreateDisplayUuid(),
            device_token: getDisplayToken(),
            name: 'Customer Display',
        })

        console.log('[display] bootstrap response', response.data)

        if (response.data?.data?.device_token) {
            storeDisplayToken(response.data.data.device_token)
            console.log('[display] stored new device token')
        }

        applyState(response.data?.data ?? {})
        await loadState()

        intervalId = window.setInterval(loadState, 3000)
        console.log('[display] polling started')
    } catch (err) {
        console.error('[display] bootstrap error', err)
        loading.value = false
        error.value = err?.response?.data?.message ?? 'Kon de display niet initialiseren.'
    }
}

onMounted(() => {
    console.log('[display] mounted')
    bootstrap()
})

onBeforeUnmount(() => {
    console.log('[display] beforeUnmount')

    if (intervalId) {
        clearInterval(intervalId)
        console.log('[display] polling stopped')
    }

    if (echo && displayId.value) {
        echo.leave(`display.${displayId.value}`)
        console.log('[display] left channel', `display.${displayId.value}`)
    }
})
</script>
