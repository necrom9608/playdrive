<template>
    <Teleport to="body">
        <div
            v-if="open"
            class="fixed inset-0 z-[200] flex items-center justify-center bg-slate-950/80 p-4"
            @click.self="close"
        >
            <div class="w-full max-w-2xl rounded-3xl border border-slate-800 bg-slate-900 shadow-2xl">
                <div class="flex items-start justify-between border-b border-slate-800 px-6 py-5">
                    <div>
                        <h2 class="text-xl font-semibold text-white">{{ title }}</h2>
                        <p class="mt-1 text-sm text-slate-400">{{ description }}</p>
                    </div>

                    <button
                        type="button"
                        class="rounded-2xl border border-slate-700 bg-slate-800 px-4 py-2 text-sm font-semibold text-white hover:bg-slate-700"
                        @click="close"
                    >
                        Sluiten
                    </button>
                </div>

                <div class="space-y-5 px-6 py-6">
                    <div class="overflow-hidden rounded-3xl border border-slate-800 bg-slate-950">
                        <div
                            id="qr-reader"
                            class="min-h-[320px] w-full"
                        />
                    </div>

                    <div class="rounded-2xl border border-slate-800 bg-slate-950/60 p-4">
                        <label class="mb-2 block text-xs font-semibold uppercase tracking-wide text-slate-400">
                            Gedetecteerde code
                        </label>

                        <input
                            ref="manualInput"
                            v-model="localValue"
                            type="text"
                            class="w-full rounded-2xl border border-slate-700 bg-slate-800 px-4 py-3 text-white outline-none transition focus:border-sky-500"
                            placeholder="QR-code verschijnt hier of geef manueel in"
                            @keydown.enter.prevent.stop="confirm"
                        />
                    </div>

                    <p
                        v-if="errorMessage"
                        class="rounded-2xl border border-rose-800/50 bg-rose-950/40 px-4 py-3 text-sm text-rose-300"
                    >
                        {{ errorMessage }}
                    </p>
                </div>

                <div class="flex items-center justify-end gap-3 border-t border-slate-800 px-6 py-5">
                    <button
                        type="button"
                        class="rounded-2xl border border-slate-700 bg-slate-800 px-5 py-3 text-sm font-semibold text-white hover:bg-slate-700"
                        @click="close"
                    >
                        Annuleren
                    </button>

                    <button
                        type="button"
                        class="rounded-2xl bg-sky-500 px-5 py-3 text-sm font-semibold text-white transition hover:bg-sky-400 disabled:cursor-not-allowed disabled:opacity-50"
                        :disabled="!localValue"
                        @click="confirm"
                    >
                        {{ confirmLabel }}
                    </button>
                </div>
            </div>
        </div>
    </Teleport>
</template>

<script setup>
import { nextTick, onBeforeUnmount, ref, watch } from 'vue'
import { Html5Qrcode } from 'html5-qrcode'

const props = defineProps({
    open: {
        type: Boolean,
        default: false,
    },
    title: {
        type: String,
        default: 'Scan QR-code',
    },
    description: {
        type: String,
        default: 'Gebruik de camera om een QR-code te scannen of geef de code manueel in.',
    },
    confirmLabel: {
        type: String,
        default: 'Bevestigen',
    },
    autoConfirm: {
        type: Boolean,
        default: true,
    },
})

const emit = defineEmits(['update:open', 'scanned', 'confirmed'])

const localValue = ref('')
const errorMessage = ref('')
const manualInput = ref(null)

let scanner = null
let lastScan = ''
let lastScanAt = 0
let closing = false

watch(
    () => props.open,
    async (isOpen) => {
        if (isOpen) {
            localValue.value = ''
            errorMessage.value = ''
            closing = false

            await nextTick()
            await startScanner()
            await nextTick()

            if (manualInput.value) {
                manualInput.value.focus()
            }
        } else {
            await stopScanner()
        }
    }
)

onBeforeUnmount(async () => {
    await stopScanner()
})

async function startScanner() {
    try {
        await stopScanner()

        scanner = new Html5Qrcode('qr-reader')

        await scanner.start(
            { facingMode: 'environment' },
            {
                fps: 10,
                qrbox: { width: 250, height: 250 },
                aspectRatio: 1.777778,
            },
            async (decodedText) => {
                const value = String(decodedText || '').trim()
                if (!value) return

                const now = Date.now()
                if (value === lastScan && now - lastScanAt < 1500) {
                    return
                }

                lastScan = value
                lastScanAt = now
                localValue.value = value

                emit('scanned', value)

                if (props.autoConfirm) {
                    await confirm()
                }
            },
            () => {
                // scan errors stil negeren
            }
        )
    } catch (error) {
        console.error('QR scanner starten mislukt', error)
        errorMessage.value = 'De camera kon niet gestart worden. Controleer camerarechten of geef de code manueel in.'
    }
}

async function stopScanner() {
    if (!scanner) return

    try {
        if (scanner.isScanning) {
            await scanner.stop()
        }
    } catch (error) {
        console.warn('QR scanner stop fout', error)
    }

    try {
        await scanner.clear()
    } catch (error) {
        console.warn('QR scanner clear fout', error)
    }

    scanner = null
}

async function confirm() {
    const value = String(localValue.value || '').trim()
    if (!value) return

    emit('scanned', value)
    emit('confirmed', value)

    await close()
}

async function close() {
    if (closing) return
    closing = true

    await stopScanner()
    emit('update:open', false)
}
</script>
