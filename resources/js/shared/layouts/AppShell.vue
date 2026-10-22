<template>
    <div class="flex h-screen flex-col overflow-hidden bg-slate-950 text-slate-100">
        <!-- Header (zelfde stijl als frontdesk) -->
        <header class="relative z-20 shrink-0 border-b border-slate-800 bg-gradient-to-r from-slate-950 via-slate-900 to-indigo-950/80 backdrop-blur">
            <div class="flex items-center justify-between gap-4 px-4 py-3">
                <div class="flex min-w-0 items-center gap-3">
                    <button
                        type="button"
                        class="inline-flex h-11 w-11 items-center justify-center rounded-2xl border border-slate-700 bg-slate-900/70 text-slate-200 transition hover:bg-slate-800 lg:hidden"
                        :aria-label="menuOpen ? 'Menu sluiten' : 'Menu openen'"
                        @click="menuOpen = !menuOpen"
                    >
                        <component :is="menuOpen ? XMarkIcon : Bars3Icon" class="h-6 w-6" />
                    </button>

                    <img
                        v-if="logoUrl"
                        :src="logoUrl"
                        :alt="title"
                        class="h-12 w-auto object-contain"
                    />
                    <div v-else class="text-xl font-bold text-white">{{ title }}</div>

                    <span
                        v-if="subtitle"
                        class="hidden rounded-full border border-indigo-500/40 bg-indigo-500/10 px-2.5 py-0.5 text-xs font-semibold uppercase tracking-[0.18em] text-indigo-300 sm:inline-flex"
                    >
                        {{ subtitle }}
                    </span>
                </div>

                <label
                    v-if="searchable"
                    class="hidden min-w-[260px] items-center gap-2 rounded-2xl border border-slate-700 bg-slate-950/55 px-4 py-2.5 text-slate-400 transition focus-within:border-blue-500/60 md:flex"
                >
                    <MagnifyingGlassIcon class="h-5 w-5 shrink-0" />
                    <input
                        v-model="search"
                        type="search"
                        placeholder="Zoek module…"
                        aria-label="Zoek module"
                        class="w-full bg-transparent text-sm text-white outline-none placeholder:text-slate-400/70"
                        @keydown.enter.prevent="openFirstMatch"
                        @keydown.esc="search = ''"
                    >
                </label>
            </div>
        </header>

        <div class="relative flex min-h-0 flex-1">
            <!-- Overlay op kleine schermen -->
            <div
                v-if="menuOpen"
                class="absolute inset-0 z-10 bg-slate-950/70 lg:hidden"
                @click="menuOpen = false"
            ></div>

            <!-- Zijbalk -->
            <aside
                class="absolute inset-y-0 left-0 z-10 w-72 shrink-0 overflow-y-auto border-r border-slate-800 bg-slate-900 p-3 transition-transform lg:static lg:translate-x-0 lg:bg-slate-900/60"
                :class="menuOpen ? 'translate-x-0' : '-translate-x-full'"
            >
                <nav class="space-y-5">
                    <div v-for="group in groupedNavigation" :key="group.label" class="space-y-1">
                        <div v-if="group.label" class="px-3 pb-1 text-[11px] font-semibold uppercase tracking-[0.18em] text-slate-400">
                            {{ group.label }}
                        </div>

                        <router-link
                            v-for="item in group.items"
                            :key="item.to"
                            :to="item.to"
                            class="flex items-center gap-3 rounded-2xl border px-3.5 py-2.5 text-sm font-semibold transition"
                            :class="isActive(item)
                                ? 'border-blue-500 bg-blue-600 text-white shadow-sm'
                                : 'border-transparent text-slate-200 hover:border-slate-700 hover:bg-slate-800 hover:text-white'"
                            @click="menuOpen = false"
                        >
                            <component v-if="item.icon" :is="item.icon" class="h-5 w-5 shrink-0" />
                            <span class="min-w-0 flex-1 truncate">{{ item.label }}</span>
                            <span
                                v-if="item.badge"
                                class="rounded-full bg-amber-500 px-2 text-xs font-bold text-slate-900"
                            >
                                {{ item.badge }}
                            </span>
                        </router-link>
                    </div>

                    <div v-if="!groupedNavigation.length" class="px-3 py-6 text-center text-sm text-slate-400">
                        Geen module gevonden.
                    </div>
                </nav>
            </aside>

            <main class="min-h-0 flex-1 overflow-y-auto p-6">
                <router-view />
            </main>
        </div>

        <!-- Statusbalk (zelfde stijl als frontdesk) -->
        <footer class="relative shrink-0 overflow-hidden border-t border-slate-800 bg-gradient-to-l from-slate-950 via-slate-900 to-indigo-950/80 backdrop-blur">
            <div class="pointer-events-none absolute inset-0 bg-[radial-gradient(circle_at_bottom_right,rgba(99,102,241,0.12),transparent_35%),radial-gradient(circle_at_bottom_left,rgba(56,189,248,0.10),transparent_30%)]"></div>

            <div class="relative flex flex-wrap items-center justify-between gap-3 px-4 py-3 text-sm">
                <div class="flex flex-wrap items-center gap-2">
                    <div class="flex items-center gap-3 pr-4">
                        <span class="text-base font-semibold text-white">{{ formattedDateTime }}</span>
                        <span class="rounded-full border border-indigo-500/40 bg-indigo-500/10 px-2 py-0.5 text-xs font-medium text-indigo-300">
                            v{{ appVersion }}
                        </span>
                        <span class="h-5 w-px bg-slate-700"></span>
                    </div>

                    <span v-if="tenantLabel" class="rounded-xl border border-slate-700 bg-slate-800/80 px-3 py-2 text-slate-300">
                        Tenant: <span class="font-semibold text-white">{{ tenantLabel }}</span>
                    </span>

                    <span v-if="statusLabel" class="rounded-xl border border-slate-700 bg-slate-800/80 px-3 py-2 text-slate-300">
                        User: <span class="font-semibold text-white">{{ statusLabel }}</span>
                    </span>
                </div>

                <button
                    v-if="canLogout"
                    type="button"
                    class="inline-flex items-center gap-2 rounded-2xl border border-red-500/30 bg-red-500/10 px-4 py-2.5 text-sm font-semibold text-red-200 shadow-sm transition hover:bg-red-500/20"
                    @click="$emit('logout')"
                >
                    <ArrowRightOnRectangleIcon class="h-5 w-5" />
                    <span>Uitloggen</span>
                </button>
            </div>
        </footer>
    </div>
</template>

<script setup>
import { computed, onMounted, onUnmounted, ref } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import {
    ArrowRightOnRectangleIcon,
    Bars3Icon,
    MagnifyingGlassIcon,
    XMarkIcon,
} from '@heroicons/vue/24/outline'

const props = defineProps({
    title: { type: String, required: true },
    subtitle: { type: String, required: true },
    navigation: { type: Array, required: true },
    statusLabel: { type: String, default: null },
    canLogout: { type: Boolean, default: true },
    logoUrl: { type: String, default: null },
    tenantLabel: { type: String, default: null },
    searchable: { type: Boolean, default: false },
})

defineEmits(['logout'])

const route = useRoute()
const router = useRouter()

const appVersion = import.meta.env.VITE_APP_VERSION || '?'
const menuOpen = ref(false)
const search = ref('')

const filteredNavigation = computed(() => {
    const term = search.value.trim().toLowerCase()

    if (!term) {
        return props.navigation
    }

    return props.navigation.filter(item =>
        item.label.toLowerCase().includes(term)
        || (item.group ?? '').toLowerCase().includes(term),
    )
})

const groupedNavigation = computed(() => {
    const groups = []

    filteredNavigation.value.forEach((item) => {
        const groupLabel = item.group ?? ''
        let group = groups.find(entry => entry.label === groupLabel)

        if (!group) {
            group = { label: groupLabel, items: [] }
            groups.push(group)
        }

        group.items.push(item)
    })

    return groups
})

function isActive(item) {
    if (item.to === '/') {
        return route.path === '/'
    }

    return route.path === item.to || route.path.startsWith(`${item.to}/`)
}

function openFirstMatch() {
    const first = filteredNavigation.value[0]

    if (first) {
        router.push(first.to)
        search.value = ''
    }
}

// Klok in de statusbalk
const now = ref(new Date())
let interval = null

onMounted(() => {
    interval = setInterval(() => {
        now.value = new Date()
    }, 1000)
})

onUnmounted(() => {
    clearInterval(interval)
})

const formattedDateTime = computed(() => {
    const date = now.value
    const day = date.toLocaleDateString('nl-BE', { weekday: 'long' })
    const month = date.toLocaleDateString('nl-BE', { month: 'long' })
    const time = date.toLocaleTimeString('nl-BE', { hour: '2-digit', minute: '2-digit' })

    return `${day.charAt(0).toUpperCase()}${day.slice(1)} ${date.getDate()} ${month} ${date.getFullYear()} - ${time}`
})
</script>
