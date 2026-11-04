<template>
    <button
        type="button"
        class="flex min-h-[132px] flex-col gap-2 rounded-3xl border bg-slate-900/80 p-4 text-left shadow-xl shadow-slate-950/20 transition hover:border-slate-600"
        :class="[meta.tile, selected ? 'border-blue-500! ring-1 ring-blue-500' : '']"
        @click="$emit('select')"
    >
        <div class="flex items-center gap-3">
            <div class="flex h-10 w-10 shrink-0 items-center justify-center rounded-xl border border-slate-700 bg-slate-950">
                <component :is="typeIcon(setup.type)" class="h-5 w-5 text-sky-300" />
            </div>
            <div class="min-w-0 flex-1">
                <div class="truncate font-semibold text-white">{{ setup.name }}</div>
                <div class="text-xs text-slate-500">{{ device ? 'Playscope' : 'Geen bediening' }}</div>
            </div>
        </div>

        <div v-if="state === 'busy'" class="truncate text-sm text-emerald-200">
            Speelt: {{ setup.current_game || 'een game' }}<span v-if="minutes !== null" class="text-emerald-200/60"> · {{ minutes }} min</span>
        </div>
        <div v-else-if="state === 'offline'" class="text-sm text-slate-500">Pc niet bereikbaar</div>
        <div v-else-if="device" class="text-sm text-slate-400">{{ device.state?.status === 'attract' ? 'Attract mode' : 'In het menu' }}</div>
        <div v-else class="text-sm text-slate-600">—</div>

        <div class="mt-auto flex items-center justify-between gap-2">
            <span class="inline-flex rounded-full border px-2 py-0.5 text-xs font-semibold" :class="meta.pill">{{ meta.label }}</span>
            <span v-if="power" class="flex items-center gap-1 text-xs" :class="power.on ? 'text-amber-300' : 'text-slate-600'" :title="power.title">
                <BoltIcon class="h-4 w-4" />
                {{ power.on ? (power.watts || 'Aan') : 'Uit' }}
            </span>
        </div>
    </button>
</template>

<script setup>
import { computed } from 'vue'
import { BoltIcon } from '@heroicons/vue/24/outline'
import { STATE_META, minutesSince, playscopeDevice, setupState, typeIcon } from '../utils/floorState'

const props = defineProps({
    setup: { type: Object, required: true },
    selected: { type: Boolean, default: false },
    now: { type: Number, default: () => Date.now() },
    // { on, watts, title } of null (geen stekker / onbekend)
    power: { type: Object, default: null },
})
defineEmits(['select'])

const state = computed(() => setupState(props.setup))
const meta = computed(() => STATE_META[state.value])
const device = computed(() => playscopeDevice(props.setup))
const minutes = computed(() => minutesSince(props.setup.playing_since, props.now))

</script>
