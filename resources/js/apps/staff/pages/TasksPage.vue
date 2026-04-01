<template>
  <div class="space-y-4">
    <section class="grid grid-cols-3 gap-3">
      <article class="rounded-3xl border border-slate-800 bg-slate-900 p-4"><div class="text-sm text-slate-400">Open</div><div class="mt-2 text-2xl font-semibold text-white">{{ store.summary.open }}</div></article>
      <article class="rounded-3xl border border-slate-800 bg-slate-900 p-4"><div class="text-sm text-slate-400">Afgewerkt</div><div class="mt-2 text-2xl font-semibold text-white">{{ store.summary.completed }}</div></article>
      <article class="rounded-3xl border border-slate-800 bg-slate-900 p-4"><div class="text-sm text-slate-400">Totaal</div><div class="mt-2 text-2xl font-semibold text-white">{{ store.summary.total }}</div></article>
    </section>

    <section class="rounded-3xl border border-slate-800 bg-slate-900 p-4">
      <input v-model="store.search" class="w-full rounded-2xl border border-slate-700 bg-slate-950 px-4 py-3 text-white" placeholder="Zoek taak" @keyup.enter="store.fetchTasks()">
      <div class="mt-3 flex gap-2">
        <button v-for="item in filters" :key="item.value" class="rounded-2xl px-3 py-2 text-sm font-semibold" :class="store.statuses.includes(item.value) ? 'bg-cyan-500 text-slate-950' : 'bg-slate-800 text-slate-300'" @click="toggle(item.value)">{{ item.label }}</button>
      </div>
    </section>

    <div class="space-y-3">
      <article v-for="task in store.tasks" :key="task.id" class="rounded-3xl border border-slate-800 bg-slate-900 p-4">
        <div class="flex items-start justify-between gap-3">
          <div>
            <div class="text-lg font-semibold text-white">{{ task.title }}</div>
            <div class="mt-1 text-sm text-slate-400">{{ task.description || 'Geen omschrijving' }}</div>
            <div class="mt-2 text-sm text-slate-400">{{ task.due_date_label || task.start_date_label || 'Geen datum' }} · {{ task.assigned_user_name || 'Algemene taak' }}</div>
          </div>
          <span class="rounded-full px-3 py-1 text-xs font-semibold" :class="statusClass(task.status)">{{ task.status_label }}</span>
        </div>
        <div class="mt-4 flex gap-2">
          <button class="rounded-2xl bg-emerald-500 px-3 py-2 text-sm font-semibold text-slate-950" @click="store.updateStatus(task.id, 'completed')">Afwerken</button>
          <button class="rounded-2xl border border-slate-700 px-3 py-2 text-sm text-slate-300" @click="store.updateStatus(task.id, 'open')">Heropenen</button>
        </div>
      </article>
      <div v-if="!store.tasks.length" class="rounded-3xl border border-dashed border-slate-700 px-4 py-8 text-center text-sm text-slate-400">Geen taken gevonden.</div>
    </div>
  </div>
</template>

<script setup>
import { onMounted } from 'vue'
import { useStaffTasksStore } from '../stores/tasksStore'

const store = useStaffTasksStore()
const filters = [
  { value: 'open', label: 'Open' },
  { value: 'completed', label: 'Afgewerkt' },
  { value: 'cancelled', label: 'Geannuleerd' },
]

function toggle(status) {
  store.statuses = store.statuses.includes(status) ? store.statuses.filter(item => item !== status) : [...store.statuses, status]
  store.fetchTasks()
}
function statusClass(status) {
  if (status === 'completed') return 'bg-emerald-500/15 text-emerald-300'
  if (status === 'cancelled') return 'bg-rose-500/15 text-rose-300'
  return 'bg-amber-500/15 text-amber-300'
}

onMounted(() => store.fetchTasks())
</script>
