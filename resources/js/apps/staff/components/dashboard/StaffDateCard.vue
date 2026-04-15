<template>
  <section class="rounded-[28px] border border-white/8 bg-slate-950/55 px-5 py-4 shadow-[0_18px_45px_rgba(2,6,23,0.28)] backdrop-blur-xl sm:px-6">
    <div class="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
      <div>
        <div class="inline-flex items-center gap-2 rounded-full border border-cyan-400/20 bg-cyan-400/10 px-3 py-1 text-[11px] font-semibold uppercase tracking-[0.24em] text-cyan-200">
          <CalendarDaysIcon class="h-4 w-4" />
          Datum
        </div>
        <div class="mt-3 text-[1.65rem] font-semibold leading-tight text-white sm:text-3xl">{{ selectedDateLabel || 'Kies een dag' }}</div>
      </div>

      <div class="flex flex-wrap items-center gap-2.5">
        <button
          type="button"
          class="inline-flex h-12 w-12 items-center justify-center rounded-2xl border border-white/8 bg-white/[0.04] text-slate-100 transition hover:bg-white/[0.07] disabled:cursor-not-allowed disabled:opacity-60"
          :disabled="loading"
          @click="$emit('previous')"
        >
          <ChevronLeftIcon class="h-5 w-5" />
        </button>

        <label class="date-input-wrap inline-flex h-12 items-center gap-2 rounded-2xl border border-white/8 bg-white/[0.04] px-4 text-sm font-semibold text-slate-100 transition hover:bg-white/[0.07]">
          <CalendarIcon class="h-5 w-5 text-cyan-200" />
          <input :value="selectedDate" type="date" class="date-input" @input="$emit('select', $event.target.value)">
        </label>

        <button
          type="button"
          class="inline-flex h-12 w-12 items-center justify-center rounded-2xl border border-white/8 bg-white/[0.04] text-slate-100 transition hover:bg-white/[0.07] disabled:cursor-not-allowed disabled:opacity-60"
          :disabled="loading"
          @click="$emit('next')"
        >
          <ChevronRightIcon class="h-5 w-5" />
        </button>

        <button
          type="button"
          class="inline-flex h-12 items-center gap-2 rounded-2xl border border-white/8 bg-white/[0.04] px-4 text-sm font-semibold text-slate-100 transition hover:bg-white/[0.07] disabled:cursor-not-allowed disabled:opacity-60"
          :disabled="loading || isToday"
          @click="$emit('today')"
        >
          <ClockIcon class="h-5 w-5" />
          <span>Vandaag</span>
        </button>
      </div>
    </div>
  </section>
</template>

<script setup>
import {
  CalendarDaysIcon,
  CalendarIcon,
  ChevronLeftIcon,
  ChevronRightIcon,
  ClockIcon,
} from '@heroicons/vue/24/outline'

defineProps({
  selectedDate: { type: String, required: true },
  selectedDateLabel: { type: String, default: '' },
  isToday: { type: Boolean, default: false },
  loading: { type: Boolean, default: false },
})

defineEmits(['previous', 'next', 'today', 'select'])
</script>

<style scoped>
.date-input-wrap {
  position: relative;
  overflow: hidden;
}

.date-input {
  background: transparent;
  font-size: 0.875rem;
  font-weight: 600;
  color: rgb(241 245 249);
  outline: none;
  color-scheme: dark;
}
</style>
