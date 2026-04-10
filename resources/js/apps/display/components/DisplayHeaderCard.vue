<template>
    <section class="rounded-[2rem] border border-slate-800 bg-slate-900/70 px-5 py-5 shadow-2xl shadow-slate-950/40">
        <DisplayTenantLogo :src="tenantLogoUrl" :tenant-name="tenantName" class="mx-auto h-16 w-full" />
        <div class="mt-5 text-xs uppercase tracking-[0.22em] text-blue-300/80">Reservatie</div>
        <h1 class="mt-2 text-3xl font-bold leading-tight">{{ reservationName || 'Reservatie' }}</h1>
    </section>
</template>

<script setup>
import DisplayTenantLogo from './DisplayTenantLogo.vue'

defineProps({
    tenantName: {
        type: String,
        default: 'PlayDrive',
    },
    tenantLogoUrl: {
        type: String,
        default: '',
    },
    reservationName: {
        type: String,
        default: '',
    },
})
</script>
