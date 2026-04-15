<template>
  <section class="w-full">
    <div class="space-y-3">
      <div class="text-center">
        <div class="truncate text-sm font-semibold text-white sm:text-base">{{ rangeLabel }}</div>
      </div>

      <div class="flex items-center gap-2">
        <div class="grid min-w-0 flex-1 grid-cols-3 gap-2 rounded-2xl bg-slate-950/70 p-1.5">
          <RouterLink
            v-for="item in viewItems"
            :key="item.to"
            :to="item.to"
            class="inline-flex items-center justify-center gap-2 rounded-xl border px-3 py-2.5 text-center text-sm font-semibold transition"
            :class="isActive(item.to)
              ? 'border-cyan-300/50 bg-cyan-400 text-slate-950 shadow-sm'
              : 'border-transparent text-slate-300 hover:bg-white/[0.06] hover:text-white'"
          >
            <component :is="item.icon" class="h-4 w-4" />
            <span>{{ item.label }}</span>
          </RouterLink>
        </div>

        <button
          type="button"
          class="inline-flex h-[50px] w-[50px] shrink-0 items-center justify-center rounded-2xl border border-white/10 bg-white/[0.04] text-slate-200 transition hover:border-white/20 hover:bg-white/[0.08] hover:text-white"
          @click="$emit('toggle-expanded')"
        >
          <AdjustmentsHorizontalIcon class="h-5 w-5" />
        </button>
      </div>

      <div v-if="expanded" class="space-y-3 rounded-[24px] border border-white/10 bg-white/[0.03] p-3">
        <div class="flex items-center gap-2">
          <button type="button" class="flex h-11 w-11 items-center justify-center rounded-2xl border border-white/10 bg-white/[0.04] text-slate-200 transition hover:border-cyan-400/50 hover:bg-white/[0.07] hover:text-white" @click="$emit('previous')">
            <ChevronLeftIcon class="h-5 w-5" />
          </button>

          <input
            :value="modelValue"
            :type="inputType"
            class="min-w-0 flex-1 rounded-2xl border border-white/10 bg-white/[0.04] px-4 py-3 text-center text-sm font-medium text-white outline-none transition placeholder:text-slate-500 focus:border-cyan-400/60"
            @input="$emit('update:modelValue', $event.target.value)"
            @change="$emit('change')"
          >

          <button type="button" class="flex h-11 w-11 items-center justify-center rounded-2xl border border-white/10 bg-white/[0.04] text-slate-200 transition hover:border-cyan-400/50 hover:bg-white/[0.07] hover:text-white" @click="$emit('next')">
            <ChevronRightIcon class="h-5 w-5" />
          </button>
        </div>

        <div class="flex items-center justify-between gap-3 rounded-2xl border border-white/10 bg-white/[0.03] px-4 py-3">
          <div class="min-w-0">
            <div class="text-[11px] font-semibold uppercase tracking-[0.24em] text-slate-500">Periode</div>
            <div class="mt-1 truncate text-sm font-semibold text-white">{{ rangeLabel }}</div>
          </div>
          <button type="button" class="inline-flex items-center gap-2 rounded-xl border border-cyan-400/30 bg-cyan-400/10 px-3 py-2 text-sm font-semibold text-cyan-200 transition hover:border-cyan-400/50 hover:bg-cyan-400/15" @click="$emit('today')">
            <CalendarDaysIcon class="h-4 w-4" />
            <span>Vandaag</span>
          </button>
        </div>
      </div>
    </div>
  </section>
</template>

<script setup>
import { RouterLink, useRoute } from 'vue-router'
import {
  AdjustmentsHorizontalIcon,
  CalendarDaysIcon,
  ChevronLeftIcon,
  ChevronRightIcon,
  ViewColumnsIcon,
  TableCellsIcon,
  CalendarIcon,
} from '@heroicons/vue/24/outline'

defineProps({
  modelValue: { type: String, required: true },
  rangeLabel: { type: String, default: '' },
  inputType: { type: String, default: 'date' },
  expanded: { type: Boolean, default: false },
})

defineEmits(['update:modelValue', 'change', 'previous', 'next', 'today', 'toggle-expanded'])

const route = useRoute()
const viewItems = [
  { to: '/agenda/day', label: 'Dag', icon: ViewColumnsIcon },
  { to: '/agenda/week', label: 'Week', icon: TableCellsIcon },
  { to: '/agenda/month', label: 'Maand', icon: CalendarIcon },
]

function isActive(path) {
  return route.path === path
}
</script>
