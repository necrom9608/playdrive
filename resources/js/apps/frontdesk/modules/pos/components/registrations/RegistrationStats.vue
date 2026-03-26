<template>
    <div class="border-b border-slate-800 bg-slate-900/80 px-4 pb-4">
        <div class="grid grid-cols-2 gap-3 xl:grid-cols-5">
            <div class="rounded-2xl border border-slate-700 bg-slate-800 p-3 shadow-sm">
                <div class="text-[11px] font-medium uppercase tracking-wide text-slate-400">
                    Totaal
                </div>

                <div class="mt-1 text-2xl font-semibold leading-none text-slate-100">
                    {{ stats.totalReservations }}
                    <span class="text-slate-500">/</span>
                    {{ stats.totalPersons }}
                </div>
            </div>

            <div class="rounded-2xl border border-orange-900 bg-orange-950/40 p-3 shadow-sm">
                <div class="text-[11px] font-medium uppercase tracking-wide text-orange-400">
                    Gereserveerd
                </div>

                <div class="mt-1 text-2xl font-semibold leading-none text-orange-300">
                    {{ stats.confirmedReservations }}
                    <span class="text-orange-700">/</span>
                    {{ stats.confirmedPersons }}
                </div>
            </div>

            <div class="rounded-2xl border border-blue-900 bg-blue-950/40 p-3 shadow-sm">
                <div class="text-[11px] font-medium uppercase tracking-wide text-blue-400">
                    In
                </div>

                <div class="mt-1 text-2xl font-semibold leading-none text-blue-300">
                    {{ stats.checkedInReservations }}
                    <span class="text-blue-700">/</span>
                    {{ stats.checkedInPersons }}
                </div>
            </div>

            <div class="rounded-2xl border border-green-900 bg-green-950/40 p-3 shadow-sm">
                <div class="text-[11px] font-medium uppercase tracking-wide text-green-400">
                    Uit
                </div>

                <div class="mt-1 text-2xl font-semibold leading-none text-green-300">
                    {{ stats.checkedOutReservations }}
                    <span class="text-green-700">/</span>
                    {{ stats.checkedOutPersons }}
                </div>
            </div>

            <div class="rounded-2xl border border-red-900 bg-red-950/40 p-3 shadow-sm">
                <div class="text-[11px] font-medium uppercase tracking-wide text-red-400">
                    No-show
                </div>

                <div class="mt-1 text-2xl font-semibold leading-none text-red-300">
                    {{ stats.noShowReservations }}
                    <span class="text-red-700">/</span>
                    {{ stats.noShowPersons }}
                </div>
            </div>
        </div>
    </div>
</template>

<script setup>
defineProps({
    stats: {
        type: Object,
        required: true,
    },
})
</script>
