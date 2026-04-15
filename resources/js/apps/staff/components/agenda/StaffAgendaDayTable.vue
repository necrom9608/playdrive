<template>
  <section class="rounded-[28px] border border-white/10 bg-slate-950/55 p-4 shadow-[0_24px_60px_rgba(2,6,23,0.28)] backdrop-blur-xl sm:p-5">
    <div class="flex flex-col gap-2 border-b border-white/10 pb-4 sm:flex-row sm:items-end sm:justify-between">
      <div>
        <div class="inline-flex items-center rounded-full border border-cyan-400/20 bg-cyan-500/10 px-3 py-1 text-[11px] font-semibold uppercase tracking-[0.24em] text-cyan-200">
          Dagplanning
        </div>
        <h2 class="mt-3 text-xl font-semibold text-white">Reservaties van de dag</h2>
        <p class="mt-1 text-sm text-slate-400">Zelfde tabelopbouw als in de POS, met een strakkere staff look.</p>
      </div>

      <div class="rounded-2xl border border-white/10 bg-white/5 px-4 py-3 text-right">
        <div class="text-[11px] uppercase tracking-[0.22em] text-slate-500">Totaal</div>
        <div class="mt-1 text-2xl font-semibold text-white">{{ registrations.length }}</div>
        <div class="text-sm text-slate-400">{{ totalParticipants }} personen</div>
      </div>
    </div>

    <div v-if="!registrations.length" class="flex min-h-[220px] items-center justify-center rounded-[24px] border border-dashed border-white/10 bg-slate-950/40 px-6 py-12 text-center text-sm text-slate-400">
      Geen reservaties voor deze dag.
    </div>

    <div v-else class="mt-4 overflow-hidden rounded-[24px] border border-white/10 bg-slate-950/40">
      <div class="overflow-x-auto">
        <table class="min-w-full table-fixed border-separate border-spacing-y-3 px-3">
          <thead>
            <tr class="text-left text-[11px] uppercase tracking-[0.24em] text-slate-400">
              <th class="px-3 py-2 font-medium">
                <div class="rounded-2xl border border-white/10 bg-white/5 px-4 py-3">Naam</div>
              </th>
              <th class="w-[88px] px-2 py-2 text-center font-medium">
                <div class="rounded-2xl border border-white/10 bg-white/5 px-3 py-3">K</div>
              </th>
              <th class="w-[88px] px-2 py-2 text-center font-medium">
                <div class="rounded-2xl border border-white/10 bg-white/5 px-3 py-3">V</div>
              </th>
              <th class="w-[88px] px-2 py-2 text-center font-medium">
                <div class="rounded-2xl border border-white/10 bg-white/5 px-3 py-3">T</div>
              </th>
              <th class="w-[120px] px-2 py-2 text-center font-medium">
                <div class="rounded-2xl border border-white/10 bg-white/5 px-3 py-3">Van</div>
              </th>
              <th class="w-[120px] px-2 py-2 text-center font-medium">
                <div class="rounded-2xl border border-white/10 bg-white/5 px-3 py-3">Duur</div>
              </th>
            </tr>
          </thead>

          <tbody>
            <tr v-for="registration in registrations" :key="registration.id" class="align-stretch text-slate-100">
              <td class="px-3 py-0">
                <article class="relative overflow-hidden rounded-[22px] border p-4 shadow-[0_18px_40px_rgba(15,23,42,0.22)]" :class="cardClass(registration)">
                  <div class="absolute bottom-4 left-0 top-4 w-1.5 rounded-full" :class="registration.status_color?.accent ?? 'bg-slate-500'" />

                  <div class="pl-4">
                    <div class="flex flex-wrap items-start justify-between gap-3">
                      <div class="min-w-0 flex-1">
                        <div class="text-xs text-slate-300/75">{{ formatDate(registration.event_date) }}</div>
                        <div class="mt-2 truncate text-2xl font-semibold text-white">{{ registration.name }}</div>
                      </div>

                      <div class="flex flex-wrap items-center justify-end gap-2">
                        <span class="rounded-full border px-3 py-1 text-xs font-semibold" :class="registration.status_color?.badge ?? 'border-white/10 bg-white/10 text-white'">
                          {{ registration.status_label }}
                        </span>
                        <span v-if="registration.event_type" class="rounded-full border border-sky-400/20 bg-sky-500/10 px-3 py-1 text-xs font-semibold text-sky-200">
                          {{ registration.event_type_emoji || '🎉' }} {{ registration.event_type }}
                        </span>
                        <span v-if="registration.catering_option" class="rounded-full border border-amber-400/20 bg-amber-500/10 px-3 py-1 text-xs font-semibold text-amber-200">
                          {{ registration.catering_option_emoji || '🍕' }} {{ registration.catering_option }}
                        </span>
                      </div>
                    </div>
                  </div>
                </article>
              </td>

              <td class="px-2 py-0">
                <div class="flex min-h-[72px] items-center justify-center rounded-[20px] border border-white/10 bg-white/[0.04] px-3 text-center text-2xl font-semibold text-white shadow-[0_14px_34px_rgba(15,23,42,0.16)]">{{ registration.participants_children ?? 0 }}</div>
              </td>
              <td class="px-2 py-0">
                <div class="flex min-h-[72px] items-center justify-center rounded-[20px] border border-white/10 bg-white/[0.04] px-3 text-center text-2xl font-semibold text-white shadow-[0_14px_34px_rgba(15,23,42,0.16)]">{{ registration.participants_adults ?? 0 }}</div>
              </td>
              <td class="px-2 py-0">
                <div class="flex min-h-[72px] items-center justify-center rounded-[20px] border border-white/10 bg-white/[0.04] px-3 text-center text-2xl font-semibold text-white shadow-[0_14px_34px_rgba(15,23,42,0.16)]">{{ registration.total_count ?? 0 }}</div>
              </td>
              <td class="px-2 py-0">
                <div class="flex min-h-[72px] items-center justify-center rounded-[20px] border border-white/10 bg-white/[0.04] px-3 text-center text-2xl font-semibold text-white shadow-[0_14px_34px_rgba(15,23,42,0.16)]">{{ registration.event_time || '—' }}</div>
              </td>
              <td class="px-2 py-0">
                <div class="flex min-h-[72px] items-center justify-center rounded-[20px] border border-white/10 bg-white/[0.04] px-3 text-center text-2xl font-semibold text-white shadow-[0_14px_34px_rgba(15,23,42,0.16)]">{{ formatDuration(registration) }}</div>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>

    <div v-if="tasks.length" class="mt-5 rounded-[24px] border border-white/10 bg-slate-950/40 p-4 sm:p-5">
      <div class="flex items-center justify-between gap-3">
        <div>
          <h3 class="text-lg font-semibold text-white">Taken van de dag</h3>
          <p class="mt-1 text-sm text-slate-400">Open en lopende taken voor deze geselecteerde dag.</p>
        </div>
        <span class="rounded-full border border-white/10 bg-white/5 px-3 py-1 text-sm font-medium text-slate-200">{{ tasks.length }}</span>
      </div>

      <div class="mt-4 grid gap-3">
        <article v-for="task in tasks" :key="task.id" class="rounded-[22px] border border-white/10 bg-white/[0.04] p-4 shadow-[0_14px_34px_rgba(15,23,42,0.18)]">
          <div class="flex flex-col gap-3 md:flex-row md:items-start md:justify-between">
            <div>
              <div class="flex flex-wrap items-center gap-2">
                <h4 class="text-base font-semibold text-white">{{ task.name }}</h4>
                <span class="rounded-full border px-2.5 py-1 text-xs font-semibold" :class="task.status_color?.badge ?? 'border-white/10 bg-white/10 text-white'">
                  {{ task.status_label }}
                </span>
              </div>
              <p class="mt-2 text-sm text-slate-400">{{ task.duration_label || 'Taak' }}<span v-if="task.assigned_user_name"> · {{ task.assigned_user_name }}</span></p>
            </div>
            <p v-if="task.comment" class="max-w-2xl text-sm text-slate-300">{{ task.comment }}</p>
          </div>
        </article>
      </div>
    </div>
  </section>
</template>

<script setup>
import { computed } from 'vue'

const props = defineProps({
  items: {
    type: Array,
    default: () => [],
  },
})

const registrations = computed(() => props.items.filter(item => item.item_type !== 'task'))
const tasks = computed(() => props.items.filter(item => item.item_type === 'task'))
const totalParticipants = computed(() => registrations.value.reduce((sum, item) => sum + Number(item.total_count || 0), 0))

function cardClass(registration) {
  return [
    registration.status_color?.border ?? 'border-white/10',
    registration.status_color?.bg ?? 'bg-white/[0.04]',
  ]
}

function formatDate(value) {
  if (!value) return 'Geen datum'

  const date = new Date(`${value}T12:00:00`)
  if (Number.isNaN(date.getTime())) return value

  return new Intl.DateTimeFormat('nl-BE', {
    weekday: 'long',
    day: '2-digit',
    month: '2-digit',
    year: 'numeric',
  }).format(date)
}

function formatDuration(registration) {
  const minutes = Number(registration.stay_duration_minutes || 0)

  if (minutes > 0) {
    const hours = Math.floor(minutes / 60)
    const remainingMinutes = minutes % 60
    return `${String(hours).padStart(2, '0')}:${String(remainingMinutes).padStart(2, '0')}`
  }

  return registration.duration_label || '—'
}
</script>
