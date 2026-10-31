<template>
  <section class="rounded-[28px] border border-white/8 bg-slate-950/55 p-5 shadow-[0_18px_45px_rgba(2,6,23,0.28)] backdrop-blur-xl sm:p-6">

    <!-- Header -->
    <div class="flex items-center justify-between mb-5">
      <div class="inline-flex items-center gap-2 rounded-full border border-blue-400/20 bg-blue-400/10 px-3 py-1 text-[11px] font-semibold uppercase tracking-[0.24em] text-blue-200">
        <InboxIcon class="h-4 w-4" />
        Nieuwe reservaties
      </div>
      <span
        v-if="items.length > 0"
        class="flex h-6 w-6 items-center justify-center rounded-full bg-blue-500 text-[11px] font-bold text-white"
      >
        {{ items.length }}
      </span>
    </div>

    <!-- Laden -->
    <div v-if="loading" class="flex justify-center py-8">
      <div class="flex gap-1.5">
        <span class="h-1.5 w-1.5 animate-bounce rounded-full bg-slate-500 [animation-delay:-0.3s]" />
        <span class="h-1.5 w-1.5 animate-bounce rounded-full bg-slate-500 [animation-delay:-0.15s]" />
        <span class="h-1.5 w-1.5 animate-bounce rounded-full bg-slate-500" />
      </div>
    </div>

    <!-- Leeg -->
    <div v-else-if="items.length === 0" class="rounded-[20px] border border-dashed border-white/10 bg-white/[0.03] px-4 py-6 text-sm text-slate-400 text-center">
      Geen openstaande reservaties.
    </div>

    <!-- Lijst -->
    <div v-else class="space-y-3">
      <div
        v-for="r in items"
        :key="r.id"
        class="rounded-[20px] border p-4 transition-colors"
        :class="r.status === 'pending'
          ? 'border-amber-500/20 bg-amber-500/5'
          : 'border-blue-500/15 bg-blue-500/5'"
      >
        <!-- Boven rij: naam + status badge -->
        <div class="flex items-start justify-between gap-3 mb-2">
          <div class="min-w-0">
            <p class="text-sm font-semibold text-white truncate">{{ r.name }}</p>
            <p class="text-xs text-slate-400 mt-0.5">
              <span v-if="r.event_emoji" class="mr-1">{{ r.event_emoji }}</span>
              {{ r.event_type ?? 'Reservatie' }}
              <span v-if="r.stay_option"> · {{ r.stay_option }}</span>
            </p>
          </div>
          <span
            class="shrink-0 inline-flex items-center gap-1 rounded-full px-2 py-0.5 text-[11px] font-medium border"
            :class="r.status === 'pending'
              ? 'bg-amber-500/15 border-amber-500/25 text-amber-300'
              : 'bg-blue-500/15 border-blue-500/25 text-blue-300'"
          >
            <span class="h-1.5 w-1.5 rounded-full" :class="r.status === 'pending' ? 'bg-amber-400' : 'bg-blue-400'" />
            {{ r.status === 'pending' ? 'In behandeling' : 'Nieuw' }}
          </span>
        </div>

        <!-- Datum + personen -->
        <div class="flex items-center gap-3 text-xs text-slate-400 mb-3">
          <span class="flex items-center gap-1">
            <CalendarIcon class="h-3.5 w-3.5" />
            {{ formatDate(r.event_date) }}
            <span v-if="r.event_time">om {{ r.event_time }}</span>
          </span>
          <span class="flex items-center gap-1">
            <UsersIcon class="h-3.5 w-3.5" />
            {{ r.total_count }} personen
          </span>
        </div>

        <!-- Buiten uren badge -->
        <div
          v-if="r.outside_opening_hours"
          class="mb-3 flex items-center gap-1.5 rounded-xl border border-amber-500/20 bg-amber-500/8 px-3 py-1.5"
        >
          <ExclamationTriangleIcon class="h-3.5 w-3.5 shrink-0 text-amber-400" />
          <span class="text-[11px] text-amber-300">Buiten openingsuren aangevraagd</span>
        </div>

        <!-- Opmerking -->
        <p v-if="r.comment" class="mb-3 text-xs text-slate-400 italic leading-relaxed line-clamp-2">
          "{{ r.comment }}"
        </p>

        <!-- Contactgegevens + acties -->
        <div class="flex flex-wrap items-center justify-between gap-3">
          <div class="flex min-w-0 items-center gap-3 text-xs text-slate-500">
            <a v-if="r.phone" :href="`tel:${r.phone}`" class="flex items-center gap-1 hover:text-slate-300 transition-colors">
              <PhoneIcon class="h-3.5 w-3.5" />
              {{ r.phone }}
            </a>
            <a v-if="r.email" :href="`mailto:${r.email}`" class="flex items-center gap-1 hover:text-slate-300 transition-colors truncate">
              <EnvelopeIcon class="h-3.5 w-3.5 shrink-0" />
              <span class="truncate">{{ r.email }}</span>
            </a>
          </div>

          <!-- Inline bevestiging voor weigeren / verwijderen -->
          <div v-if="ask?.id === r.id" class="ml-auto flex shrink-0 items-center gap-2">
            <span class="text-xs text-slate-300">
              {{ ask.action === 'delete' ? 'Definitief verwijderen?' : 'Reservatie weigeren?' }}
            </span>
            <button
              class="rounded-xl border border-white/10 bg-white/5 px-3 py-1.5 text-xs font-semibold text-slate-300 transition-colors hover:bg-white/10"
              :disabled="isBusy(r.id)"
              @click="ask = null"
            >
              Annuleer
            </button>
            <button
              class="inline-flex items-center gap-1.5 rounded-xl border border-rose-500/30 bg-rose-500/15 px-3 py-1.5 text-xs font-semibold text-rose-300 transition-colors hover:bg-rose-500/25 disabled:opacity-50"
              :disabled="isBusy(r.id)"
              @click="runAsk"
            >
              <TrashIcon v-if="ask.action === 'delete'" class="h-3.5 w-3.5" />
              <XMarkIcon v-else class="h-3.5 w-3.5" />
              {{ isBusy(r.id) ? 'Bezig...' : (ask.action === 'delete' ? 'Verwijderen' : 'Weigeren') }}
            </button>
          </div>

          <!-- Standaard acties -->
          <div v-else class="ml-auto flex shrink-0 items-center gap-2">
            <button
              class="inline-flex h-[30px] w-[30px] items-center justify-center rounded-xl border border-white/10 bg-white/5 text-slate-400 transition-colors hover:border-rose-500/30 hover:bg-rose-500/10 hover:text-rose-300 disabled:opacity-50"
              title="Verwijderen (bv. dubbel ingegeven)"
              :disabled="!!busy"
              @click="ask = { id: r.id, action: 'delete' }"
            >
              <TrashIcon class="h-3.5 w-3.5" />
            </button>
            <button
              class="inline-flex items-center gap-1.5 rounded-xl border border-rose-500/25 bg-rose-500/10 px-3 py-1.5 text-xs font-semibold text-rose-300 transition-colors hover:bg-rose-500/20 disabled:opacity-50"
              :disabled="!!busy"
              @click="ask = { id: r.id, action: 'reject' }"
            >
              <XMarkIcon class="h-3.5 w-3.5" />
              Weigeren
            </button>
            <button
              class="inline-flex items-center gap-1.5 rounded-xl border border-emerald-500/30 bg-emerald-500/15 px-3 py-1.5 text-xs font-semibold text-emerald-300 transition-colors hover:bg-emerald-500/25 disabled:opacity-50"
              :disabled="!!busy"
              @click="$emit('confirm', r.id)"
            >
              <CheckIcon class="h-3.5 w-3.5" />
              {{ busy?.id === r.id && busy?.action === 'confirm' ? 'Bezig...' : 'Bevestigen' }}
            </button>
          </div>
        </div>
      </div>
    </div>
  </section>
</template>

<script setup>
import {
  InboxIcon,
  CalendarIcon,
  UsersIcon,
  PhoneIcon,
  EnvelopeIcon,
  CheckIcon,
  ExclamationTriangleIcon,
  XMarkIcon,
  TrashIcon,
} from '@heroicons/vue/24/outline'
import { ref } from 'vue'

const props = defineProps({
  items:   { type: Array,   default: () => [] },
  loading: { type: Boolean, default: false },
  busy:    { type: Object,  default: null }, // { id, action }
})

const emit = defineEmits(['confirm', 'reject', 'delete'])

// Welke kaart vraagt bevestiging, en voor welke actie
const ask = ref(null)

function isBusy(id) {
  return props.busy?.id === id
}

function runAsk() {
  if (!ask.value) return
  const { id, action } = ask.value
  emit(action, id)
  ask.value = null
}

const MONTHS = ['jan','feb','mrt','apr','mei','jun','jul','aug','sep','okt','nov','dec']

function formatDate(s) {
  if (!s) return '—'
  const [y, m, d] = s.split('-').map(Number)
  const date = new Date(y, m - 1, d)
  return `${d} ${MONTHS[m - 1]}`
}
</script>
