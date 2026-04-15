<template>
    <div class="relative flex flex-1 flex-col items-center justify-center overflow-hidden text-center">
        <div class="pointer-events-none absolute inset-0">
            <div class="absolute left-[8%] top-[14%] h-56 w-56 rounded-full bg-cyan-400/10 blur-3xl ambient-orb ambient-orb-a"></div>
            <div class="absolute right-[10%] top-[18%] h-64 w-64 rounded-full bg-blue-500/12 blur-3xl ambient-orb ambient-orb-b"></div>
            <div class="absolute bottom-[10%] left-[28%] h-72 w-72 rounded-full bg-indigo-500/10 blur-3xl ambient-orb ambient-orb-c"></div>
        </div>

        <div class="relative w-full overflow-hidden rounded-[2.25rem] border border-white/10 bg-slate-900/60 px-10 py-12 shadow-2xl shadow-slate-950/50 backdrop-blur-xl">
            <div class="absolute inset-0 bg-[radial-gradient(circle_at_top,rgba(34,211,238,0.08),transparent_45%)]"></div>

            <div class="relative mx-auto h-40 w-full max-w-[24rem] standby-logo-float">
                <DisplayTenantLogo :src="tenantLogoUrl" :tenant-name="tenantName" class="h-full w-full justify-center" @pointerdown="emit('logo-hold-start')" @pointerup="emit('logo-hold-end')" @pointerleave="emit('logo-hold-end')" @pointercancel="emit('logo-hold-end')" />
            </div>

            <div class="relative mt-8 text-xs uppercase tracking-[0.32em] text-cyan-300/75">Welkom</div>
            <div class="relative mt-3 text-4xl font-black tracking-tight text-white">{{ tenantName }}</div>
            <div class="relative mt-4 text-lg text-slate-300">Je bestelling verschijnt hier automatisch zodra onze medewerker alles klaarzet.</div>

            <div class="relative mt-8 flex flex-wrap items-center justify-center gap-3">
                <div class="rounded-full border border-white/10 bg-white/5 px-4 py-2 text-sm text-slate-200">Live bestellingen</div>
                <div class="rounded-full border border-cyan-400/20 bg-cyan-500/10 px-4 py-2 text-sm text-cyan-100">Realtime updates</div>
                <div class="rounded-full border border-blue-400/20 bg-blue-500/10 px-4 py-2 text-sm text-blue-100">Direct zichtbaar</div>
            </div>

            <Transition name="tip-fade" mode="out-in">
                <div :key="currentTip" class="relative mt-8 text-base text-slate-400">
                    {{ currentTip }}
                </div>
            </Transition>
        </div>
    </div>
</template>

<script setup>
import { computed, onBeforeUnmount, onMounted, ref } from 'vue'
import DisplayTenantLogo from './DisplayTenantLogo.vue'

const emit = defineEmits(['logo-hold-start', 'logo-hold-end'])

const props = defineProps({
    tenantName: {
        type: String,
        default: 'PlayDrive',
    },
    tenantLogoUrl: {
        type: String,
        default: '',
    },
})

const tips = computed(() => [
    `Welkom bij ${props.tenantName}`,
    'Je bestelling verschijnt hier live zodra er iets wordt toegevoegd.',
    'Drankjes, snacks en extra’s zie je meteen op dit scherm.',
])
const tipIndex = ref(0)
let tipInterval = null

const currentTip = computed(() => tips.value[tipIndex.value] ?? '')

onMounted(() => {
    tipInterval = window.setInterval(() => {
        tipIndex.value = (tipIndex.value + 1) % tips.value.length
    }, 4200)
})

onBeforeUnmount(() => {
    if (tipInterval) {
        clearInterval(tipInterval)
    }
})
</script>

<style scoped>
.standby-logo-float {
    animation: gentle-float 4.8s ease-in-out infinite;
}

.ambient-orb {
    animation-duration: 14s;
    animation-iteration-count: infinite;
    animation-timing-function: ease-in-out;
}

.ambient-orb-a {
    animation-name: orb-a;
}

.ambient-orb-b {
    animation-name: orb-b;
}

.ambient-orb-c {
    animation-name: orb-c;
}

.tip-fade-enter-active,
.tip-fade-leave-active {
    transition: all 260ms ease;
}

.tip-fade-enter-from,
.tip-fade-leave-to {
    opacity: 0;
    transform: translateY(6px);
}

@keyframes gentle-float {
    0%,
    100% {
        transform: translateY(0);
    }
    50% {
        transform: translateY(-6px);
    }
}

@keyframes orb-a {
    0%,
    100% {
        transform: translate3d(0, 0, 0) scale(1);
    }
    50% {
        transform: translate3d(28px, -16px, 0) scale(1.08);
    }
}

@keyframes orb-b {
    0%,
    100% {
        transform: translate3d(0, 0, 0) scale(1);
    }
    50% {
        transform: translate3d(-30px, 24px, 0) scale(1.1);
    }
}

@keyframes orb-c {
    0%,
    100% {
        transform: translate3d(0, 0, 0) scale(1);
    }
    50% {
        transform: translate3d(18px, -22px, 0) scale(1.06);
    }
}
</style>
