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
    { label: 'Product Categories', to: '/' },
    { label: 'Products', to: '/products' },
]
</script>
