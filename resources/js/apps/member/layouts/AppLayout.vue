<template>
    <div class="member-app relative flex flex-col h-screen overflow-hidden text-slate-100">

        <!-- Achtergrond -->
        <div class="absolute inset-0 bg-[#030814]" />
        <div class="absolute inset-0 bg-[radial-gradient(circle_at_20%_18%,rgba(59,130,246,0.18),transparent_30%),radial-gradient(circle_at_80%_78%,rgba(168,85,247,0.14),transparent_28%),radial-gradient(circle_at_top,#0f2d63_0%,#071327_46%,#030814_100%)]" />
        <div class="pointer-events-none absolute -left-16 -top-10 h-72 w-72 rounded-full bg-blue-500/20 blur-3xl splash-drift-one" />
        <div class="pointer-events-none absolute -bottom-8 -right-10 h-64 w-64 rounded-full bg-purple-500/16 blur-3xl splash-drift-two" />

        <!-- Vaste header -->
        <header class="relative shrink-0 flex items-center justify-between px-4 border-b border-white/5 bg-[rgba(3,8,20,0.60)] backdrop-blur-xl"
                style="height: calc(52px + env(safe-area-inset-top, 0px)); padding-top: env(safe-area-inset-top, 0px)">

            <!-- Logo via inline style — Vite raakt het pad niet aan -->
            <div
                class="h-7 w-28 bg-no-repeat bg-contain bg-left"
                :style="{ backgroundImage: `url(${logo})` }"
                role="img"
                aria-label="PlayDrive"
            />

            <!-- Venue pill — alleen in Mijn tab -->
            <div
                v-if="isMyTab && venue.activeVenue"
                class="absolute left-1/2 -translate-x-1/2"
            >
                <button
                    class="glass-pill flex items-center gap-1.5 px-3 py-1.5 rounded-full"
                    @click="showSwitcher = true"
                >
                    <span
                        class="w-1.5 h-1.5 rounded-full shrink-0"
                        :class="venue.activeVenue.is_active ? 'bg-emerald-400' : 'bg-slate-500'"
                    />
                    <span class="text-xs font-medium text-slate-100 max-w-[120px] truncate">{{ venue.activeVenue.name }}</span>
                    <ChevronDownIcon class="w-3 h-3 text-slate-400 shrink-0" />
                </button>
            </div>

            <!-- Gebruiker avatar -->
            <button
                class="w-8 h-8 rounded-full bg-blue-500/20 border border-blue-500/30 flex items-center justify-center shrink-0"
                @click="router.push('/profiel')"
            >
                <span class="text-xs font-semibold text-blue-300">{{ initials }}</span>
            </button>
        </header>

        <!-- Pagina-inhoud -->
        <main class="relative flex-1 overflow-y-auto overflow-x-hidden">
            <router-view />
        </main>

        <!-- Bottom tab bar -->
        <nav class="relative shrink-0 pb-safe border-t border-white/8 bg-[rgba(3,8,20,0.80)] backdrop-blur-xl">
            <div class="flex">
                <button
                    v-for="tab in tabs"
                    :key="tab.name"
                    class="flex-1 flex flex-col items-center gap-1 py-3 transition-colors"
                    :class="tab.active ? 'text-blue-400' : 'text-slate-500'"
                    @click="router.push(tab.to)"
                >
                    <component :is="tab.icon" class="w-6 h-6" />
                    <span class="text-[10px] font-medium tracking-wide">{{ tab.label }}</span>
                </button>
            </div>
        </nav>

        <!-- Venue switcher bottom sheet -->
        <VenueSwitcher v-if="showSwitcher" @close="showSwitcher = false" />
    </div>
</template>

<script setup>
import { ref, computed } from 'vue'
import { useRouter, useRoute } from 'vue-router'
import {
    HomeIcon,
    GlobeAltIcon,
    UserCircleIcon,
    ChevronDownIcon,
} from '@heroicons/vue/24/outline'
import {
    HomeIcon as HomeIconSolid,
    GlobeAltIcon as GlobeIconSolid,
    UserCircleIcon as UserIconSolid,
} from '@heroicons/vue/24/solid'
import { useVenueStore } from '../stores/useVenueStore'
import { useAuthStore } from '../stores/useAuthStore'
import VenueSwitcher from '../components/VenueSwitcher.vue'

const router = useRouter()
const route = useRoute()
const venue = useVenueStore()
const auth = useAuthStore()
const showSwitcher = ref(false)

// Pad via array join — Vite herkent dit niet als statische import
const logo = ['/images/logos', 'logo_header.png'].join('/')

const isMyTab = computed(() => route.path.startsWith('/mijn'))

const initials = computed(() => {
    const a = auth.account
    if (!a) return '?'
    return `${a.first_name?.[0] ?? ''}${a.last_name?.[0] ?? ''}`.toUpperCase()
})

const tabs = computed(() => [
    {
        name: 'mijn',
        label: 'Mijn',
        to: '/mijn',
        active: route.path.startsWith('/mijn'),
        icon: route.path.startsWith('/mijn') ? HomeIconSolid : HomeIcon,
    },
    {
        name: 'ontdekken',
        label: 'Ontdekken',
        to: '/ontdekken',
        active: route.path.startsWith('/ontdekken'),
        icon: route.path.startsWith('/ontdekken') ? GlobeIconSolid : GlobeAltIcon,
    },
    {
        name: 'profiel',
        label: 'Profiel',
        to: '/profiel',
        active: route.path.startsWith('/profiel'),
        icon: route.path.startsWith('/profiel') ? UserIconSolid : UserCircleIcon,
    },
])
</script>
