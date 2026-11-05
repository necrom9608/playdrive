<template>
    <ModalDialog :open="open" :title="`Banner · ${name}`" description="Wordt getoond op de tegel in Frontdesk → Zaal." @close="$emit('close')">
        <div class="space-y-6">
            <!-- Huidige banner -->
            <div class="flex flex-wrap items-end gap-4">
                <div class="relative h-[104px] w-[230px] overflow-hidden rounded-2xl border border-slate-800 bg-slate-950">
                    <img v-if="current.banner_url" :src="current.banner_url" alt="" class="h-full w-full" :class="fitClass(current.banner_ratio)" />
                    <div v-else class="flex h-full items-center justify-center text-xs text-slate-500">Geen banner</div>
                </div>
                <div class="space-y-2">
                    <p class="text-sm text-slate-400">{{ sourceLabel }}</p>
                    <div class="flex flex-wrap gap-2">
                        <label class="inline-flex cursor-pointer items-center gap-1.5 rounded-xl border border-slate-700 bg-slate-950 px-3 py-2 text-sm text-slate-200 transition hover:bg-slate-800">
                            <ArrowUpTrayIcon class="h-4 w-4" /> Uploaden
                            <input type="file" accept="image/png,image/jpeg,image/webp,image/gif" class="sr-only" :disabled="busy" @change="upload" />
                        </label>
                        <button v-if="current.banner_stored || current.banner_url" type="button" class="inline-flex items-center gap-1.5 rounded-xl border border-slate-700 bg-slate-950 px-3 py-2 text-sm text-slate-400 transition hover:bg-slate-800 hover:text-red-300" :disabled="busy" @click="remove">
                            <TrashIcon class="h-4 w-4" /> Verwijderen
                        </button>
                    </div>
                </div>
            </div>

            <!-- Zoeken -->
            <form class="flex flex-wrap items-end gap-3" @submit.prevent="search">
                <label class="flex min-w-[200px] flex-1 flex-col gap-1 text-xs text-slate-400">
                    Zoekterm
                    <input v-model="query" type="text" :placeholder="defaultQuery" class="rounded-xl border border-slate-700 bg-slate-950 px-3 py-2 text-sm text-white placeholder:text-slate-600" />
                </label>
                <label v-if="showMame" class="flex w-44 flex-col gap-1 text-xs text-slate-400">
                    MAME-romnaam
                    <input v-model="mame" type="text" placeholder="bv. motocrgo" class="rounded-xl border border-slate-700 bg-slate-950 px-3 py-2 text-sm text-white placeholder:text-slate-600" />
                </label>
                <button type="submit" class="inline-flex items-center gap-1.5 rounded-xl bg-blue-600 px-4 py-2 text-sm font-semibold text-white transition hover:bg-blue-500 disabled:opacity-60" :disabled="searching">
                    <MagnifyingGlassIcon class="h-4 w-4" :class="searching ? 'animate-pulse' : ''" /> Zoeken
                </button>
            </form>

            <p v-if="!sgdbAvailable" class="rounded-xl border border-amber-500/30 bg-amber-500/10 px-3 py-2 text-xs text-amber-200">
                SteamGridDB staat niet aan. Zet <code>STEAMGRIDDB_API_KEY</code> in <code>.env</code> om ook banners voor TeknoParrot- en andere niet-Steam-games te vinden.
            </p>
            <p v-if="error" class="rounded-xl border border-red-500/30 bg-red-500/10 px-3 py-2 text-sm text-red-200">{{ error }}</p>

            <!-- Kandidaten -->
            <div v-if="searching && !candidates.length" class="py-6 text-center text-sm text-slate-500">Zoeken…</div>
            <div v-else-if="searched && !candidates.length" class="rounded-2xl border border-dashed border-slate-700 px-4 py-6 text-center text-sm text-slate-500">
                Niets gevonden. Probeer een andere zoekterm<template v-if="showMame">, de MAME-romnaam</template> of upload zelf een beeld.
            </div>
            <div v-else class="grid grid-cols-[repeat(auto-fill,minmax(180px,1fr))] gap-3">
                <button
                    v-for="c in candidates"
                    :key="c.url"
                    type="button"
                    class="group flex flex-col gap-1.5 text-left disabled:opacity-50"
                    :disabled="busy"
                    @click="pick(c)"
                >
                    <span class="relative block h-[82px] overflow-hidden rounded-xl border bg-slate-950 transition" :class="saving === c.url ? 'border-blue-500' : 'border-slate-800 group-hover:border-slate-500'">
                        <img :src="c.thumb" alt="" loading="lazy" class="h-full w-full object-contain" />
                        <span v-if="saving === c.url" class="absolute inset-0 flex items-center justify-center bg-slate-950/70 text-xs text-white">Opslaan…</span>
                    </span>
                    <span class="truncate text-xs text-slate-400">{{ c.label }}<template v-if="c.width"> · {{ c.width }}×{{ c.height }}</template></span>
                </button>
            </div>
        </div>
    </ModalDialog>
</template>

<script setup>
import { computed, reactive, ref, watch } from 'vue'
import { ArrowUpTrayIcon, MagnifyingGlassIcon, TrashIcon } from '@heroicons/vue/24/outline'
import ModalDialog from './ModalDialog.vue'
import { apiFetch } from '../../../shared/services/api'

const SOURCE_LABELS = {
    steam: 'Bron: Steam',
    steamgriddb: 'Bron: SteamGridDB',
    arcadedb: 'Bron: Arcade Database',
    upload: 'Eigen upload',
    removed: 'Verwijderd (wordt niet meer automatisch gezocht)',
}

const props = defineProps({
    open: { type: Boolean, default: false },
    name: { type: String, default: '' },
    // bv. /api/backoffice/games/12/banner
    endpoint: { type: String, required: true },
    // { banner_url, banner_ratio, banner_source, banner_stored }
    banner: { type: Object, default: () => ({}) },
    defaultQuery: { type: String, default: '' },
    showMame: { type: Boolean, default: false },
})
const emit = defineEmits(['close', 'updated'])

const current = reactive({ banner_url: null, banner_ratio: null, banner_source: null, banner_stored: false, banner_manual: false })
const query = ref('')
const mame = ref('')
const candidates = ref([])
const searching = ref(false)
const searched = ref(false)
const saving = ref(null)
const busy = ref(false)
const error = ref('')
const sgdbAvailable = ref(true)

const sourceLabel = computed(() => {
    if (current.banner_stored) return `${SOURCE_LABELS[current.banner_source] || 'Opgeslagen'}${current.banner_manual ? ' · zelf gekozen' : ' · automatisch'}`
    if (current.banner_url) return 'Steam (rechtstreeks, nog niet opgeslagen)'
    return SOURCE_LABELS[current.banner_source] || 'Nog geen banner'
})

watch(() => props.open, (open) => {
    if (!open) return
    Object.assign(current, props.banner || {})
    query.value = ''
    mame.value = props.banner?.mame_name || ''
    candidates.value = []
    searched.value = false
    error.value = ''
    search()
})

function fitClass(ratio) {
    return Number(ratio) > 2.6 ? 'object-contain bg-black' : 'object-cover'
}

function apply(payload) {
    Object.assign(current, {
        banner_url: payload.banner_url ?? null,
        banner_ratio: payload.banner_ratio ?? null,
        banner_source: payload.banner_source ?? null,
        banner_stored: !!payload.banner_stored,
        banner_manual: !!payload.banner_manual,
        banner_checked: !!payload.banner_checked,
    })
    emit('updated', { ...current, ...(payload.mame_name !== undefined ? { mame_name: payload.mame_name } : {}) })
}

async function search() {
    searching.value = true
    error.value = ''
    try {
        const params = new URLSearchParams()
        if (query.value.trim()) params.set('q', query.value.trim())
        if (mame.value.trim()) params.set('mame', mame.value.trim())
        const res = await apiFetch(`${props.endpoint}/candidates?${params}`)
        candidates.value = res.candidates ?? []
        sgdbAvailable.value = res.steamgriddb_available !== false
    } catch (e) {
        error.value = e.message || 'Zoeken mislukt.'
    } finally {
        searching.value = false
        searched.value = true
    }
}

async function pick(candidate) {
    busy.value = true
    saving.value = candidate.url
    error.value = ''
    try {
        const body = { url: candidate.url, source: candidate.source }
        if (candidate.source === 'arcadedb' && mame.value.trim()) body.mame = mame.value.trim().toLowerCase()
        apply(await apiFetch(props.endpoint, { method: 'POST', body: JSON.stringify(body) }))
    } catch (e) {
        error.value = e.message || 'Opslaan mislukt.'
    } finally {
        busy.value = false
        saving.value = null
    }
}

async function upload(event) {
    const file = event.target.files?.[0]
    event.target.value = ''
    if (!file) return
    busy.value = true
    error.value = ''
    try {
        const body = new FormData()
        body.append('file', file)
        apply(await apiFetch(props.endpoint, { method: 'POST', body }))
    } catch (e) {
        error.value = e.data?.errors?.file?.[0] || e.message || 'Uploaden mislukt.'
    } finally {
        busy.value = false
    }
}

async function remove() {
    if (!window.confirm('Banner verwijderen?')) return
    busy.value = true
    error.value = ''
    try {
        apply(await apiFetch(props.endpoint, { method: 'DELETE' }))
    } catch (e) {
        error.value = e.message || 'Verwijderen mislukt.'
    } finally {
        busy.value = false
    }
}
</script>
