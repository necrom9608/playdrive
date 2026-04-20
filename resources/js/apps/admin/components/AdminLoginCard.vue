<template>
    <div class="flex min-h-screen items-center justify-center bg-slate-950 p-6">
        <div class="w-full max-w-md rounded-3xl border border-slate-800 bg-slate-900 p-8 shadow-2xl">
            <div class="text-sm font-semibold uppercase tracking-[0.3em] text-cyan-400">PlayDrive</div>
            <h1 class="mt-3 text-3xl font-bold text-white">Admin login</h1>
            <p class="mt-2 text-slate-400">Enkel voor centraal beheer van PlayDrive.</p>

            <div
                v-if="error"
                class="mt-6 rounded-2xl border border-rose-500/30 bg-rose-500/10 px-4 py-3 text-sm text-rose-200"
            >
                {{ error }}
            </div>

            <div class="mt-6 space-y-4">
                <div>
                    <label class="mb-1 block text-sm text-slate-300">Gebruikersnaam</label>
                    <input
                        v-model="username"
                        type="text"
                        class="w-full rounded-2xl border border-slate-700 bg-slate-950 px-4 py-3 text-white outline-none transition focus:border-cyan-500"
                        @keydown.enter="submit"
                    />
                </div>
                <div>
                    <label class="mb-1 block text-sm text-slate-300">Paswoord</label>
                    <input
                        v-model="password"
                        type="password"
                        class="w-full rounded-2xl border border-slate-700 bg-slate-950 px-4 py-3 text-white outline-none transition focus:border-cyan-500"
                        @keydown.enter="submit"
                    />
                </div>
                <button
                    type="button"
                    :disabled="loading"
                    class="w-full rounded-2xl bg-cyan-600 px-4 py-3 text-sm font-semibold text-white transition hover:bg-cyan-500 disabled:opacity-60"
                    @click="submit"
                >
                    {{ loading ? 'Bezig...' : 'Inloggen' }}
                </button>
            </div>
        </div>
    </div>
</template>

<script setup>
import { ref } from 'vue'
import { useAdminAuthStore } from '../stores/authStore'

const auth = useAdminAuthStore()

const username = ref('')
const password = ref('')
const loading = ref(false)
const error = ref('')

async function submit() {
    if (!username.value || !password.value) return

    loading.value = true
    error.value = ''

    try {
        await auth.login(username.value, password.value)
    } catch (err) {
        error.value = err?.data?.message || 'Ongeldige logingegevens.'
    } finally {
        loading.value = false
    }
}
</script>
