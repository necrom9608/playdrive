<template>
    <div class="flex h-full min-h-0 flex-col overflow-hidden rounded-3xl border border-slate-800 bg-slate-900 shadow-xl">
        <div class="shrink-0 border-b border-slate-800 p-4">
            <h3 class="text-lg font-semibold text-white">Dagplanning</h3>
            <p class="mt-1 text-sm text-slate-400">
                Reservaties en taken per rij op een tijdsbalk. De uren schalen automatisch mee.
            </p>
        </div>

        <div v-if="!items.length" class="p-6 text-sm text-slate-400">
            Geen reservaties of taken voor deze dag.
        </div>

        <div v-else class="min-h-0 flex-1 overflow-auto">
            <div class="min-w-[980px]">
                <div class="sticky top-0 z-20 grid border-b border-slate-800 bg-slate-900/95 backdrop-blur" :style="headerGridStyle">
                    <div class="border-r border-slate-800 px-4 py-3 text-xs font-semibold uppercase tracking-wide text-slate-500">
                        Item
                    </div>

                    <div class="relative h-14">
                        <div class="absolute inset-0 flex">
                            <div
                                v-for="hour in hours"
                                :key="hour"
                                class="relative flex-1 border-r border-slate-800/80 last:border-r-0"
                            >
                                <span class="absolute left-2 top-3 text-xs font-semibold text-slate-400">
                                    {{ formatHour(hour) }}
                                </span>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="divide-y divide-slate-800">
                    <article
                        v-for="item in parsedItems"
                        :key="`${item.item_type}-${item.id}`"
                        class="grid min-h-[88px]"
                        :style="headerGridStyle"
                    >
                        <div class="border-r border-slate-800 px-4 py-4">
                            <div class="flex min-w-0 items-start gap-3">
                                <div class="mt-1 h-3 w-3 shrink-0 rounded-full" :class="item.status_color?.accent ?? 'bg-slate-500'" />

                                <div class="min-w-0 flex-1">
                                    <div class="flex flex-wrap items-center gap-2">
                                        <span
                                            class="rounded-full border px-2 py-0.5 text-[11px] font-semibold"
                                            :class="item.item_type === 'task'
                                                ? 'border-pink-400/30 bg-pink-500/10 text-pink-200'
                                                : 'border-blue-400/20 bg-blue-500/10 text-blue-200'"
                                        >
                                            {{ item.item_type === 'task' ? 'Taak' : 'Reservatie' }}
                                        </span>

                                        <h4 class="truncate text-sm font-semibold text-white">
                                            {{ item.name }}
                                        </h4>
                                    </div>

                                    <div class="mt-1 flex flex-wrap items-center gap-2 text-xs text-slate-400">
                                        <span>{{ formatTimeRange(item) }}</span>
                                        <span v-if="item.status_label">· {{ item.status_label }}</span>
                                        <span v-if="item.item_type === 'registration' && item.total_count !== undefined">· {{ item.total_count }} pers.</span>
                                        <span v-if="item.item_type === 'task' && item.assigned_user_name">· {{ item.assigned_user_name }}</span>
                                    </div>

                                    <div v-if="item.comment" class="mt-2 line-clamp-2 text-xs text-slate-500">
                                        {{ item.comment }}
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="relative overflow-hidden">
                            <div class="absolute inset-0 flex">
                                <div
                                    v-for="hour in hours"
                                    :key="`${item.id}-${hour}`"
                                    class="relative flex-1 border-r border-slate-800/60 last:border-r-0"
                                >
                                    <div class="absolute inset-y-0 left-1/2 w-px -translate-x-1/2 bg-slate-800/40"></div>
                                </div>
                            </div>

                            <div class="relative h-full px-3 py-4">
                                <div
                                    class="absolute top-1/2 h-10 -translate-y-1/2 rounded-2xl border shadow-lg"
                                    :class="[
                                        item.status_color?.bg ?? 'bg-slate-500/10',
                                        item.status_color?.border ?? 'border-slate-500/20',
                                        item.status_color?.text ?? 'text-slate-100',
                                    ]"
                                    :style="barStyle(item)"
                                >
                                    <div class="flex h-full items-center gap-2 px-3">
                                        <div class="h-2.5 w-2.5 shrink-0 rounded-full" :class="item.status_color?.accent ?? 'bg-slate-500'" />
                                        <span class="truncate text-xs font-semibold text-white">
                                            {{ item.name }}
                                        </span>
                                        <span class="shrink-0 text-[11px] text-slate-300">
                                            {{ formatTimeRange(item) }}
                                        </span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </article>
                </div>
            </div>
        </div>
    </div>
</template>

<script setup>
import { computed } from 'vue'

const props = defineProps({
    items: {
        type: Array,
        default: () => [],
    },
    registrations: {
        type: Array,
        default: null,
    },
})

const items = computed(() => {
    if (Array.isArray(props.items) && props.items.length) {
        return props.items
    }

    if (Array.isArray(props.registrations)) {
        return props.registrations
    }

    return []
})

const parsedItems = computed(() => items.value.map(item => ({
    ...item,
    startMinutes: resolveStartMinutes(item),
    endMinutes: resolveEndMinutes(item),
})))

const startHour = computed(() => {
    if (!parsedItems.value.length) return 8

    const earliestMinutes = Math.min(...parsedItems.value.map(item => item.startMinutes))
    const earliestHour = Math.floor(earliestMinutes / 60)

    return Math.min(8, earliestHour)
})

const endHour = computed(() => {
    if (!parsedItems.value.length) return 18

    const latestMinutes = Math.max(...parsedItems.value.map(item => item.endMinutes))
    const latestHour = Math.ceil(latestMinutes / 60)

    return Math.max(startHour.value + 1, latestHour)
})

const totalMinutes = computed(() => (endHour.value - startHour.value) * 60)

const hours = computed(() => {
    const values = []

    for (let hour = startHour.value; hour < endHour.value; hour += 1) {
        values.push(hour)
    }

    return values
})

const headerGridStyle = computed(() => ({
    gridTemplateColumns: '320px minmax(0, 1fr)',
}))

function formatHour(hour) {
    return `${String(hour).padStart(2, '0')}:00`
}

function formatTime(value) {
    if (!value) return null

    const stringValue = String(value)

    if (stringValue.includes('T')) {
        const date = new Date(stringValue)
        if (!Number.isNaN(date.getTime())) {
            return `${String(date.getHours()).padStart(2, '0')}:${String(date.getMinutes()).padStart(2, '0')}`
        }
    }

    if (stringValue.length >= 5) {
        return stringValue.slice(0, 5)
    }

    return stringValue
}

function formatTimeRange(item) {
    const start = formatTime(item.event_time || item.start_time || item.start_at)
    const end = formatTime(item.end_time || item.end_at)

    if (start && end) {
        return `${start} - ${end}`
    }

    if (start) {
        return start
    }

    return item.item_type === 'task' ? 'Geen uur ingesteld' : 'Tijd onbekend'
}

function resolveStartMinutes(item) {
    const parsed = parseMinutes(item.start_at)
        ?? parseMinutes(item.event_time)
        ?? parseMinutes(item.start_time)

    return parsed ?? (item.item_type === 'task' ? 6 * 60 : 8 * 60)
}

function resolveEndMinutes(item) {
    const explicitEnd = parseMinutes(item.end_at) ?? parseMinutes(item.end_time)

    if (explicitEnd !== null && explicitEnd > resolveStartMinutes(item)) {
        return explicitEnd
    }

    const duration = Number(item.stay_duration_minutes ?? item.duration_minutes ?? 0)
    if (duration > 0) {
        return resolveStartMinutes(item) + duration
    }

    return resolveStartMinutes(item) + 60
}

function parseMinutes(value) {
    if (!value) return null

    if (typeof value === 'string' && value.includes('T')) {
        const date = new Date(value)
        if (!Number.isNaN(date.getTime())) {
            return date.getHours() * 60 + date.getMinutes()
        }
    }

    const stringValue = String(value)
    const match = stringValue.match(/(\d{1,2}):(\d{2})/)
    if (!match) return null

    return (Number(match[1]) * 60) + Number(match[2])
}

function barStyle(item) {
    const leftMinutes = Math.max(0, item.startMinutes - (startHour.value * 60))
    const durationMinutes = Math.max(30, item.endMinutes - item.startMinutes)
    const left = (leftMinutes / totalMinutes.value) * 100
    const width = Math.min(100 - left, (durationMinutes / totalMinutes.value) * 100)

    return {
        left: `${left}%`,
        width: `${Math.max(width, 4)}%`,
    }
}
</script>
