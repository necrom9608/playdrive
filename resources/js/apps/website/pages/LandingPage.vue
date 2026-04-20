<template>
    <div class="website-bg min-h-screen relative overflow-hidden">
        <!-- Glow orbs — identiek aan splashscreen -->
        <div class="glow-orb-blue absolute w-96 h-96 -left-20 -top-16" />
        <div class="glow-orb-purple absolute w-80 h-80 -right-16 -bottom-10" />

        <div class="relative min-h-screen flex flex-col items-center justify-center px-5 py-12">

        <div class="website-card website-card-shine relative w-full max-w-md rounded-3xl overflow-hidden px-10 py-11 text-center animate-fade-up">

            <!-- Logo — :src met string zodat Vite dit niet probeert te bundelen -->
            <div class="mb-2">
                <img
                    :src="'/images/logos/logo_header.png'"
                    alt="Playdrive"
                    class="h-11 w-auto mx-auto"
                    style="filter: brightness(1.1) drop-shadow(0 0 18px rgba(59,130,246,0.25))"
                />
            </div>
            <p class="text-xs tracking-widest uppercase mb-8" style="color: var(--text-soft); opacity: 0.75; letter-spacing: 0.06em;">
                Jouw leisure paspoort
            </p>

            <div class="h-px mb-7" style="background: linear-gradient(90deg, transparent, rgba(75,98,148,0.4), transparent)" />

            <!-- Formulier -->
            <form v-if="!success" class="text-left space-y-2.5" @submit.prevent="submit">

                <div class="grid grid-cols-2 gap-2.5">
                    <div>
                        <label class="block text-xs uppercase tracking-wider mb-1.5" style="color: var(--text-soft); opacity: 0.8;">Voornaam</label>
                        <input
                            v-model="form.first_name"
                            type="text"
                            class="website-input"
                            placeholder="Jan"
                            required
                            autocomplete="given-name"
                        />
                    </div>
                    <div>
                        <label class="block text-xs uppercase tracking-wider mb-1.5" style="color: var(--text-soft); opacity: 0.8;">Familienaam</label>
                        <input
                            v-model="form.last_name"
                            type="text"
                            class="website-input"
                            placeholder="Declercq"
                            required
                            autocomplete="family-name"
                        />
                    </div>
                </div>

                <div>
                    <label class="block text-xs uppercase tracking-wider mb-1.5" style="color: var(--text-soft); opacity: 0.8;">E-mailadres</label>
                    <input
                        v-model="form.email"
                        type="email"
                        class="website-input"
                        placeholder="jan@example.com"
                        required
                        autocomplete="email"
                    />
                </div>

                <div>
                    <label class="block text-xs uppercase tracking-wider mb-1.5" style="color: var(--text-soft); opacity: 0.8;">Wachtwoord</label>
                    <input
                        v-model="form.password"
                        type="password"
                        class="website-input"
                        placeholder="Minimaal 8 tekens"
                        required
                        minlength="8"
                        autocomplete="new-password"
                    />
                </div>

                <div>
                    <label class="block text-xs uppercase tracking-wider mb-1.5" style="color: var(--text-soft); opacity: 0.8;">Herhaal wachtwoord</label>
                    <input
                        v-model="form.password_confirmation"
                        type="password"
                        class="website-input"
                        placeholder="········"
                        required
                        autocomplete="new-password"
                    />
                </div>

                <!-- Foutmeldingen -->
                <div v-if="errors.length" class="rounded-2xl px-4 py-3 space-y-1" style="background: rgba(239,68,68,0.10); border: 1px solid rgba(239,68,68,0.25);">
                    <p v-for="error in errors" :key="error" class="text-sm" style="color: #fca5a5;">{{ error }}</p>
                </div>

                <button type="submit" class="website-btn-primary mt-1" :disabled="loading">
                    <span v-if="!loading">Account aanmaken</span>
                    <span v-else class="flex items-center justify-center gap-2">
                        <span class="loader-dot" />
                        <span class="loader-dot" />
                        <span class="loader-dot" />
                    </span>
                </button>
            </form>

            <!-- Al een account -->
            <div v-if="!success" class="mt-6 pt-5 text-center" style="border-top: 1px solid rgba(75,98,148,0.20);">
                <p class="text-xs uppercase tracking-widest mb-3" style="color: var(--text-soft); opacity: 0.65;">Al een account?</p>
                <a href="/member" class="website-btn-ghost">
                    <svg class="w-4 h-4 opacity-75" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <rect x="5" y="2" width="14" height="20" rx="2"/>
                        <line x1="12" y1="18" x2="12.01" y2="18"/>
                    </svg>
                    Open de Playdrive app
                </a>
            </div>

            <!-- Successtate -->
            <div v-if="success" class="py-2 animate-fade-up">
                <div class="w-14 h-14 rounded-full flex items-center justify-center mx-auto mb-5" style="background: rgba(34,197,94,0.10); border: 1px solid rgba(34,197,94,0.22);">
                    <svg class="w-7 h-7" style="color: #4ade80;" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                        <polyline points="20 6 9 17 4 12"/>
                    </svg>
                </div>
                <h2 class="text-lg font-semibold mb-2.5" style="color: var(--text-main);">Welkom bij Playdrive!</h2>
                <p class="text-sm leading-relaxed mb-1" style="color: var(--text-soft);">
                    Je account is aangemaakt. Bevestig je e-mailadres om verder te gaan.
                </p>
                <div class="inline-flex items-center gap-2 mt-3.5 px-3.5 py-2 rounded-xl text-sm" style="background: rgba(59,130,246,0.10); border: 1px solid rgba(59,130,246,0.22); color: #93c5fd;">
                    <svg class="w-4 h-4 opacity-75 flex-shrink-0" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                        <rect x="2" y="4" width="20" height="16" rx="2"/>
                        <path d="m22 7-8.97 5.7a1.94 1.94 0 0 1-2.06 0L2 7"/>
                    </svg>
                    <span>Bevestigingsmail verstuurd naar {{ form.email }}</span>
                </div>

                <div class="mt-6 pt-5" style="border-top: 1px solid rgba(75,98,148,0.20);">
                    <p class="text-xs uppercase tracking-widest mb-3" style="color: var(--text-soft); opacity: 0.65;">Na bevestiging</p>
                    <a href="/member" class="website-btn-ghost">
                        <svg class="w-4 h-4 opacity-75" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                            <rect x="5" y="2" width="14" height="20" rx="2"/>
                            <line x1="12" y1="18" x2="12.01" y2="18"/>
                        </svg>
                        Open Playdrive
                    </a>
                </div>
            </div>

        </div>

        <p class="mt-6 text-xs" style="color: rgba(159,178,217,0.35);">© 2026 Playdrive · playdrive.be</p>
        </div>
    </div>
</template>

<script setup>
import { reactive, ref } from 'vue'

const form = reactive({
    first_name: '',
    last_name: '',
    email: '',
    password: '',
    password_confirmation: '',
})

const loading = ref(false)
const success = ref(false)
const errors = ref([])

async function submit() {
    errors.value = []

    if (form.password !== form.password_confirmation) {
        errors.value = ['De wachtwoorden komen niet overeen.']
        return
    }

    loading.value = true

    try {
        const res = await fetch('/member-api/v1/auth/register', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content ?? '',
            },
            body: JSON.stringify(form),
        })

        const data = await res.json()

        if (!res.ok) {
            if (data.errors) {
                errors.value = Object.values(data.errors).flat()
            } else {
                errors.value = [data.message ?? 'Er ging iets mis. Probeer opnieuw.']
            }
            return
        }

        success.value = true

    } catch {
        errors.value = ['Verbinding mislukt. Controleer je internet en probeer opnieuw.']
    } finally {
        loading.value = false
    }
}
</script>
