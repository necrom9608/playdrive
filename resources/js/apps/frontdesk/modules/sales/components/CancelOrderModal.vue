<template>
    <div
        v-if="open"
        class="fixed inset-0 z-50 flex items-center justify-center bg-slate-950/80 p-4"
        @click.self="$emit('close')"
    >
        <div class="w-full max-w-lg rounded-3xl border border-slate-800 bg-slate-900 p-6 shadow-2xl">
            <h3 class="text-xl font-semibold text-white">Order annuleren</h3>
            <p class="mt-2 text-sm text-slate-400">
                Order #{{ order?.id }} wordt geannuleerd. Deze verkoop telt dan niet meer mee.
            </p>

            <div class="mt-5">
                <label class="mb-2 block text-xs font-medium uppercase tracking-wide text-slate-400">
                    Reden
                </label>

                <textarea
                    v-model="reason"
                    rows="4"
                    class="w-full rounded-2xl border border-slate-700 bg-slate-800 px-4 py-3 text-sm text-slate-100 outline-none transition focus:border-blue-500 focus:ring-2 focus:ring-blue-900"
                    placeholder="Optionele reden voor annulatie"
                />
            </div>

            <div class="mt-6 grid grid-cols-2 gap-3">
                <button
                    type="button"
                    class="rounded-2xl border border-slate-700 bg-slate-800 px-4 py-3 text-sm font-semibold text-slate-200 transition hover:bg-slate-700"
                    @click="$emit('close')"
                >
                    Sluiten
                </button>

                <button
                    type="button"
                    class="rounded-2xl bg-amber-600 px-4 py-3 text-sm font-semibold text-white transition hover:bg-amber-500"
                    @click="submit"
                >
                    Bevestig annulatie
                </button>
            </div>
        </div>
    </div>
</template>

<script setup>
import { ref, watch } from 'vue'
import { useSalesStore } from '../stores/useSalesStore.js'

const props = defineProps({
    open: Boolean,
    order: {
        type: Object,
        default: null,
    },
})

const emit = defineEmits(['close'])
const store = useSalesStore()
const reason = ref('')

watch(
    () => props.open,
    (value) => {
        if (value) {
            reason.value = ''
        }
    }
)

async function submit() {
    const ok = await store.cancelSelectedOrder(reason.value)

    if (ok) {
        emit('close')
    }
}
</script>
