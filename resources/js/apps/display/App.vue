<template>
    <div>
        <SplashScreen
            :visible="showSplash"
            title="PLAYDRIVE"
            subtitle="Display wordt opgestart…"
            status-text="Display"
        />
        <router-view />
    </div>
</template>

<script setup>
import { onMounted } from 'vue'
import SplashScreen from '../../shared/components/SplashScreen.vue'
import { useSplashScreen } from '../../shared/composables/useSplashScreen'

const { showSplash, hideSplash } = useSplashScreen(1200)

onMounted(async () => {
    await hideSplash()
})
</script>
