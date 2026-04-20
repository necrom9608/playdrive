<template>
    <div v-if="!auth.initialized" class="flex min-h-screen items-center justify-center bg-slate-950 text-slate-400">
        Admin laden...
    </div>
    <AdminLoginCard v-else-if="!auth.isAuthenticated" />
    <AppShell
        v-else
        title="PlayDrive"
        subtitle="Admin"
        :navigation="navigation"
        :status-label="statusLabel"
        @logout="handleLogout"
    />
</template>

<script setup>
import { computed, onMounted } from 'vue'
import AppShell from '../../shared/layouts/AppShell.vue'
import AdminLoginCard from './components/AdminLoginCard.vue'
import { useAdminAuthStore } from './stores/authStore'

const auth = useAdminAuthStore()

const navigation = [
    { label: 'Tenants', to: '/tenants', group: 'Beheer' },
    { label: 'Medewerkers', to: '/staff', group: 'Beheer' },
    { label: 'E-mailtemplates', to: '/email-templates', group: 'Instellingen' },
    { label: "Regio's & vakanties", to: '/regions', group: 'Instellingen' },
]

const statusLabel = computed(() => auth.user ? `${auth.user.name} · admin` : null)

onMounted(() => {
    auth.initialize()
    window.addEventListener('admin-auth-required', () => {
        auth.user = null
        auth.initialized = true
    })
})

async function handleLogout() {
    await auth.logout()
}
</script>
