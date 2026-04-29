<template>
    <div v-if="!auth.initialized" class="flex min-h-screen items-center justify-center bg-slate-950 text-slate-400">
        Portal laden...
    </div>
    <PortalLoginCard v-else-if="!auth.isAuthenticated" />
    <AppShell
        v-else
        title="PlayDrive"
        :subtitle="subtitle"
        :navigation="navigation"
        :status-label="statusLabel"
        @logout="handleLogout"
    />
</template>

<script setup>
import { computed, onMounted } from 'vue'
import AppShell from '../../shared/layouts/AppShell.vue'
import PortalLoginCard from './components/PortalLoginCard.vue'
import { usePortalAuthStore } from './stores/authStore'

const auth = usePortalAuthStore()

const navigation = [
    { label: 'Dashboard', to: '/', group: '' },
    { label: 'Algemene info', to: '/info', group: 'Pagina' },
    { label: 'Foto\'s & video', to: '/media', group: 'Pagina' },
    { label: 'Activiteiten', to: '/activities', group: 'Pagina' },
    { label: 'Voorzieningen', to: '/amenities', group: 'Pagina' },
    { label: 'Externe links', to: '/links', group: 'Pagina' },
    { label: 'Publicatie', to: '/publication', group: 'Pagina' },
]

const subtitle = computed(() => auth.tenant?.display_name ?? 'Portal')

const statusLabel = computed(() => {
    if (!auth.user) return null
    return `${auth.user.name}`
})

onMounted(() => {
    auth.initialize()
    window.addEventListener('portal-auth-required', () => {
        auth.user = null
        auth.tenant = null
        auth.initialized = true
    })
})

async function handleLogout() {
    await auth.logout()
}
</script>
