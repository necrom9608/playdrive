<template>
    <div class="rounded-3xl border border-slate-800 bg-slate-900 shadow-xl">
        <div class="border-b border-slate-800 p-4">
            <h3 class="text-lg font-semibold text-white">Weektotalen</h3>
            <p class="mt-1 text-sm text-slate-400">
                Snel overzicht per dag van reservaties, taken en aanwezigen.
            </p>
        </div>

        <div class="grid grid-cols-1 gap-4 p-4 md:grid-cols-2 xl:grid-cols-7">
            <button
                v-for="day in days"
                :key="day.date"
                type="button"
                class="rounded-3xl border p-4 text-left transition hover:-translate-y-0.5 hover:border-blue-500/60 hover:bg-slate-950"
                :class="day.is_today
                    ? 'border-blue-500 bg-blue-500/10'
                    : 'border-slate-800 bg-slate-950/60'"
                @click="$emit('select-day', day.date)"
            >
                <div class="flex items-start justify-between gap-3">
                    <div>
                        <p class="text-xs uppercase tracking-wide text-slate-500">{{ day.weekday_short }}</p>
                        <h4 class="mt-1 text-lg font-semibold text-white">{{ day.day_number }}</h4>
                    </div>

                    <span class="rounded-full bg-slate-800 px-2 py-1 text-xs font-semibold text-slate-300">
                        {{ day.totals.reservations }} res. · {{ day.totals.tasks || 0 }} taken
                    </span>
                </div>

                <div class="mt-4 space-y-2 text-sm">
                    <div class="flex items-center justify-between text-slate-300">
                        <span>Personen</span>
                        <span class="font-semibold text-white">{{ day.totals.participants }}</span>
                    </div>

                    <div class="mt-3 flex flex-wrap gap-1.5">
                        <div
                            v-for="task in activeStatuses(day.task_totals || [])"
                            :key="`task-${task.key}`"
                            class="inline-flex items-center gap-1 rounded-full px-2 py-0.5 text-xs font-medium ring-1"
                            :class="task.colors.badge"
                            :title="`${task.label}: ${task.count}`"
                        >
                            <span class="h-2 w-2 rounded-full" :class="task.colors.accent"></span>
                            <span>Taak {{ task.label }}</span>
                            <span class="font-semibold">{{ task.count }}</span>
                        </div>
                        <div
                            v-for="status in activeStatuses(day.status_totals)"
                            :key="status.key"
                            class="inline-flex items-center gap-1 rounded-full px-2 py-0.5 text-xs font-medium ring-1"
                            :class="status.colors.badge"
                            :title="`${status.label}: ${status.count}`"
                        >
                            <span class="h-2 w-2 rounded-full" :class="status.colors.accent"></span>
                            <span>{{ status.label }}</span>
                            <span class="font-semibold">{{ status.count }}</span>
                        </div>
                    </div>

                    <div class="pt-2 text-xs font-medium text-blue-400">
                        Open dagoverzicht
                    </div>
                </div>
            </button>
        </div>
    </div>
</template>

<script setup>
defineProps({
    days: {
        type: Array,
        default: () => [],
    },
})

defineEmits(['select-day'])

function activeStatuses(statusTotals = []) {
    return statusTotals.filter(status => status.count > 0)
}
</script>
