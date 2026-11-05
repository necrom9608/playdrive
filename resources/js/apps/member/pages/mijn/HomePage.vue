<template>
    <div class="px-4 pt-4 pb-6 space-y-4">

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
            <div class="grid grid-cols-4 gap-2">
                <button
                    v-for="action in quickActions"
                    :key="action.label"
                    class="glass-card rounded-2xl px-1 py-3.5 flex flex-col items-center gap-2 text-center"
                    @click="router.push(action.to)"
                >
                    <div class="w-9 h-9 rounded-xl flex items-center justify-center" :class="action.color">
                        <component :is="action.icon" class="w-5 h-5" />
                    </div>
                    <span class="text-[11px] text-slate-300 font-medium leading-tight">{{ action.label }}</span>
                </button>
            </div>
            <!-- Stats (arcade-scores) -->
            <button v-if="arcade" class="w-full text-left" @click="router.push('/mijn/stats')">
                <div class="glass-card rounded-3xl overflow-hidden relative">
                    <img
                        v-if="arcadeBanner"
                        :src="arcadeBanner.url"
                        alt=""
                        class="w-full h-24 object-cover"
                    />
                    <div class="p-5">
                        <div class="flex items-start justify-between gap-3 mb-3">
                            <div class="min-w-0">
                                <p class="text-xs text-slate-400 mb-0.5">Stats</p>
                                <p class="text-sm font-medium text-white truncate">
                                    {{ arcade.linked ? arcade.players.map(p => p.name).join(' · ') : 'Je scores op de dansmachines' }}
                                </p>
                            </div>
                            <div class="w-9 h-9 rounded-xl bg-amber-500/15 border border-amber-500/25 flex items-center justify-center shrink-0">
                                <TrophyIcon class="w-5 h-5 text-amber-400" />
                            </div>
                        </div>

                        <div
                            v-if="newDethrones.length"
                            class="mb-3 rounded-2xl bg-rose-500/10 border border-rose-500/30 px-3.5 py-3"
                        >
                            <p class="text-sm font-medium text-rose-200">
                                {{ newDethrones.length === 1
                                    ? `${newDethrones[0].by} nam je #1 over op ${newDethrones[0].title}`
                                    : `Je bent je #1 kwijt op ${newDethrones.length} charts` }}
                            </p>
                            <p class="text-xs text-rose-300/80 mt-0.5">Pak ze terug ›</p>
                        </div>

                        <template v-if="arcade.linked && lastPlay">
                            <div class="flex items-center gap-3">
                                <div class="flex-1 min-w-0">
                                    <p class="text-sm text-slate-200 truncate">{{ lastPlay.title }}</p>
                                    <p class="text-[11px] text-slate-400 truncate">{{ lastPlay.short }} · {{ String(lastPlay.chart ?? '').toUpperCase() }}</p>
                                </div>
                                <div class="text-right shrink-0">
                                    <p class="text-sm font-semibold text-white tabular-nums">{{ lastPlay.score === null ? 'gespeeld' : nf.format(lastPlay.score) }}</p>
                                    <span v-if="lastPlay.is_new_best" class="inline-flex items-center gap-1 text-[9px] font-bold px-1.5 py-0.5 rounded-md bg-yellow-400/15 text-yellow-300 border border-yellow-400/30">
                                        <StarIcon class="w-2.5 h-2.5" />RECORD
                                    </span>
                                </div>
                            </div>
                            <div class="flex gap-2 mt-3">
                                <span class="text-[11px] px-2 py-1 rounded-lg bg-slate-800/60 border border-slate-700/40 text-slate-300">
                                    {{ nf.format(arcade.stats.total_plays) }} speelbeurten
                                </span>
                                <span v-if="arcade.stats.first_places" class="text-[11px] px-2 py-1 rounded-lg bg-amber-500/10 border border-amber-500/25 text-amber-300">
                                    #1 op {{ arcade.stats.first_places }} {{ arcade.stats.first_places === 1 ? 'chart' : 'charts' }}
                                </span>
                            </div>
                        </template>
                        <p v-else-if="arcade.linked" class="text-xs text-slate-400">Nog geen scores. Speel een potje en ze verschijnen hier.</p>
                        <p v-else class="text-xs text-slate-400 leading-relaxed">
                            Tik je lidkaart op de kaartlezer van een dansmachine en je scores verschijnen hier.
                        </p>

                        <div class="mt-3 flex items-center gap-1 text-xs text-blue-400">
                            <span>Bekijk je stats</span>
                            <ChevronRightIcon class="w-3.5 h-3.5" />
                        </div>
                    </div>
                </div>
            </button>

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
import { computed, ref, watch } from 'vue'
import { useRouter } from 'vue-router'
import {
    MapPinIcon,
    QrCodeIcon,
    IdentificationIcon,
    ChevronRightIcon,
    TicketIcon,
    GiftIcon,
    TrophyIcon,
    CalendarDaysIcon,
    StarIcon,
} from '@heroicons/vue/24/outline'
import { arcadeApi } from '../../services/api'
import { useVenueStore } from '../../stores/useVenueStore'
import StatusBadge from '../../components/StatusBadge.vue'

const router = useRouter()
const venue = useVenueStore()

const membershipLabel = computed(() => {
    const s = venue.activeVenue?.membership_status
    if (s === 'active') return venue.membership?.membership_type ?? 'Actief lid'
    if (s === 'expired') return 'Lidmaatschap verlopen'
    return 'Bezoekersprofiel'
})

const quickActions = [
    { label: 'Stats',       to: '/mijn/stats',       icon: TrophyIcon,       color: 'bg-amber-500/15 border border-amber-500/25 text-amber-400' },
    { label: 'Reservaties', to: '/mijn/reservaties', icon: CalendarDaysIcon, color: 'bg-blue-500/15 border border-blue-500/25 text-blue-400' },
    { label: 'Tickets',     to: '/mijn/tickets',     icon: TicketIcon,       color: 'bg-violet-500/15 border border-violet-500/25 text-violet-400' },
    { label: 'Bonnen',      to: '/mijn/bonnen',      icon: GiftIcon,         color: 'bg-emerald-500/15 border border-emerald-500/25 text-emerald-400' },
]

// Stats-kaart: arcade-overzicht van de gekozen venue (geen kaart als de venue geen arcade heeft)
const nf = new Intl.NumberFormat('nl-BE')
const arcade = ref(null)
const lastPlay = computed(() => arcade.value?.recent?.[0] ?? null)
const newDethrones = computed(() => (arcade.value?.dethrones ?? []).filter(d => d.is_new))
const arcadeBanner = computed(() => {
    const games = arcade.value?.games ?? []
    const key = lastPlay.value?.game ?? arcade.value?.stats?.per_game?.[0]?.game
    return games.find(g => g.key === key)?.banner ?? games.find(g => g.banner)?.banner ?? null
})

async function loadArcade(slug) {
    arcade.value = null
    if (!slug) return
    try {
        const { data } = await arcadeApi.summary(slug)
        // Venue zonder ritmekasten/scores en niet gekoppeld → geen kaart tonen
        const hasArcade = data.linked || data.has_arcade
        arcade.value = hasArcade ? data : null
    } catch {
        arcade.value = null
    }
}

watch(() => venue.activeSlug, loadArcade, { immediate: true })
</script>
