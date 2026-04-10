<template>
    <div class="flex flex-1 flex-col items-center justify-center text-center">
        <div class="w-full rounded-[2rem] border border-slate-800 bg-slate-900/60 px-8 py-10 shadow-2xl shadow-slate-950/50">
            <DisplayTenantLogo :src="tenantLogoUrl" :tenant-name="tenantName" class="mx-auto h-32 w-full" />
        </div>
    </div>
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
})
</script>
