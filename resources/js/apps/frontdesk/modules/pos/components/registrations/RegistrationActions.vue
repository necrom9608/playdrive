<template>
    <div class="border-t border-slate-800 bg-slate-900 p-4">
        <div class="flex gap-3">
            <div class="flex-1 space-y-3">
                <div class="grid grid-cols-3 gap-3">
                    <button
                        type="button"
                        class="inline-flex h-12 items-center justify-center gap-2 rounded-2xl px-4 text-sm font-semibold shadow-sm transition"
                        :class="buttonClass(canCheckIn, 'bg-blue-600 hover:bg-blue-700')"
                        :disabled="!canCheckIn"
                        @click="$emit('check-in')"
                    >
                        <ArrowRightCircleIcon class="h-5 w-5" />
                        <span>Inchecken</span>
                    </button>

                    <button
                        type="button"
                        class="inline-flex h-12 items-center justify-center gap-2 rounded-2xl px-4 text-sm font-semibold shadow-sm transition"
                        :class="buttonClass(canCheckOut, 'bg-emerald-600 hover:bg-emerald-700')"
                        :disabled="!canCheckOut"
                        @click="$emit('check-out')"
                    >
                        <ArrowLeftCircleIcon class="h-5 w-5" />
                        <span>Uitchecken</span>
                    </button>

                    <button
                        type="button"
                        class="inline-flex h-12 items-center justify-center gap-2 rounded-2xl px-4 text-sm font-semibold shadow-sm transition"
                        :class="buttonClass(canEdit, 'bg-amber-500 hover:bg-amber-600')"
                        :disabled="!canEdit"
                        @click="$emit('edit')"
                    >
                        <PencilSquareIcon class="h-5 w-5" />
                        <span>Bewerken</span>
                    </button>
                </div>

                <div class="grid grid-cols-3 gap-3">
                    <button
                        type="button"
                        class="inline-flex h-12 items-center justify-center gap-2 rounded-2xl px-4 text-sm font-semibold shadow-sm transition"
                        :class="buttonClass(canCancel, 'bg-slate-600 hover:bg-slate-500')"
                        :disabled="!canCancel"
                        @click="$emit('cancel')"
                    >
                        <NoSymbolIcon class="h-5 w-5" />
                        <span>Annuleren</span>
                    </button>

                    <button
                        type="button"
                        class="inline-flex h-12 items-center justify-center gap-2 rounded-2xl px-4 text-sm font-semibold shadow-sm transition"
                        :class="buttonClass(canNoShow, 'bg-rose-600 hover:bg-rose-700')"
                        :disabled="!canNoShow"
                        @click="$emit('no-show')"
                    >
                        <UserMinusIcon class="h-5 w-5" />
                        <span>No-show</span>
                    </button>

                    <button
                        type="button"
                        class="inline-flex h-12 items-center justify-center gap-2 rounded-2xl px-4 text-sm font-semibold shadow-sm transition"
                        :class="buttonClass(canDelete, 'bg-red-600 hover:bg-red-700')"
                        :disabled="!canDelete"
                        @click="$emit('delete')"
                    >
                        <TrashIcon class="h-5 w-5" />
                        <span>Verwijderen</span>
                    </button>
                </div>
            </div>

            <div class="w-56 shrink-0">
                <button
                    type="button"
                    class="inline-flex h-full min-h-[108px] w-full flex-col items-center justify-center gap-2 rounded-2xl px-4 text-sm font-semibold shadow-sm transition"
                    :class="hasSelection ? 'bg-slate-700 text-white hover:bg-slate-600' : 'cursor-not-allowed bg-slate-800 text-slate-500'"
                    :disabled="!hasSelection"
                    @click="$emit('clear-selection')"
                >
                    <XMarkIcon class="h-6 w-6" />
                    <span>Selectie wissen</span>
                </button>
            </div>
        </div>
    </div>
</template>

<script setup>
import { computed } from 'vue'
import {
    ArrowRightCircleIcon,
    ArrowLeftCircleIcon,
    PencilSquareIcon,
    NoSymbolIcon,
    UserMinusIcon,
    TrashIcon,
    XMarkIcon,
} from '@heroicons/vue/24/outline'

const props = defineProps({
    selectedReservation: {
        type: Object,
        default: null,
    },
})

defineEmits([
    'check-in',
    'check-out',
    'edit',
    'cancel',
    'no-show',
    'delete',
    'clear-selection',
])

const hasSelection = computed(() => !!props.selectedReservation)
const status = computed(() => props.selectedReservation?.status ?? null)

const canCheckIn = computed(() => {
    return hasSelection.value && ['new', 'confirmed'].includes(status.value)
})

const canCheckOut = computed(() => {
    return hasSelection.value && status.value === 'checked_in'
})

const canEdit = computed(() => {
    return hasSelection.value
})

const canCancel = computed(() => {
    return hasSelection.value && ['new', 'confirmed'].includes(status.value)
})

const canNoShow = computed(() => {
    return hasSelection.value && ['new', 'confirmed'].includes(status.value)
})

const canDelete = computed(() => {
    return hasSelection.value
})

function buttonClass(enabled, activeClass) {
    return enabled
        ? `${activeClass} text-white`
        : 'cursor-not-allowed bg-slate-800 text-slate-500'
}
</script>
