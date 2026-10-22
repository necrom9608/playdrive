<template>
    <div v-if="loading" class="rounded-3xl border border-slate-800 bg-slate-900/90 p-10 text-center text-sm text-slate-400">Laden…</div>

    <form v-else class="flex flex-col gap-4" @submit.prevent="save">
        <div v-if="!settings.api_configured" class="rounded-3xl border border-amber-500/30 bg-amber-500/10 px-5 py-4 text-sm text-amber-100">
            <span class="font-semibold text-amber-200">Claude API-sleutel ontbreekt.</span>
            Zet <code class="rounded bg-black/30 px-1.5 py-0.5">ANTHROPIC_API_KEY</code> in de .env van de server; zonder sleutel worden mails wel opgehaald maar geen concepten opgesteld.
        </div>

        <div v-if="error" class="rounded-2xl border border-rose-500/30 bg-rose-500/10 px-5 py-3 text-sm text-rose-200">{{ error }}</div>
        <div v-if="notice" class="rounded-2xl border border-emerald-500/30 bg-emerald-500/10 px-5 py-3 text-sm text-emerald-200">{{ notice }}</div>

        <!-- Aan/uit -->
        <section class="flex flex-wrap items-center justify-between gap-4 rounded-3xl border border-slate-800 bg-slate-900/90 p-5 shadow-xl">
            <div>
                <h2 class="text-xl font-semibold text-white">Mailassistent</h2>
                <p class="mt-1 text-sm text-slate-400">
                    Haalt elke 2 minuten nieuwe mails op en stelt een concept op. Versturen gebeurt altijd door jou.
                </p>
                <p v-if="settings.last_checked_at" class="mt-1 text-xs text-slate-400">
                    Laatst gecontroleerd {{ formatDateTime(settings.last_checked_at) }}
                    <span v-if="settings.last_error" class="text-amber-300"> · fout: {{ settings.last_error }}</span>
                </p>
            </div>
            <label class="flex items-center gap-3 text-sm font-semibold text-slate-200">
                {{ form.enabled ? 'Aan' : 'Uit' }}
                <ToggleSwitch v-model="form.enabled" />
            </label>
        </section>

        <div class="grid gap-4 xl:grid-cols-2">
            <!-- IMAP -->
            <section class="flex flex-col gap-4 rounded-3xl border border-slate-800 bg-slate-900/90 p-5">
                <div>
                    <h2 class="text-xl font-semibold text-white">Mailbox (ontvangen)</h2>
                    <p class="mt-1 text-sm text-slate-400">IMAP-gegevens van het adres waarop klanten mailen.</p>
                </div>
                <div class="grid gap-3 sm:grid-cols-[1fr_110px_130px]">
                    <Field label="IMAP-server" v-model="form.imap_host" placeholder="mail.voorbeeld.be" />
                    <Field label="Poort" v-model.number="form.imap_port" type="number" />
                    <label class="flex flex-col gap-1.5 text-sm text-slate-300">
                        Beveiliging
                        <select v-model="form.imap_encryption" class="field">
                            <option value="ssl">SSL (993)</option>
                            <option value="tls">STARTTLS (143)</option>
                            <option value="none">Geen</option>
                        </select>
                    </label>
                </div>
                <div class="grid gap-3 sm:grid-cols-2">
                    <Field label="Gebruikersnaam" v-model="form.imap_username" placeholder="info@voorbeeld.be" autocomplete="off" />
                    <Field
                        label="Wachtwoord"
                        v-model="form.imap_password"
                        type="password"
                        autocomplete="new-password"
                        :placeholder="settings.imap_password_set ? '•••••••• (ongewijzigd)' : ''"
                    />
                </div>
                <div class="grid gap-3 sm:grid-cols-2">
                    <Field label="Map" v-model="form.imap_folder" placeholder="INBOX" />
                    <Field label="Map voor verzonden mails" v-model="form.sent_folder" placeholder="leeg = automatisch" />
                </div>
                <label class="flex flex-col gap-1.5 text-sm text-slate-300">
                    Bij de eerste keer mails ophalen van de laatste
                    <div class="flex items-center gap-2">
                        <input v-model.number="form.fetch_since_days" type="number" min="0" max="30" class="field w-24">
                        <span class="text-slate-400">dagen</span>
                    </div>
                </label>
            </section>

            <!-- SMTP -->
            <section class="flex flex-col gap-4 rounded-3xl border border-slate-800 bg-slate-900/90 p-5">
                <div>
                    <h2 class="text-xl font-semibold text-white">Versturen (SMTP)</h2>
                    <p class="mt-1 text-sm text-slate-400">Antwoorden vertrekken vanaf het adres van de zaak en komen in de map Verzonden.</p>
                </div>
                <div class="grid gap-3 sm:grid-cols-[1fr_110px]">
                    <Field label="SMTP-server" v-model="form.smtp_host" placeholder="mail.voorbeeld.be" />
                    <Field label="Poort" v-model.number="form.smtp_port" type="number" />
                </div>
                <div class="grid gap-3 sm:grid-cols-2">
                    <Field label="Gebruikersnaam" v-model="form.smtp_username" placeholder="leeg = zelfde als IMAP" autocomplete="off" />
                    <Field
                        label="Wachtwoord"
                        v-model="form.smtp_password"
                        type="password"
                        autocomplete="new-password"
                        :placeholder="settings.smtp_password_set ? '•••••••• (ongewijzigd)' : 'leeg = zelfde als IMAP'"
                    />
                </div>
                <div class="grid gap-3 sm:grid-cols-2">
                    <Field label="Afzenderadres" v-model="form.from_address" placeholder="leeg = IMAP-gebruikersnaam" />
                    <Field label="Afzendernaam" v-model="form.from_name" placeholder="leeg = naam van de zaak" />
                </div>

                <div class="mt-auto flex flex-wrap items-center gap-2">
                    <button
                        type="button"
                        class="rounded-2xl border border-slate-700 bg-slate-950/55 px-4 py-3 text-sm font-semibold text-slate-200 transition hover:bg-slate-800 disabled:opacity-50"
                        :disabled="testing"
                        @click="test"
                    >
                        {{ testing ? 'Testen…' : 'Opslaan & test verbinding' }}
                    </button>
                </div>
                <div v-if="testResult" class="space-y-1.5 text-sm">
                    <p :class="testResult.imap.ok ? 'text-emerald-300' : 'text-rose-300'">IMAP: {{ testResult.imap.message }}</p>
                    <p :class="testResult.smtp.ok ? 'text-emerald-300' : 'text-rose-300'">SMTP: {{ testResult.smtp.message }}</p>
                </div>
            </section>

            <!-- Antwoorden -->
            <section class="flex flex-col gap-4 rounded-3xl border border-slate-800 bg-slate-900/90 p-5">
                <h2 class="text-xl font-semibold text-white">Toon &amp; handtekening</h2>
                <div class="grid gap-3 sm:grid-cols-2">
                    <label class="flex flex-col gap-1.5 text-sm text-slate-300">
                        Aanspreking
                        <select v-model="form.tone" class="field">
                            <option value="informal">Je / jij (informeel)</option>
                            <option value="formal">U (formeel)</option>
                            <option value="follow">Volgt de klant</option>
                        </select>
                    </label>
                    <label class="flex flex-col gap-1.5 text-sm text-slate-300">
                        Taal
                        <select v-model="form.language" class="field">
                            <option value="customer">Taal van de klant</option>
                            <option value="nl">Altijd Nederlands</option>
                        </select>
                    </label>
                </div>
                <label class="flex flex-col gap-1.5 text-sm text-slate-300">
                    Handtekening
                    <textarea v-model="form.signature" rows="3" class="field resize-y py-3" placeholder="Het team van …"></textarea>
                </label>
                <label class="flex flex-col gap-1.5 text-sm text-slate-300">
                    Extra richtlijnen voor de assistent
                    <textarea
                        v-model="form.instructions"
                        rows="4"
                        class="field resize-y py-3"
                        placeholder="Bv. verwijs voor groepen vanaf 20 personen naar het reservatieformulier op de website."
                    ></textarea>
                </label>
            </section>

            <!-- Bronnen -->
            <section class="flex flex-col gap-3 rounded-3xl border border-slate-800 bg-slate-900/90 p-5">
                <div>
                    <h2 class="text-xl font-semibold text-white">Bronnen</h2>
                    <p class="mt-1 text-sm text-slate-400">Welke Playdrive-gegevens de assistent mag gebruiken. De kennisbank wordt altijd gebruikt.</p>
                </div>
                <div
                    v-for="source in sourceOptions"
                    :key="source.key"
                    class="flex items-center justify-between gap-3 rounded-2xl border border-slate-800 bg-slate-950/70 px-4 py-3"
                >
                    <div>
                        <p class="font-semibold text-slate-200">{{ source.label }}</p>
                        <p class="text-xs text-slate-400">{{ source.hint }}</p>
                    </div>
                    <ToggleSwitch v-model="form.sources[source.key]" :aria-label="source.label" />
                </div>
                <div class="flex items-center justify-between gap-3 rounded-2xl border border-slate-800 bg-slate-950/40 px-4 py-3 opacity-70">
                    <div>
                        <p class="font-semibold text-slate-300">Reservaties</p>
                        <p class="text-xs text-slate-400">Module nog niet actief — de assistent belooft daarom nooit beschikbaarheid</p>
                    </div>
                    <ToggleSwitch :model-value="false" disabled aria-label="Reservaties" />
                </div>
            </section>
        </div>

        <!-- Categorieën -->
        <section class="rounded-3xl border border-slate-800 bg-slate-900/90 p-5">
            <div class="mb-4 flex flex-wrap items-end justify-between gap-3">
                <div>
                    <h2 class="text-xl font-semibold text-white">Categorieën</h2>
                    <p class="mt-1 text-sm text-slate-400">
                        Elke mail krijgt een categorie. Voorlopig is alles een concept; automatisch versturen per categorie komt in een volgende versie.
                    </p>
                </div>
                <label class="flex items-center gap-2 text-sm text-slate-300">
                    Voorstel "automatisch" na
                    <input v-model.number="form.auto_suggest_after" type="number" min="1" max="50" class="field w-20">
                    goedkeuringen zonder aanpassing
                </label>
            </div>
            <div class="flex flex-col gap-2">
                <article
                    v-for="c in settings.categories"
                    :key="c.key"
                    class="flex flex-wrap items-center gap-x-5 gap-y-2 rounded-[20px] border border-slate-800 bg-slate-950/70 px-4 py-3"
                >
                    <div class="min-w-[200px] flex-1">
                        <p class="font-semibold text-white">{{ c.label }}</p>
                        <p class="text-xs text-slate-400">{{ c.hint }}</p>
                    </div>
                    <span
                        v-if="c.locked"
                        class="inline-flex items-center gap-1.5 rounded-full bg-rose-500/15 px-3 py-1.5 text-xs font-semibold text-rose-300"
                    >
                        <LockClosedIcon class="h-3.5 w-3.5" /> Altijd manueel
                    </span>
                    <span v-else class="rounded-full bg-blue-500/15 px-3 py-1.5 text-xs font-semibold text-blue-300">Concept</span>
                    <span v-if="!c.locked" class="w-44 text-sm text-slate-300">
                        {{ Math.min(c.progress, form.auto_suggest_after) }} / {{ form.auto_suggest_after }} zonder aanpassing
                    </span>
                    <span v-else class="w-44 text-sm text-slate-400">—</span>
                    <span class="w-24 text-right">
                        <span class="text-lg font-semibold text-white">{{ c.count_30d }}</span>
                        <span class="text-xs text-slate-400"> / 30 d</span>
                    </span>
                </article>
            </div>
        </section>

        <div class="sticky bottom-0 -mx-1 flex justify-end gap-2 bg-gradient-to-t from-slate-950 via-slate-950/90 to-transparent px-1 pb-1 pt-6">
            <button
                type="submit"
                class="rounded-2xl bg-[linear-gradient(180deg,#3483ff_0%,#255df4_100%)] px-6 py-3 text-sm font-semibold text-white shadow-[0_14px_28px_rgba(37,93,244,0.28)] transition hover:brightness-110 disabled:opacity-50"
                :disabled="saving"
            >
                {{ saving ? 'Opslaan…' : 'Instellingen opslaan' }}
            </button>
        </div>
    </form>
</template>

<script setup>
import { defineComponent, h, onMounted, reactive, ref } from 'vue'
import { LockClosedIcon } from '@heroicons/vue/24/outline'
import ToggleSwitch from '../../../components/ToggleSwitch.vue'
import { errorMessage, fetchSettings, formatDateTime, testConnection, updateSettings } from '../services/mailAssistantApi'

// Klein invoerveld in dezelfde stijl als de login (frontdesk)
const Field = defineComponent({
    props: {
        label: { type: String, required: true },
        modelValue: { type: [String, Number], default: '' },
        type: { type: String, default: 'text' },
        placeholder: { type: String, default: '' },
        autocomplete: { type: String, default: undefined },
    },
    emits: ['update:modelValue'],
    setup(props, { emit }) {
        return () => h('label', { class: 'flex flex-col gap-1.5 text-sm text-slate-300' }, [
            props.label,
            h('input', {
                class: 'field',
                type: props.type,
                value: props.modelValue ?? '',
                placeholder: props.placeholder,
                autocomplete: props.autocomplete,
                onInput: (e) => emit('update:modelValue', props.type === 'number' ? Number(e.target.value) : e.target.value),
            }),
        ])
    },
})

const sourceOptions = [
    { key: 'opening_hours', label: 'Openingsuren & uitzonderingen', hint: 'Uren per dag voor de komende weken, incl. vakanties' },
    { key: 'products', label: 'Productbeheer & prijzen', hint: 'Actieve producten met prijs incl. btw' },
    { key: 'options', label: 'Event types, verblijfs- & cateringopties', hint: 'Namen van de formules' },
    { key: 'vouchers', label: 'Vouchers', hint: 'Saldo en geldigheid, enkel als de code in de mail staat' },
    { key: 'accounts', label: 'Accounts & lidmaatschap', hint: 'Naam en lidmaatschap van de afzender' },
    { key: 'history', label: 'Eerdere mails', hint: 'Vorige vragen en antwoorden van dezelfde afzender' },
]

const loading = ref(true)
const saving = ref(false)
const testing = ref(false)
const error = ref('')
const notice = ref('')
const testResult = ref(null)
const settings = ref({})

const form = reactive({
    enabled: false,
    imap_host: '',
    imap_port: 993,
    imap_encryption: 'ssl',
    imap_username: '',
    imap_password: '',
    imap_folder: 'INBOX',
    sent_folder: '',
    smtp_host: '',
    smtp_port: 587,
    smtp_username: '',
    smtp_password: '',
    from_address: '',
    from_name: '',
    tone: 'informal',
    language: 'customer',
    signature: '',
    instructions: '',
    sources: {},
    auto_suggest_after: 5,
    fetch_since_days: 3,
})

function applySettings(data) {
    settings.value = data

    for (const key of Object.keys(form)) {
        if (key === 'imap_password' || key === 'smtp_password') {
            form[key] = ''
        } else if (key === 'sources') {
            form.sources = { ...(data.sources ?? {}) }
        } else if (data[key] !== undefined && data[key] !== null) {
            form[key] = data[key]
        } else if (typeof form[key] === 'string') {
            form[key] = ''
        }
    }
}

async function load() {
    loading.value = true
    try {
        applySettings(await fetchSettings())
    } catch (e) {
        error.value = errorMessage(e, 'Instellingen laden mislukt.')
    } finally {
        loading.value = false
    }
}

async function save() {
    saving.value = true
    error.value = ''
    notice.value = ''

    try {
        const res = await updateSettings({ ...form })
        applySettings(res.data)
        notice.value = res.message
    } catch (e) {
        error.value = errorMessage(e, 'Opslaan mislukt.')
    } finally {
        saving.value = false
    }
}

async function test() {
    testing.value = true
    testResult.value = null

    // Eerst opslaan: de test gebruikt wat op de server staat
    await save()
    if (error.value) {
        testing.value = false
        return
    }

    try {
        testResult.value = await testConnection()
    } catch (e) {
        error.value = errorMessage(e, 'Testen mislukt.')
    } finally {
        testing.value = false
    }
}

onMounted(load)
</script>

<style scoped>
:deep(.field) {
    width: 100%;
    border-radius: 1rem;
    border: 1px solid rgba(255, 255, 255, 0.1);
    background: rgba(2, 6, 23, 0.55);
    padding: 0.75rem 1rem;
    color: #fff;
    outline: none;
    transition: border-color 0.2s ease, background-color 0.2s ease;
}

:deep(.field::placeholder) {
    color: rgba(148, 163, 184, 0.7);
}

:deep(.field:focus) {
    border-color: rgba(96, 165, 250, 0.65);
    background: rgba(2, 6, 23, 0.72);
}

:deep(select.field) {
    appearance: auto;
}
</style>
