<template>
    <div>
        <form class="glass-card rounded-3xl p-6 space-y-4" @submit.prevent="submit">
            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label class="block text-xs text-slate-400 mb-1.5">Voornaam</label>
                    <input v-model="form.first_name" class="member-input" placeholder="Jan" autocomplete="given-name" />
                </div>
                <div>
                    <label class="block text-xs text-slate-400 mb-1.5">Familienaam</label>
                    <input v-model="form.last_name" class="member-input" placeholder="Declercq" autocomplete="family-name" />
                </div>
            </div>

            <div>
                <label class="block text-xs text-slate-400 mb-1.5">E-mailadres</label>
                <input v-model="form.email" type="email" class="member-input" placeholder="jan@example.com" autocomplete="email" />
            </div>

            <div>
                <label class="block text-xs text-slate-400 mb-1.5">Wachtwoord</label>
                <input v-model="form.password" type="password" class="member-input" placeholder="Minimaal 8 tekens" autocomplete="new-password" />
            </div>

            <div>
                <label class="block text-xs text-slate-400 mb-1.5">Herhaal wachtwoord</label>
                <input v-model="form.password_confirmation" type="password" class="member-input" placeholder="••••••••" autocomplete="new-password" />
            </div>

            <div v-if="errors.length" class="rounded-2xl bg-rose-500/10 border border-rose-500/30 px-4 py-3 space-y-1">
                <p v-for="e in errors" :key="e" class="text-sm text-rose-300">{{ e }}</p>
            </div>

            <button type="submit" :disabled="loading" class="member-btn-primary w-full">
                <span v-if="!loading">Account aanmaken</span>
                <span v-else class="flex items-center justify-center gap-2">
                    <span class="loader-dot" /><span class="loader-dot" /><span class="loader-dot" />
                </span>
            </button>
        </form>

        <button class="member-btn-ghost w-full text-sm mt-4" @click="router.push('/login')">
            Al een account? <span class="text-blue-400">Inloggen</span>
        </button>
    </div>
</template>

<script setup>
import { ref, reactive } from 'vue'
import { useRouter } from 'vue-router'
import { useAuthStore } from '../../stores/useAuthStore'
import { useVenueStore } from '../../stores/useVenueStore'

const router = useRouter()
const auth = useAuthStore()
const venue = useVenueStore()

const form = reactive({ first_name: '', last_name: '', email: '', password: '', password_confirmation: '' })
const loading = ref(false)
const errors = ref([])

async function submit() {
    errors.value = []
    loading.value = true
    try {
        await auth.register(form)
        await venue.loadVenues()
        router.replace('/mijn')
    } catch (e) {
        if (e?.data?.errors) {
            errors.value = Object.values(e.data.errors).flat()
        } else {
            errors.value = [e?.message ?? 'Registratie mislukt.']
        }
    } finally {
        loading.value = false
    }
}
</script>
