<template>
    <div class="flex flex-col gap-2">
        <button
            type="button"
            :disabled="disabled"
            class="inline-flex items-center justify-center gap-2 rounded-2xl border border-slate-700 bg-slate-800/90 px-4 py-3 text-sm font-semibold text-white transition hover:border-sky-500 hover:bg-slate-700/90 disabled:cursor-not-allowed disabled:opacity-50"
            @click="openModal = true"
        >
            <CreditCardIcon class="h-5 w-5" />
            <span>{{ buttonLabel }}</span>
        </button>

        <p
            v-if="showValue && modelValue"
            class="rounded-xl border border-slate-800 bg-slate-900/70 px-3 py-2 text-xs text-slate-300"
        >
            {{ modelValue }}
        </p>

        <ScanRfidModal
            v-model:open="openModal"
            :title="title"
            :description="description"
            :confirm-label="confirmLabel"
            :auto-confirm="autoConfirm"
            @scanned="handleScanned"
            @confirmed="handleConfirmed"
        />
    </div>
</template>

<script setup>
import { computed, ref } from 'vue'
import { CreditCardIcon } from '@heroicons/vue/24/outline'
import ScanRfidModal from './ScanRfidModal.vue'

const props = defineProps({
    modelValue: {
        type: String,
        default: '',
    },
    label: {
        type: String,
        default: 'Scan RFID',
    },
    title: {
        type: String,
        default: 'Scan RFID',
    },
    description: {
        type: String,
        default: 'Scan een RFID-tag of badge.',
    },
    confirmLabel: {
        type: String,
        default: 'Bevestigen',
    },
    autoConfirm: {
        type: Boolean,
        default: true,
    },
    disabled: {
        type: Boolean,
        default: false,
    },
    showValue: {
        type: Boolean,
        default: false,
    },
})

const emit = defineEmits(['update:modelValue', 'scanned', 'confirmed'])

const openModal = ref(false)

const buttonLabel = computed(() => {
    if (props.showValue && props.modelValue) {
        return `${props.label}: ${props.modelValue}`
    }

    return props.label
})

function handleScanned(value) {
    emit('update:modelValue', value)
    emit('scanned', value)
}

function handleConfirmed(value) {
    emit('update:modelValue', value)
    emit('confirmed', value)
}
</script>
