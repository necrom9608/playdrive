<template>
    <div
        v-if="open && registration"
        class="fixed inset-0 z-[100] flex items-center justify-center bg-black/70 p-6 backdrop-blur-sm"
        @click.self="$emit('close')"
    >
        <div class="flex max-h-[90vh] w-full max-w-lg flex-col overflow-hidden rounded-3xl border border-slate-800 bg-slate-900 shadow-2xl">
            <!-- Header -->
            <div class="flex items-start justify-between border-b border-slate-800 px-6 py-4">
                <div>
                    <h2 class="text-xl font-semibold text-slate-100">Iemand stopt</h2>
                    <p class="mt-0.5 text-sm text-slate-400">
                        {{ registration.name }} · ingecheckt om {{ checkedInTime }}
                    </p>
                </div>
                <button
                    type="button"
                    class="inline-flex h-10 w-10 items-center justify-center rounded-xl text-slate-400 transition hover:bg-slate-800 hover:text-white"
                    @click="$emit('close')"
                >
                    ✕
                </button>
            </div>

            <div class="min-h-0 space-y-5 overflow-y-auto p-6">
                <!-- Al gestopt -->
                <div v-if="stops.length" class="space-y-2">
                    <div class="text-xs font-medium text-slate-400">Al gestopt</div>
                    <div
                        v-for="stop in stops"
                        :key="stop.id"
                        class="flex items-center gap-3 rounded-xl border border-amber-500/25 bg-amber-500/10 px-3 py-2"
                    >
                        <span>⏸</span>
                        <div class="min-w-0 flex-1 text-sm">
                            <div class="font-semibold text-slate-100">
                                {{ stopLabel(stop) }}
                                <span v-if="stop.subscriber_name" class="font-normal text-emerald-300">· 🎫 {{ stop.subscriber_name }}</span>
                            </div>
                            <div class="text-xs text-slate-400">
                                gestopt om {{ stop.stopped_time }}
                                <template v-if="stop.played_minutes !== null"> · speeltijd {{ formatDuration(stop.played_minutes) }}</template>
                            </div>
                        </div>
                        <button
                            type="button"
                            class="shrink-0 rounded-lg px-2.5 py-1.5 text-xs font-semibold text-slate-300 transition hover:bg-slate-800 hover:text-white disabled:opacity-50"
                            :disabled="busy"
                            @click="undo(stop)"
                        >
                            Ongedaan
                        </button>
                    </div>
                </div>

                <!-- Wie stopt -->
                <div class="space-y-4">
                    <div class="flex items-center justify-between">
                        <div>
                            <div class="text-sm text-slate-200">Kinderen / studenten</div>
                            <div class="text-xs text-slate-500">{{ playingChildren }} spelen nog</div>
                        </div>
                        <div class="flex items-center overflow-hidden rounded-xl border border-slate-700 bg-slate-800">
                            <button type="button" class="h-11 w-11 text-lg font-semibold text-slate-300 hover:bg-slate-700" @click="change('children', -1)">−</button>
                            <div class="w-11 text-center font-semibold text-slate-100">{{ form.children }}</div>
                            <button type="button" class="h-11 w-11 text-lg font-semibold text-slate-300 hover:bg-slate-700" @click="change('children', 1)">+</button>
                        </div>
                    </div>

                    <div class="flex items-center justify-between">
                        <div>
                            <div class="text-sm text-slate-200">Volwassenen</div>
                            <div class="text-xs text-slate-500">{{ playingAdults }} spelen nog</div>
                        </div>
                        <div class="flex items-center overflow-hidden rounded-xl border border-slate-700 bg-slate-800">
                            <button type="button" class="h-11 w-11 text-lg font-semibold text-slate-300 hover:bg-slate-700" @click="change('adults', -1)">−</button>
                            <div class="w-11 text-center font-semibold text-slate-100">{{ form.adults }}</div>
                            <button type="button" class="h-11 w-11 text-lg font-semibold text-slate-300 hover:bg-slate-700" @click="change('adults', 1)">+</button>
                        </div>
                    </div>
                </div>

                <!-- Abonnee die stopt -->
                <div v-if="availableSubscribers.length">
                    <div class="mb-2 text-xs text-slate-400">Is wie stopt een abonnee van deze groep? (optioneel)</div>
                    <div class="flex flex-wrap gap-2">
                        <button
                            v-for="subscriber in availableSubscribers"
                            :key="subscriber.id"
                            type="button"
                            class="rounded-full border px-3 py-1.5 text-xs font-semibold transition"
                            :class="form.subscriberId === subscriber.id
                                ? 'border-emerald-500/50 bg-emerald-500/15 text-emerald-300'
                                : 'border-slate-700 text-slate-300 hover:border-slate-500'"
                            @click="toggleSubscriber(subscriber)"
                        >
                            🎫 {{ subscriber.full_name }}
                        </button>
                    </div>
                    <div class="mt-1.5 text-xs text-slate-500">Een abonnee die stopt verandert niets aan de prijs, enkel aan de speeltijd.</div>
                </div>

                <!-- Tijdstip -->
                <div class="flex items-center justify-between gap-4">
                    <div>
                        <div class="text-sm text-slate-200">Gestopt om</div>
                        <div class="text-xs text-slate-500">Standaard nu; aanpasbaar als het vergeten werd</div>
                    </div>
                    <input
                        v-model="form.time"
                        type="time"
                        class="w-32 rounded-xl border border-slate-700 bg-slate-950 px-3 py-2.5 text-sm text-slate-100 outline-none focus:border-amber-500"
                    >
                </div>

                <!-- Voorbeeld -->
                <div class="rounded-xl border border-amber-500/30 bg-amber-500/10 px-3 py-2.5 text-sm text-amber-200">
                    <template v-if="form.children + form.adults > 0">
                        {{ whoLabel }} stopt na <span class="font-semibold">{{ formatDuration(previewMinutes) }}</span>
                        en betaalt het tarief voor die speeltijd<span v-if="form.subscriberId"> (abonnee: gratis)</span>.
                        Blijft op de groepsrekening.
                    </template>
                    <template v-else>Kies wie er stopt.</template>
                </div>

                <div v-if="error" class="rounded-xl border border-rose-500/25 bg-rose-500/10 px-3 py-2.5 text-sm text-rose-200">{{ error }}</div>
            </div>

            <!-- Footer -->
            <div class="flex gap-3 border-t border-slate-800 px-6 py-4">
                <button type="button" class="flex-1 rounded-2xl border border-slate-700 bg-slate-800 px-4 py-3 text-sm font-semibold text-slate-200 hover:bg-slate-700" @click="$emit('close')">
                    Sluiten
                </button>
                <button
                    type="button"
                    class="flex-1 rounded-2xl bg-amber-600 px-4 py-3 text-sm font-semibold text-white hover:bg-amber-500 disabled:cursor-not-allowed disabled:opacity-50"
                    :disabled="busy || form.children + form.adults < 1 || !canStopMore"
                    @click="submit"
                >
                    {{ busy ? 'Opslaan…' : 'Bevestigen' }}
                </button>
            </div>
        </div>
    </div>
</template>

<script setup>
import axios from '@/lib/http'
import { computed, reactive, ref, watch } from 'vue'

const props = defineProps({
    open: { type: Boolean, default: false },
    registration: { type: Object, default: null },
})

const emit = defineEmits(['close', 'saved'])

const busy = ref(false)
const error = ref('')
const form = reactive({ children: 0, adults: 0, subscriberId: null, time: '' })

const stops = computed(() => props.registration?.early_stops ?? [])

const playingChildren = computed(() =>
    Math.max(0, Number(props.registration?.participants_children ?? 0) - Number(props.registration?.stopped_children ?? 0)))
const playingAdults = computed(() =>
    Math.max(0, Number(props.registration?.participants_adults ?? 0) - Number(props.registration?.stopped_adults ?? 0)))

// minstens 1 deelnemer moet blijven spelen
const canStopMore = computed(() => (playingChildren.value - form.children) + (playingAdults.value - form.adults) >= 1)

const availableSubscribers = computed(() => {
    const stopped = new Set(stops.value.map(stop => stop.registration_subscriber_id).filter(Boolean))
    return (props.registration?.subscribers ?? []).filter(s => s.id && !stopped.has(s.id))
})

const checkedInTime = computed(() => {
    const value = props.registration?.checked_in_at
    if (!value) return '—'
    const date = new Date(value)
    return `${String(date.getHours()).padStart(2, '0')}:${String(date.getMinutes()).padStart(2, '0')}`
})

const previewMinutes = computed(() => {
    const checkedIn = props.registration?.checked_in_at ? new Date(props.registration.checked_in_at) : null
    if (!checkedIn || !form.time) return 0
    const [h, m] = form.time.split(':').map(Number)
    const stop = new Date(checkedIn)
    stop.setHours(h, m, 0, 0)
    return Math.max(0, Math.round((stop - checkedIn) / 60000))
})

const whoLabel = computed(() => [
    form.adults ? `${form.adults} volwassene${form.adults > 1 ? 'n' : ''}` : '',
    form.children ? `${form.children} kind${form.children > 1 ? 'eren' : ''}` : '',
].filter(Boolean).join(' + '))

watch(() => props.open, (open) => {
    if (!open) return
    resetForm()
})

function nowTime() {
    const now = new Date()
    return `${String(now.getHours()).padStart(2, '0')}:${String(now.getMinutes()).padStart(2, '0')}`
}

function resetForm() {
    error.value = ''
    form.children = 0
    form.adults = playingAdults.value > 0 && playingChildren.value + playingAdults.value > 1 ? 1 : 0
    form.subscriberId = null
    form.time = nowTime()
}

function change(key, delta) {
    error.value = ''
    const max = key === 'children' ? playingChildren.value : playingAdults.value
    const next = Math.max(0, Math.min(max, form[key] + delta))
    const restAfter = (playingChildren.value - (key === 'children' ? next : form.children))
        + (playingAdults.value - (key === 'adults' ? next : form.adults))
    if (restAfter < 1) {
        error.value = 'Minstens één deelnemer moet blijven spelen. Stopt de hele groep, gebruik dan Uitchecken.'
        return
    }
    form[key] = next
}

function toggleSubscriber(subscriber) {
    if (form.subscriberId === subscriber.id) {
        form.subscriberId = null
        return
    }
    form.subscriberId = subscriber.id
    const key = subscriber.participant_scope === 'children' ? 'children' : 'adults'
    if (form[key] < 1) change(key, 1)
}

function stopLabel(stop) {
    return [
        stop.participants_adults ? `${stop.participants_adults} volw.` : '',
        stop.participants_children ? `${stop.participants_children} kind${stop.participants_children > 1 ? 'eren' : ''}` : '',
    ].filter(Boolean).join(' + ')
}

function formatDuration(minutes) {
    const m = Math.max(0, Number(minutes || 0))
    return `${Math.floor(m / 60)}u${String(m % 60).padStart(2, '0')}`
}

async function submit() {
    if (!props.registration?.id) return
    busy.value = true
    error.value = ''
    try {
        const { data } = await axios.post(`/api/frontdesk/registrations/${props.registration.id}/early-stops`, {
            participants_children: form.children,
            participants_adults: form.adults,
            registration_subscriber_id: form.subscriberId,
            stopped_at: form.time || null,
        })
        emit('saved', data.data)
        emit('close')
    } catch (err) {
        error.value = err?.response?.data?.message ?? 'Opslaan mislukt.'
    } finally {
        busy.value = false
    }
}

async function undo(stop) {
    if (!props.registration?.id) return
    busy.value = true
    error.value = ''
    try {
        const { data } = await axios.delete(`/api/frontdesk/registrations/${props.registration.id}/early-stops/${stop.id}`)
        emit('saved', data.data)
        resetForm()
    } catch (err) {
        error.value = err?.response?.data?.message ?? 'Ongedaan maken mislukt.'
    } finally {
        busy.value = false
    }
}
</script>
