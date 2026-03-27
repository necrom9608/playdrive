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
                        class="inline-flex h-10 w-10 items-center justify-center rounded-xl border border-slate-700 text-slate-300 transition hover:bg-slate-800 hover:text-white"
                        @click="$emit('close')"
                        aria-label="Sluiten"
                    >
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor">
                            <path fill-rule="evenodd" d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z" clip-rule="evenodd" />
                        </svg>
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
