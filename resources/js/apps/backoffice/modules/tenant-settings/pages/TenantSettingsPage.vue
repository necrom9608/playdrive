<template>
    <div class="space-y-6">
        <div class="flex flex-wrap items-start justify-between gap-4">
            <div>
                <h1 class="text-3xl font-bold text-white">Tenantinstellingen</h1>
                <p class="mt-2 text-slate-400">
                    Beheer hier de standaardgegevens van deze tenant voor bonnen en algemene communicatie.
                </p>
            </div>

            <button
                type="button"
                class="rounded-2xl bg-blue-600 px-4 py-3 text-sm font-semibold text-white transition hover:bg-blue-500 disabled:cursor-not-allowed disabled:opacity-50"
                :disabled="loading || saving"
                @click="save"
            >
                {{ saving ? 'Opslaan...' : 'Opslaan' }}
            </button>
        </div>

        <div v-if="error" class="rounded-2xl border border-rose-500/30 bg-rose-500/10 px-5 py-4 text-sm text-rose-200">
            {{ error }}
        </div>

        <div v-if="success" class="rounded-2xl border border-emerald-500/30 bg-emerald-500/10 px-5 py-4 text-sm text-emerald-200">
            {{ success }}
        </div>

        <div class="grid gap-6 xl:grid-cols-[minmax(0,1.3fr)_minmax(320px,0.7fr)]">
            <section class="rounded-3xl border border-slate-800 bg-slate-900/80 p-6 shadow-xl shadow-slate-950/20">
                <h2 class="text-xl font-semibold text-white">Algemene gegevens</h2>
                <div class="mt-6 grid gap-4 md:grid-cols-2">
                    <label class="space-y-2 md:col-span-2">
                        <span class="text-sm font-medium text-slate-300">Naam</span>
                        <input v-model="form.name" type="text" class="w-full rounded-2xl border border-slate-700 bg-slate-950 px-4 py-3 text-sm text-white outline-none focus:border-sky-500">
                    </label>

                    <label class="space-y-2 md:col-span-2">
                        <span class="text-sm font-medium text-slate-300">Bedrijfsnaam / handelsnaam</span>
                        <input v-model="form.company_name" type="text" class="w-full rounded-2xl border border-slate-700 bg-slate-950 px-4 py-3 text-sm text-white outline-none focus:border-sky-500">
                    </label>

                    <label class="space-y-2">
                        <span class="text-sm font-medium text-slate-300">Telefoon</span>
                        <input v-model="form.phone" type="text" class="w-full rounded-2xl border border-slate-700 bg-slate-950 px-4 py-3 text-sm text-white outline-none focus:border-sky-500">
                    </label>

                    <label class="space-y-2">
                        <span class="text-sm font-medium text-slate-300">E-mail</span>
                        <input v-model="form.email" type="email" class="w-full rounded-2xl border border-slate-700 bg-slate-950 px-4 py-3 text-sm text-white outline-none focus:border-sky-500">
                    </label>

                    <label class="space-y-2">
                        <span class="text-sm font-medium text-slate-300">BTW-nummer</span>
                        <input v-model="form.vat_number" type="text" class="w-full rounded-2xl border border-slate-700 bg-slate-950 px-4 py-3 text-sm text-white outline-none focus:border-sky-500">
                    </label>
                </div>
            </section>

            <section class="rounded-3xl border border-slate-800 bg-slate-900/80 p-6 shadow-xl shadow-slate-950/20">
                <h2 class="text-xl font-semibold text-white">Logo</h2>
                <div class="mt-5 space-y-4">
                    <div class="flex min-h-[220px] items-center justify-center rounded-3xl border border-dashed border-slate-700 bg-slate-950/60 p-6">
                        <img v-if="logoPreview" :src="logoPreview" alt="Logo preview" class="max-h-40 max-w-full object-contain">
                        <div v-else class="text-center text-sm text-slate-500">Nog geen logo opgeladen.</div>
                    </div>

                    <input type="file" accept="image/*" @change="handleLogoChange">

                    <button
                        v-if="logoPreview"
                        type="button"
                        class="rounded-2xl border border-slate-700 bg-slate-900 px-4 py-3 text-sm font-semibold text-slate-200 transition hover:bg-slate-800"
                        @click="removeLogo"
                    >
                        Logo verwijderen
                    </button>
                </div>
            </section>
        </div>

        <section class="rounded-3xl border border-slate-800 bg-slate-900/80 p-6 shadow-xl shadow-slate-950/20">
            <h2 class="text-xl font-semibold text-white">Adres</h2>
            <div class="mt-6 grid gap-4 md:grid-cols-4">
                <label class="space-y-2 md:col-span-3">
                    <span class="text-sm font-medium text-slate-300">Straat</span>
                    <input v-model="form.street" type="text" class="w-full rounded-2xl border border-slate-700 bg-slate-950 px-4 py-3 text-sm text-white outline-none focus:border-sky-500">
                </label>

                <label class="space-y-2">
                    <span class="text-sm font-medium text-slate-300">Nr</span>
                    <input v-model="form.number" type="text" class="w-full rounded-2xl border border-slate-700 bg-slate-950 px-4 py-3 text-sm text-white outline-none focus:border-sky-500">
                </label>

                <label class="space-y-2">
                    <span class="text-sm font-medium text-slate-300">Postcode</span>
                    <input v-model="form.postal_code" type="text" class="w-full rounded-2xl border border-slate-700 bg-slate-950 px-4 py-3 text-sm text-white outline-none focus:border-sky-500">
                </label>

                <label class="space-y-2 md:col-span-2">
                    <span class="text-sm font-medium text-slate-300">Gemeente</span>
                    <input v-model="form.city" type="text" class="w-full rounded-2xl border border-slate-700 bg-slate-950 px-4 py-3 text-sm text-white outline-none focus:border-sky-500">
                </label>

                <label class="space-y-2">
                    <span class="text-sm font-medium text-slate-300">Land</span>
                    <input v-model="form.country" type="text" class="w-full rounded-2xl border border-slate-700 bg-slate-950 px-4 py-3 text-sm text-white outline-none focus:border-sky-500">
                </label>
            </div>
        </section>

        <section class="rounded-3xl border border-slate-800 bg-slate-900/80 p-6 shadow-xl shadow-slate-950/20">
            <h2 class="text-xl font-semibold text-white">Bon footer</h2>
            <textarea
                v-model="form.receipt_footer"
                rows="4"
                class="mt-5 w-full rounded-2xl border border-slate-700 bg-slate-950 px-4 py-3 text-sm text-white outline-none focus:border-sky-500"
                placeholder="Bijvoorbeeld: Bedankt voor je bezoek!"
            />
        </section>
    </div>
</template>

<script setup>
import { onMounted, ref } from 'vue'
import { fetchTenantSettings, updateTenantSettings } from '../services/tenantSettingsApi'

const loading = ref(false)
const saving = ref(false)
const error = ref('')
const success = ref('')
const logoPreview = ref('')
const logoFile = ref(null)
const removeLogoFlag = ref(false)
const form = ref({
    name: '',
    company_name: '',
    street: '',
    number: '',
    postal_code: '',
    city: '',
    country: '',
    vat_number: '',
    phone: '',
    email: '',
    receipt_footer: '',
})

function applyData(data = {}) {
    form.value = {
        name: data.name ?? '',
        company_name: data.company_name ?? '',
        street: data.street ?? '',
        number: data.number ?? '',
        postal_code: data.postal_code ?? '',
        city: data.city ?? '',
        country: data.country ?? '',
        vat_number: data.vat_number ?? '',
        phone: data.phone ?? '',
        email: data.email ?? '',
        receipt_footer: data.receipt_footer ?? '',
    }

    logoPreview.value = data.logo_url ?? ''
    logoFile.value = null
    removeLogoFlag.value = false
}

async function load() {
    loading.value = true
    error.value = ''

    try {
        const response = await fetchTenantSettings()
        applyData(response?.data ?? {})
    } catch (err) {
        console.error(err)
        error.value = err?.data?.message ?? err?.message ?? 'Kon tenantinstellingen niet laden.'
    } finally {
        loading.value = false
    }
}

function handleLogoChange(event) {
    const file = event.target.files?.[0] ?? null

    if (!file) {
        return
    }

    logoFile.value = file
    removeLogoFlag.value = false
    logoPreview.value = URL.createObjectURL(file)
}

function removeLogo() {
    logoFile.value = null
    removeLogoFlag.value = true
    logoPreview.value = ''
}

async function save() {
    saving.value = true
    error.value = ''
    success.value = ''

    try {
        const response = await updateTenantSettings({
            ...form.value,
            logo: logoFile.value,
            remove_logo: removeLogoFlag.value,
        })

        applyData(response?.data ?? {})
        success.value = response?.message ?? 'Tenantinstellingen werden opgeslagen.'
    } catch (err) {
        console.error(err)
        error.value = err?.data?.message ?? err?.message ?? 'Opslaan mislukt.'
    } finally {
        saving.value = false
    }
}

onMounted(load)
</script>
