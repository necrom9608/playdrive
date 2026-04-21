<template>
    <div class="website-bg min-h-screen relative overflow-hidden">
        <div class="glow-orb-blue absolute w-96 h-96 -left-20 -top-16" />
        <div class="glow-orb-purple absolute w-80 h-80 -right-16 -bottom-10" />

        <div class="relative min-h-screen flex flex-col items-center justify-center px-4 py-12">

            <!-- Laden -->
            <div v-if="loading" class="text-center">
                <div class="flex items-center justify-center gap-2 mb-3">
                    <span class="loader-dot" />
                    <span class="loader-dot" />
                    <span class="loader-dot" />
                </div>
                <p class="text-sm" style="color: var(--text-soft);">Even laden...</p>
            </div>

            <!-- Verlopen of ongeldig token -->
            <div v-else-if="tokenInvalid" class="website-card website-card-shine w-full max-w-lg rounded-3xl px-8 py-12 text-center animate-fade-up">
                <div class="w-16 h-16 rounded-full flex items-center justify-center mx-auto mb-6"
                    style="background: rgba(239,68,68,0.10); border: 1px solid rgba(239,68,68,0.22);">
                    <svg class="w-8 h-8" style="color: #f87171;" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                        <circle cx="12" cy="12" r="10"/><line x1="15" y1="9" x2="9" y2="15"/><line x1="9" y1="9" x2="15" y2="15"/>
                    </svg>
                </div>
                <h2 class="text-xl font-bold mb-3" style="color: var(--text-main);">Link niet meer geldig</h2>
                <p class="text-sm leading-relaxed" style="color: var(--text-soft);">
                    Deze link is verlopen of ongeldig. Contacteer ons als je je reservatiegegevens nodig hebt.
                </p>
            </div>

            <!-- Reservatiedetails -->
            <div v-else-if="reservation" class="w-full max-w-lg animate-fade-up space-y-4">

                <!-- Header kaart -->
                <div class="website-card website-card-shine rounded-3xl px-8 py-8">
                    <div class="flex items-center gap-3 mb-1">
                        <div class="w-10 h-10 rounded-full flex items-center justify-center shrink-0"
                            style="background: rgba(34,197,94,0.10); border: 1px solid rgba(34,197,94,0.22);">
                            <svg class="w-5 h-5" style="color: #4ade80;" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                                <polyline points="20 6 9 17 4 12"/>
                            </svg>
                        </div>
                        <div>
                            <h1 class="text-lg font-bold" style="color: var(--text-main);">Reservatie bevestigd</h1>
                            <p class="text-xs" style="color: var(--text-soft);">{{ reservation.tenant_name }}</p>
                        </div>
                    </div>
                </div>

                <!-- Details kaart -->
                <div class="website-card website-card-shine rounded-3xl px-8 py-6 space-y-4">
                    <h2 class="text-sm font-semibold uppercase tracking-widest" style="color: var(--text-soft); opacity: 0.7;">Jouw reservatie</h2>

                    <div class="space-y-3">
                        <DetailRow label="Naam" :value="reservation.name" />
                        <DetailRow v-if="reservation.event_date" label="Datum" :value="formatDate(reservation.event_date)" />
                        <DetailRow v-if="reservation.event_time" label="Startuur" :value="reservation.event_time" />
                        <DetailRow v-if="reservation.event_type" label="Type event" :value="reservation.event_type" />
                        <DetailRow v-if="reservation.stay_option" label="Formule" :value="reservation.stay_option" />
                        <DetailRow v-if="reservation.catering_option" label="Catering" :value="reservation.catering_option" />
                        <DetailRow label="Personen" :value="`${reservation.total_count} personen`" />
                    </div>

                    <!-- Status badge -->
                    <div class="pt-2 border-t" style="border-color: rgba(75,98,148,0.15);">
                        <span class="inline-flex items-center gap-1.5 rounded-full px-3 py-1 text-xs font-medium"
                            :style="statusStyle">
                            <span class="w-1.5 h-1.5 rounded-full" :style="{ background: statusDotColor }"></span>
                            {{ statusLabel }}
                        </span>
                    </div>
                </div>

                <!-- Contact kaart -->
                <div v-if="reservation.tenant_phone || reservation.tenant_email" class="website-card website-card-shine rounded-3xl px-8 py-6">
                    <h2 class="text-sm font-semibold uppercase tracking-widest mb-4" style="color: var(--text-soft); opacity: 0.7;">Contact</h2>
                    <div class="space-y-2">
                        <a v-if="reservation.tenant_phone" :href="`tel:${reservation.tenant_phone}`"
                            class="flex items-center gap-3 text-sm" style="color: var(--text-soft);">
                            <svg class="w-4 h-4 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.79 19.79 0 0 1-8.63-3.07A19.5 19.5 0 0 1 4.69 12a19.79 19.79 0 0 1-3.07-8.67A2 2 0 0 1 3.62 1.18h3a2 2 0 0 1 2 1.72c.127.96.361 1.903.7 2.81a2 2 0 0 1-.45 2.11L7.91 8.77a16 16 0 0 0 6.29 6.29l.95-.95a2 2 0 0 1 2.11-.45c.907.339 1.85.573 2.81.7A2 2 0 0 1 22 16.92z"/>
                            </svg>
                            {{ reservation.tenant_phone }}
                        </a>
                        <a v-if="reservation.tenant_email" :href="`mailto:${reservation.tenant_email}`"
                            class="flex items-center gap-3 text-sm" style="color: var(--text-soft);">
                            <svg class="w-4 h-4 shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                <path d="M4 4h16c1.1 0 2 .9 2 2v12c0 1.1-.9 2-2 2H4c-1.1 0-2-.9-2-2V6c0-1.1.9-2 2-2z"/><polyline points="22,6 12,13 2,6"/>
                            </svg>
                            {{ reservation.tenant_email }}
                        </a>
                    </div>
                </div>

                <!-- Zachte account-uitnodiging -->
                <div v-if="!reservation.has_account" class="rounded-2xl px-6 py-5 text-sm"
                    style="background: rgba(59,130,246,0.06); border: 1px solid rgba(59,130,246,0.15);">
                    <p class="font-medium mb-1" style="color: var(--text-main);">Altijd je reservaties bij de hand?</p>
                    <p style="color: var(--text-soft);">
                        Maak een gratis account aan met je e-mailadres en bekijk al je reservaties op één plek.
                    </p>
                    <a :href="registerUrl" class="inline-block mt-3 text-xs font-semibold" style="color: #60a5fa;">
                        Account aanmaken →
                    </a>
                </div>

            </div>
        </div>
    </div>
</template>

<script setup>
import { computed, onMounted, ref } from 'vue'
import { useRoute } from 'vue-router'

// Inline hulpcomponent
const DetailRow = {
    props: ['label', 'value'],
    template: `
        <div class="flex justify-between gap-4 text-sm">
            <span style="color: var(--text-soft);">{{ label }}</span>
            <span class="text-right font-medium" style="color: var(--text-main);">{{ value }}</span>
        </div>
    `,
}

const route       = useRoute()
const loading     = ref(true)
const tokenInvalid = ref(false)
const reservation = ref(null)

const statusMap = {
    new:          { label: 'Nieuw', color: '#64748b',  bg: 'rgba(100,116,139,0.12)' },
    confirmed:    { label: 'Bevestigd', color: '#22c55e', bg: 'rgba(34,197,94,0.12)' },
    checked_in:   { label: 'Ingecheckt', color: '#3b82f6', bg: 'rgba(59,130,246,0.12)' },
    checked_out:  { label: 'Uitgecheckt', color: '#8b5cf6', bg: 'rgba(139,92,246,0.12)' },
    paid:         { label: 'Betaald', color: '#22c55e', bg: 'rgba(34,197,94,0.12)' },
    cancelled:    { label: 'Geannuleerd', color: '#ef4444', bg: 'rgba(239,68,68,0.12)' },
    no_show:      { label: 'Niet komen opdagen', color: '#f97316', bg: 'rgba(249,115,22,0.12)' },
}

const statusInfo  = computed(() => statusMap[reservation.value?.status] ?? statusMap.confirmed)
const statusLabel = computed(() => statusInfo.value.label)
const statusDotColor = computed(() => statusInfo.value.color)
const statusStyle = computed(() => ({
    color: statusInfo.value.color,
    background: statusInfo.value.bg,
}))

const registerUrl = computed(() => {
    if (!reservation.value?.tenant_slug) return '/member/registreren'
    return `/registreer/${reservation.value.tenant_slug}`
})

function formatDate(dateStr) {
    if (!dateStr) return '—'
    const d = new Date(dateStr)
    return d.toLocaleDateString('nl-BE', { weekday: 'long', year: 'numeric', month: 'long', day: 'numeric' })
}

onMounted(async () => {
    const token = route.params.token
    try {
        const res = await fetch(`/api/public/reservatie/${token}`)
        if (res.status === 404 || res.status === 410) {
            tokenInvalid.value = true
            return
        }
        const json = await res.json()
        reservation.value = json.data
    } catch {
        tokenInvalid.value = true
    } finally {
        loading.value = false
    }
})
</script>
