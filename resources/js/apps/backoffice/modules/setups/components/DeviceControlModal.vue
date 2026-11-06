<template>
    <ModalDialog
        :open="open"
        :title="device ? `Bediening · ${device.name || `Toestel #${device.id}`}` : 'Bediening'"
        :description="statusLine"
        @close="$emit('close')"
    >
        <div v-if="!device?.online" class="mb-4 rounded-2xl border border-amber-500/30 bg-amber-500/10 px-4 py-3 text-sm text-amber-200">
            Dit toestel is offline. Opdrachten vervallen als het ze niet binnen 2 minuten ophaalt.
        </div>
        <div v-if="error" class="mb-4 rounded-2xl border border-red-800 bg-red-950/40 px-4 py-3 text-sm text-red-300">{{ error }}</div>

        <div v-if="device?.checks?.input_group === false" class="mb-4 rounded-2xl border border-amber-500/30 bg-amber-500/10 px-4 py-3 text-sm text-amber-200">
            <p class="font-semibold">Groep "input" ontbreekt op deze pc</p>
            <p class="mt-1 text-amber-200/80">Playscope ziet toetsenbord en muis niet, waardoor games niet afsluiten bij inactiviteit. Eén keer uitvoeren op de pc, daarna afmelden en opnieuw aanmelden:</p>
            <pre class="mt-2 overflow-x-auto rounded-xl bg-black/40 px-3 py-2 text-xs text-amber-100">grep -q '^input:' /etc/group || grep '^input:' /usr/lib/group | sudo tee -a /etc/group
sudo usermod -aG input $USER</pre>
            <p class="mt-2 text-amber-200/80">Of op de pc zelf: beheermenu (Ctrl+Alt+P of Select + Start 3 s, pincode) → <b>Groep input instellen</b>.</p>
        </div>

        <div class="grid gap-2 sm:grid-cols-2">
            <button
                v-for="a in actions"
                :key="a.command"
                type="button"
                :disabled="sending"
                class="flex items-center gap-3 rounded-xl border px-4 py-3 text-left text-sm font-semibold transition disabled:opacity-60"
                :class="a.danger
                    ? 'border-rose-500/30 bg-rose-500/10 text-rose-200 hover:bg-rose-500/20'
                    : 'border-slate-700 bg-slate-950 text-slate-200 hover:bg-slate-800'"
                @click="send(a)"
            >
                <component :is="a.icon" class="h-5 w-5 shrink-0" />
                <span>{{ a.label }}<span class="block text-xs font-normal text-slate-500">{{ a.hint }}</span></span>
            </button>
        </div>

        <!-- Bericht op het scherm -->
        <div class="mt-5 space-y-2">
            <span class="block text-sm font-medium text-slate-300">Bericht op het scherm</span>
            <div class="flex flex-wrap gap-2">
                <input
                    v-model="message"
                    type="text"
                    maxlength="200"
                    placeholder="bv. Nog 5 minuten speeltijd"
                    class="input min-w-0 flex-1"
                    @keydown.enter.prevent="sendMessage"
                >
                <select v-model.number="seconds" class="input w-28">
                    <option :value="10">10 s</option>
                    <option :value="15">15 s</option>
                    <option :value="30">30 s</option>
                    <option :value="60">1 min</option>
                </select>
                <button type="button" :disabled="sending || !message.trim()" class="rounded-xl bg-blue-600 px-4 py-2 text-sm font-semibold text-white transition hover:bg-blue-500 disabled:opacity-60" @click="sendMessage">
                    Tonen
                </button>
            </div>
            <p class="text-xs text-slate-500">Zichtbaar in Playscope. Tijdens een game ligt de game erboven.</p>
        </div>

        <!-- Inactiviteit: game afsluiten als niemand meer speelt -->
        <div class="mt-5 space-y-2">
            <span class="block text-sm font-medium text-slate-300">Game afsluiten bij inactiviteit</span>
            <div class="flex flex-wrap items-center gap-2">
                <select v-model.number="idleMinutes" :disabled="savingIdle" class="input w-44" @change="saveIdle">
                    <option v-for="o in idleOptions" :key="o.value" :value="o.value">{{ o.label }}</option>
                </select>
                <span v-if="idleSaved" class="text-xs text-emerald-400">Bewaard · actief binnen een minuut</span>
            </div>
            <p class="text-xs text-slate-500">
                Zonder controller-, toetsenbord- of muisinput eerst 60 s “Nog aan het spelen?”, daarna sluit de game.
            </p>
        </div>

        <!-- Laatste opdrachten -->
        <div v-if="commands.length" class="mt-6">
            <span class="block text-sm font-medium text-slate-300">Laatste opdrachten</span>
            <ul class="mt-2 divide-y divide-slate-800 rounded-2xl border border-slate-800">
                <li v-for="c in commands" :key="c.id" class="flex items-start gap-3 px-4 py-2.5 text-sm">
                    <span class="mt-1.5 h-2 w-2 shrink-0 rounded-full" :class="dotClass(c.status)" />
                    <div class="min-w-0 flex-1">
                        <div class="text-slate-200">
                            {{ c.label }}<span v-if="c.payload?.text" class="text-slate-400"> · “{{ c.payload.text }}”</span>
                        </div>
                        <div class="truncate text-xs text-slate-500">
                            {{ statusLabel(c.status) }} · {{ time(c.created_at) }}<span v-if="c.result"> · {{ c.result }}</span>
                        </div>
                    </div>
                </li>
            </ul>
        </div>

        <div class="mt-6 flex">
            <button type="button" class="rounded-xl border border-slate-700 px-4 py-3 text-sm font-medium text-slate-300 transition hover:bg-slate-800" @click="$emit('close')">Sluiten</button>
        </div>
    </ModalDialog>
</template>

<script setup>
import { computed, markRaw, onBeforeUnmount, ref, watch } from 'vue'
import {
    ArrowPathIcon,
    ArrowsPointingInIcon,
    ArrowsPointingOutIcon,
    CloudArrowDownIcon,
    PowerIcon,
    StopCircleIcon,
} from '@heroicons/vue/24/outline'
import ModalDialog from '../../../components/ModalDialog.vue'
import { fetchDeviceCommands, sendDeviceCommand, updateVenueDeviceSettings } from '../services/setupApi'

const props = defineProps({
    open: { type: Boolean, default: false },
    device: { type: Object, default: null },
})
const emit = defineEmits(['close', 'updated'])

const actions = [
    { command: 'stop_game', label: 'Game stoppen', hint: 'Terug naar het menu', icon: markRaw(StopCircleIcon) },
    { command: 'restart_app', label: 'Playscope herstarten', hint: 'Als het menu hangt', icon: markRaw(ArrowPathIcon) },
    { command: 'update_now', label: 'Update nu installeren', hint: 'Als er een update klaarstaat', icon: markRaw(CloudArrowDownIcon) },
    { command: 'kiosk_off', label: 'Kioskmodus uit', hint: 'Gewoon venster tot herstart', icon: markRaw(ArrowsPointingInIcon) },
    { command: 'kiosk_on', label: 'Kioskmodus aan', hint: 'Volledig scherm, niet te sluiten', icon: markRaw(ArrowsPointingOutIcon) },
    { command: 'reboot', label: 'Pc herstarten', hint: 'Een lopende game stopt', icon: markRaw(ArrowPathIcon), danger: true, confirm: 'herstarten' },
    { command: 'shutdown', label: 'Pc uitzetten', hint: 'Enkel weer aan ter plaatse', icon: markRaw(PowerIcon), danger: true, confirm: 'uitzetten' },
]

const commands = ref([])
const device = ref(null)
const sending = ref(false)
const error = ref('')
const message = ref('')
const seconds = ref(15)
let timer = null

// Inactiviteit (minuten; 0 = uit). Standaard 10.
const idleOptions = [
    { value: 0, label: 'Uit' },
    { value: 5, label: 'Na 5 minuten' },
    { value: 10, label: 'Na 10 minuten' },
    { value: 15, label: 'Na 15 minuten' },
    { value: 20, label: 'Na 20 minuten' },
    { value: 30, label: 'Na 30 minuten' },
    { value: 60, label: 'Na 1 uur' },
]
const idleMinutes = ref(10)
const savingIdle = ref(false)
const idleSaved = ref(false)

async function saveIdle() {
    savingIdle.value = true
    idleSaved.value = false
    error.value = ''
    try {
        const d = await updateVenueDeviceSettings(props.device.id, { idle_minutes: idleMinutes.value })
        device.value = { ...device.value, ...d }
        idleSaved.value = true
        emit('updated', d)
    } catch (e) {
        error.value = e.message || 'Bewaren mislukt.'
        idleMinutes.value = device.value?.idle_minutes ?? 10
    } finally {
        savingIdle.value = false
    }
}

const statusLine = computed(() => {
    const d = device.value
    if (!d) return ''
    const state = d.state?.status === 'playing' ? `speelt${d.state.game ? `: ${d.state.game}` : ''}` : d.state?.status || ''
    return [d.online ? 'Online' : 'Offline', state, d.app_version && `v${d.app_version}`].filter(Boolean).join(' · ')
})

async function load() {
    if (!props.device?.id) return
    try {
        const res = await fetchDeviceCommands(props.device.id)
        commands.value = res.commands ?? []
        device.value = res.device ?? device.value
    } catch (e) {
        error.value = e.message || 'Laden mislukt.'
    }
}

async function send(action, payload = null) {
    if (action.confirm && !window.confirm(`"${device.value?.name || 'Dit toestel'}" ${action.confirm}?`)) return
    sending.value = true
    error.value = ''
    try {
        await sendDeviceCommand(props.device.id, { command: action.command, payload })
        await load()
    } catch (e) {
        error.value = e.message || 'Versturen mislukt.'
    } finally {
        sending.value = false
    }
}

async function sendMessage() {
    if (!message.value.trim()) return
    await send({ command: 'message' }, { text: message.value.trim(), seconds: seconds.value })
    if (!error.value) message.value = ''
}

function statusLabel(s) {
    return { pending: 'Wacht op toestel', sent: 'Bezig', done: 'Uitgevoerd', failed: 'Mislukt', expired: 'Vervallen' }[s] || s
}

function dotClass(s) {
    return {
        pending: 'bg-slate-500',
        sent: 'bg-sky-400',
        done: 'bg-emerald-400',
        failed: 'bg-rose-400',
        expired: 'bg-amber-400',
    }[s] || 'bg-slate-600'
}

function time(v) {
    return v ? new Date(v).toLocaleTimeString('nl-BE', { hour: '2-digit', minute: '2-digit', second: '2-digit' }) : ''
}

watch(() => props.open, (open) => {
    window.clearInterval(timer)
    if (!open) return
    device.value = props.device
    idleMinutes.value = props.device?.idle_minutes ?? 10
    idleSaved.value = false
    commands.value = []
    error.value = ''
    message.value = ''
    load()
    // Status van de opdrachten volgen zolang het venster open is.
    timer = window.setInterval(load, 3000)
})

onBeforeUnmount(() => window.clearInterval(timer))
</script>

<style scoped>
.input {
    border-radius: 0.75rem;
    border: 1px solid rgb(51 65 85);
    background: rgb(2 6 23);
    padding: 0.6rem 0.9rem;
    font-size: 0.875rem;
    color: white;
    outline: none;
}
.input:focus {
    border-color: rgb(14 165 233);
}
</style>
