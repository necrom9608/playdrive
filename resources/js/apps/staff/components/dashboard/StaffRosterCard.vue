<template>
  <section class="rounded-[28px] border border-white/8 bg-slate-950/55 p-5 shadow-[0_18px_45px_rgba(2,6,23,0.28)] backdrop-blur-xl sm:p-6">
    <div class="inline-flex items-center gap-2 rounded-full border border-cyan-400/20 bg-cyan-400/10 px-3 py-1 text-[11px] font-semibold uppercase tracking-[0.24em] text-cyan-200">
      <ClockIcon class="h-4 w-4" />
      Mijn shift
    </div>

    <div v-if="items.length" class="mt-4 space-y-3">
      <article
        v-for="shift in items"
        :key="shift.id"
        class="rounded-[24px] border border-white/8 bg-white/[0.04] px-4 py-3.5"
        :style="shift.role?.color ? { borderLeft: `4px solid ${shift.role.color}` } : {}"
      >
        <div class="flex items-center gap-2">
          <span class="text-lg font-semibold text-white">{{ shift.time_label }}</span>
          <span class="text-xs text-slate-400">· {{ shift.duration_label }}</span>
        </div>
        <div v-if="shift.role" class="mt-2 inline-flex items-center gap-2">
          <span class="h-2.5 w-2.5 rounded-full" :style="{ backgroundColor: shift.role.color || '#64748b' }"></span>
          <span class="text-sm font-medium text-slate-200">{{ shift.role.name }}</span>
        </div>
        <p v-if="shift.comment" class="mt-2 text-sm text-slate-300">{{ shift.comment }}</p>
        <p v-if="shift.note" class="mt-1 text-sm text-amber-200/90">{{ shift.note }}</p>
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

    <div v-else class="mt-4 rounded-[24px] border border-dashed border-white/10 bg-white/[0.03] px-4 py-7 text-center text-sm text-slate-400">
      Geen shift ingepland voor deze dag.
    </div>
  </section>
</template>

<script setup>
import { ClockIcon, UsersIcon } from '@heroicons/vue/24/outline'

defineProps({
  items: { type: Array, default: () => [] },
  loading: { type: Boolean, default: false },
})
</script>
