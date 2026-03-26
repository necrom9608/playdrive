<template>
    <div
        v-if="open"
        class="fixed inset-0 z-[100] flex items-center justify-center bg-black/70 p-6 backdrop-blur-sm"
    >
        <div class="flex h-auto max-h-[90vh] w-full max-w-[1100px] flex-col overflow-hidden rounded-3xl border border-slate-800 bg-slate-900 shadow-2xl">
            <div class="flex items-center justify-between border-b border-slate-800 px-6 py-4">
                <h2 class="text-xl font-semibold text-slate-100">
                    {{ isEdit ? 'Reservatie bewerken' : 'Nieuwe reservatie' }}
                </h2>

                <button
                    type="button"
                    class="inline-flex h-10 w-10 items-center justify-center rounded-xl text-slate-400 transition hover:bg-slate-800 hover:text-white"
                    @click="$emit('close')"
                >
                    <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12" />
                    </svg>
                </button>
            </div>

            <div class="min-h-0 flex-1 overflow-y-auto bg-slate-950 p-6">
                <RegistrationForm
                    :initial-values="initialValues"
                    @cancel="$emit('close')"
                    @submit="$emit('submit', $event)"
                />
            </div>
        </div>
    </div>
</template>

<script setup>
import { computed } from 'vue'
import RegistrationForm from './RegistrationForm.vue'

const props = defineProps({
    open: {
        type: Boolean,
        default: false,
    },
    initialValues: {
        type: Object,
        default: () => ({}),
    },
})

defineEmits(['close', 'submit'])

const isEdit = computed(() => !!props.initialValues?.id)
</script>
