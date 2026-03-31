<template>
    <section class="flex h-full min-h-0 w-full flex-col rounded-3xl border border-slate-800 bg-slate-900/90 p-5 shadow-xl">
        <div class="mb-4 flex items-center justify-between gap-3">
            <div>
                <h2 class="text-xl font-semibold text-white">Catering</h2>
                <p class="mt-1 text-sm text-slate-400">Aantallen per cateringtype vandaag</p>
            </div>

            <span class="rounded-full border border-slate-700 bg-slate-950/70 px-3 py-1 text-xs text-slate-300">
                {{ items.length }} types
            </span>
        </div>

        <div v-if="items.length" class="flex min-h-0 flex-1 flex-col gap-3 overflow-y-auto pr-1">
            <article
                v-for="item in items"
                :key="item.key"
                class="rounded-3xl border border-slate-800 bg-slate-950/70 p-4"
            >
                <div class="flex items-start justify-between gap-3">
                    <div class="flex min-w-0 items-center gap-3">
                        <div class="flex h-12 w-12 shrink-0 items-center justify-center rounded-2xl border border-slate-700 bg-slate-900 text-2xl">
                            {{ item.emoji }}
                        </div>

                        <div class="min-w-0">
                            <p class="truncate font-semibold text-white">{{ item.label }}</p>
                            <p class="mt-1 text-sm text-slate-400">{{ item.reservations }} reservaties</p>
                        </div>
                    </div>

                    <div class="text-right">
                        <div class="text-2xl font-semibold text-white">{{ item.visitors }}</div>
                        <div class="text-sm text-slate-400">bezoekers</div>
                    </div>
                </div>

                <div class="mt-3 rounded-2xl border border-white/10 bg-black/10 px-3 py-3 text-sm text-slate-200">
                    <p><span class="font-semibold text-white">{{ item.visitors }}</span> bezoekers</p>
                    <p class="mt-1"><span class="font-semibold text-white">{{ item.childrenStudents }}</span> kinderen / studenten</p>
                    <p class="mt-1"><span class="font-semibold text-white">{{ item.adults }}</span> volwassenen</p>
                </div>
            </article>
        </div>

        <div
            v-else
            class="flex flex-1 items-center justify-center rounded-3xl border border-dashed border-slate-700 bg-slate-950/60 px-4 text-center text-sm text-slate-400"
        >
            Geen catering gepland vandaag.
        </div>
    </section>
</template>

<script setup>
defineProps({
    items: {
        type: Array,
        required: true,
    },
})
</script>
