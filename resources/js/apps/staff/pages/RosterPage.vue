<template>
  <div class="space-y-4 sm:space-y-5">
    <!-- Subnavigatie: eigen shiften / verlof -->
    <div class="flex gap-1 rounded-2xl border border-white/10 bg-white/[0.04] p-1">
      <button
        type="button"
        class="flex-1 rounded-xl px-3 py-2 text-sm font-semibold transition"
        :class="view === 'shifts' ? 'bg-cyan-500/20 text-cyan-100' : 'text-slate-400 hover:text-slate-200'"
        @click="view = 'shifts'"
      >Mijn shiften</button>
      <button
        type="button"
        class="flex-1 rounded-xl px-3 py-2 text-sm font-semibold transition"
        :class="view === 'leave' ? 'bg-cyan-500/20 text-cyan-100' : 'text-slate-400 hover:text-slate-200'"
        @click="view = 'leave'"
      >Verlof</button>
    </div>

    <!-- ===================== MIJN SHIFTEN ===================== -->
    <div v-if="view === 'shifts'" class="space-y-4 sm:space-y-5">
    <!-- Weeknavigatie + weektotaal -->
    <section class="rounded-[28px] border border-white/8 bg-slate-950/55 p-5 shadow-[0_18px_45px_rgba(2,6,23,0.28)] backdrop-blur-xl sm:p-6">
      <div class="flex items-center justify-between gap-3">
        <button
          type="button"
          class="inline-flex h-11 w-11 items-center justify-center rounded-2xl border border-white/10 bg-white/[0.04] text-slate-200 transition hover:bg-white/[0.08]"
          @click="store.shiftWeek(-1)"
        >
          <ChevronLeftIcon class="h-5 w-5" />
        </button>

        <div class="min-w-0 flex-1 text-center">
          <p class="truncate text-base font-semibold text-white sm:text-lg">{{ store.data.range_label || 'Week' }}</p>
          <button
            v-if="!store.data.is_current_week"
            type="button"
            class="mt-1 text-xs font-semibold text-cyan-300 hover:text-cyan-200"
            @click="store.goToThisWeek()"
          >
            Naar deze week
          </button>
          <p v-else class="mt-1 text-xs text-slate-400">Deze week</p>
        </div>

        <button
          type="button"
          class="inline-flex h-11 w-11 items-center justify-center rounded-2xl border border-white/10 bg-white/[0.04] text-slate-200 transition hover:bg-white/[0.08]"
          @click="store.shiftWeek(1)"
        >
          <ChevronRightIcon class="h-5 w-5" />
        </button>
      </div>

      <div class="mt-4 grid grid-cols-2 gap-3">
        <div class="rounded-2xl border border-white/8 bg-white/[0.04] px-4 py-3">
          <p class="text-[11px] font-semibold uppercase tracking-[0.2em] text-slate-400">Shiften</p>
          <p class="mt-1 text-2xl font-semibold text-white">{{ store.data.totals?.shifts ?? 0 }}</p>
        </div>
        <div class="rounded-2xl border border-white/8 bg-white/[0.04] px-4 py-3">
          <p class="text-[11px] font-semibold uppercase tracking-[0.2em] text-slate-400">Totaal uren</p>
          <p class="mt-1 text-2xl font-semibold text-white">{{ store.data.totals?.hours_label ?? '0min' }}</p>
        </div>
      </div>
    </section>

    <!-- Laadstatus -->
    <div v-if="store.loading && !store.hasShifts" class="rounded-[28px] border border-white/8 bg-slate-950/55 px-4 py-10 text-center text-sm text-slate-400">
      Rooster laden…
    </div>

    <!-- Werkdagen -->
    <template v-else>
      <section
        v-for="day in store.workingDays"
        :key="day.date"
        class="rounded-[28px] border border-white/8 bg-slate-950/55 p-5 shadow-[0_18px_45px_rgba(2,6,23,0.28)] backdrop-blur-xl sm:p-6"
      >
        <div class="flex items-center gap-2">
          <h2 class="text-sm font-semibold text-white">{{ day.day_label }}</h2>
          <span
            v-if="day.is_today"
            class="inline-flex items-center rounded-full border border-cyan-400/25 bg-cyan-500/10 px-2.5 py-0.5 text-[11px] font-semibold text-cyan-200"
          >
            Vandaag
          </span>
        </div>

        <div class="mt-4 space-y-3">
          <article
            v-for="shift in day.shifts"
            :key="shift.id"
            class="rounded-[24px] border border-white/8 bg-white/[0.04] px-4 py-3.5"
            :style="shift.role?.color ? { borderLeft: `4px solid ${shift.role.color}` } : {}"
          >
            <div class="flex items-start justify-between gap-3">
              <div class="min-w-0">
                <div class="flex items-center gap-2">
                  <ClockIcon class="h-4 w-4 shrink-0 text-slate-400" />
                  <span class="font-semibold text-white">{{ shift.time_label }}</span>
                  <span class="text-xs text-slate-400">· {{ shift.duration_label }}</span>
                </div>
                <div v-if="shift.role" class="mt-2 inline-flex items-center gap-2">
                  <span class="h-2.5 w-2.5 rounded-full" :style="{ backgroundColor: shift.role.color || '#64748b' }"></span>
                  <span class="text-sm font-medium text-slate-200">{{ shift.role.name }}</span>
                </div>
              </div>

              <span
                v-if="shift.status === 'cancelled'"
                class="shrink-0 rounded-full border border-rose-400/25 bg-rose-500/10 px-2.5 py-1 text-[11px] font-semibold text-rose-200"
              >
                Geannuleerd
              </span>
            </div>

            <p v-if="shift.comment" class="mt-3 text-sm text-slate-300">{{ shift.comment }}</p>
            <p v-if="shift.note" class="mt-1.5 text-sm text-amber-200/90">{{ shift.note }}</p>

            <div v-if="shift.colleagues.length" class="mt-3 flex flex-wrap items-center gap-2">
              <UsersIcon class="h-4 w-4 text-slate-500" />
              <span
                v-for="name in shift.colleagues"
                :key="name"
                class="rounded-full border border-white/10 bg-white/[0.05] px-2.5 py-1 text-xs text-slate-300"
              >
                {{ name }}
              </span>
            </div>
          </article>
        </div>
      </section>

      <!-- Lege week -->
      <div
        v-if="!store.workingDays.length"
        class="rounded-[28px] border border-dashed border-white/10 bg-white/[0.03] px-4 py-12 text-center"
      >
        <CalendarDaysIcon class="mx-auto h-8 w-8 text-slate-500" />
        <p class="mt-3 text-sm text-slate-300">Geen shiften ingepland deze week.</p>
        <p class="mt-1 text-xs text-slate-500">Zodra de planning klaar is, verschijnen je shiften hier.</p>
      </div>
    </template>
    </div>

    <!-- ===================== VERLOF ===================== -->
    <div v-else class="space-y-4 sm:space-y-5">
      <!-- Aanvraagformulier -->
      <section class="rounded-[28px] border border-white/8 bg-slate-950/55 p-5 shadow-[0_18px_45px_rgba(2,6,23,0.28)] backdrop-blur-xl sm:p-6">
        <h2 class="text-sm font-semibold text-white">Verlof aanvragen</h2>
        <div class="mt-4 grid grid-cols-2 gap-3">
          <label class="block">
            <span class="text-xs font-medium text-slate-400">Van</span>
            <input
              v-model="form.start_date"
              type="date"
              class="mt-1 w-full rounded-2xl border border-white/10 bg-slate-950/60 px-3 py-2.5 text-sm text-white outline-none focus:border-cyan-500"
            />
          </label>
          <label class="block">
            <span class="text-xs font-medium text-slate-400">Tot en met</span>
            <input
              v-model="form.end_date"
              type="date"
              :min="form.start_date || undefined"
              class="mt-1 w-full rounded-2xl border border-white/10 bg-slate-950/60 px-3 py-2.5 text-sm text-white outline-none focus:border-cyan-500"
            />
          </label>
        </div>
        <label class="mt-3 block">
          <span class="text-xs font-medium text-slate-400">Reden (optioneel)</span>
          <textarea
            v-model="form.reason"
            rows="2"
            class="mt-1 w-full rounded-2xl border border-white/10 bg-slate-950/60 px-3 py-2.5 text-sm text-white outline-none focus:border-cyan-500"
            placeholder="bv. familiaal, vakantie…"
          ></textarea>
        </label>

        <p v-if="leave.error" class="mt-3 rounded-xl border border-rose-400/25 bg-rose-500/10 px-3 py-2 text-sm text-rose-200">{{ leave.error }}</p>
        <p v-if="formMessage" class="mt-3 rounded-xl border border-emerald-400/25 bg-emerald-500/10 px-3 py-2 text-sm text-emerald-200">{{ formMessage }}</p>

        <button
          type="button"
          :disabled="!canSubmit || leave.saving"
          class="mt-4 w-full rounded-2xl border border-cyan-400/30 bg-cyan-500/90 px-4 py-3 text-sm font-semibold text-white transition hover:bg-cyan-400 disabled:opacity-50"
          @click="submit"
        >
          {{ leave.saving ? 'Versturen…' : 'Verlof aanvragen' }}
        </button>
      </section>

      <!-- Eigen aanvragen -->
      <section class="rounded-[28px] border border-white/8 bg-slate-950/55 p-5 shadow-[0_18px_45px_rgba(2,6,23,0.28)] backdrop-blur-xl sm:p-6">
        <h2 class="text-sm font-semibold text-white">Mijn aanvragen</h2>

        <div v-if="leave.loading && !leave.requests.length" class="mt-4 text-sm text-slate-400">Laden…</div>

        <div v-else-if="!leave.requests.length" class="mt-4 rounded-[24px] border border-dashed border-white/10 bg-white/[0.03] px-4 py-8 text-center text-sm text-slate-400">
          Nog geen verlofaanvragen.
        </div>

        <div v-else class="mt-4 space-y-3">
          <article
            v-for="req in leave.requests"
            :key="req.id"
            class="rounded-[24px] border border-white/8 bg-white/[0.04] px-4 py-3.5"
          >
            <div class="flex items-start justify-between gap-3">
              <div class="min-w-0">
                <div class="font-semibold text-white">{{ req.period_label }}</div>
                <div class="mt-0.5 text-xs text-slate-400">{{ req.days }} dag(en)</div>
                <div v-if="req.reason" class="mt-1 text-sm text-slate-300">“{{ req.reason }}”</div>
              </div>
              <span
                class="shrink-0 rounded-full px-2.5 py-1 text-[11px] font-semibold"
                :class="{
                  'border border-amber-400/25 bg-amber-500/10 text-amber-200': req.status === 'pending',
                  'border border-emerald-400/25 bg-emerald-500/10 text-emerald-200': req.status === 'approved',
                  'border border-rose-400/25 bg-rose-500/10 text-rose-200': req.status === 'rejected',
                  'border border-white/10 bg-white/[0.05] text-slate-400': req.status === 'cancelled',
                }"
              >{{ req.status_label }}</span>
            </div>

            <p v-if="req.status === 'pending' && req.conflict_count > 0" class="mt-2 text-xs text-amber-300/90">
              Let op: valt samen met {{ req.conflict_count }} ingeplande shift(en).
            </p>
            <p v-if="req.review_note" class="mt-2 text-sm text-slate-400">Opmerking: {{ req.review_note }}</p>

            <button
              v-if="req.can_cancel"
              type="button"
              :disabled="leave.cancellingId === req.id"
              class="mt-3 rounded-xl border border-white/10 bg-white/[0.04] px-3 py-1.5 text-xs font-semibold text-slate-300 transition hover:bg-white/[0.08] disabled:opacity-50"
              @click="leave.cancelLeave(req.id)"
            >
              Intrekken
            </button>
          </article>
        </div>
      </section>
    </div>
  </div>
</template>

<script setup>
import { computed, onMounted, ref, watch } from 'vue'
import {
  CalendarDaysIcon,
  ChevronLeftIcon,
  ChevronRightIcon,
  ClockIcon,
  UsersIcon,
} from '@heroicons/vue/24/outline'
import { useStaffRosterStore } from '../stores/rosterStore'
import { useStaffLeaveStore } from '../stores/leaveStore'

const store = useStaffRosterStore()
const leave = useStaffLeaveStore()

const view = ref('shifts')
const form = ref({ start_date: '', end_date: '', reason: '' })
const formMessage = ref('')
let leaveLoaded = false

const canSubmit = computed(() =>
  !!form.value.start_date && !!form.value.end_date && form.value.end_date >= form.value.start_date)

async function submit() {
  formMessage.value = ''
  const ok = await leave.submitLeave({ ...form.value })
  if (ok) {
    formMessage.value = 'Aanvraag verstuurd.'
    form.value = { start_date: '', end_date: '', reason: '' }
    setTimeout(() => (formMessage.value = ''), 3000)
  }
}

watch(view, (v) => {
  if (v === 'leave' && !leaveLoaded) {
    leaveLoaded = true
    leave.fetchLeave()
  }
})

onMounted(() => {
  store.fetchRoster()
})
</script>
