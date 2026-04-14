<template>
    <div class="fixed inset-0 z-[100] overflow-hidden bg-[#030814] text-white login-shell">
        <div class="absolute inset-0 bg-[radial-gradient(circle_at_20%_18%,rgba(59,130,246,0.18),transparent_30%),radial-gradient(circle_at_80%_78%,rgba(168,85,247,0.14),transparent_28%),radial-gradient(circle_at_top,#0f2d63_0%,#071327_46%,#030814_100%)] backdrop-fade"></div>
        <div class="pointer-events-none absolute inset-0 opacity-[0.08] bg-[linear-gradient(rgba(255,255,255,0.06)_1px,transparent_1px),linear-gradient(90deg,rgba(255,255,255,0.06)_1px,transparent_1px)] bg-[size:36px_36px] grid-drift"></div>
        <div class="pointer-events-none absolute inset-0 bg-[radial-gradient(circle_at_center,transparent_0%,rgba(3,8,20,0.12)_45%,rgba(3,8,20,0.45)_100%)]"></div>
        <div class="pointer-events-none absolute -left-16 -top-10 h-72 w-72 rounded-full bg-[rgba(59,130,246,0.20)] blur-3xl splash-drift-one"></div>
        <div class="pointer-events-none absolute left-[12%] top-[58%] h-28 w-28 rounded-full bg-[rgba(56,189,248,0.12)] blur-3xl splash-drift-three"></div>
        <div class="pointer-events-none absolute -bottom-8 -right-10 h-64 w-64 rounded-full bg-[rgba(168,85,247,0.16)] blur-3xl splash-drift-two"></div>

        <div class="relative flex min-h-screen items-center justify-center p-4">
            <div class="login-card relative w-full max-w-md overflow-hidden rounded-[30px] border border-[rgba(75,98,148,0.28)] bg-[linear-gradient(180deg,rgba(12,26,58,0.96)_0%,rgba(8,18,43,0.92)_100%)] shadow-[0_28px_80px_rgba(0,0,0,0.45),inset_0_1px_0_rgba(255,255,255,0.04)] backdrop-blur-xl">
                <div class="px-7 pb-6 pt-7 sm:px-8 sm:pb-8 sm:pt-8">
                    <div class="mb-6 flex justify-center">
                        <div class="relative inline-flex items-center justify-center">
                            <div class="absolute h-[120px] w-[120px] rounded-full bg-[rgba(59,130,246,0.18)] blur-3xl splash-pulse"></div>
                            <div class="absolute h-[84px] w-[84px] rounded-full bg-[rgba(168,85,247,0.14)] blur-3xl splash-pulse-delayed"></div>
                            <img
                                :src="'/images/logos/logo_header.png'"
                                alt="Playdrive"
                                class="relative z-[1] h-12 w-auto object-contain drop-shadow-[0_0_18px_rgba(59,130,246,0.25)] logo-float"
                            />
                        </div>
                    </div>

                    <h2 class="mb-6 text-center text-2xl font-semibold tracking-[0.06em] text-white">Aanmelden</h2>

                    <div v-if="errorMessage" class="mb-4 rounded-2xl border border-red-500/25 bg-red-500/10 px-4 py-3 text-sm text-red-100 error-in">
                        {{ errorMessage }}
                    </div>

                    <form class="space-y-4" @submit.prevent="submitLogin">
                        <div class="field-shell">
                            <input
                                v-model="form.username"
                                type="text"
                                class="field-input w-full rounded-2xl border border-white/10 bg-slate-950/55 px-4 py-3.5 text-white outline-none placeholder:text-slate-400/70"
                                autocomplete="username"
                                placeholder="Login"
                            >
                        </div>

                        <div class="field-shell">
                            <input
                                v-model="form.password"
                                type="password"
                                class="field-input w-full rounded-2xl border border-white/10 bg-slate-950/55 px-4 py-3.5 text-white outline-none placeholder:text-slate-400/70"
                                autocomplete="current-password"
                                placeholder="Paswoord"
                            >
                        </div>

                        <button
                            type="submit"
                            class="login-button w-full rounded-2xl bg-[linear-gradient(180deg,#3483ff_0%,#255df4_100%)] px-4 py-3.5 font-semibold text-white shadow-[0_14px_28px_rgba(37,93,244,0.28)] transition disabled:cursor-not-allowed disabled:opacity-50"
                            :disabled="busy"
                        >
                            {{ busy ? 'Bezig...' : 'Inloggen' }}
                        </button>
                    </form>

                    <template v-if="rfidAvailable">
                        <div class="my-5 flex items-center gap-3">
                            <div class="h-px flex-1 bg-white/10"></div>
                            <span class="text-[11px] uppercase tracking-[0.22em] text-slate-400/70">of</span>
                            <div class="h-px flex-1 bg-white/10"></div>
                        </div>

                        <div class="rounded-[24px] border border-white/10 bg-slate-950/35 p-4 transition duration-300 hover:border-cyan-400/20 hover:bg-slate-950/40">
                            <div v-if="scannedUid" class="mb-3 rounded-xl border border-dashed border-cyan-400/25 bg-cyan-400/5 px-4 py-3 font-mono text-sm text-cyan-200 error-in">
                                {{ scannedUid }}
                            </div>

                            <ScanRfidButton
                                v-model="scannedUid"
                                label="Scan badge"
                                title="Badge scannen"
                                description="Houd je badge bij de lezer."
                                confirm-label="Gebruik badge"
                                :auto-confirm="true"
                                :disabled="busy"
                                @confirmed="submitCardLogin"
                            />
                        </div>
                    </template>
                </div>
            </div>
        </div>
    </div>
</template>

<script setup>
import { computed, ref } from 'vue'
import { useAuthStore } from '../stores/authStore'
import ScanRfidButton from '../../../shared/components/scanners/ScanRfidButton.vue'
import { isTauriRuntime } from '../../../shared/runtime/environment'

const auth = useAuthStore()

const busy = ref(false)
const errorMessage = ref('')
const scannedUid = ref('')

const rfidAvailable = computed(() => isTauriRuntime())

const form = ref({
    username: '',
    password: '',
})

async function submitLogin() {
    busy.value = true
    errorMessage.value = ''

    try {
        await auth.login(form.value.username, form.value.password)
    } catch (error) {
        errorMessage.value = error?.data?.errors?.username?.[0]
            || error?.data?.message
            || 'Inloggen mislukt.'
    } finally {
        busy.value = false
    }
}

async function submitCardLogin() {
    if (!scannedUid.value || !rfidAvailable.value) {
        return
    }

    busy.value = true
    errorMessage.value = ''

    try {
        await auth.loginWithCard(scannedUid.value)
        scannedUid.value = ''
    } catch (error) {
        errorMessage.value = error?.data?.errors?.rfid_uid?.[0]
            || error?.data?.message
            || 'Inloggen met kaart mislukt.'
    } finally {
        busy.value = false
    }
}
</script>

<style scoped>
.login-shell {
    animation: shell-in 0.7s ease-out both;
}

.login-card {
    animation: splash-card-in 0.7s cubic-bezier(0.22, 1, 0.36, 1) both;
}

.login-card::before {
    content: '';
    position: absolute;
    inset: 0;
    background: linear-gradient(
        115deg,
        transparent 25%,
        rgba(255, 255, 255, 0.02) 45%,
        rgba(255, 255, 255, 0.01) 52%,
        transparent 68%
    );
    transform: translateX(-160%);
    animation: splash-shine 3.4s ease-in-out infinite;
    pointer-events: none;
}

.backdrop-fade {
    animation: backdrop-fade 0.9s ease-out both;
}

.grid-drift {
    animation: grid-drift 18s linear infinite;
}

.splash-pulse {
    animation: splash-pulse 2.4s ease-in-out infinite;
}

.splash-pulse-delayed {
    animation: splash-pulse 2.4s ease-in-out infinite 0.4s;
}

.logo-float {
    animation: logo-float 3.6s ease-in-out infinite;
}

.field-shell {
    position: relative;
}

.field-shell::after {
    content: '';
    position: absolute;
    inset: 1px;
    border-radius: 1rem;
    pointer-events: none;
    box-shadow: 0 0 0 0 rgba(59, 130, 246, 0);
    transition: box-shadow 0.25s ease, opacity 0.25s ease;
    opacity: 0;
}

.field-shell:focus-within::after {
    box-shadow: 0 0 0 3px rgba(59, 130, 246, 0.18);
    opacity: 1;
}

.field-input {
    transition: border-color 0.25s ease, transform 0.2s ease, background-color 0.25s ease, box-shadow 0.25s ease;
}

.field-input:hover {
    border-color: rgba(255, 255, 255, 0.18);
    background: rgba(2, 6, 23, 0.62);
}

.field-input:focus {
    border-color: rgba(96, 165, 250, 0.65);
    background: rgba(2, 6, 23, 0.72);
    box-shadow: 0 12px 28px rgba(15, 23, 42, 0.28);
    transform: translateY(-1px);
}

.login-button {
    position: relative;
    overflow: hidden;
}

.login-button::before {
    content: '';
    position: absolute;
    inset: 0;
    background: linear-gradient(90deg, transparent 0%, rgba(255,255,255,0.18) 50%, transparent 100%);
    transform: translateX(-140%);
    transition: transform 0.6s ease;
}

.login-button:hover {
    transform: translateY(-1px) scale(1.01);
    box-shadow: 0 18px 34px rgba(37,93,244,0.34), 0 0 22px rgba(59,130,246,0.18);
}

.login-button:hover::before {
    transform: translateX(140%);
}

.error-in {
    animation: error-in 0.28s ease-out both;
}

.splash-drift-one {
    animation: splash-drift-one 7s ease-in-out infinite alternate;
}

.splash-drift-two {
    animation: splash-drift-two 8s ease-in-out infinite alternate;
}

.splash-drift-three {
    animation: splash-drift-three 9s ease-in-out infinite alternate;
}

@keyframes shell-in {
    from {
        opacity: 0;
    }
    to {
        opacity: 1;
    }
}

@keyframes backdrop-fade {
    from {
        opacity: 0;
        transform: scale(1.03);
    }
    to {
        opacity: 1;
        transform: scale(1);
    }
}

@keyframes splash-card-in {
    from {
        opacity: 0;
        transform: translateY(18px) scale(0.982);
    }
    to {
        opacity: 1;
        transform: translateY(0) scale(1);
    }
}

@keyframes splash-shine {
    0% {
        transform: translateX(-160%);
    }
    55%,
    100% {
        transform: translateX(180%);
    }
}

@keyframes splash-pulse {
    0%,
    100% {
        transform: scale(0.96);
        opacity: 0.72;
    }
    50% {
        transform: scale(1.05);
        opacity: 1;
    }
}

@keyframes logo-float {
    0%,100% { transform: translateY(0); }
    50% { transform: translateY(-6px); }
}

@keyframes splash-drift-one {
    from {
        transform: translate3d(0, 0, 0);
    }
    to {
        transform: translate3d(22px, 14px, 0);
    }
}

@keyframes splash-drift-two {
    from {
        transform: translate3d(0, 0, 0);
    }
    to {
        transform: translate3d(-18px, -12px, 0);
    }
}

@keyframes splash-drift-three {
    from {
        transform: translate3d(0, 0, 0);
    }
    to {
        transform: translate3d(14px, -16px, 0);
    }
}

@keyframes grid-drift {
    from {
        transform: translate3d(0, 0, 0);
    }
    to {
        transform: translate3d(36px, 18px, 0);
    }
}

@keyframes error-in {
    from {
        opacity: 0;
        transform: translateY(-4px);
    }
    to {
        opacity: 1;
        transform: translateY(0);
    }
}
</style>
