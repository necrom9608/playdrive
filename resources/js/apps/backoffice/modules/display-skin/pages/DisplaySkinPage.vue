<template>
    <div class="space-y-6">
        <div class="flex flex-wrap items-start justify-between gap-4">
            <div>
                <h1 class="text-3xl font-bold text-white">Klantenscherm</h1>
                <p class="mt-2 max-w-2xl text-slate-400">
                    Het tweede scherm aan de kassa: skin, teksten en de filmpjes die spelen als er geen bestelling openstaat.
                    Wijzigingen staan binnen 5 minuten op het scherm (of meteen na herstarten van de kassa-app).
                </p>
            </div>
            <button
                type="button"
                :disabled="saving || loading"
                class="rounded-2xl bg-blue-600 px-4 py-3 text-sm font-semibold text-white transition hover:bg-blue-500 disabled:opacity-50"
                @click="save"
            >
                {{ saving ? 'Opslaan...' : 'Opslaan' }}
            </button>
        </div>

        <div v-if="error" class="rounded-2xl border border-rose-500/30 bg-rose-500/10 px-5 py-4 text-sm text-rose-200">{{ error }}</div>
        <div v-if="success" class="rounded-2xl border border-emerald-500/30 bg-emerald-500/10 px-5 py-4 text-sm text-emerald-200">{{ success }}</div>

        <div v-if="loading" class="py-8 text-center text-sm text-slate-400">Laden...</div>

        <template v-else>
            <!-- Skin -->
            <section class="rounded-3xl border border-slate-800 bg-slate-900/80 p-6 shadow-xl shadow-slate-950/20">
                <h2 class="text-base font-semibold text-white">Skin</h2>
                <div class="mt-4 grid gap-3 sm:grid-cols-2">
                    <button
                        v-for="option in skins"
                        :key="option.key"
                        type="button"
                        class="flex items-start gap-3 rounded-2xl border p-4 text-left transition"
                        :class="form.skin === option.key ? 'border-blue-500 bg-blue-500/10' : 'border-slate-800 bg-slate-950/40 hover:border-slate-700'"
                        @click="form.skin = option.key"
                    >
                        <span class="mt-0.5 flex h-5 w-5 shrink-0 items-center justify-center rounded-full border" :class="form.skin === option.key ? 'border-blue-400' : 'border-slate-600'">
                            <span v-if="form.skin === option.key" class="h-2.5 w-2.5 rounded-full bg-blue-400"></span>
                        </span>
                        <span>
                            <span class="block font-semibold text-white">{{ option.label }}</span>
                            <span class="mt-1 block text-sm text-slate-400">{{ skinDescription(option.key) }}</span>
                        </span>
                    </button>
                </div>
            </section>

            <template v-if="form.skin === 'gameinn'">
                <!-- Teksten -->
                <section class="rounded-3xl border border-slate-800 bg-slate-900/80 p-6 shadow-xl shadow-slate-950/20">
                    <h2 class="text-base font-semibold text-white">Teksten en effecten</h2>
                    <p class="mt-1 text-sm text-slate-400">Leeg laten = standaardtekst (grijs weergegeven).</p>

                    <div class="mt-5 grid gap-4 md:grid-cols-2">
                        <label class="block">
                            <span class="mb-1.5 block text-sm font-medium text-slate-300">Titel, regel 1 (wit)</span>
                            <input v-model="form.title_line_1" maxlength="60" :placeholder="defaults.title_line_1" class="input" />
                        </label>
                        <label class="block">
                            <span class="mb-1.5 block text-sm font-medium text-slate-300">Titel, regel 2 (oranje)</span>
                            <input v-model="form.title_line_2" maxlength="60" :placeholder="defaults.title_line_2" class="input" />
                        </label>
                        <label class="block md:col-span-2">
                            <span class="mb-1.5 block text-sm font-medium text-slate-300">Info naast de openingsuren</span>
                            <input v-model="form.info_text" maxlength="120" :placeholder="defaults.info_text" class="input" />
                            <span class="mt-1 block text-xs text-slate-500">Tussen *sterretjes* = oranje en vet.</span>
                        </label>
                    </div>

                    <div class="mt-6 grid gap-6 md:grid-cols-2">
                        <TickerEditor v-model="form.standby_ticker" label="Lopende band op de standby" :placeholder-items="defaults.standby_ticker" />
                        <TickerEditor v-model="form.order_ticker" label="Lopende band bij de bestelling" :placeholder-items="defaults.order_ticker" />
                    </div>

                    <div class="mt-6 flex flex-wrap items-center gap-8">
                        <div class="flex items-center gap-3">
                            <ToggleSwitch v-model="form.show_hours" />
                            <span class="text-sm text-slate-300">Openingsuren van vandaag tonen</span>
                        </div>
                        <div class="flex items-center gap-3">
                            <span class="text-sm text-slate-300">Retro-effect op de filmpjes</span>
                            <div class="flex gap-1 rounded-xl border border-slate-800 bg-slate-950/60 p-1">
                                <button
                                    v-for="level in ['uit', 'zacht', 'sterk']"
                                    :key="level"
                                    type="button"
                                    class="rounded-lg px-3 py-1.5 text-sm capitalize transition"
                                    :class="form.crt === level ? 'bg-slate-700 text-white' : 'text-slate-400 hover:text-slate-200'"
                                    @click="form.crt = level"
                                >{{ level }}</button>
                            </div>
                        </div>
                    </div>
                </section>

                <!-- Filmpjes -->
                <section class="rounded-3xl border border-slate-800 bg-slate-900/80 p-6 shadow-xl shadow-slate-950/20">
                    <div class="flex flex-wrap items-start justify-between gap-4">
                        <div>
                            <h2 class="text-base font-semibold text-white">Filmpjes</h2>
                            <p class="mt-1 max-w-2xl text-sm text-slate-400">
                                Staande filmpjes (9:16) spelen na elkaar, zonder geluid. De server maakt er een licht bestand van (720×1280).
                                Kies per filmpje de zone: dat icoon licht dan oranje op in het embleem.
                            </p>
                        </div>
                        <label class="inline-flex cursor-pointer items-center gap-2 rounded-2xl border border-slate-700 bg-slate-800 px-4 py-2.5 text-sm font-semibold text-white transition hover:bg-slate-700">
                            <ArrowUpTrayIcon class="h-4 w-4" />
                            Filmpjes toevoegen
                            <input type="file" accept="video/*,.mp4,.mov,.m4v,.webm,.mkv" multiple class="hidden" @change="onFiles" />
                        </label>
                    </div>

                    <div v-if="!videos.length && !uploads.length" class="mt-5 rounded-2xl border border-dashed border-slate-700 px-5 py-8 text-center text-sm text-slate-400">
                        Nog geen filmpjes. Zonder filmpjes toont het scherm het embleem op een donkere achtergrond.
                    </div>

                    <ul class="mt-5 space-y-3">
                        <li v-for="up in uploads" :key="up.key" class="flex items-center gap-4 rounded-2xl border border-slate-800 bg-slate-950/40 p-3">
                            <div class="flex h-20 w-12 shrink-0 items-center justify-center rounded-lg bg-slate-800 text-slate-500"><FilmIcon class="h-5 w-5" /></div>
                            <div class="min-w-0 flex-1">
                                <div class="truncate text-sm font-medium text-white">{{ up.name }}</div>
                                <div class="mt-2 h-1.5 overflow-hidden rounded-full bg-slate-800">
                                    <div class="h-full rounded-full bg-blue-500 transition-all" :style="{ width: `${Math.round(up.progress * 100)}%` }"></div>
                                </div>
                                <div class="mt-1 text-xs" :class="up.error ? 'text-rose-300' : 'text-slate-500'">
                                    {{ up.error || `Uploaden… ${Math.round(up.progress * 100)}% van ${mb(up.size)}` }}
                                </div>
                            </div>
                            <button v-if="up.error" type="button" class="icon-btn" title="Verbergen" @click="dropUpload(up)"><XMarkIcon class="h-4 w-4" /></button>
                        </li>

                        <li
                            v-for="(video, index) in videos"
                            :key="video.id"
                            class="flex items-center gap-4 rounded-2xl border border-slate-800 bg-slate-950/40 p-3"
                            :class="{ 'opacity-60': !video.is_active }"
                        >
                            <div class="relative h-20 w-12 shrink-0 overflow-hidden rounded-lg bg-slate-800">
                                <video
                                    v-if="video.preview_url"
                                    :src="video.preview_url"
                                    muted
                                    playsinline
                                    loop
                                    preload="metadata"
                                    class="h-full w-full object-cover"
                                    @mouseenter="e => e.target.play().catch(() => {})"
                                    @mouseleave="e => e.target.pause()"
                                ></video>
                                <div v-else class="flex h-full w-full items-center justify-center text-slate-500"><FilmIcon class="h-5 w-5" /></div>
                            </div>

                            <div class="grid min-w-0 flex-1 gap-2 md:grid-cols-[minmax(0,1fr)_180px]">
                                <input
                                    v-model="video.title"
                                    maxlength="60"
                                    placeholder="Label (bv. Racing)"
                                    class="input"
                                    @change="saveVideo(video)"
                                />
                                <select v-model="video.zone" class="input" @change="saveVideo(video)">
                                    <option :value="null">Geen zone</option>
                                    <option v-for="z in zones" :key="z" :value="z">{{ zoneLabel(z) }}</option>
                                </select>
                                <div class="text-xs md:col-span-2" :class="statusClass(video)">
                                    {{ statusText(video) }}
                                </div>
                            </div>

                            <div class="flex shrink-0 items-center gap-1">
                                <button v-if="video.status === 'failed'" type="button" class="icon-btn" title="Opnieuw verwerken" @click="retry(video)"><ArrowPathIcon class="h-4 w-4" /></button>
                                <ToggleSwitch :model-value="video.is_active" title="Tonen op het scherm" @update:model-value="v => { video.is_active = v; saveVideo(video) }" />
                                <button type="button" class="icon-btn" :disabled="index === 0" title="Hoger" @click="move(index, -1)"><ChevronUpIcon class="h-4 w-4" /></button>
                                <button type="button" class="icon-btn" :disabled="index === videos.length - 1" title="Lager" @click="move(index, 1)"><ChevronDownIcon class="h-4 w-4" /></button>
                                <button type="button" class="icon-btn hover:!text-rose-300" title="Verwijderen" @click="remove(video)"><TrashIcon class="h-4 w-4" /></button>
                            </div>
                        </li>
                    </ul>
                </section>
            </template>
        </template>
    </div>
</template>

<script setup>
import { computed, onBeforeUnmount, onMounted, reactive, ref } from 'vue'
import {
    ArrowPathIcon,
    ArrowUpTrayIcon,
    ChevronDownIcon,
    ChevronUpIcon,
    FilmIcon,
    TrashIcon,
    XMarkIcon,
} from '@heroicons/vue/24/outline'
import ToggleSwitch from '../../../components/ToggleSwitch.vue'
import TickerEditor from '../components/TickerEditor.vue'
import {
    deleteDisplayVideo,
    fetchDisplaySkin,
    reorderDisplayVideos,
    retryDisplayVideo,
    saveDisplaySkin,
    updateDisplayVideo,
    uploadDisplayVideo,
} from '../services/displaySkinApi'

const ZONE_LABELS = {
    arcade: 'Arcade', vr: 'VR', racing: 'Racing', rhythm: 'Rhythm',
    consoles: 'Consoles / couch', esports: 'E-sports', boardgames: 'Boardgames', retro: 'Retro',
}

const loading = ref(true)
const saving = ref(false)
const error = ref('')
const success = ref('')
const skins = ref([])
const allDefaults = ref({})
const zones = ref([])
const videos = ref([])
const uploads = ref([])
const form = reactive({
    skin: 'standaard',
    title_line_1: '',
    title_line_2: '',
    info_text: '',
    standby_ticker: [],
    order_ticker: [],
    show_hours: true,
    crt: 'zacht',
})

const defaults = computed(() => allDefaults.value?.[form.skin] ?? {})

let pollTimer = null
let uploadSeq = 0

function apply(data) {
    skins.value = data.skins ?? []
    allDefaults.value = data.defaults ?? {}
    zones.value = data.zones ?? []
    videos.value = (data.videos ?? []).map(v => ({ ...v }))
    Object.assign(form, {
        ...data.settings,
        title_line_1: data.settings?.title_line_1 ?? '',
        title_line_2: data.settings?.title_line_2 ?? '',
        info_text: data.settings?.info_text ?? '',
        standby_ticker: [...(data.settings?.standby_ticker ?? [])],
        order_ticker: [...(data.settings?.order_ticker ?? [])],
    })
    schedulePoll()
}

async function load() {
    loading.value = true
    error.value = ''
    try {
        apply(await fetchDisplaySkin())
    } catch (e) {
        error.value = e.message || 'Laden mislukt.'
    } finally {
        loading.value = false
    }
}

async function save() {
    saving.value = true
    error.value = ''
    success.value = ''
    try {
        apply(await saveDisplaySkin({ ...form }))
        success.value = 'Opgeslagen. Het klantenscherm neemt het binnen 5 minuten over.'
        window.setTimeout(() => { success.value = '' }, 5000)
    } catch (e) {
        error.value = e.message || 'Opslaan mislukt.'
    } finally {
        saving.value = false
    }
}

function skinDescription(key) {
    return {
        standaard: 'Het gewone PlayDrive-scherm met je logo.',
        gameinn: 'Zoals game-inn.be: staande filmpjes met retro-effect, het embleem met de acht zones en een oranje lopende band. Bij een bestelling wordt het filmpje een strook bovenaan.',
    }[key] || ''
}

function zoneLabel(z) {
    return ZONE_LABELS[z] || z
}

// ── Filmpjes ─────────────────────────────────────────────────────────
/** "RACING V.mp4" → { title: 'Racing', zone: 'racing' } */
function guessMeta(fileName) {
    const base = fileName.replace(/\.[^.]+$/, '').replace(/[\s_-]+v$/i, '').replace(/[_]+/g, ' ').trim()
    const lower = base.toLowerCase()
    const map = [
        ['arcade', 'arcade'], ['vr', 'vr'], ['racing', 'racing'], ['race', 'racing'], ['rhythm', 'rhythm'],
        ['couch', 'consoles'], ['console', 'consoles'], ['e-sports', 'esports'], ['esports', 'esports'],
        ['board', 'boardgames'], ['retro', 'retro'],
    ]
    const hit = map.find(([needle]) => new RegExp(`(^|[^a-z])${needle.replace('-', '\\-')}`).test(lower))
    const title = base.length <= 24 ? base.charAt(0).toUpperCase() + base.slice(1).toLowerCase() : ''
    return { title, zone: hit ? hit[1] : null }
}

async function onFiles(event) {
    const files = [...(event.target.files ?? [])]
    event.target.value = ''
    for (const file of files) {
        const up = reactive({ key: ++uploadSeq, name: file.name, size: file.size, progress: 0, error: '' })
        uploads.value.push(up)
        try {
            const video = await uploadDisplayVideo(file, guessMeta(file.name), (f) => { up.progress = f })
            uploads.value = uploads.value.filter(u => u !== up)
            videos.value.push({ ...video })
            schedulePoll()
        } catch (e) {
            up.error = e.message || 'Upload mislukt.'
        }
    }
}

function dropUpload(up) {
    uploads.value = uploads.value.filter(u => u !== up)
}

async function saveVideo(video) {
    try {
        const res = await updateDisplayVideo(video.id, { title: video.title, zone: video.zone, is_active: video.is_active })
        Object.assign(video, res.video)
    } catch (e) {
        error.value = e.message || 'Opslaan mislukt.'
    }
}

async function move(index, delta) {
    const list = [...videos.value]
    const [item] = list.splice(index, 1)
    list.splice(index + delta, 0, item)
    videos.value = list
    try {
        apply(await reorderDisplayVideos(list.map(v => v.id)))
    } catch (e) {
        error.value = e.message || 'Volgorde opslaan mislukt.'
    }
}

async function remove(video) {
    if (!window.confirm(`"${video.title || video.original_name}" verwijderen?`)) return
    try {
        await deleteDisplayVideo(video.id)
        videos.value = videos.value.filter(v => v.id !== video.id)
    } catch (e) {
        error.value = e.message || 'Verwijderen mislukt.'
    }
}

async function retry(video) {
    try {
        Object.assign(video, (await retryDisplayVideo(video.id)).video)
        schedulePoll()
    } catch (e) {
        error.value = e.message || 'Opnieuw verwerken mislukt.'
    }
}

function mb(bytes) {
    return `${(Number(bytes || 0) / 1048576).toFixed(1).replace('.', ',')} MB`
}

function statusText(video) {
    switch (video.status) {
        case 'uploading': return 'Upload onderbroken: verwijder dit filmpje en upload het opnieuw.'
        case 'processing': return 'Wordt verwerkt… (meestal minder dan een minuut)'
        case 'failed': return video.note || 'Verwerken mislukt.'
        default: {
            const parts = [mb(video.size_bytes)]
            if (video.duration_seconds) parts.push(`${Math.round(video.duration_seconds)} s`)
            if (video.note) parts.push(video.note)
            if (!video.is_active) parts.push('niet getoond')
            return parts.join(' · ')
        }
    }
}

function statusClass(video) {
    if (video.status === 'failed' || video.status === 'uploading') return 'text-rose-300'
    if (video.status === 'processing') return 'text-amber-300'
    return video.note ? 'text-amber-300/80' : 'text-slate-500'
}

/** Zolang er iets verwerkt wordt: elke 4 s de status ophalen. */
function schedulePoll() {
    window.clearTimeout(pollTimer)
    if (!videos.value.some(v => v.status === 'processing')) return
    pollTimer = window.setTimeout(async () => {
        try {
            const data = await fetchDisplaySkin()
            const fresh = new Map((data.videos ?? []).map(v => [v.id, v]))
            videos.value.forEach(v => {
                const f = fresh.get(v.id)
                if (f && f.status !== v.status) Object.assign(v, f)
            })
        } catch {
            // volgende poging
        }
        schedulePoll()
    }, 4000)
}

onMounted(load)
onBeforeUnmount(() => window.clearTimeout(pollTimer))
</script>

<style scoped>
.input {
    width: 100%;
    border-radius: 0.875rem;
    border: 1px solid rgb(30 41 59);
    background: rgb(2 6 23 / 0.6);
    padding: 0.6rem 0.85rem;
    font-size: 0.875rem;
    color: white;
    outline: none;
}
.input:focus { border-color: rgb(59 130 246 / 0.6); }
.input::placeholder { color: rgb(100 116 139); }
.icon-btn {
    display: inline-flex; height: 2.25rem; width: 2.25rem; align-items: center; justify-content: center;
    border-radius: 0.75rem; color: rgb(148 163 184); transition: background-color .15s, color .15s;
}
.icon-btn:hover:not(:disabled) { background: rgb(30 41 59); color: white; }
.icon-btn:disabled { opacity: .3; cursor: default; }
</style>
