<template>
    <div class="h-screen overflow-hidden bg-slate-950 text-white">
        <div class="pointer-events-none fixed inset-0 overflow-hidden">
            <div class="absolute -left-24 top-0 h-72 w-72 rounded-full bg-blue-500/12 blur-3xl"></div>
            <div class="absolute right-0 top-1/3 h-80 w-80 rounded-full bg-cyan-400/10 blur-3xl"></div>
            <div class="absolute bottom-0 left-1/3 h-64 w-64 rounded-full bg-indigo-500/10 blur-3xl"></div>
        </div>

        <div class="relative mx-auto flex h-screen w-full max-w-[1600px] flex-col px-6 py-6">
            <div v-if="loading" class="flex flex-1 items-center justify-center text-center text-xl text-slate-300">
                Display initialiseren...
            </div>

            <div v-else-if="error" class="flex flex-1 flex-col items-center justify-center text-center">
                <div class="w-full rounded-[2rem] border border-red-500/30 bg-red-500/10 px-6 py-8 shadow-2xl shadow-slate-950/50 backdrop-blur-xl">
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

            <DisplayMemberRegistration
                v-else-if="mode === 'member_registration'"
                :form="memberForm"
                :step="memberWizardStep"
                :templates="memberTemplates"
                :saving="memberSaving"
                :error="memberError"
                :success="memberSuccess"
                :success-message="memberSuccessMessage"
                @update="updateMemberField"
                @sync-form="syncMemberForm"
                @next="goToMemberStep(2)"
                @previous="goToMemberStep(1)"
                @cancel="cancelMemberRegistration"
                @save="saveMemberRegistration"
            />

            <DisplayOverview
                v-else-if="mode === 'reservation'"
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

            <DisplayStandby
                v-else
                :tenant-name="tenantName"
                :tenant-logo-url="tenantLogoUrl"
            />
        </div>
    </div>
</template>

<script setup>
import axios from 'axios'
import { computed, onBeforeUnmount, onMounted, ref } from 'vue'
import DisplayDisconnected from '../components/DisplayDisconnected.vue'
import DisplayOverview from '../components/DisplayOverview.vue'
import DisplayStandby from '../components/DisplayStandby.vue'
import DisplayMemberRegistration from '../components/DisplayMemberRegistration.vue'
import { getDisplayToken, getOrCreateDisplayUuid, storeDisplayToken } from '../shared/device'
import { getEcho, leaveChannel } from '../shared/realtime'

const tenantName = window.PlayDrive?.tenantName || 'PlayDrive'
const tenantLogoUrl = window.PlayDrive?.tenantLogoUrl || ''

const loading = ref(true)
const error = ref('')
const pairingCode = ref('')
const mode = ref('standby')
const payload = ref({})
const displayId = ref(null)
const isPaired = ref(false)
const memberSaving = ref(false)
const memberError = ref('')
const memberSuccess = ref(false)
const memberSuccessMessage = ref('')
const memberForm = ref(createDefaultMemberForm())

const IDLE_TIMEOUT_MS = 20000

let intervalId = null
let idleTimerId = null
let echo = null
let channel = null
let subscribedChannelName = null

const reservation = computed(() => payload.value?.reservation ?? {})
const order = computed(() => payload.value?.order ?? {})
const orderItems = computed(() => Array.isArray(order.value?.items) ? order.value.items : [])
const orderTotal = computed(() => Number(order.value?.total_incl_vat ?? 0))

const memberRegistration = computed(() => payload.value?.member_registration ?? {})
const memberTemplates = computed(() => {
    const nestedTemplates = Array.isArray(memberRegistration.value?.templates) ? memberRegistration.value.templates : []
    const directTemplates = Array.isArray(payload.value?.member_badge_templates) ? payload.value.member_badge_templates : []
    const legacyTemplates = Array.isArray(payload.value?.templates) ? payload.value.templates : []

    return nestedTemplates.length ? nestedTemplates : (directTemplates.length ? directTemplates : legacyTemplates)
})
const memberWizardStep = computed(() => Number(memberRegistration.value?.step ?? 1))

function createDefaultMemberForm(source = {}) {
    return {
        first_name: source.first_name ?? '',
        last_name: source.last_name ?? '',
        email: source.email ?? '',
        phone: source.phone ?? '',
        password: '',
        password_confirmation: '',
        birth_date: source.birth_date ?? '',
        type: source.type ?? 'adult',
        street: source.street ?? '',
        house_number: source.house_number ?? '',
        postal_code: source.postal_code ?? '',
        city: source.city ?? '',
        badge_template_id: source.badge_template_id ?? null,
    }
}

const groupedOrderItems = computed(() => {
    const grouped = new Map()

    for (const item of orderItems.value) {
        const name = item?.name || 'Product'
        const quantity = Number(item?.quantity ?? 0)
        const unitPrice = Number(item?.price_incl_vat ?? item?.unit_price_incl_vat ?? item?.unit_price ?? 0)
        const imageUrl = item?.image_url ?? item?.product_image_url ?? item?.thumbnail_url ?? ''

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
        member_registration: value?.member_registration ?? null,
    }
}

function clearIdleTimer() {
    if (idleTimerId) {
        clearTimeout(idleTimerId)
        idleTimerId = null
    }
}

function goToStandby() {
    mode.value = 'standby'
    payload.value = {
        ...payload.value,
        reservation: null,
        order: null,
        member_registration: null,
    }
    memberError.value = ''
    memberSaving.value = false
}

function resetIdleTimer() {
    clearIdleTimer()

    if (!isPaired.value) {
        return
    }

    if (!['reservation', 'member_registration'].includes(mode.value)) {
        return
    }

    idleTimerId = window.setTimeout(() => {
        console.log('[display] idle timeout reached, switching to standby')
        goToStandby()
    }, IDLE_TIMEOUT_MS)
}

function applyState(data) {
    console.log('[display] applyState', data)

    const previousMode = mode.value
    const previousPayload = payload.value
    const nextMode = data?.current_mode ?? data?.mode ?? mode.value ?? 'standby'
    const normalizedPayload = normalizePayload(data)

    if (nextMode === 'member_registration') {
        const incomingRegistration = normalizedPayload?.member_registration ?? {}
        const previousRegistration = previousMode === 'member_registration'
            ? (previousPayload?.member_registration ?? {})
            : {}

        const shouldKeepLocalWizardState = previousMode === 'member_registration'
            && !Boolean(incomingRegistration?.success)

        normalizedPayload.member_registration = shouldKeepLocalWizardState
            ? {
                ...incomingRegistration,
                step: previousRegistration?.step ?? incomingRegistration?.step ?? 1,
                form: {
                    ...(incomingRegistration?.defaults ?? {}),
                    ...(incomingRegistration?.form ?? {}),
                    ...memberForm.value,
                },
            }
            : incomingRegistration
    }

    displayId.value = data?.id ?? data?.display_id ?? displayId.value
    pairingCode.value = data?.pairing_uuid ?? pairingCode.value
    mode.value = nextMode
    isPaired.value = Boolean(data?.is_paired ?? data?.paired_pos_count ?? isPaired.value)
    payload.value = normalizedPayload

    if (mode.value === 'member_registration') {
        const nextMemberForm = createDefaultMemberForm(
            payload.value?.member_registration?.form ?? payload.value?.member_registration?.defaults ?? {}
        )

        memberForm.value = {
            ...nextMemberForm,
            password: memberForm.value?.password ?? '',
            password_confirmation: memberForm.value?.password_confirmation ?? '',
        }

        memberError.value = payload.value?.member_registration?.error ?? ''
        memberSuccess.value = Boolean(payload.value?.member_registration?.success)
        memberSuccessMessage.value = payload.value?.member_registration?.success_message ?? ''
    } else {
        memberError.value = ''
        memberSuccess.value = false
        memberSuccessMessage.value = ''
    }

    if (!isPaired.value) {
        clearIdleTimer()
    } else if (['reservation', 'member_registration'].includes(mode.value)) {
        resetIdleTimer()
    } else {
        clearIdleTimer()
    }

    console.log('[display] state after apply', {
        displayId: displayId.value,
        pairingCode: pairingCode.value,
        mode: mode.value,
        isPaired: isPaired.value,
        payload: payload.value,
    })
}


function updateMemberField({ field, value }) {
    memberForm.value = {
        ...memberForm.value,
        [field]: value,
    }
    memberError.value = ''
}

function syncMemberForm(form) {
    const mergedForm = {
        ...memberForm.value,
        ...(form ?? {}),
    }

    memberForm.value = {
        ...createDefaultMemberForm(mergedForm),
        password: mergedForm.password ?? '',
        password_confirmation: mergedForm.password_confirmation ?? '',
    }

    memberError.value = ''
}

function validateMemberForm(form = memberForm.value) {
    const firstName = String(form?.first_name ?? '').trim()
    const lastName = String(form?.last_name ?? '').trim()
    const email = String(form?.email ?? '').trim()
    const password = String(form?.password ?? '')
    const passwordConfirmation = String(form?.password_confirmation ?? '')

    if (!firstName) return 'Voornaam is verplicht.'
    if (!lastName) return 'Naam is verplicht.'
    if (!email) return 'E-mail is verplicht.'
    if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email)) return 'Vul een geldig e-mailadres in.'
    if (!password) return 'Paswoord is verplicht.'
    if (password.length < 6) return 'Paswoord moet minstens 6 tekens bevatten.'
    if (!passwordConfirmation) return 'Herhaal het paswoord.'
    if (password !== passwordConfirmation) return 'De paswoorden komen niet overeen.'

    return ''
}

function goToMemberStep(step, form = memberForm.value) {
    const nextForm = createDefaultMemberForm({
        ...memberForm.value,
        ...(form ?? {}),
    })

    nextForm.password = form?.password ?? memberForm.value?.password ?? ''
    nextForm.password_confirmation = form?.password_confirmation ?? memberForm.value?.password_confirmation ?? ''

    if (step === 2) {
        const validationError = validateMemberForm(nextForm)
        if (validationError) {
            memberError.value = validationError
            return
        }
    }

    memberForm.value = {
        ...nextForm,
        password: nextForm.password,
        password_confirmation: nextForm.password_confirmation,
    }

    payload.value = {
        ...payload.value,
        member_registration: {
            ...(payload.value?.member_registration ?? {}),
            step,
            form: { ...memberForm.value },
        },
    }
    memberError.value = ''
}

async function cancelMemberRegistration() {
    goToStandby()
}

async function saveMemberRegistration(form = memberForm.value) {
    const nextForm = {
        ...memberForm.value,
        ...(form ?? {}),
    }

    memberForm.value = {
        ...memberForm.value,
        ...nextForm,
        password: nextForm.password ?? '',
        password_confirmation: nextForm.password_confirmation ?? '',
    }

    memberSaving.value = true
    memberError.value = ''

    try {
        const response = await axios.post('/api/display/members', {
            device_uuid: getOrCreateDisplayUuid(),
            device_token: getDisplayToken(),
            ...memberForm.value,
            membership_type: memberForm.value.type,
        })

        const member = response.data?.data?.member
        memberSuccess.value = true
        memberSuccessMessage.value = member
            ? `#${member.id} · ${member.full_name} werd toegevoegd.`
            : 'Het nieuwe lid werd toegevoegd.'
    } catch (err) {
        memberError.value = err?.response?.data?.message ?? 'Lid opslaan mislukt.'
    } finally {
        memberSaving.value = false
    }
}

function createEcho() {
    if (echo) {
        return echo
    }

    echo = getEcho()
    window.__displayEcho = echo

    return echo
}

function subscribeToChannel() {
    if (!displayId.value) {
        return
    }

    const echoInstance = createEcho()
    const channelName = `display.${displayId.value}`

    if (subscribedChannelName && subscribedChannelName !== channelName) {
        leaveChannel(subscribedChannelName)
        channel = null
        subscribedChannelName = null
    }

    if (channel && subscribedChannelName === channelName) {
        return
    }

    channel = echoInstance.channel(channelName)
    subscribedChannelName = channelName

    channel
        .subscribed(() => {
            console.log('[display] subscription succeeded', channelName)
        })
        .error((subscriptionError) => {
            console.error('[display] subscription error', subscriptionError)
        })
        .listen('.display.state.updated', (event) => {
            console.log('[display] WS EVENT ONTVANGEN', event)

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

        if (displayId.value) {
            subscribeToChannel()
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
    loading.value = true
    error.value = ''

    console.log('[display] bootstrap start')

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
        }

        applyState(response.data?.data ?? {})
        await loadState()

        intervalId = window.setInterval(loadState, 3000)
        console.log('[display] polling started', 3000)
    } catch (err) {
        console.error('[display] bootstrap error', err)
        loading.value = false
        error.value = err?.response?.data?.message ?? 'Kon de display niet initialiseren.'
    }
}

onMounted(bootstrap)

onBeforeUnmount(() => {
    console.log('[display] beforeUnmount')

    if (intervalId) {
        clearInterval(intervalId)
    }

    clearIdleTimer()

    if (subscribedChannelName) {
        leaveChannel(subscribedChannelName)
    }
})
</script>
