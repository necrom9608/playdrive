<template>
    <AppShell
        title="PlayDrive"
        subtitle="Frontdesk"
        :navigation="navigation"
    />
</template>

<script setup>
import AppShell from '../../shared/layouts/AppShell.vue'

const navigation = [
    { label: 'Dashboard', to: '/' },
    { label: 'POS', to: '/pos' },
    { label: 'Sales', to: '/sales' },
    { label: 'Vouchers', to: '/vouchers' },
    { label: 'Agenda', to: '/agenda' },
    { label: 'Staff', to: '/staff' },
]
</script>
