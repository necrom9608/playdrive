<template>
    <div
        role="button"
        tabindex="0"
        class="flex min-h-[132px] cursor-pointer flex-col overflow-hidden rounded-3xl border bg-slate-900/80 text-left shadow-xl shadow-slate-950/20 transition hover:border-slate-600"
        :class="[meta.tile, selected ? 'border-blue-500! ring-1 ring-blue-500' : '']"
        @click="$emit('select')"
        @keydown.enter="$emit('select')"
    >
        <!-- Banner: game die nu speelt, anders het beeld van de speelplek -->
        <div class="relative h-[104px] shrink-0 border-b border-slate-800 bg-slate-950">
            <img
                v-if="bannerUrl"
                :key="bannerUrl"
                :src="bannerUrl"
                alt=""
                loading="lazy"
                class="h-full w-full transition"
                :class="[wideBanner ? 'object-contain bg-black' : 'object-cover', dimBanner ? 'grayscale' : '']"
                @error="brokenUrl = bannerUrl"
            />
            <div v-else class="flex h-full flex-col items-center justify-center gap-1.5 text-slate-500">
                <component :is="typeIcon(setup.type)" class="h-7 w-7" />
                <span class="max-w-[85%] truncate text-xs font-medium">{{ fallbackText }}</span>
            </div>

            <span v-if="state === 'busy'" class="absolute top-2 right-2 inline-flex items-center gap-1.5 rounded-full bg-slate-950/75 px-2 py-0.5 text-[10px] font-bold tracking-wider text-white">
                <span class="h-1.5 w-1.5 rounded-full bg-emerald-400"></span>LIVE
            </span>
        </div>

        <div class="flex flex-1 flex-col gap-1.5 px-4 pt-3 pb-4">
            <div class="flex items-baseline justify-between gap-2">
                <div class="truncate font-semibold text-white">{{ setup.name }}</div>
                <div class="shrink-0 text-xs text-slate-500">{{ device ? 'Playscope' : 'Geen bediening' }}</div>
            </div>

            <div v-if="state === 'busy'" class="truncate text-sm text-emerald-200">
                Speelt: {{ setup.current_game || 'een game' }}<span v-if="minutes !== null" class="text-emerald-200/60"> · {{ minutes }} min</span>
            </div>
            <div v-else-if="state === 'offline'" class="text-sm text-slate-500">Pc niet bereikbaar</div>
            <div v-else-if="device" class="text-sm text-slate-400">{{ device.state?.status === 'attract' ? 'Attract mode' : 'In het menu' }}</div>
            <div v-else class="text-sm text-slate-600">—</div>

            <div class="mt-auto flex items-center justify-between gap-2 pt-1">
                <span class="inline-flex rounded-full border px-2 py-0.5 text-xs font-semibold" :class="meta.pill">{{ meta.label }}</span>
                <div v-if="power" class="flex items-center gap-2">
                    <span v-if="power.watts" class="text-xs text-amber-300/80">{{ power.watts }}</span>
                    <PowerToggle
                        :on="power.on"
                        :pending="power.pending"
                        :unreachable="power.unreachable"
                        :disabled="!power.controllable"
                        :title="power.title"
                        @toggle="$emit('toggle-power')"
                    />
                </div>
            </div>
        </div>
    </div>
</template>

<script setup>
import { computed, ref } from 'vue'
import PowerToggle from './PowerToggle.vue'
import { STATE_META, minutesSince, playscopeDevice, setupState, typeIcon } from '../utils/floorState'

const props = defineProps({
    setup: { type: Object, required: true },
    selected: { type: Boolean, default: false },
    now: { type: Number, default: () => Date.now() },
    // { on, watts, title, pending, unreachable, controllable } of null (geen stekker / onbekend)
    power: { type: Object, default: null },
})
defineEmits(['select', 'toggle-power'])

const state = computed(() => setupState(props.setup))
const meta = computed(() => STATE_META[state.value])
const device = computed(() => playscopeDevice(props.setup))
const minutes = computed(() => minutesSince(props.setup.playing_since, props.now))

// Een beeld dat niet laadt (bv. Steam-CDN zonder header) → terugvallen op het icoon.
const brokenUrl = ref(null)
const bannerUrl = computed(() => {
    const url = props.setup.banner?.url || null
    return url && url !== brokenUrl.value ? url : null
})
// Arcade-marquees zijn veel breder dan de tegel: volledig tonen op zwart i.p.v. bij te snijden.
const wideBanner = computed(() => Number(props.setup.banner?.ratio) > 2.6)
const dimBanner = computed(() => state.value === 'offline' || state.value === 'maintenance')
const fallbackText = computed(() => (state.value === 'offline' ? 'Pc niet bereikbaar' : device.value ? 'Geen game actief' : props.setup.type_label || props.setup.name))
</script>
