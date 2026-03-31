<template>
    <section class="flex h-full min-h-0 w-full flex-col rounded-3xl border border-slate-800 bg-slate-900/90 p-5 shadow-xl">
        <div class="mb-4 flex items-center justify-between gap-3">
            <div>
                <h2 class="text-xl font-semibold text-white">Taken</h2>
                <p class="mt-1 text-sm text-slate-400">Open taken voor vandaag en algemene opvolging</p>
            </div>
            <span class="rounded-full border border-amber-500/20 bg-amber-500/10 px-3 py-1 text-xs font-semibold text-amber-200">
                {{ tasks.length }} open
            </span>
        </div>

        <div v-if="tasks.length" class="flex min-h-0 flex-1 flex-col gap-3 overflow-y-auto pr-1">
            <article
                v-for="task in tasks"
                :key="task.id"
                class="rounded-3xl border border-slate-800 bg-slate-950/70 p-4"
            >
                <div class="flex items-start justify-between gap-3">
                    <div>
                        <p class="font-semibold text-white">{{ task.title }}</p>
                        <p class="mt-2 line-clamp-3 text-sm text-slate-400">{{ task.description || 'Geen omschrijving' }}</p>
                    </div>
                    <span class="rounded-full border border-amber-500/20 bg-amber-500/10 px-2.5 py-1 text-[11px] font-semibold text-amber-200">
                        Open
                    </span>
                </div>

                <div class="mt-4 flex flex-wrap items-center justify-between gap-3">
                    <div class="flex flex-wrap gap-2 text-xs text-slate-300">
                        <span class="rounded-full border border-slate-700 bg-slate-900 px-2.5 py-1">{{ task.task_type_label }}</span>
                        <span v-if="task.due_date_label" class="rounded-full border border-slate-700 bg-slate-900 px-2.5 py-1">{{ task.due_date_label }}</span>
                        <span v-if="task.assigned_user_name" class="rounded-full border border-slate-700 bg-slate-900 px-2.5 py-1">{{ task.assigned_user_name }}</span>
                    </div>

                    <button
                        type="button"
                        class="rounded-2xl border border-emerald-500/20 bg-emerald-500/10 px-3 py-2 text-xs font-semibold text-emerald-200 transition hover:bg-emerald-500/20"
                        @click="$emit('complete-task', task)"
                    >
                        Afgewerkt
                    </button>
                </div>
            </article>
        </div>

        <div
            v-else
            class="flex flex-1 items-center justify-center rounded-3xl border border-dashed border-slate-700 bg-slate-950/60 px-4 text-center text-sm text-slate-400"
        >
            Geen open taken.
        </div>
    </section>
</template>

<script setup>
defineEmits(['complete-task'])

defineProps({
    tasks: {
        type: Array,
        required: true,
    },
})
</script>
