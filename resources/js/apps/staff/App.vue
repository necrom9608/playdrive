<template>
  <div>
    <StaffLayout v-if="auth.initialized && auth.isAuthenticated" />
    <StaffLoginPage v-else-if="auth.initialized" />
  </div>
</template>

<script setup>
import { onBeforeUnmount, onMounted } from 'vue'
import StaffLayout from './layouts/StaffLayout.vue'
import StaffLoginPage from './pages/StaffLoginPage.vue'
import { useStaffAuthStore } from './stores/authStore'

const auth = useStaffAuthStore()

function handleAuthRequired() {
  auth.user = null
  auth.initialized = true
}

onMounted(async () => {
  window.addEventListener('staff-auth-required', handleAuthRequired)
  await auth.initialize()
})

onBeforeUnmount(() => {
  window.removeEventListener('staff-auth-required', handleAuthRequired)
})
</script>
