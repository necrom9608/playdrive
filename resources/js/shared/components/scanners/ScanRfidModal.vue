<template>
  <div
    v-if="open"
    class="fixed inset-0 z-[80] flex items-center justify-center bg-slate-950/85 p-4"
    @click.self="close"
  >
    <div class="w-full max-w-md overflow-hidden rounded-3xl border border-slate-800 bg-slate-900 shadow-2xl">
      <div class="flex items-start justify-between gap-4 border-b border-slate-800 px-6 py-5">
        <div>
          <h3 class="text-lg font-semibold text-white">{{ title }}</h3>
          <p class="mt-1 text-sm text-slate-400">{{ helperText }}</p>
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
        <div
          class="rounded-2xl border px-4 py-3 text-sm"
          :class="statusBoxClass"
        >
          {{ statusMessage }}
        </div>

        <template v-if="allowManualEntry">
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
        </template>

        <div v-if="normalizedValue" class="rounded-2xl border border-slate-800 bg-slate-950 px-4 py-3 text-sm text-slate-300">
          Gescand: <span class="font-semibold text-white">{{ normalizedValue }}</span>
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
          :disabled="!normalizedValue || isScanning"
          @click="handleConfirm"
        >
          {{ confirmButtonLabel }}
        </button>
      </div>
    </div>
  </div>
</template>

<script setup>
import { computed, nextTick, onBeforeUnmount, ref, watch } from 'vue'
import {
  cancelRfidNativeScan,
  isNativeRfidSupported,
  scanRfidNative,
} from '../../services/rfidService'

const props = defineProps({
  open: { type: Boolean, default: false },
  modelValue: { type: String, default: '' },
  title: { type: String, default: 'RFID scannen' },
  description: { type: String, default: '' },
  placeholder: { type: String, default: 'Wacht op RFID-scan' },
  confirmLabel: { type: String, default: 'Bevestigen' },
  autoConfirm: { type: Boolean, default: true },
  timeoutMs: { type: Number, default: 15000 },
})

const emit = defineEmits(['update:open', 'update:modelValue', 'scanned', 'confirmed'])

const inputRef = ref(null)
const localValue = ref('')
const scanToken = ref(0)
const isScanning = ref(false)
const scanError = ref('')
let autoConfirmTimer = null

const hasNativeScan = computed(() => isNativeRfidSupported())
const allowManualEntry = computed(() => !hasNativeScan.value)
const normalizedValue = computed(() => localValue.value.trim().replace(/[\r\n]+/g, ''))
const confirmButtonLabel = computed(() => (isScanning.value ? 'Scannen…' : props.confirmLabel))
const helperText = computed(() => {
  if (props.description?.trim()) {
    return props.description
  }

  return hasNativeScan.value
    ? 'Houd de RFID-tag of badge bij de kaartlezer.'
    : 'Lokale RFID-ondersteuning is niet actief. Je kunt de code manueel ingeven.'
})
const statusBoxClass = computed(() => {
  if (scanError.value) {
    return 'border-rose-500/30 bg-rose-500/10 text-rose-100'
  }

  if (normalizedValue.value) {
    return 'border-emerald-500/30 bg-emerald-500/10 text-emerald-100'
  }

  return 'border-blue-500/30 bg-blue-500/10 text-blue-100'
})
const statusMessage = computed(() => {
  if (scanError.value) {
    return scanError.value
  }

  if (normalizedValue.value) {
    return `RFID gevonden: ${normalizedValue.value}`
  }

  if (hasNativeScan.value) {
    return isScanning.value
      ? 'Klaar om te scannen. Houd de badge of kaart tegen de lezer.'
      : 'Native RFID-scan wordt voorbereid…'
  }

  return 'Manuele modus actief. Geef de RFID-code in en bevestig.'
})

watch(
  () => props.open,
  async (isOpen) => {
    clearTimeout(autoConfirmTimer)
    scanError.value = ''

    if (!isOpen) {
      await stopScan()
      localValue.value = props.modelValue ?? ''
      return
    }

    localValue.value = props.modelValue ?? ''

    if (hasNativeScan.value) {
      await startNativeScan()
      return
    }

    await nextTick()
    inputRef.value?.focus()
    inputRef.value?.select?.()
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

onBeforeUnmount(async () => {
  clearTimeout(autoConfirmTimer)
  await stopScan()
})

async function startNativeScan() {
  await stopScan()

  const token = scanToken.value + 1
  scanToken.value = token
  isScanning.value = true
  scanError.value = ''

  try {
    const scannedValue = await scanRfidNative({ timeoutMs: props.timeoutMs })

    if (!props.open || scanToken.value !== token) {
      return
    }

    if (scannedValue) {
      localValue.value = scannedValue
    }
  } catch (error) {
    if (!props.open || scanToken.value !== token) {
      return
    }

    scanError.value = normalizeError(error)
  } finally {
    if (scanToken.value === token) {
      isScanning.value = false
    }
  }
}

async function stopScan() {
  scanToken.value += 1
  isScanning.value = false

  if (hasNativeScan.value) {
    try {
      await cancelRfidNativeScan()
    } catch {
      // no-op
    }
  }
}

async function close() {
  await stopScan()
  emit('update:open', false)
}

async function handleConfirm() {
  const value = normalizedValue.value
  if (!value) return

  emit('update:modelValue', value)
  emit('confirmed', value)
  await close()
}

function normalizeError(error) {
  const message = error instanceof Error ? error.message : String(error ?? '')

  if (!message) {
    return 'RFID-scan mislukt.'
  }

  return message
}
</script>
