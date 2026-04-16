<template>
    <Teleport to="body">
        <Transition name="modal-fade">
            <div
                v-if="open"
                class="fixed inset-0 z-[180] flex items-center justify-center p-4"
                style="background: rgba(3,8,20,0.75); backdrop-filter: blur(6px);"
                @click.self="close"
            >
                <Transition name="panel-slide" mode="out-in">

                    <!-- SCAN SCREEN -->
                    <div
                        v-if="phase === 'scan'"
                        key="scan"
                        class="glass-panel w-full max-w-md overflow-hidden rounded-3xl"
                    >
                        <div class="flex items-start justify-between gap-4 border-b border-white/10 px-6 py-5">
                            <div>
                                <h3 class="text-lg font-semibold text-white">Staf in / uitchecken</h3>
                                <p class="mt-1 text-sm text-slate-400">Scan de RFID-badge van de medewerker.</p>
                            </div>
                            <button type="button" class="glass-btn-close" @click="close">
                                <XMarkIcon class="h-5 w-5" />
                            </button>
                        </div>

                        <div class="space-y-4 px-6 py-6">
                            <div class="rounded-2xl border border-blue-500/25 bg-blue-500/10 px-4 py-3 text-sm text-blue-100">
                                Houd de RFID-badge bij de lezer — verwerking start automatisch.
                            </div>

                            <div v-if="isScanning" class="rounded-2xl border border-cyan-500/20 bg-cyan-500/8 px-4 py-3 text-sm text-cyan-100">
                                Scanner actief… wacht op badge.
                            </div>

                            <div v-if="scanError" class="rounded-2xl border border-amber-500/20 bg-amber-500/10 px-4 py-3 text-sm text-amber-100">
                                {{ scanError }}
                            </div>

                            <label class="block text-sm text-slate-300">
                                <span class="mb-2 block">RFID-code</span>
                                <input
                                    ref="inputRef"
                                    v-model="rfidValue"
                                    type="text"
                                    class="w-full rounded-2xl border border-white/10 bg-slate-900/60 px-4 py-3 text-sm text-slate-100 outline-none transition focus:border-blue-500 backdrop-blur-sm"
                                    placeholder="Wacht op RFID-scan…"
                                    @keydown.enter.prevent="handleConfirm"
                                />
                            </label>

                            <div v-if="processing" class="flex items-center gap-3 rounded-2xl border border-white/10 bg-white/5 px-4 py-3 text-sm text-slate-300">
                                <div class="h-4 w-4 animate-spin rounded-full border-2 border-white/20 border-t-white"></div>
                                <span>Verwerken…</span>
                            </div>
                        </div>

                        <div class="border-t border-white/10 px-6 py-4">
                            <button type="button" class="glass-btn-secondary w-full" @click="close">
                                Annuleren
                            </button>
                        </div>
                    </div>

                    <!-- RESULT SCREEN -->
                    <div
                        v-else-if="phase === 'result' && result"
                        key="result"
                        class="glass-panel w-full max-w-md overflow-hidden rounded-3xl"
                    >
                        <div class="flex items-start justify-between gap-4 border-b border-white/10 px-6 py-5">
                            <div class="flex items-center gap-3">
                                <div
                                    class="flex h-10 w-10 items-center justify-center rounded-2xl"
                                    :class="result.action === 'check_in'
                                        ? 'border border-emerald-400/30 bg-emerald-500/15'
                                        : 'border border-violet-400/30 bg-violet-500/15'"
                                >
                                    <ArrowRightOnRectangleIcon v-if="result.action === 'check_in'" class="h-5 w-5 text-emerald-300" />
                                    <ArrowLeftOnRectangleIcon v-else class="h-5 w-5 text-violet-300" />
                                </div>
                                <div>
                                    <h3 class="text-lg font-semibold text-white">{{ result.action === 'check_in' ? 'Ingecheckt' : 'Uitgecheckt' }}</h3>
                                    <p class="mt-0.5 text-sm text-slate-400">{{ result.action_label }}</p>
                                </div>
                            </div>
                            <button type="button" class="glass-btn-close" @click="close">
                                <XMarkIcon class="h-5 w-5" />
                            </button>
                        </div>

                        <div class="space-y-3 px-6 py-6">
                            <!-- Name + status -->
                            <div
                                class="flex items-center gap-4 rounded-2xl border px-5 py-4"
                                :class="result.action === 'check_in'
                                    ? 'border-emerald-400/20 bg-emerald-500/10'
                                    : 'border-violet-400/20 bg-violet-500/10'"
                            >
                                <div
                                    class="flex h-12 w-12 shrink-0 items-center justify-center rounded-2xl text-lg font-bold text-white"
                                    :class="result.action === 'check_in' ? 'bg-emerald-500/20' : 'bg-violet-500/20'"
                                >
                                    {{ initials(result.user_name) }}
                                </div>
                                <div>
                                    <p class="text-base font-semibold text-white">{{ result.user_name }}</p>
                                    <p class="mt-0.5 text-sm" :class="result.action === 'check_in' ? 'text-emerald-300' : 'text-violet-300'">
                                        {{ result.action === 'check_in' ? 'Met succes ingecheckt' : 'Met succes uitgecheckt' }}
                                    </p>
                                </div>
                            </div>

                            <!-- Time info -->
                            <div class="grid gap-2" :class="result.action === 'check_out' ? 'grid-cols-3' : 'grid-cols-1'">
                                <div class="rounded-2xl border border-white/10 bg-white/5 px-4 py-3 text-center">
                                    <p class="text-xs uppercase tracking-wide text-slate-400">Ingecheckt om</p>
                                    <p class="mt-1 text-lg font-semibold text-white">{{ result.checked_in_at }}</p>
                                </div>
                                <template v-if="result.action === 'check_out'">
                                    <div class="rounded-2xl border border-white/10 bg-white/5 px-4 py-3 text-center">
                                        <p class="text-xs uppercase tracking-wide text-slate-400">Uitgecheckt om</p>
                                        <p class="mt-1 text-lg font-semibold text-white">{{ result.checked_out_at }}</p>
                                    </div>
                                    <div class="rounded-2xl border border-violet-400/20 bg-violet-500/10 px-4 py-3 text-center">
                                        <p class="text-xs uppercase tracking-wide text-violet-300">Gewerkt</p>
                                        <p class="mt-1 text-lg font-semibold text-white">{{ result.duration }}</p>
                                    </div>
                                </template>
                            </div>
                        </div>

                        <div class="border-t border-white/10 px-6 py-4">
                            <button type="button" class="glass-btn-primary w-full" @click="close">
                                Sluiten
                            </button>
                        </div>
                    </div>

                    <!-- ERROR SCREEN -->
                    <div
                        v-else-if="phase === 'error'"
                        key="error"
                        class="glass-panel w-full max-w-md overflow-hidden rounded-3xl"
                    >
                        <div class="flex items-start justify-between gap-4 border-b border-white/10 px-6 py-5">
                            <div class="flex items-center gap-3">
                                <div class="flex h-10 w-10 items-center justify-center rounded-2xl border border-rose-400/30 bg-rose-500/15">
                                    <ExclamationTriangleIcon class="h-5 w-5 text-rose-300" />
                                </div>
                                <h3 class="text-lg font-semibold text-white">Scan mislukt</h3>
                            </div>
                            <button type="button" class="glass-btn-close" @click="close">
                                <XMarkIcon class="h-5 w-5" />
                            </button>
                        </div>

                        <div class="px-6 py-6">
                            <div class="rounded-2xl border border-rose-500/25 bg-rose-500/10 px-4 py-3 text-sm text-rose-200">
                                {{ errorMessage }}
                            </div>
                        </div>

                        <div class="flex gap-3 border-t border-white/10 px-6 py-4">
                            <button type="button" class="glass-btn-secondary flex-1" @click="reset">
                                Opnieuw proberen
                            </button>
                            <button type="button" class="glass-btn-primary flex-1" @click="close">
                                Sluiten
                            </button>
                        </div>
                    </div>

                </Transition>
            </div>
        </Transition>
    </Teleport>
</template>

<script setup>
import { nextTick, ref, watch } from 'vue'
import axios from 'axios'
import {
    XMarkIcon,
    ArrowRightOnRectangleIcon,
    ArrowLeftOnRectangleIcon,
    ExclamationTriangleIcon,
} from '@heroicons/vue/24/outline'
import { cancelRfidNativeScan, scanRfidNative } from '../../../../../shared/services/rfidService'

const props = defineProps({
    open: { type: Boolean, default: false },
})

const emit = defineEmits(['update:open', 'done'])

const inputRef = ref(null)
const rfidValue = ref('')
const phase = ref('scan')       // 'scan' | 'result' | 'error'
const processing = ref(false)
const isScanning = ref(false)
const scanError = ref('')
const errorMessage = ref('')
const result = ref(null)

let autoConfirmTimer = null
let scanRequestId = 0

// ── Watchers ──────────────────────────────────────────────────────────────────

watch(() => props.open, async (isOpen) => {
    if (isOpen) {
        reset()
        await nextTick()
        inputRef.value?.focus()
        startNativeScan()
    } else {
        stopNativeScan()
        clearTimeout(autoConfirmTimer)
    }
})

watch(rfidValue, (value) => {
    if (!props.open || !value.trim() || processing.value) return
    clearTimeout(autoConfirmTimer)
    autoConfirmTimer = setTimeout(() => handleConfirm(), 180)
})

// ── Actions ───────────────────────────────────────────────────────────────────

async function handleConfirm() {
    const uid = rfidValue.value.trim().replace(/[\r\n]+/g, '')
    if (!uid || processing.value) return

    stopNativeScan()
    processing.value = true
    scanError.value = ''

    try {
        const { data } = await axios.post('/api/frontdesk/staff-attendance/scan', { rfid_uid: uid })
        const last = data.last_action
        const session = data.today_sessions?.[0] ?? null

        result.value = {
            action: last.action,
            action_label: last.action_label,
            user_name: last.user_name,
            checked_in_at: session?.checked_in_at_full_label?.slice(-5) ?? '—',
            checked_out_at: session?.checked_out_at_full_label?.slice(-5) ?? null,
            duration: session?.duration_label ?? null,
        }

        phase.value = 'result'
        emit('done', data)
    } catch (err) {
        errorMessage.value = err?.response?.data?.message ?? 'Onbekende fout bij verwerken.'
        phase.value = 'error'
    } finally {
        processing.value = false
    }
}

function reset() {
    phase.value = 'scan'
    rfidValue.value = ''
    result.value = null
    processing.value = false
    scanError.value = ''
    errorMessage.value = ''
}

function close() {
    stopNativeScan()
    emit('update:open', false)
}

// ── Native RFID ───────────────────────────────────────────────────────────────

async function startNativeScan() {
    const requestId = ++scanRequestId
    isScanning.value = true
    scanError.value = ''

    try {
        const scanned = await scanRfidNative()
        if (requestId !== scanRequestId || !props.open) return
        rfidValue.value = `${scanned ?? ''}`.trim()
    } catch (err) {
        if (requestId !== scanRequestId || !props.open) return
        const msg = `${err?.message ?? err ?? ''}`.trim()
        if (msg && !/cancel/i.test(msg)) scanError.value = msg
    } finally {
        if (requestId === scanRequestId) isScanning.value = false
    }
}

function stopNativeScan() {
    scanRequestId += 1
    isScanning.value = false
    cancelRfidNativeScan().catch(() => {})
}

// ── Helpers ───────────────────────────────────────────────────────────────────

function initials(name) {
    return (name ?? '?').split(' ').slice(0, 2).map(w => w[0]).join('').toUpperCase()
}
</script>

<style scoped>
.glass-panel {
    background: linear-gradient(160deg, rgba(15, 25, 50, 0.92) 0%, rgba(8, 14, 30, 0.96) 100%);
    border: 1px solid rgba(255, 255, 255, 0.10);
    box-shadow: 0 32px 80px rgba(0, 0, 0, 0.5), inset 0 1px 0 rgba(255, 255, 255, 0.06);
    backdrop-filter: blur(20px);
}

.glass-btn-close {
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 0.5rem;
    border-radius: 0.75rem;
    border: 1px solid rgba(255, 255, 255, 0.10);
    background: rgba(255, 255, 255, 0.06);
    color: #94a3b8;
    transition: background 0.15s, color 0.15s;
}
.glass-btn-close:hover {
    background: rgba(255, 255, 255, 0.12);
    color: #fff;
}

.glass-btn-secondary {
    padding: 0.75rem 1rem;
    border-radius: 1rem;
    border: 1px solid rgba(255, 255, 255, 0.12);
    background: rgba(255, 255, 255, 0.06);
    color: #cbd5e1;
    font-size: 0.875rem;
    font-weight: 600;
    transition: background 0.15s;
}
.glass-btn-secondary:hover { background: rgba(255, 255, 255, 0.12); }

.glass-btn-primary {
    padding: 0.75rem 1rem;
    border-radius: 1rem;
    background: linear-gradient(180deg, #3b82f6 0%, #2563eb 100%);
    color: #fff;
    font-size: 0.875rem;
    font-weight: 600;
    transition: opacity 0.15s;
    border: none;
}
.glass-btn-primary:hover { opacity: 0.88; }

.modal-fade-enter-active, .modal-fade-leave-active { transition: opacity 0.25s ease; }
.modal-fade-enter-from, .modal-fade-leave-to { opacity: 0; }

.panel-slide-enter-active, .panel-slide-leave-active { transition: opacity 0.2s ease, transform 0.2s ease; }
.panel-slide-enter-from { opacity: 0; transform: translateY(8px) scale(0.98); }
.panel-slide-leave-to  { opacity: 0; transform: translateY(-6px) scale(0.98); }
</style>
