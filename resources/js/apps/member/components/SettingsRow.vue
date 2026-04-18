<template>
    <button class="w-full flex items-center gap-3 py-3 border-b border-slate-800/40 last:border-0" @click="$emit('click')">
        <div class="w-8 h-8 rounded-xl bg-slate-800/60 flex items-center justify-center shrink-0">
            <component :is="icon" class="w-4 h-4 text-slate-400" />
        </div>
        <span class="flex-1 text-sm text-slate-200 text-left">{{ label }}</span>
        <ChevronRightIcon class="w-4 h-4 text-slate-600" />
    </button>
</template>

<script setup>
import { ChevronRightIcon } from '@heroicons/vue/24/outline'
defineProps({ icon: Object, label: String })
defineEmits(['click'])
</script>
