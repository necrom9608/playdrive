<template>
  <section class="space-y-4">
    <div v-if="!registrations.length && !tasks.length" class="flex min-h-[220px] items-center justify-center rounded-[24px] border border-dashed border-white/10 bg-slate-950/40 px-6 py-12 text-center text-sm text-slate-400">
      Geen items voor deze dag.
    </div>

    <template v-else>
      <article
        v-for="registration in registrations"
        :key="registration.id"
        class="relative overflow-hidden rounded-[28px] border p-4 shadow-[0_18px_44px_rgba(15,23,42,0.22)] backdrop-blur-xl sm:p-5"
        :class="cardClass(registration)"
      >
        <div class="absolute bottom-4 left-0 top-4 w-1.5 rounded-full" :class="registration.status_color?.accent ?? 'bg-slate-500'" />

        <div class="pl-4">
          <div class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
            <div class="min-w-0 flex-1">
              <div class="text-xs text-slate-300/75">{{ formatDate(registration.event_date) }}</div>
              <div class="mt-2 text-2xl font-semibold text-white">{{ registration.name }}</div>

              <div class="mt-3 flex flex-wrap gap-2">
                <span class="rounded-full border px-3 py-1 text-xs font-semibold" :class="registration.status_color?.badge ?? 'border-white/10 bg-white/10 text-white'">
                  {{ registration.status_label }}
                </span>
                <span class="rounded-full border border-white/10 bg-white/[0.05] px-3 py-1 text-xs font-semibold text-white">
                  K {{ registration.participants_children ?? 0 }}
                </span>
                <span class="rounded-full border border-white/10 bg-white/[0.05] px-3 py-1 text-xs font-semibold text-white">
                  V {{ registration.participants_adults ?? 0 }}
                </span>
                <span class="rounded-full border border-white/10 bg-white/[0.05] px-3 py-1 text-xs font-semibold text-white">
                  T {{ registration.total_count ?? 0 }}
                </span>
                <span class="rounded-full border border-white/10 bg-white/[0.05] px-3 py-1 text-xs font-semibold text-white">
                  Van {{ registration.event_time || '—' }}
                </span>
                <span class="rounded-full border border-white/10 bg-white/[0.05] px-3 py-1 text-xs font-semibold text-white">
                  Duur {{ formatDuration(registration) }}
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
        </div>
      </article>

      <article
        v-for="task in tasks"
        :key="`task-${task.id}`"
        class="rounded-[24px] border border-white/10 bg-slate-950/55 p-4 shadow-[0_14px_34px_rgba(15,23,42,0.18)] backdrop-blur-xl"
      >
        <div class="flex flex-col gap-3 lg:flex-row lg:items-start lg:justify-between">
          <div>
            <div class="text-xs uppercase tracking-[0.24em] text-slate-500">Taak</div>
            <h3 class="mt-2 text-lg font-semibold text-white">{{ task.name }}</h3>
            <div class="mt-3 flex flex-wrap gap-2">
              <span class="rounded-full border px-3 py-1 text-xs font-semibold" :class="task.status_color?.badge ?? 'border-white/10 bg-white/10 text-white'">
                {{ task.status_label }}
              </span>
              <span v-if="task.assigned_user_name" class="rounded-full border border-white/10 bg-white/[0.05] px-3 py-1 text-xs font-semibold text-white">
                {{ task.assigned_user_name }}
              </span>
              <span v-if="task.duration_label" class="rounded-full border border-white/10 bg-white/[0.05] px-3 py-1 text-xs font-semibold text-white">
                {{ task.duration_label }}
              </span>
            </div>
          </div>

          <p v-if="task.comment" class="max-w-2xl text-sm text-slate-300">{{ task.comment }}</p>
        </div>
      </article>
    </template>
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
