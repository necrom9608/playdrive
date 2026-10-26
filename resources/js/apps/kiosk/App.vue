<template>
    <div>
        <SplashScreen
            :visible="showSplash"
            title="PLAYDRIVE"
            subtitle="Kiosk wordt opgestart…"
            status-text="Kiosk"
        />

        <!-- Koppelen: code tonen die de admin in de backoffice ingeeft -->
        <div v-if="phase === 'pairing'" class="flex min-h-screen flex-col items-center justify-center bg-slate-950 px-8 text-center text-white">
            <LinkIcon class="h-14 w-14 text-sky-400" />
            <h1 class="mt-6 text-4xl font-bold">Kiosk koppelen</h1>
            <p class="mt-3 max-w-xl text-lg text-slate-400">
                Ga in de backoffice naar <span class="font-semibold text-slate-200">Speelplekken → Toestellen → Toestel koppelen</span> en geef deze code in.
            </p>

            <div v-if="pairing.code" class="mt-10 rounded-3xl border border-slate-700 bg-slate-900 px-12 py-8 font-mono text-7xl font-bold tracking-[0.3em] text-white">
                {{ pairing.code }}
            </div>
            <div v-else class="mt-10 text-slate-500">Code aanvragen…</div>

            <p v-if="pairing.code" class="mt-6 text-sm text-slate-500">Geldig tot {{ expiresLabel }} · wacht op koppeling…</p>
            <p v-if="message" class="mt-4 text-sm text-amber-300">{{ message }}</p>
        </div>

        <!-- Gekoppeld: placeholder tot de kiosk-content (leaderboards, events, promo's) gebouwd is -->
        <div v-else-if="phase === 'ready'" class="flex min-h-screen flex-col items-center justify-center bg-slate-950 px-8 text-center text-white">
            <img v-if="info.tenant?.logo_url" :src="info.tenant.logo_url" alt="" class="h-28 w-auto object-contain" />
            <h1 class="mt-8 text-4xl font-bold">{{ info.tenant?.display_name || 'Playdrive' }}</h1>
            <p class="mt-3 text-lg text-slate-400">{{ info.device?.name }}<span v-if="info.setup"> · {{ info.setup.name }}</span></p>
            <p class="mt-10 inline-flex items-center gap-2 rounded-full border border-emerald-500/40 bg-emerald-500/10 px-4 py-2 text-sm font-semibold text-emerald-200">
                <CheckCircleIcon class="h-5 w-5" /> Gekoppeld
            </p>
            <p v-if="message" class="mt-4 text-sm text-amber-300">{{ message }}</p>
        </div>

        <div v-else class="min-h-screen bg-slate-950" />
    </div>
</template>

<script setup>
import { computed, onBeforeUnmount, onMounted, reactive, ref } from 'vue'
import { CheckCircleIcon, LinkIcon } from '@heroicons/vue/24/outline'
import SplashScreen from '../../shared/components/SplashScreen.vue'
import { useSplashScreen } from '../../shared/composables/useSplashScreen'
import { fetchMe, getToken, pollPairing, sendHeartbeat, setToken, startPairing } from './services/deviceClient'

const HEARTBEAT_MS = 30000
const RETRY_MS = 5000

const { showSplash, hideSplash } = useSplashScreen(1200)

const phase = ref('boot') // boot | pairing | ready
const message = ref('')
const pairing = reactive({ code: '', secret: '', expiresAt: null, interval: 3 })
const info = reactive({ device: null, tenant: null, setup: null })

let timer = null

const expiresLabel = computed(() => pairing.expiresAt
    ? new Date(pairing.expiresAt).toLocaleTimeString('nl-BE', { hour: '2-digit', minute: '2-digit' })
    : '')

function schedule(fn, ms) {
    window.clearTimeout(timer)
    timer = window.setTimeout(fn, ms)
}

async function boot() {
    if (!getToken()) {
        return beginPairing()
    }
    try {
        Object.assign(info, await fetchMe())
        phase.value = 'ready'
        message.value = ''
        schedule(heartbeat, HEARTBEAT_MS)
    } catch (e) {
        if (e.status === 401) {
            setToken('')
            return beginPairing()
        }
        message.value = 'Geen verbinding met Playdrive, opnieuw proberen…'
        schedule(boot, RETRY_MS)
    }
}

async function beginPairing() {
    phase.value = 'pairing'
    pairing.code = ''
    try {
        const data = await startPairing('kiosk')
        pairing.code = data.pairing_code
        pairing.secret = data.poll_secret
        pairing.expiresAt = data.expires_at
        pairing.interval = data.poll_interval || 3
        message.value = ''
        schedule(poll, pairing.interval * 1000)
    } catch {
        message.value = 'Geen verbinding met Playdrive, opnieuw proberen…'
        schedule(beginPairing, RETRY_MS)
    }
}

async function poll() {
    try {
        const data = await pollPairing(pairing.secret)
        if (data.status === 'paired' && data.token) {
            setToken(data.token)
            return boot()
        }
        message.value = ''
        schedule(poll, pairing.interval * 1000)
    } catch (e) {
        if (e.status === 410) {
            return beginPairing() // verlopen → nieuwe code
        }
        message.value = 'Verbinding verloren, opnieuw proberen…'
        schedule(poll, RETRY_MS)
    }
}

async function heartbeat() {
    try {
        const data = await sendHeartbeat({ status: 'idle' })
        info.setup = data.setup
        message.value = ''
        schedule(heartbeat, HEARTBEAT_MS)
    } catch (e) {
        if (e.status === 401) {
            setToken('')
            return beginPairing() // ontkoppeld in de backoffice
        }
        message.value = 'Geen verbinding met Playdrive.'
        schedule(heartbeat, RETRY_MS * 2)
    }
}

onMounted(async () => {
    await boot()
    await hideSplash()
})

onBeforeUnmount(() => {
    window.clearTimeout(timer)
})
</script>
