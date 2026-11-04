<template>
    <teleport to="body">
        <div v-if="open" class="fixed inset-0 z-50 flex items-center justify-center p-4">
            <div class="absolute inset-0 bg-slate-950/80" @click="$emit('close')" />
            <form class="relative z-10 w-full max-w-sm rounded-3xl border border-slate-800 bg-slate-900 p-6 shadow-2xl" @submit.prevent="submit">
                <div class="flex items-start gap-3">
                    <div class="flex h-11 w-11 shrink-0 items-center justify-center rounded-2xl border border-slate-700 bg-slate-950">
                        <LockClosedIcon class="h-5 w-5 text-amber-300" />
                    </div>
                    <div>
                        <h2 class="text-lg font-semibold text-white">{{ title }}</h2>
                        <p class="mt-1 text-sm text-slate-400">Geef de beheerpincode in.</p>
                    </div>
                </div>
                <input
                    ref="input"
                    v-model="pin"
                    type="password"
                    inputmode="numeric"
                    autocomplete="off"
                    maxlength="8"
                    class="mt-5 w-full rounded-2xl border border-slate-700 bg-slate-950 px-4 py-4 text-center font-mono text-2xl tracking-[0.5em] text-white outline-none focus:border-blue-500"
                    @input="pin = pin.replace(/\D/g, '')"
                >
                <div class="mt-5 flex gap-3">
                    <button type="submit" :disabled="pin.length < 4" class="flex-1 rounded-2xl bg-blue-600 px-4 py-3 text-sm font-semibold text-white transition hover:bg-blue-500 disabled:opacity-50">Bevestigen</button>
                    <button type="button" class="rounded-2xl border border-slate-700 px-4 py-3 text-sm font-medium text-slate-300 transition hover:bg-slate-800" @click="$emit('close')">Annuleren</button>
                </div>
            </form>
        </div>
    </teleport>
</template>

<script setup>
import { nextTick, ref, watch } from 'vue'
import { LockClosedIcon } from '@heroicons/vue/24/outline'

const props = defineProps({
    open: { type: Boolean, default: false },
    title: { type: String, default: 'Beheerpincode' },
})
const emit = defineEmits(['close', 'confirm'])

const pin = ref('')
const input = ref(null)

watch(() => props.open, async (open) => {
    if (!open) return
    pin.value = ''
    await nextTick()
    input.value?.focus()
})

function submit() {
    if (pin.value.length < 4) return
    emit('confirm', pin.value)
}
</script>
