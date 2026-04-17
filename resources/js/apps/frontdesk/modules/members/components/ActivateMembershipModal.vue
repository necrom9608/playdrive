<template>
    <Teleport to="body">
        <Transition name="modal-fade">
            <div
                v-if="open"
                class="fixed inset-0 z-[180] flex items-center justify-center p-4"
                style="background: rgba(3,8,20,0.75); backdrop-filter: blur(6px);"
                @click.self="$emit('close')"
            >
                <div class="w-full max-w-md overflow-hidden rounded-3xl border border-white/10 shadow-2xl"
                    style="background: linear-gradient(160deg, rgba(15,25,50,0.95) 0%, rgba(8,14,30,0.98) 100%);">

                    <!-- Header -->
                    <div class="flex items-start justify-between gap-4 border-b border-white/10 px-6 py-5">
                        <div>
                            <h3 class="text-lg font-semibold text-white">Abonnement activeren</h3>
                            <p class="mt-0.5 text-sm text-slate-400">{{ registration?.full_name }}</p>
                        </div>
                        <button type="button"
                            class="flex items-center justify-center rounded-xl border border-white/10 bg-white/6 p-2 text-slate-400 transition hover:text-white"
                            @click="$emit('close')"
                        >
                            <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                            </svg>
                        </button>
                    </div>

                    <!-- Body -->
                    <div class="space-y-4 px-6 py-5">
                        <!-- Klantinfo -->
                        <div class="rounded-2xl border border-white/8 bg-white/4 px-4 py-3">
                            <p class="text-xs text-slate-400">E-mailadres</p>
                            <p class="mt-0.5 text-sm font-medium text-white">{{ registration?.email }}</p>
                        </div>

                        <!-- Type -->
                        <label class="block">
                            <span class="mb-2 block text-sm text-slate-300">Type abonnement</span>
                            <select
                                v-model="form.membership_type"
                                class="w-full rounded-2xl border border-white/10 bg-slate-900/60 px-4 py-3 text-sm text-slate-100 outline-none transition focus:border-cyan-500"
                            >
                                <option value="adult">Volwassene</option>
                                <option value="student">Student</option>
                            </select>
                        </label>

                        <!-- Startdatum -->
                        <label class="block">
                            <span class="mb-2 block text-sm text-slate-300">Startdatum</span>
                            <input
                                v-model="form.membership_starts_at"
                                type="date"
                                class="w-full rounded-2xl border border-white/10 bg-slate-900/60 px-4 py-3 text-sm text-slate-100 outline-none transition focus:border-cyan-500"
                            />
                        </label>

                        <!-- Einddatum -->
                        <label class="block">
                            <span class="mb-2 block text-sm text-slate-300">Einddatum</span>
                            <input
                                v-model="form.membership_ends_at"
                                type="date"
                                class="w-full rounded-2xl border border-white/10 bg-slate-900/60 px-4 py-3 text-sm text-slate-100 outline-none transition focus:border-cyan-500"
                            />
                        </label>

                        <!-- Fout -->
                        <div v-if="error"
                            class="rounded-2xl border border-rose-500/25 bg-rose-500/10 px-4 py-3 text-sm text-rose-200">
                            {{ error }}
                        </div>
                    </div>

                    <!-- Footer -->
                    <div class="flex gap-3 border-t border-white/10 px-6 py-4">
                        <button type="button"
                            class="flex-1 rounded-2xl border border-white/12 bg-white/6 px-4 py-3 text-sm font-semibold text-slate-200 transition hover:bg-white/10"
                            @click="$emit('close')"
                        >
                            Annuleren
                        </button>
                        <button type="button"
                            :disabled="loading"
                            class="flex-1 rounded-2xl px-4 py-3 text-sm font-semibold text-white transition disabled:cursor-not-allowed disabled:opacity-50"
                            style="background: linear-gradient(180deg, #06b6d4 0%, #0891b2 100%);"
                            @click="submit"
                        >
                            <span v-if="loading" class="flex items-center justify-center gap-2">
                                <span class="h-3.5 w-3.5 animate-spin rounded-full border border-white/30 border-t-white"></span>
                                Activeren…
                            </span>
                            <span v-else>Activeren</span>
                        </button>
                    </div>
                </div>
            </div>
        </Transition>
    </Teleport>
</template>

<script setup>
import { reactive, ref, watch } from 'vue'
import axios from '@/lib/http'

const props = defineProps({
    open: { type: Boolean, default: false },
    registration: { type: Object, default: null },
})

const emit = defineEmits(['close', 'activated'])

const loading = ref(false)
const error = ref('')

const today = new Date().toISOString().slice(0, 10)
const nextYear = new Date(new Date().setFullYear(new Date().getFullYear() + 1)).toISOString().slice(0, 10)

const form = reactive({
    membership_type: 'adult',
    membership_starts_at: today,
    membership_ends_at: nextYear,
})

watch(() => props.open, (val) => {
    if (val) {
        form.membership_type = 'adult'
        form.membership_starts_at = today
        form.membership_ends_at = nextYear
        error.value = ''
    }
})

async function submit() {
    if (!props.registration?.membership_id) return
    loading.value = true
    error.value = ''

    try {
        await axios.post('/api/frontdesk/new-registrations/activate', {
            membership_id: props.registration.membership_id,
            ...form,
        })
        emit('activated', props.registration)
        emit('close')
    } catch (err) {
        error.value = err?.response?.data?.message ?? 'Activeren mislukt.'
    } finally {
        loading.value = false
    }
}
</script>

<style scoped>
.modal-fade-enter-active, .modal-fade-leave-active { transition: opacity 0.2s ease; }
.modal-fade-enter-from, .modal-fade-leave-to { opacity: 0; }
</style>
