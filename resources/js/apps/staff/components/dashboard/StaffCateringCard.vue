<template>
  <section class="rounded-[28px] border border-white/8 bg-slate-950/55 p-5 shadow-[0_18px_45px_rgba(2,6,23,0.28)] backdrop-blur-xl sm:p-6">
    <div class="flex items-start justify-between gap-3">
      <div>
        <div class="inline-flex items-center gap-2 rounded-full border border-fuchsia-400/20 bg-fuchsia-400/10 px-3 py-1 text-[11px] font-semibold uppercase tracking-[0.24em] text-fuchsia-200">
          <CakeIcon class="h-4 w-4" />
          Catering
        </div>
        <div class="mt-4 flex items-end gap-3">
          <p class="text-4xl font-semibold leading-none text-white">{{ data?.total ?? 0 }}</p>
          <p class="pb-1 text-sm text-slate-400">cateringreservaties</p>
        </div>
      </div>
    </div>

    <div v-if="items.length" class="mt-5 space-y-3">
      <article v-for="item in items" :key="item.key" class="rounded-[24px] border border-white/8 bg-white/[0.04] px-4 py-3.5">
        <div class="flex items-center justify-between gap-3">
          <div class="flex items-center gap-3">
            <div class="flex h-11 w-11 items-center justify-center rounded-2xl border border-white/8 bg-white/[0.04] text-xl">{{ item.emoji }}</div>
            <div>
              <div class="font-semibold text-white">{{ item.label }}</div>
              <div class="mt-1 text-sm text-slate-400">{{ item.participants }} personen</div>
            </div>
          </div>
          <div class="text-right">
            <div class="text-xl font-semibold leading-none text-white">{{ item.count }}</div>
            <div class="mt-1 text-xs text-slate-500">reservaties</div>
          </div>
        </div>
      </article>
    </div>

    <div v-else class="mt-5 rounded-[24px] border border-dashed border-white/10 bg-white/[0.03] px-4 py-7 text-center text-sm text-slate-400">
      Geen catering voor deze dag.
    </div>
  </section>
</template>

<script setup>
import { computed } from 'vue'
import { CakeIcon } from '@heroicons/vue/24/outline'

const props = defineProps({
  data: { type: Object, default: () => ({ total: 0, items: [] }) },
  loading: { type: Boolean, default: false },
})

const items = computed(() => props.data?.items ?? [])
</script>
