<template>
    <PlaceholderPage
        title="Tickets"
        :icon="TicketIcon"
        icon-bg="bg-violet-500/15 border border-violet-500/25"
        icon-color="text-violet-400"
        description="Bekijk en beheer je reservaties. Maak een nieuw bezoek vast voor jouw favoriete venue."
    />
</template>

<script setup>
import { TicketIcon } from '@heroicons/vue/24/outline'
import PlaceholderPage from '../../components/PlaceholderPage.vue'
</script>
