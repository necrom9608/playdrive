<template>
    <footer class="relative overflow-hidden border-t border-slate-800 bg-gradient-to-l from-slate-950 via-slate-900 to-indigo-950/80 backdrop-blur">
        <div class="pointer-events-none absolute inset-0 bg-[radial-gradient(circle_at_bottom_right,rgba(99,102,241,0.12),transparent_35%),radial-gradient(circle_at_bottom_left,rgba(56,189,248,0.10),transparent_30%)]"></div>

        <div class="relative flex flex-wrap items-center justify-between gap-3 px-4 py-3 text-sm">
            <div class="flex flex-wrap items-center gap-2">
                <div class="flex items-center pr-4">
    <span class="text-base font-semibold text-white">
        {{ formattedDateTime }}
    </span>

                    <span class="ml-4 h-5 w-px bg-slate-700"></span>
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

                <span class="rounded-xl border border-slate-700 bg-slate-800/80 px-3 py-2 text-slate-300">
                    Runtime: <span class="font-semibold text-white">{{ runtimeLabel }}</span>
                </span>
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
import { ArrowRightOnRectangleIcon } from '@heroicons/vue/24/outline'
import { useAuthStore } from '../stores/authStore'
import { usePosStore } from '../modules/pos/stores/usePosStore'
import { frontdeskConfig } from '../config/frontdeskConfig'
import { getDeviceRuntimeSummary, getStoredDeviceName } from '../services/deviceService'

const auth = useAuthStore()
const posStore = usePosStore()
const tenantName = frontdeskConfig.tenantName || 'Onbekende tenant'
const runtimeSummary = getDeviceRuntimeSummary()

const configuredDeviceName = getStoredDeviceName()
const deviceName = computed(() => posStore.posDevice?.name || configuredDeviceName || 'Niet gekoppeld')
const displayLabel = computed(() => posStore.posDevice?.display_name || 'Geen externe display')
const deviceDotClass = computed(() => posStore.posDevice?.device_token ? 'bg-emerald-400' : 'bg-slate-500')
const displayDotClass = computed(() => posStore.posDevice?.display_device_id ? 'bg-emerald-400' : 'bg-amber-400')
const runtimeLabel = computed(() => runtimeSummary.environment === 'tauri' ? 'Lokale app' : 'Web')

const now = ref(new Date())
let interval = null

onMounted(() => {
    interval = setInterval(() => {
        now.value = new Date()
    }, 1000)
})

onUnmounted(() => {
    clearInterval(interval)
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
