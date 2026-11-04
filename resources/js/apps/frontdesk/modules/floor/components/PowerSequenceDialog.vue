<template>
    <teleport to="body">
        <div v-if="open" class="fixed inset-0 z-50 flex items-center justify-center p-4">
            <div class="absolute inset-0 bg-slate-950/80" @click="$emit('close')" />
            <div class="relative z-10 flex max-h-[85vh] w-full max-w-xl flex-col overflow-hidden rounded-3xl border border-slate-800 bg-slate-900 shadow-2xl">
                <div class="flex items-center gap-3 border-b border-slate-800 px-6 py-4">
                    <div class="flex h-11 w-11 items-center justify-center rounded-2xl border border-slate-700 bg-slate-950">
                        <BoltIcon class="h-6 w-6" :class="kind === 'open' ? 'text-amber-300' : 'text-slate-400'" />
                    </div>
                    <div class="flex-1">
                        <h2 class="text-lg font-semibold text-white">{{ kind === 'open' ? 'Zaal openen' : 'Zaal sluiten' }}</h2>
                        <p class="text-sm text-slate-400">{{ subtitle }}</p>
                    </div>
                    <button type="button" class="rounded-xl p-2 text-slate-400 transition hover:bg-slate-800 hover:text-white" aria-label="Sluiten" @click="$emit('close')">
                        <XMarkIcon class="h-5 w-5" />
                    </button>
                </div>

                <!-- Bevestigen -->
                <div v-if="!seq" class="min-h-0 flex-1 space-y-4 overflow-y-auto p-6">
                    <p v-if="!plan.length" class="rounded-2xl border border-slate-800 bg-slate-950 px-4 py-3 text-sm text-slate-400">
                        Nog geen stekkers in een golf. Stel de golven in via de backoffice (Speelplekken → Stroom).
                    </p>
                    <template v-else>
                        <p v-if="kind === 'open'" class="text-sm text-slate-300">
                            {{ plugCount }} stekkers in {{ plan.length }} {{ plan.length === 1 ? 'golf' : 'golven' }}, duurt ongeveer {{ formatDuration(power.openDuration) }}.
                        </p>
                        <p v-else class="text-sm text-slate-300">
                            Eerst sluiten alle Playscope-pc's netjes af ({{ grace }} s, lopende games stoppen). Daarna gaan {{ plugCount }} stekkers uit, in omgekeerde volgorde.
                        </p>
                        <div v-for="warning in warnings" :key="warning" class="rounded-2xl border border-amber-500/30 bg-amber-500/10 px-4 py-3 text-sm text-amber-100">{{ warning }}</div>
                        <div class="space-y-3">
                            <div v-for="(wave, i) in orderedPlan" :key="wave.id" class="rounded-2xl border border-slate-800 bg-slate-950 p-3">
                                <div class="flex items-center justify-between text-sm">
                                    <span class="font-semibold text-white">{{ i + 1 }}. {{ wave.name }}</span>
                                    <span v-if="kind === 'open' && i < orderedPlan.length - 1 && wave.delay_after_seconds" class="text-xs text-slate-500">daarna {{ formatDuration(wave.delay_after_seconds) }} wachten</span>
                                </div>
                                <p class="mt-1 text-xs text-slate-400">{{ wave.plugs.map(p => p.name).join(', ') }}</p>
                            </div>
                        </div>
                    </template>
                </div>

                <!-- Voortgang -->
                <div v-else class="min-h-0 flex-1 space-y-4 overflow-y-auto p-6">
                    <div class="flex items-center gap-3 rounded-2xl border px-4 py-3 text-sm" :class="statusClass">
                        <ArrowPathIcon v-if="seq.status === 'running'" class="h-5 w-5 animate-spin" />
                        <CheckCircleIcon v-else-if="seq.status === 'done'" class="h-5 w-5" />
                        <ExclamationTriangleIcon v-else class="h-5 w-5" />
                        <span class="flex-1 font-semibold">{{ seq.phase }}</span>
                        <span v-if="seq.status !== 'running'" class="text-xs opacity-70">{{ doneCount }}/{{ seq.steps.length }}</span>
                    </div>
                    <div v-if="seq.waitUntil" class="flex items-center gap-3 rounded-2xl border border-slate-800 bg-slate-950 px-4 py-3 text-sm text-slate-300">
                        <ClockIcon class="h-5 w-5 text-slate-500" />
                        <span class="flex-1">{{ seq.waitLabel }} · nog {{ formatDuration(waitLeft) }}</span>
                        <button type="button" class="rounded-xl border border-slate-700 px-3 py-1.5 text-xs font-semibold text-slate-200 hover:bg-slate-800" @click="power.skipWait()">Overslaan</button>
                    </div>
                    <div v-for="wave in progressWaves" :key="wave.name" class="space-y-1.5">
                        <h3 class="text-xs font-semibold uppercase tracking-wider text-slate-500">{{ wave.name }}</h3>
                        <div v-for="step in wave.steps" :key="step.plugId" class="flex items-center gap-3 rounded-xl border border-slate-800 bg-slate-950 px-3 py-2 text-sm">
                            <span class="h-2.5 w-2.5 shrink-0 rounded-full" :class="dotClass(step.state)" />
                            <span class="min-w-0 flex-1 truncate text-slate-200">{{ step.name }}</span>
                            <span class="text-xs" :class="step.state === 'failed' ? 'text-rose-300' : 'text-slate-500'">{{ stepText(step) }}</span>
                        </div>
                    </div>
                </div>

                <div class="flex gap-3 border-t border-slate-800 px-6 py-4">
                    <template v-if="!seq">
                        <button
                            type="button"
                            :disabled="!plan.length || !power.canControl"
                            class="rounded-2xl px-5 py-3 text-sm font-semibold text-white transition disabled:opacity-50"
                            :class="kind === 'open' ? 'bg-blue-600 hover:bg-blue-500' : 'bg-rose-600 hover:bg-rose-500'"
                            @click="$emit('start')"
                        >
                            {{ kind === 'open' ? 'Starten' : 'Zaal sluiten' }}
                        </button>
                        <button type="button" class="rounded-2xl border border-slate-700 px-5 py-3 text-sm font-medium text-slate-300 hover:bg-slate-800" @click="$emit('close')">Annuleren</button>
                    </template>
                    <template v-else-if="seq.status === 'running'">
                        <button type="button" class="rounded-2xl border border-rose-500/40 px-5 py-3 text-sm font-semibold text-rose-200 hover:bg-rose-500/10" @click="power.cancelSequence()">Stoppen</button>
                        <button type="button" class="ml-auto rounded-2xl border border-slate-700 px-5 py-3 text-sm font-medium text-slate-300 hover:bg-slate-800" @click="$emit('close')">Verbergen</button>
                    </template>
                    <button v-else type="button" class="rounded-2xl bg-blue-600 px-5 py-3 text-sm font-semibold text-white hover:bg-blue-500" @click="power.clearSequence(); $emit('close')">Sluiten</button>
                </div>
            </div>
        </div>
    </teleport>
</template>

<script setup>
import { computed } from 'vue'
import {
    ArrowPathIcon,
    BoltIcon,
    CheckCircleIcon,
    ClockIcon,
    ExclamationTriangleIcon,
    XMarkIcon,
} from '@heroicons/vue/24/outline'
import { usePowerStore, SHUTDOWN_GRACE_SECONDS } from '../stores/usePowerStore'
import { formatDuration } from '../utils/floorState'

const props = defineProps({
    open: { type: Boolean, default: false },
    // gekozen actie als er nog geen sequentie loopt
    mode: { type: String, default: 'open' },
    now: { type: Number, required: true },
})
defineEmits(['close', 'start'])

const power = usePowerStore()
const grace = SHUTDOWN_GRACE_SECONDS

const seq = computed(() => power.sequence)
const kind = computed(() => seq.value?.kind ?? props.mode)
const plan = computed(() => power.plan)
const orderedPlan = computed(() => (kind.value === 'open' ? plan.value : [...plan.value].reverse()))
const plugCount = computed(() => plan.value.reduce((n, w) => n + w.plugs.length, 0))

const subtitle = computed(() => {
    if (!seq.value) return kind.value === 'open' ? 'Stekkers per golf aanzetten' : 'Pc\'s afsluiten en stekkers uitzetten'
    if (seq.value.status === 'running') return 'Bezig… je kan dit venster verbergen, het loopt verder.'
    return seq.value.finishedAt ? `Klaar om ${new Date(seq.value.finishedAt).toLocaleTimeString('nl-BE', { hour: '2-digit', minute: '2-digit' })}` : ''
})

const warnings = computed(() => {
    const list = []
    if (!power.available) list.push('Stekkers bedienen kan enkel in de kassa-app (lokaal netwerk).')
    else if (power.appOutdated) list.push('Werk de kassa-app bij om de stekkers te bedienen.')
    const hubIds = new Set(plan.value.flatMap(w => w.plugs.map(p => p.hub_id)))
    for (const id of hubIds) {
        const hub = power.hubs.find(h => h.id === id)
        if (!hub?.paired) list.push(`${hub?.name || 'Een hub'} is niet gekoppeld; die stekkers worden overgeslagen.`)
        else if (power.hubError(id)) list.push(`${hub.name}: ${power.hubError(id)}`)
    }
    return list
})

const waitLeft = computed(() => (seq.value?.waitUntil ? Math.max(0, (seq.value.waitUntil - props.now) / 1000) : 0))
const doneCount = computed(() => seq.value?.steps.filter(s => s.state === 'ok' || s.state === 'skipped').length ?? 0)

const progressWaves = computed(() => {
    const map = new Map()
    for (const step of seq.value?.steps ?? []) {
        if (!map.has(step.wave)) map.set(step.wave, [])
        map.get(step.wave).push(step)
    }
    return [...map.entries()].map(([name, steps]) => ({ name, steps }))
})

const statusClass = computed(() => ({
    running: 'border-sky-400/30 bg-sky-400/10 text-sky-100',
    done: 'border-emerald-500/30 bg-emerald-500/10 text-emerald-100',
    failed: 'border-amber-500/30 bg-amber-500/10 text-amber-100',
    cancelled: 'border-slate-700 bg-slate-950 text-slate-300',
}[seq.value?.status] ?? ''))

function dotClass(state) {
    return {
        pending: 'bg-slate-700',
        busy: 'bg-sky-400 animate-pulse',
        ok: kind.value === 'open' ? 'bg-amber-300' : 'bg-slate-500',
        skipped: 'bg-slate-600',
        failed: 'bg-rose-400',
    }[state]
}

function stepText(step) {
    if (step.state === 'failed') return step.error || 'Mislukt'
    if (step.state === 'skipped') return step.error
    if (step.state === 'ok') return kind.value === 'open' ? 'Aan' : 'Uit'
    if (step.state === 'busy') return 'Bezig…'
    return ''
}
</script>
