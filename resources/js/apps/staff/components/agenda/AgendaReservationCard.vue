<template>
  <article class="overflow-hidden rounded-[28px] border shadow-[0_20px_60px_-28px_rgba(15,23,42,0.9)] backdrop-blur-xl transition hover:-translate-y-0.5" :class="cardClass">
    <div class="flex items-start justify-between gap-3 p-4 sm:p-5">
      <div class="min-w-0 flex-1">
        <div class="flex flex-wrap items-center gap-2">
          <span class="inline-flex items-center gap-1 rounded-full px-2.5 py-1 text-[11px] font-semibold ring-1 ring-inset" :class="typeTagClass">
            <component :is="typeIcon" class="h-3.5 w-3.5" />
            {{ typeLabel }}
          </span>
          <span class="inline-flex items-center rounded-full px-2.5 py-1 text-[11px] font-semibold ring-1 ring-inset" :class="badgeClass">
            {{ item.status_label }}
          </span>
        </div>

        <div class="mt-3 truncate text-lg font-semibold text-white sm:text-xl">{{ item.name }}</div>

        <div class="mt-2 flex flex-wrap items-center gap-2 text-sm text-slate-200/85">
          <span v-if="item.event_time" class="inline-flex items-center gap-1.5 rounded-full bg-black/20 px-2.5 py-1 ring-1 ring-white/10">
            <ClockIcon class="h-4 w-4" />
            {{ item.event_time }}
          </span>
          <span v-if="item.event_type" class="inline-flex items-center gap-1.5 rounded-full bg-black/20 px-2.5 py-1 ring-1 ring-white/10">
            <SparklesIcon class="h-4 w-4" />
            {{ item.event_type_emoji ? `${item.event_type_emoji} ` : '' }}{{ item.event_type }}
          </span>
          <span v-if="item.duration_label" class="inline-flex items-center gap-1.5 rounded-full bg-black/20 px-2.5 py-1 ring-1 ring-white/10">
            <BoltIcon class="h-4 w-4" />
            {{ item.duration_label }}
          </span>
        </div>
      </div>

      <div class="hidden h-12 w-1.5 rounded-full sm:block" :class="accentClass"></div>
    </div>

    <div class="border-t border-white/10 bg-black/10 px-4 py-3 sm:px-5">
      <div class="flex flex-wrap gap-2">
        <span v-if="item.total_count" class="inline-flex items-center gap-1.5 rounded-full bg-black/20 px-3 py-1.5 text-xs font-semibold text-white ring-1 ring-white/10">
          <UsersIcon class="h-4 w-4" />
          {{ item.total_count }} personen
        </span>
        <span v-if="item.catering_option" class="inline-flex items-center gap-1.5 rounded-full bg-black/20 px-3 py-1.5 text-xs font-semibold text-white ring-1 ring-white/10">
          <CakeIcon class="h-4 w-4" />
          {{ item.catering_option_emoji ? `${item.catering_option_emoji} ` : '' }}{{ item.catering_option }}
        </span>
        <span v-if="item.assigned_user_name" class="inline-flex items-center gap-1.5 rounded-full bg-black/20 px-3 py-1.5 text-xs font-semibold text-white ring-1 ring-white/10">
          <UserCircleIcon class="h-4 w-4" />
          {{ item.assigned_user_name }}
        </span>
        <span v-if="item.is_general" class="inline-flex items-center gap-1.5 rounded-full bg-black/20 px-3 py-1.5 text-xs font-semibold text-white ring-1 ring-white/10">
          <UsersIcon class="h-4 w-4" />
          Algemene taak
        </span>
        <span v-if="item.invoice_requested" class="inline-flex items-center gap-1.5 rounded-full bg-black/20 px-3 py-1.5 text-xs font-semibold text-white ring-1 ring-white/10">
          <DocumentTextIcon class="h-4 w-4" />
          Factuur
        </span>
        <span v-if="item.outside_opening_hours" class="inline-flex items-center gap-1.5 rounded-full bg-black/20 px-3 py-1.5 text-xs font-semibold text-white ring-1 ring-white/10">
          <MoonIcon class="h-4 w-4" />
          Buiten openingsuren
        </span>
      </div>

      <p v-if="item.comment" class="mt-3 text-sm leading-6 text-slate-100/85">
        {{ item.comment }}
      </p>
    </div>
  </article>
</template>

<script setup>
import { computed } from 'vue'
import {
  BoltIcon,
  CakeIcon,
  ClockIcon,
  DocumentTextIcon,
  MoonIcon,
  SparklesIcon,
  UserCircleIcon,
  UsersIcon,
  WrenchScrewdriverIcon,
} from '@heroicons/vue/24/outline'

const props = defineProps({
  item: { type: Object, required: true },
})

const reservationStatusStyles = {
  new: {
    card: 'border-yellow-400/30 bg-gradient-to-br from-yellow-500/18 via-yellow-500/8 to-slate-950/78',
    badge: 'bg-yellow-500/18 text-yellow-100 ring-yellow-300/30',
    accent: 'bg-yellow-400',
  },
  confirmed: {
    card: 'border-orange-400/30 bg-gradient-to-br from-orange-500/18 via-orange-500/8 to-slate-950/78',
    badge: 'bg-orange-500/18 text-orange-100 ring-orange-300/30',
    accent: 'bg-orange-400',
  },
  checked_in: {
    card: 'border-blue-400/30 bg-gradient-to-br from-blue-500/18 via-blue-500/8 to-slate-950/78',
    badge: 'bg-blue-500/18 text-blue-100 ring-blue-300/30',
    accent: 'bg-blue-400',
  },
  checked_out: {
    card: 'border-violet-400/30 bg-gradient-to-br from-violet-500/18 via-violet-500/8 to-slate-950/78',
    badge: 'bg-violet-500/18 text-violet-100 ring-violet-300/30',
    accent: 'bg-violet-400',
  },
  paid: {
    card: 'border-emerald-400/30 bg-gradient-to-br from-emerald-500/18 via-emerald-500/8 to-slate-950/78',
    badge: 'bg-emerald-500/18 text-emerald-100 ring-emerald-300/30',
    accent: 'bg-emerald-400',
  },
  cancelled: {
    card: 'border-slate-400/25 bg-gradient-to-br from-slate-500/16 via-slate-500/8 to-slate-950/78',
    badge: 'bg-slate-500/20 text-slate-100 ring-slate-300/20',
    accent: 'bg-slate-400',
  },
  no_show: {
    card: 'border-rose-400/30 bg-gradient-to-br from-rose-500/18 via-rose-500/8 to-slate-950/78',
    badge: 'bg-rose-500/18 text-rose-100 ring-rose-300/30',
    accent: 'bg-rose-400',
  },
}

const taskStatusStyles = {
  completed: {
    card: 'border-emerald-400/30 bg-gradient-to-br from-emerald-500/16 via-emerald-500/7 to-slate-950/78',
    badge: 'bg-emerald-500/18 text-emerald-100 ring-emerald-300/30',
    accent: 'bg-emerald-400',
  },
  default: {
    card: 'border-fuchsia-400/30 bg-gradient-to-br from-fuchsia-500/16 via-fuchsia-500/8 to-slate-950/78',
    badge: 'bg-fuchsia-500/18 text-fuchsia-100 ring-fuchsia-300/30',
    accent: 'bg-fuchsia-400',
  },
}

const palette = computed(() => {
  if (props.item.item_type === 'task') {
    return taskStatusStyles[props.item.status] ?? taskStatusStyles.default
  }

  return reservationStatusStyles[props.item.status] ?? reservationStatusStyles.confirmed
})

const cardClass = computed(() => palette.value.card)
const badgeClass = computed(() => palette.value.badge)
const accentClass = computed(() => palette.value.accent)

const typeLabel = computed(() => props.item.item_type === 'task' ? 'Taak' : 'Reservatie')
const typeTagClass = computed(() => props.item.item_type === 'task'
  ? 'bg-fuchsia-500/18 text-fuchsia-100 ring-fuchsia-300/30'
  : 'bg-cyan-500/18 text-cyan-100 ring-cyan-300/30')
const typeIcon = computed(() => props.item.item_type === 'task' ? WrenchScrewdriverIcon : SparklesIcon)
</script>
