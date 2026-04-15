<template>
  <section class="rounded-[28px] border border-emerald-400/18 bg-[linear-gradient(135deg,rgba(16,185,129,0.16),rgba(6,182,212,0.08))] p-5 shadow-[0_18px_45px_rgba(2,6,23,0.28)] backdrop-blur-xl sm:p-6">
    <div class="flex items-start justify-between gap-3">
      <div>
        <div class="inline-flex items-center gap-2 rounded-full border border-emerald-300/25 bg-emerald-300/10 px-3 py-1 text-[11px] font-semibold uppercase tracking-[0.24em] text-emerald-100">
          <BanknotesIcon class="h-4 w-4" />
          Omzet
        </div>
        <p class="mt-4 text-3xl font-semibold text-white sm:text-[2rem]">€ {{ data?.label || '0,00' }}</p>
        <p class="mt-1 text-sm text-emerald-50/75">Betaalde omzet van de geselecteerde dag</p>
      </div>
    </div>
  </section>
</template>

<script setup>
import { BanknotesIcon } from '@heroicons/vue/24/outline'

defineProps({
  data: { type: Object, default: () => ({ visible: false, total: null, label: null }) },
  loading: { type: Boolean, default: false },
})
</script>
