<template>
  <div class="space-y-4">
    <div v-if="store.loading" class="rounded-[28px] border border-slate-800/80 bg-slate-900/70 px-4 py-10 text-center text-sm text-slate-400 shadow-[0_20px_60px_-28px_rgba(15,23,42,0.9)]">
      Agenda laden...
    </div>

    <router-view v-else />
  </div>
</template>

<script setup>
import { onMounted, watch } from 'vue'
import { useRoute } from 'vue-router'
import { useStaffAgendaStore } from '../stores/agendaStore'

const store = useStaffAgendaStore()
const route = useRoute()

function syncViewFromRoute() {
  const view = route.name === 'staff.agenda.week'
    ? 'week'
    : route.name === 'staff.agenda.month'
      ? 'month'
      : 'day'

  if (store.view !== view) {
    store.view = view
  }
}

watch(() => route.name, async () => {
  syncViewFromRoute()
  await store.fetchAgenda()
})

onMounted(async () => {
  syncViewFromRoute()
  await store.fetchAgenda()
})
</script>
