<template>
  <div class="min-h-screen bg-slate-950 px-5 py-8 text-slate-100">
    <div class="mx-auto flex min-h-[calc(100vh-4rem)] max-w-md flex-col justify-center">
      <div class="mb-8 text-center">
        <div class="text-sm uppercase tracking-[0.3em] text-cyan-400">PlayDrive Staff</div>
        <h1 class="mt-3 text-3xl font-semibold text-white">Medewerkersportaal</h1>
        <p class="mt-2 text-sm text-slate-400">Inloggen op game-inn.playdrive.be/staff</p>
      </div>

      <form class="rounded-3xl border border-slate-800 bg-slate-900/90 p-6 shadow-2xl" @submit.prevent="submit">
        <label class="mb-4 block">
          <span class="mb-2 block text-sm text-slate-300">Login</span>
          <input v-model="username" class="w-full rounded-2xl border border-slate-700 bg-slate-950 px-4 py-3 text-white outline-none" autocomplete="username">
        </label>

        <label class="mb-4 block">
          <span class="mb-2 block text-sm text-slate-300">Paswoord</span>
          <input v-model="password" type="password" class="w-full rounded-2xl border border-slate-700 bg-slate-950 px-4 py-3 text-white outline-none" autocomplete="current-password">
        </label>

        <div v-if="error" class="mb-4 rounded-2xl border border-rose-500/30 bg-rose-500/10 px-4 py-3 text-sm text-rose-200">{{ error }}</div>
        <div v-if="message" class="mb-4 rounded-2xl border border-emerald-500/30 bg-emerald-500/10 px-4 py-3 text-sm text-emerald-200">{{ message }}</div>

        <button :disabled="loading" class="w-full rounded-2xl bg-cyan-500 px-4 py-3 font-semibold text-slate-950 disabled:opacity-60">Inloggen</button>

        <button type="button" class="mt-4 w-full rounded-2xl border border-slate-700 px-4 py-3 text-sm text-slate-300" @click="forgotPassword">Paswoord vergeten</button>
      </form>
    </div>
  </div>
</template>

<script setup>
import { ref } from 'vue'
import { useRouter } from 'vue-router'
import { useStaffAuthStore } from '../stores/authStore'

const auth = useStaffAuthStore()
const router = useRouter()
const username = ref('')
const password = ref('')
const loading = ref(false)
const error = ref('')
const message = ref('')

async function submit() {
  loading.value = true
  error.value = ''
  message.value = ''
  try {
    await auth.login(username.value, password.value)
    await router.replace('/')
  } catch (e) {
    error.value = e?.data?.errors?.username?.[0] || e?.data?.message || 'Inloggen mislukt.'
  } finally {
    loading.value = false
  }
}

async function forgotPassword() {
  error.value = ''
  message.value = ''
  if (!username.value) {
    error.value = 'Geef eerst je login in.'
    return
  }
  loading.value = true
  try {
    const response = await auth.forgotPassword(username.value)
    message.value = response.message || 'Er werd een tijdelijk paswoord verzonden.'
  } catch (e) {
    error.value = e?.data?.errors?.username?.[0] || e?.data?.message || 'Verzenden mislukt.'
  } finally {
    loading.value = false
  }
}
</script>
