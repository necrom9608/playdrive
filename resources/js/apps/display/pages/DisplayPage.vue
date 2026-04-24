<template>
    <div class="h-screen overflow-hidden bg-slate-950 text-white">
        <div class="pointer-events-none fixed inset-0 overflow-hidden">
            <div class="absolute -left-24 top-0 h-72 w-72 rounded-full bg-blue-500/12 blur-3xl"></div>
            <div class="absolute right-0 top-1/3 h-80 w-80 rounded-full bg-cyan-400/10 blur-3xl"></div>
            <div class="absolute bottom-0 left-1/3 h-64 w-64 rounded-full bg-indigo-500/10 blur-3xl"></div>
        </div>

        <div class="relative mx-auto flex h-screen w-full max-w-[1600px] flex-col px-6 py-6">

            <div v-if="showConfig" class="flex flex-1 items-center justify-center text-center">
                <div class="w-full max-w-2xl rounded-[2rem] border border-white/10 bg-slate-900/70 px-8 py-10 shadow-2xl shadow-slate-950/50 backdrop-blur-xl">
                    <p class="text-sm uppercase tracking-[0.28em] text-cyan-300/80">Display configuratie</p>
                    <h2 class="mt-4 text-3xl font-black tracking-tight text-white">{{ tenantName }}</h2>
                    <p class="mt-3 text-base text-slate-400">Geef een duidelijke toestelnaam in voor dit scherm.</p>

                    <div class="mt-8 text-left">
                        <label for="display-name" class="mb-2 block text-sm font-medium text-slate-300">Toestelnaam</label>
                        <input
                            id="display-name"
                            v-model="configDeviceName"
                            type="text"
                            maxlength="80"
                            class="w-full rounded-2xl border border-white/10 bg-slate-950/80 px-4 py-3 text-lg text-white outline-none transition placeholder:text-slate-500 focus:border-cyan-400/40"
                            placeholder="Bijvoorbeeld Display Inkom"
                            @keyup.enter="saveDisplayConfiguration"
                        />
                        <p v-if="configError" class="mt-3 text-sm text-red-300">{{ configError }}</p>
                    </div>

                    <div class="mt-8 flex items-center justify-end gap-3">
                        <button
                            v-if="hasDisplayName"
                            type="button"
                            class="rounded-2xl border border-white/10 bg-white/5 px-4 py-3 text-sm font-semibold text-slate-200 transition hover:bg-white/10"
                            @click="cancelConfiguration"
                        >
                            Annuleren
                        </button>
                        <button
                            type="button"
                            class="rounded-2xl bg-cyan-400 px-5 py-3 text-sm font-semibold text-slate-950 transition hover:bg-cyan-300"
                            @click="saveDisplayConfiguration"
                        >
                            Opslaan
                        </button>
                    </div>
                </div>
            </div>

            <div v-else-if="loading" class="flex flex-1 items-center justify-center text-center text-xl text-slate-300">
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
                :device-name="displayName"
                @logo-hold-start="startConfigAccess"
                @logo-hold-end="cancelConfigAccess"
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
                @logo-hold-start="startConfigAccess"
                @logo-hold-end="cancelConfigAccess"
            />

            <DisplayStandby
                v-else
                :tenant-name="tenantName"
                :tenant-logo-url="tenantLogoUrl"
                @logo-hold-start="startConfigAccess"
                @logo-hold-end="cancelConfigAccess"
            />

            <div v-if="showPinPrompt" class="absolute inset-0 z-30 flex items-center justify-center bg-slate-950/80 px-6 backdrop-blur-sm">
                <div class="w-full max-w-md rounded-[2rem] border border-white/10 bg-slate-900/95 px-6 py-7 shadow-2xl shadow-slate-950/50">
                    <p class="text-center text-sm uppercase tracking-[0.24em] text-cyan-300/80">Configuratie</p>
                    <h3 class="mt-3 text-center text-2xl font-bold text-white">Geef je pincode in</h3>
                    <input
                        v-model="pinInput"
                        type="password"
                        inputmode="numeric"
                        maxlength="12"
                        class="mt-6 w-full rounded-2xl border border-white/10 bg-slate-950/80 px-4 py-3 text-center text-2xl tracking-[0.35em] text-white outline-none transition placeholder:text-slate-500 focus:border-cyan-400/40"
                        placeholder="••••"
                        @keyup.enter="submitPin"
                    />
                    <p v-if="pinError" class="mt-3 text-center text-sm text-red-300">{{ pinError }}</p>
                    <div class="mt-6 flex items-center justify-end gap-3">
                        <button type="button" class="rounded-2xl border border-white/10 bg-white/5 px-4 py-3 text-sm font-semibold text-slate-200 transition hover:bg-white/10" @click="closePinPrompt">Annuleren</button>
                        <button type="button" class="rounded-2xl bg-cyan-400 px-5 py-3 text-sm font-semibold text-slate-950 transition hover:bg-cyan-300" @click="submitPin">Open configuratie</button>
                    </div>
                </div>
            </div>
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
import { getDisplayName, getDisplayToken, getOrCreateDisplayUuid, storeDisplayName, storeDisplayToken } from '../shared/device'
import { getEcho, leaveChannel } from '../shared/realtime'
import { isLocalDisplayMode, localDisplayListen, localDisplayClose } from '../../../shared/localDisplay'

const tenantName = window.PlayDrive?.tenantName || 'PlayDrive'
const tenantLogoUrl = window.PlayDrive?.tenantLogoUrl || ''
const displayConfigPin = String(window.PlayDrive?.displayConfigPin || '2580')

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
const displayName = ref(getDisplayName())
const configDeviceName = ref(displayName.value)
const configError = ref('')
const showConfig = ref(!displayName.value)
const showPinPrompt = ref(false)
const pinInput = ref('')
const pinError = ref('')
const hasDisplayName = computed(() => displayName.value.trim().length > 0)

const IDLE_TIMEOUT_MS = 20000
const CONFIG_ACCESS_HOLD_MS = 5000

let intervalId = null
let idleTimerId = null
let echo = null
let channel = null
let subscribedChannelName = null
let configAccessTimerId = null

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

function startConfigAccess() {
    cancelConfigAccess()
    configAccessTimerId = window.setTimeout(() => {
        showPinPrompt.value = true
        pinInput.value = ''
        pinError.value = ''
    }, CONFIG_ACCESS_HOLD_MS)
}

function cancelConfigAccess() {
    if (configAccessTimerId) {
        clearTimeout(configAccessTimerId)
        configAccessTimerId = null
    }
}

function closePinPrompt() {
    showPinPrompt.value = false
    pinInput.value = ''
    pinError.value = ''
}

function submitPin() {
    if (String(pinInput.value).trim() !== displayConfigPin) {
        pinError.value = 'Ongeldige pincode.'
        return
    }

    closePinPrompt()
    configDeviceName.value = displayName.value
    configError.value = ''
    showConfig.value = true
}

function cancelConfiguration() {
    configDeviceName.value = displayName.value
    configError.value = ''
    showConfig.value = false
}

async function saveDisplayConfiguration() {
    const normalizedName = String(configDeviceName.value || '').trim()

    if (!normalizedName) {
        configError.value = 'Geef een toestelnaam in.'
        return
    }

    displayName.value = storeDisplayName(normalizedName)
    configDeviceName.value = displayName.value
    configError.value = ''
    showConfig.value = false

    await bootstrap()
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
            name: displayName.value || 'Customer Display',
        })

        console.log('[display] bootstrap response', response.data)

        if (response.data?.data?.device_token) {
            storeDisplayToken(response.data.data.device_token)
        }

        applyState(response.data?.data ?? {})
        await loadState()

        if (intervalId) {
            clearInterval(intervalId)
        }

        intervalId = window.setInterval(loadState, 3000)
        console.log('[display] polling started', 3000)
    } catch (err) {
        console.error('[display] bootstrap error', err)
        loading.value = false
        error.value = err?.response?.data?.message ?? 'Kon de display niet initialiseren.'
    }
}

let localCleanup = null

onMounted(() => {
    // Lokale modus (Tauri tweede scherm): geen server bootstrap, luister naar BroadcastChannel
    if (isLocalDisplayMode()) {
        console.log('[display] local mode active — skipping server bootstrap')

        // Forceer een naam zodat de config-prompt niet komt
        if (!displayName.value) {
            displayName.value = 'Local Display'
        }

        // Markeer als gekoppeld; standby tonen tot frontdesk iets stuurt
        isPaired.value = true
        loading.value = false
        mode.value = 'standby'
        showConfig.value = false

        // Luister naar updates van het frontdesk-venster
        localCleanup = localDisplayListen((message) => {
            console.log('[display] local message', message)

            const nextPayload = message.payload ?? {}
            mode.value = message.mode ?? 'standby'
            payload.value = {
                ...nextPayload,
                reservation: nextPayload?.reservation ?? nextPayload?.registration ?? null,
                order: nextPayload?.order ?? null,
            }
        })

        return
    }

    if (hasDisplayName.value) {
        bootstrap()
        return
    }

    loading.value = false
    showConfig.value = true
})

onBeforeUnmount(() => {
    console.log('[display] beforeUnmount')

    if (intervalId) {
        clearInterval(intervalId)
    }

    cancelConfigAccess()
    clearIdleTimer()

    if (subscribedChannelName) {
        leaveChannel(subscribedChannelName)
    }

    if (localCleanup) {
        localCleanup()
        localCleanup = null
    }
    localDisplayClose()
})
</script>
