<template>
  <div class="space-y-5">
    <section class="rounded-[30px] border border-white/10 bg-slate-950/55 p-4 shadow-[0_24px_60px_rgba(2,6,23,0.28)] backdrop-blur-xl sm:p-5">
      <div class="flex flex-col gap-4 xl:flex-row xl:items-end xl:justify-between">
        <div>
          <div class="inline-flex items-center rounded-full border border-indigo-400/20 bg-indigo-500/10 px-3 py-1 text-[11px] font-semibold uppercase tracking-[0.24em] text-indigo-200">
            Agenda
          </div>
          <h1 class="mt-3 text-3xl font-semibold text-white">Planning per dag, week of maand</h1>
          <p class="mt-2 max-w-2xl text-sm text-slate-400">Gebruik de periodekiezer om snel door de agenda te bewegen. In dagzicht krijg je dezelfde tabelstructuur als in de POS.</p>
        </div>

        <div class="flex flex-wrap items-center gap-2">
          <button v-for="view in views" :key="view.value" type="button" class="rounded-2xl border px-4 py-2.5 text-sm font-semibold transition" :class="store.view === view.value
            ? 'border-cyan-400/30 bg-cyan-500/15 text-cyan-100 shadow-[0_8px_24px_rgba(6,182,212,0.18)]'
            : 'border-white/10 bg-white/[0.04] text-slate-300 hover:border-white/15 hover:bg-white/[0.07]'" @click="changeView(view.value)">
            {{ view.label }}
          </button>
        </div>
      </div>

      <div class="mt-5 flex flex-col gap-3 xl:flex-row xl:items-center xl:justify-between">
        <div class="rounded-[24px] border border-white/10 bg-white/[0.04] p-3">
          <div class="flex flex-wrap items-center gap-2">
            <button type="button" class="inline-flex h-12 w-12 items-center justify-center rounded-2xl border border-white/10 bg-white/[0.04] text-slate-200 transition hover:border-white/15 hover:bg-white/[0.08]" @click="shiftDate(-1)">
              <ChevronLeftIcon class="h-5 w-5" />
            </button>

            <label class="relative flex min-w-[210px] items-center gap-3 rounded-2xl border border-white/10 bg-slate-950/80 px-4 py-3 text-sm text-slate-200 shadow-inner shadow-slate-950/30">
              <CalendarDaysIcon class="h-5 w-5 text-cyan-300" />
              <input v-model="store.date" type="date" class="w-full bg-transparent outline-none [color-scheme:dark]" @change="store.fetchAgenda()">
            </label>

            <button type="button" class="inline-flex h-12 w-12 items-center justify-center rounded-2xl border border-white/10 bg-white/[0.04] text-slate-200 transition hover:border-white/15 hover:bg-white/[0.08]" @click="shiftDate(1)">
              <ChevronRightIcon class="h-5 w-5" />
            </button>

            <button type="button" class="rounded-2xl border border-white/10 bg-white/[0.04] px-4 py-3 text-sm font-semibold text-slate-200 transition hover:border-white/15 hover:bg-white/[0.07]" @click="goToday">
              Vandaag
            </button>
          </div>
        </div>

        <div class="rounded-[24px] border border-white/10 bg-white/[0.04] px-4 py-3 text-sm text-slate-300">
          <div class="text-[11px] uppercase tracking-[0.24em] text-slate-500">Periode</div>
          <div class="mt-1 text-base font-semibold text-white">{{ store.data.range?.label }}</div>
        </div>
      </div>
    </section>

    <div v-if="store.loading" class="rounded-[28px] border border-white/10 bg-slate-950/45 px-6 py-12 text-center text-sm text-slate-300 backdrop-blur">
      Agenda laden...
    </div>

    <template v-else>
      <StaffAgendaDayTable v-if="store.view === 'day'" :items="store.data.day_registrations || []" />

      <section v-else class="grid gap-4 sm:grid-cols-2 xl:grid-cols-3">
        <button
          v-for="day in store.data.days"
          :key="day.date"
          type="button"
          class="overflow-hidden rounded-[28px] border p-5 text-left shadow-[0_22px_50px_rgba(2,6,23,0.22)] transition duration-200"
          :class="day.is_selected
            ? 'border-cyan-400/30 bg-cyan-500/[0.14]'
            : day.is_today
              ? 'border-indigo-400/25 bg-indigo-500/[0.10] hover:border-indigo-300/35 hover:bg-indigo-500/[0.14]'
              : 'border-white/10 bg-slate-950/55 hover:border-white/15 hover:bg-slate-950/70'"
          @click="selectDay(day.date)"
        >
          <div class="flex items-start justify-between gap-4">
            <div>
              <div class="text-sm text-slate-400">{{ day.weekday_label }}</div>
              <div class="mt-1 text-3xl font-semibold text-white">{{ day.day_number }}</div>
              <div class="mt-2 text-sm text-slate-500">{{ day.label }}</div>
            </div>

            <span v-if="day.is_today" class="rounded-full border border-cyan-400/20 bg-cyan-500/10 px-3 py-1 text-xs font-semibold uppercase tracking-[0.2em] text-cyan-200">Vandaag</span>
          </div>

          <div class="mt-5 grid grid-cols-2 gap-3">
            <div class="rounded-[22px] border border-white/10 bg-white/[0.04] px-4 py-3">
              <div class="text-[11px] uppercase tracking-[0.22em] text-slate-500">Reservaties</div>
              <div class="mt-2 text-2xl font-semibold text-white">{{ day.totals?.reservations ?? 0 }}</div>
            </div>
            <div class="rounded-[22px] border border-white/10 bg-white/[0.04] px-4 py-3">
              <div class="text-[11px] uppercase tracking-[0.22em] text-slate-500">Taken</div>
              <div class="mt-2 text-2xl font-semibold text-white">{{ day.totals?.tasks ?? 0 }}</div>
            </div>
          </div>

          <div class="mt-4 flex flex-wrap gap-2">
            <span v-for="status in day.status_totals || []" :key="status.key" class="rounded-full border px-3 py-1 text-xs font-semibold" :class="status.colors?.badge ?? 'border-white/10 bg-white/10 text-white'">
              {{ status.label }} {{ status.count }}
            </span>
          </div>
        </button>
      </section>
    </template>
  </div>
</template>

<script setup>
import { onMounted } from 'vue'
import {
  CalendarDaysIcon,
  ChevronLeftIcon,
  ChevronRightIcon,
} from '@heroicons/vue/24/outline'
import StaffAgendaDayTable from '../components/agenda/StaffAgendaDayTable.vue'
import { useStaffAgendaStore } from '../stores/agendaStore'

const store = useStaffAgendaStore()
const views = [
  { value: 'day', label: 'Dag' },
  { value: 'week', label: 'Week' },
  { value: 'month', label: 'Maand' },
]

function changeView(view) {
  store.view = view
  store.fetchAgenda()
}

function selectDay(date) {
  store.date = date
  store.view = 'day'
  store.fetchAgenda()
}

function shiftDate(amount) {
  const value = store.date || new Date().toISOString().slice(0, 10)
  const date = new Date(`${value}T12:00:00`)
  if (Number.isNaN(date.getTime())) return

  date.setDate(date.getDate() + amount)
  store.date = date.toISOString().slice(0, 10)
  store.fetchAgenda()
}

function goToday() {
  store.date = new Date().toISOString().slice(0, 10)
  store.fetchAgenda()
}

onMounted(() => store.fetchAgenda())
</script>
