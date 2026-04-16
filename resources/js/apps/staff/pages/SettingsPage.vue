<template>
  <div class="space-y-4">
    <section v-if="!pwa.isInstalled" class="rounded-[30px] border border-cyan-400/20 bg-cyan-500/5 p-5 shadow-[0_24px_70px_rgba(2,6,23,0.35)] backdrop-blur-xl">
      <div class="flex items-start justify-between gap-3">
        <div>
          <h2 class="text-xl font-semibold text-white">App installeren</h2>
          <p class="mt-1 text-sm text-slate-400">Installeer PlayDrive Staff als app op je toestel voor snellere toegang.</p>
        </div>
        <ArrowDownTrayIcon class="h-6 w-6 shrink-0 text-cyan-400" />
      </div>
      <button
        v-if="pwa.installPrompt"
        class="mt-4 inline-flex w-full items-center justify-center gap-2 rounded-2xl bg-cyan-400 px-4 py-3 font-semibold text-slate-950 shadow-[0_16px_30px_rgba(34,211,238,0.22)]"
        @click="installApp"
      >
        <ArrowDownTrayIcon class="h-5 w-5" />
        Installeer app
      </button>
      <p v-else class="mt-4 rounded-2xl border border-white/10 bg-white/5 px-4 py-3 text-sm text-slate-400">
        Open deze pagina in Chrome om de app te installeren.
      </p>
    </section>

    <section class="rounded-[30px] border border-white/10 bg-slate-950/60 p-5 shadow-[0_24px_70px_rgba(2,6,23,0.35)] backdrop-blur-xl">
      <div class="flex items-start justify-between gap-3">
        <div>
          <h1 class="text-xl font-semibold text-white">Mijn gegevens</h1>
          <p class="mt-1 text-sm text-slate-400">Pas je profiel, adres en paswoord aan.</p>
        </div>
        <button class="inline-flex items-center gap-2 rounded-2xl border border-rose-400/20 bg-rose-500/10 px-4 py-3 text-sm font-semibold text-rose-100 transition hover:bg-rose-500/15" @click="auth.logout()">
          <ArrowLeftOnRectangleIcon class="h-5 w-5" />
          Uitloggen
        </button>
      </div>

      <div v-if="store.message" class="mt-4 rounded-2xl border border-emerald-500/30 bg-emerald-500/10 px-4 py-3 text-sm text-emerald-200">{{ store.message }}</div>
      <div v-if="store.error" class="mt-4 rounded-2xl border border-rose-500/30 bg-rose-500/10 px-4 py-3 text-sm text-rose-200">{{ store.error }}</div>

      <form class="mt-5 space-y-5" @submit.prevent="submit">
        <section class="rounded-3xl border border-white/10 bg-white/5 p-4">
          <div class="flex items-center gap-2 text-sm font-semibold uppercase tracking-[0.18em] text-slate-300">
            <UserCircleIcon class="h-5 w-5" />
            Basisgegevens
          </div>
          <div class="mt-4 grid gap-3 md:grid-cols-2">
            <input v-model="store.form.name" class="w-full rounded-2xl border border-white/10 bg-slate-950/80 px-4 py-3 text-white" placeholder="Naam">
            <input v-model="store.form.username" disabled class="w-full rounded-2xl border border-white/10 bg-slate-900 px-4 py-3 text-slate-500" placeholder="Login">
            <input v-model="store.form.email" class="w-full rounded-2xl border border-white/10 bg-slate-950/80 px-4 py-3 text-white md:col-span-2" placeholder="E-mail">
          </div>
        </section>

        <section class="rounded-3xl border border-white/10 bg-white/5 p-4">
          <div class="flex items-center gap-2 text-sm font-semibold uppercase tracking-[0.18em] text-slate-300">
            <MapPinIcon class="h-5 w-5" />
            Adres
          </div>
          <div class="mt-4 space-y-3">
            <input v-model="store.form.street" class="w-full rounded-2xl border border-white/10 bg-slate-950/80 px-4 py-3 text-white" placeholder="Straat">
            <div class="grid grid-cols-3 gap-3">
              <input v-model="store.form.house_number" class="rounded-2xl border border-white/10 bg-slate-950/80 px-4 py-3 text-white" placeholder="Nr">
              <input v-model="store.form.bus" class="rounded-2xl border border-white/10 bg-slate-950/80 px-4 py-3 text-white" placeholder="Bus">
              <input v-model="store.form.postal_code" class="rounded-2xl border border-white/10 bg-slate-950/80 px-4 py-3 text-white" placeholder="Postcode">
            </div>
            <input v-model="store.form.city" class="w-full rounded-2xl border border-white/10 bg-slate-950/80 px-4 py-3 text-white" placeholder="Gemeente">
          </div>
        </section>

        <section class="rounded-3xl border border-white/10 bg-white/5 p-4">
          <div class="flex items-center gap-2 text-sm font-semibold uppercase tracking-[0.18em] text-slate-300">
            <KeyIcon class="h-5 w-5" />
            Paswoord wijzigen
          </div>
          <div class="mt-4 space-y-3">
            <input v-model="store.form.current_password" type="password" class="w-full rounded-2xl border border-white/10 bg-slate-950/80 px-4 py-3 text-white" placeholder="Huidig paswoord">
            <input v-model="store.form.password" type="password" class="w-full rounded-2xl border border-white/10 bg-slate-950/80 px-4 py-3 text-white" placeholder="Nieuw paswoord">
            <input v-model="store.form.password_confirmation" type="password" class="w-full rounded-2xl border border-white/10 bg-slate-950/80 px-4 py-3 text-white" placeholder="Bevestig nieuw paswoord">
          </div>
        </section>

        <button :disabled="store.saving" class="inline-flex w-full items-center justify-center gap-2 rounded-2xl bg-cyan-400 px-4 py-3 font-semibold text-slate-950 shadow-[0_16px_30px_rgba(34,211,238,0.22)] disabled:cursor-not-allowed disabled:opacity-70">
          <CheckCircleIcon class="h-5 w-5" />
          Opslaan
        </button>
      </form>
    </section>
  </div>
</template>

<script setup>
import { onMounted } from 'vue'
import {
  ArrowLeftOnRectangleIcon,
  ArrowDownTrayIcon,
  UserCircleIcon,
  MapPinIcon,
  KeyIcon,
  CheckCircleIcon,
} from '@heroicons/vue/24/outline'
import { useStaffProfileStore } from '../stores/profileStore'
import { useStaffAuthStore } from '../stores/authStore'
import { usePwaStore } from '../stores/pwaStore'

const store = useStaffProfileStore()
const auth = useStaffAuthStore()
const pwa = usePwaStore()

async function installApp() { await pwa.triggerInstall() }
async function submit() { await store.saveProfile() }
onMounted(() => store.fetchProfile())
</script>
