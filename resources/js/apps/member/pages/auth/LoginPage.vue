<template>
    <div>
        <form class="glass-card rounded-3xl p-6 space-y-4" @submit.prevent="submit">
            <div>
                <label class="block text-xs text-slate-400 mb-1.5">E-mailadres</label>
                <input
                    v-model="email"
                    type="email"
                    autocomplete="email"
                    class="member-input"
                    placeholder="jan@example.com"
                />
            </div>

            <div>
                <label class="block text-xs text-slate-400 mb-1.5">Wachtwoord</label>
                <input
                    v-model="password"
                    type="password"
                    autocomplete="current-password"
                    class="member-input"
                    placeholder="••••••••"
                />
            </div>

            <div v-if="error" class="rounded-2xl bg-rose-500/10 border border-rose-500/30 px-4 py-3 text-sm text-rose-300">
                {{ error }}
            </div>

            <button type="submit" :disabled="loading" class="member-btn-primary w-full">
                <span v-if="!loading">Inloggen</span>
                <span v-else class="flex items-center justify-center gap-2">
                    <span class="loader-dot" /><span class="loader-dot" /><span class="loader-dot" />
                </span>
            </button>
        </form>

        <div class="mt-4 flex flex-col gap-2">
            <button class="member-btn-ghost w-full text-sm" @click="router.push('/forgot-password')">
                Wachtwoord vergeten?
            </button>
            <button class="member-btn-ghost w-full text-sm" @click="router.push('/register')">
                Nog geen account? <span class="text-blue-400">Registreren</span>
            </button>
        </div>
    </div>
</template>

<script setup>
import { ref } from 'vue'
import { useRouter } from 'vue-router'
import { useAuthStore } from '../../stores/useAuthStore'
import { useVenueStore } from '../../stores/useVenueStore'

const router = useRouter()
const auth = useAuthStore()
const venue = useVenueStore()
const email = ref('')
const password = ref('')
const loading = ref(false)
const error = ref('')

async function submit() {
    error.value = ''
    loading.value = true
    try {
        await auth.login(email.value, password.value)
        await venue.loadVenues()
        router.replace('/mijn')
    } catch (e) {
        error.value = e?.data?.errors?.email?.[0] ?? e?.message ?? 'Inloggen mislukt.'
    } finally {
        loading.value = false
    }
}
</script>
