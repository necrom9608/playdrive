<template>
    <footer class="border-t border-slate-800 bg-slate-900/95 backdrop-blur">
        <div class="flex flex-wrap items-center justify-between gap-3 px-4 py-3 text-sm">
            <div class="flex flex-wrap items-center gap-2">
                <span class="rounded-xl border border-slate-700 bg-slate-800 px-3 py-2 text-slate-300">
                    Tenant: <span class="font-semibold text-white">{{ tenantName }}</span>
                </span>
                <span v-if="auth.user" class="rounded-xl border border-slate-700 bg-slate-800 px-3 py-2 text-slate-300">
                    User: <span class="font-semibold text-white">{{ auth.user.name }}</span>
                </span>
                <span class="inline-flex items-center gap-2 rounded-xl border border-slate-700 bg-slate-800 px-3 py-2 text-slate-300">
                    <span class="h-2.5 w-2.5 rounded-full" :class="deviceDotClass"></span>
                    <span>Device: <span class="font-semibold text-white">{{ deviceName }}</span></span>
                </span>
                <span class="inline-flex items-center gap-2 rounded-xl border border-slate-700 bg-slate-800 px-3 py-2 text-slate-300">
                    <span class="h-2.5 w-2.5 rounded-full" :class="displayDotClass"></span>
                    <span>Display: <span class="font-semibold text-white">{{ displayLabel }}</span></span>
                </span>
            </div>

            <button
                v-if="auth.user"
                type="button"
                class="inline-flex items-center gap-2 rounded-2xl border border-red-500/30 bg-red-500/10 px-4 py-2.5 text-sm font-semibold text-red-200 shadow-sm transition hover:bg-red-500/20"
                @click="handleLogout"
            >
                Uitloggen
            </button>
        </div>
    </footer>
</template>

<script setup>
import { computed } from 'vue'
import { useAuthStore } from '../stores/authStore'
import { usePosStore } from '../modules/pos/stores/usePosStore'

const auth = useAuthStore()
const posStore = usePosStore()
const tenantName = window.PlayDrive?.tenantName || 'Onbekende tenant'

const deviceName = computed(() => posStore.posDevice?.name || 'Niet gekoppeld')
const displayLabel = computed(() => posStore.posDevice?.display_name || 'Geen externe display')
const deviceDotClass = computed(() => posStore.posDevice?.device_token ? 'bg-emerald-400' : 'bg-slate-500')
const displayDotClass = computed(() => posStore.posDevice?.display_device_id ? 'bg-emerald-400' : 'bg-amber-400')

async function handleLogout() {
    await auth.logout()
}
</script>
