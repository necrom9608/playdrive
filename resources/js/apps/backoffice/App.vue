<template>
    <div v-if="!auth.initialized" class="flex min-h-screen items-center justify-center bg-slate-950 text-slate-400">Backoffice laden...</div>
    <BackofficeLoginCard v-else-if="!auth.isAuthenticated" />
    <AppShell
        v-else
        title="PlayDrive"
        subtitle="Backoffice"
        :navigation="navigation"
        :status-label="statusLabel"
        @logout="handleLogout"
    />
</template>

<script setup>
import { computed, onMounted } from 'vue'
import AppShell from '../../shared/layouts/AppShell.vue'
import BackofficeLoginCard from './components/BackofficeLoginCard.vue'
import { useBackofficeAuthStore } from './stores/authStore'

const auth = useBackofficeAuthStore()

const navigation = [
    { label: 'Dashboard', to: '/', group: 'Inzichten' },
    { label: 'Rapportering', to: '/reporting', group: 'Inzichten' },
    { label: 'Dagtotalen', to: '/daytotals', group: 'Inzichten' },
    { label: 'Productbeheer', to: '/catalog', group: 'Verkoop' },
    { label: 'Cateringopties', to: '/catering-options', group: 'Verkoop' },
    { label: 'Personeel', to: '/staff', group: 'Beheer' },
    { label: 'Badge creator', to: '/badges', group: 'Beheer' },
    { label: 'Displays & POS', to: '/devices', group: 'Beheer' },
    { label: 'Event types', to: '/event-types', group: 'Instellingen' },
    { label: 'Verblijfsopties', to: '/stay-options', group: 'Instellingen' },
]

const statusLabel = computed(() => auth.user ? `${auth.user.name} · admin` : null)

onMounted(() => {
    auth.initialize()
    window.addEventListener('backoffice-auth-required', () => {
        auth.user = null
        auth.initialized = true
    })
})

async function handleLogout() {
    await auth.logout()
}
</script>
