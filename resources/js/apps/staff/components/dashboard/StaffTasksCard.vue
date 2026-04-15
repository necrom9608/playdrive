<template>
  <section class="rounded-[28px] border border-white/8 bg-slate-950/55 p-5 shadow-[0_18px_45px_rgba(2,6,23,0.28)] backdrop-blur-xl sm:p-6">
    <div class="flex flex-wrap items-start justify-between gap-3">
      <div>
        <div class="inline-flex items-center gap-2 rounded-full border border-cyan-400/20 bg-cyan-400/10 px-3 py-1 text-[11px] font-semibold uppercase tracking-[0.24em] text-cyan-200">
          <ClipboardDocumentListIcon class="h-4 w-4" />
          Taken
        </div>
        <div class="mt-4 flex flex-wrap items-end gap-x-3 gap-y-1">
          <p class="text-4xl font-semibold leading-none text-white">{{ data?.open_count ?? 0 }}</p>
          <p class="pb-1 text-sm text-slate-400">open taken</p>
        </div>
        <p class="mt-1 text-sm text-slate-400"><span class="text-rose-300">{{ data?.overdue_count ?? 0 }} te laat</span></p>
      </div>
      <RouterLink to="/tasks" class="inline-flex items-center gap-2 rounded-2xl border border-white/8 bg-white/[0.04] px-4 py-3 text-sm font-semibold text-slate-100 transition hover:bg-white/[0.07]">
        <EyeIcon class="h-5 w-5" />
        Alles bekijken
      </RouterLink>
    </div>

    <div v-if="items.length" class="mt-5 space-y-3">
      <article v-for="task in items" :key="task.id" class="rounded-[24px] border border-white/8 bg-white/[0.04] px-4 py-3.5">
        <div class="flex items-start justify-between gap-3">
          <div>
            <div class="font-semibold text-white">{{ task.title }}</div>
            <div class="mt-1.5 text-sm text-slate-400">{{ task.description || 'Geen omschrijving' }}</div>
            <div class="mt-3 flex flex-wrap items-center gap-2 text-xs text-slate-300">
              <span class="inline-flex items-center gap-1 rounded-full border border-white/8 bg-slate-950/65 px-2.5 py-1">
                <CalendarIcon class="h-4 w-4" />
                {{ task.due_date_label || task.start_date_label || 'Geen datum' }}
              </span>
              <span class="inline-flex items-center gap-1 rounded-full border border-white/8 bg-slate-950/65 px-2.5 py-1">
                <UserIcon class="h-4 w-4" />
                {{ task.assigned_to_me ? 'Mijn taak' : (task.assigned_user_name || 'Algemene taak') }}
              </span>
              <span v-if="task.is_overdue" class="inline-flex items-center gap-1 rounded-full border border-rose-400/25 bg-rose-500/10 px-2.5 py-1 text-rose-200">
                <ExclamationTriangleIcon class="h-4 w-4" />
                Te laat
              </span>
            </div>
          </div>
        </div>
      </article>
    </div>

    <div v-else class="mt-5 rounded-[24px] border border-dashed border-white/10 bg-white/[0.03] px-4 py-7 text-center text-sm text-slate-400">
      Geen open taken voor deze dag.
    </div>
  </section>
</template>

<script setup>
import { computed } from 'vue'
import { RouterLink } from 'vue-router'
import {
  ClipboardDocumentListIcon,
  EyeIcon,
  CalendarIcon,
  UserIcon,
  ExclamationTriangleIcon,
} from '@heroicons/vue/24/outline'

const props = defineProps({
  data: { type: Object, default: () => ({ open_count: 0, overdue_count: 0, items: [] }) },
  loading: { type: Boolean, default: false },
})

const items = computed(() => props.data?.items ?? [])
</script>
