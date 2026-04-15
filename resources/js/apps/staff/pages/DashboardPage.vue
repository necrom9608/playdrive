<template>
  <div class="space-y-4 sm:space-y-5">
    <div v-if="store.flash" class="rounded-2xl border border-cyan-400/20 bg-cyan-400/10 px-4 py-3 text-sm text-cyan-100 shadow-[0_18px_35px_rgba(8,47,73,0.28)]">
      {{ store.flash }}
    </div>

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
import { useStaffDashboardStore } from '../stores/dashboardStore'
import StaffDateCard from '../components/dashboard/StaffDateCard.vue'
import StaffReservationsCard from '../components/dashboard/StaffReservationsCard.vue'
import StaffCateringCard from '../components/dashboard/StaffCateringCard.vue'
import StaffRevenueCard from '../components/dashboard/StaffRevenueCard.vue'
import StaffTasksCard from '../components/dashboard/StaffTasksCard.vue'
import StaffSessionsCard from '../components/dashboard/StaffSessionsCard.vue'

const store = useStaffDashboardStore()
onMounted(() => store.fetchDashboard())
</script>
