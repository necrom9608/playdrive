<template>
    <div class="flex h-full min-h-0 flex-col overflow-hidden rounded-3xl border border-slate-800 bg-slate-900 shadow-xl">
        <div class="shrink-0 border-b border-slate-800 p-4">
            <h3 class="text-lg font-semibold text-white">Dagoverzicht</h3>
            <p class="mt-1 text-sm text-slate-400">
                Tijdsschema van alle reservaties voor deze dag.
            </p>
        </div>

        <div v-if="!registrations.length" class="p-6 text-sm text-slate-400">
            Geen reservaties voor deze dag.
        </div>

        <div v-else class="min-h-0 flex-1 overflow-auto">
            <div class="min-w-[1100px]">
                <!-- Header -->
                <div class="sticky top-0 z-20 grid border-b border-slate-800 bg-slate-900/95 backdrop-blur"
                     :style="gridTemplate">
                    <div class="border-r border-slate-800 px-4 py-3">
                        <div class="text-xs font-semibold uppercase tracking-wide text-slate-500">
                            Reservatie
                        </div>
                    </div>

                    <div class="relative">
                        <div class="grid h-full" :style="timeGridTemplate">
                            <div
                                v-for="slot in timeSlots"
                                :key="slot.key"
                                class="border-r border-slate-800 px-2 py-3 text-xs font-semibold text-slate-400"
                            >
                                {{ slot.label }}
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Rows -->
                <div
                    v-for="registration in sortedRegistrations"
                    :key="registration.id"
                    class="grid border-b border-slate-800 last:border-b-0"
                    :style="gridTemplate"
                >
                    <div class="border-r border-slate-800 px-4 py-3">
                        <div class="flex items-start justify-between gap-3">
                            <div class="min-w-0">
                                <div class="flex flex-wrap items-center gap-2">
                                    <h4 class="truncate text-sm font-semibold text-white">
                                        {{ registration.name || `Reservatie #${registration.id}` }}
                                    </h4>

                                    <span
                                        class="inline-flex items-center rounded-full px-2 py-0.5 text-[11px] font-semibold ring-1"
                                        :class="registration.status_color?.badge ?? 'bg-slate-500/15 text-slate-300 ring-slate-500/30'"
                                    >
                                        {{ registration.status_label }}
                                    </span>
                                </div>

                                <div class="mt-1 text-xs text-slate-400">
                                    {{ registration.event_time || '—' }} — {{ endTimeLabel(registration) }}
                                </div>

                                <div class="mt-2 flex flex-wrap gap-1.5">
                                    <span
                                        class="inline-flex items-center rounded-full bg-slate-800 px-2 py-0.5 text-[11px] font-medium text-slate-200"
                                    >
                                        {{ registration.total_count }} pers.
                                    </span>

                                    <span
                                        v-if="registration.event_type"
                                        class="inline-flex items-center rounded-full bg-slate-800 px-2 py-0.5 text-[11px] font-medium text-slate-200"
                                    >
                                        <span v-if="registration.event_type_emoji" class="mr-1">{{ registration.event_type_emoji }}</span>
                                        {{ registration.event_type }}
                                    </span>

                                    <span
                                        v-if="showCatering(registration)"
                                        class="inline-flex items-center rounded-full bg-cyan-500/15 px-2 py-0.5 text-[11px] font-medium text-cyan-300 ring-1 ring-cyan-500/30"
                                    >
                                        <span v-if="registration.catering_option_emoji" class="mr-1">{{ registration.catering_option_emoji }}</span>
                                        {{ registration.catering_option }}
                                    </span>

                                    <span
                                        v-if="registration.outside_opening_hours"
                                        class="inline-flex items-center rounded-full bg-amber-500/15 px-2 py-0.5 text-[11px] font-medium text-amber-300 ring-1 ring-amber-500/30"
                                    >
                                        Buiten openingsuren
                                    </span>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div class="relative px-0 py-0">
                        <!-- Grid lines -->
                        <div class="absolute inset-0 grid pointer-events-none" :style="timeGridTemplate">
                            <div
                                v-for="slot in timeSlots"
                                :key="`line-${registration.id}-${slot.key}`"
                                class="border-r border-slate-800"
                            ></div>
                        </div>

                        <!-- Timeline lane -->
                        <div class="relative h-[84px]">
                            <div
                                class="absolute top-1/2 h-12 -translate-y-1/2 overflow-hidden rounded-2xl border shadow-sm"
                                :class="[
                                    registration.status_color?.bg ?? 'bg-slate-500/10',
                                    registration.status_color?.border ?? 'border-slate-500/20',
                                ]"
                                :style="barStyle(registration)"
                            >
                                <div class="absolute inset-y-0 left-0 w-1.5"
                                     :class="registration.status_color?.accent ?? 'bg-slate-500'"></div>

                                <div class="flex h-full min-w-0 items-center gap-2 pl-3 pr-3">
                                    <div class="min-w-0 flex-1">
                                        <div class="truncate text-sm font-semibold text-white">
                                            {{ registration.name || `Reservatie #${registration.id}` }}
                                        </div>

                                        <div class="mt-0.5 text-[11px] text-slate-200">
                                            {{ registration.event_time || '—' }} - {{ endTimeLabel(registration) }}
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Fallback label if no valid time -->
                            <div
                                v-if="!hasValidTimeRange(registration)"
                                class="absolute inset-y-0 left-3 right-3 flex items-center"
                            >
                                <div class="rounded-xl bg-slate-800 px-3 py-2 text-xs text-slate-300">
                                    Geen geldig tijdsblok beschikbaar
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="shrink-0 border-t border-slate-800 px-4 py-3 text-xs text-slate-500">
            Tijdsschaal: {{ formatHour(displayStartMinutes) }} - {{ formatHour(displayEndMinutes) }}
        </div>
    </div>
</template>

<script setup>
import { computed } from 'vue'

const props = defineProps({
    registrations: {
        type: Array,
        default: () => [],
    },
})

const detailsColumnWidth = 320
const fallbackStartMinutes = 10 * 60
const fallbackEndMinutes = 22 * 60
const minimumBarPercent = 3

const sortedRegistrations = computed(() => {
    return [...props.registrations].sort((a, b) => {
        const aStart = startMinutes(a)
        const bStart = startMinutes(b)

        if (aStart !== bStart) {
            return aStart - bStart
        }

        return (a.id ?? 0) - (b.id ?? 0)
    })
})

const displayStartMinutes = computed(() => {
    const starts = sortedRegistrations.value
        .map(item => startMinutes(item))
        .filter(value => value !== null)

    if (!starts.length) {
        return fallbackStartMinutes
    }

    const earliest = Math.min(...starts)

    // afronden naar uur met 1u marge
    const roundedEarliest = Math.max(
        0,
        Math.floor((earliest - 60) / 60) * 60
    )

    // nooit later starten dan fallback (10u)
    return Math.min(fallbackStartMinutes, roundedEarliest)
})

const displayEndMinutes = computed(() => {
    const ends = sortedRegistrations.value
        .map(item => endMinutes(item))
        .filter(value => value !== null)

    if (!ends.length) {
        return fallbackEndMinutes
    }

    const latest = Math.max(...ends)
    return Math.min(24 * 60, Math.ceil((latest + 60) / 60) * 60)
})

const totalMinutes = computed(() => {
    return Math.max(displayEndMinutes.value - displayStartMinutes.value, 60)
})

const timeSlots = computed(() => {
    const slots = []

    for (let minutes = displayStartMinutes.value; minutes < displayEndMinutes.value; minutes += 60) {
        slots.push({
            key: minutes,
            label: formatHour(minutes),
        })
    }

    return slots
})

const gridTemplate = computed(() => {
    return {
        gridTemplateColumns: `${detailsColumnWidth}px minmax(0, 1fr)`,
    }
})

const timeGridTemplate = computed(() => {
    return {
        gridTemplateColumns: `repeat(${timeSlots.value.length}, minmax(0, 1fr))`,
    }
})

function barStyle(registration) {
    if (!hasValidTimeRange(registration)) {
        return {
            left: '12px',
            width: '240px',
        }
    }

    const start = clamp(startMinutes(registration), displayStartMinutes.value, displayEndMinutes.value)
    const end = clamp(endMinutes(registration), displayStartMinutes.value, displayEndMinutes.value)

    const left = ((start - displayStartMinutes.value) / totalMinutes.value) * 100
    const width = Math.max(((end - start) / totalMinutes.value) * 100, minimumBarPercent)

    return {
        left: `${left}%`,
        width: `${width}%`,
    }
}

function hasValidTimeRange(registration) {
    const start = startMinutes(registration)
    const end = endMinutes(registration)

    return start !== null && end !== null && end > start
}

function startMinutes(registration) {
    if (registration?.start_at) {
        const value = isoToMinutes(registration.start_at)
        if (value !== null) {
            return value
        }
    }

    if (registration?.event_time) {
        return clockToMinutes(registration.event_time)
    }

    return null
}

function endMinutes(registration) {
    if (registration?.end_at) {
        const value = isoToMinutes(registration.end_at)
        if (value !== null) {
            return value
        }
    }

    const start = startMinutes(registration)
    const duration = Number(registration?.stay_duration_minutes ?? 0)

    if (start !== null && duration > 0) {
        return start + duration
    }

    return null
}

function isoToMinutes(value) {
    const date = new Date(value)

    if (Number.isNaN(date.getTime())) {
        return null
    }

    return (date.getHours() * 60) + date.getMinutes()
}

function clockToMinutes(value) {
    const parts = String(value).split(':')
    const hour = Number(parts[0] ?? 0)
    const minute = Number(parts[1] ?? 0)

    if (Number.isNaN(hour) || Number.isNaN(minute)) {
        return null
    }

    return (hour * 60) + minute
}

function endTimeLabel(registration) {
    const end = endMinutes(registration)

    if (end === null) {
        return '—'
    }

    return formatHour(end)
}

function formatHour(minutes) {
    const safe = Math.max(0, Math.min(minutes, 24 * 60))
    const hour = Math.floor(safe / 60)
    const minute = safe % 60

    return `${String(hour).padStart(2, '0')}:${String(minute).padStart(2, '0')}`
}

function clamp(value, min, max) {
    return Math.min(Math.max(value, min), max)
}

function showCatering(registration) {
    const label = String(registration?.catering_option ?? '').trim().toLowerCase()
    return !['', 'geen', 'none', 'nvt', 'n.v.t.', 'zonder', 'no catering'].includes(label)
}
</script>
