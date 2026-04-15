<template>
  <section class="grid grid-cols-2 gap-3 md:grid-cols-3 xl:grid-cols-4">
    <button
      v-for="day in store.data.days"
      :key="day.date"
      type="button"
      class="rounded-[28px] border p-4 text-left shadow-[0_20px_60px_-28px_rgba(15,23,42,0.9)] transition"
      :class="day.is_selected ? 'border-cyan-400/50 bg-cyan-400/10' : day.is_current_month ? 'border-slate-800/80 bg-slate-900/90 hover:border-slate-700' : 'border-slate-800/40 bg-slate-900/40 text-slate-500'"
      @click="selectDay(day.date)"
    >
      <div class="flex items-start justify-between gap-2">
        <div>
          <div class="text-xs uppercase tracking-[0.2em]" :class="day.is_current_month ? 'text-slate-500' : 'text-slate-600'">{{ day.weekday_short }}</div>
          <div class="mt-1 text-2xl font-semibold" :class="day.is_current_month ? 'text-white' : 'text-slate-500'">{{ day.day_number }}</div>
        </div>
        <span v-if="day.is_today" class="rounded-full bg-cyan-400/10 px-2.5 py-1 text-[11px] font-semibold text-cyan-200 ring-1 ring-cyan-400/20">Vandaag</span>
      </div>

      <div class="mt-4 flex flex-wrap gap-2">
        <span v-if="day.totals?.reservations" class="rounded-full bg-slate-950/80 px-3 py-1 text-xs font-semibold text-slate-200 ring-1 ring-slate-800">
          {{ day.totals.reservations }} reservaties
        </span>
        <span v-if="day.totals?.tasks" class="rounded-full bg-fuchsia-500/10 px-3 py-1 text-xs font-semibold text-fuchsia-200 ring-1 ring-fuchsia-500/20">
          {{ day.totals.tasks }} taken
        </span>
      </div>
    </button>

    <div v-if="!store.data.days?.length && !store.loading" class="rounded-[28px] border border-dashed border-slate-700 bg-slate-900/40 px-4 py-10 text-center text-sm text-slate-400 md:col-span-3 xl:col-span-4">
      Geen items voor deze maand.
    </div>
  </section>
</template>

<script setup>
import { onMounted } from 'vue'
import { useRouter } from 'vue-router'
import { useStaffAgendaStore } from '../../stores/agendaStore'

const store = useStaffAgendaStore()
const router = useRouter()

function selectDay(date) {
  store.date = date
  router.push('/agenda/day')
}

onMounted(() => {
  if (!store.data.days?.length && !store.loading) {
    store.fetchAgenda()
  }
})
</script>
