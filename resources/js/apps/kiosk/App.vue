<template>
    <div>
        <SplashScreen
            :visible="showSplash"
            title="PLAYDRIVE"
            subtitle="Kiosk wordt opgestart…"
            status-text="Kiosk"
        />

        <div class="min-h-screen bg-slate-950 p-8 text-white">
            <h1 class="text-3xl font-bold">PlayDrive Kiosk</h1>
            <p class="mt-2 text-slate-400">Vue + Pinia werkt.</p>
        </div>
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
