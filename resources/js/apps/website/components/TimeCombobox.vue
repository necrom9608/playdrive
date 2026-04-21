<template>
    <div class="relative" ref="wrapper">
        <input
            ref="input"
            v-model="inputValue"
            type="text"
            class="website-input"
            placeholder="bv. 14:00"
            autocomplete="off"
            @focus="openDropdown"
            @input="openDropdown"
            @blur="onBlur"
            @keydown.arrow-down.prevent="moveHighlight(1)"
            @keydown.arrow-up.prevent="moveHighlight(-1)"
            @keydown.enter.prevent="confirmHighlighted"
            @keydown.escape="closeDropdown"
        />

        <!-- Dropdown -->
        <div
            v-if="open && filteredSlots.length"
            class="time-dropdown"
        >
            <button
                v-for="(slot, idx) in filteredSlots"
                :key="slot"
                type="button"
                class="time-slot"
                :class="{ 'time-slot-highlighted': idx === highlighted }"
                @mousedown.prevent="selectSlot(slot)"
            >
                {{ slot }}
            </button>
        </div>
    </div>
</template>

<script setup>
import { computed, ref, watch } from 'vue'

const props = defineProps({
    modelValue: { type: String, default: '' },
    // Suggesties beperken tot openingsuren van de gekozen dag
    // null = alle uren (privé / geen beperking)
    openFrom:   { type: String, default: null },
    openUntil:  { type: String, default: null },
})

const emit = defineEmits(['update:modelValue'])

const input      = ref(null)
const wrapper    = ref(null)
const open       = ref(false)
const highlighted = ref(-1)
const inputValue = ref(props.modelValue ?? '')

watch(() => props.modelValue, (val) => {
    if (val !== inputValue.value) inputValue.value = val ?? ''
})

watch(inputValue, (val) => {
    emit('update:modelValue', val)
})

// Genereer slots per half uur
function generateSlots(from, until) {
    const slots = []
    const [hFrom, mFrom]   = from.split(':').map(Number)
    const [hUntil, mUntil] = until.split(':').map(Number)
    const startMin = hFrom * 60 + mFrom
    const endMin   = hUntil * 60 + mUntil

    for (let m = startMin; m < endMin; m += 30) {
        const h   = Math.floor(m / 60)
        const min = m % 60
        slots.push(`${String(h).padStart(2, '0')}:${String(min).padStart(2, '0')}`)
    }
    return slots
}

const allSlots = computed(() => {
    if (props.openFrom && props.openUntil) {
        return generateSlots(props.openFrom, props.openUntil)
    }
    // Geen beperking: volledige dag
    return generateSlots('00:00', '23:30')
})

const filteredSlots = computed(() => {
    const q = inputValue.value.trim()
    if (!q) return allSlots.value
    return allSlots.value.filter((s) => s.startsWith(q))
})

function openDropdown() {
    open.value = true
    highlighted.value = -1
}

function closeDropdown() {
    open.value = false
    highlighted.value = -1
}

function onBlur() {
    setTimeout(closeDropdown, 150)
}

function selectSlot(slot) {
    inputValue.value = slot
    emit('update:modelValue', slot)
    closeDropdown()
}

function moveHighlight(dir) {
    if (!open.value) openDropdown()
    const max = filteredSlots.value.length - 1
    highlighted.value = Math.max(0, Math.min(max, highlighted.value + dir))
}

function confirmHighlighted() {
    if (highlighted.value >= 0 && filteredSlots.value[highlighted.value]) {
        selectSlot(filteredSlots.value[highlighted.value])
    }
}
</script>

<style scoped>
.time-dropdown {
    position: absolute;
    top: calc(100% + 4px);
    left: 0;
    right: 0;
    max-height: 200px;
    overflow-y: auto;
    border-radius: 0.875rem;
    border: 1px solid rgba(75, 98, 148, 0.35);
    background: rgba(10, 18, 40, 0.97);
    backdrop-filter: blur(12px);
    z-index: 50;
    padding: 0.25rem;
}

.time-slot {
    width: 100%;
    padding: 0.5rem 0.75rem;
    border-radius: 0.625rem;
    border: none;
    background: transparent;
    color: var(--text-soft);
    font-size: 0.875rem;
    text-align: left;
    cursor: pointer;
    transition: background 0.1s, color 0.1s;
}
.time-slot:hover,
.time-slot-highlighted {
    background: rgba(59, 130, 246, 0.15);
    color: var(--text-main);
}
</style>
