<template>
    <!-- Ontworpen op 1920×1080 en geschaald naar het scherm (75" in 1080p of 4K). -->
    <div class="gs-viewport">
        <div class="gs" :style="{ transform: `scale(${scale})` }">
            <header class="gs-head">
                <div class="gs-brand">
                    <span class="gs-logo">GAME-INN</span>
                    <span class="gs-group">{{ data.group?.name }}</span>
                </div>
                <time class="gs-clock">{{ clock }}</time>
            </header>

            <!-- Volgend spel: de eerste seconden na een wissel -->
            <main v-if="mode === 'switch'" class="gs-switch">
                <span class="gs-kicker gs-kicker--big">Volgend spel</span>
                <h1 class="gs-switch-name">{{ data.game?.name }}</h1>
                <div class="gs-bar" aria-hidden="true"><span /></div>
                <p class="gs-switch-text">Alle racers starten samen. Kies een stoel en maak je klaar.</p>
            </main>

            <main v-else-if="mode === 'empty'" class="gs-switch">
                <span class="gs-kicker gs-kicker--big">Even geduld</span>
                <h1 class="gs-switch-name gs-switch-name--small">Straks terug</h1>
            </main>

            <main v-else class="gs-main">
                <div class="gs-cover">
                    <img v-if="data.game?.cover_url" :src="data.game.cover_url" alt="" />
                    <span v-else class="gs-cover-name">{{ data.game?.name }}</span>
                    <span class="gs-now">NU</span>
                </div>

                <div class="gs-info">
                    <span class="gs-kicker">Speelt nu</span>
                    <h1 class="gs-title" :class="titleSize">{{ data.game?.name }}</h1>
                    <p class="gs-sub">Race tegen elkaar: alle {{ seatCount }} racers spelen samen.</p>

                    <!-- Wisseltijd voorbij: wacht op het einde van de race -->
                    <div v-if="mode === 'waiting'" class="gs-last">
                        <span class="gs-kicker">Laatste race</span>
                        <span class="gs-last-name">Na deze race: {{ data.next?.name }}</span>
                        <span class="gs-last-text">Rijd je race rustig uit. Ten laatste om {{ time(data.switch_by) }} wisselen alle racers samen.</span>
                    </div>

                    <div v-else class="gs-count">
                        <div>
                            <span class="gs-label">{{ mode === 'paused' ? 'Blijft nog even staan' : 'Volgend spel over' }}</span>
                            <span v-if="mode !== 'paused'" class="gs-timer">{{ remaining }}</span>
                        </div>
                        <div v-if="data.next" class="gs-then">
                            <span class="gs-label">Daarna</span>
                            <span class="gs-then-name">{{ data.next.name }}</span>
                        </div>
                    </div>
                </div>
            </main>

            <footer class="gs-foot" :class="{ 'gs-foot--wide': mode === 'switch' || mode === 'empty' }">
                <div>
                    <span class="gs-label gs-label--block">Racers</span>
                    <div class="gs-seats" :style="{ gridTemplateColumns: `repeat(${Math.max(seatCount, 1)}, minmax(0, 1fr))` }">
                        <div v-for="s in data.seats" :key="s.id" class="gs-seat" :class="`gs-seat--${seatState(s)}`">
                            <span class="gs-seat-n">{{ s.number }}</span>
                            <span class="gs-seat-l">{{ seatLabel(s) }}</span>
                        </div>
                    </div>
                </div>

                <div v-if="mode !== 'switch' && mode !== 'empty' && data.programme?.length">
                    <span class="gs-label gs-label--block">Programma</span>
                    <ol class="gs-prog">
                        <li v-for="(p, i) in data.programme" :key="i">
                            <span class="gs-prog-t" :class="{ 'gs-prog-t--next': i === 0 }">{{ i === 0 && mode === 'waiting' ? 'straks' : p.eta ? `±${time(p.eta)}` : '—' }}</span>
                            {{ p.name }}
                        </li>
                    </ol>
                </div>
            </footer>

            <p v-if="offline" class="gs-offline">Geen verbinding met PlayDrive</p>
        </div>
    </div>
</template>

<script setup>
import { computed, onBeforeUnmount, onMounted, ref } from 'vue'

// data = antwoord van GET /api/device/v1/group-display (zie App\Domain\Setups\DeviceRotation::display)
const props = defineProps({
    data: { type: Object, required: true },
    offline: { type: Boolean, default: false },
    offset: { type: Number, default: 0 }, // klok van de server min die van dit toestel (ms)
})

const SWITCH_SECONDS = 45 // zo lang "Volgend spel" na een wissel

const now = ref(Date.now())
const scale = ref(1)
let tick = null

// Klok van de server volgen (de mini-pc kan scheef lopen).
const serverNow = computed(() => now.value + props.offset)

const mode = computed(() => {
    const d = props.data
    if (!d.game || d.state === 'empty') return 'empty'
    if (d.started_at && serverNow.value - Date.parse(d.started_at) < SWITCH_SECONDS * 1000) return 'switch'
    if (d.state === 'paused') return 'paused'
    if (d.state === 'waiting') return 'waiting'
    return 'playing'
})

const seatCount = computed(() => props.data.seats?.length || 0)

// Lange namen kleiner, zodat stoelen en programma altijd op het scherm blijven.
const titleSize = computed(() => {
    const n = (props.data.game?.name || '').length
    return n <= 16 ? 'gs-title--l' : n <= 28 ? 'gs-title--m' : 'gs-title--s'
})

const remaining = computed(() => {
    if (!props.data.due_at) return '--:--'
    const s = Math.max(0, Math.round((Date.parse(props.data.due_at) - serverNow.value) / 1000))
    return `${Math.floor(s / 60)}:${String(s % 60).padStart(2, '0')}`
})

const clock = computed(() => new Date(serverNow.value).toLocaleTimeString('nl-BE', { hour: '2-digit', minute: '2-digit' }))

function time(iso) {
    return iso ? new Date(iso).toLocaleTimeString('nl-BE', { hour: '2-digit', minute: '2-digit' }) : ''
}

function seatState(s) {
    return mode.value === 'switch' && s.state !== 'off' ? 'loading' : s.state
}

function seatLabel(s) {
    return { busy: 'BEZET', free: 'VRIJ · STAP IN', loading: 'LAADT…', off: 'UIT' }[seatState(s)] || ''
}

function fit() {
    scale.value = Math.min(window.innerWidth / 1920, window.innerHeight / 1080)
}

onMounted(() => {
    fit()
    window.addEventListener('resize', fit)
    tick = window.setInterval(() => (now.value = Date.now()), 500)
})
onBeforeUnmount(() => {
    window.removeEventListener('resize', fit)
    window.clearInterval(tick)
})
</script>

<style scoped>
@font-face { font-family: 'GS Big Shoulders'; src: url('../assets/fonts/big-shoulders-display-latin-900-normal.woff2') format('woff2'); font-weight: 900; font-display: block; }
@font-face { font-family: 'GS Orbitron'; src: url('../assets/fonts/orbitron-latin-700-normal.woff2') format('woff2'); font-weight: 700; font-display: block; }
@font-face { font-family: 'GS Orbitron'; src: url('../assets/fonts/orbitron-latin-800-normal.woff2') format('woff2'); font-weight: 800; font-display: block; }
@font-face { font-family: 'GS Inter'; src: url('../assets/fonts/inter-latin-400-normal.woff2') format('woff2'); font-weight: 400; font-display: block; }
@font-face { font-family: 'GS Inter'; src: url('../assets/fonts/inter-latin-600-normal.woff2') format('woff2'); font-weight: 600; font-display: block; }
@font-face { font-family: 'GS Vermin'; src: url('../assets/fonts/vermin-vibes-2-soft.woff2') format('woff2'); font-display: block; }

.gs-viewport {
    position: fixed;
    inset: 0;
    background: #0a0c11;
    overflow: hidden;
    cursor: none;
}
.gs {
    --ink: #0a0c11; --panel: #141824; --line: #252b3b; --text: #f0eee8; --soft: #c9cdd8;
    --muted: #9aa1b3; --dim: #6b7285; --orange: #f27724; --blue: #3e6bb1; --blue-text: #8fb0e6;
    position: absolute;
    left: 50%;
    top: 50%;
    width: 1920px;
    height: 1080px;
    margin: -540px 0 0 -960px;
    transform-origin: center;
    box-sizing: border-box;
    display: grid;
    grid-template-rows: auto minmax(0, 1fr) auto;
    background: var(--ink);
    color: var(--text);
    font-family: 'GS Inter', 'Segoe UI', sans-serif;
}

.gs-head { display: flex; align-items: center; justify-content: space-between; padding: 40px 72px 0; }
.gs-brand { display: flex; align-items: baseline; gap: 28px; }
.gs-logo { font-family: 'GS Vermin', 'GS Orbitron', sans-serif; font-size: 56px; color: var(--orange); letter-spacing: 0.02em; }
.gs-group { font: 800 26px/1 'GS Orbitron', sans-serif; letter-spacing: 0.32em; color: var(--muted); text-transform: uppercase; }
.gs-clock { font: 700 44px/1 'GS Orbitron', sans-serif; color: var(--soft); letter-spacing: 0.06em; }

.gs-kicker { font: 700 24px/1 'GS Orbitron', sans-serif; letter-spacing: 0.3em; color: var(--orange); text-transform: uppercase; }
.gs-kicker--big { font-size: 40px; letter-spacing: 0.4em; font-weight: 800; }
.gs-label { font: 700 22px/1 'GS Orbitron', sans-serif; letter-spacing: 0.24em; color: var(--muted); text-transform: uppercase; }
.gs-label--block { display: block; margin-bottom: 18px; }

.gs-main { display: grid; grid-template-columns: 760px 1fr; gap: 72px; padding: 40px 72px 0; align-items: center; min-height: 0; }
.gs-cover {
    position: relative; height: 100%; max-height: 540px; box-sizing: border-box; border-radius: 24px; border: 2px solid var(--line); overflow: hidden;
    background: repeating-linear-gradient(-45deg, #141824 0 40px, #11141e 40px 80px);
    display: flex; align-items: center; justify-content: center;
}
.gs-cover img { width: 100%; height: 100%; object-fit: cover; }
.gs-cover-name {
    padding: 0 48px; text-align: center; font: 900 96px/0.9 'GS Big Shoulders', sans-serif; text-transform: uppercase;
    color: transparent; -webkit-text-stroke: 2px var(--orange);
}
.gs-now {
    position: absolute; top: 24px; left: 24px; padding: 10px 18px; border-radius: 999px; background: var(--orange);
    color: var(--ink); font: 800 20px/1 'GS Orbitron', sans-serif; letter-spacing: 0.2em;
}

.gs-info { display: flex; flex-direction: column; gap: 24px; min-width: 0; }
.gs-title { margin: 0; font: 900 132px/0.9 'GS Big Shoulders', sans-serif; text-transform: uppercase; overflow-wrap: anywhere; }
.gs-title--m { font-size: 104px; }
.gs-title--s { font-size: 84px; }
.gs-sub { margin: 0; font: 400 30px/1.4 'GS Inter', sans-serif; color: var(--muted); }

.gs-count { display: flex; align-items: flex-end; gap: 36px; margin-top: 8px; padding-top: 28px; border-top: 2px solid var(--line); }
.gs-count .gs-label { display: block; }
.gs-timer { display: block; margin-top: 14px; font: 800 112px/1 'GS Orbitron', sans-serif; letter-spacing: 0.02em; }
.gs-then { padding-bottom: 14px; }
.gs-then-name { display: block; margin-top: 12px; font: 900 56px/1 'GS Big Shoulders', sans-serif; text-transform: uppercase; }

.gs-last {
    margin-top: 8px; padding: 32px 40px; border-radius: 24px; border: 2px solid var(--orange);
    background: rgba(242, 119, 36, 0.10); display: flex; flex-direction: column; gap: 16px;
}
.gs-last-name { font: 900 76px/0.95 'GS Big Shoulders', sans-serif; text-transform: uppercase; }
.gs-last-text { font: 400 28px/1.4 'GS Inter', sans-serif; color: var(--soft); }

.gs-switch { display: flex; flex-direction: column; align-items: center; justify-content: center; gap: 40px; text-align: center; padding: 0 120px; }
.gs-switch-name { margin: 0; font: 900 260px/0.85 'GS Big Shoulders', sans-serif; text-transform: uppercase; }
.gs-switch-name--small { font-size: 160px; }
.gs-switch-text { margin: 0; font: 600 36px/1.4 'GS Inter', sans-serif; color: var(--soft); }
.gs-bar { width: 720px; height: 8px; border-radius: 4px; background: var(--line); overflow: hidden; }
.gs-bar span { display: block; width: 35%; height: 100%; background: var(--orange); animation: gs-bar 1.4s ease-in-out infinite; }
@keyframes gs-bar { from { transform: translateX(-100%); } to { transform: translateX(290%); } }

.gs-foot { display: grid; grid-template-columns: 1fr 560px; gap: 56px; padding: 36px 72px 48px; align-items: end; }
.gs-foot--wide { grid-template-columns: 1fr; }
.gs-seats { display: grid; gap: 16px; }
.gs-seat {
    height: 140px; box-sizing: border-box; border-radius: 18px; padding: 18px 20px; display: flex; flex-direction: column;
    justify-content: space-between; border: 2px solid var(--line); background: var(--panel);
}
.gs-seat-n { font: 800 40px/1 'GS Orbitron', sans-serif; }
.gs-seat-l { font: 700 19px/1.15 'GS Orbitron', sans-serif; letter-spacing: 0.08em; }
.gs-seat--busy { border-color: var(--orange); background: rgba(242, 119, 36, 0.14); }
.gs-seat--busy .gs-seat-n { color: var(--orange); }
.gs-seat--free { border-color: var(--blue); background: rgba(62, 107, 177, 0.10); color: var(--blue-text); }
.gs-seat--loading { border-color: var(--blue); background: rgba(62, 107, 177, 0.10); color: var(--blue-text); }
.gs-seat--loading .gs-seat-l { animation: gs-pulse 1.2s ease-in-out infinite; }
.gs-seat--off { color: var(--dim); }
@keyframes gs-pulse { 50% { opacity: 0.4; } }

.gs-prog { list-style: none; margin: 0; padding: 0; display: flex; flex-direction: column; gap: 10px; }
.gs-prog li { display: flex; gap: 24px; align-items: baseline; font: 600 30px/1.2 'GS Inter', sans-serif; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.gs-prog-t { flex: none; width: 130px; font: 700 28px/1 'GS Orbitron', sans-serif; color: var(--muted); }
.gs-prog-t--next { color: var(--orange); }

.gs-offline {
    position: absolute; right: 72px; top: 110px; margin: 0; padding: 8px 16px; border-radius: 999px;
    background: rgba(242, 119, 36, 0.15); color: var(--orange); font: 600 20px/1 'GS Inter', sans-serif;
}
</style>
