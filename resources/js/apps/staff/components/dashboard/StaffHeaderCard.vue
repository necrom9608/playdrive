<template>
  <section class="px-1 pt-1">
    <div class="flex flex-col gap-4 lg:flex-row lg:items-start lg:justify-between">
      <div class="min-w-0 flex-1">
        <div class="flex items-start justify-between gap-4">
          <div class="min-w-0">
            <p class="text-xs font-semibold uppercase tracking-[0.28em] text-cyan-200/80">Staff</p>
            <h1 class="mt-2 text-2xl font-semibold text-white sm:text-3xl">Hallo{{ userName ? `, ${firstName}` : '' }}</h1>
            <p class="mt-3 text-sm text-slate-300 sm:text-base">
              <template v-if="attendance?.is_checked_in">
                Ingecheckt sinds <span class="font-semibold text-white">{{ attendance.checked_in_at_label }}</span>
                · Huidige duur <span class="font-semibold text-white">{{ attendance.current_duration_label }}</span>
              </template>
              <template v-else>
                Momenteel niet ingecheckt.
              </template>
            </p>
          </div>

          <img
            v-if="tenantLogoUrl"
            :src="tenantLogoUrl"
            :alt="tenantLabel"
            class="h-12 max-w-[140px] shrink-0 object-contain object-right sm:h-14 sm:max-w-[180px]"
          >
          <div v-else class="shrink-0 text-sm font-semibold uppercase tracking-[0.26em] text-slate-300">{{ tenantInitials }}</div>
        </div>
      </div>

      <div class="flex w-full justify-end lg:w-auto lg:pt-7">
        <button
          type="button"
          :disabled="saving"
          class="inline-flex w-full items-center justify-center gap-3 rounded-[22px] border px-5 py-4 text-sm font-semibold shadow-lg transition disabled:cursor-not-allowed disabled:opacity-70 sm:w-auto"
          :class="attendance?.is_checked_in
            ? 'border-rose-300/20 bg-rose-500 text-white shadow-rose-950/40'
            : 'border-emerald-300/20 bg-emerald-400 text-slate-950 shadow-emerald-950/40'"
          @click="$emit('toggle-attendance')"
        >
          <component :is="attendance?.is_checked_in ? ArrowLeftOnRectangleIcon : ArrowRightOnRectangleIcon" class="h-5 w-5" />
          <span>{{ attendance?.is_checked_in ? 'Uitchecken' : 'Inchecken' }}</span>
        </button>
      </div>
    </div>
  </section>
</template>

<script setup>
import { computed } from 'vue'
import { ArrowLeftOnRectangleIcon, ArrowRightOnRectangleIcon } from '@heroicons/vue/24/outline'

const props = defineProps({
  tenantLabel: { type: String, default: 'PlayDrive' },
  tenantLogoUrl: { type: String, default: '' },
  userName: { type: String, default: '' },
  attendance: { type: Object, default: () => ({}) },
  saving: { type: Boolean, default: false },
})

defineEmits(['toggle-attendance'])

const firstName = computed(() => String(props.userName || '').trim().split(' ')[0] || '')
const tenantInitials = computed(() => String(props.tenantLabel || 'PD').split(/\s+/).slice(0, 2).map(part => part.charAt(0)).join('').toUpperCase())
</script>
