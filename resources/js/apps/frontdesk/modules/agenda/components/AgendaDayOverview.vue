<template>
    <div class="space-y-4">
        <div
            v-if="!items.length"
            class="rounded-3xl border border-dashed border-slate-700 bg-slate-900/60 px-6 py-10 text-center text-sm text-slate-400"
        >
            Geen reservaties of taken voor deze dag.
        </div>

        <article
            v-for="registration in items"
            :key="`${registration.item_type}-${registration.id}`"
            class="rounded-3xl border border-slate-800 bg-slate-900/80 p-5 shadow-lg shadow-slate-950/20"
        >
            <div class="flex flex-col gap-4 xl:flex-row xl:items-start xl:justify-between">
                <div class="space-y-3">
                    <div class="flex flex-wrap items-center gap-2">
                        <span
                            class="rounded-full border px-2.5 py-1 text-xs font-semibold"
                            :class="registration.item_type === 'task'
                                ? 'border-pink-400/30 bg-pink-500/10 text-pink-200'
                                : 'border-blue-400/20 bg-blue-500/10 text-blue-200'"
                        >
                            {{ registration.item_type === 'task' ? 'Taak' : 'Reservatie' }}
                        </span>

                        <h4 class="text-lg font-semibold text-white">
                            {{ registration.name }}
                        </h4>

                        <span
                            class="rounded-full border px-2.5 py-1 text-xs font-semibold"
                            :class="registration.status_color?.badge ?? 'border-slate-700 bg-slate-800 text-slate-200'"
                        >
                            {{ registration.status_label }}
                        </span>
                    </div>

                    <div class="flex flex-wrap items-center gap-2 text-sm text-slate-300">
                        <span
                            class="inline-flex items-center gap-2 rounded-2xl border border-slate-800 bg-slate-950/70 px-3 py-1.5"
                        >
                            <span class="text-base">🕒</span>
                            {{ formatTimeRange(registration) }}
                        </span>

                        <span
                            v-if="registration.item_type === 'task' && registration.is_recurring"
                            class="inline-flex items-center gap-2 rounded-2xl border border-pink-400/20 bg-pink-500/10 px-3 py-1.5 text-pink-200"
                        >
                            <span class="text-base">🔁</span>
                            Herhalend
                        </span>

                        <span
                            v-if="registration.item_type === 'task' && registration.visibility_label"
                            class="inline-flex items-center gap-2 rounded-2xl border border-slate-800 bg-slate-950/70 px-3 py-1.5"
                        >
                            <span class="text-base">📌</span>
                            {{ registration.visibility_label }}
                        </span>

                        <span
                            v-if="registration.item_type !== 'task' && registration.stay_option"
                            class="inline-flex items-center gap-2 rounded-2xl border border-slate-800 bg-slate-950/70 px-3 py-1.5"
                        >
                            <span class="text-base">⏱️</span>
                            {{ registration.stay_option }}
                        </span>
                    </div>

                    <p
                        v-if="registration.comment"
                        class="max-w-4xl rounded-2xl border border-slate-800 bg-slate-950/60 px-4 py-3 text-sm leading-6 text-slate-300"
                    >
                        {{ registration.comment }}
                    </p>
                </div>

                <div class="flex flex-wrap gap-2 xl:justify-end">
                    <template v-if="registration.item_type === 'task'">
                        <MetaTag
                            :label="registration.assigned_user_name ? `👤 ${registration.assigned_user_name}` : '🌍 Algemeen'"
                        />
                        <MetaTag
                            v-if="registration.scheduled_by"
                            :label="`🗓️ ${registration.scheduled_by}`"
                        />
                    </template>

                    <template v-else>
                        <MetaTag
                            v-if="registration.event_type"
                            :label="`${registration.event_type_emoji || '🎉'} ${registration.event_type}`"
                        />
                        <MetaTag
                            v-if="registration.catering_option"
                            :label="`${registration.catering_option_emoji || '🍽️'} ${registration.catering_option}`"
                        />
                        <MetaTag
                            :label="`👥 ${registration.participants_children ?? 0}/${registration.participants_adults ?? 0}/${registration.participants_supervisors ?? 0}`"
                        />
                        <MetaTag
                            v-if="registration.invoice_requested"
                            label="🧾 Factuur"
                        />
                        <MetaTag
                            v-if="registration.outside_opening_hours"
                            label="🌙 Buiten openingsuren"
                        />
                    </template>
                </div>
            </div>
        </article>
    </div>
</template>

<script setup>
import { computed } from 'vue'
import MetaTag from './MetaTag.vue'

const props = defineProps({
    items: {
        type: Array,
        default: () => [],
    },
})

const items = computed(() => props.items ?? [])

function formatTime(value) {
    if (!value) return null

    const stringValue = String(value)

    if (stringValue.length >= 5) {
        return stringValue.slice(0, 5)
    }

    return stringValue
}

function formatTimeRange(item) {
    const start = formatTime(item.event_time || item.start_time)
    const end = formatTime(item.end_time)

    if (start && end) {
        return `${start} - ${end}`
    }

    if (start) {
        return start
    }

    if (item.item_type === 'task') {
        return 'Geen uur ingesteld'
    }

    return 'Tijd onbekend'
}
</script>
