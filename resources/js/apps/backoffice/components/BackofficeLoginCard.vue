<template>
    <div class="flex min-h-screen items-center justify-center bg-slate-950 p-6 text-slate-100">
        <div class="w-full max-w-md rounded-3xl border border-slate-800 bg-slate-900 p-8 shadow-2xl">
            <div class="text-sm font-semibold uppercase tracking-[0.28em] text-cyan-400">{{ tenantName }}</div>
            <h1 class="mt-3 text-3xl font-bold text-white">Backoffice login</h1>
            <p class="mt-2 text-slate-400">Alleen medewerkers met adminrechten kunnen hier aanmelden.</p>

            <div v-if="error" class="mt-6 rounded-2xl border border-rose-500/30 bg-rose-500/10 px-4 py-3 text-sm text-rose-200">
                {{ error }}
            </div>

            <form class="mt-6 space-y-4" @submit.prevent="submit">
                <label class="block space-y-2">
                    <span class="text-sm text-slate-300">Login</span>
                    <input v-model="form.username" type="text" class="w-full rounded-2xl border border-slate-700 bg-slate-950 px-4 py-3 text-white outline-none focus:border-cyan-500" required>
                </label>
                <label class="block space-y-2">
                    <span class="text-sm text-slate-300">Paswoord</span>
                    <input v-model="form.password" type="password" class="w-full rounded-2xl border border-slate-700 bg-slate-950 px-4 py-3 text-white outline-none focus:border-cyan-500" required>
                </label>
                <button :disabled="submitting" type="submit" class="w-full rounded-2xl bg-cyan-600 px-4 py-3 text-sm font-semibold text-white transition hover:bg-cyan-500 disabled:opacity-60">
                    {{ submitting ? 'Bezig met aanmelden...' : 'Inloggen' }}
                </button>
            </form>
        </div>
    </div>
</template>

<script setup>
import { reactive, ref } from 'vue'
import { useBackofficeAuthStore } from '../stores/authStore'

const auth = useBackofficeAuthStore()
const tenantName = window.PlayDrive?.tenantName || 'PlayDrive'

const form = reactive({ username: '', password: '' })
const error = ref('')
const submitting = ref(false)

async function submit() {
    submitting.value = true
    error.value = ''

    try {
        await auth.login(form.username, form.password)
        window.location.reload()
    } catch (err) {
        error.value = err?.data?.errors?.username?.[0] ?? err?.data?.message ?? 'Aanmelden mislukt.'
    } finally {
        submitting.value = false
    }
}
</script>
