<template>
    <div class="flex flex-1 flex-col items-center justify-center text-center">
        <div class="w-full overflow-hidden rounded-[2.25rem] border border-white/10 bg-slate-900/65 px-8 py-10 shadow-2xl shadow-slate-950/50 backdrop-blur-xl">
            <div class="mx-auto h-24 w-full max-w-[20rem]">
                <DisplayTenantLogo :src="tenantLogoUrl" :tenant-name="tenantName" class="h-full w-full justify-center" />
            </div>
            <p class="mt-8 text-sm uppercase tracking-[0.28em] text-cyan-300/80">Customer display</p>
            <h1 class="mt-3 text-4xl font-black leading-tight tracking-tight text-white">{{ tenantName }}</h1>
            <p class="mt-4 text-lg text-slate-400">
                Dit toestel is nog niet gekoppeld aan een POS-terminal.
            </p>

            <div class="mx-auto mt-10 max-w-xl rounded-[1.75rem] border border-white/8 bg-black/20 px-6 py-6">
                <div class="text-xs uppercase tracking-[0.22em] text-slate-400">Koppelcode</div>
                <div class="mt-3 break-all text-3xl font-semibold tracking-wide text-white">{{ pairingCode || '...' }}</div>
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
