<template>
    <footer class="relative overflow-hidden border-t border-slate-800 bg-gradient-to-l from-slate-950 via-slate-900 to-indigo-950/80 backdrop-blur">
        <div class="pointer-events-none absolute inset-0 bg-[radial-gradient(circle_at_bottom_right,rgba(99,102,241,0.12),transparent_35%),radial-gradient(circle_at_bottom_left,rgba(56,189,248,0.10),transparent_30%)]"></div>

        <div class="relative flex flex-wrap items-center justify-between gap-3 px-4 py-3 text-sm">
            <div class="flex flex-wrap items-center gap-2">
                <div class="flex items-center gap-3 pr-4">
                    <span class="text-base font-semibold text-white">
                        {{ formattedDateTime }}
                    </span>
                    <span class="rounded-full border border-indigo-500/40 bg-indigo-500/10 px-2 py-0.5 text-xs font-medium text-indigo-300">
                        v{{ appVersion }}
                    </span>
                    <span class="h-5 w-px bg-slate-700"></span>
                </div>

                <span class="rounded-xl border border-slate-700 bg-slate-800/80 px-3 py-2 text-slate-300">
                    Tenant: <span class="font-semibold text-white">{{ tenantName }}</span>
                </span>

                <span
                    v-if="auth.user"
                    class="rounded-xl border border-slate-700 bg-slate-800/80 px-3 py-2 text-slate-300"
                >
                    User: <span class="font-semibold text-white">{{ auth.user.name }}</span>
                </span>

                <span class="inline-flex items-center gap-2 rounded-xl border border-slate-700 bg-slate-800/80 px-3 py-2 text-slate-300">
                    <span class="h-2.5 w-2.5 rounded-full" :class="deviceDotClass"></span>
                    <span>Device: <span class="font-semibold text-white">{{ deviceName }}</span></span>
                </span>

                <span class="inline-flex items-center gap-2 rounded-xl border border-slate-700 bg-slate-800/80 px-3 py-2 text-slate-300">
                    <span class="h-2.5 w-2.5 rounded-full" :class="displayDotClass"></span>
                    <span>Display: <span class="font-semibold text-white">{{ displayLabel }}</span></span>
                </span>

                <span class="rounded-xl border border-slate-700 bg-slate-800/80 px-3 py-2 text-slate-300" :title="desktopError || ''">
                    Runtime: <span class="font-semibold text-white">{{ runtimeLabel }}</span>
                    <span v-if="desktopError" class="ml-1 text-amber-300">· app niet bereikbaar: {{ desktopError }}</span>
                </span>

                <!-- Kassa-app: nieuwe versie gedownload (src-tauri/src/commands/updater.rs) -->
                <button
                    v-if="desktopUpdate?.available"
                    type="button"
                    class="inline-flex items-center gap-2 rounded-xl border px-3 py-2 font-semibold transition"
                    :class="confirmRestart
                        ? 'border-amber-400 bg-amber-500 text-slate-950'
                        : 'border-amber-500/40 bg-amber-500/10 text-amber-200 hover:bg-amber-500/20'"
                    :title="desktopUpdate.notes || ''"
                    @click="installDesktopUpdate"
                >
                    <ArrowPathIcon class="h-4 w-4" />
                    {{ confirmRestart ? 'Nu herstarten?' : `Kassa-app ${desktopUpdate.available} klaar · Herstarten` }}
                </button>
            </div>

            <button
                v-if="auth.user"
                type="button"
                class="inline-flex items-center gap-2 rounded-2xl border border-red-500/30 bg-red-500/10 px-4 py-2.5 text-sm font-semibold text-red-200 shadow-sm transition hover:bg-red-500/20"
                @click="handleLogout"
            >
                <ArrowRightOnRectangleIcon class="h-5 w-5" />
                <span>Uitloggen</span>
            </button>
        </div>
    </footer>
</template>

<script setup>
import { computed, ref, onMounted, onUnmounted } from 'vue'
import { ArrowPathIcon, ArrowRightOnRectangleIcon } from '@heroicons/vue/24/outline'
import { useAuthStore } from '../stores/authStore'
import { usePosStore } from '../modules/pos/stores/usePosStore'
import { frontdeskConfig } from '../config/frontdeskConfig'
import { getDeviceRuntimeSummary } from '../services/deviceService'
import { isTauriRuntime } from '../../../shared/runtime/environment'

const appVersion = import.meta.env.VITE_APP_VERSION || '?'

const auth = useAuthStore()
const posStore = usePosStore()
const tenantName = frontdeskConfig.tenantName || 'Onbekende tenant'
const runtimeSummary = getDeviceRuntimeSummary()

const deviceName = computed(() => posStore.posDevice?.name || posStore.configuredDeviceName || 'Niet gekoppeld')
const displayLabel = computed(() => posStore.posDevice?.display_name || 'Geen externe display')
const deviceDotClass = computed(() => posStore.posDevice?.device_token ? 'bg-emerald-400' : 'bg-slate-500')
const displayDotClass = computed(() => posStore.posDevice?.display_device_id ? 'bg-emerald-400' : 'bg-amber-400')
const runtimeLabel = computed(() => {
    if (runtimeSummary.environment !== 'tauri') return 'Web'
    return desktopUpdate.value?.current ? `Lokale app ${desktopUpdate.value.current}` : 'Lokale app'
})

// Automatische updates van de kassa-app: { current, available, notes, phase, error }
const desktopUpdate = ref(null)
const desktopError = ref('')
const confirmRestart = ref(false)
let updateTimer = null
let confirmTimer = null

async function tauriInvoke(command) {
    const { invoke } = await import('@tauri-apps/api/core')
    return invoke(command)
}

async function refreshDesktopUpdate() {
    if (!isTauriRuntime()) return
    try {
        desktopUpdate.value = await tauriInvoke('desktop_update_status')
        desktopError.value = ''
    } catch (error) {
        desktopUpdate.value = null // oudere kassa-app zonder updater, of opdracht geblokkeerd
        desktopError.value = (typeof error === 'string' ? error : error?.message || String(error)).slice(0, 160)
        console.warn('Kassa-app niet bereikbaar', error)
    }
}

async function installDesktopUpdate() {
    if (!confirmRestart.value) {
        confirmRestart.value = true
        window.clearTimeout(confirmTimer)
        confirmTimer = window.setTimeout(() => { confirmRestart.value = false }, 5000)
        return
    }
    try {
        await tauriInvoke('desktop_update_install')
    } catch (error) {
        console.error('Kassa-app bijwerken mislukt', error)
        confirmRestart.value = false
        refreshDesktopUpdate()
    }
}

const now = ref(new Date())
let interval = null

onMounted(() => {
    interval = setInterval(() => {
        now.value = new Date()
    }, 1000)
    refreshDesktopUpdate()
    updateTimer = setInterval(refreshDesktopUpdate, 60000)
})

onUnmounted(() => {
    clearInterval(interval)
    clearInterval(updateTimer)
    window.clearTimeout(confirmTimer)
})

const formattedDateTime = computed(() => {
    const date = now.value

    const day = date.toLocaleDateString('nl-BE', { weekday: 'long' })
    const dayNumber = date.getDate()
    const month = date.toLocaleDateString('nl-BE', { month: 'long' })
    const year = date.getFullYear()
    const time = date.toLocaleTimeString('nl-BE', {
        hour: '2-digit',
        minute: '2-digit',
    })

    return `${capitalize(day)} ${dayNumber} ${month} ${year} - ${time}`
})

function capitalize(value) {
    if (!value) return ''
    return value.charAt(0).toUpperCase() + value.slice(1)
}

async function handleLogout() {
    await auth.logout()
}
</script>
