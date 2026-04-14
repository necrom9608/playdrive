<template>
    <div class="text-red-500 text-2xl">SPLASH TEST</div>
    <transition name="splash-fade">
        <div v-if="props.visible" class="fixed inset-0 z-[9999] overflow-hidden bg-[#030814] text-white">
            <div class="absolute inset-0 bg-[radial-gradient(circle_at_20%_18%,rgba(59,130,246,0.18),transparent_30%),radial-gradient(circle_at_80%_78%,rgba(168,85,247,0.14),transparent_28%),radial-gradient(circle_at_top,#0f2d63_0%,#071327_46%,#030814_100%)]"></div>
            <div class="pointer-events-none absolute -left-16 -top-10 h-72 w-72 rounded-full bg-[rgba(59,130,246,0.20)] blur-3xl splash-drift-one"></div>
            <div class="pointer-events-none absolute -bottom-8 -right-10 h-64 w-64 rounded-full bg-[rgba(168,85,247,0.16)] blur-3xl splash-drift-two"></div>

            <div class="relative flex min-h-screen items-center justify-center p-6">
                <div class="splash-card relative w-full max-w-[520px] overflow-hidden rounded-[30px] border border-[rgba(75,98,148,0.28)] bg-[linear-gradient(180deg,rgba(12,26,58,0.96)_0%,rgba(8,18,43,0.92)_100%)] px-9 py-10 text-center shadow-[0_28px_80px_rgba(0,0,0,0.45),inset_0_1px_0_rgba(255,255,255,0.04)]">
                    <div class="relative mx-auto mb-5 inline-flex items-center justify-center">
                        <div class="absolute h-[150px] w-[150px] rounded-full bg-[rgba(59,130,246,0.22)] blur-3xl splash-pulse"></div>
                        <div class="absolute h-[110px] w-[110px] rounded-full bg-[rgba(168,85,247,0.16)] blur-3xl splash-pulse-delayed"></div>
                        <img
                            :src="props.logoSrc"
                            :alt="props.title"
                            class="relative z-[1] block h-[118px] w-[118px] object-contain drop-shadow-[0_0_18px_rgba(59,130,246,0.28)] splash-float"
                        />
                    </div>

                    <div class="splash-title mb-2 text-[38px] leading-none tracking-[0.12em] text-white">
                        {{ props.title }}
                    </div>

                    <div v-if="props.subtitle" class="mb-6 text-[15px] leading-6 text-slate-300/90">
                        {{ props.subtitle }}
                    </div>

                    <div v-if="props.showLoader" class="inline-flex items-center gap-2.5" aria-hidden="true">
                        <div class="h-2.5 w-2.5 rounded-full bg-[linear-gradient(180deg,#7db7ff_0%,#4f7dff_100%)] shadow-[0_0_14px_rgba(79,125,255,0.4)] splash-bounce"></div>
                        <div class="h-2.5 w-2.5 rounded-full bg-[linear-gradient(180deg,#7db7ff_0%,#4f7dff_100%)] shadow-[0_0_14px_rgba(79,125,255,0.4)] splash-bounce [animation-delay:0.14s]"></div>
                        <div class="h-2.5 w-2.5 rounded-full bg-[linear-gradient(180deg,#7db7ff_0%,#4f7dff_100%)] shadow-[0_0_14px_rgba(79,125,255,0.4)] splash-bounce [animation-delay:0.28s]"></div>
                    </div>

                    <div v-if="props.statusText" class="mt-4 text-xs uppercase tracking-[0.08em] text-slate-300/70">
                        {{ props.statusText }}
                    </div>
                </div>
            </div>
        </div>
    </transition>
</template>

<script setup>
const props = defineProps({
    visible: {
        type: Boolean,
        default: true,
    },
    title: {
        type: String,
        default: 'PLAYDRIVE',
    },
    subtitle: {
        type: String,
        default: 'Wordt opgestart…',
    },
    statusText: {
        type: String,
        default: 'Loading',
    },
    logoSrc: {
        type: String,
        default: '/images/logos/icon.png',
    },
    showLoader: {
        type: Boolean,
        default: true,
    },
})
</script>

<style scoped>
@font-face {
    font-family: 'MicroSquare';
    src: url('/fonts/MicroSquare-Regular.ttf') format('truetype');
    font-weight: normal;
    font-style: normal;
}

.splash-title {
    font-family: 'MicroSquare', 'Inter', 'Segoe UI', sans-serif;
}

.splash-card {
    animation: splash-card-in 0.7s ease-out both;
}

.splash-card::before {
    content: '';
    position: absolute;
    inset: 0;
    background: linear-gradient(
        115deg,
        transparent 25%,
        rgba(255, 255, 255, 0.08) 45%,
        rgba(255, 255, 255, 0.02) 52%,
        transparent 68%
    );
    transform: translateX(-160%);
    animation: splash-shine 3.4s ease-in-out infinite;
    pointer-events: none;
}

.splash-float {
    animation: splash-float 2.8s ease-in-out infinite;
}

.splash-pulse {
    animation: splash-pulse 2.4s ease-in-out infinite;
}

.splash-pulse-delayed {
    animation: splash-pulse 2.4s ease-in-out infinite 0.4s;
}

.splash-bounce {
    animation: splash-bounce 1.15s infinite ease-in-out;
}

.splash-drift-one {
    animation: splash-drift-one 7s ease-in-out infinite alternate;
}

.splash-drift-two {
    animation: splash-drift-two 8s ease-in-out infinite alternate;
}

.splash-fade-enter-active,
.splash-fade-leave-active {
    transition: opacity 0.35s ease;
}

.splash-fade-enter-from,
.splash-fade-leave-to {
    opacity: 0;
}

@keyframes splash-card-in {
    from {
        opacity: 0;
        transform: translateY(12px) scale(0.985);
    }
    to {
        opacity: 1;
        transform: translateY(0) scale(1);
    }
}

@keyframes splash-shine {
    0% {
        transform: translateX(-160%);
    }
    55%,
    100% {
        transform: translateX(180%);
    }
}

@keyframes splash-pulse {
    0%,
    100% {
        transform: scale(0.96);
        opacity: 0.72;
    }
    50% {
        transform: scale(1.05);
        opacity: 1;
    }
}

@keyframes splash-float {
    0%,
    100% {
        transform: translateY(0);
    }
    50% {
        transform: translateY(-3px);
    }
}

@keyframes splash-bounce {
    0%,
    80%,
    100% {
        transform: scale(0.72);
        opacity: 0.5;
    }
    40% {
        transform: scale(1);
        opacity: 1;
    }
}

@keyframes splash-drift-one {
    from {
        transform: translate3d(0, 0, 0);
    }
    to {
        transform: translate3d(22px, 14px, 0);
    }
}

@keyframes splash-drift-two {
    from {
        transform: translate3d(0, 0, 0);
    }
    to {
        transform: translate3d(-18px, -12px, 0);
    }
}
</style>
