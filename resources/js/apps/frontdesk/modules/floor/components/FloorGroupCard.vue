<template>
    <section class="space-y-4 rounded-3xl border border-sky-400/25 bg-gradient-to-b from-sky-500/10 to-slate-900/80 p-5 shadow-xl shadow-slate-950/20">
        <div class="flex flex-wrap items-start justify-between gap-4">
            <div class="min-w-0">
                <div class="mb-2 flex flex-wrap items-center gap-2">
                    <span class="rounded-full border border-sky-400/40 bg-sky-400/10 px-2 py-0.5 text-xs font-semibold text-sky-200">Groep · {{ group.members.length }} pc's</span>
                    <span v-if="status.paused" class="inline-flex items-center gap-1 rounded-full border border-amber-500/40 bg-amber-500/10 px-2 py-0.5 text-xs font-semibold text-amber-200">
                        <LockClosedIcon class="h-3.5 w-3.5" /> Vastgezet
                    </span>
                </div>
                <h2 class="truncate text-xl font-bold text-white">{{ group.name }}: {{ status.game?.name || 'nog geen game' }}</h2>
                <p class="mt-1 text-sm text-slate-400">
                    <template v-if="status.state === 'empty'">De afspeellijst is leeg. Vul ze aan in de backoffice (Speelplekken → Groepen).</template>
                    <template v-else-if="status.paused">De rotatie staat stil: dit spel blijft draaien tot je ze hervat.</template>
                    <template v-else-if="status.state === 'waiting'">Wisseltijd voorbij: wisselt naar <span class="font-semibold text-slate-200">{{ status.next?.name }}</span> zodra niemand meer speelt.</template>
                    <template v-else-if="status.next">
                        Volgende: <span class="font-semibold text-slate-200">{{ status.next.name }}</span>
                        <span v-if="remaining !== null"> · nog {{ remaining }}</span>, wisselt pas als niemand meer speelt
                    </template>
                    <template v-else>Eén game in de afspeellijst.</template>
                </p>
                <div v-if="progress !== null && !status.paused" class="mt-2 h-1.5 max-w-sm overflow-hidden rounded-full bg-slate-800">
                    <div class="h-full rounded-full bg-sky-300 transition-all" :style="{ width: `${progress}%` }" />
                </div>
            </div>
            <div class="flex flex-wrap gap-2">
                <button type="button" :disabled="group.playlist.length < 2" class="btn" @click="ask(null)">
                    <ForwardIcon class="h-5 w-5" /> Volgende
                </button>
                <button type="button" class="btn" :class="status.paused ? 'border-amber-500/50! bg-amber-500/10! text-amber-200!' : ''" @click="$emit('pause', !status.paused)">
                    <component :is="status.paused ? PlayIcon : LockClosedIcon" class="h-5 w-5" />
                    {{ status.paused ? 'Rotatie hervatten' : 'Vastzetten' }}
                </button>
            </div>
        </div>

        <div class="grid grid-cols-3 gap-2 md:grid-cols-6">
            <button
                v-for="seat in seats"
                :key="seat.id"
                type="button"
                class="rounded-2xl border bg-slate-950 px-3 py-2.5 text-center text-xs transition"
                :class="[seat.cls, seat.setupId && seat.setupId === selectedSetupId ? 'ring-1 ring-blue-500' : '', seat.setupId ? 'hover:border-slate-500' : 'cursor-default']"
                @click="seat.setupId && $emit('select-seat', seat.setupId)"
            >
                <span class="block truncate text-sm font-semibold text-white">{{ seat.name }}</span>
                {{ seat.label }}
            </button>
        </div>

        <div v-if="confirm" class="flex flex-wrap items-center gap-3 rounded-2xl border border-amber-500/30 bg-amber-500/10 px-4 py-3 text-sm text-amber-100">
            <ExclamationTriangleIcon class="h-5 w-5 shrink-0 text-amber-300" />
            <span class="flex-1">{{ busyCount }} {{ busyCount === 1 ? 'pc speelt' : "pc's spelen" }} nog. Toch wisselen naar {{ confirm.game ? confirm.game.name : status.next?.name }}?</span>
            <button type="button" class="rounded-xl bg-amber-500 px-3 py-2 font-semibold text-slate-950" @click="doSwitch(confirm.game)">Wisselen</button>
            <button type="button" class="rounded-xl border border-amber-500/40 px-3 py-2" @click="confirm = null">Annuleren</button>
        </div>

        <div v-if="group.playlist.length">
            <p class="mb-2 text-sm text-slate-400">Hele groep wisselen naar:</p>
            <div class="flex flex-wrap gap-2">
                <button
                    v-for="game in group.playlist"
                    :key="game.id"
                    type="button"
                    class="inline-flex items-center gap-2 rounded-2xl border px-3 py-2.5 text-sm font-semibold transition"
                    :class="game.id === status.game?.game_id
                        ? 'border-sky-400/50 bg-sky-400/10 text-sky-100'
                        : 'border-slate-700 bg-slate-950 text-slate-200 hover:border-slate-500'"
                    @click="ask(game)"
                >
                    <PlayIcon v-if="game.id === status.game?.game_id" class="h-4 w-4" />
                    {{ game.name }}
                </button>
            </div>
        </div>
    </section>
</template>

<script setup>
import { computed, ref } from 'vue'
import { ExclamationTriangleIcon, ForwardIcon, LockClosedIcon, PlayIcon } from '@heroicons/vue/24/outline'
import { formatDuration } from '../utils/floorState'

const props = defineProps({
    group: { type: Object, required: true },
    now: { type: Number, required: true },
    selectedSetupId: { type: Number, default: null },
})
const emit = defineEmits(['switch', 'pause', 'select-seat'])

const confirm = ref(null)
const status = computed(() => props.group.status ?? {})

const remaining = computed(() => {
    if (!status.value.due_at) return null
    const s = (new Date(status.value.due_at).getTime() - props.now) / 1000
    return s > 0 ? formatDuration(s) : null
})

const progress = computed(() => {
    const { started_at: start, due_at: due } = status.value
    if (!start || !due) return null
    const a = new Date(start).getTime()
    const b = new Date(due).getTime()
    if (b <= a) return null
    return Math.min(100, Math.max(0, ((props.now - a) / (b - a)) * 100))
})

const seats = computed(() => props.group.members.map((m) => {
    let label = 'Vrij'
    let cls = 'border-slate-800 text-slate-400'
    if (!m.online) {
        label = 'Offline'
        cls = 'border-slate-800 text-slate-600 opacity-60'
    } else if (m.phase === 'starting' || m.phase === 'switching') {
        label = 'Laadt…'
        cls = 'border-sky-500/30 text-sky-200'
    } else if (m.busy) {
        label = 'Speelt'
        cls = 'border-emerald-500/40 text-emerald-200'
    }
    return { id: m.id, name: m.name, setupId: m.setup_id, label, cls }
}))

const busyCount = computed(() => props.group.members.filter(m => m.online && m.busy).length)

function ask(game) {
    if (game && game.id === status.value.game?.game_id) return
    if (busyCount.value > 0) {
        confirm.value = { game }
        return
    }
    doSwitch(game)
}

function doSwitch(game) {
    confirm.value = null
    emit('switch', game)
}
</script>

<style scoped>
.btn {
    display: inline-flex;
    align-items: center;
    gap: 0.5rem;
    border-radius: 1rem;
    border: 1px solid rgb(51 65 85);
    background: rgb(2 6 23);
    padding: 0.625rem 0.875rem;
    font-size: 0.875rem;
    font-weight: 600;
    color: rgb(226 232 240);
    transition: background-color 0.15s;
}
.btn:hover:not(:disabled) {
    background: rgb(30 41 59);
}
.btn:disabled {
    opacity: 0.5;
}
</style>
