<template>
    <div class="px-4 pt-4 pb-6 space-y-3">

        <!-- Ranking van één chart -->
        <template v-if="detail">
            <button class="flex items-center gap-2 text-sm text-slate-400" @click="closeDetail">
                <ArrowLeftIcon class="w-4 h-4" />
                Terug
            </button>

            <div v-if="detail.loading" class="flex justify-center pt-16"><Loader /></div>

            <template v-else-if="detail.data">
                <div class="glass-card rounded-3xl overflow-hidden text-center">
                    <img v-if="detail.data.banner" :src="detail.data.banner.url" alt="" class="w-full object-cover" :style="{ aspectRatio: detail.data.banner.ratio }" />
                    <div class="p-5">
                    <span class="game-tag" :class="tagClass(detail.data.game)">{{ gameName(detail.data.game) }}</span>
                    <h2 class="text-lg font-semibold text-white mt-2 break-words">{{ detail.data.title }}</h2>
                    <p class="text-xs text-slate-400 mt-0.5">
                        {{ chartLabel(detail.data) }} · {{ detail.data.total }} {{ detail.data.total === 1 ? 'speler' : 'spelers' }} bij {{ venueName }}
                    </p>
                    <div v-if="detail.data.my_position" class="grid grid-cols-2 gap-2 mt-4">
                        <div class="stat-tile">
                            <p class="stat-k">Jouw plaats</p>
                            <p class="stat-v" :class="detail.data.my_position === 1 ? 'text-amber-400' : ''">#{{ detail.data.my_position }}</p>
                        </div>
                        <div class="stat-tile">
                            <p class="stat-k">Jouw score</p>
                            <p class="stat-v text-lg">{{ num(detail.data.my_score) }}</p>
                        </div>
                    </div>
                    </div>
                </div>

                <div class="glass-card rounded-3xl p-1.5">
                    <div
                        v-for="e in detail.data.entries"
                        :key="e.position"
                        class="flex items-center gap-3 px-3 py-2.5 rounded-2xl"
                        :class="e.is_me ? 'bg-blue-500/15 border border-blue-500/35' : ''"
                    >
                        <span class="w-6 text-center font-bold tabular-nums" :class="podiumClass(e.position)">{{ e.position }}</span>
                        <span class="flex-1 min-w-0 truncate text-sm text-slate-100">
                            {{ e.name }} <span v-if="e.is_me" class="text-xs text-slate-400">(jij)</span>
                        </span>
                        <span v-if="e.grade_label" class="text-[10px] font-bold text-violet-300">{{ e.grade_label }}</span>
                        <span class="text-sm font-semibold tabular-nums text-white">{{ num(e.score) }}</span>
                    </div>
                    <p v-if="!detail.data.entries.length" class="text-sm text-slate-400 text-center py-6">Nog geen scores op deze chart.</p>
                </div>
                <p class="text-xs text-slate-500 text-center">Enkel de naam die spelers in de game kozen is zichtbaar.</p>
            </template>

            <p v-else class="text-sm text-rose-300 text-center pt-10">{{ detail.error }}</p>
        </template>

        <template v-else>
            <!-- Segmentkiezer -->
            <div class="flex gap-0.5 p-[3px] rounded-2xl bg-slate-900/70 border border-slate-600/30">
                <button
                    v-for="t in tabs"
                    :key="t.key"
                    class="flex-1 py-2 rounded-xl text-xs font-medium transition-colors"
                    :class="tab === t.key ? 'bg-blue-500/20 border border-blue-500/35 text-blue-100' : 'border border-transparent text-slate-400'"
                    @click="setTab(t.key)"
                >
                    {{ t.label }}
                </button>
            </div>

            <div v-if="!venue.activeSlug && !venue.loading" class="glass-card rounded-3xl p-6 text-center text-sm text-slate-400">
                Kies eerst een venue.
            </div>

            <div v-else-if="loading && !summary" class="flex justify-center pt-16"><Loader /></div>

            <p v-else-if="error" class="glass-card rounded-3xl p-5 text-sm text-rose-300 text-center">{{ error }}</p>

            <!-- Nog niet gekoppeld -->
            <div v-else-if="summary && !summary.linked" class="glass-card rounded-3xl px-4 py-6 text-center flex flex-col items-center gap-3">
                <div class="w-14 h-14 rounded-2xl bg-amber-500/15 border border-amber-500/25 flex items-center justify-center">
                    <TrophyIcon class="w-7 h-7 text-amber-400" />
                </div>
                <h2 class="text-base font-semibold text-white">Je scores op de dansmachines</h2>
                <p class="text-xs text-slate-400 leading-relaxed">
                    Tik je {{ venueName }}-lidkaart op de kaartlezer van de kast. Je scores verschijnen hier dan vanzelf.
                </p>
                <button class="member-btn-ghost w-full text-sm flex items-center justify-center gap-2" @click="openLink">
                    <LinkIcon class="w-4 h-4" />
                    Speel je met een andere kaart?
                </button>
            </div>

            <template v-else-if="summary">

                <!-- 1. Overzicht -->
                <template v-if="tab === 'overzicht'">
                    <div v-if="summary.dethrones?.length" class="glass-card rounded-3xl p-5 border !border-rose-500/30">
                        <p class="section-label !text-rose-300">Je #1 kwijt · pak ze terug</p>
                        <button
                            v-for="d in summary.dethrones"
                            :key="d.id"
                            class="list-row w-full text-left"
                            @click="openDetail(d)"
                        >
                            <span class="game-tag" :class="tagClass(d.game)">{{ d.short }}</span>
                            <div class="flex-1 min-w-0">
                                <p class="text-sm text-white truncate">
                                    {{ d.title }}
                                    <span v-if="d.is_new" class="ml-1 text-[9px] font-bold px-1.5 py-0.5 rounded-md bg-rose-500/20 text-rose-300 border border-rose-500/30 align-middle">NIEUW</span>
                                </p>
                                <p class="text-[11px] text-slate-400 truncate">{{ chartLabel(d) }} · {{ d.by }} · {{ ago(d.at) }}</p>
                            </div>
                            <div class="text-right shrink-0">
                                <p class="text-sm font-semibold text-white tabular-nums">{{ num(d.by_score) }}</p>
                                <p class="text-[10px] text-slate-400 tabular-nums">jij: {{ num(d.my_score) }}</p>
                            </div>
                        </button>
                    </div>

                    <div class="glass-card rounded-3xl p-5">
                        <div class="flex items-center justify-between gap-2">
                            <h2 class="text-lg font-semibold text-white truncate">{{ playerNames }}</h2>
                            <span v-if="summary.stats.last_played_at" class="flex items-center gap-1 text-xs text-slate-400 shrink-0">
                                <ClockIcon class="w-3.5 h-3.5" />{{ ago(summary.stats.last_played_at) }}
                            </span>
                        </div>
                        <p class="text-xs text-slate-400 mt-0.5 mb-4">{{ linkedLabel }}</p>
                        <div class="grid grid-cols-2 gap-2">
                            <div class="stat-tile">
                                <p class="stat-k"><MusicalNoteIcon class="w-3.5 h-3.5" />Speelbeurten</p>
                                <p class="stat-v">{{ num(summary.stats.total_plays) }}</p>
                            </div>
                            <div class="stat-tile">
                                <p class="stat-k">Charts gespeeld</p>
                                <p class="stat-v">{{ num(summary.stats.charts_played) }}</p>
                            </div>
                            <div class="stat-tile">
                                <p class="stat-k"><StarIcon class="w-3.5 h-3.5" />Records (30 d)</p>
                                <p class="stat-v">{{ num(summary.stats.records_30d) }}</p>
                            </div>
                            <div class="stat-tile">
                                <p class="stat-k"><TrophyIcon class="w-3.5 h-3.5" />#1 bij {{ venueName }}</p>
                                <p class="stat-v text-amber-400">{{ num(summary.stats.first_places) }}</p>
                            </div>
                        </div>
                    </div>

                    <div v-if="summary.stats.per_game.length" class="glass-card rounded-3xl p-5">
                        <p class="section-label">Per game</p>
                        <div v-if="summary.stats.per_game.some(g => g.banner)" class="flex gap-2 overflow-x-auto pb-2 mb-1 scrollbar-none">
                            <button
                                v-for="g in summary.stats.per_game.filter(g => g.banner)"
                                :key="'b' + g.game"
                                class="shrink-0 h-16 rounded-xl overflow-hidden border border-white/10"
                                @click="scoreGame = g.game; setTab('scores')"
                            >
                                <img :src="g.banner.url" :alt="g.short" class="h-full w-auto object-cover" />
                            </button>
                        </div>
                        <div v-for="g in summary.stats.per_game" :key="g.game" class="flex items-center gap-2 text-xs my-2">
                            <span class="w-20 text-slate-300 truncate">{{ g.short }}</span>
                            <span class="flex-1 h-[7px] rounded-full bg-slate-800/80 overflow-hidden">
                                <i class="block h-full rounded-full bg-gradient-to-r from-blue-500 to-purple-500" :style="{ width: barWidth(g.plays) }" />
                            </span>
                            <span class="w-9 text-right text-slate-400 tabular-nums">{{ num(g.plays) }}</span>
                        </div>
                    </div>

                    <div v-if="summary.stats.favorites.length" class="glass-card rounded-3xl p-5">
                        <p class="section-label">Favoriete songs</p>
                        <div v-for="f in summary.stats.favorites" :key="f.game + f.song_id" class="list-row">
                            <span class="game-tag" :class="tagClass(f.game)">{{ f.short }}</span>
                            <div class="flex-1 min-w-0">
                                <p class="text-sm text-white truncate">{{ f.title }}</p>
                                <p v-if="f.artist" class="text-[11px] text-slate-400 truncate">{{ f.artist }}</p>
                            </div>
                            <span class="text-xs text-slate-400 tabular-nums shrink-0">{{ f.plays }}×</span>
                        </div>
                    </div>

                    <div v-if="summary.recent.length" class="glass-card rounded-3xl p-5">
                        <p class="section-label">Recent gespeeld</p>
                        <PlayRow v-for="p in summary.recent" :key="p.id" :play="p" />
                    </div>

                    <div class="glass-card rounded-3xl p-5">
                        <p class="section-label">Gekoppelde spelers</p>
                        <div v-for="p in summary.players" :key="p.id" class="list-row">
                            <div class="flex-1 min-w-0">
                                <p class="text-sm text-white truncate">{{ p.name }}</p>
                                <p class="text-[11px] text-slate-400 truncate">
                                    {{ p.linked_via === 'manual' ? 'Manueel gekoppeld' : 'Via je lidkaart' }}
                                    <template v-if="p.games.length"> · {{ p.games.map(g => g.short).join(', ') }}</template>
                                </p>
                            </div>
                            <button
                                class="text-xs shrink-0 px-2.5 py-1 rounded-lg border transition-colors"
                                :class="confirmUnlink === p.id ? 'border-rose-500/40 bg-rose-500/15 text-rose-300' : 'border-slate-600/40 text-slate-400'"
                                :disabled="unlinking"
                                @click="unlink(p)"
                            >
                                {{ confirmUnlink === p.id ? 'Zeker?' : 'Ontkoppelen' }}
                            </button>
                        </div>
                        <button class="member-btn-ghost w-full text-sm mt-2 flex items-center justify-center gap-2" @click="openLink">
                            <LinkIcon class="w-4 h-4" />
                            Nog een speler koppelen
                        </button>
                    </div>
                </template>

                <!-- 2. Scores -->
                <template v-else-if="tab === 'scores'">
                    <GameChips v-model="scoreGame" :games="playedGames" />
                    <GameBanner :banner="bannerOf(scoreGame)" />
                    <div class="flex items-center justify-between">
                        <span class="text-xs text-slate-400">{{ scores.length }} {{ scores.length === 1 ? 'chart' : 'charts' }}</span>
                        <select v-model="scoreSort" class="sort-select">
                            <option value="recent">Recent gespeeld</option>
                            <option value="score">Hoogste score</option>
                            <option value="rank">Beste plaats</option>
                            <option value="title">Titel</option>
                        </select>
                    </div>

                    <div v-if="listLoading" class="flex justify-center pt-10"><Loader /></div>
                    <template v-else>
                        <button
                            v-for="s in scores"
                            :key="s.id"
                            class="glass-card rounded-2xl p-3.5 w-full text-left flex items-center gap-3"
                            @click="openDetail(s)"
                        >
                            <span class="game-tag" :class="tagClass(s.game)">{{ s.short }}</span>
                            <div class="flex-1 min-w-0">
                                <p class="text-sm font-medium text-white truncate">{{ s.title }}</p>
                                <p class="text-[11px] text-slate-400 flex flex-wrap items-center gap-x-1.5">
                                    <span>{{ chartLabel(s) }}</span>
                                    <span v-if="s.grade_label" class="font-bold text-violet-300">{{ s.grade_label }}</span>
                                    <span v-if="s.clear_label" class="font-bold text-cyan-300">{{ s.clear_label }}</span>
                                    <span v-if="s.combo">combo {{ s.combo }}</span>
                                    <span v-if="s.play_count">· {{ s.play_count }}×</span>
                                </p>
                            </div>
                            <div class="text-right shrink-0">
                                <p class="text-base font-semibold text-white tabular-nums">{{ s.score === null ? '—' : num(s.score) }}</p>
                                <p v-if="s.position" class="text-[10px] flex items-center justify-end gap-1" :class="s.position === 1 ? 'text-amber-400' : 'text-slate-400'">
                                    <TrophyIcon v-if="s.position === 1" class="w-3 h-3" />#{{ s.position }}
                                </p>
                            </div>
                        </button>
                        <p v-if="!scores.length" class="text-sm text-slate-400 text-center py-8">Nog geen scores voor deze game.</p>
                        <p v-else class="text-xs text-slate-500 text-center">Tik op een score voor de ranking van die chart.</p>
                    </template>
                </template>

                <!-- 3. Historiek -->
                <template v-else-if="tab === 'historiek'">
                    <GameChips v-model="playsGame" :games="playedGames" />
                    <GameBanner :banner="bannerOf(playsGame)" />
                    <div v-if="listLoading && !plays.length" class="flex justify-center pt-10"><Loader /></div>
                    <div v-else class="glass-card rounded-3xl p-5">
                        <PlayRow v-for="p in plays" :key="p.id" :play="p" />
                        <p v-if="!plays.length" class="text-sm text-slate-400 text-center py-6">Nog geen speelbeurten.</p>
                    </div>
                    <button v-if="playsPage < playsLastPage" class="member-btn-ghost w-full text-sm" :disabled="listLoading" @click="loadPlays(playsPage + 1)">
                        {{ listLoading ? 'Laden…' : 'Meer laden' }}
                    </button>
                    <p class="text-xs text-slate-500 text-center leading-relaxed">
                        pop'n, SDVX en NOSTALGIA bewaren enkel je beste resultaat: daar staat "gespeeld", met een score als het een nieuw record was.
                    </p>
                </template>

                <!-- 4. Ranking (meest gespeelde charts) -->
                <template v-else-if="tab === 'ranking'">
                    <GameChips v-model="rankGame" :games="summary.games" />
                    <GameBanner :banner="bannerOf(rankGame)" />
                    <div v-if="listLoading" class="flex justify-center pt-10"><Loader /></div>
                    <template v-else>
                        <button
                            v-for="c in charts"
                            :key="c.game + c.song_id + c.chart"
                            class="glass-card rounded-2xl p-3.5 w-full text-left flex items-center gap-3"
                            @click="openDetail(c)"
                        >
                            <span class="game-tag" :class="tagClass(c.game)">{{ c.short }}</span>
                            <div class="flex-1 min-w-0">
                                <p class="text-sm font-medium text-white truncate">{{ c.title }}</p>
                                <p class="text-[11px] text-slate-400">{{ chartLabel(c) }} · {{ c.players }} {{ c.players === 1 ? 'speler' : 'spelers' }}</p>
                            </div>
                            <div class="text-right shrink-0">
                                <p class="text-sm font-semibold text-white tabular-nums">{{ num(c.top_score) }}</p>
                                <p class="text-[10px] flex items-center justify-end gap-1" :class="c.is_top ? 'text-amber-400' : 'text-slate-400'">
                                    <TrophyIcon v-if="c.is_top" class="w-3 h-3" />
                                    {{ c.is_top ? 'jij staat #1' : (c.my_score !== null ? 'jij: ' + num(c.my_score) : 'topscore') }}
                                </p>
                            </div>
                            <ChevronRightIcon class="w-4 h-4 text-slate-500 shrink-0" />
                        </button>
                        <p v-if="!charts.length" class="text-sm text-slate-400 text-center py-8">Nog geen scores bij {{ venueName }}.</p>
                    </template>
                </template>
            </template>
        </template>

        <!-- Bottom sheet: speler koppelen -->
        <div v-if="linkOpen" class="fixed inset-0 z-50 flex items-end">
            <div class="absolute inset-0 bg-black/55" @click="linkOpen = false" />
            <form class="relative w-full glass-card rounded-t-3xl p-5 pb-8 space-y-3" @submit.prevent="submitLink">
                <div class="w-10 h-1 rounded bg-slate-400/35 mx-auto" />
                <h2 class="text-base font-semibold text-white">Speler koppelen</h2>
                <p class="text-xs text-slate-400 leading-relaxed">
                    Vul het nummer van je kaart of je speler-ID uit de game in (bv. je DANCERUSH-ID of DDR-code), plus de 4-cijferige PIN die je op de kast gebruikt.
                </p>
                <input v-model="linkCode" type="text" class="member-input" placeholder="Kaartnummer of speler-ID" autocomplete="off" autocapitalize="characters" />
                <input v-model="linkPin" type="password" inputmode="numeric" maxlength="4" class="member-input tracking-[0.5em]" placeholder="PIN" autocomplete="off" />
                <div v-if="linkError" class="rounded-2xl bg-rose-500/10 border border-rose-500/30 px-4 py-3 text-sm text-rose-300">{{ linkError }}</div>
                <button type="submit" class="member-btn-primary w-full flex items-center justify-center gap-2" :disabled="linkBusy">
                    <LinkIcon class="w-4 h-4" />
                    {{ linkBusy ? 'Bezig…' : 'Koppelen' }}
                </button>
            </form>
        </div>
    </div>
</template>

<script setup>
import { ref, computed, watch, onMounted, h } from 'vue'
import {
    TrophyIcon, ClockIcon, StarIcon, MusicalNoteIcon, LinkIcon, ArrowLeftIcon, ChevronRightIcon,
} from '@heroicons/vue/24/outline'
import { arcadeApi } from '../../services/api'
import { useVenueStore } from '../../stores/useVenueStore'

const venue = useVenueStore()

const tabs = [
    { key: 'overzicht', label: 'Overzicht' },
    { key: 'scores',    label: 'Scores' },
    { key: 'historiek', label: 'Historiek' },
    { key: 'ranking',   label: 'Ranking' },
]

const tab = ref('overzicht')
const summary = ref(null)
const loading = ref(false)
const error = ref('')
const listLoading = ref(false)

const scores = ref([])
const scoreGame = ref('')
const scoreSort = ref('recent')

const plays = ref([])
const playsGame = ref('')
const playsPage = ref(1)
const playsLastPage = ref(1)

const charts = ref([])
const rankGame = ref('')

const detail = ref(null)

const linkOpen = ref(false)
const linkCode = ref('')
const linkPin = ref('')
const linkError = ref('')
const linkBusy = ref(false)

const confirmUnlink = ref(null)
const unlinking = ref(false)

const slug = computed(() => venue.activeSlug)
const venueName = computed(() => summary.value?.venue?.name ?? venue.activeVenue?.name ?? 'de venue')

const playerNames = computed(() => summary.value?.players.map(p => p.name).join(' · ') || 'Speler')
const linkedLabel = computed(() => {
    const ps = summary.value?.players ?? []
    const cards = ps.reduce((n, p) => n + (p.cards?.length ?? 0), 0)
    const via = ps.every(p => p.linked_via === 'manual') ? 'Manueel gekoppeld' : 'Gekoppeld via je lidkaart'
    return `${via} · ${cards} ${cards === 1 ? 'kaart' : 'kaarten'}`
})
const playedGames = computed(() => (summary.value?.stats.per_game ?? []).map(g => ({ key: g.game, short: g.short })))
function bannerOf(game) {
    if (!game) return null
    return summary.value?.games?.find(g => g.key === game)?.banner ?? null
}
const maxPlays = computed(() => Math.max(1, ...(summary.value?.stats.per_game ?? []).map(g => g.plays)))

// ── laden ──────────────────────────────────────────────────────────────

async function loadSummary() {
    if (!slug.value) return
    loading.value = true
    error.value = ''
    try {
        const { data } = await arcadeApi.summary(slug.value)
        summary.value = data
        // Overgenomen plaatsen zijn nu gezien → verdwijnen van de startpagina
        if (data.dethrones?.some(d => d.is_new)) {
            arcadeApi.seen(slug.value).catch(() => {})
        }
    } catch (e) {
        summary.value = null
        error.value = e?.message ?? 'Scores konden niet geladen worden.'
    } finally {
        loading.value = false
    }
}

async function loadScores() {
    listLoading.value = true
    try {
        const { data } = await arcadeApi.scores(slug.value, scoreGame.value, scoreSort.value)
        scores.value = data
    } catch {
        scores.value = []
    } finally {
        listLoading.value = false
    }
}

async function loadPlays(page = 1) {
    listLoading.value = true
    try {
        const res = await arcadeApi.plays(slug.value, playsGame.value, page)
        plays.value = page === 1 ? res.data : [...plays.value, ...res.data]
        playsPage.value = res.meta.page
        playsLastPage.value = res.meta.last_page
    } catch {
        if (page === 1) plays.value = []
    } finally {
        listLoading.value = false
    }
}

async function loadCharts() {
    listLoading.value = true
    try {
        const { data } = await arcadeApi.charts(slug.value, rankGame.value)
        charts.value = data
    } catch {
        charts.value = []
    } finally {
        listLoading.value = false
    }
}

function loadTab() {
    if (!summary.value?.linked && tab.value !== 'ranking') return
    if (tab.value === 'scores') loadScores()
    else if (tab.value === 'historiek') loadPlays(1)
    else if (tab.value === 'ranking') loadCharts()
}

function setTab(key) {
    tab.value = key
    loadTab()
}

watch([scoreGame, scoreSort], loadScores)
watch(playsGame, () => loadPlays(1))
watch(rankGame, loadCharts)

watch(slug, async () => {
    detail.value = null
    tab.value = 'overzicht'
    summary.value = null
    await loadSummary()
})

onMounted(async () => {
    if (!venue.venues.length && !venue.loading) {
        await venue.loadVenues()
    }
    await loadSummary()
})

// ── ranking van één chart ──────────────────────────────────────────────

async function openDetail(row) {
    detail.value = { loading: true, data: null, error: '' }
    try {
        const { data } = await arcadeApi.ranking(slug.value, row.game, row.song_id, row.chart)
        detail.value = { loading: false, data, error: '' }
    } catch (e) {
        detail.value = { loading: false, data: null, error: e?.message ?? 'Ranking kon niet geladen worden.' }
    }
}

function closeDetail() {
    detail.value = null
}

// ── koppelen / ontkoppelen ─────────────────────────────────────────────

function openLink() {
    linkCode.value = ''
    linkPin.value = ''
    linkError.value = ''
    linkOpen.value = true
}

async function submitLink() {
    linkError.value = ''
    if (!linkCode.value.trim() || !/^\d{4}$/.test(linkPin.value)) {
        linkError.value = 'Vul je kaartnummer of speler-ID en je 4-cijferige PIN in.'
        return
    }
    linkBusy.value = true
    try {
        await arcadeApi.link(slug.value, linkCode.value.trim(), linkPin.value)
        linkOpen.value = false
        await loadSummary()
    } catch (e) {
        linkError.value = e?.status === 429
            ? 'Te veel pogingen. Probeer het binnen een minuut opnieuw.'
            : (e?.data?.errors?.pin?.[0] ?? e?.message ?? 'Koppelen mislukt.')
    } finally {
        linkBusy.value = false
    }
}

async function unlink(player) {
    if (confirmUnlink.value !== player.id) {
        confirmUnlink.value = player.id
        setTimeout(() => { if (confirmUnlink.value === player.id) confirmUnlink.value = null }, 4000)
        return
    }
    unlinking.value = true
    try {
        await arcadeApi.unlink(slug.value, player.id)
        confirmUnlink.value = null
        await loadSummary()
    } finally {
        unlinking.value = false
    }
}

// ── weergave ───────────────────────────────────────────────────────────

const nf = new Intl.NumberFormat('nl-BE')
function num(n) {
    return n === null || n === undefined ? '—' : nf.format(n)
}

function barWidth(plays) {
    return Math.max(4, Math.round((plays / maxPlays.value) * 100)) + '%'
}

const GAME_NAMES = { drs: 'DANCERUSH STARDOM', ddr: 'DanceDanceRevolution', popn: "pop'n music", sdvx: 'SOUND VOLTEX', nostalgia: 'NOSTALGIA' }
function gameName(game) {
    return GAME_NAMES[game] ?? String(game).toUpperCase()
}

function tagClass(game) {
    return {
        drs:       'bg-pink-500/15 text-pink-300 border-pink-500/30',
        ddr:       'bg-amber-500/15 text-amber-300 border-amber-500/30',
        sdvx:      'bg-blue-500/15 text-blue-300 border-blue-500/30',
        popn:      'bg-emerald-500/15 text-emerald-300 border-emerald-500/30',
        nostalgia: 'bg-violet-500/15 text-violet-300 border-violet-500/30',
    }[game] ?? 'bg-slate-500/15 text-slate-300 border-slate-500/30'
}

function chartLabel(row) {
    const c = String(row.chart ?? '').toUpperCase()
    return row.level ? `${c} · lv ${row.level}` : c
}

function podiumClass(pos) {
    return { 1: 'text-amber-400', 2: 'text-slate-200', 3: 'text-amber-600' }[pos] ?? 'text-slate-400'
}

function ago(iso) {
    if (!iso) return ''
    const d = new Date(iso)
    const mins = Math.round((Date.now() - d.getTime()) / 60000)
    if (mins < 1) return 'net nu'
    if (mins < 60) return `${mins} min geleden`
    const hrs = Math.round(mins / 60)
    if (hrs < 24) return `${hrs} u geleden`
    const days = Math.round(hrs / 24)
    if (days === 1) return 'gisteren'
    if (days < 30) return `${days} dagen geleden`
    return d.toLocaleDateString('nl-BE', { day: 'numeric', month: 'short', year: 'numeric' })
}

function when(iso) {
    if (!iso) return ''
    const d = new Date(iso)
    const today = new Date()
    const yesterday = new Date(); yesterday.setDate(today.getDate() - 1)
    const time = d.toLocaleTimeString('nl-BE', { hour: '2-digit', minute: '2-digit' })
    if (d.toDateString() === today.toDateString()) return `vandaag ${time}`
    if (d.toDateString() === yesterday.toDateString()) return `gisteren ${time}`
    return d.toLocaleDateString('nl-BE', { day: 'numeric', month: 'short' }) + ` ${time}`
}

// ── kleine componenten ─────────────────────────────────────────────────

const Loader = () => h('div', { class: 'flex gap-2' }, [
    h('span', { class: 'loader-dot' }), h('span', { class: 'loader-dot' }), h('span', { class: 'loader-dot' }),
])

const GameChips = {
    props: { modelValue: String, games: Array },
    emits: ['update:modelValue'],
    setup(props, { emit }) {
        const chip = (key, label) => h('button', {
            class: [
                'px-3 py-1.5 rounded-xl text-xs font-medium whitespace-nowrap shrink-0 border transition-colors',
                props.modelValue === key
                    ? 'bg-blue-500/20 border-blue-500/30 text-blue-300'
                    : 'bg-slate-800/50 border-slate-700/40 text-slate-400',
            ],
            onClick: () => emit('update:modelValue', key),
        }, label)
        return () => h('div', { class: 'flex gap-2 overflow-x-auto pb-1 scrollbar-none' }, [
            chip('', 'Alle'),
            ...(props.games ?? []).map(g => chip(g.key, g.short)),
        ])
    },
}

const GameBanner = {
    props: { banner: Object },
    setup(props) {
        return () => props.banner
            ? h('img', {
                src: props.banner.url,
                alt: '',
                class: 'w-full rounded-2xl object-cover border border-white/10 max-h-40',
                style: { aspectRatio: props.banner.ratio },
            })
            : null
    },
}

const PlayRow = {
    props: { play: Object },
    setup(props) {
        return () => {
            const p = props.play
            const meta = [chartLabel(p), p.grade_label, p.clear_label].filter(Boolean).join(' · ')
            const right = p.score === null
                ? [h('p', { class: 'text-sm text-slate-500' }, '—'), h('p', { class: 'text-[10px] text-slate-400' }, when(p.played_at))]
                : [
                    h('p', { class: 'text-sm font-semibold text-white tabular-nums' }, num(p.score)),
                    p.is_new_best
                        ? h('span', { class: 'inline-flex items-center gap-1 text-[9px] font-bold px-1.5 py-0.5 rounded-md bg-yellow-400/15 text-yellow-300 border border-yellow-400/30' }, [h(StarIcon, { class: 'w-2.5 h-2.5' }), 'RECORD'])
                        : h('p', { class: 'text-[10px] text-slate-400' }, when(p.played_at)),
                ]
            return h('div', { class: 'list-row' }, [
                h('span', { class: ['game-tag', tagClass(p.game)] }, p.short),
                h('div', { class: 'flex-1 min-w-0' }, [
                    h('p', { class: 'text-sm text-white truncate' }, p.title),
                    h('p', { class: 'text-[11px] text-slate-400 truncate' }, p.score === null ? `${meta} · gespeeld` : (p.is_new_best ? `${meta} · ${when(p.played_at)}` : meta)),
                ]),
                h('div', { class: 'text-right shrink-0' }, right),
            ])
        }
    },
}
</script>

<style scoped>
.game-tag {
    font-size: 9px;
    font-weight: 700;
    letter-spacing: .04em;
    padding: 3px 6px;
    border-radius: 6px;
    border-width: 1px;
    flex-shrink: 0;
    white-space: nowrap;
}
.stat-tile {
    border-radius: 16px;
    padding: 10px 12px;
    background: rgba(15, 23, 42, .55);
    border: 1px solid rgba(75, 98, 148, .22);
}
.stat-k {
    display: flex;
    align-items: center;
    gap: 4px;
    font-size: 11px;
    color: #94a3b8;
}
.stat-v {
    font-size: 22px;
    font-weight: 600;
    color: #f1f5f9;
    font-variant-numeric: tabular-nums;
    margin-top: 2px;
}
.section-label {
    font-size: 11px;
    text-transform: uppercase;
    letter-spacing: .12em;
    color: #94a3b8;
    margin-bottom: 8px;
}
.list-row {
    display: flex;
    align-items: center;
    gap: 10px;
    padding: 9px 0;
    border-top: 1px solid rgba(75, 98, 148, .16);
}
.list-row:first-of-type {
    border-top: 0;
}
.sort-select {
    font-size: 12px;
    color: #cbd5e1;
    background: rgba(30, 41, 59, .6);
    border: 1px solid rgba(71, 85, 105, .4);
    border-radius: 10px;
    padding: 5px 8px;
}
</style>
