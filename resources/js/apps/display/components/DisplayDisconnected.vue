<template>
    <div class="flex flex-1 flex-col items-center justify-center text-center">
        <div class="w-full rounded-[2rem] border border-slate-800 bg-slate-900/70 px-6 py-8 shadow-2xl shadow-slate-950/50">
            <DisplayTenantLogo :src="tenantLogoUrl" :tenant-name="tenantName" class="mx-auto h-24 w-full" />
            <p class="mt-6 text-sm uppercase tracking-[0.28em] text-blue-300/80">Customer display</p>
            <h1 class="mt-3 text-3xl font-bold leading-tight">{{ tenantName }}</h1>
            <p class="mt-4 text-base text-slate-400">
                Dit toestel is nog niet gekoppeld aan een POS-terminal.
            </p>

            <div class="mt-8 rounded-[1.75rem] border border-slate-700 bg-slate-950/70 px-6 py-6">
                <div class="text-xs uppercase tracking-[0.22em] text-slate-400">Koppelcode</div>
                <div class="mt-3 break-all text-2xl font-semibold tracking-wide text-white">{{ pairingCode || '...' }}</div>
                <div class="mt-4 text-sm text-slate-500">
                    Gebruik deze code in backoffice of via de eenmalige POS-koppeling.
                </div>
            </div>
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
    pairingCode: {
        type: String,
        default: '',
    },
})
</script>
