<template>
    <header class="border-b border-slate-800 bg-gradient-to-r from-slate-950 via-slate-900 to-indigo-950/80 backdrop-blur">
        <div class="flex items-center justify-between gap-6 px-4 py-3">
            <div class="flex min-w-0 items-center">
                <img
                    :src="logoHeaderUrl"
                    alt="Game-INN"
                    class="h-12 w-auto object-contain"
                />
            </div>

            <div class="flex items-center gap-3">
                <FrontdeskNav :items="navigation" />
            </div>
        </div>
    </header>
</template>

<script setup>
import FrontdeskNav from './FrontdeskNav.vue'

const logoHeaderUrl = '/images/logos/logo_header.png'

const navigation = [
    { label: 'DASHBOARD', to: '/', icon: 'home' },
    { label: 'POS', to: '/pos', icon: 'credit-card' },
    { label: 'VERKOPEN', to: '/sales', icon: 'clipboard-document-list' },
    { label: 'AGENDA', to: '/agenda', icon: 'calendar-days' },
    { label: 'TAKEN', to: '/tasks', icon: 'clipboard-document-check' },
    { label: 'CADEAUBONNEN', to: '/vouchers', icon: 'ticket' },
    { label: 'LEDEN', to: '/members', icon: 'identification' },
    { label: 'PERSONEEL', to: '/staff', icon: 'users' },
    { label: 'MAILS', to: '/mail-logs', icon: 'envelope' },
]
</script>
