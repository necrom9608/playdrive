<template>
    <section class="rounded-[2rem] border border-white/10 bg-slate-900/65 p-3 shadow-2xl shadow-slate-950/40 backdrop-blur-xl">
        <div class="grid grid-cols-4 gap-3">
            <div
                v-for="stat in stats"
                :key="stat.label"
                class="rounded-[1.5rem] border border-white/8 bg-gradient-to-br from-white/8 to-white/4 px-4 py-4 transition-transform duration-300 hover:-translate-y-0.5"
            >
                <div class="flex items-center gap-3">
                    <div class="flex h-11 w-11 items-center justify-center rounded-2xl bg-blue-500/15 text-blue-100 shadow-lg shadow-blue-500/10">
                        <component :is="stat.icon" class="h-5 w-5" />
                    </div>
                    <div class="min-w-0 flex-1">
                        <div class="text-[11px] uppercase tracking-[0.22em] text-slate-400">{{ stat.label }}</div>
                        <Transition name="stat-value" mode="out-in">
                            <div :key="`${stat.label}-${stat.value}`" class="mt-1 truncate text-xl font-bold text-white">{{ stat.value }}</div>
                        </Transition>
                    </div>
                </div>
            </div>
        </div>
    </section>
</template>

<script setup>
import { computed } from 'vue'
import {
    ClockIcon,
    FlagIcon,
    PlayIcon,
    UserGroupIcon,
} from '@heroicons/vue/24/outline'

const props = defineProps({
    totalPersonsLabel: {
        type: String,
        default: '-',
    },
    playedTimeLabel: {
        type: String,
        default: '00:00',
    },
    startTimeLabel: {
        type: String,
        default: '-',
    },
    endTimeLabel: {
        type: String,
        default: '-',
    },
})

const stats = computed(() => [
    { label: 'Personen', value: props.totalPersonsLabel, icon: UserGroupIcon },
    { label: 'Tijd gespeeld', value: props.playedTimeLabel, icon: ClockIcon },
    { label: 'Startuur', value: props.startTimeLabel, icon: PlayIcon },
    { label: 'Einduur', value: props.endTimeLabel, icon: FlagIcon },
])
</script>

<style scoped>
.stat-value-enter-active,
.stat-value-leave-active {
    transition: all 180ms ease;
}

.stat-value-enter-from {
    opacity: 0;
    transform: translateY(6px);
}

.stat-value-leave-to {
    opacity: 0;
    transform: translateY(-6px);
}
</style>
