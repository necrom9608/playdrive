<template>
  <div class="space-y-4">
    <section class="rounded-3xl border border-slate-800 bg-slate-900 p-5">
      <h1 class="text-xl font-semibold text-white">Mijn gegevens</h1>
      <p class="mt-1 text-sm text-slate-400">Pas je eigen gegevens en paswoord aan.</p>

      <div v-if="store.message" class="mt-4 rounded-2xl border border-emerald-500/30 bg-emerald-500/10 px-4 py-3 text-sm text-emerald-200">{{ store.message }}</div>
      <div v-if="store.error" class="mt-4 rounded-2xl border border-rose-500/30 bg-rose-500/10 px-4 py-3 text-sm text-rose-200">{{ store.error }}</div>

      <form class="mt-5 space-y-4" @submit.prevent="submit">
        <input v-model="store.form.name" class="w-full rounded-2xl border border-slate-700 bg-slate-950 px-4 py-3 text-white" placeholder="Naam">
        <input v-model="store.form.username" disabled class="w-full rounded-2xl border border-slate-800 bg-slate-900 px-4 py-3 text-slate-500" placeholder="Login">
        <input v-model="store.form.email" class="w-full rounded-2xl border border-slate-700 bg-slate-950 px-4 py-3 text-white" placeholder="E-mail">
        <input v-model="store.form.street" class="w-full rounded-2xl border border-slate-700 bg-slate-950 px-4 py-3 text-white" placeholder="Straat">
        <div class="grid grid-cols-3 gap-3">
          <input v-model="store.form.house_number" class="rounded-2xl border border-slate-700 bg-slate-950 px-4 py-3 text-white" placeholder="Nr">
          <input v-model="store.form.bus" class="rounded-2xl border border-slate-700 bg-slate-950 px-4 py-3 text-white" placeholder="Bus">
          <input v-model="store.form.postal_code" class="rounded-2xl border border-slate-700 bg-slate-950 px-4 py-3 text-white" placeholder="Postcode">
        </div>
        <input v-model="store.form.city" class="w-full rounded-2xl border border-slate-700 bg-slate-950 px-4 py-3 text-white" placeholder="Gemeente">
        <div class="border-t border-slate-800 pt-4">
          <h2 class="text-sm font-semibold uppercase tracking-[0.18em] text-slate-400">Paswoord wijzigen</h2>
          <div class="mt-3 space-y-3">
            <input v-model="store.form.current_password" type="password" class="w-full rounded-2xl border border-slate-700 bg-slate-950 px-4 py-3 text-white" placeholder="Huidig paswoord">
            <input v-model="store.form.password" type="password" class="w-full rounded-2xl border border-slate-700 bg-slate-950 px-4 py-3 text-white" placeholder="Nieuw paswoord">
            <input v-model="store.form.password_confirmation" type="password" class="w-full rounded-2xl border border-slate-700 bg-slate-950 px-4 py-3 text-white" placeholder="Bevestig nieuw paswoord">
          </div>
        </div>
        <button :disabled="store.saving" class="w-full rounded-2xl bg-cyan-500 px-4 py-3 font-semibold text-slate-950">Opslaan</button>
      </form>
    </section>
  </div>
</template>

<script setup>
import { onMounted } from 'vue'
import { useStaffProfileStore } from '../stores/profileStore'

const store = useStaffProfileStore()
async function submit() { await store.saveProfile() }
onMounted(() => store.fetchProfile())
</script>
