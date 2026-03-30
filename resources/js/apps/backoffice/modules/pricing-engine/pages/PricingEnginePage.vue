<template>
    <div class="space-y-6">
        <div class="flex flex-wrap items-start justify-between gap-4">
            <div>
                <h1 v-if="!embedded" class="text-3xl font-bold text-white">Automatische prijsregels</h1>
                <h2 v-else class="text-2xl font-bold text-white">Automatische prijsregels</h2>
                <p class="mt-2 max-w-4xl text-slate-400">
                    Flexibele prijsregels voor tickets, extra blokken en automatische productkoppelingen.
                    De regels zijn bewust generiek opgezet zodat ze later ook voor andere concepten dan Game-INN bruikbaar blijven.
                </p>
            </div>

            <div class="flex gap-3">
                <button
                    type="button"
                    @click="loadOverview"
                    :disabled="loading"
                    class="rounded-xl border border-slate-700 px-4 py-3 text-sm font-medium text-slate-300 transition hover:bg-slate-800 disabled:opacity-60"
                >
                    Vernieuwen
                </button>

                <button
                    type="button"
                    @click="openCreateProfileModal"
                    class="rounded-xl bg-blue-600 px-4 py-3 text-sm font-semibold text-white transition hover:bg-blue-500"
                >
                    Nieuw profiel
                </button>
            </div>
        </div>

        <div class="grid gap-6 xl:grid-cols-[360px_minmax(0,1fr)]">
            <section class="rounded-2xl border border-slate-800 bg-slate-900 shadow-sm">
                <div class="border-b border-slate-800 px-5 py-4">
                    <h2 class="text-lg font-semibold text-white">Profielen</h2>
                    <p class="mt-1 text-sm text-slate-400">Bijvoorbeeld Game-INN standaardtarief of speciale arrangementen.</p>
                </div>

                <div v-if="loading" class="p-5 text-sm text-slate-400">Laden...</div>
                <div v-else-if="profiles.length === 0" class="p-5 text-sm text-slate-400">Nog geen prijsprofielen gevonden.</div>

                <div v-else class="divide-y divide-slate-800">
                    <button
                        v-for="profile in profiles"
                        :key="profile.id"
                        type="button"
                        class="block w-full px-5 py-4 text-left transition hover:bg-slate-800/60"
                        :class="selectedProfile?.id === profile.id ? 'bg-slate-800/80' : ''"
                        @click="selectedProfileId = profile.id"
                    >
                        <div class="flex items-start justify-between gap-3">
                            <div>
                                <div class="flex flex-wrap items-center gap-2">
                                    <span class="font-semibold text-white">{{ profile.name }}</span>
                                    <span v-if="profile.is_default" class="rounded-full bg-emerald-500/15 px-2 py-0.5 text-xs font-semibold text-emerald-300">Standaard</span>
                                    <span v-if="!profile.is_active" class="rounded-full bg-slate-700 px-2 py-0.5 text-xs font-semibold text-slate-300">Inactief</span>
                                </div>
                                <p class="mt-1 text-xs text-slate-400">{{ profile.slug }}</p>
                                <p class="mt-2 text-sm text-slate-300">{{ profile.description || 'Geen omschrijving.' }}</p>
                                <div class="mt-3 flex flex-wrap gap-2 text-xs text-slate-400">
                                    <span class="rounded-full border border-slate-700 px-2 py-1">Academisch kwartier: {{ profile.grace_minutes }} min</span>
                                    <span class="rounded-full border border-slate-700 px-2 py-1">Extra blok: {{ profile.extra_block_minutes }} min</span>
                                    <span class="rounded-full border border-slate-700 px-2 py-1">{{ profile.rules.length }} regels</span>
                                </div>
                            </div>
                        </div>
                    </button>
                </div>
            </section>

            <section class="space-y-6">
                <div v-if="error" class="rounded-2xl border border-red-800 bg-red-950/40 px-4 py-3 text-sm text-red-300">
                    {{ error }}
                </div>

                <div v-if="!selectedProfile" class="rounded-2xl border border-dashed border-slate-700 bg-slate-900/60 p-8 text-sm text-slate-400">
                    Selecteer links een prijsprofiel om regels te beheren.
                </div>

                <template v-else>
                    <div class="rounded-2xl border border-slate-800 bg-slate-900 shadow-sm">
                        <div class="flex flex-wrap items-start justify-between gap-4 border-b border-slate-800 px-5 py-4">
                            <div>
                                <h2 class="text-xl font-semibold text-white">{{ selectedProfile.name }}</h2>
                                <p class="mt-1 text-sm text-slate-400">{{ selectedProfile.description || 'Geen omschrijving.' }}</p>
                            </div>

                            <div class="flex gap-2">
                                <button type="button" class="rounded-lg border border-slate-700 px-3 py-2 text-xs font-medium text-slate-300 transition hover:bg-slate-800" @click="openEditProfileModal(selectedProfile)">Profiel bewerken</button>
                                <button type="button" class="rounded-lg border border-red-800 px-3 py-2 text-xs font-medium text-red-300 transition hover:bg-red-950/40" @click="removeProfile(selectedProfile)">Profiel verwijderen</button>
                            </div>
                        </div>

                        <div class="grid gap-4 px-5 py-4 md:grid-cols-3">
                            <div class="rounded-2xl border border-slate-800 bg-slate-950/50 p-4">
                                <p class="text-xs uppercase tracking-wide text-slate-500">Academisch kwartier</p>
                                <p class="mt-2 text-2xl font-semibold text-white">{{ selectedProfile.grace_minutes }} min</p>
                            </div>
                            <div class="rounded-2xl border border-slate-800 bg-slate-950/50 p-4">
                                <p class="text-xs uppercase tracking-wide text-slate-500">Extra blok</p>
                                <p class="mt-2 text-2xl font-semibold text-white">{{ selectedProfile.extra_block_minutes }} min</p>
                            </div>
                            <div class="rounded-2xl border border-slate-800 bg-slate-950/50 p-4">
                                <p class="text-xs uppercase tracking-wide text-slate-500">Actieve regels</p>
                                <p class="mt-2 text-2xl font-semibold text-white">{{ activeRulesCount }}</p>
                            </div>
                        </div>
                    </div>

                    <div class="rounded-2xl border border-slate-800 bg-slate-900 shadow-sm">
                        <div class="flex flex-wrap items-start justify-between gap-4 border-b border-slate-800 px-5 py-4">
                            <div>
                                <h3 class="text-lg font-semibold text-white">Prijsregels</h3>
                                <p class="mt-1 text-sm text-slate-400">Duurregels en cateringregels leven samen in hetzelfde profiel.</p>
                            </div>
                            <button
                                type="button"
                                class="rounded-xl bg-blue-600 px-4 py-3 text-sm font-semibold text-white transition hover:bg-blue-500"
                                @click="openCreateRuleModal"
                            >
                                Nieuwe regel
                            </button>
                        </div>

                        <div v-if="selectedProfile.rules.length === 0" class="p-5 text-sm text-slate-400">Nog geen regels in dit profiel.</div>

                        <div v-else class="overflow-x-auto">
                            <table class="min-w-full text-sm">
                                <thead class="bg-slate-950 text-slate-300">
                                    <tr>
                                        <th class="px-4 py-3 text-left font-semibold">Volgorde</th>
                                        <th class="px-4 py-3 text-left font-semibold">Type</th>
                                        <th class="px-4 py-3 text-left font-semibold">Naam</th>
                                        <th class="px-4 py-3 text-left font-semibold">Voorwaarden</th>
                                        <th class="px-4 py-3 text-left font-semibold">Actie</th>
                                        <th class="px-4 py-3 text-left font-semibold">Status</th>
                                        <th class="px-4 py-3 text-right font-semibold">Acties</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <tr
                                        v-for="rule in selectedProfile.rules"
                                        :key="rule.id"
                                        draggable="true"
                                        class="border-t border-slate-800 bg-slate-900"
                                        :class="draggingRuleId === rule.id ? 'opacity-40' : ''"
                                        @dragstart="draggingRuleId = rule.id"
                                        @dragover.prevent
                                        @drop="handleRuleDrop(rule.id)"
                                    >
                                        <td class="px-4 py-3 text-slate-400">↕ {{ rule.sort_order }}</td>
                                        <td class="px-4 py-3">
                                            <span class="rounded-full px-2.5 py-1 text-xs font-semibold" :class="rule.type === 'duration' ? 'bg-blue-500/15 text-blue-300' : 'bg-amber-500/15 text-amber-300'">
                                                {{ rule.type === 'duration' ? 'Duur' : 'Catering' }}
                                            </span>
                                        </td>
                                        <td class="px-4 py-3 text-white">
                                            <div class="font-medium">{{ rule.name }}</div>
                                            <div class="mt-1 text-xs text-slate-400">{{ rule.description || '-' }}</div>
                                        </td>
                                        <td class="px-4 py-3 text-slate-300">{{ describeConditions(rule) }}</td>
                                        <td class="px-4 py-3 text-slate-300">{{ describeActions(rule) }}</td>
                                        <td class="px-4 py-3">
                                            <span class="inline-flex rounded-full px-2.5 py-1 text-xs font-semibold" :class="rule.is_active ? 'bg-emerald-500/15 text-emerald-300' : 'bg-slate-700 text-slate-300'">
                                                {{ rule.is_active ? 'Actief' : 'Inactief' }}
                                            </span>
                                        </td>
                                        <td class="px-4 py-3">
                                            <div class="flex justify-end gap-2">
                                                <button type="button" class="rounded-lg border border-slate-700 px-3 py-2 text-xs font-medium text-slate-300 transition hover:bg-slate-800" @click="openEditRuleModal(rule)">Bewerken</button>
                                                <button type="button" class="rounded-lg border border-red-800 px-3 py-2 text-xs font-medium text-red-300 transition hover:bg-red-950/40" @click="removeRule(rule)">Verwijderen</button>
                                            </div>
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </div>

                    <div class="rounded-2xl border border-slate-800 bg-slate-900 p-5 shadow-sm">
                        <h3 class="text-lg font-semibold text-white">Game-INN voorbeeld</h3>
                        <div class="mt-3 space-y-2 text-sm text-slate-300">
                            <p>Voor Game-INN kan je in één profiel bijvoorbeeld 6 duurregels zetten: 1u, 2u en dagticket voor kinderen en volwassenen.</p>
                            <p>Daarbovenop kan je een extra duurregel zetten met <span class="font-semibold">billing_mode = product_plus_extra</span> om vanaf minuut 16 een toeslagproduct toe te voegen.</p>
                            <p>Daarnaast kan je cateringregels maken zoals <span class="font-semibold">Pannenkoeken → Pannenkoeken product + Goodiebag product, participant_scope = children</span>.</p>
                        </div>
                    </div>
                </template>
            </section>
        </div>

        <ModalDialog :open="profileModalOpen" :title="editingProfile ? 'Profiel bewerken' : 'Nieuw prijsprofiel'" description="Een profiel groepeert alle regels voor één prijsstrategie." @close="closeProfileModal">
            <form class="space-y-4" @submit.prevent="submitProfile">
                <AlertError :message="formError" />
                <div>
                    <label class="mb-1 block text-sm font-medium text-slate-300">Naam</label>
                    <input v-model="profileForm.name" type="text" class="field" placeholder="Bijvoorbeeld: Game-INN standaard" />
                </div>
                <div>
                    <label class="mb-1 block text-sm font-medium text-slate-300">Slug</label>
                    <input v-model="profileForm.slug" type="text" class="field" placeholder="Laat leeg om automatisch te genereren" />
                </div>
                <div>
                    <label class="mb-1 block text-sm font-medium text-slate-300">Omschrijving</label>
                    <textarea v-model="profileForm.description" rows="3" class="field" />
                </div>
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="mb-1 block text-sm font-medium text-slate-300">Academisch kwartier (min)</label>
                        <input v-model.number="profileForm.grace_minutes" type="number" min="0" max="240" class="field" />
                    </div>
                    <div>
                        <label class="mb-1 block text-sm font-medium text-slate-300">Extra blok (min)</label>
                        <input v-model.number="profileForm.extra_block_minutes" type="number" min="1" max="240" class="field" />
                    </div>
                </div>
                <label class="flex items-center gap-3 text-sm text-slate-300"><input v-model="profileForm.is_active" type="checkbox" class="h-4 w-4 rounded border-slate-600 bg-slate-950" /> Actief</label>
                <label class="flex items-center gap-3 text-sm text-slate-300"><input v-model="profileForm.is_default" type="checkbox" class="h-4 w-4 rounded border-slate-600 bg-slate-950" /> Standaardprofiel</label>
                <div class="flex flex-wrap gap-3 pt-2">
                    <button type="submit" :disabled="saving" class="rounded-xl bg-blue-600 px-4 py-3 text-sm font-semibold text-white transition hover:bg-blue-500 disabled:opacity-60">{{ saving ? 'Opslaan...' : 'Opslaan' }}</button>
                    <button type="button" @click="closeProfileModal" class="rounded-xl border border-slate-700 px-4 py-3 text-sm font-medium text-slate-300 transition hover:bg-slate-800">Annuleren</button>
                </div>
            </form>
        </ModalDialog>

        <ModalDialog :open="ruleModalOpen" :title="editingRule ? 'Regel bewerken' : 'Nieuwe prijsregel'" description="Regels zijn bewust generiek opgebouwd met voorwaarden en acties." @close="closeRuleModal">
            <form class="space-y-4" @submit.prevent="submitRule">
                <AlertError :message="formError" />
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <label class="mb-1 block text-sm font-medium text-slate-300">Type</label>
                        <select v-model="ruleForm.type" class="field">
                            <option value="duration">Duurregel</option>
                            <option value="catering">Cateringregel</option>
                        </select>
                    </div>
                    <div>
                        <label class="mb-1 block text-sm font-medium text-slate-300">Status</label>
                        <label class="mt-3 flex items-center gap-3 text-sm text-slate-300"><input v-model="ruleForm.is_active" type="checkbox" class="h-4 w-4 rounded border-slate-600 bg-slate-950" /> Actief</label>
                    </div>
                </div>
                <div>
                    <label class="mb-1 block text-sm font-medium text-slate-300">Naam</label>
                    <input v-model="ruleForm.name" type="text" class="field" placeholder="Bijvoorbeeld: Kinderen 1 uur" />
                </div>
                <div>
                    <label class="mb-1 block text-sm font-medium text-slate-300">Omschrijving</label>
                    <textarea v-model="ruleForm.description" rows="2" class="field" />
                </div>

                <template v-if="ruleForm.type === 'duration'">
                    <div class="rounded-2xl border border-slate-800 bg-slate-950/50 p-4">
                        <h4 class="text-sm font-semibold text-white">Voorwaarden</h4>
                        <div class="mt-4 grid gap-4 md:grid-cols-3">
                            <div>
                                <label class="mb-1 block text-sm font-medium text-slate-300">Doelgroep</label>
                                <select v-model="ruleForm.conditions.participant_scope" class="field">
                                    <option value="children">Kinderen / studenten</option>
                                    <option value="adults">Volwassenen</option>
                                    <option value="all">Iedereen</option>
                                </select>
                            </div>
                            <div>
                                <label class="mb-1 block text-sm font-medium text-slate-300">Van minuut</label>
                                <input v-model.number="ruleForm.conditions.from_minutes" type="number" min="0" class="field" />
                            </div>
                            <div>
                                <label class="mb-1 block text-sm font-medium text-slate-300">Tot minuut</label>
                                <input v-model.number="ruleForm.conditions.until_minutes" type="number" min="1" class="field" placeholder="Leeg = onbeperkt" />
                            </div>
                        </div>
                    </div>

                    <div class="rounded-2xl border border-slate-800 bg-slate-950/50 p-4">
                        <h4 class="text-sm font-semibold text-white">Actie</h4>
                        <div class="mt-4 space-y-4">
                            <div>
                                <label class="mb-1 block text-sm font-medium text-slate-300">Billing mode</label>
                                <select v-model="ruleForm.actions.billing_mode" class="field">
                                    <option value="fixed_product">Vast product</option>
                                    <option value="product_plus_extra">Product + extra product</option>
                                    <option value="next_rule">Doorschuiven naar volgend tarief</option>
                                </select>
                            </div>
                            <div class="grid gap-4 md:grid-cols-2">
                                <div>
                                    <label class="mb-1 block text-sm font-medium text-slate-300">Hoofdproduct</label>
                                    <select v-model="ruleForm.actions.product_id" class="field">
                                        <option :value="null">Geen</option>
                                        <option v-for="product in products" :key="product.id" :value="product.id">{{ product.name }}</option>
                                    </select>
                                </div>
                                <div>
                                    <label class="mb-1 block text-sm font-medium text-slate-300">Extra product</label>
                                    <select v-model="ruleForm.actions.extra_product_id" class="field">
                                        <option :value="null">Geen</option>
                                        <option v-for="product in products" :key="product.id" :value="product.id">{{ product.name }}</option>
                                    </select>
                                </div>
                            </div>
                            <div class="grid gap-4 md:grid-cols-2">
                                <div>
                                    <label class="mb-1 block text-sm font-medium text-slate-300">Extra drempel (min)</label>
                                    <input v-model.number="ruleForm.actions.extra_threshold_minutes" type="number" min="1" max="240" class="field" placeholder="Bijv. 16" />
                                </div>
                                <div>
                                    <label class="mb-1 block text-sm font-medium text-slate-300">Extra aantal</label>
                                    <input v-model.number="ruleForm.actions.extra_quantity" type="number" min="1" class="field" />
                                </div>
                            </div>
                        </div>
                    </div>
                </template>

                <template v-else>
                    <div class="rounded-2xl border border-slate-800 bg-slate-950/50 p-4">
                        <h4 class="text-sm font-semibold text-white">Voorwaarden & actie</h4>
                        <div class="mt-4 grid gap-4 md:grid-cols-2">
                            <div>
                                <label class="mb-1 block text-sm font-medium text-slate-300">Cateringoptie</label>
                                <select v-model="ruleForm.conditions.catering_option_id" class="field">
                                    <option :value="null">Selecteer</option>
                                    <option v-for="option in cateringOptions" :key="option.id" :value="option.id">{{ option.name }}</option>
                                </select>
                            </div>
                            <div>
                                <label class="mb-1 block text-sm font-medium text-slate-300">Doelgroep</label>
                                <select v-model="ruleForm.conditions.participant_scope" class="field">
                                    <option value="children">Kinderen / studenten</option>
                                    <option value="adults">Volwassenen</option>
                                    <option value="all">Iedereen</option>
                                </select>
                            </div>
                            <div>
                                <label class="mb-1 block text-sm font-medium text-slate-300">Product</label>
                                <select v-model="ruleForm.actions.product_id" class="field">
                                    <option :value="null">Selecteer</option>
                                    <option v-for="product in products" :key="product.id" :value="product.id">{{ product.name }}</option>
                                </select>
                            </div>
                            <div>
                                <label class="mb-1 block text-sm font-medium text-slate-300">Aantal per persoon</label>
                                <input v-model.number="ruleForm.actions.quantity_per_person" type="number" min="0.01" step="0.01" class="field" />
                            </div>
                        </div>
                    </div>
                </template>

                <div class="flex flex-wrap gap-3 pt-2">
                    <button type="submit" :disabled="saving" class="rounded-xl bg-blue-600 px-4 py-3 text-sm font-semibold text-white transition hover:bg-blue-500 disabled:opacity-60">{{ saving ? 'Opslaan...' : 'Opslaan' }}</button>
                    <button type="button" @click="closeRuleModal" class="rounded-xl border border-slate-700 px-4 py-3 text-sm font-medium text-slate-300 transition hover:bg-slate-800">Annuleren</button>
                </div>
            </form>
        </ModalDialog>
    </div>
</template>

<script setup>
import { computed, onMounted, ref } from 'vue'
import ModalDialog from '../../../components/ModalDialog.vue'
import {
    createPricingProfile,
    createPricingRule,
    deletePricingProfile,
    deletePricingRule,
    fetchPricingEngineOverview,
    reorderPricingRules,
    updatePricingProfile,
    updatePricingRule,
} from '../services/pricingEngineApi'

const loading = ref(false)
const saving = ref(false)
const error = ref('')
const formError = ref('')

const profiles = ref([])
const props = defineProps({
    embedded: {
        type: Boolean,
        default: false,
    },
})

const products = ref([])
const cateringOptions = ref([])
const selectedProfileId = ref(null)
const draggingRuleId = ref(null)

const profileModalOpen = ref(false)
const ruleModalOpen = ref(false)
const editingProfile = ref(null)
const editingRule = ref(null)

const profileForm = ref(defaultProfileForm())
const ruleForm = ref(defaultRuleForm())

const selectedProfile = computed(() => profiles.value.find((profile) => profile.id === selectedProfileId.value) || null)
const activeRulesCount = computed(() => selectedProfile.value ? selectedProfile.value.rules.filter((rule) => rule.is_active).length : 0)

function defaultProfileForm() {
    return {
        name: '',
        slug: '',
        description: '',
        is_active: true,
        is_default: false,
        grace_minutes: 15,
        extra_block_minutes: 15,
    }
}

function defaultRuleForm() {
    return {
        type: 'duration',
        name: '',
        description: '',
        is_active: true,
        conditions: {
            participant_scope: 'children',
            from_minutes: 0,
            until_minutes: null,
            catering_option_id: null,
        },
        actions: {
            billing_mode: 'fixed_product',
            product_id: null,
            extra_product_id: null,
            extra_threshold_minutes: 16,
            extra_quantity: 1,
            quantity_per_person: 1,
        },
    }
}

async function loadOverview() {
    loading.value = true
    error.value = ''

    try {
        const data = await fetchPricingEngineOverview()
        profiles.value = data.profiles ?? []
        products.value = data.products ?? []
        cateringOptions.value = data.catering_options ?? []

        if (!selectedProfileId.value || !profiles.value.some((profile) => profile.id === selectedProfileId.value)) {
            selectedProfileId.value = profiles.value[0]?.id ?? null
        }
    } catch (err) {
        console.error(err)
        error.value = 'Kon pricing engine niet laden.'
    } finally {
        loading.value = false
    }
}

function openCreateProfileModal() {
    editingProfile.value = null
    profileForm.value = defaultProfileForm()
    formError.value = ''
    profileModalOpen.value = true
}

function openEditProfileModal(profile) {
    editingProfile.value = profile
    profileForm.value = {
        name: profile.name,
        slug: profile.slug,
        description: profile.description || '',
        is_active: profile.is_active,
        is_default: profile.is_default,
        grace_minutes: profile.grace_minutes,
        extra_block_minutes: profile.extra_block_minutes,
    }
    formError.value = ''
    profileModalOpen.value = true
}

function closeProfileModal() {
    profileModalOpen.value = false
    editingProfile.value = null
    profileForm.value = defaultProfileForm()
    formError.value = ''
}

async function submitProfile() {
    saving.value = true
    formError.value = ''

    try {
        if (editingProfile.value?.id) {
            await updatePricingProfile(editingProfile.value.id, profileForm.value)
        } else {
            await createPricingProfile(profileForm.value)
        }

        closeProfileModal()
        await loadOverview()
    } catch (err) {
        console.error(err)
        formError.value = extractErrorMessage(err, 'Kon prijsprofiel niet opslaan.')
    } finally {
        saving.value = false
    }
}

async function removeProfile(profile) {
    if (!window.confirm(`Prijsprofiel "${profile.name}" verwijderen?`)) return

    try {
        await deletePricingProfile(profile.id)
        await loadOverview()
    } catch (err) {
        console.error(err)
        error.value = 'Kon prijsprofiel niet verwijderen.'
    }
}

function openCreateRuleModal() {
    editingRule.value = null
    ruleForm.value = defaultRuleForm()
    formError.value = ''
    ruleModalOpen.value = true
}

function openEditRuleModal(rule) {
    editingRule.value = rule
    ruleForm.value = {
        type: rule.type,
        name: rule.name,
        description: rule.description || '',
        is_active: rule.is_active,
        conditions: {
            participant_scope: rule.conditions?.participant_scope ?? 'children',
            from_minutes: rule.conditions?.from_minutes ?? 0,
            until_minutes: rule.conditions?.until_minutes ?? null,
            catering_option_id: rule.conditions?.catering_option_id ?? null,
        },
        actions: {
            billing_mode: rule.actions?.billing_mode ?? 'fixed_product',
            product_id: rule.actions?.product_id ?? null,
            extra_product_id: rule.actions?.extra_product_id ?? null,
            extra_threshold_minutes: rule.actions?.extra_threshold_minutes ?? 16,
            extra_quantity: rule.actions?.extra_quantity ?? 1,
            quantity_per_person: rule.actions?.quantity_per_person ?? 1,
        },
    }
    formError.value = ''
    ruleModalOpen.value = true
}

function closeRuleModal() {
    ruleModalOpen.value = false
    editingRule.value = null
    ruleForm.value = defaultRuleForm()
    formError.value = ''
}

async function submitRule() {
    if (!selectedProfile.value) return

    saving.value = true
    formError.value = ''

    try {
        const payload = JSON.parse(JSON.stringify(ruleForm.value))

        if (payload.type !== 'duration') {
            delete payload.conditions.from_minutes
            delete payload.conditions.until_minutes
            delete payload.actions.billing_mode
            delete payload.actions.extra_product_id
            delete payload.actions.extra_threshold_minutes
            delete payload.actions.extra_quantity
        }

        if (payload.type !== 'catering') {
            delete payload.conditions.catering_option_id
            delete payload.actions.quantity_per_person
        }

        if (editingRule.value?.id) {
            await updatePricingRule(editingRule.value.id, payload)
        } else {
            await createPricingRule(selectedProfile.value.id, payload)
        }

        closeRuleModal()
        await loadOverview()
    } catch (err) {
        console.error(err)
        formError.value = extractErrorMessage(err, 'Kon prijsregel niet opslaan.')
    } finally {
        saving.value = false
    }
}

async function removeRule(rule) {
    if (!window.confirm(`Prijsregel "${rule.name}" verwijderen?`)) return

    try {
        await deletePricingRule(rule.id)
        await loadOverview()
    } catch (err) {
        console.error(err)
        error.value = 'Kon prijsregel niet verwijderen.'
    }
}

async function handleRuleDrop(targetRuleId) {
    if (!selectedProfile.value || !draggingRuleId.value || draggingRuleId.value === targetRuleId) {
        draggingRuleId.value = null
        return
    }

    const items = [...selectedProfile.value.rules]
    const fromIndex = items.findIndex((rule) => rule.id === draggingRuleId.value)
    const toIndex = items.findIndex((rule) => rule.id === targetRuleId)

    if (fromIndex < 0 || toIndex < 0) {
        draggingRuleId.value = null
        return
    }

    const [moved] = items.splice(fromIndex, 1)
    items.splice(toIndex, 0, moved)

    try {
        await reorderPricingRules(selectedProfile.value.id, items.map((item) => ({ id: item.id })))
        await loadOverview()
    } catch (err) {
        console.error(err)
        error.value = 'Kon regelvolgorde niet opslaan.'
    } finally {
        draggingRuleId.value = null
    }
}

function describeConditions(rule) {
    if (rule.type === 'duration') {
        const scope = participantScopeLabel(rule.conditions?.participant_scope)
        const from = rule.conditions?.from_minutes ?? 0
        const until = rule.conditions?.until_minutes
        return `${scope} · ${from} - ${until ?? '∞'} min`
    }

    const option = cateringOptions.value.find((item) => item.id === rule.conditions?.catering_option_id)
    return `${option?.name ?? 'Onbekende optie'} · ${participantScopeLabel(rule.conditions?.participant_scope)}`
}

function describeActions(rule) {
    const mainProduct = productLabel(rule.actions?.product_id)

    if (rule.type === 'duration') {
        const mode = rule.actions?.billing_mode

        if (mode === 'product_plus_extra') {
            return `${mainProduct} + ${productLabel(rule.actions?.extra_product_id)} vanaf ${rule.actions?.extra_threshold_minutes ?? '-'} min`
        }

        if (mode === 'next_rule') {
            return 'Doorschuiven naar volgend tarief'
        }

        return mainProduct
    }

    return `${mainProduct} × ${rule.actions?.quantity_per_person ?? 1} / p.`
}

function participantScopeLabel(scope) {
    return {
        children: 'Kinderen / studenten',
        adults: 'Volwassenen',
        all: 'Iedereen',
    }[scope] || 'Onbekend'
}

function productLabel(productId) {
    if (!productId) return 'Geen product'
    return products.value.find((item) => item.id === productId)?.name || `Product #${productId}`
}

function extractErrorMessage(err, fallback) {
    if (err?.status === 422 && err?.data?.errors) {
        const firstError = Object.values(err.data.errors)?.[0]?.[0]
        return firstError || fallback
    }

    return err?.data?.message || err?.message || fallback
}

onMounted(loadOverview)

const AlertError = {
    props: {
        message: { type: String, default: '' },
    },
    template: `
        <div v-if="message" class="rounded-2xl border border-red-800 bg-red-950/40 px-4 py-3 text-sm text-red-300">
            {{ message }}
        </div>
    `,
}
</script>

<style scoped>
.field {
    width: 100%;
    border-radius: 0.75rem;
    border: 1px solid rgb(51 65 85);
    background: rgb(2 6 23);
    padding: 0.75rem 1rem;
    color: white;
    outline: none;
    transition: border-color 0.2s ease;
}

.field:focus {
    border-color: rgb(59 130 246);
}
</style>
