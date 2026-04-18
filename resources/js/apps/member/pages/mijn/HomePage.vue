<template>
    <div class="px-4 pt-3 pb-6 space-y-4">
        <!-- Onboarding — geen venues -->
        <div v-if="!venue.loading && venue.venues.length === 0" class="glass-card rounded-3xl p-8 text-center space-y-4 mt-8">
            <div class="w-16 h-16 rounded-2xl bg-blue-500/15 border border-blue-500/25 flex items-center justify-center mx-auto">
                <MapPinIcon class="w-8 h-8 text-blue-400" />
            </div>
            <div>
                <h2 class="text-lg font-semibold text-white mb-1">Voeg je eerste venue toe</h2>
                <p class="text-sm text-slate-400">Scan de QR-code bij een PlayDrive-venue of zoek er zelf een op.</p>
            </div>
            <div class="space-y-2">
                <button class="member-btn-primary w-full flex items-center justify-center gap-2" @click="router.push('/ontdekken')">
                    <QrCodeIcon class="w-4 h-4" />
                    QR scannen
                </button>
                <button class="member-btn-ghost w-full" @click="router.push('/ontdekken')">
                    Venues ontdekken
                </button>
            </div>
        </div>

        <!-- Dashboard — met venues -->
        <template v-else-if="venue.activeVenue">
            <!-- Welkom -->
            <div class="flex items-center justify-between pt-1">
                <div>
                    <p class="text-xs text-slate-400">Welkom terug</p>
                    <h2 class="text-xl font-semibold text-white">{{ auth.account?.first_name }}</h2>
                </div>
                <div class="w-10 h-10 rounded-full bg-blue-500/20 border border-blue-500/30 flex items-center justify-center">
                    <span class="text-sm font-semibold text-blue-300">{{ initials }}</span>
                </div>
            </div>

            <!-- Sub-navigatie -->
            <div class="flex gap-2 overflow-x-auto pb-1 scrollbar-none">
                <button
                    v-for="tab in subTabs"
                    :key="tab.to"
                    class="flex items-center gap-1.5 px-3 py-2 rounded-xl text-xs font-medium whitespace-nowrap transition-colors shrink-0"
                    :class="route.path === tab.to
                        ? 'bg-blue-500/20 border border-blue-500/30 text-blue-300'
                        : 'bg-slate-800/50 border border-slate-700/40 text-slate-400'"
                    @click="router.push(tab.to)"
                >
                    <component :is="tab.icon" class="w-3.5 h-3.5" />
                    {{ tab.label }}
                </button>
            </div>

            <!-- Lidmaatschap kaart -->
            <button class="w-full text-left" @click="router.push('/mijn/lidmaatschap')">
                <div class="glass-card rounded-3xl p-5 relative overflow-hidden">
                    <div class="absolute top-0 right-0 w-32 h-32 bg-blue-500/10 rounded-full -translate-y-8 translate-x-8 blur-2xl pointer-events-none" />
                    <div class="flex items-start justify-between mb-4">
                        <div>
                            <p class="text-xs text-slate-400 mb-0.5">Lidmaatschap</p>
                            <p class="text-sm font-medium text-white">{{ venue.activeVenue.name }}</p>
                        </div>
                        <StatusBadge :status="venue.activeVenue.membership_status" />
                    </div>
                    <div class="flex items-center gap-2 text-slate-300">
                        <IdentificationIcon class="w-4 h-4 text-slate-400" />
                        <span class="text-sm">{{ membershipLabel }}</span>
                    </div>
                    <div class="mt-3 flex items-center gap-1 text-xs text-blue-400">
                        <span>Toon QR-code</span>
                        <ChevronRightIcon class="w-3.5 h-3.5" />
                    </div>
                </div>
            </button>

            <!-- Snelle acties -->
            <div class="grid grid-cols-3 gap-3">
                <button
                    v-for="action in quickActions"
                    :key="action.label"
                    class="glass-card rounded-2xl p-4 flex flex-col items-center gap-2 text-center"
                    @click="router.push(action.to)"
                >
                    <div class="w-9 h-9 rounded-xl flex items-center justify-center" :class="action.color">
                        <component :is="action.icon" class="w-5 h-5" />
                    </div>
                    <span class="text-xs text-slate-300 font-medium leading-tight">{{ action.label }}</span>
                </button>
            </div>
        </template>

        <!-- Loading -->
        <div v-else class="flex justify-center pt-20">
            <div class="flex gap-2">
                <span class="loader-dot" /><span class="loader-dot" /><span class="loader-dot" />
            </div>
        </div>
    </div>
</template>

<script setup>
import { computed } from 'vue'
import { useRouter, useRoute } from 'vue-router'
import {
    MapPinIcon,
    QrCodeIcon,
    IdentificationIcon,
    ChevronRightIcon,
    TicketIcon,
    GiftIcon,
    TrophyIcon,
} from '@heroicons/vue/24/outline'
import { useAuthStore } from '../../stores/useAuthStore'
import { useVenueStore } from '../../stores/useVenueStore'
import StatusBadge from '../../components/StatusBadge.vue'

const router = useRouter()
const route = useRoute()
const auth = useAuthStore()
const venue = useVenueStore()

const initials = computed(() => {
    const a = auth.account
    if (!a) return '?'
    return `${a.first_name?.[0] ?? ''}${a.last_name?.[0] ?? ''}`.toUpperCase()
})

const membershipLabel = computed(() => {
    const s = venue.activeVenue?.membership_status
    if (s === 'active') return venue.membership?.membership_type ?? 'Actief lid'
    if (s === 'expired') return 'Lidmaatschap verlopen'
    return 'Bezoekersprofiel'
})

const subTabs = [
    { to: '/mijn/lidmaatschap', label: 'Lidmaatschap', icon: IdentificationIcon },
    { to: '/mijn/tickets', label: 'Tickets', icon: TicketIcon },
    { to: '/mijn/bonnen', label: 'Bonnen', icon: GiftIcon },
    { to: '/mijn/stats', label: 'Stats', icon: TrophyIcon },
]

const quickActions = [
    { label: 'Tickets', to: '/mijn/tickets', icon: TicketIcon, color: 'bg-violet-500/15 border border-violet-500/25 text-violet-400' },
    { label: 'Bonnen', to: '/mijn/bonnen', icon: GiftIcon, color: 'bg-emerald-500/15 border border-emerald-500/25 text-emerald-400' },
    { label: 'Stats', to: '/mijn/stats', icon: TrophyIcon, color: 'bg-amber-500/15 border border-amber-500/25 text-amber-400' },
]
</script>
