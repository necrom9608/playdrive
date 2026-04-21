<template>
    <div :class="isEmbed ? 'embed-mode' : 'website-bg min-h-screen relative overflow-hidden'">
        <template v-if="!isEmbed">
            <div class="glow-orb-blue absolute w-96 h-96 -left-20 -top-16" />
            <div class="glow-orb-purple absolute w-80 h-80 -right-16 -bottom-10" />
        </template>

        <div :class="isEmbed ? 'embed-inner' : 'relative min-h-screen flex flex-col items-center justify-center px-4 py-12'">

            <!-- Laden -->
            <div v-if="loading" class="text-center">
                <div class="flex items-center justify-center gap-2 mb-3">
                    <span class="loader-dot" />
                    <span class="loader-dot" />
                    <span class="loader-dot" />
                </div>
                <p class="text-sm" style="color: var(--text-soft);">Formulier laden...</p>
            </div>

            <!-- Fout bij laden -->
            <div v-else-if="loadError" class="website-card website-card-shine w-full max-w-lg rounded-3xl px-8 py-10 text-center animate-fade-up">
                <p class="text-lg font-semibold mb-2" style="color: var(--text-main);">Kon het formulier niet laden</p>
                <p class="text-sm" style="color: var(--text-soft);">{{ loadError }}</p>
            </div>

            <!-- Succes -->
            <div v-else-if="submitted" class="website-card website-card-shine w-full max-w-lg rounded-3xl px-8 py-12 text-center animate-fade-up">
                <div class="w-16 h-16 rounded-full flex items-center justify-center mx-auto mb-6"
                    style="background: rgba(34,197,94,0.10); border: 1px solid rgba(34,197,94,0.22);">
                    <svg class="w-8 h-8" style="color: #4ade80;" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                        <polyline points="20 6 9 17 4 12"/>
                    </svg>
                </div>
                <h2 class="text-xl font-bold mb-3" style="color: var(--text-main);">Reservatie ontvangen!</h2>
                <p class="text-sm leading-relaxed" style="color: var(--text-soft);">
                    Bedankt {{ form.name }}. We hebben je aanvraag goed ontvangen en nemen
                    zo snel mogelijk contact met je op via {{ form.email }}.
                </p>
                <PoweredBy v-if="isEmbed" class="mt-8" />
            </div>

            <!-- Formulier -->
            <div v-else class="website-card website-card-shine relative w-full max-w-2xl rounded-3xl overflow-hidden animate-fade-up">

                <!-- Voortgangsbalk -->
                <div class="h-1 w-full" style="background: rgba(75,98,148,0.2);">
                    <div
                        class="h-full transition-all duration-500"
                        style="background: linear-gradient(90deg, #3b82f6, #6366f1);"
                        :style="{ width: `${(currentStep / totalSteps) * 100}%` }"
                    />
                </div>

                <div class="px-8 py-8">

                    <!-- Stap indicator -->
                    <div class="flex items-center justify-between mb-6">
                        <p class="text-xs uppercase tracking-widest" style="color: var(--text-soft); opacity: 0.65;">
                            Stap {{ currentStep }} van {{ totalSteps }}
                        </p>
                        <p class="text-xs font-medium" style="color: var(--text-soft);">{{ stepTitle }}</p>
                    </div>

                    <!-- ── STAP 1: Event type & formule ── -->
                    <div v-if="currentStep === 1" class="space-y-6 animate-fade-up">
                        <h2 class="text-xl font-bold" style="color: var(--text-main);">Wat voor event organiseer je?</h2>

                        <!-- Event types -->
                        <div>
                            <label class="block text-xs uppercase tracking-wider mb-3" style="color: var(--text-soft); opacity: 0.8;">Type event</label>
                            <div class="flex flex-wrap gap-2">
                                <button
                                    v-for="et in visibleEventTypes"
                                    :key="et.event_type_id"
                                    type="button"
                                    class="booking-tab"
                                    :class="{ 'booking-tab-active': form.event_type_id === et.event_type_id }"
                                    @click="selectEventType(et)"
                                >
                                    <span v-if="et.emoji" class="mr-1.5">{{ et.emoji }}</span>{{ et.name }}
                                </button>
                            </div>
                        </div>

                        <!-- Doelgroep (conditioneel) -->
                        <div v-if="selectedEventType && selectedEventType.audience_mode !== 'none'" class="space-y-3">
                            <label class="block text-xs uppercase tracking-wider mb-3" style="color: var(--text-soft); opacity: 0.8;">Voor wie is het event?</label>
                            <div class="grid grid-cols-2 gap-3">
                                <button
                                    v-for="opt in audienceOptions"
                                    :key="opt.audience"
                                    type="button"
                                    class="booking-choice-card"
                                    :class="{ 'booking-choice-card-active': form.audience === opt.audience }"
                                    @click="selectAudience(opt)"
                                >
                                    <span class="text-lg mb-1">{{ opt.audience === 'children' ? '🧒' : '🧑' }}</span>
                                    <span class="text-sm font-medium">{{ opt.label }}</span>
                                </button>
                            </div>
                        </div>

                        <!-- Catering keuze (conditioneel) -->
                        <div v-if="showCateringChoice" class="space-y-3">
                            <label class="block text-xs uppercase tracking-wider mb-3" style="color: var(--text-soft); opacity: 0.8;">Catering</label>
                            <div class="space-y-2">
                                <button
                                    v-for="opt in cateringChoiceOptions"
                                    :key="opt.id ?? 'none'"
                                    type="button"
                                    class="booking-choice-row"
                                    :class="{ 'booking-choice-row-active': form.catering_option_id === opt.id }"
                                    @click="form.catering_option_id = opt.id"
                                >
                                    <span class="text-base mr-2">{{ opt.emoji ?? '🍽️' }}</span>
                                    <span class="text-sm font-medium">{{ opt.name }}</span>
                                    <span v-if="form.catering_option_id === opt.id" class="ml-auto">
                                        <svg class="w-4 h-4" style="color: #60a5fa;" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                                            <polyline points="20 6 9 17 4 12"/>
                                        </svg>
                                    </span>
                                </button>
                            </div>
                        </div>

                        <!-- Auto catering melding -->
                        <div v-if="autoCateringName" class="booking-info-box">
                            <span class="text-base mr-2">🍽️</span>
                            <span class="text-sm" style="color: var(--text-soft);">
                                <strong style="color: var(--text-main);">{{ autoCateringName }}</strong> wordt automatisch toegevoegd bij deze keuze.
                            </span>
                        </div>
                    </div>

                    <!-- ── STAP 2: Datum, tijd & personen ── -->
                    <div v-if="currentStep === 2" class="space-y-6 animate-fade-up">
                        <h2 class="text-xl font-bold" style="color: var(--text-main);">Wanneer kom je langs?</h2>

                        <!-- Kalender -->
                        <div>
                            <label class="block text-xs uppercase tracking-wider mb-3" style="color: var(--text-soft); opacity: 0.8;">Kies een datum</label>
                            <CalendarPicker
                                v-model="form.event_date"
                                :opening-hours="setup.opening_hours ?? []"
                                :seasons="setup.seasons ?? []"
                                :exceptions="setup.exceptions ?? []"
                                @select="onDaySelected"
                            />
                        </div>

                        <!-- Openingsuren van de gekozen dag -->
                        <div v-if="form.event_date">
                            <div v-if="selectedDayInfo.isOpen" class="booking-info-box">
                                <span class="text-base mr-2">🕐</span>
                                <span class="text-sm" style="color: var(--text-soft);">
                                    Open van <strong style="color: var(--text-main);">{{ selectedDayInfo.openFrom }}</strong>
                                    tot <strong style="color: var(--text-main);">{{ selectedDayInfo.openUntil }}</strong>
                                </span>
                            </div>
                            <div v-else class="booking-warning-box">
                                <svg class="w-5 h-5 shrink-0 mt-0.5" style="color: #fbbf24;" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                    <path d="M10.29 3.86 1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/>
                                </svg>
                                <div class="text-sm" style="color: var(--text-soft);">
                                    <strong style="color: #fbbf24;">We zijn normaal gesloten op deze dag.</strong>
                                    <span v-if="outsideHoursMinRevenue"> Voor een privéreservatie geldt een minimumomzet van <strong style="color: var(--text-main);">€{{ outsideHoursMinRevenue }}</strong>.</span>
                                    We nemen contact met je op om dit te bevestigen.
                                </div>
                            </div>
                        </div>

                        <!-- Stay option -->
                        <div>
                            <label class="block text-xs uppercase tracking-wider mb-3" style="color: var(--text-soft); opacity: 0.8;">Hoe lang?</label>
                            <div class="flex flex-wrap gap-2">
                                <button
                                    v-for="so in visibleStayOptions"
                                    :key="so.stay_option_id"
                                    type="button"
                                    class="booking-tab"
                                    :class="{ 'booking-tab-active': form.stay_option_id === so.stay_option_id }"
                                    @click="form.stay_option_id = so.stay_option_id"
                                >
                                    {{ so.name }}
                                </button>
                            </div>
                        </div>

                        <!-- Startuur -->
                        <div>
                            <label class="block text-xs uppercase tracking-wider mb-2" style="color: var(--text-soft); opacity: 0.8;">Startuur</label>
                            <select
                                v-model="form.event_time"
                                class="website-input"
                                :disabled="!form.event_date"
                            >
                                <option value="" disabled>
                                    {{ form.event_date ? 'Kies een startuur' : 'Kies eerst een datum' }}
                                </option>
                                <option v-for="slot in timeSlots" :key="slot" :value="slot">
                                    {{ slot }}
                                </option>
                            </select>
                        </div>

                        <!-- Personen -->
                        <div class="space-y-3">
                            <label class="block text-xs uppercase tracking-wider mb-1" style="color: var(--text-soft); opacity: 0.8;">Aantal personen</label>

                            <div v-if="setup.config.show_participant_children" class="flex items-center justify-between">
                                <span class="text-sm" style="color: var(--text-soft);">Kinderen</span>
                                <CounterInput v-model="form.participants_children" />
                            </div>
                            <div v-if="setup.config.show_participant_adults" class="flex items-center justify-between">
                                <span class="text-sm" style="color: var(--text-soft);">Volwassenen</span>
                                <CounterInput v-model="form.participants_adults" />
                            </div>
                            <div v-if="setup.config.show_participant_supervisors" class="flex items-center justify-between">
                                <span class="text-sm" style="color: var(--text-soft);">Begeleiders</span>
                                <CounterInput v-model="form.participants_supervisors" />
                            </div>

                            <p v-if="participantError" class="text-xs mt-1" style="color: #fca5a5;">{{ participantError }}</p>
                        </div>
                    </div>

                    <!-- ── STAP 3: Contactgegevens ── -->
                    <div v-if="currentStep === 3" class="space-y-4 animate-fade-up">
                        <h2 class="text-xl font-bold" style="color: var(--text-main);">Jouw gegevens</h2>

                        <div>
                            <label class="block text-xs uppercase tracking-wider mb-1.5" style="color: var(--text-soft); opacity: 0.8;">Naam *</label>
                            <input v-model="form.name" type="text" class="website-input" placeholder="Voor- en familienaam" autocomplete="name" />
                        </div>
                        <div class="grid grid-cols-2 gap-3">
                            <div>
                                <label class="block text-xs uppercase tracking-wider mb-1.5" style="color: var(--text-soft); opacity: 0.8;">E-mail *</label>
                                <input v-model="form.email" type="email" class="website-input" placeholder="jouw@email.be" autocomplete="email" />
                            </div>
                            <div>
                                <label class="block text-xs uppercase tracking-wider mb-1.5" style="color: var(--text-soft); opacity: 0.8;">Telefoon</label>
                                <input v-model="form.phone" type="tel" class="website-input" placeholder="+32 ..." autocomplete="tel" />
                            </div>
                        </div>
                        <div class="grid grid-cols-2 gap-3">
                            <div>
                                <label class="block text-xs uppercase tracking-wider mb-1.5" style="color: var(--text-soft); opacity: 0.8;">Postcode</label>
                                <input v-model="form.postal_code" type="text" class="website-input" placeholder="8000" autocomplete="postal-code" />
                            </div>
                            <div>
                                <label class="block text-xs uppercase tracking-wider mb-1.5" style="color: var(--text-soft); opacity: 0.8;">Gemeente</label>
                                <input v-model="form.municipality" type="text" class="website-input" placeholder="Brugge" autocomplete="address-level2" />
                            </div>
                        </div>
                        <div>
                            <label class="block text-xs uppercase tracking-wider mb-1.5" style="color: var(--text-soft); opacity: 0.8;">Opmerkingen</label>
                            <textarea v-model="form.comment" class="website-input resize-none" rows="3" placeholder="Eventuele vragen of opmerkingen..." />
                        </div>
                    </div>

                    <!-- ── STAP 4: Factuur ── -->
                    <div v-if="currentStep === 4" class="space-y-4 animate-fade-up">
                        <h2 class="text-xl font-bold" style="color: var(--text-main);">Factuur gewenst?</h2>

                        <div class="flex gap-3">
                            <button
                                type="button"
                                class="booking-choice-card flex-1"
                                :class="{ 'booking-choice-card-active': form.invoice_requested === false }"
                                @click="form.invoice_requested = false"
                            >
                                <span class="text-lg mb-1">🧾</span>
                                <span class="text-sm font-medium">Geen factuur</span>
                            </button>
                            <button
                                type="button"
                                class="booking-choice-card flex-1"
                                :class="{ 'booking-choice-card-active': form.invoice_requested === true }"
                                @click="form.invoice_requested = true"
                            >
                                <span class="text-lg mb-1">🏢</span>
                                <span class="text-sm font-medium">Ja, factuur</span>
                            </button>
                        </div>

                        <template v-if="form.invoice_requested">
                            <div class="h-px" style="background: rgba(75,98,148,0.2);" />
                            <div>
                                <label class="block text-xs uppercase tracking-wider mb-1.5" style="color: var(--text-soft); opacity: 0.8;">Bedrijfsnaam *</label>
                                <input v-model="form.invoice_company_name" type="text" class="website-input" placeholder="Bedrijf BV" />
                            </div>
                            <div class="grid grid-cols-2 gap-3">
                                <div>
                                    <label class="block text-xs uppercase tracking-wider mb-1.5" style="color: var(--text-soft); opacity: 0.8;">BTW-nummer</label>
                                    <input v-model="form.invoice_vat_number" type="text" class="website-input" placeholder="BE 0xxx.xxx.xxx" />
                                </div>
                                <div>
                                    <label class="block text-xs uppercase tracking-wider mb-1.5" style="color: var(--text-soft); opacity: 0.8;">Factuur e-mail</label>
                                    <input v-model="form.invoice_email" type="email" class="website-input" placeholder="factuur@bedrijf.be" />
                                </div>
                            </div>
                            <div>
                                <label class="block text-xs uppercase tracking-wider mb-1.5" style="color: var(--text-soft); opacity: 0.8;">Adres</label>
                                <input v-model="form.invoice_address" type="text" class="website-input" placeholder="Straat en nummer" />
                            </div>
                            <div class="grid grid-cols-2 gap-3">
                                <div>
                                    <label class="block text-xs uppercase tracking-wider mb-1.5" style="color: var(--text-soft); opacity: 0.8;">Postcode</label>
                                    <input v-model="form.invoice_postal_code" type="text" class="website-input" placeholder="8000" />
                                </div>
                                <div>
                                    <label class="block text-xs uppercase tracking-wider mb-1.5" style="color: var(--text-soft); opacity: 0.8;">Stad</label>
                                    <input v-model="form.invoice_city" type="text" class="website-input" placeholder="Brugge" />
                                </div>
                            </div>
                        </template>
                    </div>

                    <!-- ── STAP 5: Statistieken ── -->
                    <div v-if="currentStep === 5" class="space-y-5 animate-fade-up">
                        <h2 class="text-xl font-bold" style="color: var(--text-main);">Nog één vraagje</h2>
                        <p class="text-sm" style="color: var(--text-soft);">Hoe hebben jullie ons leren kennen? (meerdere antwoorden mogelijk)</p>

                        <div class="space-y-2">
                            <label v-for="opt in statsOptions" :key="opt.key" class="booking-choice-row cursor-pointer" :class="{ 'booking-choice-row-active': form.stats[opt.key] }">
                                <input type="checkbox" v-model="form.stats[opt.key]" class="sr-only" />
                                <span class="text-base mr-2">{{ opt.emoji }}</span>
                                <span class="text-sm font-medium" style="color: var(--text-main);">{{ opt.label }}</span>
                                <span v-if="form.stats[opt.key]" class="ml-auto">
                                    <svg class="w-4 h-4" style="color: #60a5fa;" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                                        <polyline points="20 6 9 17 4 12"/>
                                    </svg>
                                </span>
                            </label>
                        </div>
                    </div>

                    <!-- Validatiefout -->
                    <div v-if="stepError" class="mt-4 rounded-2xl px-4 py-3" style="background: rgba(239,68,68,0.10); border: 1px solid rgba(239,68,68,0.25);">
                        <p class="text-sm" style="color: #fca5a5;">{{ stepError }}</p>
                    </div>

                    <!-- Navigatieknoppen -->
                    <div class="flex items-center justify-between mt-8 pt-6" style="border-top: 1px solid rgba(75,98,148,0.20);">
                        <button
                            v-if="currentStep > 1"
                            type="button"
                            class="website-btn-ghost"
                            @click="prevStep"
                        >
                            ← Vorige
                        </button>
                        <div v-else />

                        <button
                            v-if="currentStep < totalSteps"
                            type="button"
                            class="website-btn-primary"
                            style="width: auto; min-width: 140px;"
                            :disabled="saving"
                            @click="nextStep"
                        >
                            Volgende →
                        </button>
                        <button
                            v-else
                            type="button"
                            class="website-btn-primary"
                            style="width: auto; min-width: 160px;"
                            :disabled="saving"
                            @click="submit"
                        >
                            <span v-if="!saving">Verzenden ✓</span>
                            <span v-else class="flex items-center gap-2">
                                <span class="loader-dot" />
                                <span class="loader-dot" />
                                <span class="loader-dot" />
                            </span>
                        </button>
                    </div>

                </div>
                <PoweredBy v-if="isEmbed" />
            </div>

        </div>
    </div>
</template>

<script setup>
import { computed, nextTick, onMounted, reactive, ref, watch } from 'vue'
import CalendarPicker from '../components/CalendarPicker.vue'
import CounterInput from '../components/CounterInput.vue'
import { fetchBookingFormSetup, submitReservation } from '../services/bookingFormApi'

// ──────────────────────────────────────────────────────────────────────────────
// Props — tenant slug wordt meegegeven via de route of een data-attribuut
// ──────────────────────────────────────────────────────────────────────────────
const props = defineProps({
    tenant: { type: String, default: () => document.querySelector('[data-tenant]')?.dataset.tenant ?? '' },
})

// ──────────────────────────────────────────────────────────────────────────────
// Embed modus
// ──────────────────────────────────────────────────────────────────────────────
const isEmbed = computed(() => new URLSearchParams(window.location.search).has('embed'))

// Stuur hoogte naar parent iframe
function postResize() {
    if (!isEmbed.value) return
    nextTick(() => {
        const h = document.documentElement.scrollHeight
        window.parent.postMessage({ type: 'playdrive:resize', height: h }, '*')
    })
}

// Stuur scroll-naar-top bij stapwisseling
function postScrollTop() {
    if (!isEmbed.value) return
    window.parent.postMessage({ type: 'playdrive:scroll-top' }, '*')
}

// PoweredBy inline component
const PoweredBy = {
    template: `
        <div style="padding:12px 32px 20px;text-align:center;border-top:1px solid rgba(75,98,148,0.15);margin-top:4px;">
            <a href="https://playdrive.be" target="_blank" rel="noopener"
               style="display:inline-flex;align-items:center;gap:6px;text-decoration:none;opacity:0.55;transition:opacity 0.15s;"
               onmouseover="this.style.opacity='0.85'" onmouseout="this.style.opacity='0.55'">
                <img src="/images/logos/icon.png" alt="PlayDrive" style="width:16px;height:16px;border-radius:3px;" />
                <span style="font-size:11px;color:var(--text-soft);font-family:inherit;letter-spacing:0.03em;">Powered by PlayDrive</span>
            </a>
        </div>
    `,
}

// ──────────────────────────────────────────────────────────────────────────────
// State
// ──────────────────────────────────────────────────────────────────────────────
const loading    = ref(true)
const loadError  = ref('')
const saving     = ref(false)
const submitted  = ref(false)
const stepError  = ref('')

const currentStep = ref(1)
const totalSteps  = 5

const setup = ref({
    config: {},
    event_types: [],
    stay_options: [],
    catering_options: [],
    opening_hours: [],
})

const form = reactive({
    event_type_id:          null,
    audience:               null,
    catering_option_id:     null,
    event_date:             '',
    event_time:             '',
    stay_option_id:         null,
    participants_children:  0,
    participants_adults:    0,
    participants_supervisors: 0,
    name:                   '',
    email:                  '',
    phone:                  '',
    postal_code:            '',
    municipality:           '',
    comment:                '',
    invoice_requested:      false,
    invoice_company_name:   '',
    invoice_vat_number:     '',
    invoice_email:          '',
    invoice_address:        '',
    invoice_postal_code:    '',
    invoice_city:           '',
    stats: {
        already_visited:      false,
        recommended_by_friend: false,
        internet:             false,
        social_media:         false,
        facade:               false,
        ai:                   false,
    },
})

// ──────────────────────────────────────────────────────────────────────────────
// Stap titels
// ──────────────────────────────────────────────────────────────────────────────
const stepTitles = [
    'Type & formule',
    'Datum & personen',
    'Contactgegevens',
    'Facturatie',
    'Hoe kende je ons?',
]
const stepTitle = computed(() => stepTitles[currentStep.value - 1] ?? '')

// ──────────────────────────────────────────────────────────────────────────────
// Afgeleide data
// ──────────────────────────────────────────────────────────────────────────────

const visibleEventTypes = computed(() =>
    (setup.value.event_types ?? []).filter((et) => et.show_in_form)
)

const visibleStayOptions = computed(() =>
    (setup.value.stay_options ?? []).filter((so) => so.show_in_form)
)

const selectedEventType = computed(() =>
    visibleEventTypes.value.find((et) => et.event_type_id === form.event_type_id) ?? null
)

const audienceOptions = computed(() => {
    const et = selectedEventType.value
    if (!et || et.audience_mode === 'none') return []
    return et.audience_options ?? []
})

const selectedAudienceOption = computed(() =>
    audienceOptions.value.find((o) => o.audience === form.audience) ?? null
)

const showCateringChoice = computed(() => {
    const opt = selectedAudienceOption.value
    if (!opt) return false
    return Array.isArray(opt.catering_choices)
})

const cateringChoiceOptions = computed(() => {
    const choices = selectedAudienceOption.value?.catering_choices ?? []
    return choices.map((id) => {
        if (id === null) return { id: null, name: 'Geen catering', emoji: '🚫' }
        return setup.value.catering_options.find((c) => c.id === id) ?? { id, name: `Optie ${id}` }
    })
})

const autoCateringName = computed(() => {
    const opt = selectedAudienceOption.value
    if (!opt?.auto_catering_option_id) return null
    return setup.value.catering_options.find((c) => c.id === opt.auto_catering_option_id)?.name ?? null
})

const selectedStayOption = computed(() =>
    visibleStayOptions.value.find((so) => so.stay_option_id === form.stay_option_id) ?? null
)

const selectedDayInfo = ref({ isOpen: true, openFrom: null, openUntil: null })
const participantError = ref('')

// Tijdslots per half uur, van openingsuur tot 30 min voor sluiting
const timeSlots = computed(() => {
    const from  = selectedDayInfo.value.openFrom
    const until = selectedDayInfo.value.openUntil
    if (!from || !until) return []

    const [hFrom, mFrom]   = from.split(':').map(Number)
    const [hUntil, mUntil] = until.split(':').map(Number)

    const startMin = hFrom * 60 + mFrom
    const endMin   = hUntil * 60 + mUntil - 30  // laatste slot = 30 min voor sluiting

    const slots = []
    for (let m = startMin; m <= endMin; m += 30) {
        const h   = Math.floor(m / 60)
        const min = m % 60
        slots.push(`${String(h).padStart(2, '0')}:${String(min).padStart(2, '0')}`)
    }
    return slots
})

const outsideHoursWarning = computed(() => !selectedDayInfo.value.isOpen)

const outsideHoursMinRevenue = computed(() => {
    if (!outsideHoursWarning.value || !selectedStayOption.value) return null
    const cents = selectedStayOption.value.min_revenue_outside_hours_cents
    if (!cents) return null
    return (cents / 100).toFixed(0)
})

function onDaySelected(info) {
    selectedDayInfo.value = info
    // Automatisch vroegste uur instellen als de dag open is
    if (info.isOpen && info.openFrom) {
        form.event_time = info.openFrom
    } else {
        form.event_time = ''
    }
}

const statsOptions = [
    { key: 'already_visited',       emoji: '🔄', label: 'Al eens geweest' },
    { key: 'recommended_by_friend', emoji: '👥', label: 'Aanbevolen door iemand' },
    { key: 'internet',              emoji: '🔍', label: 'Via internet gevonden' },
    { key: 'social_media',          emoji: '📱', label: 'Via sociale media' },
    { key: 'facade',                emoji: '🏠', label: 'Gevel / bord gezien' },
    { key: 'ai',                    emoji: '🤖', label: 'Via AI-assistent' },
]

// ──────────────────────────────────────────────────────────────────────────────
// Acties
// ──────────────────────────────────────────────────────────────────────────────

function selectEventType(et) {
    form.event_type_id      = et.event_type_id
    form.audience           = null
    form.catering_option_id = null

    // Als er geen doelgroepvraag is: direct auto-catering of reset
    if (et.audience_mode === 'none') {
        const def = (et.audience_options ?? []).find((o) => o.audience === 'default')
        if (def?.auto_catering_option_id) {
            form.catering_option_id = def.auto_catering_option_id
        }
    }
}

function selectAudience(opt) {
    form.audience           = opt.audience
    form.catering_option_id = null

    // Auto-catering instellen indien van toepassing
    if (opt.auto_catering_option_id) {
        form.catering_option_id = opt.auto_catering_option_id
    }
    // Bij keuze: standaard eerste optie selecteren
    if (Array.isArray(opt.catering_choices) && opt.catering_choices.length > 0) {
        form.catering_option_id = opt.catering_choices[0]
    }
}

function selectStayOption(so) {
    form.stay_option_id = so.stay_option_id
}

// ──────────────────────────────────────────────────────────────────────────────
// Stap validatie
// ──────────────────────────────────────────────────────────────────────────────

function validateStep(step) {
    stepError.value      = ''
    participantError.value = ''

    if (step === 1) {
        if (!form.event_type_id) {
            stepError.value = 'Kies een type event.'
            return false
        }
        const et = selectedEventType.value
        if (et?.audience_mode === 'children_adults' && !form.audience) {
            stepError.value = 'Kies voor wie het event is.'
            return false
        }
        if (showCateringChoice.value && form.catering_option_id === null) {
            stepError.value = 'Kies een cateringoptie.'
            return false
        }
    }

    if (step === 2) {
        if (!form.event_date) { stepError.value = 'Kies een datum.'; return false }
        if (!form.stay_option_id) { stepError.value = 'Kies een verblijfsduur.'; return false }
        if (!form.event_time) { stepError.value = 'Kies een startuur.'; return false }

        const total = form.participants_children + form.participants_adults + form.participants_supervisors
        if (total < 1) {
            participantError.value = 'Voeg minstens 1 deelnemer toe.'
            stepError.value = 'Voeg minstens 1 deelnemer toe.'
            return false
        }
    }

    if (step === 3) {
        if (!form.name.trim()) { stepError.value = 'Vul je naam in.'; return false }
        if (!form.email.trim()) { stepError.value = 'Vul je e-mailadres in.'; return false }
        if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(form.email)) {
            stepError.value = 'Vul een geldig e-mailadres in.'
            return false
        }
    }

    if (step === 4) {
        if (form.invoice_requested && !form.invoice_company_name.trim()) {
            stepError.value = 'Vul de bedrijfsnaam in.'
            return false
        }
    }

    return true
}

function nextStep() {
    if (!validateStep(currentStep.value)) return
    currentStep.value++
    if (isEmbed.value) {
        postScrollTop()
        postResize()
    } else {
        window.scrollTo({ top: 0, behavior: 'smooth' })
    }
}

function prevStep() {
    stepError.value = ''
    currentStep.value--
    if (isEmbed.value) {
        postScrollTop()
        postResize()
    } else {
        window.scrollTo({ top: 0, behavior: 'smooth' })
    }
}

// ──────────────────────────────────────────────────────────────────────────────
// Verzenden
// ──────────────────────────────────────────────────────────────────────────────

async function submit() {
    if (!validateStep(currentStep.value)) return

    saving.value    = true
    stepError.value = ''

    try {
        await submitReservation({
            tenant:                  props.tenant,
            name:                    form.name,
            email:                   form.email,
            phone:                   form.phone || null,
            postal_code:             form.postal_code || null,
            municipality:            form.municipality || null,
            event_type_id:           form.event_type_id,
            event_date:              form.event_date,
            event_time:              form.event_time,
            stay_option_id:          form.stay_option_id,
            catering_option_id:      form.catering_option_id,
            participants_children:   form.participants_children,
            participants_adults:     form.participants_adults,
            participants_supervisors: form.participants_supervisors,
            comment:                 form.comment || null,
            outside_opening_hours:   outsideHoursWarning.value,
            invoice_requested:       form.invoice_requested,
            invoice_company_name:    form.invoice_company_name || null,
            invoice_vat_number:      form.invoice_vat_number || null,
            invoice_email:           form.invoice_email || null,
            invoice_address:         form.invoice_address || null,
            invoice_postal_code:     form.invoice_postal_code || null,
            invoice_city:            form.invoice_city || null,
            stats:                   form.stats,
        })

        submitted.value = true
        if (isEmbed.value) {
            postScrollTop()
            postResize()
        } else {
            window.scrollTo({ top: 0, behavior: 'smooth' })
        }
    } catch (err) {
        if (err.data?.errors) {
            stepError.value = Object.values(err.data.errors).flat().join(' ')
        } else {
            stepError.value = err.message ?? 'Er ging iets mis. Probeer opnieuw.'
        }
    } finally {
        saving.value = false
    }
}

// ──────────────────────────────────────────────────────────────────────────────
// Laden
// ──────────────────────────────────────────────────────────────────────────────

// Resize sturen wanneer inhoud verandert in embed modus
watch([loading, currentStep, submitted], () => postResize())

onMounted(async () => {
    try {
        setup.value = await fetchBookingFormSetup(props.tenant)
    } catch (err) {
        loadError.value = err.message ?? 'Kon het formulier niet laden.'
    } finally {
        loading.value = false
        postResize()
    }
})
</script>
