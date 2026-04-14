<template>
  <div>
    <SplashScreen
      :visible="showSplash"
      title="PLAYDRIVE"
      subtitle="Staff wordt opgestart…"
      status-text="Staff"
    />
    <StaffLayout v-if="auth.initialized && auth.isAuthenticated" />
    <StaffLoginPage v-else-if="auth.initialized" />
  </div>
</template>

<script setup>
import { onBeforeUnmount, onMounted } from 'vue'
import StaffLayout from './layouts/StaffLayout.vue'
import StaffLoginPage from './pages/StaffLoginPage.vue'
import { useStaffAuthStore } from './stores/authStore'
import SplashScreen from '../../shared/components/SplashScreen.vue'
import { useSplashScreen } from '../../shared/composables/useSplashScreen'

const auth = useStaffAuthStore()
const { showSplash, hideSplash } = useSplashScreen(1200)

function handleAuthRequired() {
  auth.user = null
  auth.initialized = true
}

onMounted(async () => {
  window.addEventListener('staff-auth-required', handleAuthRequired)
  await auth.initialize()
  await hideSplash()
})

onBeforeUnmount(() => {
  window.removeEventListener('staff-auth-required', handleAuthRequired)
})
</script>
