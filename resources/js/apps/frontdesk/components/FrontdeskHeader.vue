<template>
    <header class="border-b border-slate-800 bg-slate-900/95 backdrop-blur">
        <div class="flex items-center justify-between gap-6 px-4 py-3">
            <div class="min-w-0">
                <h1 class="truncate text-2xl font-bold tracking-tight text-white">
                    PlayDrive Frontdesk
                </h1>
                <p class="mt-1 text-sm text-slate-400">
                    Snel overzicht van balie, verkoop, vouchers en planning.
                </p>
            </div>

            <div class="flex items-center gap-3">
                <FrontdeskNav :items="navigation" />

                <div class="hidden items-center gap-3 xl:flex">
                    <div class="rounded-xl border border-slate-700 bg-slate-800 px-3 py-2 text-sm text-slate-300">
                        Tenant: <span class="font-semibold text-white">{{ tenantName }}</span>
                    </div>

                    <div
                        v-if="auth.user"
                        class="rounded-xl border border-slate-700 bg-slate-800 px-3 py-2 text-sm text-slate-300"
                    >
                        Ingelogd als:
                        <span class="font-semibold text-white">{{ auth.user.name }}</span>
                    </div>

                    <button
                        type="button"
                        class="inline-flex items-center gap-2 rounded-2xl border border-red-500/30 bg-red-500/10 px-4 py-2.5 text-sm font-semibold text-red-200 shadow-sm transition hover:bg-red-500/20"
                        @click="handleLogout"
                    >
                        <ArrowLeftOnRectangleIcon class="h-5 w-5" />
                        <span>Uitloggen</span>
                    </button>
                </div>
            </div>
        </div>
    </header>
</template>

<script setup>
import { ArrowLeftOnRectangleIcon } from '@heroicons/vue/24/outline'
import FrontdeskNav from './FrontdeskNav.vue'
import { useAuthStore } from '../stores/authStore'

const auth = useAuthStore()

const tenantName = window.PlayDrive?.tenantName || 'Onbekende tenant'

const navigation = [
    { label: 'Dashboard', to: '/', icon: 'home' },
    { label: 'POS', to: '/pos', icon: 'credit-card' },
    { label: 'Verkopen', to: '/sales', icon: 'clipboard-document-list' },
    { label: 'Agenda', to: '/agenda', icon: 'calendar-days' },
    { label: 'Taken', to: '/tasks', icon: 'clipboard-document-check' },
    { label: 'Cadeaubonnen', to: '/vouchers', icon: 'ticket' },
    { label: 'Abonnementen', to: '/members', icon: 'identification' },
    { label: 'Personeel', to: '/staff', icon: 'users' },
]

async function handleLogout() {
    await auth.logout()
}
</script>
