<template>
    <div>
        <SplashScreen
            :visible="showSplash"
            title="PLAYDRIVE"
            subtitle="Jouw speelwereld"
            status-text="Member"
        />

        <template v-if="!showSplash">
            <AppLayout v-if="layout === 'app'" />
            <AuthLayout v-else />
        </template>
    </div>
</template>

<script setup>
import { computed, onMounted } from 'vue'
import { useRoute } from 'vue-router'
import SplashScreen from '../../shared/components/SplashScreen.vue'
import { useSplashScreen } from '../../shared/composables/useSplashScreen'
import { useAuthStore } from './stores/useAuthStore'
import { useVenueStore } from './stores/useVenueStore'
import AppLayout from './layouts/AppLayout.vue'
import AuthLayout from './layouts/AuthLayout.vue'

const route = useRoute()
const auth = useAuthStore()
const venue = useVenueStore()
const { showSplash, hideSplash } = useSplashScreen(1400)

const layout = computed(() => route.meta?.layout ?? 'auth')

onMounted(async () => {
    await auth.initialize()
    if (auth.isAuthenticated) {
        await venue.loadVenues()
    }
    await hideSplash()
})
</script>
