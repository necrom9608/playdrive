<template>
    <div class="flex h-[calc(100vh-10.5rem)] min-h-0 flex-col gap-4">

        <!-- Statsrij + actieknop -->
        <div class="grid shrink-0 gap-4 md:grid-cols-3 xl:grid-cols-5">
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

            <button
                type="button"
                class="flex flex-col items-center justify-center gap-2 rounded-3xl border border-cyan-500/25 bg-cyan-500/10 p-4 shadow-lg shadow-slate-950/20 transition hover:bg-cyan-500/20"
                @click="scanModal = true"
            >
                <CreditCardIcon class="h-7 w-7 text-cyan-300" />
                <span class="text-sm font-semibold text-cyan-200">In / uitchecken</span>
            </button>
        </div>

        <!-- Hoofdinhoud: 70/30 -->
        <div class="grid min-h-0 flex-1 gap-4 xl:grid-cols-[minmax(0,7fr)_minmax(0,3fr)]">

            <!-- Scans van vandaag (70%) -->
            <section class="flex min-h-0 flex-col rounded-3xl border border-slate-800 bg-slate-900/80 shadow-lg shadow-slate-950/20">
                <div class="flex shrink-0 items-center justify-between gap-4 border-b border-slate-800 px-5 py-4">
                    <div>
                        <h3 class="text-xl font-semibold text-white">Scans van vandaag</h3>
                        <p class="mt-1 text-sm text-slate-400">Laatste in- en uitcheckacties van vandaag.</p>
                    </div>
                    <div class="flex items-center gap-2">
                        <span class="rounded-full border border-slate-700 bg-slate-950/70 px-3 py-1 text-sm text-slate-300">
                            {{ todaySessions.length }} scans
                        </span>
                        <button
                            type="button"
                            class="rounded-2xl border border-slate-700 bg-slate-800 px-3 py-2 text-sm font-semibold text-slate-200 transition hover:bg-slate-700"
                            :disabled="loading"
                            @click="loadData"
                        >
                            Vernieuwen
                        </button>
                    </div>
                </div>

                <div v-if="errorMessage" class="mx-5 mt-4 rounded-2xl border border-rose-500/30 bg-rose-500/10 px-4 py-3 text-sm text-rose-200">
                    {{ errorMessage }}
                </div>

                <div v-if="!todaySessions.length" class="flex flex-1 items-center justify-center px-5 py-10 text-sm text-slate-400">
                    Nog geen scans vandaag.
                </div>

                <div v-else class="flex min-h-0 flex-1 flex-col">
                    <div class="overflow-x-auto border-b border-slate-800">
                        <table class="min-w-full table-fixed">
                            <thead class="bg-slate-900/95">
                            <tr class="text-left text-[11px] uppercase tracking-[0.16em] text-slate-500">
                                <th class="w-[220px] px-4 py-3 font-medium">Medewerker</th>
                                <th class="w-[155px] px-4 py-3 font-medium">In</th>
                                <th class="w-[155px] px-4 py-3 font-medium">Uit</th>
                                <th class="w-[110px] px-4 py-3 font-medium">Duur</th>
                                <th class="w-[130px] px-4 py-3 font-medium">Status</th>
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
                                <td class="w-[220px] px-4 py-3 align-top">
                                    <div class="font-semibold text-white">{{ session.user_name }}</div>
                                    <div class="mt-1 font-mono text-xs text-slate-500">{{ session.rfid_uid }}</div>
                                </td>
                                <td class="w-[155px] whitespace-nowrap px-4 py-3 align-top">{{ session.checked_in_at_full_label ?? '—' }}</td>
                                <td class="w-[155px] whitespace-nowrap px-4 py-3 align-top">{{ session.checked_out_at_full_label ?? '—' }}</td>
                                <td class="w-[110px] whitespace-nowrap px-4 py-3 align-top">{{ session.duration_label ?? '—' }}</td>
                                <td class="w-[130px] px-4 py-3 align-top">
                                    <span
                                        class="inline-flex rounded-full border px-3 py-1 text-xs font-semibold"
                                        :class="session.is_active
                                            ? 'border-emerald-500/20 bg-emerald-500/10 text-emerald-300'
                                            : 'border-slate-700 bg-slate-800 text-slate-200'"
                                    >
                                        {{ session.is_active ? 'Ingecheckt' : 'Uitgecheckt' }}
                                    </span>
                                </td>
                                <td class="px-4 py-3 align-top">{{ session.processed_by_name ?? '—' }}</td>
                            </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </section>

            <!-- Momenteel ingecheckt (30%) -->
            <section class="flex min-h-0 flex-col rounded-3xl border border-slate-800 bg-slate-900/80 p-4 shadow-lg shadow-slate-950/20">
                <div class="mb-3 flex shrink-0 items-center justify-between gap-3">
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
                    class="flex flex-1 items-center justify-center rounded-2xl border border-dashed border-slate-700 bg-slate-950/60 px-4 text-center text-sm text-slate-400"
                >
                    Niemand ingecheckt.
                </div>

                <div v-else class="flex min-h-0 flex-1 flex-col gap-2 overflow-y-auto pr-1">
                    <div
                        v-for="session in activeSessions"
                        :key="session.id"
                        class="rounded-2xl border border-slate-800 bg-slate-950/70 px-3 py-3"
                    >
                        <div class="flex items-start justify-between gap-3">
                            <div class="min-w-0">
                                <div class="truncate font-semibold text-white">{{ session.user_name }}</div>
                                <div class="mt-1 text-sm text-slate-400">Sinds {{ session.checked_in_at_label }}</div>
                            </div>
                            <span class="shrink-0 rounded-full border border-emerald-500/20 bg-emerald-500/10 px-2.5 py-1 text-xs font-semibold text-emerald-300">
                                {{ session.duration_label }}
                            </span>
                        </div>
                    </div>
                </div>
            </section>
        </div>

        <StaffAttendanceModal v-model:open="scanModal" @done="onScanDone" />
    </div>
</template>

<script setup>
import axios from '@/lib/http'
import { onMounted, ref } from 'vue'
import { CreditCardIcon } from '@heroicons/vue/24/outline'
import StaffAttendanceModal from '../../dashboard/modals/StaffAttendanceModal.vue'

const loading = ref(false)
const scanModal = ref(false)
const errorMessage = ref('')

const stats = ref({
    active_users: 0,
    checked_in_now: 0,
    started_today: 0,
    checked_out_today: 0,
})

const activeSessions = ref([])
const todaySessions = ref([])

onMounted(async () => {
    await loadData()
})

async function loadData() {
    loading.value = true
    errorMessage.value = ''

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
    } catch (error) {
        errorMessage.value = error?.response?.data?.message ?? 'Kon personeelsgegevens niet laden.'
    } finally {
        loading.value = false
    }
}

function onScanDone(data) {
    stats.value = data.stats ?? stats.value
    activeSessions.value = data.active_sessions ?? []
    todaySessions.value = data.today_sessions ?? []
}
</script>
