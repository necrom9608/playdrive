<template>
    <section class="flex w-full flex-col rounded-3xl border border-slate-800 bg-slate-900/90 p-5 shadow-xl">

        <div class="grid gap-3 md:grid-cols-5">
            <button
                v-for="action in actions"
                :key="action.key"
                type="button"
                class="flex min-h-[104px] items-center gap-4 rounded-3xl border p-4 text-left transition hover:-translate-y-0.5"
                :class="action.class"
                @click="$emit(action.emit)"
            >
                <div class="rounded-2xl border border-white/10 bg-black/10 p-3 text-white/95">
                    <component :is="action.icon" class="h-7 w-7" />
                </div>

                <div class="min-w-0">
                    <div class="text-base font-semibold text-white">{{ action.label }}</div>
                    <p class="mt-1 text-sm text-white/75">{{ action.description }}</p>
                </div>
            </button>
        </div>
    </section>
</template>

<script setup>
defineProps({
    actions: {
        type: Array,
        required: true,
    },
})

defineEmits([
    'new-registration',
    'member-attendance',
    'create-member',
    'staff-attendance',
    'create-task',
])
</script>
