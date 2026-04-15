<template>
  <section class="rounded-[28px] border border-white/8 bg-slate-950/55 p-5 shadow-[0_18px_45px_rgba(2,6,23,0.28)] backdrop-blur-xl sm:p-6">
    <div>
      <div class="inline-flex items-center gap-2 rounded-full border border-amber-400/20 bg-amber-400/10 px-3 py-1 text-[11px] font-semibold uppercase tracking-[0.24em] text-amber-200">
        <ChartPieIcon class="h-4 w-4" />
        Reservaties
      </div>
      <div class="mt-4 flex items-end gap-3">
        <p class="text-4xl font-semibold leading-none text-white">{{ data?.total ?? 0 }}</p>
        <p class="pb-1 text-sm text-slate-400">{{ data?.participants ?? 0 }} personen</p>
      </div>
    </div>

    <div class="mt-5">
      <div class="overflow-hidden rounded-full border border-white/8 bg-slate-900/75">
        <div class="flex h-3.5 w-full overflow-hidden rounded-full">
          <div
            v-for="segment in visibleSegments"
            :key="segment.key"
            :style="{ width: `${segment.percentage}%`, backgroundColor: segment.color }"
            class="h-full"
          ></div>
          <div v-if="!visibleSegments.length" class="h-full w-full bg-white/5"></div>
        </div>
      </div>

      <div class="mt-4 grid gap-3 sm:grid-cols-2 xl:grid-cols-3">
        <article
          v-for="segment in visibleSegments"
          :key="segment.key"
          class="rounded-[24px] px-4 py-3.5"
          :style="segment.cardStyle"
        >
          <div class="flex items-start justify-between gap-3">
            <div class="flex items-center gap-3">
              <span class="mt-1 h-3 w-3 rounded-full" :style="{ backgroundColor: segment.color }"></span>
              <div>
                <p class="font-semibold text-white">{{ segment.label }}</p>
                <p class="mt-1 text-sm text-white/70">{{ segment.participants }} personen</p>
              </div>
            </div>
            <div class="text-right">
              <div class="text-xl font-semibold leading-none" :style="{ color: segment.color }">{{ segment.count }}</div>
              <div class="mt-1 text-xs text-white/55">{{ segment.percentage }}%</div>
            </div>
          </div>
        </article>
        <div
          v-if="!visibleSegments.length"
          class="rounded-[24px] border border-dashed border-white/10 bg-white/[0.03] px-4 py-6 text-sm text-slate-400"
        >
          Geen reservaties voor deze dag.
        </div>
      </div>
    </div>
  </section>
</template>

<script setup>
import { computed } from 'vue'
import { ChartPieIcon } from '@heroicons/vue/24/outline'

const props = defineProps({
  data: { type: Object, default: () => ({ total: 0, participants: 0, statuses: [] }) },
  loading: { type: Boolean, default: false },
})

const palette = {
  new: '#f59e0b',
  confirmed: '#38bdf8',
  checked_in: '#3b82f6',
  checked_out: '#8b5cf6',
  paid: '#10b981',
  cancelled: '#f43f5e',
  no_show: '#fb7185',
}

const hexToRgba = (hex, alpha) => {
  const normalized = (hex || '').replace('#', '')
  if (normalized.length !== 6) {
    return `rgba(148, 163, 184, ${alpha})`
  }

  const r = parseInt(normalized.slice(0, 2), 16)
  const g = parseInt(normalized.slice(2, 4), 16)
  const b = parseInt(normalized.slice(4, 6), 16)

  return `rgba(${r}, ${g}, ${b}, ${alpha})`
}

const segments = computed(() => {
  const total = Number(props.data?.total ?? 0)
  return (props.data?.statuses ?? []).map((item) => {
    const color = palette[item.key] || '#94a3b8'

    return {
      ...item,
      color,
      percentage: total > 0 ? Math.round((Number(item.count || 0) / total) * 100) : 0,
      cardStyle: {
        backgroundColor: hexToRgba(color, 0.16),
        border: `1px solid ${hexToRgba(color, 0.34)}`,
        boxShadow: `inset 0 1px 0 ${hexToRgba(color, 0.1)}`,
      },
    }
  })
})

const visibleSegments = computed(() => segments.value.filter((segment) => segment.count > 0))
</script>
