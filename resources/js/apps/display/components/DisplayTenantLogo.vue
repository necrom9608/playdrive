<template>
    <div class="flex items-center justify-center">
        <img
            v-if="src"
            :src="src"
            :alt="tenantName"
            class="max-h-full max-w-full object-contain"
            @error="failed = true"
        />

        <div v-else class="text-center">
            <div class="text-5xl">🎮</div>
            <div class="mt-3 text-2xl font-bold text-white">
                {{ tenantName }}
            </div>
        </div>
    </div>
</template>

<script setup>
import { computed, ref } from 'vue'

const failed = ref(false)

const tenantName = computed(() => {
    return window.PlayDrive?.tenantName || 'PlayDrive'
})

const src = computed(() => {
    const url = window.PlayDrive?.tenantLogoUrl || ''

    // 🔥 extra debug (mag je straks weer verwijderen)
    console.log('Tenant logo URL:', url)

    return failed.value ? '' : url
})
</script>
