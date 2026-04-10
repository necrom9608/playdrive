<template>
    <div class="flex h-full w-full items-center justify-start overflow-hidden">
        <img
            v-if="hasImage"
            :src="src"
            :alt="tenantName"
            class="block max-h-full max-w-full object-contain"
            @error="failed = true"
        >

        <div v-else class="text-left">
            <div class="text-[11px] uppercase tracking-[0.24em] text-slate-400">Customer display</div>
            <div class="mt-1 text-2xl font-black tracking-tight text-white">{{ tenantName }}</div>
        </div>
    </div>
</template>

<script setup>
import { computed, ref } from 'vue'

const props = defineProps({
    src: {
        type: String,
        default: '',
    },
    tenantName: {
        type: String,
        default: 'PlayDrive',
    },
})

const failed = ref(false)
const hasImage = computed(() => Boolean(props.src) && !failed.value)
</script>
