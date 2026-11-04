<template>
    <div>
        <span class="mb-1.5 block text-sm font-medium text-slate-300">{{ label }}</span>
        <div class="rounded-2xl border border-slate-800 bg-slate-950/40 p-3">
            <div v-if="modelValue.length" class="flex flex-wrap gap-2">
                <span
                    v-for="(item, i) in modelValue"
                    :key="`${i}-${item}`"
                    class="inline-flex items-center gap-1.5 rounded-full bg-orange-500/15 px-3 py-1 text-xs font-semibold uppercase tracking-wider text-orange-200"
                >
                    {{ item }}
                    <button type="button" class="text-orange-300/70 hover:text-white" title="Verwijderen" @click="remove(i)">
                        <XMarkIcon class="h-3.5 w-3.5" />
                    </button>
                </span>
            </div>
            <div v-else class="flex flex-wrap gap-2">
                <span
                    v-for="item in placeholderItems"
                    :key="item"
                    class="rounded-full border border-dashed border-slate-700 px-3 py-1 text-xs uppercase tracking-wider text-slate-500"
                >{{ item }}</span>
            </div>
            <div class="mt-3 flex gap-2">
                <input
                    v-model="draft"
                    maxlength="60"
                    placeholder="Tekst toevoegen…"
                    class="min-w-0 flex-1 rounded-xl border border-slate-800 bg-slate-950/60 px-3 py-2 text-sm text-white outline-none placeholder:text-slate-500 focus:border-blue-500/60"
                    @keydown.enter.prevent="add"
                />
                <button type="button" class="rounded-xl bg-slate-800 px-3 py-2 text-sm font-semibold text-white transition hover:bg-slate-700" @click="add">
                    Toevoegen
                </button>
            </div>
            <button
                v-if="!modelValue.length && placeholderItems.length"
                type="button"
                class="mt-2 text-xs text-slate-400 underline-offset-2 hover:text-white hover:underline"
                @click="$emit('update:modelValue', [...placeholderItems])"
            >Standaardteksten overnemen om aan te passen</button>
        </div>
    </div>
</template>

<script setup>
import { ref } from 'vue'
import { XMarkIcon } from '@heroicons/vue/24/outline'

const props = defineProps({
    modelValue: { type: Array, default: () => [] },
    label: { type: String, default: '' },
    placeholderItems: { type: Array, default: () => [] },
})
const emit = defineEmits(['update:modelValue'])

const draft = ref('')

function add() {
    const text = draft.value.trim()
    if (!text) return
    emit('update:modelValue', [...props.modelValue, text])
    draft.value = ''
}

function remove(index) {
    emit('update:modelValue', props.modelValue.filter((_, i) => i !== index))
}
</script>
