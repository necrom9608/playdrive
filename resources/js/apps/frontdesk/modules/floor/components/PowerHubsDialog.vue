<template>
    <teleport to="body">
        <div v-if="open" class="fixed inset-0 z-50 flex items-center justify-center p-4">
            <div class="absolute inset-0 bg-slate-950/80" @click="close" />
            <div class="relative z-10 flex max-h-[85vh] w-full max-w-xl flex-col overflow-hidden rounded-3xl border border-slate-800 bg-slate-900 shadow-2xl">
                <div class="flex items-center justify-between border-b border-slate-800 px-6 py-4">
                    <div>
                        <h2 class="text-lg font-semibold text-white">Stroom · hubs</h2>
                        <p class="text-sm text-slate-400">IKEA DIRIGERA-hubs koppelen aan deze kassa.</p>
                    </div>
                    <button type="button" class="rounded-xl p-2 text-slate-400 transition hover:bg-slate-800 hover:text-white" aria-label="Sluiten" @click="close">
                        <XMarkIcon class="h-5 w-5" />
                    </button>
                </div>

                <div class="min-h-0 flex-1 space-y-3 overflow-y-auto p-6">
                    <p v-if="!power.available" class="rounded-2xl border border-amber-500/30 bg-amber-500/10 px-4 py-3 text-sm text-amber-100">
                        Koppelen en bedienen kan enkel in de kassa-app: de hubs zijn alleen in het lokale netwerk bereikbaar.
                    </p>
                    <p v-else-if="power.appOutdated" class="rounded-2xl border border-amber-500/30 bg-amber-500/10 px-4 py-3 text-sm text-amber-100">
                        Deze versie van de kassa-app kent de stekkers nog niet. Installeer de nieuwe versie.
                    </p>
                    <p v-if="!power.hubs.length" class="rounded-2xl border border-slate-800 bg-slate-950 px-4 py-3 text-sm text-slate-400">
                        Nog geen hubs. Voeg ze toe in de backoffice (Speelplekken → Stroom) met hun IP-adres.
                    </p>

                    <div v-for="hub in power.hubs" :key="hub.id" class="rounded-2xl border border-slate-800 bg-slate-950 p-4">
                        <div class="flex items-center gap-3">
                            <span class="h-2.5 w-2.5 shrink-0 rounded-full" :class="dot(hub)" />
                            <div class="min-w-0 flex-1">
                                <div class="font-semibold text-white">{{ hub.name }}</div>
                                <div class="text-xs text-slate-500">{{ hub.host }} · {{ statusText(hub) }}</div>
                            </div>
                            <button
                                v-if="power.available && !power.appOutdated"
                                type="button"
                                :disabled="pairingBusy"
                                class="rounded-xl border px-3 py-2 text-sm font-semibold transition disabled:opacity-50"
                                :class="hub.paired ? 'border-slate-700 text-slate-300 hover:bg-slate-800' : 'border-blue-500 bg-blue-600 text-white hover:bg-blue-500'"
                                @click="power.pair(hub)"
                            >
                                {{ hub.paired ? 'Opnieuw koppelen' : 'Koppelen' }}
                            </button>
                        </div>
                        <p v-if="power.hubError(hub.id)" class="mt-2 text-sm text-rose-300">{{ power.hubError(hub.id) }}</p>

                        <div v-if="pairing && pairing.hubId === hub.id" class="mt-3 rounded-xl border px-4 py-3 text-sm" :class="pairingClass">
                            <template v-if="pairing.state === 'starting'">Verbinden met de hub…</template>
                            <template v-else-if="pairing.state === 'waiting'">
                                <p class="font-semibold">Druk nu op de actieknop onderaan de hub.</p>
                                <p class="mt-1 opacity-80">Nog {{ secondsLeft }} s.</p>
                                <button type="button" class="mt-2 rounded-lg border border-current px-3 py-1 text-xs" @click="power.cancelPairing()">Annuleren</button>
                            </template>
                            <template v-else>{{ pairing.message }}</template>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </teleport>
</template>

<script setup>
import { computed } from 'vue'
import { XMarkIcon } from '@heroicons/vue/24/outline'
import { usePowerStore } from '../stores/usePowerStore'

const props = defineProps({
    open: { type: Boolean, default: false },
    now: { type: Number, required: true },
})
const emit = defineEmits(['close'])

const power = usePowerStore()
const pairing = computed(() => power.pairing)
const pairingBusy = computed(() => ['starting', 'waiting'].includes(pairing.value?.state))
const secondsLeft = computed(() => (pairing.value?.deadline ? Math.max(0, Math.round((pairing.value.deadline - props.now) / 1000)) : 0))
const pairingClass = computed(() => ({
    starting: 'border-slate-700 text-slate-300',
    waiting: 'border-sky-400/40 bg-sky-400/10 text-sky-100',
    done: 'border-emerald-500/40 bg-emerald-500/10 text-emerald-100',
    error: 'border-rose-500/40 bg-rose-500/10 text-rose-100',
}[pairing.value?.state] ?? ''))

function outletCount(hub) {
    const outlets = power.live[hub.id]?.outlets
    return outlets ? Object.keys(outlets).length : null
}

function statusText(hub) {
    if (!hub.paired) return 'niet gekoppeld'
    const n = outletCount(hub)
    if (power.hubError(hub.id)) return 'fout'
    return n === null ? 'gekoppeld' : `gekoppeld · ${n} stekker${n === 1 ? '' : 's'}`
}

function dot(hub) {
    if (!hub.paired) return 'bg-slate-600'
    if (power.hubError(hub.id)) return 'bg-rose-400'
    return power.live[hub.id] ? 'bg-emerald-400' : 'bg-slate-500'
}

function close() {
    if (!pairingBusy.value) power.cancelPairing()
    emit('close')
}
</script>
