<template>
    <div class="mx-auto max-w-4xl space-y-6">
        <div>
            <h1 class="text-3xl font-bold text-white">Welkom terug</h1>
            <p class="mt-2 text-slate-400">Beheer hier de publieke pagina van je venue.</p>
        </div>

        <!-- Status banner -->
        <div
            v-if="auth.tenant"
            class="rounded-3xl border p-6"
            :class="auth.tenant.public_status === 'live'
                ? 'border-emerald-500/30 bg-emerald-500/5'
                : 'border-amber-500/30 bg-amber-500/5'"
        >
            <div class="flex items-start justify-between gap-4">
                <div>
                    <div class="flex items-center gap-2">
                        <div
                            class="h-2.5 w-2.5 rounded-full"
                            :class="auth.tenant.public_status === 'live' ? 'bg-emerald-400' : 'bg-amber-400'"
                        />
                        <div class="text-xs uppercase tracking-[0.18em] font-semibold"
                             :class="auth.tenant.public_status === 'live' ? 'text-emerald-300' : 'text-amber-300'"
                        >
                            {{ auth.tenant.public_status === 'live' ? 'Live' : 'Concept' }}
                        </div>
                    </div>
                    <div class="mt-2 text-xl font-semibold text-white">
                        {{ auth.tenant.display_name }}
                    </div>
                    <div v-if="auth.tenant.public_status === 'live' && auth.tenant.public_slug"
                         class="mt-1 text-sm text-slate-400">
                        Je pagina staat live op
                        <a
                            :href="`/venues/${auth.tenant.public_slug}`"
                            target="_blank"
                            class="font-medium text-cyan-400 hover:text-cyan-300"
                        >/venues/{{ auth.tenant.public_slug }}</a>
                    </div>
                    <div v-else class="mt-1 text-sm text-slate-400">
                        Je pagina is nog niet zichtbaar voor bezoekers.
                    </div>
                </div>

                <router-link
                    to="/publication"
                    class="shrink-0 rounded-xl border border-slate-700 bg-slate-900/60 px-4 py-2 text-sm font-medium text-slate-200 transition hover:border-cyan-500 hover:text-cyan-300"
                >
                    {{ auth.tenant.public_status === 'live' ? 'Beheer' : 'Publiceer' }}
                </router-link>
            </div>
        </div>

        <!-- Quick actions -->
        <div class="grid grid-cols-1 gap-4 md:grid-cols-2">
            <router-link
                v-for="card in cards"
                :key="card.to"
                :to="card.to"
                class="group rounded-2xl border border-slate-800 bg-slate-900/60 p-5 transition hover:border-cyan-500/40 hover:bg-slate-900"
            >
                <div class="text-base font-semibold text-white group-hover:text-cyan-300">{{ card.title }}</div>
                <div class="mt-1 text-sm text-slate-400">{{ card.description }}</div>
            </router-link>
        </div>
    </div>
</template>

<script setup>
import { usePortalAuthStore } from '../stores/authStore'

const auth = usePortalAuthStore()

const cards = [
    { title: 'Algemene info', description: 'Naam, tagline, adres, contact en doelgroep.', to: '/info' },
    { title: 'Foto\'s & video', description: 'Logo, hero-afbeelding, galerij en YouTube-link.', to: '/media' },
    { title: 'Activiteiten', description: 'Wat bezoekers bij jou kunnen doen.', to: '/activities' },
    { title: 'Voorzieningen', description: 'Parking, toegankelijkheid, kindvriendelijk, ...', to: '/amenities' },
    { title: 'Externe links', description: 'Social media en eigen website.', to: '/links' },
    { title: 'Publicatie', description: 'Je publieke URL en status (concept / live).', to: '/publication' },
]
</script>
