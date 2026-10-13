<template>
    <section class="flex h-full min-h-0 flex-col gap-6 overflow-auto">
        <div class="rounded-3xl border border-slate-800 bg-slate-900/90 p-6 shadow-xl">
            <div class="flex flex-wrap items-start justify-between gap-4">
                <div>
                    <h1 class="text-2xl font-semibold text-white">Boekhouding</h1>
                    <p class="mt-1 text-sm text-slate-400">
                        Dagtotalen automatisch per mail naar de boekhouding: samenvatting in de mail, volledig overzicht als Excel-bijlage.
                        Manueel versturen kan via
                        <router-link to="/daytotals" class="text-violet-300 underline-offset-2 hover:underline">Dagtotalen</router-link>.
                    </p>
                </div>

                <div
                    v-if="next"
                    class="rounded-2xl border px-4 py-3 text-sm"
                    :class="settings.enabled ? 'border-emerald-500/30 bg-emerald-500/10 text-emerald-200' : 'border-slate-700 bg-slate-950 text-slate-400'"
                >
                    <p class="font-semibold">Volgende automatische verzending</p>
                    <p class="mt-1">
                        {{ next.period.label }} ·
                        <template v-if="next.overdue">bij de volgende dagelijkse run (07:00)</template>
                        <template v-else>{{ formatDate(next.send_date) }} om 07:00</template>
                    </p>
                </div>
                <div v-else class="rounded-2xl border border-slate-700 bg-slate-950 px-4 py-3 text-sm text-slate-400">
                    Automatisch versturen staat uit.
                </div>
            </div>
        </div>

        <div v-if="loading" class="rounded-3xl border border-slate-800 bg-slate-900/90 p-6 text-sm text-slate-400">Laden...</div>

        <template v-else>
            <div class="rounded-3xl border border-slate-800 bg-slate-900/90 p-6 shadow-xl">
                <div class="mb-6 flex items-center justify-between gap-4">
                    <div>
                        <h2 class="text-lg font-semibold text-white">Instellingen</h2>
                        <p class="text-sm text-slate-400">Gelden voor zowel automatische als manuele verzending.</p>
                    </div>

                    <label class="inline-flex items-center gap-3 text-sm text-slate-200">
                        <span>Automatisch versturen</span>
                        <ToggleSwitch v-model="settings.enabled" />
                    </label>
                </div>

                <div class="grid gap-5 md:grid-cols-2">
                    <div>
                        <label class="mb-2 block text-sm font-medium text-slate-300">E-mail boekhouding</label>
                        <textarea
                            v-model="recipientsText"
                            rows="2"
                            placeholder="boekhouder@kantoor.be"
                            class="w-full rounded-2xl border border-slate-700 bg-slate-950 px-4 py-3 text-sm text-white placeholder:text-slate-600"
                        />
                        <p class="mt-1 text-xs text-slate-500">Meerdere adressen scheiden met een komma of nieuwe lijn.</p>
                    </div>

                    <div>
                        <label class="mb-2 block text-sm font-medium text-slate-300">Kopie (cc)</label>
                        <textarea
                            v-model="ccText"
                            rows="2"
                            placeholder="optioneel, bv. je eigen adres"
                            class="w-full rounded-2xl border border-slate-700 bg-slate-950 px-4 py-3 text-sm text-white placeholder:text-slate-600"
                        />
                    </div>

                    <div>
                        <label class="mb-2 block text-sm font-medium text-slate-300">Frequentie</label>
                        <select
                            v-model="settings.frequency"
                            class="w-full rounded-2xl border border-slate-700 bg-slate-950 px-4 py-3 text-sm text-white"
                        >
                            <option value="quarter">Per kwartaal</option>
                            <option value="month">Per maand</option>
                        </select>
                    </div>

                    <div>
                        <label class="mb-2 block text-sm font-medium text-slate-300">Verzenddag</label>
                        <div class="flex items-center gap-3">
                            <input
                                v-model.number="settings.send_day"
                                type="number"
                                min="1"
                                max="28"
                                class="w-24 rounded-2xl border border-slate-700 bg-slate-950 px-4 py-3 text-sm text-white"
                            />
                            <span class="text-sm text-slate-400">
                                dag van de maand na {{ settings.frequency === 'month' ? 'de maand' : 'het kwartaal' }}
                            </span>
                        </div>
                        <p class="mt-1 text-xs text-slate-500">Een paar dagen wachten laat ruimte voor late correcties.</p>
                    </div>

                    <div class="md:col-span-2 grid gap-3 md:grid-cols-2">
                        <label class="inline-flex items-center gap-3 rounded-2xl border border-slate-700 bg-slate-950 px-4 py-3 text-sm text-slate-200">
                            <input v-model="settings.include_payment_methods" type="checkbox" class="h-4 w-4 rounded border-slate-600 bg-slate-900 text-violet-500" />
                            <span>Overzicht per betaalwijze meesturen (cash, Bancontact, ...)</span>
                        </label>
                        <label class="inline-flex items-center gap-3 rounded-2xl border border-slate-700 bg-slate-950 px-4 py-3 text-sm text-slate-200">
                            <input v-model="settings.include_invoices" type="checkbox" class="h-4 w-4 rounded border-slate-600 bg-slate-900 text-violet-500" />
                            <span>Factuurverkopen meenemen</span>
                        </label>
                    </div>

                    <div class="md:col-span-2">
                        <p class="mb-2 text-sm font-medium text-slate-300">Kolommen in de Excel</p>
                        <div class="flex flex-wrap gap-3">
                            <label
                                v-for="group in groupOptions"
                                :key="group.key"
                                class="inline-flex items-center gap-2 rounded-2xl border border-slate-700 bg-slate-950 px-3 py-2 text-sm text-slate-200"
                            >
                                <input v-model="settings.visible_groups[group.key]" type="checkbox" class="h-4 w-4 rounded border-slate-600 bg-slate-900 text-violet-500" />
                                <span>{{ group.label }}</span>
                            </label>
                        </div>
                    </div>

                    <div class="md:col-span-2">
                        <label class="mb-2 block text-sm font-medium text-slate-300">Extra tekst in de mail</label>
                        <textarea
                            v-model="settings.message"
                            rows="3"
                            placeholder="optioneel"
                            class="w-full rounded-2xl border border-slate-700 bg-slate-950 px-4 py-3 text-sm text-white placeholder:text-slate-600"
                        />
                    </div>
                </div>

                <div class="mt-6 flex flex-wrap items-center justify-end gap-4">
                    <p v-if="feedback" class="text-sm" :class="feedback.type === 'error' ? 'text-rose-300' : 'text-emerald-300'">
                        {{ feedback.text }}
                    </p>
                    <button
                        :disabled="saving"
                        class="rounded-2xl border border-violet-500/30 bg-violet-500/10 px-5 py-3 text-sm font-semibold text-violet-300 transition hover:bg-violet-500/20 disabled:cursor-not-allowed disabled:opacity-60"
                        @click="save"
                    >
                        {{ saving ? 'Opslaan...' : 'Opslaan' }}
                    </button>
                </div>
            </div>

            <div class="rounded-3xl border border-slate-800 bg-slate-900/90 shadow-xl">
                <div class="border-b border-slate-800 px-6 py-4">
                    <h2 class="text-lg font-semibold text-white">Verzendhistoriek</h2>
                </div>

                <div class="overflow-auto">
                    <table class="min-w-full divide-y divide-slate-800 text-sm">
                        <thead class="bg-slate-950/80 text-slate-300">
                        <tr>
                            <th class="px-4 py-3 text-left">Periode</th>
                            <th class="px-4 py-3 text-left">Verstuurd</th>
                            <th class="px-4 py-3 text-left">Hoe</th>
                            <th class="px-4 py-3 text-left">Naar</th>
                            <th class="px-4 py-3 text-right">Totaal incl.</th>
                            <th class="px-4 py-3 text-left">Status</th>
                        </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-800 text-slate-200">
                        <tr v-for="item in exports" :key="item.id">
                            <td class="px-4 py-3 font-medium text-white">{{ item.period_label }}</td>
                            <td class="px-4 py-3">{{ formatDateTime(item.sent_at || item.created_at) }}</td>
                            <td class="px-4 py-3">
                                {{ item.trigger === 'auto' ? 'Automatisch' : 'Manueel' }}
                                <span v-if="item.sent_by" class="text-slate-400">· {{ item.sent_by }}</span>
                            </td>
                            <td class="px-4 py-3 text-slate-300">{{ [...item.recipients, ...item.cc].join(', ') }}</td>
                            <td class="px-4 py-3 text-right">{{ formatCurrency(item.total_incl_vat) }}</td>
                            <td class="px-4 py-3">
                                <span
                                    v-if="item.status === 'sent'"
                                    class="rounded-full bg-emerald-500/15 px-3 py-1 text-xs font-semibold text-emerald-300"
                                >Verstuurd</span>
                                <span
                                    v-else
                                    class="rounded-full bg-rose-500/15 px-3 py-1 text-xs font-semibold text-rose-300"
                                    :title="item.error"
                                >Mislukt</span>
                            </td>
                        </tr>
                        <tr v-if="!exports.length">
                            <td colspan="6" class="px-4 py-10 text-center text-slate-400">Nog niets verstuurd.</td>
                        </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </template>
    </section>
</template>

<script setup>
import { onMounted, reactive, ref } from 'vue'
import ToggleSwitch from '../../../components/ToggleSwitch.vue'
import { errorMessage, fetchAccounting, updateAccountingSettings } from '../services/accountingApi'

const loading = ref(true)
const saving = ref(false)
const feedback = ref(null)
const next = ref(null)
const exports = ref([])
const recipientsText = ref('')
const ccText = ref('')

const settings = reactive({
    enabled: false,
    frequency: 'quarter',
    send_day: 5,
    include_invoices: false,
    include_payment_methods: true,
    visible_groups: { rate0: false, rate6: true, rate12: false, rate21: true, total: true },
    message: '',
})

const groupOptions = [
    { key: 'rate0', label: '0%' },
    { key: 'rate6', label: '6%' },
    { key: 'rate12', label: '12%' },
    { key: 'rate21', label: '21%' },
    { key: 'total', label: 'Totaal' },
]

function apply(data) {
    const s = data.settings
    settings.enabled = s.enabled
    settings.frequency = s.frequency
    settings.send_day = s.send_day
    settings.include_invoices = s.include_invoices
    settings.include_payment_methods = s.include_payment_methods
    settings.visible_groups = { ...settings.visible_groups, ...s.visible_groups }
    settings.message = s.message || ''
    recipientsText.value = (s.recipients || []).join(', ')
    ccText.value = (s.cc || []).join(', ')
    next.value = data.next
    exports.value = data.exports || []
}

async function load() {
    loading.value = true
    try {
        apply(await fetchAccounting())
    } catch (error) {
        feedback.value = { type: 'error', text: errorMessage(error, 'Instellingen konden niet geladen worden.') }
    } finally {
        loading.value = false
    }
}

async function save() {
    saving.value = true
    feedback.value = null

    try {
        const response = await updateAccountingSettings({
            ...settings,
            recipients: recipientsText.value,
            cc: ccText.value,
        })
        apply(response.data)
        feedback.value = { type: 'success', text: response.message || 'Opgeslagen.' }
    } catch (error) {
        feedback.value = { type: 'error', text: errorMessage(error, 'Opslaan mislukt.') }
    } finally {
        saving.value = false
    }
}

function formatCurrency(value) {
    return new Intl.NumberFormat('nl-BE', { style: 'currency', currency: 'EUR' }).format(Number(value || 0))
}

function formatDate(value) {
    if (!value) return '—'
    const [year, month, day] = value.split('-')
    return `${day}/${month}/${year}`
}

function formatDateTime(value) {
    if (!value) return '—'
    return new Intl.DateTimeFormat('nl-BE', { dateStyle: 'short', timeStyle: 'short' }).format(new Date(value))
}

onMounted(load)
</script>
