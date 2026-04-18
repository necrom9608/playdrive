<template>
  <Teleport to="body">
    <div
      v-if="open"
      class="fixed inset-0 z-[180] flex items-center justify-center bg-slate-950/85 p-4"
      @click.self="close"
    >
      <div class="w-full max-w-md overflow-hidden rounded-3xl border border-slate-800 bg-slate-900 shadow-2xl">
        <div class="flex items-start justify-between gap-4 border-b border-slate-800 px-6 py-5">
          <div>
            <h3 class="text-lg font-semibold text-white">{{ title }}</h3>
            <p class="mt-1 text-sm text-slate-400">{{ descriptionText }}</p>
          </div>

          <button
            type="button"
            class="rounded-xl border border-slate-700 bg-slate-800 px-3 py-2 text-sm font-semibold text-slate-200 transition hover:bg-slate-700"
            @click="close"
          >
            Sluiten
          </button>
        </div>

        <div class="space-y-4 px-6 py-6">
          <div class="rounded-2xl border border-blue-500/30 bg-blue-500/10 px-4 py-3 text-sm text-blue-100">
            Houd de RFID-tag bij de lezer. De scan wordt rechtstreeks via de desktop-app gelezen.
          </div>

          <div
            v-if="isScanning"
            class="rounded-2xl border border-cyan-500/20 bg-cyan-500/5 px-4 py-3 text-sm text-cyan-100"
          >
            Scanner actief… wacht op badge.
          </div>

          <div
            v-else-if="scanError"
            class="rounded-2xl border border-amber-500/20 bg-amber-500/10 px-4 py-3 text-sm text-amber-100"
          >
            {{ scanError }}
          </div>

          <label class="block text-sm text-slate-300">
            <span class="mb-2 block">RFID-code</span>
            <input
              ref="inputRef"
              v-model="localValue"
              type="text"
              class="w-full rounded-2xl border border-slate-700 bg-slate-800 px-4 py-3 text-sm text-slate-100 outline-none transition focus:border-blue-500"
              :placeholder="placeholder"
              @keydown.enter.prevent="handleConfirm"
            >
          </label>

          <div v-if="localValue" class="rounded-2xl border border-slate-800 bg-slate-950 px-4 py-3 text-sm text-slate-300">
            Gescand: <span class="font-semibold text-white">{{ localValue }}</span>
          </div>
        </div>

        <div class="grid grid-cols-2 gap-3 border-t border-slate-800 px-6 py-5">
          <button
            type="button"
            class="rounded-2xl border border-slate-700 bg-slate-800 px-4 py-3 text-sm font-semibold text-slate-200 transition hover:bg-slate-700"
            @click="close"
          >
            Annuleren
          </button>

          <button
            type="button"
            class="rounded-2xl bg-blue-600 px-4 py-3 text-sm font-semibold text-white transition hover:bg-blue-500 disabled:cursor-not-allowed disabled:opacity-50"
            :disabled="!normalizedValue"
            @click="handleConfirm"
          >
            {{ confirmLabel }}
          </button>
        </div>
      </div>
    </div>
  </Teleport>
</template>

<script setup>
// v1.2 - confirmFired guard voorkomt dubbele confirmed-emit bij autoConfirm + klik/enter.
// Dit voorkomt dat een hangende scan-promise de UI blokkeert na sluiten.

import { computed, nextTick, onBeforeUnmount, ref, watch } from 'vue'
import { cancelRfidNativeScan, scanRfidNative } from '../../services/rfidService'

const props = defineProps({
  open: { type: Boolean, default: false },
  modelValue: { type: String, default: '' },
  title: { type: String, default: 'RFID scannen' },
  description: { type: String, default: 'Scan een RFID-tag of badge.' },
  placeholder: { type: String, default: 'Wacht op RFID-scan' },
  confirmLabel: { type: String, default: 'Bevestigen' },
  autoConfirm: { type: Boolean, default: true },
})

const emit = defineEmits(['update:open', 'update:modelValue', 'scanned', 'confirmed'])

const inputRef = ref(null)
const localValue = ref('')
const isScanning = ref(false)
const scanError = ref('')
let autoConfirmTimer = null
let scanRequestId = 0
let confirmFired = false

const normalizedValue = computed(() => localValue.value.trim().replace(/[\r\n]+/g, ''))
const descriptionText = computed(() => props.description || 'Scan een RFID-tag of badge.')

watch(
  () => props.open,
  async (isOpen) => {
    clearTimeout(autoConfirmTimer)

    if (!isOpen) {
      localValue.value = props.modelValue ?? ''
      confirmFired = false
      stopNativeScan()
      return
    }

    localValue.value = props.modelValue ?? ''
    scanError.value = ''
    confirmFired = false

    await nextTick()
    inputRef.value?.focus()
    inputRef.value?.select?.()

    startNativeScan()
  },
  { immediate: true }
)

watch(normalizedValue, (value, previousValue) => {
  if (!props.open) return
  if (!value || value === previousValue) return

  emit('update:modelValue', value)
  emit('scanned', value)

  if (!props.autoConfirm) return

  clearTimeout(autoConfirmTimer)
  autoConfirmTimer = setTimeout(() => {
    handleConfirm()
  }, 180)
})

onBeforeUnmount(() => {
  clearTimeout(autoConfirmTimer)
  stopNativeScan()
})

// v1.1 - stopNativeScan() wordt aangeroepen VOOR de emit zodat de Rust thread
// geannuleerd is tegen de tijd dat de parent reageert op de close.
function close() {
  clearTimeout(autoConfirmTimer)
  stopNativeScan()
  emit('update:open', false)
}

function handleConfirm() {
  const value = normalizedValue.value
  if (!value) return
  // Guard: voorkomt dat timer én klik/enter tegelijk een dubbel request sturen
  if (confirmFired) return
  confirmFired = true

  clearTimeout(autoConfirmTimer)
  emit('update:modelValue', value)
  emit('confirmed', value)
  close()
}

async function startNativeScan() {
  const requestId = ++scanRequestId
  isScanning.value = true
  scanError.value = ''

  try {
    const scannedValue = await scanRfidNative()

    if (requestId !== scanRequestId || !props.open) {
      return
    }

    localValue.value = `${scannedValue ?? ''}`.trim()
  } catch (error) {
    if (requestId !== scanRequestId || !props.open) {
      return
    }

    const message = `${error?.message ?? error ?? ''}`.trim()

    if (message && !/cancel/i.test(message)) {
      scanError.value = message
    }
  } finally {
    if (requestId === scanRequestId) {
      isScanning.value = false
    }
  }
}

function stopNativeScan() {
  scanRequestId += 1
  isScanning.value = false
  scanError.value = ''
  cancelRfidNativeScan().catch(() => {})
}
</script>
