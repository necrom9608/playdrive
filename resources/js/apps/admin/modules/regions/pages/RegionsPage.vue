<template>
    <div class="space-y-6">
        <div class="flex flex-wrap items-start justify-between gap-4">
            <div>
                <h1 class="text-3xl font-bold text-white">Regio's</h1>
                <p class="mt-2 text-slate-400">
                    Beheer de regio's en hun schoolvakanties. Tenants erven automatisch de vakantiekalender van hun regio.
                </p>
            </div>
            <button
                type="button"
                class="rounded-xl bg-cyan-600 px-4 py-3 text-sm font-semibold text-white transition hover:bg-cyan-500"
                @click="openCreateRegionModal"
            >
                Nieuwe regio
            </button>
        </div>

        <div v-if="error" class="rounded-2xl border border-red-800 bg-red-950/40 px-4 py-3 text-sm text-red-300">
            {{ error }}
        </div>

        <!-- Geen regio geselecteerd: toon regio-lijst -->
        <template v-if="!activeRegion">
            <div v-if="loading" class="py-8 text-center text-sm text-slate-400">Laden...</div>

            <div v-else-if="regions.length === 0" class="rounded-2xl border border-slate-800 bg-slate-900 p-8 text-center text-sm text-slate-400">
                Nog geen regio's aangemaakt.
            </div>

            <div v-else class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                <div
                    v-for="region in regions"
                    :key="region.id"
                    class="group rounded-2xl border border-slate-800 bg-slate-900 p-5 transition hover:border-slate-700"
                >
                    <div class="flex items-start justify-between gap-3">
                        <div>
                            <div class="font-mono text-xs font-semibold text-cyan-400">{{ region.code }}</div>
                            <div class="mt-1 text-base font-semibold text-white">{{ region.name }}</div>
                            <div class="mt-2 text-xs text-slate-500">{{ region.seasons_count ?? 0 }} periodes</div>
                        </div>
                        <div class="flex gap-2">
                            <button
                                type="button"
                                class="rounded-xl border border-slate-700 px-3 py-2 text-xs font-medium text-slate-300 transition hover:bg-slate-800"
                                @click="openEditRegionModal(region)"
                            >
                                Bewerken
                            </button>
                            <button
                                type="button"
                                class="rounded-xl border border-rose-500/30 bg-rose-500/10 px-3 py-2 text-xs font-semibold text-rose-200 transition hover:bg-rose-500/20"
                                @click="handleDeleteRegion(region)"
                            >
                                Verwijderen
                            </button>
                        </div>
                    </div>
                    <button
                        type="button"
                        class="mt-4 w-full rounded-xl border border-slate-700 py-2 text-xs font-medium text-slate-300 transition hover:bg-slate-800"
                        @click="openSeasons(region)"
                    >
                        Vakantieperiodes beheren →
                    </button>
                </div>
            </div>
        </template>

        <!-- Regio geselecteerd: toon seizoenenlijst -->
        <template v-else>
            <div class="flex items-center gap-4">
                <button
                    type="button"
                    class="rounded-xl border border-slate-700 px-3 py-2 text-xs font-medium text-slate-300 transition hover:bg-slate-800"
                    @click="closeSeasons"
                >
                    ← Terug naar regio's
                </button>
                <h2 class="text-xl font-semibold text-white">
                    <span class="font-mono text-cyan-400">{{ activeRegion.code }}</span>
                    — {{ activeRegion.name }}
                </h2>
            </div>

            <!-- Kopieer naar volgend jaar -->
            <div class="flex flex-wrap items-end gap-3 rounded-2xl border border-slate-800 bg-slate-900 p-4">
                <div>
                    <label class="mb-1 block text-xs font-medium text-slate-400">Van jaar</label>
                    <input
                        v-model.number="copyFrom"
                        type="number"
                        min="2024"
                        max="2050"
                        class="w-28 rounded-xl border border-slate-700 bg-slate-950 px-3 py-2 text-sm text-white outline-none focus:border-cyan-500"
                    />
                </div>
                <div>
                    <label class="mb-1 block text-xs font-medium text-slate-400">Naar jaar</label>
                    <input
                        v-model.number="copyTo"
                        type="number"
                        min="2024"
                        max="2050"
                        class="w-28 rounded-xl border border-slate-700 bg-slate-950 px-3 py-2 text-sm text-white outline-none focus:border-cyan-500"
                    />
                </div>
                <button
                    type="button"
                    :disabled="copying"
                    class="rounded-xl border border-slate-700 px-4 py-2 text-sm font-medium text-slate-300 transition hover:bg-slate-800 disabled:opacity-60"
                    @click="handleCopySeasons"
                >
                    {{ copying ? 'Kopiëren...' : 'Kopieer periodes →' }}
                </button>
                <span v-if="copySuccess" class="text-sm text-emerald-400">{{ copySuccess }}</span>
            </div>

            <div class="flex justify-end">
                <button
                    type="button"
                    class="rounded-xl bg-cyan-600 px-4 py-3 text-sm font-semibold text-white transition hover:bg-cyan-500"
                    @click="openCreateSeasonModal"
                >
                    Nieuwe periode
                </button>
            </div>

            <div v-if="seasonsLoading" class="py-6 text-center text-sm text-slate-400">Laden...</div>

            <div v-else-if="seasons.length === 0" class="rounded-2xl border border-slate-800 bg-slate-900 p-8 text-center text-sm text-slate-400">
                Nog geen periodes voor deze regio.
            </div>

            <div v-else class="rounded-2xl border border-slate-800 bg-slate-900 overflow-x-auto">
                <table class="min-w-full text-sm">
                    <thead class="bg-slate-950 text-slate-300">
                        <tr>
                            <th class="px-4 py-3 text-left font-semibold">Naam</th>
                            <th class="px-4 py-3 text-left font-semibold">Season key</th>
                            <th class="px-4 py-3 text-left font-semibold">Van</th>
                            <th class="px-4 py-3 text-left font-semibold">Tot en met</th>
                            <th class="px-4 py-3 text-left font-semibold">Prioriteit</th>
                            <th class="px-4 py-3 text-right font-semibold">Acties</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr
                            v-for="season in seasonsSorted"
                            :key="season.id"
                            class="border-t border-slate-800"
                        >
                            <td class="px-4 py-3 font-medium text-white">{{ season.season_name }}</td>
                            <td class="px-4 py-3">
                                <span class="rounded-full bg-slate-800 px-2.5 py-1 font-mono text-xs text-cyan-300">
                                    {{ season.season_key }}
                                </span>
                            </td>
                            <td class="px-4 py-3 text-slate-300">{{ formatDate(season.date_from) }}</td>
                            <td class="px-4 py-3 text-slate-300">{{ formatDate(season.date_until) }}</td>
                            <td class="px-4 py-3 text-slate-400">{{ season.priority }}</td>
                            <td class="px-4 py-3 text-right">
                                <div class="flex justify-end gap-2">
                                    <button
                                        type="button"
                                        class="rounded-xl border border-slate-700 px-3 py-2 text-xs font-medium text-slate-300 transition hover:bg-slate-800"
                                        @click="openEditSeasonModal(season)"
                                    >
                                        Bewerken
                                    </button>
                                    <button
                                        type="button"
                                        class="rounded-xl border border-rose-500/30 bg-rose-500/10 px-3 py-2 text-xs font-semibold text-rose-200 transition hover:bg-rose-500/20"
                                        @click="handleDeleteSeason(season)"
                                    >
                                        Verwijderen
                                    </button>
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </template>

        <!-- Modals -->
        <RegionFormModal
            :open="regionModalOpen"
            :region="editingRegion"
            :saving="saving"
            :error="modalError"
            @close="closeRegionModal"
            @submit="handleRegionSubmit"
        />

        <SeasonFormModal
            :open="seasonModalOpen"
            :season="editingSeason"
            :region-name="activeRegion?.name ?? ''"
            :saving="saving"
            :error="modalError"
            @close="closeSeasonModal"
            @submit="handleSeasonSubmit"
        />
    </div>
</template>

<script setup>
import { computed, onMounted, ref } from 'vue'
import RegionFormModal from '../components/RegionFormModal.vue'
import SeasonFormModal from '../components/SeasonFormModal.vue'
import {
    copySeasons,
    createRegion,
    createSeason,
    deleteRegion,
    deleteSeason,
    fetchRegions,
    fetchSeasons,
    updateRegion,
    updateSeason,
} from '../services/regionApi'

// ─── State ────────────────────────────────────────────────────────────────
const regions       = ref([])
const loading       = ref(false)
const error         = ref('')

const activeRegion  = ref(null)
const seasons       = ref([])
const seasonsLoading = ref(false)

const saving        = ref(false)
const modalError    = ref('')

const regionModalOpen = ref(false)
const editingRegion   = ref(null)

const seasonModalOpen = ref(false)
const editingSeason   = ref(null)

const copyFrom      = ref(new Date().getFullYear())
const copyTo        = ref(new Date().getFullYear() + 1)
const copying       = ref(false)
const copySuccess   = ref('')

// ─── Computed ─────────────────────────────────────────────────────────────
const seasonsSorted = computed(() =>
    [...seasons.value].sort((a, b) => a.date_from.localeCompare(b.date_from))
)

// ─── Regio's ─────────────────────────────────────────────────────────────
async function loadRegions() {
    loading.value = true
    error.value = ''
    try {
        const res = await fetchRegions()
        regions.value = res.regions ?? []
    } catch {
        error.value = 'Kon regio\'s niet laden.'
    } finally {
        loading.value = false
    }
}

function openCreateRegionModal() {
    editingRegion.value = null
    modalError.value = ''
    regionModalOpen.value = true
}

function openEditRegionModal(region) {
    editingRegion.value = region
    modalError.value = ''
    regionModalOpen.value = true
}

function closeRegionModal() {
    regionModalOpen.value = false
    editingRegion.value = null
    modalError.value = ''
}

async function handleRegionSubmit(payload) {
    saving.value = true
    modalError.value = ''
    try {
        if (editingRegion.value?.id) {
            await updateRegion(editingRegion.value.id, payload)
        } else {
            await createRegion(payload)
        }
        closeRegionModal()
        await loadRegions()
    } catch (err) {
        modalError.value = err?.data?.message || 'Opslaan mislukt.'
    } finally {
        saving.value = false
    }
}

async function handleDeleteRegion(region) {
    if (!confirm(`Regio "${region.name}" en alle bijhorende periodes verwijderen?`)) return
    try {
        await deleteRegion(region.id)
        await loadRegions()
    } catch {
        error.value = 'Verwijderen mislukt.'
    }
}

// ─── Seizoenen ────────────────────────────────────────────────────────────
async function openSeasons(region) {
    activeRegion.value = region
    seasonsLoading.value = true
    seasons.value = []
    try {
        const res = await fetchSeasons(region.id)
        seasons.value = res.seasons ?? []
    } catch {
        error.value = 'Kon periodes niet laden.'
    } finally {
        seasonsLoading.value = false
    }
}

function closeSeasons() {
    activeRegion.value = null
    seasons.value = []
    copySuccess.value = ''
}

function openCreateSeasonModal() {
    editingSeason.value = null
    modalError.value = ''
    seasonModalOpen.value = true
}

function openEditSeasonModal(season) {
    editingSeason.value = season
    modalError.value = ''
    seasonModalOpen.value = true
}

function closeSeasonModal() {
    seasonModalOpen.value = false
    editingSeason.value = null
    modalError.value = ''
}

async function handleSeasonSubmit(payload) {
    saving.value = true
    modalError.value = ''
    try {
        if (editingSeason.value?.id) {
            await updateSeason(activeRegion.value.id, editingSeason.value.id, payload)
        } else {
            await createSeason(activeRegion.value.id, payload)
        }
        closeSeasonModal()
        await openSeasons(activeRegion.value)
    } catch (err) {
        modalError.value = err?.data?.message || 'Opslaan mislukt.'
    } finally {
        saving.value = false
    }
}

async function handleDeleteSeason(season) {
    if (!confirm(`Periode "${season.season_name}" verwijderen?`)) return
    try {
        await deleteSeason(activeRegion.value.id, season.id)
        await openSeasons(activeRegion.value)
    } catch {
        error.value = 'Verwijderen mislukt.'
    }
}

async function handleCopySeasons() {
    if (!confirm(`Alle periodes van ${copyFrom.value} kopiëren naar ${copyTo.value}?`)) return
    copying.value = true
    copySuccess.value = ''
    try {
        const res = await copySeasons(activeRegion.value.id, copyFrom.value, copyTo.value)
        copySuccess.value = `${res.created} periodes aangemaakt.`
        await openSeasons(activeRegion.value)
    } catch {
        error.value = 'Kopiëren mislukt.'
    } finally {
        copying.value = false
    }
}

// ─── Helpers ──────────────────────────────────────────────────────────────
function formatDate(dateStr) {
    if (!dateStr) return '—'
    return new Date(dateStr).toLocaleDateString('nl-BE', { day: '2-digit', month: 'short', year: 'numeric' })
}

onMounted(loadRegions)
</script>
