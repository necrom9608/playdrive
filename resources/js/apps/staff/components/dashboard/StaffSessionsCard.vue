<template>
  <section class="rounded-[28px] border border-white/8 bg-slate-950/55 p-5 shadow-[0_18px_45px_rgba(2,6,23,0.28)] backdrop-blur-xl sm:p-6">
    <div class="flex items-start justify-between gap-3">
      <div>
        <div class="inline-flex items-center gap-2 rounded-full border border-violet-400/20 bg-violet-400/10 px-3 py-1 text-[11px] font-semibold uppercase tracking-[0.24em] text-violet-200">
          <ClockIcon class="h-4 w-4" />
          Werkmomenten
        </div>
        <div class="mt-4 flex items-end gap-3">
          <p class="text-4xl font-semibold leading-none text-white">{{ workedLabel || '00:00' }}</p>
          <p class="pb-1 text-sm text-slate-400">gewerkt</p>
        </div>
      </div>
    </div>

    <div v-if="items.length" class="mt-5 space-y-3">
      <article v-for="session in items" :key="session.id" class="rounded-[24px] border border-white/8 bg-white/[0.04] px-4 py-3.5">
        <div class="flex items-center justify-between gap-3">
          <div>
            <div class="font-semibold text-white">{{ session.checked_in_at_label }} - {{ session.checked_out_at_label || 'actief' }}</div>
            <div class="mt-1.5 text-sm text-slate-400">Duur {{ session.duration_label }}</div>
          </div>
          <span v-if="session.is_active" class="inline-flex items-center gap-1 rounded-full border border-emerald-400/25 bg-emerald-500/10 px-2.5 py-1 text-xs font-semibold text-emerald-200">
            <PlayCircleIcon class="h-4 w-4" />
            Actief
          </span>
        </div>
      </article>
    </div>

    <div v-else class="mt-5 rounded-[24px] border border-dashed border-white/10 bg-white/[0.03] px-4 py-7 text-center text-sm text-slate-400">
      Geen werkmomenten voor deze dag.
    </div>
  </section>
</template>

<script setup>
import { ClockIcon, PlayCircleIcon } from '@heroicons/vue/24/outline'

defineProps({
  items: { type: Array, default: () => [] },
  workedLabel: { type: String, default: '00:00' },
  loading: { type: Boolean, default: false },
})
</script>
