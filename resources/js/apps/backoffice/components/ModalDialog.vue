<template>
    <teleport to="body">
        <div v-if="open" class="fixed inset-0 z-50 flex items-center justify-center p-4">
            <div class="absolute inset-0 bg-slate-950/80" @click="$emit('close')" />

            <div class="relative z-10 max-h-[90vh] w-full max-w-2xl overflow-hidden rounded-3xl border border-slate-800 bg-slate-900 shadow-2xl">
                <div class="flex items-center justify-between border-b border-slate-800 px-6 py-4">
                    <div>
                        <h2 class="text-lg font-semibold text-white">{{ title }}</h2>
                        <p v-if="description" class="mt-1 text-sm text-slate-400">{{ description }}</p>
                    </div>

                    <button
                        type="button"
                        class="rounded-xl border border-slate-700 px-3 py-2 text-sm text-slate-300 transition hover:bg-slate-800"
                        @click="$emit('close')"
                    >
                        Sluiten
                    </button>
                </div>

                <div class="max-h-[calc(90vh-88px)] overflow-y-auto p-6">
                    <slot />
                </div>
            </div>
        </div>
    </teleport>
</template>

<script setup>
defineProps({
    open: { type: Boolean, default: false },
    title: { type: String, required: true },
    description: { type: String, default: '' },
})

defineEmits(['close'])
</script>
