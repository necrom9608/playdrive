<template>
    <div>
        <div v-if="!token || !email" class="glass-card rounded-3xl p-6 text-center space-y-3">
            <p class="text-sm text-slate-300">Deze resetlink is onvolledig. Vraag een nieuwe link aan.</p>
            <button class="member-btn-primary w-full" @click="router.push('/forgot-password')">
                Nieuwe resetlink aanvragen
            </button>
        </div>

        <form v-else class="glass-card rounded-3xl p-6 space-y-4" @submit.prevent="submit">
            <p class="text-sm text-slate-400">Kies een nieuw wachtwoord voor je account.</p>

            <div>
                <label class="block text-xs text-slate-400 mb-1.5">E-mailadres</label>
                <input :value="email" type="email" class="member-input opacity-70" disabled />
            </div>

            <div>
                <label class="block text-xs text-slate-400 mb-1.5">Nieuw wachtwoord</label>
                <input
                    v-model="password"
                    type="password"
                    autocomplete="new-password"
                    class="member-input"
                    placeholder="Minstens 8 tekens"
                />
            </div>

            <div>
                <label class="block text-xs text-slate-400 mb-1.5">Herhaal wachtwoord</label>
                <input
                    v-model="passwordConfirmation"
                    type="password"
                    autocomplete="new-password"
                    class="member-input"
                    placeholder="••••••••"
                />
            </div>

            <div v-if="error" class="rounded-2xl bg-rose-500/10 border border-rose-500/30 px-4 py-3 text-sm text-rose-300">
                {{ error }}
            </div>

            <button type="submit" :disabled="loading" class="member-btn-primary w-full">
                <span v-if="!loading">Wachtwoord opslaan</span>
                <span v-else class="flex items-center justify-center gap-2">
                    <span class="loader-dot" /><span class="loader-dot" /><span class="loader-dot" />
                </span>
            </button>
        </form>

        <button class="member-btn-ghost w-full text-sm mt-4" @click="router.push('/login')">
            Terug naar inloggen
        </button>
    </div>
</template>

<script setup>
import { ref, computed } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { useAuthStore } from '../../stores/useAuthStore'
import { useVenueStore } from '../../stores/useVenueStore'

const route = useRoute()
const router = useRouter()
const auth = useAuthStore()
const venue = useVenueStore()

const token = computed(() => String(route.query.token ?? ''))
const email = computed(() => String(route.query.email ?? '').trim().toLowerCase())

const password = ref('')
const passwordConfirmation = ref('')
const loading = ref(false)
const error = ref('')

async function submit() {
    error.value = ''

    if (password.value.length < 8) {
        error.value = 'Je wachtwoord moet minstens 8 tekens lang zijn.'
        return
    }
    if (password.value !== passwordConfirmation.value) {
        error.value = 'De wachtwoorden komen niet overeen.'
        return
    }

    loading.value = true
    try {
        await auth.resetPassword({
            token: token.value,
            email: email.value,
            password: password.value,
            password_confirmation: passwordConfirmation.value,
        })

        // Meteen inloggen met het nieuwe wachtwoord
        try {
            await auth.login(email.value, password.value)
            await venue.loadVenues()
            router.replace('/mijn')
        } catch {
            router.replace('/login')
        }
    } catch (e) {
        const errors = e?.data?.errors ?? {}
        error.value = errors.password?.[0]
            ?? (errors.email?.[0] ? 'Deze resetlink is ongeldig of verlopen. Vraag een nieuwe link aan.' : null)
            ?? e?.message
            ?? 'Wachtwoord wijzigen mislukt.'
    } finally {
        loading.value = false
    }
}
</script>
