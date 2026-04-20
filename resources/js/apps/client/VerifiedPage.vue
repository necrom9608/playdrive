<template>
    <div class="min-h-screen flex flex-col items-center justify-center px-5 py-10"
         style="background: radial-gradient(ellipse at 30% 20%, rgba(6,182,212,0.08) 0%, transparent 60%), radial-gradient(ellipse at 80% 80%, rgba(99,102,241,0.08) 0%, transparent 60%), #020817;">

        <div class="w-full max-w-md text-center">

            <!-- Logo -->
            <div class="mb-8 flex flex-col items-center gap-2">
                <div class="text-xs uppercase tracking-[0.3em] text-cyan-400">PlayDrive</div>
            </div>

            <!-- Status: success -->
            <div v-if="status === 'success'"
                class="rounded-3xl border border-emerald-500/20 bg-emerald-500/10 p-8 backdrop-blur-sm">
                <div class="mx-auto mb-5 flex h-16 w-16 items-center justify-center rounded-2xl border border-emerald-400/30 bg-emerald-500/15">
                    <svg class="h-8 w-8 text-emerald-300" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" />
                    </svg>
                </div>
                <h1 class="text-2xl font-semibold text-white">E-mail bevestigd!</h1>
                <p class="mt-3 text-sm leading-relaxed text-slate-300">
                    Je account is geactiveerd. Download de PlayDrive app om in te loggen.
                </p>

                <!-- App download knoppen -->
                <div class="mt-8 flex flex-col gap-3">
                    <a
                        :href="appStoreUrl"
                        target="_blank"
                        rel="noopener"
                        class="flex items-center justify-center gap-3 rounded-2xl border border-white/10 bg-white/5 px-5 py-4 text-sm font-medium text-white transition hover:bg-white/10 hover:border-white/20"
                    >
                        <!-- Apple icon -->
                        <svg class="h-6 w-6 flex-shrink-0" viewBox="0 0 24 24" fill="currentColor">
                            <path d="M18.71 19.5c-.83 1.24-1.71 2.45-3.05 2.47-1.34.03-1.77-.79-3.29-.79-1.53 0-2 .77-3.27.82-1.31.05-2.3-1.32-3.14-2.53C4.25 17 2.94 12.45 4.7 9.39c.87-1.52 2.43-2.48 4.12-2.51 1.28-.02 2.5.87 3.29.87.78 0 2.26-1.07 3.8-.91.65.03 2.47.26 3.64 1.98-.09.06-2.17 1.28-2.15 3.81.03 3.02 2.65 4.03 2.68 4.04-.03.07-.42 1.44-1.38 2.83M13 3.5c.73-.83 1.94-1.46 2.94-1.5.13 1.17-.34 2.35-1.04 3.19-.69.85-1.83 1.51-2.95 1.42-.15-1.15.41-2.35 1.05-3.11z"/>
                        </svg>
                        <div class="text-left">
                            <div class="text-xs text-slate-400 leading-none mb-0.5">Download in de</div>
                            <div class="font-semibold leading-none">App Store</div>
                        </div>
                    </a>

                    <a
                        :href="playStoreUrl"
                        target="_blank"
                        rel="noopener"
                        class="flex items-center justify-center gap-3 rounded-2xl border border-white/10 bg-white/5 px-5 py-4 text-sm font-medium text-white transition hover:bg-white/10 hover:border-white/20"
                    >
                        <!-- Google Play icon -->
                        <svg class="h-6 w-6 flex-shrink-0" viewBox="0 0 24 24" fill="currentColor">
                            <path d="M3.18 23.76c.3.17.64.24.99.2L15.35 12 11.85 8.5 3.18 23.76zM20.77 10.34l-2.96-1.7-3.19 3.19 3.19 3.19 3-1.73c.85-.49.85-1.46-.04-1.95zM3.02.41C2.72.59 2.5.91 2.5 1.33v21.34c0 .42.22.74.52.92l.1.06 11.95-11.95v-.28L3.12.35 3.02.41zM15.35 12l3.46-3.46-11.83-6.8L15.35 12z"/>
                        </svg>
                        <div class="text-left">
                            <div class="text-xs text-slate-400 leading-none mb-0.5">Beschikbaar op</div>
                            <div class="font-semibold leading-none">Google Play</div>
                        </div>
                    </a>
                </div>

                <p class="mt-6 text-xs text-slate-500">
                    Log in met het e-mailadres en wachtwoord waarmee je je hebt geregistreerd.
                </p>
            </div>

            <!-- Status: verlopen token -->
            <div v-else-if="status === 'expired'"
                class="rounded-3xl border border-amber-500/20 bg-amber-500/10 p-8 backdrop-blur-sm">
                <div class="mx-auto mb-5 flex h-16 w-16 items-center justify-center rounded-2xl border border-amber-400/30 bg-amber-500/15">
                    <svg class="h-8 w-8 text-amber-300" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                    </svg>
                </div>
                <h1 class="text-2xl font-semibold text-white">Link vervallen</h1>
                <p class="mt-3 text-sm leading-relaxed text-slate-300">
                    Deze bevestigingslink is verlopen (geldig voor 24 uur).<br>
                    Ga terug naar het registratieformulier en verstuur een nieuwe e-mail.
                </p>
                <a
                    v-if="tenantSlug"
                    :href="`/client/register/${tenantSlug}`"
                    class="mt-6 inline-block rounded-2xl px-6 py-3 text-sm font-semibold text-white transition"
                    style="background: linear-gradient(180deg, #06b6d4 0%, #0891b2 100%);"
                >
                    Opnieuw proberen
                </a>
            </div>

            <!-- Status: ongeldig token -->
            <div v-else
                class="rounded-3xl border border-rose-500/20 bg-rose-500/10 p-8 backdrop-blur-sm">
                <div class="mx-auto mb-5 flex h-16 w-16 items-center justify-center rounded-2xl border border-rose-400/30 bg-rose-500/15">
                    <svg class="h-8 w-8 text-rose-300" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                    </svg>
                </div>
                <h1 class="text-2xl font-semibold text-white">Ongeldige link</h1>
                <p class="mt-3 text-sm leading-relaxed text-slate-300">
                    Deze bevestigingslink is ongeldig of al gebruikt.<br>
                    Mogelijk is je e-mail al bevestigd.
                </p>
            </div>

            <p class="mt-8 text-center text-xs text-slate-600">
                Aangedreven door <span class="text-slate-500">PlayDrive</span>
            </p>
        </div>
    </div>
</template>

<script setup>
import { computed } from 'vue'
import { useRoute } from 'vue-router'

const route = useRoute()

const status     = computed(() => route.query.status ?? 'invalid')
const tenantSlug = computed(() => route.query.tenant ?? null)

// Deze URLs kunnen later vervangen worden door echte store-links
const appStoreUrl  = 'https://apps.apple.com'
const playStoreUrl = 'https://play.google.com'
</script>
