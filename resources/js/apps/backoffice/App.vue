<template>
    <AppShell
        title="PlayDrive"
        subtitle="Backoffice"
        :navigation="navigation"
    />
</template>

<script setup>
import AppShell from '../../shared/layouts/AppShell.vue'

const navigation = [
    { label: 'Productbeheer', to: '/', group: 'Verkoop' },
    { label: 'Cateringopties', to: '/catering-options', group: 'Verkoop' },
    { label: 'Personeel', to: '/staff', group: 'Beheer' },
    { label: 'Displays & POS', to: '/devices', group: 'Beheer' },
    { label: 'Event types', to: '/event-types', group: 'Instellingen' },
    { label: 'Verblijfsopties', to: '/stay-options', group: 'Instellingen' },
]
</script>
