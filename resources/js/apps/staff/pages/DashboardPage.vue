<template>
  <div class="space-y-4">
    <section class="rounded-3xl border border-slate-800 bg-slate-900 p-5 shadow-xl">
      <div class="flex items-start justify-between gap-4">
        <div>
          <div class="text-sm text-slate-400">Status vandaag</div>
          <div class="mt-2 text-2xl font-semibold text-white">{{ attendance.is_checked_in ? 'Ingecheckt' : 'Niet ingecheckt' }}</div>
          <div class="mt-2 text-sm text-slate-400">Gewerkte tijd vandaag: <span class="text-slate-200">{{ attendance.worked_time_today_label || '00:00' }}</span></div>
        </div>
        <button :disabled="store.saving" class="rounded-2xl px-4 py-3 font-semibold" :class="attendance.is_checked_in ? 'bg-rose-500 text-white' : 'bg-emerald-500 text-slate-950'" @click="store.toggleAttendance()">
          {{ attendance.is_checked_in ? 'Uitchecken' : 'Inchecken' }}
        </button>
      </div>
      <div v-if="store.flash" class="mt-4 rounded-2xl border border-cyan-500/30 bg-cyan-500/10 px-4 py-3 text-sm text-cyan-200">{{ store.flash }}</div>
    </section>

    <section class="grid grid-cols-2 gap-3">
      <article v-for="card in cards" :key="card.label" class="rounded-3xl border border-slate-800 bg-slate-900 p-4">
        <div class="text-sm text-slate-400">{{ card.label }}</div>
        <div class="mt-2 text-2xl font-semibold text-white">{{ card.value }}</div>
      </article>
    </section>

    <section class="rounded-3xl border border-slate-800 bg-slate-900 p-5 shadow-xl">
      <div class="mb-3 flex items-center justify-between">
        <h2 class="text-lg font-semibold text-white">Mijn taken</h2>
        <RouterLink to="/tasks" class="text-sm text-cyan-400">Alles bekijken</RouterLink>
      </div>
      <div v-if="!store.data.tasks?.length" class="rounded-2xl border border-dashed border-slate-700 px-4 py-6 text-sm text-slate-400">Geen openstaande taken.</div>
      <div v-else class="space-y-3">
        <div v-for="task in store.data.tasks" :key="task.id" class="rounded-2xl border border-slate-800 bg-slate-950/60 px-4 py-3">
          <div class="font-semibold text-white">{{ task.title }}</div>
          <div class="mt-1 text-sm text-slate-400">{{ task.due_label || 'Geen datum' }} · {{ task.assigned_to_me ? 'Mijn taak' : 'Algemene taak' }}</div>
        </div>
      </div>
    </section>
  </div>
</template>

<script setup>
import { computed, onMounted } from 'vue'
import { RouterLink } from 'vue-router'
import { useStaffDashboardStore } from '../stores/dashboardStore'

const store = useStaffDashboardStore()
const attendance = computed(() => store.data.attendance || {})
const cards = computed(() => [
  { label: 'Reservaties vandaag', value: store.data.stats?.registrations_today ?? 0 },
  { label: 'Mijn open taken', value: store.data.stats?.my_open_tasks ?? 0 },
  { label: 'Taken vandaag', value: store.data.stats?.tasks_today ?? 0 },
  { label: 'Personeel ingecheckt', value: store.data.stats?.checked_in_staff_now ?? 0 },
])

onMounted(() => store.fetchDashboard())
</script>
