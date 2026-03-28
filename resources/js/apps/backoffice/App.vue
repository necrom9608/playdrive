<template>
    <AppShell
        title="PlayDrive"
        subtitle="Backoffice"
        :navigation="navigation"
    />
</template>

<script setup>
import {
    ArchiveBoxIcon,
    CakeIcon,
    ClockIcon,
    Squares2X2Icon,
    TagIcon,
    UsersIcon,
    CurrencyEuroIcon,
} from '@heroicons/vue/24/outline'
import AppShell from '../../shared/layouts/AppShell.vue'

const navigation = [
    { label: 'Product Categories', to: '/', icon: Squares2X2Icon },
    { label: 'Products', to: '/products', icon: TagIcon },
    { label: 'Catering Options', to: '/catering-options', icon: CakeIcon },
    { label: 'Event Types', to: '/event-types', icon: ArchiveBoxIcon },
    { label: 'Stay Options', to: '/stay-options', icon: ClockIcon },
    { label: 'Staff', to: '/staff', icon: UsersIcon },
    { label: 'Pricing Engine', to: '/pricing-engine', icon: CurrencyEuroIcon },
]
</script>
