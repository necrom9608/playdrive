<template>
    <section class="overflow-hidden rounded-[2rem] border border-white/10 bg-slate-900/65 p-5 shadow-2xl shadow-slate-950/40 backdrop-blur-xl">
        <div class="flex items-center gap-5">
            <div class="h-16 w-52 max-w-[45%] flex-shrink-0">
                <DisplayTenantLogo :src="tenantLogoUrl" :tenant-name="tenantName" class="h-full w-full" />
            </div>

            <div class="min-w-0 flex-1 rounded-[1.5rem] bg-white/[0.03] px-4 py-4">
                <div class="flex flex-wrap items-center gap-2">
                    <div class="inline-flex items-center gap-2 rounded-full border border-emerald-400/20 bg-emerald-500/10 px-3 py-1 text-[11px] uppercase tracking-[0.22em] text-emerald-100/90">
                        <span class="h-2.5 w-2.5 rounded-full bg-emerald-300 live-dot"></span>
                        Live reservering
                    </div>

                    <div
                        v-for="tag in reservationTags"
                        :key="`${tag.kind}-${tag.label}`"
                        :class="[
                            'inline-flex items-center gap-2 rounded-full border px-3 py-1 text-[11px] font-semibold tracking-[0.18em] uppercase shadow-lg',
                            tag.classes,
                        ]"
                    >
                        <component :is="tag.icon" class="h-3.5 w-3.5" />
                        <span class="truncate">{{ tag.label }}</span>
                    </div>
                </div>

                <Transition name="header-title" mode="out-in">
                    <h1
                        :key="reservationName || 'reservation-empty'"
                        class="mt-3 truncate text-[2rem] font-black leading-tight tracking-tight text-white"
                    >
                        {{ reservationName || 'Reservatie' }}
                    </h1>
                </Transition>
            </div>
        </div>
    </section>
</template>

<script setup>
import { computed } from 'vue'
import {
    CakeIcon,
    CheckBadgeIcon,
    ClockIcon,
    SparklesIcon,
    TagIcon,
} from '@heroicons/vue/24/outline'
import DisplayTenantLogo from './DisplayTenantLogo.vue'

const props = defineProps({
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
    reservation: {
        type: Object,
        default: () => ({}),
    },
})

const statusStyles = {
    new: 'border-sky-400/20 bg-sky-500/10 text-sky-100/90',
    confirmed: 'border-indigo-400/20 bg-indigo-500/10 text-indigo-100/90',
    checked_in: 'border-emerald-400/20 bg-emerald-500/10 text-emerald-100/90',
    checked_out: 'border-violet-400/20 bg-violet-500/10 text-violet-100/90',
    paid: 'border-cyan-400/20 bg-cyan-500/10 text-cyan-100/90',
    cancelled: 'border-rose-400/20 bg-rose-500/10 text-rose-100/90',
    no_show: 'border-amber-400/20 bg-amber-500/10 text-amber-100/90',
}

function statusLabel(value) {
    return String(value || '')
        .replace(/_/g, ' ')
        .trim()
}

const reservationTags = computed(() => {
    const tags = []
    const eventType = props.reservation?.event_type || props.reservation?.eventType?.name || ''
    const catering = props.reservation?.catering_option || props.reservation?.cateringOption?.name || ''
    const status = props.reservation?.status || ''

    if (eventType) {
        tags.push({
            kind: 'event_type',
            label: eventType,
            icon: CakeIcon,
            classes: 'border-fuchsia-400/20 bg-fuchsia-500/10 text-fuchsia-100/90',
        })
    }

    if (catering) {
        tags.push({
            kind: 'catering',
            label: catering,
            icon: SparklesIcon,
            classes: 'border-cyan-400/20 bg-cyan-500/10 text-cyan-100/90',
        })
    }

    if (status) {
        tags.push({
            kind: 'status',
            label: statusLabel(status),
            icon: status === 'checked_in' ? CheckBadgeIcon : (status === 'confirmed' ? ClockIcon : TagIcon),
            classes: statusStyles[status] || 'border-white/10 bg-white/5 text-slate-100/90',
        })
    }

    return tags
})
</script>

<style scoped>
.live-dot {
    animation: live-pulse 1.8s ease-in-out infinite;
    box-shadow: 0 0 0 0 rgba(110, 231, 183, 0.45);
}

@keyframes live-pulse {
    0% {
        transform: scale(0.95);
        box-shadow: 0 0 0 0 rgba(110, 231, 183, 0.45);
        opacity: 0.9;
    }
    70% {
        transform: scale(1.1);
        box-shadow: 0 0 0 10px rgba(110, 231, 183, 0);
        opacity: 1;
    }
    100% {
        transform: scale(0.95);
        box-shadow: 0 0 0 0 rgba(110, 231, 183, 0);
        opacity: 0.9;
    }
}

.header-title-enter-active,
.header-title-leave-active {
    transition: all 220ms ease;
}

.header-title-enter-from {
    opacity: 0;
    transform: translateY(8px);
}

.header-title-leave-to {
    opacity: 0;
    transform: translateY(-8px);
}
</style>
