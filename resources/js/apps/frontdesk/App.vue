<template>

    <div>
        <SplashScreen
            :visible="showSplash"
            title="PLAYDRIVE"
            subtitle="Frontdesk wordt opgestart…"
            status-text="Frontdesk"
        />

        <FrontdeskLayout v-if="!showSplash && auth.initialized && auth.isAuthenticated" />
        <FrontdeskLoginModal v-if="!showSplash && auth.initialized && !auth.isAuthenticated" />
    </div>
</template>

<script setup>
import { onMounted, onBeforeUnmount, nextTick } from 'vue'
import FrontdeskLayout from './layouts/FrontdeskLayout.vue'
import FrontdeskLoginModal from './components/FrontdeskLoginModal.vue'
import { useAuthStore } from './stores/authStore'
import SplashScreen from '../../shared/components/SplashScreen.vue'
import { useSplashScreen } from '../../shared/composables/useSplashScreen'

const auth = useAuthStore()
const { showSplash, hideSplash } = useSplashScreen(2000)

function handleAuthRequired() {
    auth.user = null
    auth.initialized = true
}

function waitForPaint() {
    return new Promise((resolve) => {
        requestAnimationFrame(() => {
            requestAnimationFrame(resolve)
        })
    })
}

onMounted(async () => {
    window.addEventListener('frontdesk-auth-required', handleAuthRequired)

    try {
        await nextTick()
        await waitForPaint()
        await auth.initialize()
    } finally {
        await hideSplash()
    }
})

onBeforeUnmount(() => {
    window.removeEventListener('frontdesk-auth-required', handleAuthRequired)
})
</script>
