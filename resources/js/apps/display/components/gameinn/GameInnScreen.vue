<script setup>
import { computed, nextTick, onBeforeUnmount, onMounted, reactive, ref, watch } from 'vue'
import GameInnOrbit from './GameInnOrbit.vue'
import { ZONE_LABELS } from './icons.js'

/**
 * Klantenscherm met de Game-INN-skin (zoals game-inn.be).
 *
 *  standby  verticale filmpjes na elkaar met CRT-effect (scanlines, vignet, rollende band, korte
 *           beeldstoring bij elke wissel), embleem met de acht zones, titel, openingsuren, lopende band.
 *  order    het filmpje loopt door maar wordt een strook bovenaan; daaronder de bestelling. Geen orbit.
 *
 * Ontworpen op 405×720 en geschaald naar het scherm (het klantenscherm aan de kassa is 607×1080).
 */
const props = defineProps({
    mode: { type: String, default: 'standby' },          // standby | order
    skin: { type: Object, required: true },               // payload van GET /api/display/skin
    reservation: { type: Object, default: () => ({}) },
    items: { type: Array, default: () => [] },            // [{name, quantity, unit_price}]
    total: { type: Number, default: 0 },
    totalPersonsLabel: { type: String, default: '-' },
    startTimeLabel: { type: String, default: '-' },
    endTimeLabel: { type: String, default: '-' },
})

const emit = defineEmits(['logo-hold-start', 'logo-hold-end'])

const W = 405
const H = 720

// ── Schalen naar het scherm ──────────────────────────────────────────
const scale = ref(1)
function fit() {
    scale.value = Math.min(window.innerWidth / W, window.innerHeight / H)
}

// ── Instellingen ─────────────────────────────────────────────────────
const settings = computed(() => props.skin?.settings ?? {})
const assets = computed(() => props.skin?.assets ?? {})
const videos = computed(() => Array.isArray(props.skin?.videos) ? props.skin.videos : [])
const crt = computed(() => settings.value.crt || 'zacht')
const isOrder = computed(() => props.mode === 'order')

const standbyTicker = computed(() => settings.value.standby_ticker ?? [])
const orderTicker = computed(() => settings.value.order_ticker ?? [])
const tickerSeconds = (list) => Math.max(16, Math.round(list.join(' ').length * 0.32))

/** "Waregem · *vlak aan het station*" → stukken met vet. */
const infoParts = computed(() => String(settings.value.info_text || '')
    .split(/(\*[^*]+\*)/)
    .filter(Boolean)
    .map(part => part.startsWith('*') && part.endsWith('*')
        ? { text: part.slice(1, -1), bold: true }
        : { text: part, bold: false }))

// ── Openingsuren ─────────────────────────────────────────────────────
const now = ref(new Date())
const WEEKDAYS = ['', 'maandag', 'dinsdag', 'woensdag', 'donderdag', 'vrijdag', 'zaterdag', 'zondag']

function minutes(hm) {
    const [h, m] = String(hm || '').split(':').map(Number)
    return Number.isNaN(h) ? null : h * 60 + (m || 0)
}

const hours = computed(() => {
    const data = props.skin?.hours
    if (!settings.value.show_hours || !data) return null

    const today = data.today
    const nowMin = now.value.getHours() * 60 + now.value.getMinutes()
    const todayIso = localIso(now.value)

    if (today?.is_open && today.date === todayIso) {
        const from = minutes(today.from)
        let until = minutes(today.until)
        if (until !== null && from !== null && until <= from) until += 24 * 60   // tot na middernacht
        if (nowMin < from) return { open: false, soon: true, text: 'Vandaag open vanaf', time: today.from }
        if (nowMin < until) return { open: true, text: 'Vandaag open tot', time: today.until }
    }

    const next = data.next
    if (!next) return null
    const tomorrow = localIso(new Date(now.value.getTime() + 864e5))
    const day = next.date === tomorrow ? 'morgen' : WEEKDAYS[next.weekday] || ''
    return { open: false, text: `Gesloten · ${day} open vanaf`, time: next.from }
})

function localIso(d) {
    return `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}-${String(d.getDate()).padStart(2, '0')}`
}

// ── Filmpjes: twee spelers (A/B) zodat de volgende al klaarstaat ─────
const players = [null, null]
const slots = reactive([{ src: '', id: null }, { src: '', id: null }])
const activeSlot = ref(0)
const currentIndex = ref(-1)
const glitch = ref(false)
let watchdog = null
let glitchTimer = null

const current = computed(() => videos.value[currentIndex.value] ?? null)
const zone = computed(() => current.value?.zone || null)
const zoneLabel = computed(() => {
    if (!current.value) return ''
    return current.value.title || (zone.value ? ZONE_LABELS[zone.value] : '')
})

function video(slot) {
    return players[slot]
}

function preload(slot, index) {
    const item = videos.value[index]
    if (!item) return
    if (slots[slot].src !== item.url) {
        slots[slot].src = item.url
        slots[slot].id = item.id
    }
}

function armWatchdog() {
    window.clearTimeout(watchdog)
    if (videos.value.length < 2) return
    const own = video(activeSlot.value)?.duration
    const seconds = Number(current.value?.duration) || (Number.isFinite(own) ? own : 180)
    // Filmpje blijft hangen of "ended" komt nooit: na de duur + marge toch doorgaan.
    watchdog = window.setTimeout(next, (seconds + 8) * 1000)
}

async function playSlot(slot) {
    const el = video(slot)
    if (!el) return
    try {
        el.currentTime = 0
        await el.play()
    } catch {
        // Afspelen geweigerd of bestand stuk: volgende proberen.
        window.setTimeout(next, 1500)
        return
    }
    const other = video(1 - slot)
    activeSlot.value = slot
    if (other && other !== el) other.pause()
    triggerGlitch()
    armWatchdog()

    // Het volgende filmpje alvast laden in de andere speler.
    if (videos.value.length > 1) {
        preload(1 - slot, (currentIndex.value + 1) % videos.value.length)
    }
}

async function start(index = 0) {
    if (!videos.value.length) {
        currentIndex.value = -1
        slots[0].src = slots[1].src = ''
        window.clearTimeout(watchdog)
        return
    }
    currentIndex.value = index % videos.value.length
    const slot = activeSlot.value
    preload(slot, currentIndex.value)
    await nextTick()
    playSlot(slot)
}

async function next() {
    const n = videos.value.length
    if (!n) return
    if (n === 1) {
        // Eén filmpje: gewoon herhalen.
        const el = video(activeSlot.value)
        if (el) { el.currentTime = 0; el.play().catch(() => {}) }
        triggerGlitch()
        return
    }
    currentIndex.value = (currentIndex.value + 1) % n
    const slot = 1 - activeSlot.value
    preload(slot, currentIndex.value)
    await nextTick()
    playSlot(slot)
}

function onEnded(slot) {
    if (slot === activeSlot.value) next()
}

function onError(slot) {
    if (slot === activeSlot.value) window.setTimeout(next, 1500)
}

function triggerGlitch() {
    glitch.value = false
    window.clearTimeout(glitchTimer)
    requestAnimationFrame(() => {
        glitch.value = true
        glitchTimer = window.setTimeout(() => { glitch.value = false }, 280)
    })
}

// Lijst gewijzigd (filmpje erbij, weg of andere volgorde): blijven spelen als het huidige er nog is.
watch(() => videos.value.map(v => v.url).join('|'), () => {
    const id = current.value?.id ?? slots[activeSlot.value].id
    const index = videos.value.findIndex(v => v.id === id)
    if (index >= 0) {
        currentIndex.value = index
        if (videos.value.length > 1) preload(1 - activeSlot.value, (index + 1) % videos.value.length)
        return
    }
    start(0)
})

// ── Bestelling ───────────────────────────────────────────────────────
const money = new Intl.NumberFormat('nl-BE', { minimumFractionDigits: 2, maximumFractionDigits: 2 })
const euro = (value) => `€ ${money.format(Number(value) || 0)}`

const flashing = ref(new Set())
const listEl = ref(null)
let previousQty = new Map()

watch(() => props.items.map(i => `${i.name}:${i.quantity}`).join('|'), async () => {
    const changed = []
    const nextQty = new Map()
    for (const item of props.items) {
        const qty = Number(item.quantity) || 0
        nextQty.set(item.name, qty)
        if (qty > (previousQty.get(item.name) ?? 0)) changed.push(item.name)
    }
    const wasEmpty = previousQty.size === 0
    previousQty = nextQty
    // Scherm komt op met een bestaande bestelling: niet alles laten oplichten.
    if (!changed.length || (wasEmpty && changed.length > 1)) return

    const set = new Set(flashing.value)
    changed.forEach(name => set.add(name))
    flashing.value = set
    window.setTimeout(() => {
        const rest = new Set(flashing.value)
        changed.forEach(name => rest.delete(name))
        flashing.value = rest
    }, 2600)

    await nextTick()
    listEl.value?.querySelector(`[data-name="${CSS.escape(changed[changed.length - 1])}"]`)
        ?.scrollIntoView({ block: 'nearest', behavior: 'smooth' })
}, { immediate: true })

const metaPills = computed(() => {
    const pills = []
    if (props.reservation?.name) pills.push(props.reservation.name)
    if (props.totalPersonsLabel && props.totalPersonsLabel !== '-') {
        pills.push(`${props.totalPersonsLabel} ${Number(props.totalPersonsLabel) === 1 ? 'persoon' : 'personen'}`)
    }
    if (props.startTimeLabel !== '-' && props.endTimeLabel !== '-') pills.push(`${props.startTimeLabel} → ${props.endTimeLabel}`)
    else if (props.startTimeLabel !== '-') pills.push(`vanaf ${props.startTimeLabel}`)
    return pills
})

// ── Lettertypes van de website ───────────────────────────────────────
function loadFonts() {
    if (document.getElementById('gi-fonts')) return
    const link = document.createElement('link')
    link.id = 'gi-fonts'
    link.rel = 'stylesheet'
    link.href = 'https://fonts.googleapis.com/css2?family=Big+Shoulders+Display:wght@700;900&family=Inter:wght@400;500;600&family=Orbitron:wght@600;800&family=Sora:wght@600;700&display=swap'
    document.head.appendChild(link)
}

let clock = null
onMounted(() => {
    loadFonts()
    fit()
    window.addEventListener('resize', fit)
    clock = window.setInterval(() => { now.value = new Date() }, 30000)
    start(0)
})

onBeforeUnmount(() => {
    window.removeEventListener('resize', fit)
    window.clearInterval(clock)
    window.clearTimeout(watchdog)
    window.clearTimeout(glitchTimer)
})

const holdStart = () => emit('logo-hold-start')
const holdEnd = () => emit('logo-hold-end')
</script>

<template>
    <div class="gi-root">
        <div class="gi-stage" :class="[`crt-${crt}`, { 'is-order': isOrder }]" :style="{ transform: `translate(-50%, -50%) scale(${scale})` }">
            <!-- Filmpjes + retrofilter -->
            <div class="bg" :class="{ storing: glitch }">
                <div v-if="!videos.length" class="novideo"></div>
                <video
                    v-for="(slot, i) in slots"
                    v-show="slot.src"
                    :key="i"
                    :ref="el => { players[i] = el }"
                    class="vid"
                    :class="{ on: activeSlot === i }"
                    :src="slot.src || undefined"
                    :loop="videos.length === 1"
                    muted
                    playsinline
                    preload="auto"
                    disablepictureinpicture
                    @ended="onEnded(i)"
                    @error="onError(i)"
                ></video>
                <div v-if="crt !== 'uit'" class="crt"><i class="roll"></i></div>
                <div class="headfade"></div>
            </div>

            <!-- Standby -->
            <Transition name="gi-fade">
                <div v-if="!isOrder" class="layer">
                    <div class="shade"></div>
                    <div class="floor"></div>
                    <div class="toplogo">
                        <img :src="assets.logo" alt="Game-INN" draggable="false" @pointerdown="holdStart" @pointerup="holdEnd" @pointerleave="holdEnd" @pointercancel="holdEnd">
                    </div>
                    <div class="orbitwrap">
                        <GameInnOrbit class="big" :emblem="assets.emblem" :zone="zone" :label="zoneLabel" />
                    </div>
                    <div class="bottom">
                        <div v-if="settings.title_line_1 || settings.title_line_2" class="title">
                            {{ settings.title_line_1 }}<span v-if="settings.title_line_2">{{ settings.title_line_2 }}</span>
                        </div>
                        <div class="info">
                            <span v-if="hours" class="pill">
                                <span class="dot" :class="{ off: !hours.open, soon: hours.soon }"></span>{{ hours.text }} <b>{{ hours.time }}</b>
                            </span>
                            <span v-if="infoParts.length" class="pill">
                                <template v-for="(part, i) in infoParts" :key="i"><b v-if="part.bold">{{ part.text }}</b><template v-else>{{ part.text }}</template></template>
                            </span>
                        </div>
                        <div v-if="standbyTicker.length" class="ticker">
                            <div class="track" :style="{ animationDuration: `${tickerSeconds(standbyTicker)}s` }">
                                <span v-for="(text, i) in [...standbyTicker, ...standbyTicker]" :key="i">{{ text }}</span>
                            </div>
                        </div>
                    </div>
                </div>
            </Transition>

            <!-- Bestelling -->
            <Transition name="gi-fade">
                <div v-if="isOrder" class="layer">
                    <div class="toplogo left">
                        <img :src="assets.logo" alt="Game-INN" draggable="false" @pointerdown="holdStart" @pointerup="holdEnd" @pointerleave="holdEnd" @pointercancel="holdEnd">
                    </div>
                    <div class="order">
                        <div class="otitle">Jouw <span>bestelling</span></div>
                        <div v-if="metaPills.length" class="meta">
                            <span v-for="pill in metaPills" :key="pill" class="pill">{{ pill }}</span>
                        </div>
                        <div ref="listEl" class="items">
                            <div v-if="!items.length" class="it empty">Je bestelling verschijnt hier zodra we iets toevoegen.</div>
                            <div
                                v-for="item in items"
                                :key="item.name"
                                class="it"
                                :class="{ new: flashing.has(item.name) }"
                                :data-name="item.name"
                            >
                                <div class="q">{{ item.quantity }}</div>
                                <div class="n">
                                    {{ item.name }}
                                    <small v-if="Number(item.quantity) > 1 && item.unit_price">{{ item.quantity }} × {{ euro(item.unit_price) }}</small>
                                </div>
                                <div class="p">{{ euro(Number(item.unit_price || 0) * Number(item.quantity || 0)) }}</div>
                            </div>
                        </div>
                        <div class="total">
                            <div><small>Totaal</small><span class="vat">incl. btw</span></div>
                            <b>{{ euro(total) }}</b>
                        </div>
                    </div>
                    <div v-if="orderTicker.length" class="foot">
                        <div class="ticker">
                            <div class="track" :style="{ animationDuration: `${tickerSeconds(orderTicker)}s` }">
                                <span v-for="(text, i) in [...orderTicker, ...orderTicker, ...orderTicker, ...orderTicker]" :key="i">{{ text }}</span>
                            </div>
                        </div>
                    </div>
                </div>
            </Transition>
        </div>
    </div>
</template>

<style scoped>
.gi-root {
    position: fixed; inset: 0; z-index: 10; overflow: hidden;
    background: #0a0c11; color: #f0eee8;
    font: 14px/1.45 Inter, system-ui, sans-serif;
    -webkit-font-smoothing: antialiased;
}
.gi-stage {
    position: absolute; left: 50%; top: 50%; width: 405px; height: 720px;
    overflow: hidden; isolation: isolate; transform-origin: center;
}

/* ── video + retrofilter (zelfde als de website) ── */
.bg {
    position: absolute; left: 0; right: 0; top: 0; height: 720px; z-index: 0; overflow: hidden;
    transition: height .7s cubic-bezier(.6, 0, .2, 1);
}
.is-order .bg { height: 262px; }
.novideo {
    position: absolute; inset: 0;
    background: radial-gradient(70% 50% at 50% 35%, rgba(62, 107, 177, .35), transparent 70%), #0a0c11;
}
.vid { position: absolute; inset: 0; width: 100%; height: 100%; object-fit: cover; opacity: 0; }
.vid.on { opacity: 1; }
.bg.storing .vid.on { animation: gi-storing .26s steps(4) 1; }
@keyframes gi-storing {
    0% { transform: translate(-10px, 0) skewX(6deg); filter: brightness(1.6) saturate(2) hue-rotate(-20deg); clip-path: inset(10% 0 55% 0); }
    25% { transform: translate(8px, 0); filter: brightness(1.3) saturate(1.6) hue-rotate(25deg); clip-path: inset(40% 0 20% 0); }
    50% { transform: translate(-4px, 0) skewX(-4deg); filter: brightness(1.2); clip-path: inset(0); }
    100% { transform: none; filter: none; }
}
.crt { position: absolute; inset: 0; pointer-events: none; overflow: hidden; }
.crt::before { content: ""; position: absolute; inset: 0; background: repeating-linear-gradient(0deg, rgba(0, 0, 0, .32) 0 1px, transparent 1px 3px); }
.crt::after { content: ""; position: absolute; inset: 0; background: radial-gradient(ellipse 80% 70% at 50% 45%, transparent 55%, rgba(0, 0, 0, .6) 100%); }
.crt .roll {
    position: absolute; left: 0; right: 0; top: 0; height: 22%;
    background: linear-gradient(180deg, transparent, rgba(255, 255, 255, .05) 50%, transparent);
    animation: gi-roll 7s linear infinite;
}
.crt-sterk .crt::before { background: repeating-linear-gradient(0deg, rgba(0, 0, 0, .48) 0 1px, transparent 1px 3px); }
.crt-sterk .crt::after { background: radial-gradient(ellipse 75% 65% at 50% 45%, transparent 50%, rgba(0, 0, 0, .75) 100%); }
.crt-sterk .crt .roll { background: linear-gradient(180deg, transparent, rgba(255, 255, 255, .09) 50%, transparent); animation-duration: 5s; }
.crt-sterk .crt { animation: gi-flicker 4s steps(1) infinite; }
@keyframes gi-roll { from { transform: translateY(-100%); } to { transform: translateY(560%); } }
@keyframes gi-flicker { 0%, 100% { opacity: 1; } 47% { opacity: .85; } 48% { opacity: 1; } 81% { opacity: .9; } 82% { opacity: 1; } }
.headfade {
    position: absolute; inset: 0; pointer-events: none; opacity: 0; transition: opacity .6s ease;
    background: linear-gradient(0deg, #0a0c11 2%, rgba(10, 12, 17, .3) 55%, rgba(10, 12, 17, .7));
}
.is-order .headfade { opacity: 1; }

.layer { position: absolute; inset: 0; z-index: 1; }
.gi-fade-enter-active, .gi-fade-leave-active { transition: opacity .45s ease; }
.gi-fade-enter-from, .gi-fade-leave-to { opacity: 0; }

.shade {
    position: absolute; inset: 0; pointer-events: none;
    background:
        radial-gradient(70% 40% at 50% 62%, rgba(242, 119, 36, .14), transparent 70%),
        radial-gradient(60% 40% at 20% 90%, rgba(62, 107, 177, .35), transparent 70%),
        linear-gradient(180deg, rgba(10, 12, 17, .85) 0%, rgba(10, 12, 17, .15) 18%, rgba(10, 12, 17, .25) 38%, rgba(10, 12, 17, .88) 70%, #0a0c11 100%);
}
.floor {
    position: absolute; left: -30%; right: -30%; bottom: -2px; height: 30%; pointer-events: none;
    background:
        repeating-linear-gradient(90deg, rgba(91, 141, 224, .2) 0 1px, transparent 1px 60px),
        repeating-linear-gradient(0deg, rgba(91, 141, 224, .2) 0 1px, transparent 1px 45px);
    transform: perspective(380px) rotateX(62deg); transform-origin: bottom;
    -webkit-mask-image: linear-gradient(to top, #000 10%, transparent 85%);
    mask-image: linear-gradient(to top, #000 10%, transparent 85%);
    animation: gi-floor 3s linear infinite;
}
@keyframes gi-floor { to { background-position: 0 0, 0 45px; } }

.toplogo { position: absolute; top: 20px; left: 0; right: 0; display: flex; justify-content: center; }
.toplogo img { width: 210px; user-select: none; -webkit-user-drag: none; }
.toplogo.left { justify-content: flex-start; left: 20px; top: 18px; }
.toplogo.left img { width: 160px; }

.orbitwrap { position: absolute; top: 150px; left: 0; right: 0; }
.big { width: 280px; margin: 0 auto; }

.bottom { position: absolute; left: 0; right: 0; bottom: 0; padding: 0 22px; }
.title {
    font: 900 46px/.9 'Big Shoulders Display', 'Arial Narrow', sans-serif;
    text-transform: uppercase; text-align: center; margin-bottom: 12px;
}
.title span {
    display: block;
    background: linear-gradient(90deg, #ff9147, #f27724 40%, #ffb27a);
    -webkit-background-clip: text; background-clip: text; color: transparent;
}
.info { display: flex; gap: 8px; justify-content: center; flex-wrap: wrap; margin-bottom: 18px; }
.pill { border: 1px solid #252b3b; background: rgba(20, 24, 36, .85); border-radius: 999px; padding: 6px 11px; font-size: 12px; }
.pill b { color: #ff9147; font-weight: 600; }
.dot { display: inline-block; width: 7px; height: 7px; border-radius: 9px; background: #3ddc84; margin-right: 6px; animation: gi-ping 1.8s infinite; vertical-align: 1px; }
.dot.off { background: #6b7285; animation: none; }
.dot.soon { background: #f27724; }
@keyframes gi-ping { 0% { box-shadow: 0 0 0 0 rgba(61, 220, 132, .7); } 70% { box-shadow: 0 0 0 7px rgba(61, 220, 132, 0); } 100% { box-shadow: 0 0 0 0 rgba(61, 220, 132, 0); } }

.ticker {
    margin: 0 -30px 18px; background: #f27724; color: #140a02; overflow: hidden; white-space: nowrap;
    transform: rotate(-1.2deg); box-shadow: 0 20px 40px -20px rgba(242, 119, 36, .6);
}
.ticker .track { display: inline-flex; animation: gi-tick 26s linear infinite; padding: 11px 0; }
.ticker span {
    font: 800 12px/1 Orbitron, sans-serif; letter-spacing: .14em; padding: 0 18px;
    display: inline-flex; align-items: center; gap: 18px; text-transform: uppercase;
}
.ticker span::after { content: ""; width: 6px; height: 6px; background: #140a02; transform: rotate(45deg); }
@keyframes gi-tick { to { transform: translateX(-50%); } }

/* ── bestelling ── */
.order { position: absolute; left: 20px; right: 20px; top: 206px; bottom: 64px; display: flex; flex-direction: column; }
.otitle { font: 900 42px/.92 'Big Shoulders Display', 'Arial Narrow', sans-serif; text-transform: uppercase; }
.otitle span { color: #f27724; }
.meta { display: flex; gap: 6px; margin: 10px 0 0; flex-wrap: wrap; }
.meta .pill { font-size: 11px; padding: 4px 10px; }
.items {
    margin-top: 12px; flex: 0 1 auto; min-height: 0; overflow: hidden;
    border: 1px solid #252b3b; background: #141824; border-radius: 16px;
}
.it { display: flex; align-items: center; gap: 10px; padding: 10px 14px; border-top: 1px solid #252b3b; }
.it:first-child { border-top: 0; }
.it.empty { color: #9aa1b3; font-size: 13px; justify-content: center; padding: 18px 14px; }
.q {
    min-width: 28px; height: 24px; padding: 0 6px; border-radius: 8px; background: #1a1f2d; border: 1px solid #252b3b;
    display: flex; align-items: center; justify-content: center; font: 700 12px Sora, sans-serif; color: #ff9147;
}
.n { flex: 1; min-width: 0; font-weight: 500; overflow: hidden; text-overflow: ellipsis; }
.n small { display: block; color: #6b7285; font-size: 11px; font-weight: 400; }
.p { font: 600 13px Sora, sans-serif; white-space: nowrap; }
.it.new { animation: gi-flash 2.6s ease-out 1; }
@keyframes gi-flash { 0% { background: rgba(242, 119, 36, .35); } 60%, 100% { background: transparent; } }
.total {
    margin-top: 12px; flex: none; display: flex; align-items: flex-end; justify-content: space-between;
    padding: 14px 16px; border-radius: 16px;
    background: linear-gradient(135deg, rgba(242, 119, 36, .18), rgba(62, 107, 177, .15));
    border: 1px solid rgba(242, 119, 36, .45);
}
.total small { display: block; font: 600 11px Sora, sans-serif; letter-spacing: .2em; text-transform: uppercase; color: #9aa1b3; }
.total .vat { color: #9aa1b3; font-size: 12px; }
.total b { font: 800 32px Orbitron, sans-serif; color: #ff9147; }
.foot { position: absolute; left: 0; right: 0; bottom: 0; overflow: hidden; padding-top: 10px; }
.foot .ticker { margin: 0 -30px 14px; }
</style>
