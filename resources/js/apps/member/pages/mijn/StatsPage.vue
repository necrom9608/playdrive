<template>
    <PlaceholderPage
        title="Stats"
        :icon="TrophyIcon"
        icon-bg="bg-amber-500/15 border border-amber-500/25"
        icon-color="text-amber-400"
        description="Je persoonlijke stats, achievements en scores per venue. Hoe ver raak jij?"
    />
</template>

<script setup>
import { TrophyIcon } from '@heroicons/vue/24/outline'
import PlaceholderPage from '../../components/PlaceholderPage.vue'
</script>
