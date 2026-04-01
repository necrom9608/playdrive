<template>
  <div class="min-h-screen bg-slate-950 text-slate-100">
    <header class="sticky top-0 z-30 border-b border-slate-800 bg-slate-950/95 px-4 pb-3 pt-4 backdrop-blur">
      <div class="mx-auto flex max-w-5xl items-center justify-between gap-3">
        <div>
          <div class="text-xs uppercase tracking-[0.25em] text-cyan-400">{{ tenantLabel }}</div>
          <div class="mt-1 text-lg font-semibold text-white">{{ auth.user?.name }}</div>
        </div>
        <button class="rounded-2xl border border-slate-700 px-3 py-2 text-sm text-slate-300" @click="auth.logout()">Uitloggen</button>
      </div>
    </header>

    <main class="mx-auto max-w-5xl px-4 pb-28 pt-4">
      <router-view />
    </main>

    <nav class="fixed inset-x-0 bottom-0 z-30 border-t border-slate-800 bg-slate-950/95 px-3 pb-[calc(env(safe-area-inset-bottom)+0.75rem)] pt-3 backdrop-blur">
      <div class="mx-auto grid max-w-5xl grid-cols-4 gap-2">
        <RouterLink v-for="item in items" :key="item.to" :to="item.to" class="rounded-2xl px-3 py-3 text-center text-xs font-semibold" :class="$route.path === item.to ? 'bg-cyan-500 text-slate-950' : 'bg-slate-900 text-slate-300'">{{ item.label }}</RouterLink>
      </div>
    </nav>
  </div>
</template>

<script setup>
import { computed } from 'vue'
import { RouterLink, useRoute } from 'vue-router'
import { useStaffAuthStore } from '../stores/authStore'

useRoute()
const auth = useStaffAuthStore()
const items = [
  { to: '/', label: 'Dashboard' },
  { to: '/agenda', label: 'Agenda' },
  { to: '/tasks', label: 'Taken' },
  { to: '/settings', label: 'Settings' },
]
const tenantLabel = computed(() => window.PlayDrive?.tenantName || 'PlayDrive')
</script>
