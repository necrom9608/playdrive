<template>
  <div class="relative min-h-screen overflow-hidden bg-[#020617] text-slate-100">
    <div class="pointer-events-none absolute inset-0">
      <div class="absolute inset-0 bg-[radial-gradient(circle_at_top_left,rgba(59,130,246,0.16),transparent_28%),radial-gradient(circle_at_80%_18%,rgba(168,85,247,0.15),transparent_24%),linear-gradient(180deg,#020617_0%,#071126_45%,#020617_100%)]"></div>
      <div class="absolute -left-20 top-0 h-72 w-72 rounded-full bg-[rgba(59,130,246,0.16)] blur-3xl splash-drift-one"></div>
      <div class="absolute right-0 top-24 h-72 w-72 rounded-full bg-[rgba(168,85,247,0.14)] blur-3xl splash-drift-two"></div>
      <div class="absolute bottom-10 left-[18%] h-56 w-56 rounded-full bg-[rgba(56,189,248,0.1)] blur-3xl splash-drift-three"></div>
    </div>

    <header class="fixed inset-x-0 top-0 z-30 border-b border-white/8 bg-slate-950/78 px-4 py-3 backdrop-blur-2xl">
      <div class="mx-auto flex max-w-6xl items-center justify-between gap-4">
        <div class="min-w-0 flex flex-1 items-center gap-3 sm:gap-4">
          <img
            v-if="tenantLogoUrl"
            :src="tenantLogoUrl"
            :alt="tenantLabel"
            class="h-10 max-w-[132px] shrink-0 object-contain object-left sm:h-11 sm:max-w-[165px]"
          >
          <div v-else class="shrink-0 text-sm font-semibold tracking-[0.22em] text-white uppercase">{{ tenantInitials }}</div>

          <template v-if="isDashboardRoute">
            <div class="min-w-0">
              <h1 class="truncate text-xl font-semibold text-white sm:text-2xl">{{ auth.user?.name || 'Staff' }}</h1>
              <p class="mt-0.5 truncate text-xs text-slate-300 sm:text-sm">
                <template v-if="dashboard.attendance?.is_checked_in">
                  Ingecheckt sinds <span class="font-semibold text-white">{{ dashboard.attendance.checked_in_at_label }}</span>
                  <span class="mx-1 text-slate-500">•</span>
                  <span>{{ dashboard.attendance.current_duration_label }} gewerkt</span>
                </template>
                <template v-else>
                  Momenteel niet ingecheckt.
                </template>
              </p>
            </div>
          </template>
        </div>

        <div v-if="isDashboardRoute" class="shrink-0">
          <button
            type="button"
            :disabled="dashboardStore.saving"
            class="inline-flex min-w-[148px] items-center justify-center gap-2.5 rounded-2xl border px-4 py-3 text-sm font-semibold shadow-[0_18px_35px_rgba(2,6,23,0.28)] transition hover:scale-[1.01] disabled:cursor-not-allowed disabled:opacity-70"
            :class="dashboard.attendance?.is_checked_in
              ? 'border-rose-300/20 bg-rose-500 text-white hover:bg-rose-400'
              : 'border-emerald-300/20 bg-emerald-400 text-slate-950 hover:bg-emerald-300'"
            @click="dashboardStore.toggleAttendance()"
          >
            <component :is="dashboard.attendance?.is_checked_in ? ArrowLeftOnRectangleIcon : ArrowRightOnRectangleIcon" class="h-5 w-5" />
            <span>{{ dashboard.attendance?.is_checked_in ? 'Uitchecken' : 'Inchecken' }}</span>
          </button>
        </div>

        <div v-else class="flex min-w-0 items-center gap-2.5 text-sm text-slate-300">
          <span class="inline-flex h-2.5 w-2.5 rounded-full bg-emerald-400 shadow-[0_0_14px_rgba(52,211,153,0.85)]"></span>
          <span class="truncate">{{ auth.user?.name }}</span>
        </div>
      </div>
    </header>

    <main class="relative z-10 mx-auto max-w-6xl px-4 pb-28 pt-[5.9rem] sm:pt-[6.35rem]">
      <router-view />
    </main>

    <nav class="fixed inset-x-0 bottom-0 z-30 border-t border-white/8 bg-slate-950/78 px-3 pb-[calc(env(safe-area-inset-bottom)+0.75rem)] pt-2.5 backdrop-blur-2xl">
      <div class="mx-auto grid max-w-6xl grid-cols-4 gap-2">
        <RouterLink
          v-for="item in items"
          :key="item.to"
          :to="item.to"
          class="rounded-2xl border px-3 py-2.5 text-center text-[11px] font-semibold transition"
          :class="$route.path === item.to
            ? 'border-cyan-300/25 bg-cyan-400 text-slate-950 shadow-[0_12px_26px_rgba(34,211,238,0.22)]'
            : 'border-white/8 bg-white/[0.04] text-slate-300 hover:bg-white/[0.07]'"
        >
          <component :is="item.icon" class="mx-auto mb-1 h-[18px] w-[18px]" />
          <span>{{ item.label }}</span>
        </RouterLink>
      </div>
    </nav>
  </div>
</template>

<script setup>
import { computed } from 'vue'
import { RouterLink, useRoute } from 'vue-router'
import {
  HomeIcon,
  CalendarDaysIcon,
  ClipboardDocumentListIcon,
  Cog6ToothIcon,
  ArrowLeftOnRectangleIcon,
  ArrowRightOnRectangleIcon,
} from '@heroicons/vue/24/outline'
import { useStaffAuthStore } from '../stores/authStore'
import { useStaffDashboardStore } from '../stores/dashboardStore'

const route = useRoute()
const auth = useStaffAuthStore()
const dashboardStore = useStaffDashboardStore()
const items = [
  { to: '/', label: 'Dashboard', icon: HomeIcon },
  { to: '/agenda', label: 'Agenda', icon: CalendarDaysIcon },
  { to: '/tasks', label: 'Taken', icon: ClipboardDocumentListIcon },
  { to: '/settings', label: 'Settings', icon: Cog6ToothIcon },
]
const tenantLabel = computed(() => window.PlayDrive?.tenantName || 'PlayDrive')
const tenantLogoUrl = computed(() => window.PlayDrive?.tenantLogoUrl || '')
const tenantInitials = computed(() => String(tenantLabel.value || 'PD').split(/\s+/).slice(0, 2).map(part => part.charAt(0)).join('').toUpperCase())
const isDashboardRoute = computed(() => route.path === '/')
const dashboard = computed(() => dashboardStore.data || {})
</script>

<style scoped>
.splash-drift-one {
  animation: splash-drift-one 7s ease-in-out infinite alternate;
}

.splash-drift-two {
  animation: splash-drift-two 8s ease-in-out infinite alternate;
}

.splash-drift-three {
  animation: splash-drift-three 9s ease-in-out infinite alternate;
}

@keyframes splash-drift-one {
  0% { transform: translate3d(0, 0, 0) scale(1); }
  100% { transform: translate3d(26px, 20px, 0) scale(1.08); }
}

@keyframes splash-drift-two {
  0% { transform: translate3d(0, 0, 0) scale(1); }
  100% { transform: translate3d(-30px, 26px, 0) scale(1.12); }
}

@keyframes splash-drift-three {
  0% { transform: translate3d(0, 0, 0) scale(1); }
  100% { transform: translate3d(18px, -18px, 0) scale(1.1); }
}
</style>
