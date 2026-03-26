<template>
    <button
        type="button"
        class="group relative grid w-full cursor-pointer grid-cols-[6px_minmax(260px,2fr)_52px_52px_52px_72px_90px] items-stretch gap-2 rounded-2xl border p-2 text-left transition"
        :class="selected ? selectedRowClass : baseRowClass"
        @click="$emit('select', registration.id)"
        @dblclick.stop="$emit('edit', registration.id)"
    >
        <div class="rounded-xl" :class="accentClass" />

        <div class="min-w-0 rounded-xl px-3 py-2" :class="statusSoftClass">
            <div class="flex items-center justify-between gap-2 text-xs text-slate-400">
                <div class="truncate">
                    {{ dateLabel }}
                </div>

                <span
                    class="inline-flex shrink-0 items-center rounded-full px-2 py-0.5 font-semibold ring-1"
                    :class="statusClass"
                >
                    {{ statusLabel }}
                </span>
            </div>

            <div class="mt-1 flex items-center justify-between gap-2">
                <div class="truncate text-[15px] font-semibold text-slate-100">
                    {{ registration.name }}
                </div>

                <div class="flex shrink-0 items-center gap-1.5 whitespace-nowrap text-[11px]">
                    <span
                        v-if="registration.outside_opening_hours"
                        class="inline-flex items-center gap-1 rounded-full bg-violet-500 px-2 py-0.5 font-semibold text-white shadow-sm"
                    >
                        🌙 Buiten
                    </span>

                    <span class="inline-flex items-center rounded-full bg-blue-500/15 px-2 py-0.5 font-medium text-blue-300 ring-1 ring-blue-500/25">
                        {{ typeEmoji }} {{ typeLabel }}
                    </span>

                    <span
                        v-if="hasCatering"
                        class="inline-flex items-center rounded-full bg-orange-500/15 px-2 py-0.5 font-medium text-orange-300 ring-1 ring-orange-500/25"
                    >
                        {{ cateringEmoji }} {{ cateringLabel }}
                    </span>
                </div>
            </div>
        </div>

        <div class="flex items-center justify-center rounded-xl text-lg font-bold text-slate-100" :class="statusSoftClass">
            {{ children }}
        </div>

        <div class="flex items-center justify-center rounded-xl text-lg font-bold text-slate-100" :class="statusSoftClass">
            {{ adults }}
        </div>

        <div class="flex items-center justify-center rounded-xl text-lg font-bold text-white" :class="statusSoftClass">
            {{ total }}
        </div>

        <div class="flex items-center justify-center rounded-xl text-lg font-bold text-slate-100" :class="statusSoftClass">
            {{ timeLabel }}
        </div>

        <div
            class="flex items-center justify-center rounded-xl px-2 py-1 font-mono text-lg font-bold tabular-nums"
            :class="durationBoxClass"
        >
            {{ durationLabel }}
        </div>
    </button>
</template>

<script setup>
import { computed, onBeforeUnmount, onMounted, ref } from 'vue'

const nowTs = ref(Date.now())
let timer = null

onMounted(() => {
    timer = window.setInterval(() => {
        nowTs.value = Date.now()
    }, 1000)
})

onBeforeUnmount(() => {
    if (timer) {
        window.clearInterval(timer)
    }
})

const props = defineProps({
    registration: {
        type: Object,
        required: true,
    },
    selected: {
        type: Boolean,
        default: false,
    },
})

defineEmits(['select', 'edit'])

function parseDate(value) {
    if (!value) return null
    const date = new Date(value)
    return Number.isNaN(date.getTime()) ? null : date
}

function parseDateTime(value) {
    if (!value) return null
    const date = new Date(value)
    return Number.isNaN(date.getTime()) ? null : date
}

function formatDateLong(value) {
    const date = parseDate(value)
    if (!date) return 'Geen datum'

    return new Intl.DateTimeFormat('nl-BE', {
        weekday: 'long',
        day: '2-digit',
        month: '2-digit',
        year: 'numeric',
    }).format(date)
}

function formatTime(value) {
    if (!value) return '--:--'
    return String(value).slice(0, 5)
}

function formatMinutesAsHours(minutes) {
    if (minutes == null) return '--:--'

    const totalMinutes = Math.max(0, Number(minutes) || 0)
    const hours = Math.floor(totalMinutes / 60)
    const mins = totalMinutes % 60

    return `${String(hours).padStart(2, '0')}:${String(mins).padStart(2, '0')}`
}

const status = computed(() => props.registration.status ?? 'new')

const children = computed(() => Number(props.registration.participants_children ?? 0))
const adults = computed(() => Number(props.registration.participants_adults ?? 0))
const supervisors = computed(() => Number(props.registration.participants_supervisors ?? 0))
const total = computed(() => children.value + adults.value + supervisors.value)

const checkedInAt = computed(() => parseDateTime(props.registration.checked_in_at))
const checkedOutAt = computed(() => parseDateTime(props.registration.checked_out_at))

const plannedMinutes = computed(() => {
    const value = props.registration.stay_duration_minutes
    if (value == null) return null
    return Number(value)
})

const effectivePlayedMinutes = computed(() => {
    if (status.value === 'checked_in' && checkedInAt.value) {
        return Math.max(0, Math.floor((nowTs.value - checkedInAt.value.getTime()) / 60000))
    }

    if (props.registration.played_minutes != null) {
        return Number(props.registration.played_minutes)
    }

    if (checkedInAt.value && checkedOutAt.value) {
        return Math.max(0, Math.floor((checkedOutAt.value.getTime() - checkedInAt.value.getTime()) / 60000))
    }

    return null
})

const showPlannedTiming = computed(() =>
    ['new', 'confirmed', 'cancelled', 'no_show'].includes(status.value)
)

const showEffectiveTiming = computed(() =>
    ['checked_in', 'checked_out', 'paid'].includes(status.value)
)

const timeLabel = computed(() => {
    if (showPlannedTiming.value) {
        return formatTime(props.registration.event_time)
    }

    if (showEffectiveTiming.value && checkedInAt.value) {
        return checkedInAt.value.toLocaleTimeString('nl-BE', {
            hour: '2-digit',
            minute: '2-digit',
            hour12: false,
        })
    }

    return '--:--'
})

const durationLabel = computed(() => {
    if (showPlannedTiming.value) {
        return formatMinutesAsHours(plannedMinutes.value)
    }

    if (showEffectiveTiming.value) {
        return formatMinutesAsHours(effectivePlayedMinutes.value)
    }

    return '--:--'
})

const typeRaw = computed(() => {
    return props.registration.event_type_code
        ?? props.registration.event_type
        ?? 'free_play'
})

const typeLabel = computed(() => {
    const raw = String(typeRaw.value)

    switch (raw) {
        case 'birthday':
            return 'Verjaardag'
        case 'bachelor':
            return 'Vrijgezellen'
        case 'team_building':
            return 'Teambuilding'
        case 'company_event':
            return 'Bedrijf'
        case 'group_visit':
            return 'Groepsbezoek'
        case 'school':
        case 'school_group':
            return 'School'
        case 'free_play':
            return 'Vrij bezoek'
        case 'other':
            return 'Anders'
        default:
            return raw.replaceAll('_', ' ').replace(/\b\w/g, c => c.toUpperCase())
    }
})

const typeEmoji = computed(() => {
    if (props.registration.event_type_emoji) {
        return props.registration.event_type_emoji
    }

    switch (String(typeRaw.value)) {
        case 'birthday':
            return '🎂'
        case 'bachelor':
            return '🥳'
        case 'team_building':
            return '🤝'
        case 'company_event':
            return '🏢'
        case 'school':
        case 'school_group':
            return '🎓'
        case 'group_visit':
            return '👥'
        case 'other':
            return '❓'
        default:
            return '🎮'
    }
})

const cateringRaw = computed(() => {
    return props.registration.catering_option_code
        ?? props.registration.catering_option
        ?? null
})

const hasCatering = computed(() => {
    const raw = cateringRaw.value
    return !!raw && raw !== 'none'
})

const cateringLabel = computed(() => {
    const raw = String(cateringRaw.value ?? '')

    switch (raw) {
        case 'pizza':
            return 'Pizza'
        case 'sandwiches':
            return 'Broodjes'
        case 'pancakes':
            return 'Pannenkoeken'
        case 'drinks':
            return 'Drank'
        case 'snacks':
            return 'Snacks'
        case 'fries':
            return 'Frietjes'
        case 'hotdog':
            return 'Hotdog'
        case 'custom':
            return 'Catering'
        default:
            return props.registration.catering_option ?? 'Catering'
    }
})

const cateringEmoji = computed(() => {
    if (props.registration.catering_option_emoji) {
        return props.registration.catering_option_emoji
    }

    switch (String(cateringRaw.value ?? '')) {
        case 'pizza':
            return '🍕'
        case 'sandwiches':
            return '🥪'
        case 'pancakes':
            return '🥞'
        case 'fries':
            return '🍟'
        case 'hotdog':
            return '🌭'
        case 'drinks':
            return '🥤'
        default:
            return '🍽️'
    }
})

const dateLabel = computed(() => formatDateLong(props.registration.event_date))

const statusLabel = computed(() => {
    switch (status.value) {
        case 'new':
            return 'Nieuw'
        case 'confirmed':
            return 'Bevestigd'
        case 'checked_in':
            return 'Ingecheckt'
        case 'checked_out':
            return 'Uitgecheckt'
        case 'paid':
            return 'Betaald'
        case 'cancelled':
            return 'Geannuleerd'
        case 'no_show':
            return 'No-show'
        default:
            return 'Onbekend'
    }
})

const accentClass = computed(() => {
    switch (status.value) {
        case 'new':
            return 'bg-yellow-500'
        case 'confirmed':
            return 'bg-orange-500'
        case 'checked_in':
            return 'bg-blue-500'
        case 'checked_out':
            return 'bg-purple-500'
        case 'paid':
            return 'bg-green-500'
        case 'cancelled':
            return 'bg-slate-500'
        case 'no_show':
            return 'bg-red-500'
        default:
            return 'bg-slate-500'
    }
})

const statusClass = computed(() => {
    switch (status.value) {
        case 'new':
            return 'bg-yellow-500/15 text-yellow-300 ring-yellow-500/30'
        case 'confirmed':
            return 'bg-orange-500/15 text-orange-300 ring-orange-500/30'
        case 'checked_in':
            return 'bg-blue-500/15 text-blue-300 ring-blue-500/30'
        case 'checked_out':
            return 'bg-purple-500/15 text-purple-300 ring-purple-500/30'
        case 'paid':
            return 'bg-green-500/15 text-green-300 ring-green-500/30'
        case 'cancelled':
            return 'bg-slate-500/15 text-slate-300 ring-slate-500/30'
        case 'no_show':
            return 'bg-red-500/15 text-red-300 ring-red-500/30'
        default:
            return 'bg-slate-500/15 text-slate-300 ring-slate-500/30'
    }
})

const statusSoftClass = computed(() => {
    switch (status.value) {
        case 'new':
            return 'bg-yellow-500/10 ring-1 ring-yellow-500/20'
        case 'confirmed':
            return 'bg-orange-500/10 ring-1 ring-orange-500/20'
        case 'checked_in':
            return 'bg-blue-500/10 ring-1 ring-blue-500/20'
        case 'checked_out':
            return 'bg-purple-500/10 ring-1 ring-purple-500/20'
        case 'paid':
            return 'bg-green-500/10 ring-1 ring-green-500/20'
        case 'cancelled':
            return 'bg-slate-500/10 ring-1 ring-slate-500/20'
        case 'no_show':
            return 'bg-red-500/10 ring-1 ring-red-500/20'
        default:
            return 'bg-slate-500/10 ring-1 ring-slate-500/20'
    }
})

const rowTintClass = computed(() => {
    switch (status.value) {
        case 'new':
            return 'bg-yellow-500/5 hover:bg-yellow-500/10'
        case 'confirmed':
            return 'bg-orange-500/5 hover:bg-orange-500/10'
        case 'checked_in':
            return 'bg-blue-500/5 hover:bg-blue-500/10'
        case 'checked_out':
            return 'bg-purple-500/5 hover:bg-purple-500/10'
        case 'paid':
            return 'bg-green-500/5 hover:bg-green-500/10'
        case 'cancelled':
            return 'bg-slate-500/8 hover:bg-slate-500/12'
        case 'no_show':
            return 'bg-red-500/5 hover:bg-red-500/10'
        default:
            return 'bg-slate-800 hover:bg-slate-700'
    }
})

const selectedRowClass = computed(() => {
    switch (status.value) {
        case 'new':
            return 'border-yellow-500/40 bg-yellow-500/15 ring-2 ring-yellow-500/25'
        case 'confirmed':
            return 'border-orange-500/40 bg-orange-500/15 ring-2 ring-orange-500/25'
        case 'checked_in':
            return 'border-blue-500/40 bg-blue-500/15 ring-2 ring-blue-500/25'
        case 'checked_out':
            return 'border-purple-500/40 bg-purple-500/15 ring-2 ring-purple-500/25'
        case 'paid':
            return 'border-green-500/40 bg-green-500/15 ring-2 ring-green-500/25'
        case 'cancelled':
            return 'border-slate-500/40 bg-slate-500/15 ring-2 ring-slate-500/25'
        case 'no_show':
            return 'border-red-500/40 bg-red-500/15 ring-2 ring-red-500/25'
        default:
            return 'border-slate-500/40 bg-slate-500/15 ring-2 ring-slate-500/25'
    }
})

const baseRowClass = computed(() => `border-slate-800 ${rowTintClass.value}`)

const isOvertime = computed(() => {
    if (status.value !== 'checked_in') return false
    if (plannedMinutes.value == null || effectivePlayedMinutes.value == null) return false

    return effectivePlayedMinutes.value > plannedMinutes.value
})

const durationIdleClass = computed(() => {
    switch (status.value) {
        case 'new':
            return 'bg-yellow-500/15 text-yellow-200 ring-1 ring-yellow-500/25'
        case 'confirmed':
            return 'bg-orange-500/15 text-orange-200 ring-1 ring-orange-500/25'
        case 'checked_out':
            return 'bg-purple-500/15 text-purple-200 ring-1 ring-purple-500/25'
        case 'paid':
            return 'bg-green-500/15 text-green-200 ring-1 ring-green-500/25'
        case 'cancelled':
            return 'bg-slate-700 text-slate-300 ring-1 ring-slate-600'
        case 'no_show':
            return 'bg-red-500/15 text-red-200 ring-1 ring-red-500/25'
        default:
            return 'bg-slate-800 text-slate-300 ring-1 ring-slate-700'
    }
})

const durationBoxClass = computed(() => {
    if (status.value === 'checked_in') {
        return isOvertime.value
            ? 'bg-red-500/30 text-red-200 ring-1 ring-red-400/50 shadow-inner'
            : 'bg-blue-500/30 text-blue-100 ring-1 ring-blue-400/50 shadow-inner'
    }

    return durationIdleClass.value
})
</script>
