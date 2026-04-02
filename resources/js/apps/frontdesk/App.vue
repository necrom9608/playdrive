<template>
    <div>
        <FrontdeskLayout v-if="auth.initialized && auth.isAuthenticated" />
        <FrontdeskLoginModal v-if="auth.initialized && !auth.isAuthenticated" />
    </div>
</template>

<script setup>
import { onMounted, onBeforeUnmount } from 'vue'
import FrontdeskLayout from './layouts/FrontdeskLayout.vue'
import FrontdeskLoginModal from './components/FrontdeskLoginModal.vue'
import { useAuthStore } from './stores/authStore'

const auth = useAuthStore()

function handleAuthRequired() {
    auth.user = null
    auth.initialized = true
}

onMounted(async () => {
    window.addEventListener('frontdesk-auth-required', handleAuthRequired)
    await auth.initialize()
})

onBeforeUnmount(() => {
    window.removeEventListener('frontdesk-auth-required', handleAuthRequired)
})
</script>
