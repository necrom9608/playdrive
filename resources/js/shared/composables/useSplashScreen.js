import { ref } from 'vue'

export function useSplashScreen(minDuration = 2000) {
    const showSplash = ref(true)
    const startedAt = Date.now()

    async function hideSplash() {
        const elapsed = Date.now() - startedAt
        const remaining = Math.max(0, minDuration - elapsed)

        if (remaining > 0) {
            await new Promise(resolve => setTimeout(resolve, remaining))
        }

        showSplash.value = false
    }

    return {
        showSplash,
        hideSplash,
    }
}
