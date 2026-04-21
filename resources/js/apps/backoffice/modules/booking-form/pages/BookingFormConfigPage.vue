<template>
    <div class="space-y-8">

        <!-- Header -->
        <div class="flex flex-wrap items-start justify-between gap-4">
            <div>
                <h1 class="text-3xl font-bold text-white">Reservatieformulier</h1>
                <p class="mt-2 text-slate-400">
                    Stel in hoe het reservatieformulier zich gedraagt voor deze venue.
                </p>
            </div>
            <button
                type="button"
                :disabled="saving"
                class="rounded-xl bg-blue-600 px-4 py-3 text-sm font-semibold text-white transition hover:bg-blue-500 disabled:opacity-60"
                @click="save"
            >
                {{ saving ? 'Opslaan...' : 'Opslaan' }}
            </button>
        </div>

        <!-- Feedback -->
        <div v-if="error" class="rounded-2xl border border-rose-500/30 bg-rose-500/10 px-5 py-4 text-sm text-rose-200">
            {{ error }}
        </div>
        <div v-if="success" class="rounded-2xl border border-emerald-500/30 bg-emerald-500/10 px-5 py-4 text-sm text-emerald-200">
            {{ success }}
        </div>

        <div v-if="loading" class="py-12 text-center text-sm text-slate-400">Laden...</div>

        <template v-else>

            <!-- Sectie 1: Algemeen -->
            <section class="rounded-3xl border border-slate-800 bg-slate-900/80 p-6 space-y-5">
                <h2 class="text-base font-semibold text-white">Algemeen</h2>

                <label class="flex items-center justify-between gap-4 cursor-pointer">
                    <div>
                        <p class="text-sm font-medium text-slate-200">Formulier actief</p>
                        <p class="text-xs text-slate-400 mt-0.5">Zet het reservatieformulier aan of uit voor deze venue.</p>
                    </div>
                    <ToggleSwitch v-model="form.config.is_active" />
                </label>

                <label class="flex items-center justify-between gap-4 cursor-pointer">
                    <div>
                        <p class="text-sm font-medium text-slate-200">Waarschuwing buiten openingsuren</p>
                        <p class="text-xs text-slate-400 mt-0.5">
                            Toon een melding met minimumtarief wanneer er buiten de openingsuren gereserveerd wordt.
                        </p>
                    </div>
                    <ToggleSwitch v-model="form.config.outside_hours_warning_enabled" />
                </label>

                <div class="flex items-center justify-between gap-4">
                    <div>
                        <p class="text-sm font-medium text-slate-200">Annuleringstermijn</p>
                        <p class="text-xs text-slate-400 mt-0.5">
                            Klanten kunnen enkel annuleren als het event nog minstens dit aantal uur in de toekomst ligt.
                        </p>
                    </div>
                    <div class="flex items-center gap-2 shrink-0">
                        <input
                            v-model.number="form.config.cancellation_hours_before"
                            type="number"
                            min="0"
                            max="720"
                            class="w-20 rounded-xl border border-slate-700 bg-slate-800 px-3 py-1.5 text-sm text-white text-center focus:outline-none focus:border-blue-500"
                        />
                        <span class="text-sm text-slate-400">uur</span>
                    </div>
                </div>
            </section>

            <!-- Sectie 2: Persoonsgroepen -->
            <section class="rounded-3xl border border-slate-800 bg-slate-900/80 p-6 space-y-5">
                <div>
                    <h2 class="text-base font-semibold text-white">Persoonsgroepen</h2>
                    <p class="text-xs text-slate-400 mt-1">Welke categorieën worden gevraagd in het formulier.</p>
                </div>

                <label class="flex items-center justify-between gap-4 cursor-pointer">
                    <span class="text-sm text-slate-200">Kinderen</span>
                    <ToggleSwitch v-model="form.config.show_participant_children" />
                </label>
                <label class="flex items-center justify-between gap-4 cursor-pointer">
                    <span class="text-sm text-slate-200">Volwassenen</span>
                    <ToggleSwitch v-model="form.config.show_participant_adults" />
                </label>
                <label class="flex items-center justify-between gap-4 cursor-pointer">
                    <span class="text-sm text-slate-200">Begeleiders</span>
                    <ToggleSwitch v-model="form.config.show_participant_supervisors" />
                </label>
            </section>

            <!-- Sectie 3: Event-types -->
            <section class="rounded-3xl border border-slate-800 bg-slate-900/80 p-6 space-y-6">
                <div>
                    <h2 class="text-base font-semibold text-white">Event-types</h2>
                    <p class="text-xs text-slate-400 mt-1">
                        Welke event-types zichtbaar zijn in het formulier en hoe de doelgroep- en cateringkeuze werkt.
                    </p>
                </div>

                <div
                    v-for="(etConfig, index) in form.event_type_configs"
                    :key="etConfig.event_type_id"
                    class="rounded-2xl border border-slate-700/60 bg-slate-800/40 p-5 space-y-4"
                >
                    <!-- Event-type header -->
                    <div class="flex items-center justify-between gap-4">
                        <div class="flex items-center gap-2">
                            <span class="text-xl">{{ eventTypeMeta(etConfig.event_type_id)?.emoji }}</span>
                            <span class="text-sm font-semibold text-white">
                                {{ eventTypeMeta(etConfig.event_type_id)?.name }}
                            </span>
                        </div>
                        <label class="flex items-center gap-2 cursor-pointer">
                            <span class="text-xs text-slate-400">Zichtbaar</span>
                            <ToggleSwitch v-model="etConfig.show_in_form" />
                        </label>
                    </div>

                    <template v-if="etConfig.show_in_form">
                        <!-- Audience mode -->
                        <div>
                            <label class="mb-1.5 block text-xs font-medium text-slate-400">Doelgroepvraag</label>
                            <div class="flex flex-wrap gap-2">
                                <button
                                    v-for="mode in audienceModes"
                                    :key="mode.value"
                                    type="button"
                                    class="rounded-lg border px-3 py-1.5 text-xs font-medium transition"
                                    :class="etConfig.audience_mode === mode.value
                                        ? 'border-blue-500 bg-blue-600/20 text-blue-300'
                                        : 'border-slate-700 text-slate-400 hover:border-slate-500 hover:text-slate-200'"
                                    @click="etConfig.audience_mode = mode.value"
                                >
                                    {{ mode.label }}
                                </button>
                            </div>
                        </div>

                        <!-- Audience options -->
                        <div v-if="etConfig.audience_mode !== 'none'" class="space-y-3">
                            <label class="block text-xs font-medium text-slate-400">
                                Catering per doelgroep
                            </label>

                            <div
                                v-for="(audienceOpt, aIdx) in audienceOptionsFor(etConfig)"
                                :key="audienceOpt.audience"
                                class="rounded-xl border border-slate-700/40 bg-slate-900/60 p-4 space-y-3"
                            >
                                <p class="text-xs font-semibold text-slate-300">{{ audienceOpt.label }}</p>

                                <!-- Auto catering of vrije keuze -->
                                <div class="flex flex-wrap gap-2">
                                    <button
                                        type="button"
                                        class="rounded-lg border px-3 py-1.5 text-xs font-medium transition"
                                        :class="getCateringMode(etConfig, index, aIdx) === 'auto'
                                            ? 'border-blue-500 bg-blue-600/20 text-blue-300'
                                            : 'border-slate-700 text-slate-400 hover:border-slate-500'"
                                        @click="setCateringMode(etConfig, index, aIdx, 'auto')"
                                    >
                                        Automatisch koppelen
                                    </button>
                                    <button
                                        type="button"
                                        class="rounded-lg border px-3 py-1.5 text-xs font-medium transition"
                                        :class="getCateringMode(etConfig, index, aIdx) === 'choice'
                                            ? 'border-blue-500 bg-blue-600/20 text-blue-300'
                                            : 'border-slate-700 text-slate-400 hover:border-slate-500'"
                                        @click="setCateringMode(etConfig, index, aIdx, 'choice')"
                                    >
                                        Gebruiker kiest
                                    </button>
                                    <button
                                        type="button"
                                        class="rounded-lg border px-3 py-1.5 text-xs font-medium transition"
                                        :class="getCateringMode(etConfig, index, aIdx) === 'none'
                                            ? 'border-blue-500 bg-blue-600/20 text-blue-300'
                                            : 'border-slate-700 text-slate-400 hover:border-slate-500'"
                                        @click="setCateringMode(etConfig, index, aIdx, 'none')"
                                    >
                                        Geen catering
                                    </button>
                                </div>

                                <!-- Auto: kies één catering-optie -->
                                <div v-if="getCateringMode(etConfig, index, aIdx) === 'auto'">
                                    <label class="mb-1 block text-xs text-slate-400">Welke catering automatisch koppelen?</label>
                                    <select
                                        :value="audienceOpt.auto_catering_option_id"
                                        class="rounded-xl border border-slate-700 bg-slate-950 px-3 py-2 text-sm text-white outline-none focus:border-blue-500"
                                        @change="setAutoCatering(etConfig, index, aIdx, $event.target.value)"
                                    >
                                        <option value="">— Kies catering —</option>
                                        <option v-for="c in cateringOptions" :key="c.id" :value="c.id">
                                            {{ c.emoji }} {{ c.name }}
                                        </option>
                                    </select>
                                </div>

                                <!-- Choice: vink aan welke catering-opties beschikbaar zijn -->
                                <div v-if="getCateringMode(etConfig, index, aIdx) === 'choice'" class="space-y-2">
                                    <label class="block text-xs text-slate-400">Welke opties mag de gebruiker kiezen?</label>
                                    <label class="flex items-center gap-2 text-xs text-slate-300 cursor-pointer">
                                        <input
                                            type="checkbox"
                                            :checked="audienceOpt.catering_choices?.includes(null)"
                                            class="h-3.5 w-3.5 rounded border-slate-600 bg-slate-950"
                                            @change="toggleCateringChoice(etConfig, index, aIdx, null, $event.target.checked)"
                                        />
                                        Geen catering
                                    </label>
                                    <label
                                        v-for="c in cateringOptions"
                                        :key="c.id"
                                        class="flex items-center gap-2 text-xs text-slate-300 cursor-pointer"
                                    >
                                        <input
                                            type="checkbox"
                                            :checked="audienceOpt.catering_choices?.includes(c.id)"
                                            class="h-3.5 w-3.5 rounded border-slate-600 bg-slate-950"
                                            @change="toggleCateringChoice(etConfig, index, aIdx, c.id, $event.target.checked)"
                                        />
                                        {{ c.emoji }} {{ c.name }}
                                    </label>
                                </div>
                            </div>
                        </div>
                    </template>
                </div>
            </section>

            <!-- Sectie 4: Stay-opties -->
            <section class="rounded-3xl border border-slate-800 bg-slate-900/80 p-6 space-y-5">
                <div>
                    <h2 class="text-base font-semibold text-white">Verblijfsopties</h2>
                    <p class="text-xs text-slate-400 mt-1">
                        Welke opties zichtbaar zijn en wat het minimumtarief is bij reservaties buiten de openingsuren.
                    </p>
                </div>

                <div class="overflow-x-auto">
                    <table class="min-w-full text-sm">
                        <thead>
                            <tr class="text-left text-xs text-slate-400 border-b border-slate-800">
                                <th class="pb-3 pr-4 font-medium">Optie</th>
                                <th class="pb-3 pr-4 font-medium">Duur</th>
                                <th class="pb-3 pr-4 font-medium">Zichtbaar</th>
                                <th class="pb-3 font-medium">Min. omzet buiten uren</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr
                                v-for="soConfig in form.stay_option_configs"
                                :key="soConfig.stay_option_id"
                                class="border-t border-slate-800/60"
                            >
                                <td class="py-3 pr-4 font-medium text-white">
                                    {{ stayOptionMeta(soConfig.stay_option_id)?.name }}
                                </td>
                                <td class="py-3 pr-4 text-slate-400">
                                    {{ formatDuration(stayOptionMeta(soConfig.stay_option_id)?.duration_minutes) }}
                                </td>
                                <td class="py-3 pr-4">
                                    <ToggleSwitch v-model="soConfig.show_in_form" />
                                </td>
                                <td class="py-3">
                                    <div class="flex items-center gap-2">
                                        <span class="text-slate-400 text-xs">€</span>
                                        <input
                                            :value="centsToEuros(soConfig.min_revenue_outside_hours_cents)"
                                            type="number"
                                            min="0"
                                            step="1"
                                            placeholder="Geen minimum"
                                            class="w-36 rounded-xl border border-slate-700 bg-slate-950 px-3 py-2 text-sm text-white outline-none focus:border-blue-500 placeholder:text-slate-600"
                                            @input="soConfig.min_revenue_outside_hours_cents = eurosToCents($event.target.value)"
                                        />
                                    </div>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </section>

        </template>
    </div>
</template>

<script setup>
import { onMounted, reactive, ref } from 'vue'
import ToggleSwitch from '../../../components/ToggleSwitch.vue'
import { fetchBookingFormConfig, saveBookingFormConfig } from '../services/bookingFormApi'

// ──────────────────────────────────────────────────────────────────────────────
// State
// ──────────────────────────────────────────────────────────────────────────────

const loading   = ref(false)
const saving    = ref(false)
const error     = ref('')
const success   = ref('')

// Raw lookup data (voor namen/emoji in de UI)
const rawEventTypes    = ref([])
const rawStayOptions   = ref([])
const cateringOptions  = ref([])

// Bewerkbaar formulier
const form = reactive({
    config: {
        is_active:                     true,
        show_participant_children:     true,
        show_participant_adults:       true,
        show_participant_supervisors:  false,
        outside_hours_warning_enabled: true,
    },
    event_type_configs:  [],
    stay_option_configs: [],
})

const audienceModes = [
    { value: 'none',            label: 'Geen' },
    { value: 'children_adults', label: 'Kinderen / Volwassenen' },
    { value: 'adults_only',     label: 'Altijd volwassenen' },
]

// ──────────────────────────────────────────────────────────────────────────────
// Helpers
// ──────────────────────────────────────────────────────────────────────────────

function eventTypeMeta(id) {
    return rawEventTypes.value.find((et) => et.event_type_id === id)
}

function stayOptionMeta(id) {
    return rawStayOptions.value.find((so) => so.stay_option_id === id)
}

function formatDuration(minutes) {
    if (!minutes) return '—'
    if (minutes < 60) return `${minutes} min`
    const h = Math.floor(minutes / 60)
    const m = minutes % 60
    return m > 0 ? `${h}u ${m}min` : `${h}u`
}

function centsToEuros(cents) {
    if (cents == null || cents === '') return ''
    return cents / 100
}

function eurosToCents(euros) {
    if (euros === '' || euros == null) return null
    const val = parseFloat(euros)
    return isNaN(val) ? null : Math.round(val * 100)
}

/**
 * Geeft de audience_options array terug voor een event-type config,
 * gegenereerd op basis van de audience_mode.
 */
function audienceOptionsFor(etConfig) {
    const opts = etConfig.audience_options || []
    if (etConfig.audience_mode === 'children_adults') {
        const children = opts.find((o) => o.audience === 'children') ?? { audience: 'children', label: 'Kinderen / jongeren' }
        const adults   = opts.find((o) => o.audience === 'adults')   ?? { audience: 'adults',   label: 'Volwassenen' }
        return [children, adults]
    }
    if (etConfig.audience_mode === 'adults_only') {
        const def = opts.find((o) => o.audience === 'default') ?? { audience: 'default', label: 'Volwassenen' }
        return [def]
    }
    return []
}

/**
 * Bepaalt welke catering-modus actief is voor een audience-optie:
 * 'auto' | 'choice' | 'none'
 */
function getCateringMode(etConfig, etIdx, aIdx) {
    const opt = audienceOptionsFor(etConfig)[aIdx]
    if (!opt) return 'none'
    if (opt.auto_catering_option_id != null) return 'auto'
    if (opt.catering_choices != null)        return 'choice'
    return 'none'
}

function setCateringMode(etConfig, etIdx, aIdx, mode) {
    ensureAudienceOptions(etConfig)
    const opts = audienceOptionsFor(etConfig)
    const opt  = opts[aIdx]
    if (!opt) return

    // Reset beide velden, dan zetten we het juiste
    delete opt.auto_catering_option_id
    delete opt.catering_choices

    if (mode === 'auto')   opt.auto_catering_option_id = null
    if (mode === 'choice') opt.catering_choices = [null]

    syncAudienceOptions(etConfig)
}

function setAutoCatering(etConfig, etIdx, aIdx, value) {
    ensureAudienceOptions(etConfig)
    const opts = audienceOptionsFor(etConfig)
    const opt  = opts[aIdx]
    if (!opt) return
    opt.auto_catering_option_id = value ? parseInt(value) : null
    syncAudienceOptions(etConfig)
}

function toggleCateringChoice(etConfig, etIdx, aIdx, cateringId, checked) {
    ensureAudienceOptions(etConfig)
    const opts = audienceOptionsFor(etConfig)
    const opt  = opts[aIdx]
    if (!opt) return
    if (!Array.isArray(opt.catering_choices)) opt.catering_choices = []
    if (checked) {
        if (!opt.catering_choices.includes(cateringId)) opt.catering_choices.push(cateringId)
    } else {
        opt.catering_choices = opt.catering_choices.filter((id) => id !== cateringId)
    }
    syncAudienceOptions(etConfig)
}

/**
 * Zorgt dat audience_options als array bestaat op etConfig.
 */
function ensureAudienceOptions(etConfig) {
    if (!Array.isArray(etConfig.audience_options)) {
        etConfig.audience_options = []
    }
    // Zorg dat de juiste audience-objecten aanwezig zijn
    const audiences = etConfig.audience_mode === 'children_adults'
        ? [{ audience: 'children', label: 'Kinderen / jongeren' }, { audience: 'adults', label: 'Volwassenen' }]
        : etConfig.audience_mode === 'adults_only'
            ? [{ audience: 'default', label: 'Volwassenen' }]
            : []

    for (const a of audiences) {
        if (!etConfig.audience_options.find((o) => o.audience === a.audience)) {
            etConfig.audience_options.push({ ...a })
        }
    }
}

/**
 * Synct de lokale audienceOptionsFor array terug naar etConfig.audience_options.
 * (audienceOptionsFor werkt op de bestaande array, dus dit is al live — maar
 * we moeten wél verouderde audience-entries opruimen bij mode-wissels.)
 */
function syncAudienceOptions(etConfig) {
    const validAudiences = audienceOptionsFor(etConfig).map((o) => o.audience)
    etConfig.audience_options = (etConfig.audience_options || [])
        .filter((o) => validAudiences.includes(o.audience))
}

// ──────────────────────────────────────────────────────────────────────────────
// Data laden & opslaan
// ──────────────────────────────────────────────────────────────────────────────

async function load() {
    loading.value = true
    error.value   = ''
    try {
        const data = await fetchBookingFormConfig()

        Object.assign(form.config, data.config)

        rawEventTypes.value  = data.event_types
        rawStayOptions.value = data.stay_options
        cateringOptions.value = data.catering_options

        form.event_type_configs  = data.event_types.map((et) => ({
            event_type_id:   et.event_type_id,
            show_in_form:    et.show_in_form,
            audience_mode:   et.audience_mode,
            audience_options: et.audience_options ?? [],
        }))

        form.stay_option_configs = data.stay_options.map((so) => ({
            stay_option_id:                  so.stay_option_id,
            show_in_form:                    so.show_in_form,
            min_revenue_outside_hours_cents: so.min_revenue_outside_hours_cents ?? null,
        }))
    } catch (err) {
        error.value = err?.data?.message ?? 'Kon configuratie niet laden.'
        console.error(err)
    } finally {
        loading.value = false
    }
}

async function save() {
    saving.value  = true
    error.value   = ''
    success.value = ''
    try {
        await saveBookingFormConfig({
            config:              form.config,
            event_type_configs:  form.event_type_configs,
            stay_option_configs: form.stay_option_configs,
        })
        success.value = 'Configuratie opgeslagen.'
        setTimeout(() => { success.value = '' }, 3000)
    } catch (err) {
        error.value = err?.data?.message ?? 'Kon configuratie niet opslaan.'
        console.error(err)
    } finally {
        saving.value = false
    }
}

onMounted(load)
</script>
