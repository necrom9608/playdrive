<template>
    <div
        v-if="open"
        class="fixed inset-0 z-[100] flex items-center justify-center bg-black/70 p-6 backdrop-blur-sm"
        @click.self="$emit('close')"
    >
        <div class="w-full max-w-2xl rounded-3xl border border-slate-800 bg-slate-900 shadow-2xl">
            <div class="flex items-center justify-between border-b border-slate-800 px-6 py-4">
                <div>
                    <h3 class="text-xl font-semibold text-white">Lid in / uitchecken</h3>
                    <p class="mt-1 text-sm text-slate-400">Zoek op naam, login, e-mail of RFID</p>
                </div>
                <button type="button" class="rounded-xl border border-slate-700 bg-slate-800 px-3 py-2 text-sm text-slate-200" @click="$emit('close')">
                    Sluiten
                </button>
            </div>

            <form class="space-y-5 p-6" @submit.prevent="$emit('submit')">
                <label class="block space-y-2 text-sm text-slate-300">
                    <span>Lid zoeken</span>
                    <input
                        :value="query"
                        type="text"
                        class="w-full rounded-2xl border border-slate-700 bg-slate-950 px-4 py-3 text-white outline-none focus:border-sky-500"
                        placeholder="Bijv. naam, login of RFID"
                        @input="$emit('update:query', $event.target.value)"
                    >
                </label>

                <div v-if="error" class="rounded-2xl border border-rose-500/30 bg-rose-500/10 px-4 py-3 text-sm text-rose-200">
                    {{ error }}
                </div>

                <div class="flex justify-end gap-3">
                    <button type="button" class="rounded-2xl border border-slate-700 bg-slate-800 px-4 py-3 text-sm font-semibold text-slate-200" @click="$emit('close')">
                        Annuleren
                    </button>
                    <button type="submit" class="rounded-2xl bg-sky-600 px-5 py-3 text-sm font-semibold text-white hover:bg-sky-500" :disabled="processing">
                        {{ processing ? 'Bezig...' : 'Bevestigen' }}
                    </button>
                </div>
            </form>
        </div>
    </div>
</template>

<script setup>
defineEmits(['close', 'submit', 'update:query'])

defineProps({
    open: {
        type: Boolean,
        required: true,
    },
    query: {
        type: String,
        required: true,
    },
    processing: {
        type: Boolean,
        required: true,
    },
    error: {
        type: String,
        default: '',
    },
})
</script>
