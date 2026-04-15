<template>
    <div class="flex flex-1 flex-col items-center justify-center text-center">
        <div class="w-full overflow-hidden rounded-[2.25rem] border border-white/10 bg-slate-900/65 px-8 py-10 shadow-2xl shadow-slate-950/50 backdrop-blur-xl">
            <div class="mx-auto h-24 w-full max-w-[20rem]">
                <DisplayTenantLogo :src="tenantLogoUrl" :tenant-name="tenantName" class="h-full w-full justify-center" @pointerdown="emit('logo-hold-start')" @pointerup="emit('logo-hold-end')" @pointerleave="emit('logo-hold-end')" @pointercancel="emit('logo-hold-end')" />
            </div>
            <p class="mt-8 text-sm uppercase tracking-[0.28em] text-cyan-300/80">Customer display</p>
            <h1 class="mt-3 text-4xl font-black leading-tight tracking-tight text-white">{{ tenantName }}</h1>
            <div class="mt-6 text-3xl font-semibold tracking-tight text-white">{{ deviceName || 'Display' }}</div>
            <p class="mt-4 text-lg text-slate-400">
                Niet gekoppeld
            </p>
        </div>
    </div>
</template>

<script setup>
import DisplayTenantLogo from './DisplayTenantLogo.vue'

const emit = defineEmits(['logo-hold-start', 'logo-hold-end'])

defineProps({
    tenantName: {
        type: String,
        default: 'PlayDrive',
    },
    tenantLogoUrl: {
        type: String,
        default: '',
    },
    deviceName: {
        type: String,
        default: '',
    },
})
</script>
