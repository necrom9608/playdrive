<template>
    <label class="inline-flex cursor-pointer items-center gap-2 rounded-xl border border-slate-700 bg-slate-900/60 px-4 py-2.5 text-sm font-medium text-slate-200 transition hover:border-cyan-500 hover:text-cyan-300">
        <slot />
        <input
            ref="fileInput"
            type="file"
            class="hidden"
            :accept="accept"
            :multiple="multiple"
            @change="onChange"
        />
    </label>
</template>

<script setup>
import { ref } from 'vue'

const props = defineProps({
    accept: { type: String, default: '*' },
    multiple: { type: Boolean, default: false },
})

const emit = defineEmits(['selected'])
const fileInput = ref(null)

function onChange(event) {
    const files = Array.from(event.target.files || [])
    if (files.length) {
        emit('selected', files)
    }
    if (fileInput.value) {
        fileInput.value.value = ''
    }
}
</script>
