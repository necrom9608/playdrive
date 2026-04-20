<template>
    <div class="min-h-screen flex flex-col items-center justify-center px-5 py-10"
         style="background: radial-gradient(ellipse at 30% 20%, rgba(6,182,212,0.08) 0%, transparent 60%), radial-gradient(ellipse at 80% 80%, rgba(99,102,241,0.08) 0%, transparent 60%), #020817;">

        <!-- Laadscherm -->
        <div v-if="pageLoading" class="flex flex-col items-center gap-4">
            <div class="h-8 w-8 animate-spin rounded-full border-2 border-white/10 border-t-cyan-400"></div>
            <p class="text-sm text-slate-500">Laden…</p>
        </div>

        <!-- Niet gevonden -->
        <div v-else-if="!tenant" class="text-center">
            <p class="text-slate-400">Locatie niet gevonden of niet actief.</p>
        </div>

        <!-- Registratieformulier -->
        <div v-else class="w-full max-w-md">

            <!-- Logo + naam tenant -->
            <div class="mb-8 flex flex-col items-center gap-4 text-center">
                <img
                    v-if="tenant.logo_url"
                    :src="tenant.logo_url"
                    :alt="tenant.name"
                    class="h-16 w-auto object-contain"
                />
                <div v-else
                    class="flex h-16 w-16 items-center justify-center rounded-2xl border border-white/10 bg-white/5 text-2xl font-bold text-white">
                    {{ tenant.name?.charAt(0) }}
                </div>
                <div>
                    <div class="text-xs uppercase tracking-[0.3em] text-cyan-400">PlayDrive</div>
                    <h1 class="mt-1 text-2xl font-semibold text-white">{{ tenant.name }}</h1>
                    <p class="mt-1 text-sm text-slate-400">Maak je account aan</p>
                </div>
            </div>

            <!-- Succes scherm -->
            <div v-if="success"
                class="rounded-3xl border border-emerald-500/20 bg-emerald-500/10 p-8 text-center backdrop-blur-sm">
                <div class="mx-auto mb-4 flex h-14 w-14 items-center justify-center rounded-2xl border border-emerald-400/30 bg-emerald-500/15">
                    <svg class="h-7 w-7 text-emerald-300" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z" />
                    </svg>
                </div>
                <h2 class="text-xl font-semibold text-white">Bevestig je e-mailadres</h2>
                <p class="mt-3 text-sm leading-relaxed text-slate-300">
                    We hebben een bevestigingsmail gestuurd naar
                </p>
                <div class="mt-3 rounded-2xl border border-white/8 bg-white/4 px-4 py-3 text-sm font-medium text-slate-200">
                    {{ registeredEmail }}
                </div>
                <p class="mt-4 text-sm leading-relaxed text-slate-400">
                    Klik op de link in de e-mail om je account te activeren.<br>
                    Controleer ook je spam- of ongewenste e-mailmap.
                </p>

                <!-- Mail warning indien verzending mislukt -->
                <div v-if="mailWarning"
                    class="mt-4 rounded-2xl border border-amber-500/25 bg-amber-500/10 px-4 py-3 text-sm text-amber-200">
                    ⚠️ {{ mailWarning }}
                </div>
                <button
                    type="button"
                    :disabled="resendLoading || resendSent"
                    class="mt-6 text-xs text-slate-500 underline-offset-2 hover:text-slate-300 disabled:opacity-50 transition"
                    :class="resendSent ? '' : 'underline cursor-pointer'"
                    @click="resend"
                >
                    <span v-if="resendLoading">Bezig met verzenden…</span>
                    <span v-else-if="resendSent">✓ Nieuwe e-mail verstuurd</span>
                    <span v-else>Geen e-mail ontvangen? Opnieuw versturen</span>
                </button>
            </div>

            <!-- Formulier -->
            <form v-else
                class="rounded-3xl border border-white/10 p-6 shadow-2xl backdrop-blur-sm"
                style="background: linear-gradient(160deg, rgba(15,25,50,0.92) 0%, rgba(8,14,30,0.96) 100%);"
                @submit.prevent="submit"
            >
                <!-- Naam rij -->
                <div class="grid grid-cols-2 gap-3">
                    <label class="block">
                        <span class="mb-2 block text-sm text-slate-300">Voornaam</span>
                        <input
                            v-model="form.first_name"
                            type="text"
                            autocomplete="given-name"
                            class="w-full rounded-2xl border bg-slate-900/60 px-4 py-3 text-sm text-slate-100 outline-none transition backdrop-blur-sm"
                            :class="errors.first_name ? 'border-rose-500/60 focus:border-rose-400' : 'border-white/10 focus:border-cyan-500'"
                            placeholder="Jan"
                        />
                        <p v-if="errors.first_name" class="mt-1.5 text-xs text-rose-400">{{ errors.first_name }}</p>
                    </label>

                    <label class="block">
                        <span class="mb-2 block text-sm text-slate-300">Achternaam</span>
                        <input
                            v-model="form.last_name"
                            type="text"
                            autocomplete="family-name"
                            class="w-full rounded-2xl border bg-slate-900/60 px-4 py-3 text-sm text-slate-100 outline-none transition backdrop-blur-sm"
                            :class="errors.last_name ? 'border-rose-500/60 focus:border-rose-400' : 'border-white/10 focus:border-cyan-500'"
                            placeholder="Janssen"
                        />
                        <p v-if="errors.last_name" class="mt-1.5 text-xs text-rose-400">{{ errors.last_name }}</p>
                    </label>
                </div>

                <!-- E-mail -->
                <label class="mt-4 block">
                    <span class="mb-2 block text-sm text-slate-300">E-mailadres</span>
                    <input
                        v-model="form.email"
                        type="email"
                        autocomplete="email"
                        class="w-full rounded-2xl border bg-slate-900/60 px-4 py-3 text-sm text-slate-100 outline-none transition backdrop-blur-sm"
                        :class="errors.email ? 'border-rose-500/60 focus:border-rose-400' : 'border-white/10 focus:border-cyan-500'"
                        placeholder="jan@voorbeeld.be"
                    />
                    <p v-if="errors.email" class="mt-1.5 text-xs text-rose-400">{{ errors.email }}</p>
                </label>

                <!-- Wachtwoord -->
                <label class="mt-4 block">
                    <span class="mb-2 block text-sm text-slate-300">Wachtwoord</span>
                    <input
                        v-model="form.password"
                        type="password"
                        autocomplete="new-password"
                        class="w-full rounded-2xl border bg-slate-900/60 px-4 py-3 text-sm text-slate-100 outline-none transition backdrop-blur-sm"
                        :class="errors.password ? 'border-rose-500/60 focus:border-rose-400' : 'border-white/10 focus:border-cyan-500'"
                        placeholder="Minimaal 8 tekens"
                    />
                    <p v-if="errors.password" class="mt-1.5 text-xs text-rose-400">{{ errors.password }}</p>
                </label>

                <!-- Wachtwoord bevestigen -->
                <label class="mt-4 block">
                    <span class="mb-2 block text-sm text-slate-300">Wachtwoord bevestigen</span>
                    <input
                        v-model="form.password_confirmation"
                        type="password"
                        autocomplete="new-password"
                        class="w-full rounded-2xl border bg-slate-900/60 px-4 py-3 text-sm text-slate-100 outline-none transition backdrop-blur-sm"
                        :class="errors.password_confirmation ? 'border-rose-500/60 focus:border-rose-400' : 'border-white/10 focus:border-cyan-500'"
                        placeholder="Herhaal wachtwoord"
                    />
                    <p v-if="errors.password_confirmation" class="mt-1.5 text-xs text-rose-400">{{ errors.password_confirmation }}</p>
                </label>

                <!-- Algemene fout -->
                <div v-if="generalError"
                    class="mt-4 rounded-2xl border border-rose-500/25 bg-rose-500/10 px-4 py-3 text-sm text-rose-200">
                    {{ generalError }}
                </div>

                <!-- Submit -->
                <button
                    type="submit"
                    :disabled="loading"
                    class="mt-6 w-full rounded-2xl px-4 py-3.5 text-sm font-semibold text-white transition disabled:cursor-not-allowed disabled:opacity-50"
                    style="background: linear-gradient(180deg, #06b6d4 0%, #0891b2 100%);"
                >
                    <span v-if="loading" class="flex items-center justify-center gap-2">
                        <span class="h-4 w-4 animate-spin rounded-full border-2 border-white/30 border-t-white"></span>
                        Account aanmaken…
                    </span>
                    <span v-else>Account aanmaken</span>
                </button>
            </form>

            <!-- Footer -->
            <p class="mt-6 text-center text-xs text-slate-600">
                Aangedreven door <span class="text-slate-500">PlayDrive</span>
            </p>
        </div>
    </div>
</template>

<script setup>
import { onMounted, reactive, ref } from 'vue'
import { useRoute } from 'vue-router'
import axios from 'axios'

const route = useRoute()
const tenantSlug = route.params.tenant

const pageLoading = ref(true)
const tenant = ref(null)
const loading = ref(false)
const success = ref(false)
const registeredEmail = ref('')
const generalError = ref('')
const resendLoading = ref(false)
const resendSent = ref(false)
const mailWarning = ref('')
const errors = reactive({
    first_name: '',
    last_name: '',
    email: '',
    password: '',
    password_confirmation: '',
})

const form = reactive({
    first_name: '',
    last_name: '',
    email: '',
    password: '',
    password_confirmation: '',
})

onMounted(async () => {
    try {
        const { data } = await axios.get(`/api/register/${tenantSlug}`)
        tenant.value = data.data
    } catch {
        tenant.value = null
    } finally {
        pageLoading.value = false
    }
})

function clearErrors() {
    Object.keys(errors).forEach(k => errors[k] = '')
    generalError.value = ''
}

async function submit() {
    clearErrors()
    loading.value = true

    try {
        const res = await axios.post(`/api/register/${tenantSlug}`, { ...form })
        registeredEmail.value = form.email
        success.value = true
        if (res?.data?.mail_warning) {
            mailWarning.value = res.data.mail_warning
        }
    } catch (err) {
        const data = err?.response?.data
        if (data?.errors) {
            Object.entries(data.errors).forEach(([field, messages]) => {
                if (field in errors) errors[field] = messages[0]
            })
        } else {
            generalError.value = data?.message ?? 'Er is iets misgegaan. Probeer opnieuw.'
        }
    } finally {
        loading.value = false
    }
}

async function resend() {
    if (resendLoading.value || resendSent.value) return
    resendLoading.value = true
    try {
        await axios.post('/api/register/resend-verification', {
            email: registeredEmail.value,
            tenant_slug: tenantSlug,
        })
        resendSent.value = true
    } catch {
        // Stil falen — privacy
        resendSent.value = true
    } finally {
        resendLoading.value = false
    }
}
</script>
