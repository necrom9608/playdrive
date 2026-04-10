<template>
    <section class="rounded-[2rem] border border-slate-800 bg-slate-900/70 px-5 py-5 shadow-2xl shadow-slate-950/40">
        <div class="grid grid-cols-2 gap-3">
            <div class="rounded-2xl border border-slate-800 bg-slate-950/60 px-4 py-4 text-left">
                <div class="text-[11px] uppercase tracking-[0.18em] text-slate-400">Personen</div>
                <div class="mt-2 text-xl font-semibold text-white">{{ totalPersonsLabel }}</div>
            </div>
            <div class="rounded-2xl border border-slate-800 bg-slate-950/60 px-4 py-4 text-left">
                <div class="text-[11px] uppercase tracking-[0.18em] text-slate-400">Tijd gespeeld</div>
                <div class="mt-2 text-xl font-semibold text-white">{{ playedTimeLabel }}</div>
            </div>
            <div class="rounded-2xl border border-slate-800 bg-slate-950/60 px-4 py-4 text-left">
                <div class="text-[11px] uppercase tracking-[0.18em] text-slate-400">Startuur</div>
                <div class="mt-2 text-xl font-semibold text-white">{{ startTimeLabel }}</div>
            </div>
            <div class="rounded-2xl border border-slate-800 bg-slate-950/60 px-4 py-4 text-left">
                <div class="text-[11px] uppercase tracking-[0.18em] text-slate-400">Einduur</div>
                <div class="mt-2 text-xl font-semibold text-white">{{ endTimeLabel }}</div>
            </div>
        </div>
    </section>
</template>

<script setup>
defineProps({
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
</script>
