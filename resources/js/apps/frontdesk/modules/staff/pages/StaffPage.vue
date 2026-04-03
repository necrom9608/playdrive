<template>
    <div class="flex h-[calc(100vh-10.5rem)] min-h-0 flex-col gap-4">
        <div class="grid shrink-0 gap-4 md:grid-cols-2 xl:grid-cols-4">
            <article class="rounded-3xl border border-slate-800 bg-slate-900/80 p-4 shadow-lg shadow-slate-950/20">
                <p class="text-sm font-medium text-slate-400">Actieve medewerkers</p>
                <p class="mt-3 text-4xl font-semibold text-white">{{ stats.active_users ?? 0 }}</p>
            </article>

            <article class="rounded-3xl border border-emerald-500/20 bg-emerald-950/20 p-4 shadow-lg shadow-slate-950/20">
                <p class="text-sm font-medium text-emerald-300">Nu ingecheckt</p>
                <p class="mt-3 text-4xl font-semibold text-emerald-200">{{ stats.checked_in_now ?? 0 }}</p>
            </article>

            <article class="rounded-3xl border border-cyan-500/20 bg-cyan-950/20 p-4 shadow-lg shadow-slate-950/20">
                <p class="text-sm font-medium text-cyan-300">Vandaag gestart</p>
                <p class="mt-3 text-4xl font-semibold text-cyan-200">{{ stats.started_today ?? 0 }}</p>
            </article>

            <article class="rounded-3xl border border-violet-500/20 bg-violet-950/20 p-4 shadow-lg shadow-slate-950/20">
                <p class="text-sm font-medium text-violet-300">Vandaag uitgecheckt</p>
                <p class="mt-3 text-4xl font-semibold text-violet-200">{{ stats.checked_out_today ?? 0 }}</p>
            </article>
        </div>

        <div class="grid shrink-0 gap-4 xl:grid-cols-[minmax(0,1.7fr)_360px]">
            <section class="rounded-3xl border border-slate-800 bg-slate-900/80 p-4 shadow-lg shadow-slate-950/20">
                <div class="mb-3 flex items-center justify-between gap-3">
                    <div>
                        <h3 class="text-lg font-semibold text-white">Personeel in- en uitchecken</h3>
                        <p class="mt-1 text-sm text-slate-400">
                            Scan een NFC-kaart om direct in of uit te checken.
                        </p>
                    </div>

                    <button
                        type="button"
                        class="shrink-0 rounded-2xl border border-slate-700 bg-slate-800 px-3 py-2 text-sm font-semibold text-slate-200 transition hover:bg-slate-700"
                        :disabled="loading"
                        @click="loadData"
                    >
                        Vernieuwen
                    </button>
                </div>

                <div class="grid gap-3 xl:grid-cols-[minmax(0,1fr)_180px_150px]">
                    <input
                        ref="scannerInputRef"
                        v-model="scanInput"
                        type="text"
                        autocomplete="off"
                        spellcheck="false"
                        class="w-full rounded-2xl border border-slate-700 bg-slate-950 px-4 py-3 font-mono text-cyan-300 outline-none transition focus:border-cyan-400 focus:ring-2 focus:ring-cyan-500/20"
                        placeholder="Wachten op scan..."
                        @focus="clearError"
                        @keydown.enter.prevent="submitScan"
                    >

                    <div class="rounded-2xl border border-slate-800 bg-slate-950 px-4 py-3">
                        <p class="text-[11px] uppercase tracking-[0.18em] text-slate-500">UID</p>
                        <p class="mt-1 truncate font-mono text-sm text-cyan-300">
                            {{ normalizedScan || '—' }}
                        </p>
                    </div>

                    <button
                        type="button"
                        class="rounded-2xl bg-cyan-600 px-4 py-3 text-sm font-semibold text-white transition hover:bg-cyan-500 disabled:cursor-not-allowed disabled:opacity-50"
                        :disabled="processing || !normalizedScan"
                        @click="submitScan"
                    >
                        {{ processing ? 'Bezig...' : 'Verwerk scan' }}
                    </button>
                </div>

                <div class="mt-3 flex flex-wrap items-center gap-2">
                    <span class="rounded-full border border-slate-700 bg-slate-950/70 px-3 py-1 text-xs text-slate-300">
                        Scanner klaar
                    </span>

                    <template v-if="lastAction">
                        <span
                            class="rounded-full border px-3 py-1 text-xs font-semibold"
                            :class="lastAction.action === 'check_in'
                                ? 'border-emerald-500/20 bg-emerald-500/10 text-emerald-300'
                                : 'border-violet-500/20 bg-violet-500/10 text-violet-300'"
                        >
                            {{ lastAction.action === 'check_in' ? 'Ingecheckt' : 'Uitgecheckt' }}
                        </span>

                        <span class="rounded-full border border-slate-700 bg-slate-950/70 px-3 py-1 text-xs text-slate-200">
                            {{ lastAction.user_name }}
                        </span>

                        <span class="rounded-full border border-slate-700 bg-slate-950/70 px-3 py-1 text-xs text-slate-400">
                            {{ lastAction.time_label }}
                        </span>

                        <span class="text-sm text-slate-300">
                            {{ lastAction.description }}
                        </span>
                    </template>
                </div>

                <div
                    v-if="errorMessage"
                    class="mt-3 rounded-2xl border border-rose-500/30 bg-rose-500/10 px-4 py-3 text-sm text-rose-200"
                >
                    {{ errorMessage }}
                </div>
            </section>

            <section class="rounded-3xl border border-slate-800 bg-slate-900/80 p-4 shadow-lg shadow-slate-950/20">
                <div class="mb-3 flex items-center justify-between gap-3">
                    <div>
                        <h3 class="text-lg font-semibold text-white">Momenteel ingecheckt</h3>
                        <p class="mt-1 text-sm text-slate-400">Live overzicht</p>
                    </div>

                    <span class="shrink-0 rounded-full border border-emerald-500/20 bg-emerald-500/10 px-3 py-1 text-sm font-semibold text-emerald-300">
                        {{ activeSessions.length }} actief
                    </span>
                </div>

                <div
                    v-if="!activeSessions.length"
                    class="flex h-40 items-center justify-center rounded-2xl border border-dashed border-slate-700 bg-slate-950/60 px-4 text-center text-sm text-slate-400"
                >
                    Niemand ingecheckt.
                </div>

                <div v-else class="flex max-h-40 flex-col gap-2 overflow-y-auto pr-1">
                    <div
                        v-for="session in activeSessions"
                        :key="session.id"
                        class="rounded-2xl border border-slate-800 bg-slate-950/70 px-3 py-3"
                    >
                        <div class="flex items-start justify-between gap-3">
                            <div class="min-w-0">
                                <div class="truncate font-semibold text-white">{{ session.user_name }}</div>
                                <div class="mt-1 text-sm text-slate-400">
                                    Sinds {{ session.checked_in_at_label }}
                                </div>
                            </div>

                            <span class="shrink-0 rounded-full border border-emerald-500/20 bg-emerald-500/10 px-2.5 py-1 text-xs font-semibold text-emerald-300">
                                {{ session.duration_label }}
                            </span>
                        </div>
                    </div>
                </div>
            </section>
        </div>

        <section class="min-h-0 flex-1 rounded-3xl border border-slate-800 bg-slate-900/80 shadow-lg shadow-slate-950/20">
            <div class="flex items-center justify-between gap-4 border-b border-slate-800 px-5 py-4">
                <div>
                    <h3 class="text-xl font-semibold text-white">Scans van vandaag</h3>
                    <p class="mt-1 text-sm text-slate-400">
                        Laatste in- en uitcheckacties van vandaag.
                    </p>
                </div>

                <span class="rounded-full border border-slate-700 bg-slate-950/70 px-3 py-1 text-sm text-slate-300">
            {{ todaySessions.length }} scans
        </span>
            </div>

            <div v-if="!todaySessions.length" class="flex h-full items-center justify-center px-5 py-10 text-sm text-slate-400">
                Nog geen scans vandaag.
            </div>

            <div v-else class="flex h-[calc(100%-73px)] min-h-0 flex-col">
                <div class="overflow-x-auto border-b border-slate-800">
                    <table class="min-w-full table-fixed">
                        <thead class="bg-slate-900/95">
                        <tr class="text-left text-[11px] uppercase tracking-[0.16em] text-slate-500">
                            <th class="w-[240px] px-4 py-3 font-medium">Medewerker</th>
                            <th class="w-[170px] px-4 py-3 font-medium">In</th>
                            <th class="w-[170px] px-4 py-3 font-medium">Uit</th>
                            <th class="w-[140px] px-4 py-3 font-medium">Duur</th>
                            <th class="w-[140px] px-4 py-3 font-medium">Status</th>
                            <th class="px-4 py-3 font-medium">Verwerkt door</th>
                        </tr>
                        </thead>
                    </table>
                </div>

                <div class="min-h-0 flex-1 overflow-y-auto overflow-x-auto">
                    <table class="min-w-full table-fixed">
                        <tbody class="divide-y divide-slate-800">
                        <tr
                            v-for="session in todaySessions"
                            :key="`today-${session.id}`"
                            class="text-sm text-slate-200"
                        >
                            <td class="w-[240px] px-4 py-3 align-top">
                                <div class="font-semibold text-white">{{ session.user_name }}</div>
                                <div class="mt-1 font-mono text-xs text-slate-500">{{ session.rfid_uid }}</div>
                            </td>

                            <td class="w-[170px] whitespace-nowrap px-4 py-3 align-top">
                                {{ session.checked_in_at_full_label ?? '—' }}
                            </td>

                            <td class="w-[170px] whitespace-nowrap px-4 py-3 align-top">
                                {{ session.checked_out_at_full_label ?? '—' }}
                            </td>

                            <td class="w-[140px] whitespace-nowrap px-4 py-3 align-top">
                                {{ session.duration_label ?? '—' }}
                            </td>

                            <td class="w-[140px] px-4 py-3 align-top">
                            <span
                                class="inline-flex rounded-full border px-3 py-1 text-xs font-semibold"
                                :class="session.is_active
                                    ? 'border-emerald-500/20 bg-emerald-500/10 text-emerald-300'
                                    : 'border-slate-700 bg-slate-800 text-slate-200'"
                            >
                                {{ session.is_active ? 'Ingecheckt' : 'Uitgecheckt' }}
                            </span>
                            </td>

                            <td class="px-4 py-3 align-top">
                                {{ session.processed_by_name ?? '—' }}
                            </td>
                        </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </section>
    </div>
</template>

<script setup>
import axios from '@/lib/http'
import { computed, nextTick, onMounted, ref } from 'vue'

const loading = ref(false)
const processing = ref(false)
const scanInput = ref('')
const errorMessage = ref('')
const scannerInputRef = ref(null)

const stats = ref({
    active_users: 0,
    checked_in_now: 0,
    started_today: 0,
    checked_out_today: 0,
})

const activeSessions = ref([])
const todaySessions = ref([])
const lastAction = ref(null)

const normalizedScan = computed(() => normalizeUid(scanInput.value))

onMounted(async () => {
    await loadData()
    focusScanner()
})

async function loadData() {
    loading.value = true
    clearError()

    try {
        const { data } = await axios.get('/api/frontdesk/staff-attendance')

        stats.value = data.stats ?? {
            active_users: 0,
            checked_in_now: 0,
            started_today: 0,
            checked_out_today: 0,
        }

        activeSessions.value = data.active_sessions ?? []
        todaySessions.value = data.today_sessions ?? []
        lastAction.value = data.last_action ?? null
    } catch (error) {
        errorMessage.value = extractErrorMessage(error, 'Kon personeelsgegevens niet laden.')
    } finally {
        loading.value = false
        focusScanner()
    }
}

async function submitScan() {
    const uid = normalizedScan.value

    if (!uid || processing.value) {
        return
    }

    processing.value = true
    clearError()

    try {
        const { data } = await axios.post('/api/frontdesk/staff-attendance/scan', {
            rfid_uid: uid,
            raw_uid: scanInput.value,
        })

        stats.value = data.stats ?? stats.value
        activeSessions.value = data.active_sessions ?? []
        todaySessions.value = data.today_sessions ?? []
        lastAction.value = data.last_action ?? null
        scanInput.value = ''
    } catch (error) {
        errorMessage.value = extractErrorMessage(error, 'Onbekende of niet-actieve personeelskaart.')
    } finally {
        processing.value = false
        focusScanner()
    }
}

function clearError() {
    errorMessage.value = ''
}

function focusScanner() {
    nextTick(() => {
        scannerInputRef.value?.focus?.()
    })
}

function normalizeUid(value) {
    if (!value) {
        return ''
    }

    const cleaned = String(value)
        .trim()
        .toLowerCase()
        .replace(/[^a-f0-9]/g, '')

    if (!cleaned) {
        return ''
    }

    if (cleaned.length === 16) {
        const pairs = cleaned.match(/.{1,2}/g) ?? []
        const everySecondPair = pairs.filter((_, index) => index % 2 === 0).join('')
        return everySecondPair || cleaned
    }

    if (cleaned.length > 8) {
        return cleaned.slice(-8)
    }

    return cleaned
}

function extractErrorMessage(error, fallback) {
    return (
        error?.response?.data?.message
        || error?.response?.data?.error
        || fallback
    )
}
</script>
