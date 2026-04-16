<template>
  <div class="relative h-screen overflow-hidden bg-[#020617] text-slate-100">
    <div class="pointer-events-none absolute inset-0">
      <div class="absolute inset-0 bg-[radial-gradient(circle_at_top_left,rgba(59,130,246,0.16),transparent_28%),radial-gradient(circle_at_80%_18%,rgba(168,85,247,0.15),transparent_24%),linear-gradient(180deg,#020617_0%,#071126_45%,#020617_100%)]"></div>
      <div class="absolute -left-20 top-0 h-72 w-72 rounded-full bg-[rgba(59,130,246,0.16)] blur-3xl splash-drift-one"></div>
      <div class="absolute right-0 top-24 h-72 w-72 rounded-full bg-[rgba(168,85,247,0.14)] blur-3xl splash-drift-two"></div>
      <div class="absolute bottom-10 left-[18%] h-56 w-56 rounded-full bg-[rgba(56,189,248,0.1)] blur-3xl splash-drift-three"></div>
    </div>

    <header class="fixed inset-x-0 top-0 z-30 border-b border-white/8 bg-slate-950/78 px-4 py-3 backdrop-blur-2xl">
      <div class="mx-auto max-w-6xl" :class="isAgendaRoute ? 'space-y-3' : ''">
        <div class="flex items-center justify-between gap-4">
          <div class="min-w-0 flex flex-1 items-center gap-3 sm:gap-4">
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

            <template v-else>
              <div v-if="!isAgendaRoute" class="min-w-0">
                <h1 class="truncate text-lg font-semibold text-white sm:text-xl">{{ currentPageTitle }}</h1>
              </div>
            </template>
          </div>

          <div v-if="isDashboardRoute" class="shrink-0">
            <button
              type="button"
              :disabled="dashboardStore.saving"
              class="inline-flex items-center gap-2 rounded-2xl border px-4 py-3 text-sm font-semibold shadow-[0_14px_34px_rgba(15,23,42,0.22)] transition sm:px-5"
              :class="dashboard.attendance?.is_checked_in
                ? 'border-rose-400/30 bg-rose-500/90 text-white hover:bg-rose-400 disabled:opacity-70'
                : 'border-emerald-400/30 bg-emerald-500/90 text-white hover:bg-emerald-400 disabled:opacity-70'"
              @click="toggleAttendance"
            >
              <ArrowRightOnRectangleIcon class="h-5 w-5" />
              {{ dashboard.attendance?.is_checked_in ? 'Uitchecken' : 'Inchecken' }}
            </button>
          </div>
        </div>

        <AgendaFilters
          v-if="isAgendaRoute"
          v-model="agendaPickerValue"
          :expanded="agendaFiltersExpanded"
          :range-label="agendaStore.data.range?.label"
          :input-type="agendaPickerType"
          @change="handleAgendaPickerChange"
          @previous="navigateAgenda(-1)"
          @next="navigateAgenda(1)"
          @today="goAgendaToday"
          @toggle-expanded="agendaFiltersExpanded = !agendaFiltersExpanded"
        />
      </div>
    </header>

    <main class="relative z-10 mx-auto w-full max-w-6xl overflow-y-auto px-4 pb-28 sm:px-5" :class="mainTopPaddingClass" style="height: 100vh; height: 100dvh;">
      <RouterView />
    </main>

    <nav class="fixed inset-x-0 bottom-0 z-30 border-t border-white/8 bg-slate-950/80 px-3 py-2 backdrop-blur-2xl">
      <div class="mx-auto grid max-w-6xl grid-cols-4 gap-2">
        <RouterLink
          v-for="item in navItems"
          :key="item.to"
          :to="item.to"
          class="group flex min-h-[64px] flex-col items-center justify-center rounded-2xl border px-3 py-2 text-[11px] font-semibold transition"
          :class="isActive(item)
            ? 'border-cyan-400/30 bg-cyan-500/15 text-cyan-100 shadow-[0_10px_28px_rgba(6,182,212,0.18)]'
            : 'border-transparent bg-white/[0.03] text-slate-400 hover:border-white/10 hover:bg-white/[0.06] hover:text-slate-200'"
        >
          <component :is="item.icon" class="mb-1 h-5 w-5" />
          <span>{{ item.label }}</span>
        </RouterLink>
      </div>
    </nav>
  </div>
</template>

<script setup>
import { computed, ref } from 'vue'
import { RouterLink, RouterView, useRoute } from 'vue-router'
import {
  ArrowRightOnRectangleIcon,
  CalendarDaysIcon,
  ClipboardDocumentListIcon,
  Cog6ToothIcon,
  Squares2X2Icon,
} from '@heroicons/vue/24/outline'
import AgendaFilters from '../components/agenda/AgendaFilters.vue'
import { useStaffAuthStore } from '../stores/authStore'
import { useStaffDashboardStore } from '../stores/dashboardStore'
import { useStaffAgendaStore } from '../stores/agendaStore'

const auth = useStaffAuthStore()
const dashboardStore = useStaffDashboardStore()
const agendaStore = useStaffAgendaStore()
const route = useRoute()

const navItems = [
  { to: '/', label: 'Dashboard', icon: Squares2X2Icon, match: '/' },
  { to: '/agenda/day', label: 'Agenda', icon: CalendarDaysIcon, match: '/agenda' },
  { to: '/tasks', label: 'Taken', icon: ClipboardDocumentListIcon, match: '/tasks' },
  { to: '/settings', label: 'Settings', icon: Cog6ToothIcon, match: '/settings' },
]

const isDashboardRoute = computed(() => route.path === '/')
const isAgendaRoute = computed(() => route.path.startsWith('/agenda'))
const dashboard = computed(() => dashboardStore.data || {})

const currentPageTitle = computed(() => {
  if (route.path.startsWith('/tasks')) return 'Taken'
  if (route.path.startsWith('/settings')) return 'Settings'
  return 'Staff'
})

const mainTopPaddingClass = computed(() => {
  if (isAgendaRoute.value) return agendaFiltersExpanded.value ? 'pt-52 sm:pt-56' : 'pt-36 sm:pt-40'
  return 'pt-24 sm:pt-24'
})

const agendaPickerType = computed(() => {
  if (agendaStore.view === 'month') return 'month'
  if (agendaStore.view === 'week') return 'week'
  return 'date'
})

const agendaPickerValue = computed({
  get() {
    if (agendaStore.view === 'month') return (agendaStore.date || '').slice(0, 7)
    if (agendaStore.view === 'week') return toWeekInputValue(agendaStore.date)
    return agendaStore.date
  },
  set(value) {
    if (!value) return
    if (agendaStore.view === 'month') {
      agendaStore.date = `${value}-01`
      return
    }
    if (agendaStore.view === 'week') {
      agendaStore.date = fromWeekInputValue(value)
      return
    }
    agendaStore.date = value
  },
})

function isActive(item) {
  if (item.match === '/') return route.path === '/'
  return route.path.startsWith(item.match)
}

async function toggleAttendance() {
  if (dashboardStore.saving) return
  await dashboardStore.toggleAttendance()
}

function handleAgendaPickerChange() {
  agendaStore.fetchAgenda()
}

function goAgendaToday() {
  agendaStore.setToday()
  agendaStore.fetchAgenda()
}

function navigateAgenda(direction) {
  agendaStore.shiftRange(direction)
  agendaStore.fetchAgenda()
}

function toWeekInputValue(dateString) {
  const date = parseLocalDate(dateString)
  const day = (date.getDay() + 6) % 7
  date.setDate(date.getDate() - day + 3)
  const firstThursday = new Date(date.getFullYear(), 0, 4)
  const diff = date.getTime() - firstThursday.getTime()
  const week = 1 + Math.round(diff / 604800000)
  return `${date.getFullYear()}-W${String(week).padStart(2, '0')}`
}

function fromWeekInputValue(value) {
  const [year, weekString] = value.split('-W')
  const week = Number(weekString)
  const simple = new Date(Number(year), 0, 1 + (week - 1) * 7)
  const day = simple.getDay()
  const monday = new Date(simple)

  if (day <= 4 && day !== 0) {
    monday.setDate(simple.getDate() - day + 1)
  } else if (day === 0) {
    monday.setDate(simple.getDate() - 6)
  } else {
    monday.setDate(simple.getDate() + 8 - day)
  }

  return formatLocalDate(monday)
}

function parseLocalDate(dateString) {
  const [year, month, day] = (dateString || '').split('-').map(Number)
  return new Date(year, (month || 1) - 1, day || 1)
}

function formatLocalDate(date) {
  const year = date.getFullYear()
  const month = String(date.getMonth() + 1).padStart(2, '0')
  const day = String(date.getDate()).padStart(2, '0')
  return `${year}-${month}-${day}`
}

const agendaFiltersExpanded = ref(false)
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
  100% { transform: translate3d(-24px, 18px, 0) scale(1.07); }
}

@keyframes splash-drift-three {
  0% { transform: translate3d(0, 0, 0) scale(1); }
  100% { transform: translate3d(16px, -16px, 0) scale(1.06); }
}
</style>
