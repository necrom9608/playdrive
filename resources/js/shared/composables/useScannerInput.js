import { computed } from 'vue'

export function normalizeScannerValue(value) {
  return String(value ?? '').trim().replace(/[\r\n]+/g, '')
}

export function useScannerInput(source) {
  return computed(() => normalizeScannerValue(source?.value))
}
