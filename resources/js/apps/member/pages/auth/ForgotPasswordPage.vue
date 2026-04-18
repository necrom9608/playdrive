<template>
    <div>
        <div v-if="!sent">
            <form class="glass-card rounded-3xl p-6 space-y-4" @submit.prevent="submit">
                <p class="text-sm text-slate-400">Geef je e-mailadres in. Als het gekend is sturen we een resetlink.</p>

                <div>
                    <label class="block text-xs text-slate-400 mb-1.5">E-mailadres</label>
                    <input v-model="email" type="email" class="member-input" placeholder="jan@example.com" autocomplete="email" />
                </div>

                <div v-if="error" class="rounded-2xl bg-rose-500/10 border border-rose-500/30 px-4 py-3 text-sm text-rose-300">
                    {{ error }}
                </div>

                <button type="submit" :disabled="loading" class="member-btn-primary w-full">
                    <span v-if="!loading">Resetlink versturen</span>
                    <span v-else class="flex items-center justify-center gap-2">
                        <span class="loader-dot" /><span class="loader-dot" /><span class="loader-dot" />
                    </span>
                </button>
            </form>
        </div>

        <div v-else class="glass-card rounded-3xl p-6 text-center space-y-3">
            <div class="w-12 h-12 rounded-full bg-emerald-500/15 border border-emerald-500/30 flex items-center justify-center mx-auto">
                <CheckIcon class="w-6 h-6 text-emerald-400" />
            </div>
            <p class="text-sm text-slate-300">Als dit adres gekend is, ontvang je zo meteen een e-mail.</p>
        </div>

        <button class="member-btn-ghost w-full text-sm mt-4" @click="router.push('/login')">
            Terug naar inloggen
        </button>
    </div>
</template>

<script setup>
import { ref } from 'vue'
import { useRouter } from 'vue-router'
import { useAuthStore } from '../../stores/useAuthStore'
import { CheckIcon } from '@heroicons/vue/24/outline'

const router = useRouter()
const auth = useAuthStore()
const email = ref('')
const loading = ref(false)
const error = ref('')
const sent = ref(false)

async function submit() {
    error.value = ''
    loading.value = true
    try {
        await auth.forgotPassword(email.value)
        sent.value = true
    } catch (e) {
        error.value = e?.message ?? 'Er is een fout opgetreden.'
    } finally {
        loading.value = false
    }
}
</script>
