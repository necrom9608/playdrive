<template>
  <div class="space-y-4">
    <section class="rounded-3xl border border-slate-800 bg-slate-900 p-4">
      <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
        <div>
          <div class="text-sm text-slate-400">Periode</div>
          <div class="mt-1 text-lg font-semibold text-white">{{ store.data.range?.label }}</div>
        </div>
        <div class="flex gap-2">
          <button v-for="view in ['day','week','month']" :key="view" class="rounded-2xl px-3 py-2 text-sm font-semibold" :class="store.view === view ? 'bg-cyan-500 text-slate-950' : 'bg-slate-800 text-slate-300'" @click="changeView(view)">{{ labels[view] }}</button>
        </div>
      </div>
      <input v-model="store.date" type="date" class="mt-3 w-full rounded-2xl border border-slate-700 bg-slate-950 px-4 py-3 text-white" @change="store.fetchAgenda()">
    </section>

    <section v-if="store.view === 'day'" class="space-y-3">
      <article v-for="item in store.data.day_registrations" :key="item.id" class="rounded-3xl border border-slate-800 bg-slate-900 p-4">
        <div class="flex items-start justify-between gap-3">
          <div>
            <div class="text-xs uppercase tracking-[0.2em] text-slate-500">{{ item.item_type === 'task' ? 'Taak' : 'Reservatie' }}</div>
            <div class="mt-1 text-lg font-semibold text-white">{{ item.name }}</div>
            <div class="mt-2 text-sm text-slate-400">{{ item.event_time || item.duration_label || '—' }}</div>
          </div>
          <span class="rounded-full px-3 py-1 text-xs font-semibold" :class="badgeClass(item)">{{ item.status_label }}</span>
        </div>
      </article>
      <div v-if="!store.data.day_registrations?.length" class="rounded-3xl border border-dashed border-slate-700 px-4 py-8 text-center text-sm text-slate-400">Geen items voor deze dag.</div>
    </section>

    <section v-else class="grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-4">
      <button v-for="day in store.data.days" :key="day.date" class="rounded-3xl border p-4 text-left" :class="day.is_selected ? 'border-cyan-500 bg-cyan-500/10' : 'border-slate-800 bg-slate-900'" @click="selectDay(day.date)">
        <div class="text-sm text-slate-400">{{ day.weekday_label }}</div>
        <div class="mt-1 text-2xl font-semibold text-white">{{ day.day_number }}</div>
        <div class="mt-3 text-sm text-slate-300">{{ day.totals?.reservations ?? 0 }} reservaties</div>
        <div class="text-sm text-slate-400">{{ day.totals?.tasks ?? 0 }} taken</div>
      </button>
    </section>
  </div>
</template>

<script setup>
import { onMounted } from 'vue'
import { useStaffAgendaStore } from '../stores/agendaStore'

const store = useStaffAgendaStore()
const labels = { day: 'Dag', week: 'Week', month: 'Maand' }

function changeView(view) { store.view = view; store.fetchAgenda() }
function selectDay(date) { store.date = date; store.view = 'day'; store.fetchAgenda() }
function badgeClass(item) {
  if (item.status === 'completed' || item.status === 'paid') return 'bg-emerald-500/15 text-emerald-300'
  if (item.status === 'checked_in') return 'bg-blue-500/15 text-blue-300'
  if (item.status === 'cancelled' || item.status === 'no_show') return 'bg-rose-500/15 text-rose-300'
  return 'bg-amber-500/15 text-amber-300'
}

onMounted(() => store.fetchAgenda())
</script>
