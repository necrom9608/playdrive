<template>
    <div class="member-app flex flex-col h-screen overflow-hidden bg-slate-950 text-slate-100">

        <!-- Venue pill header — alleen zichtbaar in Mijn tab -->
        <header v-if="isMyTab" class="shrink-0 px-4 pt-safe pt-4 pb-2">
            <button
                v-if="venue.activeVenue"
                class="glass-pill flex items-center gap-2 px-3 py-1.5 rounded-full w-full max-w-xs mx-auto"
                @click="showSwitcher = true"
            >
                <span
                    class="w-2 h-2 rounded-full shrink-0"
                    :class="venue.activeVenue.is_active ? 'bg-emerald-400' : 'bg-slate-500'"
                />
                <span class="text-sm font-medium text-slate-100 truncate flex-1">{{ venue.activeVenue.name }}</span>
                <ChevronDownIcon class="w-3.5 h-3.5 text-slate-400 shrink-0" />
            </button>
        </header>

        <!-- Pagina-inhoud -->
        <main class="flex-1 overflow-y-auto overflow-x-hidden">
            <router-view />
        </main>

        <!-- Bottom tab bar -->
        <nav class="shrink-0 pb-safe border-t border-slate-800/60 bg-slate-950/95 backdrop-blur-xl">
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
import VenueSwitcher from '../components/VenueSwitcher.vue'

const router = useRouter()
const route = useRoute()
const venue = useVenueStore()
const showSwitcher = ref(false)

const isMyTab = computed(() => route.path.startsWith('/mijn'))

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
