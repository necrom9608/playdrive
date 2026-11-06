<template>
  <div class="space-y-4 sm:space-y-5">
    <section class="rounded-[28px] border border-white/8 bg-slate-950/55 p-5 shadow-[0_18px_45px_rgba(2,6,23,0.28)] backdrop-blur-xl sm:p-6">
      <div class="inline-flex items-center gap-2 rounded-full border border-cyan-400/20 bg-cyan-400/10 px-3 py-1 text-[11px] font-semibold uppercase tracking-[0.24em] text-cyan-200">
        <QrCodeIcon class="h-4 w-4" />
        Toestel koppelen
      </div>

      <!-- Geen admin -->
      <p v-if="!isAdmin" class="mt-4 rounded-[20px] border border-dashed border-white/10 bg-white/[0.03] px-4 py-6 text-center text-sm text-slate-400">
        Enkel beheerders kunnen toestellen koppelen.
      </p>

      <!-- Gekoppeld -->
      <div v-else-if="done" class="mt-5 space-y-4 text-center">
        <div class="mx-auto flex h-16 w-16 items-center justify-center rounded-full bg-emerald-500/15 text-emerald-300">
          <CheckIcon class="h-9 w-9" />
        </div>
        <div>
          <p class="text-lg font-semibold text-white">{{ done.name }}</p>
          <p class="text-sm text-slate-400">
            gekoppeld{{ done.setup_name ? ` aan ${done.setup_name}` : '' }} · het toestel bevestigt het binnen enkele seconden
          </p>
        </div>
        <button type="button" class="w-full rounded-2xl bg-cyan-500 px-4 py-3 text-sm font-semibold text-slate-950" @click="reset">
          Nog een toestel koppelen
        </button>
      </div>

      <template v-else>
        <!-- Stap 1: code -->
        <div v-if="!found" class="mt-5 space-y-4">
          <p class="text-sm text-slate-300">
            Scan de QR-code op het scherm van Playscope of de kiosk, of geef de code van 6 tekens in.
          </p>
          <button
            type="button"
            class="flex w-full items-center justify-center gap-2 rounded-2xl bg-cyan-500 px-4 py-3.5 text-sm font-semibold text-slate-950"
            @click="scanOpen = true"
          >
            <CameraIcon class="h-5 w-5" />
            QR-code scannen
          </button>
          <div class="flex gap-2">
            <input
              v-model="code"
              type="text"
              maxlength="7"
              autocomplete="off"
              autocapitalize="characters"
              placeholder="ABC123"
              class="min-w-0 flex-1 rounded-2xl border border-white/10 bg-white/[0.04] px-4 py-3 text-center font-mono text-xl uppercase tracking-[0.35em] text-white placeholder:text-slate-600 focus:border-cyan-400/50 focus:outline-none"
              @keydown.enter.prevent="lookup()"
            />
            <button
              type="button"
              class="rounded-2xl border border-white/10 bg-white/[0.06] px-4 text-sm font-semibold text-white disabled:opacity-40"
              :disabled="normalized.length < 6 || busy"
              @click="lookup()"
            >
              Verder
            </button>
          </div>
        </div>

        <!-- Stap 2: naam en speelplek -->
        <div v-else class="mt-5 space-y-4">
          <div class="rounded-[20px] border border-white/8 bg-white/[0.04] px-4 py-3">
            <p class="text-xs uppercase tracking-[0.2em] text-slate-500">{{ found.type_label }}</p>
            <p class="mt-1 font-mono text-lg tracking-[0.3em] text-white">{{ found.code }}</p>
            <p class="mt-1 text-sm text-slate-400">
              {{ [found.hostname, found.platform, found.app_version && `v${found.app_version}`].filter(Boolean).join(' · ') || 'Onbekend toestel' }}
            </p>
          </div>

          <label class="block space-y-1">
            <span class="block text-sm font-medium text-slate-300">Naam *</span>
            <input
              v-model="name"
              type="text"
              placeholder="bijv. LAN-PC 01"
              class="w-full rounded-2xl border border-white/10 bg-white/[0.04] px-4 py-3 text-white placeholder:text-slate-600 focus:border-cyan-400/50 focus:outline-none"
            />
          </label>

          <label class="block space-y-1">
            <span class="block text-sm font-medium text-slate-300">Speelplek</span>
            <select
              v-model="setupId"
              class="w-full rounded-2xl border border-white/10 bg-slate-900 px-4 py-3 text-white focus:border-cyan-400/50 focus:outline-none"
            >
              <option :value="null">Geen (bv. kioskscherm)</option>
              <option v-for="s in setups" :key="s.id" :value="s.id">{{ s.name }}{{ s.zone ? ` · ${s.zone}` : '' }}</option>
            </select>
          </label>

          <div class="flex gap-2">
            <button type="button" class="rounded-2xl border border-white/10 bg-white/[0.06] px-4 py-3 text-sm font-semibold text-white" @click="reset">
              Terug
            </button>
            <button
              type="button"
              class="flex-1 rounded-2xl bg-cyan-500 px-4 py-3 text-sm font-semibold text-slate-950 disabled:opacity-40"
              :disabled="!name.trim() || busy"
              @click="claim"
            >
              {{ busy ? 'Bezig…' : 'Koppelen' }}
            </button>
          </div>
        </div>

        <p v-if="error" class="mt-4 rounded-2xl border border-red-800 bg-red-950/40 px-4 py-3 text-sm text-red-300">{{ error }}</p>
      </template>
    </section>

    <ScanQrModal
      v-model:open="scanOpen"
      title="Scan de QR-code"
      description="Richt de camera op de QR-code op het scherm van het toestel."
      @confirmed="onScanned"
    />
  </div>
</template>

<script setup>
// Toestel (Playscope, kiosk) koppelen door de QR-code op zijn scherm te scannen.
// De QR bevat <PlayDrive>/staff/koppel/<code>: met de gewone camera opent hij meteen deze pagina
// met de code ingevuld. API: /api/staff/devices/* (StaffDeviceController, enkel admins).
import { computed, onMounted, ref, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { CameraIcon, CheckIcon, QrCodeIcon } from '@heroicons/vue/24/outline'
import { apiFetch } from '../../../shared/services/api'
import ScanQrModal from '../../../shared/components/scanners/ScanQrModal.vue'
import { useStaffAuthStore } from '../stores/authStore'

const route = useRoute()
const router = useRouter()
const auth = useStaffAuthStore()
const isAdmin = computed(() => Boolean(auth.user?.is_admin))

const code = ref('')
const found = ref(null)
const name = ref('')
const setupId = ref(null)
const setups = ref([])
const busy = ref(false)
const error = ref('')
const done = ref(null)
const scanOpen = ref(false)

const normalized = computed(() => code.value.toUpperCase().replace(/[^A-Z0-9]/g, ''))

/** Gescande tekst → code: de URL van de QR (…/koppel/ABC123) of de code zelf. */
function codeFrom(text) {
  const value = String(text || '').trim()
  const m = value.match(/\/koppel\/([A-Za-z0-9]{4,12})/)
  return (m ? m[1] : value).toUpperCase().replace(/[^A-Z0-9]/g, '')
}

function onScanned(text) {
  code.value = codeFrom(text)
  lookup()
}

async function lookup() {
  if (normalized.value.length < 6 || busy.value) return
  busy.value = true
  error.value = ''
  try {
    found.value = await apiFetch(`/api/staff/devices/pairing/${encodeURIComponent(normalized.value)}`)
    name.value = found.value.hostname || ''
    if (!setups.value.length) {
      const res = await apiFetch('/api/staff/devices/setups')
      setups.value = res.setups ?? []
    }
  } catch (e) {
    found.value = null
    error.value = e.message || 'Code niet gevonden.'
  } finally {
    busy.value = false
  }
}

async function claim() {
  if (!found.value || !name.value.trim() || busy.value) return
  busy.value = true
  error.value = ''
  try {
    done.value = await apiFetch('/api/staff/devices/claim', {
      method: 'POST',
      body: JSON.stringify({ pairing_code: found.value.code, name: name.value.trim(), setup_id: setupId.value }),
    })
    if (route.params.code) router.replace({ name: 'staff.pair' })
  } catch (e) {
    error.value = e.message || 'Koppelen mislukt.'
  } finally {
    busy.value = false
  }
}

function reset() {
  code.value = ''
  found.value = null
  name.value = ''
  setupId.value = null
  error.value = ''
  done.value = null
  if (route.params.code) router.replace({ name: 'staff.pair' })
}

// Geopend via de QR-code met de gewone camera: code staat in de URL.
function fromRoute() {
  const c = codeFrom(route.params.code)
  if (c && isAdmin.value && !found.value && !done.value) {
    code.value = c
    lookup()
  }
}

onMounted(fromRoute)
watch(() => route.params.code, fromRoute)
watch(isAdmin, fromRoute)
</script>
