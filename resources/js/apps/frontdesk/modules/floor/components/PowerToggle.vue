<template>
    <button
        type="button"
        class="inline-flex shrink-0 items-center justify-center rounded-full border transition disabled:cursor-not-allowed"
        :class="[sizeClass, stateClass]"
        :disabled="disabled || pending"
        :title="title"
        :aria-label="on ? 'Stroom uitzetten' : 'Stroom aanzetten'"
        :aria-pressed="on"
        @click.stop="$emit('toggle')"
    >
        <ArrowPathIcon v-if="pending" :class="iconClass" class="animate-spin" />
        <ExclamationTriangleIcon v-else-if="unreachable" :class="iconClass" />
        <PowerIcon v-else :class="iconClass" />
    </button>
</template>

<script setup>
import { computed } from 'vue'
import { ArrowPathIcon, ExclamationTriangleIcon, PowerIcon } from '@heroicons/vue/24/outline'

const props = defineProps({
    on: { type: Boolean, default: false },
    pending: { type: Boolean, default: false },
    unreachable: { type: Boolean, default: false },
    disabled: { type: Boolean, default: false },
    title: { type: String, default: '' },
    size: { type: String, default: 'md' }, // md | lg
})
defineEmits(['toggle'])

const sizeClass = computed(() => (props.size === 'lg' ? 'h-12 w-12' : 'h-10 w-10'))
const iconClass = computed(() => (props.size === 'lg' ? 'h-6 w-6' : 'h-5 w-5'))
const stateClass = computed(() => {
    if (props.unreachable) return 'border-rose-500/40 bg-rose-500/10 text-rose-300 hover:bg-rose-500/20'
    if (props.on) return 'border-amber-400/60 bg-amber-400/15 text-amber-300 shadow-[0_0_14px_rgba(251,191,36,0.25)] hover:bg-amber-400/25'
    return 'border-slate-700 bg-slate-950 text-slate-500 hover:border-slate-500 hover:text-slate-200'
})
</script>
