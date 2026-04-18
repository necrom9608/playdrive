<template>
    <PlaceholderPage
        title="Bonnen"
        :icon="GiftIcon"
        icon-bg="bg-emerald-500/15 border border-emerald-500/25"
        icon-color="text-emerald-400"
        description="Jouw digitale wallet voor cadeaubonnen. Toon de QR-code aan de balie om een bon in te wisselen."
    />
</template>

<script setup>
import { GiftIcon } from '@heroicons/vue/24/outline'
import PlaceholderPage from '../../components/PlaceholderPage.vue'
</script>
