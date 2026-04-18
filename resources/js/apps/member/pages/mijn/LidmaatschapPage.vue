<template>
    <div class="px-4 pt-3 pb-6 space-y-4">
        <h1 class="text-lg font-semibold text-white">Lidmaatschap</h1>

        <!-- Actief lid -->
        <template v-if="membership?.status === 'active'">
            <div class="glass-card rounded-3xl p-6 text-center space-y-4 relative overflow-hidden">
                <div class="absolute top-0 right-0 w-40 h-40 bg-blue-500/10 rounded-full -translate-y-12 translate-x-12 blur-3xl pointer-events-none" />
                <div class="absolute bottom-0 left-0 w-32 h-32 bg-purple-500/10 rounded-full translate-y-8 -translate-x-8 blur-3xl pointer-events-none" />

                <div class="relative">
                    <StatusBadge status="active" />
                    <p class="text-base font-semibold text-white mt-2">{{ holderName }}</p>
                    <p class="text-xs text-slate-400 capitalize">{{ membership.membership_type }}</p>
                </div>

                <!-- QR code -->
                <div class="relative inline-block">
                    <div class="w-44 h-44 rounded-2xl bg-white p-3 mx-auto" id="qr-container">
                        <QRCode :value="membership.qr_token" :size="152" />
                    </div>
                    <p class="text-[10px] text-slate-500 mt-2 font-mono tracking-wider">{{ membership.card_number }}</p>
                </div>

                <div class="grid grid-cols-2 gap-3 pt-1">
                    <div class="bg-slate-800/50 rounded-xl px-3 py-2">
                        <p class="text-[10px] text-slate-500 mb-0.5">Geldig vanaf</p>
                        <p class="text-xs font-medium text-slate-200">{{ formatDate(membership.starts_at) }}</p>
                    </div>
                    <div class="bg-slate-800/50 rounded-xl px-3 py-2">
                        <p class="text-[10px] text-slate-500 mb-0.5">Geldig tot</p>
                        <p class="text-xs font-medium text-slate-200">{{ formatDate(membership.ends_at) }}</p>
                    </div>
                </div>

                <button
                    class="w-full flex items-center justify-center gap-2 py-2.5 rounded-2xl bg-slate-800/50 border border-slate-700/40 text-sm text-slate-300"
                    @click="enlargeQr = true"
                >
                    <ArrowsPointingOutIcon class="w-4 h-4" />
                    Vergroot QR-code
                </button>
            </div>
        </template>

        <!-- Verlopen -->
        <template v-else-if="membership?.status === 'expired'">
            <div class="glass-card rounded-3xl p-6 text-center space-y-4">
                <div class="w-14 h-14 rounded-2xl bg-amber-500/15 border border-amber-500/25 flex items-center justify-center mx-auto">
                    <ExclamationTriangleIcon class="w-7 h-7 text-amber-400" />
                </div>
                <div>
                    <StatusBadge status="expired" />
                    <p class="text-base font-semibold text-white mt-2">{{ holderName }}</p>
                    <p class="text-xs text-slate-400 mt-1">Lidmaatschap vervallen op {{ formatDate(membership.ends_at) }}</p>
                </div>
                <a
                    href="#"
                    class="member-btn-primary w-full flex items-center justify-center gap-2"
                    @click.prevent="openRenew"
                >
                    <ArrowPathIcon class="w-4 h-4" />
                    Verlengen via website
                </a>
                <p class="text-xs text-slate-500">Opent in je browser</p>
            </div>
        </template>

        <!-- Geen lidmaatschap -->
        <template v-else-if="membership?.status === 'none'">
            <div class="glass-card rounded-3xl p-6 text-center space-y-4">
                <div class="w-14 h-14 rounded-2xl bg-slate-700/50 border border-slate-600/40 flex items-center justify-center mx-auto">
                    <UserIcon class="w-7 h-7 text-slate-400" />
                </div>
                <div>
                    <StatusBadge status="none" />
                    <p class="text-base font-semibold text-white mt-2">{{ holderName }}</p>
                    <p class="text-xs text-slate-400 mt-1 leading-relaxed">Je bent geregistreerd bij {{ venue.activeVenue?.name }}. Vraag aan de balie naar lidmaatschapsopties.</p>
                </div>
            </div>
        </template>

        <!-- Loading -->
        <div v-else class="flex justify-center pt-16">
            <div class="flex gap-2">
                <span class="loader-dot" /><span class="loader-dot" /><span class="loader-dot" />
            </div>
        </div>

        <!-- Vergroot QR overlay -->
        <div
            v-if="enlargeQr && membership?.qr_token"
            class="fixed inset-0 z-50 bg-slate-950/90 backdrop-blur-sm flex items-center justify-center p-8"
            @click="enlargeQr = false"
        >
            <div class="bg-white rounded-3xl p-6 w-full max-w-xs">
                <QRCode :value="membership.qr_token" :size="260" />
                <p class="text-center text-slate-500 text-xs mt-3 font-mono">{{ membership.card_number }}</p>
            </div>
        </div>
    </div>
</template>

<script setup>
import { ref, computed, onMounted } from 'vue'
import {
    ArrowsPointingOutIcon,
    ExclamationTriangleIcon,
    ArrowPathIcon,
    UserIcon,
} from '@heroicons/vue/24/outline'
import { Browser } from '@capacitor/browser'
import { useVenueStore } from '../../stores/useVenueStore'
import { useAuthStore } from '../../stores/useAuthStore'
import StatusBadge from '../../components/StatusBadge.vue'
import QRCode from '../../components/QRCode.vue'

const venue = useVenueStore()
const auth = useAuthStore()
const enlargeQr = ref(false)

const membership = computed(() => venue.membership)
const holderName = computed(() => {
    const h = membership.value?.holder
    return h ? `${h.first_name} ${h.last_name}` : auth.account ? `${auth.account.first_name} ${auth.account.last_name}` : ''
})

function formatDate(d) {
    if (!d) return '—'
    return new Date(d).toLocaleDateString('nl-BE', { day: 'numeric', month: 'short', year: 'numeric' })
}

async function openRenew() {
    const url = `${import.meta.env.VITE_APP_URL ?? ''}/lidmaatschap/verlengen`
    try {
        await Browser.open({ url })
    } catch {
        window.open(url, '_blank')
    }
}

onMounted(async () => {
    if (venue.activeSlug && !venue.membership) {
        await venue.loadMembership(venue.activeSlug)
    }
})
</script>
