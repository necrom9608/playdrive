<template>
    <div class="calendar">

        <!-- Maand navigatie -->
        <div class="flex items-center justify-between mb-4">
            <button type="button" class="calendar-nav-btn" @click="prevMonth" :disabled="!canGoPrev">
                ‹
            </button>
            <span class="text-sm font-semibold" style="color: var(--text-main);">
                {{ monthLabel }}
            </span>
            <button type="button" class="calendar-nav-btn" @click="nextMonth">
                ›
            </button>
        </div>

        <!-- Weekdag headers -->
        <div class="grid grid-cols-7 mb-1">
            <div
                v-for="day in weekdays"
                :key="day"
                class="text-center text-xs font-medium pb-2"
                style="color: var(--text-soft); opacity: 0.6;"
            >
                {{ day }}
            </div>
        </div>

        <!-- Dagen grid -->
        <div class="grid grid-cols-7 gap-1">
            <!-- Lege cellen voor eerste dag van de maand -->
            <div v-for="n in firstDayOffset" :key="`empty-${n}`" />

            <!-- Dagen -->
            <button
                v-for="day in daysInMonth"
                :key="day.date"
                type="button"
                class="calendar-day"
                :class="dayClass(day)"
                :disabled="day.isPast || day.isTooFar"
                :title="day.label"
                @click="selectDay(day)"
            >
                {{ day.num }}
                <span v-if="day.isOpen" class="calendar-dot" />
            </button>
        </div>

        <!-- Legende -->
        <div class="flex items-center gap-4 mt-4 pt-3" style="border-top: 1px solid rgba(75,98,148,0.2);">
            <div class="flex items-center gap-1.5">
                <span class="calendar-dot-legend open" />
                <span class="text-xs" style="color: var(--text-soft);">Open</span>
            </div>
            <div class="flex items-center gap-1.5">
                <span class="calendar-dot-legend closed" />
                <span class="text-xs" style="color: var(--text-soft);">Gesloten / privé</span>
            </div>
        </div>
    </div>
</template>

<script setup>
import { computed, ref } from 'vue'

const props = defineProps({
    modelValue:   { type: String, default: null },  // 'YYYY-MM-DD'
    openingHours: { type: Array,  default: () => [] },
    seasons:      { type: Array,  default: () => [] },
    exceptions:   { type: Array,  default: () => [] },
})

const emit = defineEmits(['update:modelValue', 'select'])

// ──────────────────────────────────────────────────────────────────────────────
// Maand navigatie
// ──────────────────────────────────────────────────────────────────────────────

const today     = new Date()
today.setHours(0, 0, 0, 0)

const viewYear  = ref(today.getFullYear())
const viewMonth = ref(today.getMonth()) // 0-based

const weekdays  = ['Ma', 'Di', 'Wo', 'Do', 'Vr', 'Za', 'Zo']

const monthLabel = computed(() => {
    const d = new Date(viewYear.value, viewMonth.value, 1)
    return d.toLocaleDateString('nl-BE', { month: 'long', year: 'numeric' })
})

const canGoPrev = computed(() => {
    return viewYear.value > today.getFullYear() ||
        (viewYear.value === today.getFullYear() && viewMonth.value > today.getMonth())
})

function prevMonth() {
    if (!canGoPrev.value) return
    if (viewMonth.value === 0) { viewYear.value--; viewMonth.value = 11 }
    else viewMonth.value--
}

function nextMonth() {
    if (viewMonth.value === 11) { viewYear.value++; viewMonth.value = 0 }
    else viewMonth.value++
}

// ──────────────────────────────────────────────────────────────────────────────
// Dag berekeningen
// ──────────────────────────────────────────────────────────────────────────────

// ISO weekdag: 1=ma, 7=zo
function isoWeekday(date) {
    const d = date.getDay()
    return d === 0 ? 7 : d
}

// Offset voor de eerste dag van de maand (0=ma)
const firstDayOffset = computed(() => {
    const first = new Date(viewYear.value, viewMonth.value, 1)
    return isoWeekday(first) - 1
})

const daysInMonth = computed(() => {
    const count = new Date(viewYear.value, viewMonth.value + 1, 0).getDate()
    const days  = []

    for (let n = 1; n <= count; n++) {
        const date    = new Date(viewYear.value, viewMonth.value, n)
        const dateStr = formatDate(date)
        const info    = getHoursForDate(date)

        days.push({
            num:      n,
            date:     dateStr,
            isPast:   date < today,
            isTooFar: date > new Date(today.getFullYear(), today.getMonth() + 6, today.getDate()),
            isOpen:   info.is_open,
            openFrom: info.open_from,
            openUntil: info.open_until,
            label:    info.is_open
                ? `Open van ${info.open_from} tot ${info.open_until}`
                : 'Gesloten',
        })
    }

    return days
})

// ──────────────────────────────────────────────────────────────────────────────
// Openingsuren logica per datum
// ──────────────────────────────────────────────────────────────────────────────

function formatDate(date) {
    const y = date.getFullYear()
    const m = String(date.getMonth() + 1).padStart(2, '0')
    const d = String(date.getDate()).padStart(2, '0')
    return `${y}-${m}-${d}`
}

function getHoursForDate(date) {
    const dateStr = formatDate(date)

    // 1. Uitzondering heeft altijd voorrang
    const exception = props.exceptions.find((e) => e.date === dateStr)
    if (exception) {
        return {
            is_open:    exception.is_open,
            open_from:  exception.open_from,
            open_until: exception.open_until,
        }
    }

    // 2. Bepaal het seizoen op basis van datum (hoogste prioriteit wint)
    const seasonKey = getSeasonKeyForDate(dateStr)

    // 3. Zoek de openingsuren voor dit seizoen + weekdag
    const weekday = isoWeekday(date)
    const hour    = props.openingHours.find(
        (h) => h.season_key === seasonKey && h.weekday === weekday
    )

    if (!hour) {
        return { is_open: false, open_from: null, open_until: null }
    }

    return {
        is_open:    hour.is_open,
        open_from:  hour.open_from,
        open_until: hour.open_until,
    }
}

function getSeasonKeyForDate(dateStr) {
    // Seizoenen zijn gesorteerd op aflopende prioriteit
    const match = props.seasons.find((s) => dateStr >= s.date_from && dateStr <= s.date_until)
    return match?.season_key ?? 'regular'
}

// ──────────────────────────────────────────────────────────────────────────────
// CSS klassen per dag
// ──────────────────────────────────────────────────────────────────────────────

function dayClass(day) {
    if (day.isPast || day.isTooFar) return 'calendar-day-disabled'
    if (day.date === props.modelValue) return 'calendar-day-selected'
    if (day.isOpen) return 'calendar-day-open'
    return 'calendar-day-closed'
}

// ──────────────────────────────────────────────────────────────────────────────
// Selectie
// ──────────────────────────────────────────────────────────────────────────────

function selectDay(day) {
    if (day.isPast || day.isTooFar) return
    emit('update:modelValue', day.date)
    emit('select', {
        date:      day.date,
        isOpen:    day.isOpen,
        openFrom:  day.openFrom,
        openUntil: day.openUntil,
    })
}
</script>

<style scoped>
.calendar {
    width: 100%;
}

.calendar-nav-btn {
    width: 2rem;
    height: 2rem;
    border-radius: 0.5rem;
    border: 1px solid rgba(75, 98, 148, 0.30);
    background: rgba(15, 23, 42, 0.50);
    color: var(--text-soft);
    font-size: 1.2rem;
    line-height: 1;
    cursor: pointer;
    transition: background 0.15s, border-color 0.15s;
    display: flex;
    align-items: center;
    justify-content: center;
}
.calendar-nav-btn:hover:not(:disabled) {
    background: rgba(59, 130, 246, 0.15);
    border-color: rgba(59, 130, 246, 0.40);
}
.calendar-nav-btn:disabled {
    opacity: 0.25;
    cursor: not-allowed;
}

.calendar-day {
    position: relative;
    aspect-ratio: 1;
    border-radius: 0.5rem;
    border: 1px solid transparent;
    font-size: 0.8125rem;
    font-weight: 500;
    cursor: pointer;
    transition: background 0.12s, border-color 0.12s, color 0.12s;
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    gap: 2px;
}

.calendar-day-open {
    background: rgba(34, 197, 94, 0.08);
    border-color: rgba(34, 197, 94, 0.20);
    color: #d1fae5;
}
.calendar-day-open:hover {
    background: rgba(34, 197, 94, 0.18);
    border-color: rgba(34, 197, 94, 0.40);
}

.calendar-day-closed {
    background: rgba(15, 23, 42, 0.30);
    border-color: rgba(75, 98, 148, 0.12);
    color: rgba(159, 178, 217, 0.30);
    cursor: pointer;
}
.calendar-day-closed:hover {
    background: rgba(251, 191, 36, 0.08);
    border-color: rgba(251, 191, 36, 0.25);
    color: rgba(251, 191, 36, 0.7);
}

.calendar-day-selected {
    background: rgba(59, 130, 246, 0.25) !important;
    border-color: rgba(59, 130, 246, 0.60) !important;
    color: #93c5fd !important;
}

.calendar-day-disabled {
    background: transparent;
    border-color: transparent;
    color: rgba(159, 178, 217, 0.15);
    cursor: not-allowed;
}

.calendar-dot {
    width: 4px;
    height: 4px;
    border-radius: 50%;
    background: rgba(34, 197, 94, 0.7);
    display: block;
}

.calendar-dot-legend {
    width: 8px;
    height: 8px;
    border-radius: 50%;
    display: inline-block;
}
.calendar-dot-legend.open   { background: rgba(34, 197, 94, 0.7); }
.calendar-dot-legend.closed { background: rgba(75, 98, 148, 0.40); }
</style>
