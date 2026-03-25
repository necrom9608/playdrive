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
                        Tenant: <span class="font-semibold text-white">Game-INN</span>
                    </div>
                </div>
            </div>
        </div>
    </header>
</template>

<script setup>
import FrontdeskNav from './FrontdeskNav.vue'

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
</script>
