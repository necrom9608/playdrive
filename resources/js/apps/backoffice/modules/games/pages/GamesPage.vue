<template>
    <div class="space-y-6">
        <div class="flex flex-wrap items-start justify-between gap-4">
            <div>
                <h1 class="text-3xl font-bold text-white">Games</h1>
                <p class="mt-2 text-slate-400">
                    Alle games die Playscope op jullie PC's vond. Deel ze in categorieën in en duid het aantal spelers aan; Playscope toont dat als filters.
                </p>
            </div>

            <div class="flex flex-wrap gap-3">
                <button
                    v-if="steamCount"
                    type="button"
                    :disabled="meta.busy"
                    :title="pendingMeta ? 'Categorieën en spelers ophalen via Steam, SteamSpy en PCGamingWiki. Enkel lege velden worden ingevuld.' : 'Alles opnieuw ophalen. Automatisch ingevulde waarden worden bijgewerkt, wat je zelf aanduidde blijft staan.'"
                    class="inline-flex items-center gap-2 rounded-2xl border px-4 py-3 text-sm font-semibold transition disabled:opacity-70"
                    :class="pendingMeta ? 'border-orange-500/50 bg-orange-500/15 text-orange-100 hover:bg-orange-500/25' : 'border-slate-700 bg-slate-900 text-slate-200 hover:bg-slate-800'"
                    @click="fetchMeta(!pendingMeta)"
                >
                    <SparklesIcon class="h-5 w-5" :class="{ 'animate-pulse': meta.busy }" />
                    <template v-if="meta.busy">Ophalen… {{ meta.done }}/{{ meta.total }}</template>
                    <template v-else-if="pendingMeta">Gegevens ophalen ({{ pendingMeta }})</template>
                    <template v-else>Opnieuw ophalen</template>
                </button>
                <button
                    v-if="pendingBanners || bannerFetch.busy"
                    type="button"
                    :disabled="bannerFetch.busy"
                    title="Banners voor de Zaal-tegels zoeken (Steam, SteamGridDB). Eigen uploads blijven staan."
                    class="inline-flex items-center gap-2 rounded-2xl border border-sky-500/40 bg-sky-500/10 px-4 py-3 text-sm font-semibold text-sky-100 transition hover:bg-sky-500/20 disabled:opacity-70"
                    @click="fetchBanners"
                >
                    <PhotoIcon class="h-5 w-5" :class="{ 'animate-pulse': bannerFetch.busy }" />
                    <template v-if="bannerFetch.busy">Banners… {{ bannerFetch.done }}/{{ bannerFetch.total }}</template>
                    <template v-else>Banners ophalen ({{ pendingBanners }})</template>
                </button>
                <button type="button" class="inline-flex items-center gap-2 rounded-2xl border border-slate-700 bg-slate-900 px-4 py-3 text-sm font-semibold text-slate-200 transition hover:bg-slate-800" @click="load">
                    <ArrowPathIcon class="h-5 w-5" /> Vernieuwen
                </button>
                <button type="button" class="inline-flex items-center gap-2 rounded-2xl bg-blue-600 px-4 py-3 text-sm font-semibold text-white transition hover:bg-blue-500" @click="startNewCategory">
                    <PlusIcon class="h-5 w-5" /> Categorie
                </button>
            </div>
        </div>

        <div class="inline-flex rounded-2xl border border-slate-800 bg-slate-900 p-1">
            <button
                v-for="t in tabs"
                :key="t.key"
                type="button"
                class="rounded-xl px-4 py-2 text-sm font-semibold transition"
                :class="tab === t.key ? 'bg-slate-700 text-white' : 'text-slate-400 hover:text-white'"
                @click="tab = t.key"
            >
                {{ t.label }} <span class="ml-1 text-slate-500">{{ t.count }}</span>
            </button>
        </div>

        <div v-if="error" class="rounded-2xl border border-red-500/30 bg-red-500/10 px-5 py-4 text-sm text-red-200">{{ error }}</div>

        <!-- ═══════════════ Games ═══════════════ -->
        <template v-if="tab === 'games'">
            <div class="flex flex-wrap items-center gap-2">
                <div class="relative min-w-[220px] flex-1">
                    <MagnifyingGlassIcon class="pointer-events-none absolute left-4 top-1/2 h-4 w-4 -translate-y-1/2 text-slate-500" />
                    <input v-model="search" type="search" placeholder="Zoek een game…" class="input w-full !pl-10" />
                </div>
                <button
                    v-for="f in quickFilters"
                    :key="f.key"
                    type="button"
                    class="rounded-full border px-3.5 py-2 text-xs font-semibold transition"
                    :class="filter === f.key ? f.on : 'border-slate-800 bg-slate-900 text-slate-400 hover:text-white'"
                    @click="filter = filter === f.key ? 'all' : f.key"
                >
                    {{ f.label }} · {{ f.count }}
                </button>
                <select v-model="categoryFilter" class="input !w-auto !py-2 text-xs">
                    <option :value="null">Alle categorieën</option>
                    <option v-for="c in categories" :key="c.id" :value="c.id">{{ c.name }}</option>
                </select>
            </div>

            <div v-if="counts.new" class="flex flex-wrap items-center gap-3 rounded-2xl border border-amber-500/30 bg-amber-500/10 px-5 py-3 text-sm text-amber-100">
                <ExclamationTriangleIcon class="h-5 w-5 shrink-0 text-amber-300" />
                <span class="flex-1"><b class="text-white">{{ counts.new }} {{ counts.new === 1 ? 'nieuwe game' : 'nieuwe games' }}</b> om in te delen. Tot dan staan ze in Playscope enkel onder “Alles”.</span>
                <button type="button" class="rounded-xl border border-amber-500/40 px-3 py-1.5 text-xs font-semibold text-amber-100 transition hover:bg-amber-500/10" @click="markAllReviewed">
                    Alles als bekeken markeren
                </button>
            </div>

            <div v-if="!games.length && !loading" class="rounded-3xl border border-dashed border-slate-800 px-6 py-12 text-center">
                <PuzzlePieceIcon class="mx-auto h-10 w-10 text-slate-600" />
                <p class="mt-3 text-slate-300">Nog geen games.</p>
                <p class="mt-1 text-sm text-slate-500">Koppel een Playscope-PC bij Speelplekken → Toestellen. Die geeft zijn geïnstalleerde games dan vanzelf door.</p>
            </div>

            <div v-else-if="!visibleGames.length && !loading" class="rounded-3xl border border-dashed border-slate-800 px-6 py-10 text-center text-sm text-slate-500">
                Geen games voor deze filter.
            </div>

            <div v-else class="overflow-hidden rounded-3xl border border-slate-800 bg-slate-900/80">
                <div class="hidden grid-cols-[150px_minmax(0,1fr)_minmax(0,1.4fr)_170px_minmax(0,0.9fr)_92px] gap-4 border-b border-slate-800 px-5 py-3 text-xs font-semibold uppercase tracking-wider text-slate-500 xl:grid">
                    <span>Game</span><span></span><span>Categorieën</span><span>Spelers</span><span>Staat op</span><span class="text-right">Kijker · zichtbaar</span>
                </div>

                <div
                    v-for="g in visibleGames"
                    :key="g.id"
                    class="grid grid-cols-1 gap-4 border-t border-slate-800 px-5 py-4 first:border-t-0 xl:grid-cols-[150px_minmax(0,1fr)_minmax(0,1.4fr)_170px_minmax(0,0.9fr)_92px] xl:items-center"
                    :class="g.is_hidden ? 'opacity-50' : ''"
                >
                    <!-- Cover -->
                    <button
                        type="button"
                        class="group relative h-14 w-[150px] overflow-hidden rounded-xl border border-slate-800 transition hover:border-slate-500"
                        :style="{ background: fallbackBg(g) }"
                        :title="g.banner_stored ? 'Banner wijzigen' : 'Banner kiezen voor de Zaal'"
                        @click="openBanner(g)"
                    >
                        <img
                            v-if="(g.banner_url || g.cover_url) && !brokenCovers.has(g.id)"
                            :src="g.banner_url || g.cover_url"
                            alt=""
                            loading="lazy"
                            class="h-full w-full"
                            :class="Number(g.banner_ratio) > 2.6 ? 'object-contain bg-black' : 'object-cover'"
                            @error="brokenCovers.add(g.id)"
                        />
                        <span class="absolute inset-x-0 bottom-0 flex items-center justify-center gap-1 bg-slate-950/80 py-0.5 text-[10px] font-semibold text-slate-200 opacity-0 transition group-hover:opacity-100">
                            <PhotoIcon class="h-3 w-3" /> Banner
                        </span>
                    </button>

                    <!-- Naam -->
                    <div class="min-w-0">
                        <div class="flex flex-wrap items-center gap-2">
                            <span class="truncate font-semibold text-white">{{ g.name }}</span>
                            <span v-if="g.is_new" class="rounded-full border border-amber-500/50 px-2 py-0.5 text-[10px] font-bold tracking-wider text-amber-300">NIEUW</span>
                        </div>
                        <div class="mt-0.5 text-xs text-slate-500">
                            {{ g.source_label }} · {{ g.external_id }}<template v-if="g.is_new && g.first_seen_at"> · {{ relativeDate(g.first_seen_at) }}</template><template v-if="g.is_hidden"> · verborgen</template><span v-if="g.metadata_source" class="text-orange-300/80" :title="g.metadata_source"> · automatisch ingevuld</span><span v-else-if="g.metadata_status === 'not_found'" class="text-slate-600"> · geen gegevens gevonden</span>
                        </div>
                    </div>

                    <!-- Categorieën -->
                    <div class="flex flex-wrap gap-1.5">
                        <button
                            v-for="c in categories"
                            :key="c.id"
                            type="button"
                            class="rounded-full border px-2.5 py-1 text-xs font-semibold transition"
                            :class="g.categories.includes(c.id) ? 'border-blue-500/50 bg-blue-600/15 text-blue-100' : 'border-dashed border-slate-700 text-slate-500 hover:border-slate-500 hover:text-slate-300'"
                            @click="toggleCategory(g, c.id)"
                        >
                            {{ c.name }}
                        </button>
                        <button v-if="!categories.length" type="button" class="text-xs text-slate-500 underline-offset-2 hover:text-slate-300 hover:underline" @click="startNewCategory">
                            Eerst een categorie maken
                        </button>
                    </div>

                    <!-- Spelers -->
                    <div class="space-y-1.5">
                        <div class="text-[10px] font-semibold uppercase tracking-wider text-slate-600">
                            Op één scherm (max)
                            <span v-if="g.auto_fields?.local_players_max === 'steam'" class="ml-1 normal-case tracking-normal text-amber-400" title="Steam zegt enkel 'split screen' zonder aantal: nakijken">· gok</span>
                            <span v-else-if="g.auto_fields?.local_players_max" class="ml-1 normal-case tracking-normal text-slate-500">· {{ g.auto_fields.local_players_max === 'igdb' ? 'IGDB' : 'PCGamingWiki' }}</span>
                        </div>
                        <div class="inline-flex overflow-hidden rounded-lg border border-slate-700">
                            <button
                                v-for="(n, i) in playerOptions"
                                :key="n"
                                type="button"
                                class="px-2.5 py-1 text-xs font-semibold transition"
                                :class="[i ? 'border-l border-slate-700' : '', g.local_players_max === n ? 'bg-blue-600/25 text-blue-100' : 'text-slate-500 hover:text-slate-200']"
                                @click="save(g, { local_players_max: g.local_players_max === n ? null : n })"
                            >
                                {{ n }}
                            </button>
                        </div>
                        <button
                            type="button"
                            class="block text-xs font-semibold transition"
                            :class="g.lan_multiplayer === true ? 'text-emerald-300' : g.lan_multiplayer === false ? 'text-slate-500' : 'text-slate-600 hover:text-slate-400'"
                            @click="save(g, { lan_multiplayer: g.lan_multiplayer !== true })"
                        >
                            {{ g.lan_multiplayer === true ? '✓ Samen over LAN/online' : g.lan_multiplayer === false ? '✕ Geen LAN/online' : '? LAN/online onbekend' }}
                        </button>
                    </div>

                    <!-- Staat op -->
                    <div class="flex flex-wrap gap-1">
                        <span v-for="w in g.installed_on.slice(0, 3)" :key="w" class="rounded-lg border border-slate-700 bg-slate-950 px-2 py-0.5 text-xs text-slate-300">{{ w }}</span>
                        <span v-if="g.installed_on.length > 3" class="px-1 py-0.5 text-xs text-slate-500" :title="g.installed_on.slice(3).join(', ')">+{{ g.installed_on.length - 3 }}</span>
                        <span v-if="!g.installed_on.length" class="text-xs text-slate-600">Nergens meer geïnstalleerd</span>
                    </div>

                    <!-- Kijker / zichtbaar / verwijderen -->
                    <div class="flex justify-start gap-2 xl:justify-end">
                        <button
                            type="button"
                            class="flex h-9 w-9 items-center justify-center rounded-xl border transition"
                            :class="g.is_featured ? 'border-amber-500/50 bg-amber-500/10 text-amber-300' : 'border-slate-700 bg-slate-950 text-slate-500 hover:text-slate-200'"
                            :title="g.is_featured ? 'Uitgelicht in Playscope' : 'In de kijker zetten'"
                            @click="save(g, { is_featured: !g.is_featured })"
                        >
                            <component :is="g.is_featured ? StarSolidIcon : StarIcon" class="h-4 w-4" />
                        </button>
                        <button
                            type="button"
                            class="flex h-9 w-9 items-center justify-center rounded-xl border transition"
                            :class="g.is_hidden ? 'border-red-500/50 bg-red-500/10 text-red-300' : 'border-slate-700 bg-slate-950 text-slate-500 hover:text-slate-200'"
                            :title="g.is_hidden ? 'Verborgen in Playscope — klik om te tonen' : 'Verbergen in Playscope'"
                            @click="save(g, { is_hidden: !g.is_hidden })"
                        >
                            <component :is="g.is_hidden ? EyeSlashIcon : EyeIcon" class="h-4 w-4" />
                        </button>
                        <button
                            v-if="!g.installed_on.length"
                            type="button"
                            class="flex h-9 w-9 items-center justify-center rounded-xl border border-slate-700 bg-slate-950 text-slate-500 transition hover:border-red-500/50 hover:text-red-300"
                            title="Uit de catalogus verwijderen"
                            @click="removeGame(g)"
                        >
                            <TrashIcon class="h-4 w-4" />
                        </button>
                    </div>
                </div>
            </div>
        </template>

        <!-- ═══════════════ Categorieën ═══════════════ -->
        <template v-else>
            <p class="text-sm text-slate-500">De volgorde hier is de volgorde van de filterknoppen in Playscope. Een categorie zonder games op een PC wordt daar niet getoond.</p>

            <div class="space-y-3">
                <div
                    v-for="(c, i) in categories"
                    :key="c.id"
                    class="flex flex-wrap items-center gap-3 rounded-3xl border border-slate-800 bg-slate-900/80 px-4 py-3"
                >
                    <div class="flex flex-col">
                        <button type="button" class="rounded p-0.5 text-slate-500 transition hover:text-white disabled:opacity-20" :disabled="i === 0" title="Hoger" @click="moveCategory(i, -1)">
                            <ChevronUpIcon class="h-4 w-4" />
                        </button>
                        <button type="button" class="rounded p-0.5 text-slate-500 transition hover:text-white disabled:opacity-20" :disabled="i === categories.length - 1" title="Lager" @click="moveCategory(i, 1)">
                            <ChevronDownIcon class="h-4 w-4" />
                        </button>
                    </div>
                    <input
                        v-model="c.name"
                        type="text"
                        maxlength="60"
                        class="input !w-56"
                        @change="saveCategory(c)"
                        @keydown.enter="$event.target.blur()"
                    />
                    <div class="flex gap-1.5">
                        <button
                            v-for="col in COLORS"
                            :key="col"
                            type="button"
                            class="h-6 w-6 rounded-full border-2 transition"
                            :class="c.color === col ? 'border-white' : 'border-transparent opacity-60 hover:opacity-100'"
                            :style="{ background: col }"
                            :title="col"
                            @click="c.color = col; saveCategory(c)"
                        />
                    </div>
                    <span class="ml-auto text-sm text-slate-500">{{ c.games_count }} {{ c.games_count === 1 ? 'game' : 'games' }}</span>
                    <button type="button" class="flex h-9 w-9 items-center justify-center rounded-xl border border-slate-700 bg-slate-950 text-slate-500 transition hover:border-red-500/50 hover:text-red-300" title="Verwijderen" @click="removeCategory(c)">
                        <TrashIcon class="h-4 w-4" />
                    </button>
                </div>

                <form class="flex flex-wrap items-center gap-3 rounded-3xl border border-dashed border-slate-800 px-4 py-3" @submit.prevent="addCategory">
                    <PlusIcon class="h-5 w-5 text-slate-500" />
                    <input ref="newCategoryInput" v-model="newCategory" type="text" maxlength="60" placeholder="Nieuwe categorie, bv. Party, Kids, Racing" class="input min-w-[220px] flex-1" />
                    <button type="submit" class="rounded-2xl bg-blue-600 px-4 py-2.5 text-sm font-semibold text-white transition hover:bg-blue-500 disabled:opacity-50" :disabled="!newCategory.trim()">
                        Toevoegen
                    </button>
                </form>
            </div>
        </template>
        <BannerPickerModal
            :open="banner.open"
            :name="banner.game?.name || ''"
            :endpoint="banner.game ? `/api/backoffice/games/${banner.game.id}/banner` : '/api/backoffice/games/0/banner'"
            :banner="banner.game || {}"
            :default-query="banner.game?.name || ''"
            :show-mame="banner.game?.source === 'teknoparrot'"
            @close="banner.open = false"
            @updated="onBannerUpdated"
        />
    </div>
</template>

<script setup>
import { computed, nextTick, onBeforeUnmount, onMounted, reactive, ref } from 'vue'
import {
    ArrowPathIcon,
    ChevronDownIcon,
    ChevronUpIcon,
    ExclamationTriangleIcon,
    EyeIcon,
    EyeSlashIcon,
    MagnifyingGlassIcon,
    PhotoIcon,
    PlusIcon,
    PuzzlePieceIcon,
    SparklesIcon,
    StarIcon,
    TrashIcon,
} from '@heroicons/vue/24/outline'
import { StarIcon as StarSolidIcon } from '@heroicons/vue/24/solid'
import BannerPickerModal from '../../../components/BannerPickerModal.vue'
import {
    announceNewGamesCount,
    createGameCategory,
    deleteGame,
    deleteGameCategory,
    fetchGameBanners,
    fetchGameMetadata,
    fetchGames,
    markAllGamesReviewed,
    reorderGameCategories,
    updateGame,
    updateGameCategory,
} from '../services/gameApi'

// Huisstijlkleuren van Game-INN + een paar extra, voor de chips in Playscope.
const COLORS = ['#f27724', '#5b8de0', '#ff3fb4', '#3ddc84', '#a78bfa', '#facc15', '#22d3ee', '#94a3b8']

const tab = ref('games')
const loading = ref(false)
const error = ref('')
const games = ref([])
const categories = ref([])
const counts = ref({ total: 0, new: 0, uncategorized: 0, hidden: 0 })
const playerOptions = ref([1, 2, 4, 8])

const search = ref('')
const filter = ref('all')            // all | new | uncategorized | hidden
const categoryFilter = ref(null)
const brokenCovers = reactive(new Set())

const newCategory = ref('')
const newCategoryInput = ref(null)

let refreshTimer = null

const tabs = computed(() => [
    { key: 'games', label: 'Games', count: games.value.length },
    { key: 'categories', label: 'Categorieën', count: categories.value.length },
])

const quickFilters = computed(() => [
    { key: 'new', label: 'Nieuw', count: counts.value.new, on: 'border-amber-500/50 bg-amber-500/10 text-amber-200' },
    { key: 'uncategorized', label: 'Zonder categorie', count: counts.value.uncategorized, on: 'border-blue-500/50 bg-blue-600/15 text-blue-100' },
    { key: 'hidden', label: 'Verborgen', count: counts.value.hidden, on: 'border-red-500/50 bg-red-500/10 text-red-200' },
])

const visibleGames = computed(() => {
    const term = search.value.trim().toLowerCase()
    return games.value.filter(g => {
        if (term && !g.name.toLowerCase().includes(term) && !g.external_id.includes(term)) return false
        if (filter.value === 'new' && !g.is_new) return false
        if (filter.value === 'uncategorized' && (g.categories.length || g.is_hidden)) return false
        if (filter.value === 'hidden' && !g.is_hidden) return false
        if (categoryFilter.value && !g.categories.includes(categoryFilter.value)) return false
        return true
    })
})

function recount() {
    counts.value = {
        total: games.value.length,
        new: games.value.filter(g => g.is_new).length,
        uncategorized: games.value.filter(g => !g.categories.length && !g.is_hidden).length,
        hidden: games.value.filter(g => g.is_hidden).length,
    }
    for (const c of categories.value) {
        c.games_count = games.value.filter(g => g.categories.includes(c.id)).length
    }
    announceNewGamesCount(counts.value.new)
}

// ── Automatisch ophalen (Steam / SteamSpy / PCGamingWiki) ─────────────
const meta = reactive({ busy: false, done: 0, total: 0 })
const steamCount = computed(() => games.value.filter(g => g.source === 'steam').length)
const pendingMeta = computed(() => games.value.filter(g => g.source === 'steam' && !g.metadata_status).length)

async function fetchMeta(force = false) {
    if (force && !window.confirm('Gegevens van alle Steam-games opnieuw ophalen?\n\nAutomatisch ingevulde waarden worden bijgewerkt; wat je zelf aanduidde, blijft staan.')) return
    meta.busy = true
    meta.done = 0
    meta.total = force ? steamCount.value : pendingMeta.value
    let afterId = 0
    error.value = ''
    try {
        for (;;) {
            const res = await fetchGameMetadata({ force, after_id: afterId })
            const fresh = new Map((res.games ?? []).map(g => [g.id, g]))
            games.value = games.value.map(g => fresh.get(g.id) ?? g)
            categories.value = res.categories ?? categories.value
            meta.done += res.processed ?? 0
            meta.total = Math.max(meta.total, meta.done + (res.remaining ?? 0))
            afterId = res.last_id ?? afterId
            recount()
            if (!res.remaining || !res.processed) break
        }
    } catch (e) {
        error.value = e.message || 'Gegevens ophalen mislukt.'
    } finally {
        meta.busy = false
        await load({ silent: true })
    }
}

// ── Banners voor Frontdesk → Zaal ──────────────────────────────────────
const banner = reactive({ open: false, game: null })
const bannerFetch = reactive({ busy: false, done: 0, total: 0 })
const pendingBanners = computed(() => games.value.filter(g => !g.banner_stored && !g.banner_checked && !g.banner_source).length)

function openBanner(game) {
    banner.game = game
    banner.open = true
}

function onBannerUpdated(payload) {
    if (!banner.game) return
    Object.assign(banner.game, payload)
    brokenCovers.delete(banner.game.id)
}

async function fetchBanners() {
    bannerFetch.busy = true
    bannerFetch.done = 0
    bannerFetch.total = pendingBanners.value
    let afterId = 0
    error.value = ''
    try {
        for (;;) {
            const res = await fetchGameBanners({ after_id: afterId })
            const fresh = new Map((res.games ?? []).map(g => [g.id, g]))
            games.value = games.value.map(g => (fresh.has(g.id) ? { ...g, ...fresh.get(g.id) } : g))
            bannerFetch.done += res.processed ?? 0
            bannerFetch.total = Math.max(bannerFetch.total, bannerFetch.done + (res.remaining ?? 0))
            afterId = res.last_id ?? afterId
            if (!res.remaining || !res.processed) break
        }
    } catch (e) {
        error.value = e.message || 'Banners ophalen mislukt.'
    } finally {
        bannerFetch.busy = false
    }
}

async function load({ silent = false } = {}) {
    if (!silent) loading.value = true
    error.value = ''
    try {
        const data = await fetchGames()
        games.value = data.games ?? []
        categories.value = data.categories ?? []
        playerOptions.value = data.local_player_options ?? playerOptions.value
        recount()
    } catch (e) {
        if (!silent) error.value = e.message || 'Laden mislukt.'
    } finally {
        loading.value = false
    }
}

// Meteen tonen, bij een fout terugzetten.
async function save(game, patch) {
    const before = { ...game, categories: [...game.categories] }
    Object.assign(game, patch, { is_new: false })
    recount()
    try {
        const fresh = await updateGame(game.id, patch)
        Object.assign(game, fresh)
        recount()
    } catch (e) {
        Object.assign(game, before)
        recount()
        error.value = e.message || 'Opslaan mislukt.'
    }
}

function toggleCategory(game, id) {
    const next = game.categories.includes(id) ? game.categories.filter(c => c !== id) : [...game.categories, id]
    save(game, { categories: next })
}

async function markAllReviewed() {
    try {
        await markAllGamesReviewed()
        games.value.forEach(g => { g.is_new = false })
        recount()
    } catch (e) {
        error.value = e.message || 'Markeren mislukt.'
    }
}

async function removeGame(game) {
    if (!window.confirm(`"${game.name}" uit de catalogus verwijderen? Staat hij later weer op een PC, dan komt hij terug als nieuwe game.`)) return
    try {
        await deleteGame(game.id)
        games.value = games.value.filter(g => g.id !== game.id)
        recount()
    } catch (e) {
        error.value = e.message || 'Verwijderen mislukt.'
    }
}

// ── Categorieën ─────────────────────────────────────────────────────────────

async function startNewCategory() {
    tab.value = 'categories'
    await nextTick()
    newCategoryInput.value?.focus()
}

async function addCategory() {
    const name = newCategory.value.trim()
    if (!name) return
    try {
        const color = COLORS[categories.value.length % COLORS.length]
        const created = await createGameCategory({ name, color })
        categories.value.push(created)
        newCategory.value = ''
    } catch (e) {
        error.value = e.message || 'Toevoegen mislukt.'
    }
}

async function saveCategory(category) {
    if (!category.name.trim()) {
        await load({ silent: true })
        return
    }
    try {
        await updateGameCategory(category.id, { name: category.name.trim(), color: category.color })
    } catch (e) {
        error.value = e.message || 'Opslaan mislukt.'
    }
}

async function moveCategory(index, delta) {
    const list = [...categories.value]
    const [item] = list.splice(index, 1)
    list.splice(index + delta, 0, item)
    categories.value = list
    try {
        await reorderGameCategories(list.map(c => ({ id: c.id })))
    } catch (e) {
        error.value = e.message || 'Volgorde opslaan mislukt.'
        await load({ silent: true })
    }
}

async function removeCategory(category) {
    if (!window.confirm(`Categorie "${category.name}" verwijderen? De games zelf blijven bestaan.`)) return
    try {
        await deleteGameCategory(category.id)
        categories.value = categories.value.filter(c => c.id !== category.id)
        games.value.forEach(g => { g.categories = g.categories.filter(id => id !== category.id) })
        if (categoryFilter.value === category.id) categoryFilter.value = null
        recount()
    } catch (e) {
        error.value = e.message || 'Verwijderen mislukt.'
    }
}

// ── Weergave ────────────────────────────────────────────────────────────────

function fallbackBg(game) {
    const hue = (Number.parseInt(game.external_id, 10) || game.id) * 47 % 360
    return `linear-gradient(135deg, hsl(${hue} 55% 32%), hsl(${(hue + 40) % 360} 55% 14%))`
}

function relativeDate(value) {
    const days = Math.floor((Date.now() - new Date(value).getTime()) / 86400000)
    if (days <= 0) return 'vandaag'
    if (days === 1) return 'sinds gisteren'
    return `sinds ${days} dagen`
}

onMounted(() => {
    load()
    // Nieuwe meldingen van Playscope oppikken zonder te herladen (niet tijdens het typen).
    refreshTimer = window.setInterval(() => {
        if (document.activeElement?.tagName !== 'INPUT') load({ silent: true })
    }, 60000)
})

onBeforeUnmount(() => {
    window.clearInterval(refreshTimer)
})
</script>
