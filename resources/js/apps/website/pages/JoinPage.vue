<template>
    <div class="join" :class="`theme-${venue?.theme || 'standaard'}`">
        <div class="glow" aria-hidden="true"></div>

        <main class="wrap">
            <header class="brand">
                <img v-if="venue?.logo_url" :src="venue.logo_url" :alt="venue.name" class="logo">
                <span v-else-if="venue" class="brand-name">{{ venue.name }}</span>
            </header>

            <!-- Laden / fout -->
            <p v-if="step === 'loading'" class="muted center">Laden…</p>
            <section v-else-if="step === 'missing'" class="stack">
                <h1>Niet gevonden</h1>
                <p class="soft">Deze link werkt niet (meer). Vraag de juiste code aan de kassa.</p>
            </section>

            <!-- Nieuw account -->
            <section v-else-if="step === 'form'" class="stack">
                <div class="intro">
                    <h1>Word lid</h1>
                    <p class="soft">Gratis. Je wachtwoord kies je straks via de bevestigingsmail.</p>
                </div>

                <button type="button" class="existing" @click="goLogin()">
                    <span>
                        <strong>Al een Playdrive-account?</strong>
                        <small>Log in, dan hoef je niets in te vullen</small>
                    </span>
                    <ChevronRightIcon class="icon" aria-hidden="true" />
                </button>

                <div class="divider"><span>of maak een nieuw account</span></div>

                <form class="stack form" novalidate @submit.prevent="submitRegister">
                    <div class="grid2">
                        <label class="field">
                            <span>Voornaam</span>
                            <input v-model.trim="form.first_name" type="text" autocomplete="given-name" required>
                        </label>
                        <label class="field">
                            <span>Familienaam</span>
                            <input v-model.trim="form.last_name" type="text" autocomplete="family-name" required>
                        </label>
                    </div>
                    <label class="field">
                        <span>E-mailadres</span>
                        <input v-model.trim="form.email" type="email" autocomplete="email" inputmode="email" required>
                    </label>
                    <div class="grid2">
                        <label class="field">
                            <span>Geboortedatum</span>
                            <input v-model="form.birth_date" type="date" autocomplete="bday" :max="today">
                        </label>
                        <label class="field">
                            <span>Gsm <em>(optioneel)</em></span>
                            <input v-model.trim="form.phone" type="tel" autocomplete="tel" inputmode="tel">
                        </label>
                    </div>

                    <div class="kids">
                        <div class="kids-head">
                            <strong>Kinderen</strong>
                            <small>optioneel</small>
                        </div>
                        <div v-for="(kid, i) in form.children" :key="kid.key" class="kid">
                            <label class="field">
                                <span>Voornaam kind</span>
                                <input v-model.trim="kid.first_name" type="text" autocomplete="off">
                            </label>
                            <label class="field">
                                <span>Geboortedatum</span>
                                <input v-model="kid.birth_date" type="date" :max="today">
                            </label>
                            <button type="button" class="icon-btn" :aria-label="`Kind ${i + 1} verwijderen`" @click="removeKid(i)">
                                <XMarkIcon class="icon" aria-hidden="true" />
                            </button>
                        </div>
                        <button v-if="form.children.length < 8" type="button" class="ghost" @click="addKid">
                            <PlusIcon class="icon" aria-hidden="true" /> Kind toevoegen
                        </button>
                    </div>

                    <label class="check">
                        <input v-model="form.accept_terms" type="checkbox">
                        <span>Ik ga akkoord dat {{ venue.name }} mijn gegevens bewaart om mijn lidmaatschap te beheren.</span>
                    </label>

                    <p v-if="error" class="error" role="alert">{{ error }}</p>

                    <button type="submit" class="primary" :disabled="busy">{{ busy ? 'Even geduld…' : 'Word lid' }}</button>
                </form>
            </section>

            <!-- Bestaand account -->
            <section v-else-if="step === 'login'" class="stack">
                <div class="intro">
                    <h1>Inloggen</h1>
                    <p class="soft">Met je Playdrive-account. Daarna ben je meteen lid van {{ venue.name }}.</p>
                </div>

                <p v-if="notice" class="notice">{{ notice }}</p>

                <form class="stack form" novalidate @submit.prevent="submitLogin">
                    <label class="field">
                        <span>E-mailadres</span>
                        <input v-model.trim="login.email" type="email" autocomplete="email" inputmode="email" required>
                    </label>
                    <label class="field">
                        <span>Wachtwoord</span>
                        <input v-model="login.password" type="password" autocomplete="current-password" required>
                    </label>
                    <label class="check">
                        <input v-model="login.accept_terms" type="checkbox">
                        <span>Ik ga akkoord dat {{ venue.name }} mijn gegevens uit mijn Playdrive-account (naam, e-mail, geboortedatum, gsm) gebruikt voor mijn lidmaatschap.</span>
                    </label>

                    <p v-if="error" class="error" role="alert">{{ error }}</p>

                    <button type="submit" class="primary" :disabled="busy">{{ busy ? 'Even geduld…' : 'Word lid' }}</button>
                    <a href="/member#/forgot-password" class="link center">Wachtwoord vergeten?</a>
                    <button type="button" class="link center" @click="goForm()">Nog geen account? Maak er een</button>
                </form>
            </section>

            <!-- Klaar -->
            <section v-else-if="step === 'done'" class="stack center-col">
                <div class="ok" aria-hidden="true"><CheckIcon class="icon" /></div>
                <div class="intro center">
                    <h1>Welkom, {{ result.first_name }}!</h1>
                    <p class="soft">{{ result.already_member ? `Je was al lid van ${venue.name}.` : `Je bent nu lid van ${venue.name}.` }}</p>
                    <p v-if="result.children?.length" class="soft">
                        Ook lid: {{ result.children.map(c => c.first_name).join(', ') }}
                    </p>
                </div>

                <div class="qr">
                    <img v-if="qr" :src="qr" alt="Persoonlijke QR-code om aan de kassa te tonen">
                </div>
                <p class="qr-caption">Toon dit aan de kassa voor je lidkaart</p>
                <p class="code">{{ result.code }}</p>

                <div v-if="result.needs_confirmation" class="mailbox">
                    <EnvelopeIcon class="icon" aria-hidden="true" />
                    <div>
                        <strong>Bevestig je e-mailadres</strong>
                        <span v-if="result.mail_sent !== false">
                            We stuurden een mail naar <b>{{ result.email }}</b>. Daar kies je ook je wachtwoord voor de app.
                        </span>
                        <span v-else>De mail kon niet verstuurd worden. Vraag aan de kassa om hem opnieuw te sturen.</span>
                    </div>
                </div>
                <div v-if="result.needs_confirmation" class="row">
                    <button type="button" class="link" :disabled="resent || busy" @click="resend">{{ resent ? 'Mail opnieuw verstuurd' : 'Mail opnieuw sturen' }}</button>
                    <button v-if="!result.already_member" type="button" class="link" @click="editEmail">E-mailadres aanpassen</button>
                </div>
            </section>

            <footer class="powered">powered by Playdrive</footer>
        </main>
    </div>
</template>

<script setup>
import { onMounted, reactive, ref } from 'vue'
import QRCode from 'qrcode'
import { CheckIcon, ChevronRightIcon, EnvelopeIcon, PlusIcon, XMarkIcon } from '@heroicons/vue/24/outline'

const props = defineProps({ slug: { type: String, required: true } })

const step = ref('loading')        // loading | missing | form | login | done
const venue = ref(null)
const busy = ref(false)
const error = ref('')
const notice = ref('')
const result = ref(null)
const qr = ref('')
const resent = ref(false)
const today = new Date().toISOString().slice(0, 10)

let kidSeq = 0
const form = reactive({ first_name: '', last_name: '', email: '', birth_date: '', phone: '', children: [], accept_terms: false })
const login = reactive({ email: '', password: '', accept_terms: false })

const base = () => `/api/join/${encodeURIComponent(props.slug)}`

async function api(path, body) {
    const res = await fetch(base() + path, {
        method: body ? 'POST' : 'GET',
        headers: { Accept: 'application/json', ...(body ? { 'Content-Type': 'application/json' } : {}) },
        body: body ? JSON.stringify(body) : undefined,
    })
    const data = await res.json().catch(() => ({}))
    if (!res.ok) {
        const err = new Error(firstError(data) || 'Er liep iets mis. Probeer opnieuw.')
        err.status = res.status
        err.data = data
        throw err
    }
    return data
}

function firstError(data) {
    const errors = data?.errors ? Object.values(data.errors).flat() : []
    return errors[0] || data?.message || ''
}

function addKid() {
    form.children.push({ key: ++kidSeq, first_name: '', birth_date: '' })
}
function removeKid(i) {
    form.children.splice(i, 1)
}

function goLogin(message = '') {
    error.value = ''
    notice.value = message
    if (!login.email) login.email = form.email
    step.value = 'login'
    window.scrollTo(0, 0)
}
function goForm() {
    error.value = ''
    notice.value = ''
    step.value = 'form'
    window.scrollTo(0, 0)
}

function validateRegister() {
    if (!form.first_name || !form.last_name) return 'Vul je voornaam en familienaam in.'
    if (!/^\S+@\S+\.\S+$/.test(form.email)) return 'Vul een geldig e-mailadres in.'
    if (form.children.some(k => !k.first_name)) return 'Vul de voornaam van elk kind in of verwijder de lege rij.'
    if (!form.accept_terms) return 'Ga akkoord om lid te worden.'
    return ''
}

async function submitRegister() {
    error.value = validateRegister()
    if (error.value || busy.value) return
    busy.value = true
    try {
        const { data } = await api('/register', {
            first_name: form.first_name,
            last_name: form.last_name,
            email: form.email,
            birth_date: form.birth_date || null,
            phone: form.phone || null,
            accept_terms: true,
            children: form.children.map(k => ({ first_name: k.first_name, birth_date: k.birth_date || null })),
        })
        await finish(data)
    } catch (e) {
        if (e.status === 409 && e.data?.code === 'account_exists') {
            goLogin('Dit e-mailadres heeft al een Playdrive-account. Log in om lid te worden.')
        } else {
            error.value = e.message
        }
    } finally {
        busy.value = false
    }
}

async function submitLogin() {
    error.value = ''
    if (!login.email || !login.password) { error.value = 'Vul je e-mailadres en wachtwoord in.'; return }
    if (!login.accept_terms) { error.value = 'Ga akkoord om lid te worden.'; return }
    if (busy.value) return
    busy.value = true
    try {
        const { data } = await api('/login', { email: login.email, password: login.password, accept_terms: true })
        await finish(data)
    } catch (e) {
        error.value = e.message
    } finally {
        busy.value = false
    }
}

async function finish(data) {
    result.value = data
    resent.value = false
    try {
        qr.value = await QRCode.toDataURL(data.code, { margin: 1, width: 360, errorCorrectionLevel: 'M' })
    } catch {
        qr.value = ''
    }
    step.value = 'done'
    window.scrollTo(0, 0)
}

async function resend() {
    if (!result.value || busy.value) return
    busy.value = true
    try {
        await api('/resend', { membership_id: result.value.membership_id, email: result.value.email })
        resent.value = true
    } catch (e) {
        error.value = e.message
    } finally {
        busy.value = false
    }
}

function editEmail() {
    form.email = ''
    goForm()
}

function loadFonts() {
    if (document.getElementById('join-fonts')) return
    const link = document.createElement('link')
    link.id = 'join-fonts'
    link.rel = 'stylesheet'
    link.href = 'https://fonts.googleapis.com/css2?family=Big+Shoulders+Display:wght@700;900&family=Inter:wght@400;500;600;700&display=swap'
    document.head.appendChild(link)
}

onMounted(async () => {
    try {
        const { data } = await api('')
        venue.value = data
        document.title = `Lid worden · ${data.name}`
        if (data.theme === 'gameinn') loadFonts()
        step.value = 'form'
    } catch {
        step.value = 'missing'
    }
})
</script>

<style scoped>
.join {
    --bg: #071327; --card: rgba(12, 26, 58, .9); --border: rgba(75, 98, 148, .35); --field: rgba(255, 255, 255, .05);
    --accent: #3b82f6; --accent-soft: rgba(59, 130, 246, .12); --on-accent: #ffffff; --link: #93c5fd;
    --text: #ffffff; --soft: #c3d0ea; --muted: #8fa1c4; --head: 'Instrument Sans', ui-sans-serif, system-ui, sans-serif;
    --head-weight: 700; --head-case: none;
    position: relative; min-height: 100vh; overflow: hidden; background: var(--bg); color: var(--text);
    font-family: Inter, 'Instrument Sans', ui-sans-serif, system-ui, sans-serif; font-size: 15px; line-height: 1.45;
}
.theme-gameinn {
    --bg: #0a0c11; --card: rgba(20, 24, 36, .9); --border: #252b3b; --field: rgba(255, 255, 255, .05);
    --accent: #f27724; --accent-soft: rgba(91, 141, 224, .1); --on-accent: #140a02; --link: #ff9147;
    --text: #f0eee8; --soft: #c9cbd4; --muted: #9aa1b3; --head: 'Big Shoulders Display', 'Arial Narrow', sans-serif;
    --head-weight: 900; --head-case: uppercase;
}
.glow {
    position: absolute; right: -140px; top: -160px; width: 420px; height: 420px; border-radius: 50%; pointer-events: none;
    background: radial-gradient(circle, color-mix(in srgb, var(--accent) 24%, transparent), transparent 65%);
}
.wrap { position: relative; max-width: 440px; margin: 0 auto; padding: 20px 20px 28px; display: flex; flex-direction: column; gap: 22px; min-height: 100vh; box-sizing: border-box; }
.brand { display: flex; align-items: center; min-height: 44px; }
.logo { max-height: 48px; max-width: 180px; width: auto; }
.brand-name { font-family: var(--head); font-weight: var(--head-weight); text-transform: var(--head-case); font-size: 22px; letter-spacing: .04em; }

.stack { display: flex; flex-direction: column; gap: 16px; }
.form { gap: 14px; }
.intro { display: flex; flex-direction: column; gap: 6px; }
h1 { margin: 0; font-family: var(--head); font-weight: var(--head-weight); text-transform: var(--head-case); font-size: 34px; line-height: 1; }
.soft { margin: 0; color: var(--soft); }
.muted { color: var(--muted); }
.center { text-align: center; }
.center-col { align-items: center; text-align: center; }
.grid2 { display: grid; grid-template-columns: repeat(2, minmax(0, 1fr)); gap: 10px; }

.existing {
    display: flex; align-items: center; justify-content: space-between; gap: 12px; padding: 14px 16px; border-radius: 16px; text-align: left;
    background: var(--accent-soft); border: 1px solid color-mix(in srgb, var(--link) 35%, transparent); color: var(--text); font: inherit; cursor: pointer;
}
.existing strong { display: block; font-size: 14px; }
.existing small { display: block; font-size: 13px; color: var(--soft); margin-top: 2px; }
.divider { display: flex; align-items: center; gap: 12px; font-size: 12px; color: var(--muted); }
.divider::before, .divider::after { content: ''; flex: 1; height: 1px; background: rgba(255, 255, 255, .1); }

.field { display: flex; flex-direction: column; gap: 6px; min-width: 0; }
.field > span { font-size: 13px; font-weight: 500; color: var(--soft); }
.field em { font-style: normal; color: var(--muted); font-weight: 400; }
.field input {
    height: 48px; box-sizing: border-box; width: 100%; padding: 0 14px; border-radius: 12px; border: 1px solid rgba(255, 255, 255, .14);
    background: var(--field); color: var(--text); font: inherit; font-size: 16px; color-scheme: dark; outline: none;
}
.field input:focus { border-color: var(--accent); }

.kids { display: flex; flex-direction: column; gap: 10px; padding: 14px; border-radius: 16px; border: 1px dashed rgba(255, 255, 255, .18); }
.kids-head { display: flex; justify-content: space-between; align-items: center; }
.kids-head small { color: var(--muted); font-size: 12px; }
.kid { display: grid; grid-template-columns: minmax(0, 1fr) minmax(0, 1fr) 40px; gap: 8px; align-items: end; }

.check { display: flex; gap: 12px; align-items: flex-start; font-size: 14px; color: var(--soft); cursor: pointer; }
.check input { width: 20px; height: 20px; margin: 1px 0 0; flex: none; accent-color: var(--accent); }

.primary {
    height: 54px; border: 0; border-radius: 16px; background: var(--accent); color: var(--on-accent); font: inherit; font-size: 17px; font-weight: 700; cursor: pointer;
}
.primary:disabled { opacity: .6; cursor: default; }
.ghost {
    height: 44px; border-radius: 12px; border: 1px solid rgba(255, 255, 255, .14); background: transparent; color: var(--text);
    font: inherit; font-size: 14px; font-weight: 600; display: flex; align-items: center; justify-content: center; gap: 8px; cursor: pointer;
}
.icon-btn { width: 40px; height: 48px; border: 0; border-radius: 10px; background: transparent; color: var(--muted); display: flex; align-items: center; justify-content: center; cursor: pointer; }
.icon { width: 20px; height: 20px; flex: none; }
.link { background: none; border: 0; padding: 8px 4px; color: var(--link); font: inherit; font-size: 14px; cursor: pointer; text-decoration: none; }
.link:disabled { color: var(--muted); cursor: default; }
.row { display: flex; justify-content: space-between; width: 100%; gap: 12px; flex-wrap: wrap; }

.error { margin: 0; padding: 12px 14px; border-radius: 12px; background: rgba(244, 63, 94, .12); border: 1px solid rgba(244, 63, 94, .35); color: #fecdd3; font-size: 14px; }
.notice { margin: 0; padding: 12px 14px; border-radius: 12px; background: var(--accent-soft); border: 1px solid color-mix(in srgb, var(--link) 35%, transparent); font-size: 14px; color: var(--soft); }

.ok { width: 56px; height: 56px; border-radius: 50%; background: rgba(52, 211, 153, .15); border: 1px solid rgba(52, 211, 153, .4); display: flex; align-items: center; justify-content: center; color: #34d399; }
.ok .icon { width: 28px; height: 28px; }
.qr { background: #fff; border-radius: 24px; padding: 16px; box-shadow: 0 0 0 4px var(--accent); line-height: 0; }
.qr img { width: 200px; height: 200px; image-rendering: pixelated; }
.qr-caption { margin: 0; font-weight: 600; }
.code { margin: -8px 0 0; font-size: 13px; letter-spacing: .12em; color: var(--muted); }
.mailbox {
    display: flex; gap: 12px; align-items: flex-start; text-align: left; padding: 14px; border-radius: 16px; width: 100%; box-sizing: border-box;
    background: rgba(250, 204, 21, .08); border: 1px solid rgba(250, 204, 21, .3); color: #facc15;
}
.mailbox strong { display: block; color: #fef08a; font-size: 14px; margin-bottom: 4px; }
.mailbox span { display: block; color: var(--soft); font-size: 13px; }
.mailbox b { color: var(--text); }

.powered { margin-top: auto; text-align: center; font-size: 12px; color: var(--muted); }
</style>
