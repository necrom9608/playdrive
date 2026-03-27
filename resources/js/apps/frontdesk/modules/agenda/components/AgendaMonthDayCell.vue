<template>
    <button
        type="button"
        class="flex h-full min-h-0 flex-col overflow-hidden bg-slate-950 px-2 py-2 text-left transition hover:bg-slate-900"
        :class="[
            day.is_today ? 'ring-1 ring-inset ring-blue-500' : '',
            !day.is_current_month ? 'bg-slate-950/60' : '',
        ]"
        @click="$emit('select-day', day.date)"
    >
        <div class="flex items-center justify-between gap-2">
            <span
                class="text-sm font-semibold leading-none"
                :class="day.is_selected ? 'text-blue-400' : (!day.is_current_month ? 'text-slate-500' : 'text-white')"
            >
                {{ day.day_number }}
            </span>

            <span class="rounded-full bg-slate-800 px-1.5 py-0.5 text-[10px] font-semibold leading-none text-slate-300">
                {{ headerTotal }}
            </span>
        </div>

        <div class="mt-1 text-[10px] leading-none text-slate-500">
            {{ day.totals.reservations }} res.
        </div>

        <div class="mt-2 flex flex-wrap content-start gap-1.5">
            <span
                v-for="tag in visibleTags"
                :key="tag.id"
                class="inline-flex max-w-full items-center gap-1.5 rounded-full px-2 py-1 text-[11px] font-medium leading-none ring-1"
                :class="tag.badge"
                :title="`${tag.label}: ${tag.value}`"
            >
                <span class="h-2 w-2 shrink-0 rounded-full" :class="tag.accent"></span>
                <span class="truncate">{{ tag.shortLabel }}</span>
                <span class="shrink-0 font-semibold">{{ tag.value }}</span>
            </span>
        </div>
    </button>
</template>

<script setup>
import { computed } from 'vue'

const props = defineProps({
    day: {
        type: Object,
        required: true,
    },
})

defineEmits(['select-day'])

const headerTotal = computed(() => {
    const participants = Number(props.day?.totals?.participants ?? 0)
    if (participants > 0) {
        return participants
    }

    return Number(props.day?.totals?.reservations ?? 0)
})

const visibleTags = computed(() => {
    const statusTags = (props.day?.status_totals ?? [])
        .map((status) => {
            const peopleCount = Number(status.people_count ?? 0)
            const count = Number(status.count ?? 0)
            const value = peopleCount > 0 ? peopleCount : count

            return {
                id: `status-${status.key}`,
                label: status.label,
                shortLabel: shortStatusLabel(status.label),
                value,
                badge: status.colors?.badge ?? 'bg-slate-500/15 text-slate-300 ring-slate-500/30',
                accent: status.colors?.accent ?? 'bg-slate-500',
            }
        })
        .filter(tag => tag.value > 0)

    const cateringTags = (props.day?.catering_totals ?? [])
        .map((item) => {
            const peopleCount = Number(item.people_count ?? 0)
            const count = Number(item.count ?? 0)
            const value = peopleCount > 0 ? peopleCount : count

            return {
                id: `catering-${item.key}`,
                label: item.label,
                normalizedLabel: normalizeLabel(item.label),
                shortLabel: shortCateringLabel(item.label, item.emoji),
                value,
                badge: item.badge ?? 'bg-cyan-500/15 text-cyan-300 ring-cyan-500/30',
                accent: item.accent ?? 'bg-cyan-500',
            }
        })
        .filter(tag => tag.value > 0)
        .filter(tag => !isEmptyCatering(tag.normalizedLabel))

    return [...statusTags, ...cateringTags].slice(0, 4)
})

function shortStatusLabel(label) {
    const map = {
        Nieuw: 'Nieuw',
        Bevestigd: 'Bevest.',
        Ingecheckt: 'In',
        Uitgecheckt: 'Uit',
        Betaald: 'Betaald',
        Geannuleerd: 'Geann.',
        'No-show': 'No-show',
    }

    return map[label] ?? label
}

function shortCateringLabel(label, emoji = null) {
    const base = label?.length > 12 ? `${label.slice(0, 10)}…` : (label || 'Catering')
    return emoji ? `${emoji} ${base}` : base
}

function normalizeLabel(label) {
    return String(label ?? '').trim().toLowerCase()
}

function isEmptyCatering(label) {
    return ['', 'geen', 'none', 'nvt', 'n.v.t.', 'zonder', 'no catering'].includes(label)
}
</script>
