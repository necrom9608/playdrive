<template>
  <div class="space-y-4 sm:space-y-5">
    <div v-if="store.flash" class="rounded-2xl border border-cyan-400/20 bg-cyan-400/10 px-4 py-3 text-sm text-cyan-100 shadow-[0_18px_35px_rgba(8,47,73,0.28)]">
      {{ store.flash }}
    </div>

    <!-- Inbox — enkel voor admins -->
    <StaffInboxCard
      v-if="auth.user?.is_admin"
      :items="store.inbox"
      :loading="store.inboxLoading"
      :busy="store.inboxBusy"
      @confirm="store.confirmReservation($event)"
      @reject="store.rejectReservation($event)"
      @delete="store.deleteReservation($event)"
    />

    <!-- Toestel koppelen (QR-code op Playscope/kiosk) — enkel voor admins -->
    <RouterLink
      v-if="auth.user?.is_admin"
      to="/koppel"
      class="flex items-center gap-4 rounded-[28px] border border-white/8 bg-slate-950/55 p-5 shadow-[0_18px_45px_rgba(2,6,23,0.28)] backdrop-blur-xl sm:p-6"
    >
      <span class="flex h-11 w-11 shrink-0 items-center justify-center rounded-2xl bg-cyan-400/10 text-cyan-200">
        <QrCodeIcon class="h-6 w-6" />
      </span>
      <span class="min-w-0 flex-1">
        <span class="block font-semibold text-white">Toestel koppelen</span>
        <span class="block text-sm text-slate-400">Scan de QR-code op het scherm van een nieuwe pc of kiosk</span>
      </span>
      <ChevronRightIcon class="h-5 w-5 text-slate-500" />
    </RouterLink>

    <StaffDateCard
      :selected-date="store.selectedDate"
      :selected-date-label="store.data.selected_date_label"
      :is-today="store.data.is_today"
      :loading="store.loading"
      @previous="store.shiftDate(-1)"
      @next="store.shiftDate(1)"
      @today="store.goToToday()"
      @select="store.setDate($event)"
    />

    <StaffRosterCard :items="store.data.my_shifts?.items || []" :loading="store.loading" />

    <div class="grid gap-4 xl:grid-cols-[minmax(0,1.25fr)_minmax(0,0.75fr)]">
      <StaffReservationsCard :data="store.data.reservations" :loading="store.loading" />
      <StaffCateringCard :data="store.data.catering" :loading="store.loading" />
    </div>

    <StaffRevenueCard v-if="store.data.revenue?.visible" :data="store.data.revenue" :loading="store.loading" />

    <div class="grid gap-4 xl:grid-cols-[minmax(0,1.1fr)_minmax(0,0.9fr)]">
      <StaffTasksCard :data="store.data.tasks" :loading="store.loading" />
      <StaffSessionsCard :items="store.data.sessions_for_day" :worked-label="store.data.attendance?.worked_time_for_day_label" :loading="store.loading" />
    </div>
  </div>
</template>

<script setup>
import { onMounted } from 'vue'
import { ChevronRightIcon, QrCodeIcon } from '@heroicons/vue/24/outline'
import { useStaffDashboardStore } from '../stores/dashboardStore'
import { useStaffAuthStore } from '../stores/authStore'
import StaffDateCard from '../components/dashboard/StaffDateCard.vue'
import StaffReservationsCard from '../components/dashboard/StaffReservationsCard.vue'
import StaffCateringCard from '../components/dashboard/StaffCateringCard.vue'
import StaffRevenueCard from '../components/dashboard/StaffRevenueCard.vue'
import StaffTasksCard from '../components/dashboard/StaffTasksCard.vue'
import StaffSessionsCard from '../components/dashboard/StaffSessionsCard.vue'
import StaffInboxCard from '../components/dashboard/StaffInboxCard.vue'
import StaffRosterCard from '../components/dashboard/StaffRosterCard.vue'

const store = useStaffDashboardStore()
const auth  = useStaffAuthStore()

onMounted(() => {
  store.fetchDashboard()
  if (auth.user?.is_admin) {
    store.fetchInbox()
  }
})
</script>
