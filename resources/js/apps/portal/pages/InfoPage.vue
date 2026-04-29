<template>
    <div class="mx-auto max-w-3xl space-y-6">
        <div>
            <h1 class="text-3xl font-bold text-white">Algemene info</h1>
            <p class="mt-2 text-slate-400">Basisgegevens die op je venuepagina verschijnen.</p>
        </div>

        <div v-if="loading" class="text-slate-400">Laden...</div>

        <form v-else @submit.prevent="save" class="space-y-6">

            <!-- Identiteit -->
            <Section title="Identiteit">
                <Field label="Naam" required>
                    <input v-model="form.name" type="text" class="input" required />
                </Field>
                <Field label="Bedrijfsnaam" hint="Optioneel — komt op facturen en lange beschrijvingen.">
                    <input v-model="form.company_name" type="text" class="input" />
                </Field>
                <Field label="Tagline" hint="Eén regel die zegt wat je bent (max 160 tekens).">
                    <input v-model="form.tagline" type="text" maxlength="160" class="input" />
                </Field>
                <Field label="Beschrijving" hint="Een paar paragrafen voor bezoekers.">
                    <textarea v-model="form.public_description" rows="6" maxlength="5000" class="input" />
                </Field>
            </Section>

            <!-- Locatie -->
            <Section title="Locatie">
                <div class="grid grid-cols-1 gap-3 md:grid-cols-[1fr_auto]">
                    <Field label="Straat">
                        <input v-model="form.street" type="text" class="input" />
                    </Field>
                    <Field label="Nr.">
                        <input v-model="form.number" type="text" class="input w-24" />
                    </Field>
                </div>
                <div class="grid grid-cols-1 gap-3 md:grid-cols-[auto_1fr]">
                    <Field label="Postcode">
                        <input v-model="form.postal_code" type="text" class="input w-32" />
                    </Field>
                    <Field label="Stad">
                        <input v-model="form.city" type="text" class="input" />
                    </Field>
                </div>
                <Field label="Land">
                    <input v-model="form.country" type="text" class="input" />
                </Field>
                <div class="grid grid-cols-1 gap-3 md:grid-cols-2">
                    <Field label="Latitude" hint="Optioneel, voor kaart op pagina.">
                        <input v-model.number="form.latitude" type="number" step="0.0000001" class="input" />
                    </Field>
                    <Field label="Longitude">
                        <input v-model.number="form.longitude" type="number" step="0.0000001" class="input" />
                    </Field>
                </div>
            </Section>

            <!-- Contact -->
            <Section title="Contact">
                <Field label="Telefoon">
                    <input v-model="form.phone" type="text" class="input" />
                </Field>
                <Field label="E-mail">
                    <input v-model="form.email" type="email" class="input" />
                </Field>
                <Field label="Eigen website" hint="Wordt op de pagina als knop getoond.">
                    <input v-model="form.website_url" type="url" placeholder="https://" class="input" />
                </Field>
                <Field label="Video-URL (YouTube of Vimeo)" hint="Eén video voor de pagina-hero.">
                    <input v-model="form.video_url" type="url" placeholder="https://youtu.be/..." class="input" />
                </Field>
            </Section>

            <!-- Doelgroep -->
            <Section title="Doelgroep" subtitle="Voor welke bezoekers is je venue geschikt?">
                <div class="grid grid-cols-1 gap-2 md:grid-cols-2">
                    <label
                        v-for="audience in availableAudiences"
                        :key="audience.key"
                        class="flex cursor-pointer items-center gap-3 rounded-xl border border-slate-700 bg-slate-900/40 px-4 py-3 transition hover:border-cyan-500/50"
                    >
                        <input
                            type="checkbox"
                            :value="audience.key"
                            v-model="form.target_audiences"
                            class="h-4 w-4 rounded border-slate-600 bg-slate-800 text-cyan-500"
                        />
                        <span class="text-sm text-slate-200">{{ audience.label }}</span>
                    </label>
                </div>
            </Section>

            <!-- Submit -->
            <div class="flex items-center gap-3 pt-2">
                <button
                    type="submit"
                    :disabled="saving"
                    class="rounded-xl bg-cyan-600 px-5 py-3 text-sm font-semibold text-white transition hover:bg-cyan-500 disabled:opacity-60"
                >
                    {{ saving ? 'Bezig met opslaan...' : 'Wijzigingen opslaan' }}
                </button>
                <span v-if="lastSaved" class="text-sm text-slate-400">
                    Opgeslagen — {{ lastSavedHuman }}
                </span>
                <span v-if="error" class="text-sm text-rose-400">{{ error }}</span>
            </div>
        </form>
    </div>
</template>

<script setup>
import { computed, onMounted, ref } from 'vue'
import Section from '../components/Section.vue'
import Field from '../components/Field.vue'
import { getInfo, updateInfo } from '../services/venueApi'

const loading = ref(true)
const saving = ref(false)
const error = ref('')
const lastSaved = ref(null)

const form = ref({
    name: '',
    company_name: '',
    tagline: '',
    public_description: '',
    street: '',
    number: '',
    postal_code: '',
    city: '',
    country: '',
    latitude: null,
    longitude: null,
    phone: '',
    email: '',
    website_url: '',
    video_url: '',
    target_audiences: [],
})

// Hardcoded — dit komt overeen met config/venue_audiences.php op de server.
// Houden we synchroon — voor V1 is dat de simpelste oplossing.
const availableAudiences = [
    { key: 'families', label: 'Families' },
    { key: 'kids_under_12', label: 'Kinderen tot 12' },
    { key: 'teens', label: 'Tieners' },
    { key: 'adults', label: 'Volwassenen' },
    { key: 'corporate', label: 'Bedrijven' },
    { key: 'birthdays', label: 'Verjaardagen' },
    { key: 'groups', label: 'Vriendengroepen' },
]

const lastSavedHuman = computed(() => {
    if (!lastSaved.value) return null
    const seconds = Math.floor((Date.now() - lastSaved.value) / 1000)
    if (seconds < 5) return 'zojuist'
    if (seconds < 60) return `${seconds}s geleden`
    return `${Math.floor(seconds / 60)}m geleden`
})

onMounted(async () => {
    try {
        const data = await getInfo()
        Object.keys(form.value).forEach(key => {
            if (key === 'target_audiences') {
                form.value[key] = Array.isArray(data[key]) ? data[key] : []
            } else if (data[key] !== undefined) {
                form.value[key] = data[key] ?? ''
            }
        })
    } finally {
        loading.value = false
    }
})

async function save() {
    saving.value = true
    error.value = ''

    try {
        const payload = { ...form.value }
        // Lege strings naar null voor optionele velden
        for (const key of Object.keys(payload)) {
            if (payload[key] === '' && key !== 'name') payload[key] = null
        }
        await updateInfo(payload)
        lastSaved.value = Date.now()
    } catch (err) {
        error.value = err?.data?.message || 'Opslaan mislukt.'
    } finally {
        saving.value = false
    }
}
</script>

<style scoped>
.input {
    width: 100%;
    border-radius: 0.75rem;
    border: 1px solid rgb(51 65 85);
    background: rgb(2 6 23);
    padding: 0.6rem 0.9rem;
    color: white;
    outline: none;
    transition: border-color 0.15s;
}
.input:focus { border-color: rgb(6 182 212); }
</style>
