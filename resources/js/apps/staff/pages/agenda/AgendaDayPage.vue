<template>
  <section class="space-y-6">
    <div>
      <div class="mb-3 flex items-center justify-between gap-3">
        <h2 class="text-sm font-semibold uppercase tracking-[0.22em] text-slate-400">Reservaties</h2>
        <span class="rounded-full border border-cyan-400/20 bg-cyan-500/10 px-3 py-1 text-xs font-semibold text-cyan-100">
          {{ reservations.length }}
        </span>
      </div>

      <div class="space-y-3">
        <AgendaReservationCard
          v-for="item in reservations"
          :key="`${item.item_type}-${item.id}-${item.event_date}`"
          :item="item"
        />
        <div v-if="!reservations.length && !store.loading" class="rounded-[28px] border border-dashed border-slate-700 bg-slate-900/40 px-4 py-10 text-center text-sm text-slate-400">
          Geen reservaties voor deze dag.
        </div>
      </div>
    </div>

    <div>
      <div class="mb-3 flex items-center justify-between gap-3">
        <h2 class="text-sm font-semibold uppercase tracking-[0.22em] text-slate-400">Taken</h2>
        <span class="rounded-full border border-fuchsia-400/20 bg-fuchsia-500/10 px-3 py-1 text-xs font-semibold text-fuchsia-100">
          {{ tasks.length }}
        </span>
      </div>

      <div class="space-y-3">
        <AgendaReservationCard
          v-for="item in tasks"
          :key="`${item.item_type}-${item.id}-${item.event_date}`"
          :item="item"
        />
        <div v-if="!tasks.length && !store.loading" class="rounded-[28px] border border-dashed border-slate-700 bg-slate-900/40 px-4 py-10 text-center text-sm text-slate-400">
          Geen taken voor deze dag.
        </div>
      </div>
    </div>
  </section>
</template>

<script setup>
import { computed, onMounted } from 'vue'
import AgendaReservationCard from '../../components/agenda/AgendaReservationCard.vue'
import { useStaffAgendaStore } from '../../stores/agendaStore'

const store = useStaffAgendaStore()

const reservations = computed(() => (store.data.day_registrations || []).filter((item) => item.item_type === 'registration'))
const tasks = computed(() => (store.data.day_registrations || []).filter((item) => item.item_type === 'task'))

onMounted(() => {
  if (!store.data.day_registrations?.length && !store.loading) {
    store.fetchAgenda()
  }
})
</script>
