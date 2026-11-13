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
            <h2 class="text-xl font-semibold text-white">Familieabonnement</h2>
            <p class="mt-2 text-sm text-slate-400">
                Maximum aantal personen per familieabonnement. Leeg laten = onbeperkt. Het hoofd telt mee als volwassene.
                Bij overschrijding krijgt de frontdesk een melding en kan toch toevoegen.
            </p>
            <div class="mt-5 grid gap-4 md:grid-cols-2">
                <label class="space-y-2">
                    <span class="text-sm font-medium text-slate-300">Max. volwassenen</span>
                    <input v-model="form.family_max_adults" type="number" min="1" placeholder="Onbeperkt" class="w-full rounded-2xl border border-slate-700 bg-slate-950 px-4 py-3 text-sm text-white outline-none focus:border-sky-500">
                </label>
                <label class="space-y-2">
                    <span class="text-sm font-medium text-slate-300">Max. kinderen / studenten</span>
                    <input v-model="form.family_max_children" type="number" min="0" placeholder="Onbeperkt" class="w-full rounded-2xl border border-slate-700 bg-slate-950 px-4 py-3 text-sm text-white outline-none focus:border-sky-500">
                </label>
            </div>
        </section>

        <section class="rounded-3xl border border-slate-800 bg-slate-900/80 p-6 shadow-xl shadow-slate-950/20">
            <h2 class="text-xl font-semibold text-white">Nieuwsbrief</h2>
            <p class="mt-2 text-sm text-slate-400">
                Bij "lid worden" (QR aan de kassa) krijgen klanten een vinkje voor je nieuwsbrief. Wie het aanvinkt,
                wordt doorgestuurd naar je eigen nieuwsbrief (bv. game-inn.be), die zelf de bevestigingsmail stuurt.
                Leeg laten = geen vinkje.
            </p>
            <div class="mt-5 grid gap-4 md:grid-cols-2">
                <label class="space-y-2">
                    <span class="text-sm font-medium text-slate-300">Adres voor inschrijvingen</span>
                    <input v-model="form.newsletter_url" type="url" placeholder="https://game-inn.be/api/nieuwsbrief/playdrive" class="w-full rounded-2xl border border-slate-700 bg-slate-950 px-4 py-3 text-sm text-white outline-none focus:border-sky-500">
                </label>
                <label class="space-y-2">
                    <span class="text-sm font-medium text-slate-300">Token</span>
                    <input v-model="form.newsletter_token" type="password" autocomplete="new-password" :placeholder="newsletterTokenSet ? 'Ingesteld · leeg laten om te behouden' : 'Zelfde als NIEUWSBRIEF_PLAYDRIVE_TOKEN op de site'" class="w-full rounded-2xl border border-slate-700 bg-slate-950 px-4 py-3 text-sm text-white outline-none focus:border-sky-500">
                    <button v-if="newsletterTokenSet" type="button" class="text-xs text-rose-300 hover:text-rose-200" @click="clearNewsletterToken = true; newsletterTokenSet = false">Token wissen</button>
                </label>
            </div>
        </section>

        <section class="rounded-3xl border border-slate-800 bg-slate-900/80 p-6 shadow-xl shadow-slate-950/20">
            <div class="flex flex-wrap items-start justify-between gap-4">
                <div>
                    <h2 class="text-xl font-semibold text-white">WeFact (facturen)</h2>
                    <p class="mt-2 max-w-3xl text-sm text-slate-400">
                        Facturen van de kassa gaan rechtstreeks naar WeFact: definitief, als betaald gemarkeerd en verstuurd
                        (via Peppol bij een Belgisch btw-nummer, anders per e-mail). Zet het IP-adres van de Playdrive-server
                        op de whitelist in WeFact → Instellingen → API.
                    </p>
                </div>
                <button
                    type="button"
                    class="rounded-2xl border border-slate-700 bg-slate-800 px-4 py-2.5 text-sm font-semibold text-slate-200 transition hover:bg-slate-700 disabled:opacity-50"
                    :disabled="saving || (!wefactKeySet && !form.wefact_api_key)"
                    @click="save({ test_wefact: true })"
                >
                    Verbinding testen
                </button>
            </div>
            <div class="mt-5 grid gap-4 md:grid-cols-2">
                <label class="space-y-2">
                    <span class="text-sm font-medium text-slate-300">API-sleutel</span>
                    <input v-model="form.wefact_api_key" type="password" autocomplete="new-password" :placeholder="wefactKeySet ? 'Ingesteld · leeg laten om te behouden' : 'WeFact → Instellingen → API'" class="w-full rounded-2xl border border-slate-700 bg-slate-950 px-4 py-3 text-sm text-white outline-none focus:border-sky-500">
                    <button v-if="wefactKeySet" type="button" class="text-xs text-rose-300 hover:text-rose-200" @click="clearWefactKey = true; wefactKeySet = false">Sleutel wissen (facturen uit)</button>
                </label>
                <label class="space-y-2">
                    <span class="text-sm font-medium text-slate-300">Betaaltermijn "Op factuur" (dagen)</span>
                    <input v-model="form.wefact_payment_term" type="number" min="0" max="120" placeholder="14" class="w-full rounded-2xl border border-slate-700 bg-slate-950 px-4 py-3 text-sm text-white outline-none focus:border-sky-500">
                    <span class="block text-xs text-slate-500">Voor firma's die achteraf betalen. Herinneringen stuurt WeFact volgens je instellingen daar.</span>
                </label>
                <div class="space-y-2">
                    <span class="text-sm font-medium text-slate-300">Btw-codes in WeFact</span>
                    <div class="grid grid-cols-4 gap-2">
                        <label v-for="rate in ['21', '12', '6', '0']" :key="rate" class="space-y-1">
                            <span class="block text-xs text-slate-500">{{ rate }}%</span>
                            <input v-model="form[`wefact_tax_${rate}`]" type="text" class="w-full rounded-xl border border-slate-700 bg-slate-950 px-3 py-2 text-sm text-white outline-none focus:border-sky-500">
                        </label>
                    </div>
                </div>
            </div>
            <div v-if="wefactError" class="mt-4 rounded-2xl border border-amber-500/30 bg-amber-500/10 px-4 py-3 text-sm text-amber-200">{{ wefactError }}</div>
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
const newsletterTokenSet = ref(false)
const clearNewsletterToken = ref(false)
const wefactKeySet = ref(false)
const clearWefactKey = ref(false)
const wefactError = ref('')
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
    family_max_adults: '',
    family_max_children: '',
    newsletter_url: '',
    newsletter_token: '',
    wefact_api_key: '',
    wefact_tax_21: 'V21',
    wefact_tax_12: 'V12',
    wefact_tax_6: 'V6',
    wefact_tax_0: 'V0',
    wefact_payment_term: 14,
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
        family_max_adults: data.family_max_adults ?? '',
        family_max_children: data.family_max_children ?? '',
        newsletter_url: data.newsletter_url ?? '',
        newsletter_token: '',
        wefact_api_key: '',
        wefact_tax_21: data.wefact_tax_codes?.['21'] ?? 'V21',
        wefact_tax_12: data.wefact_tax_codes?.['12'] ?? 'V12',
        wefact_tax_6: data.wefact_tax_codes?.['6'] ?? 'V6',
        wefact_tax_0: data.wefact_tax_codes?.['0'] ?? 'V0',
        wefact_payment_term: data.wefact_payment_term ?? 14,
    }
    newsletterTokenSet.value = !!data.newsletter_token_set
    wefactKeySet.value = !!data.wefact_api_key_set
    clearWefactKey.value = false
    clearNewsletterToken.value = false

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

async function save(extra = {}) {
    saving.value = true
    error.value = ''
    success.value = ''
    wefactError.value = ''

    try {
        const response = await updateTenantSettings({
            ...form.value,
            logo: logoFile.value,
            remove_logo: removeLogoFlag.value,
            clear_newsletter_token: clearNewsletterToken.value,
            clear_wefact_api_key: clearWefactKey.value,
            test_wefact: !!extra.test_wefact,
        })

        applyData(response?.data ?? {})
        wefactError.value = response?.wefact_error ?? ''
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
