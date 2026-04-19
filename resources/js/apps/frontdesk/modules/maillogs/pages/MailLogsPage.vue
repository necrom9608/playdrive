<template>
    <div class="flex h-full min-h-0 gap-6">

        <!-- Linkerkolom: lijst -->
        <div class="flex min-w-0 flex-1 flex-col gap-5">

            <!-- Summary cards -->
            <div class="grid gap-4 md:grid-cols-4">
                <article v-for="card in summaryCards" :key="card.label" class="rounded-3xl border p-5 shadow-xl" :class="card.wrapperClass">
                    <p class="text-sm font-medium" :class="card.labelClass">{{ card.label }}</p>
                    <p class="mt-3 text-3xl font-semibold" :class="card.valueClass">{{ card.value }}</p>
                </article>
            </div>

            <!-- Filters -->
            <div class="rounded-3xl border border-slate-800 bg-slate-900 p-4 shadow-xl">
                <div class="flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
                    <div class="grid flex-1 gap-4 md:grid-cols-[minmax(0,1fr)_auto_auto]">
                        <label class="space-y-2 text-sm text-slate-300">
                            <span>Zoeken</span>
                            <input
                                :value="store.search"
                                type="text"
                                placeholder="E-mail, naam of onderwerp"
                                class="w-full rounded-2xl border border-slate-700 bg-slate-950 px-4 py-3 text-white outline-none transition focus:border-blue-500"
                                @input="store.setSearch($event.target.value)"
                                @keyup.enter="store.fetchLogs()"
                            >
                        </label>

                        <!-- Status filter -->
                        <div class="space-y-2 text-sm text-slate-300">
                            <span>Status</span>
                            <div class="flex flex-wrap gap-1.5 rounded-2xl border border-slate-700 bg-slate-950 p-2">
                                <button
                                    v-for="opt in statusOptions"
                                    :key="opt.value"
                                    type="button"
                                    class="rounded-full px-3 py-1.5 text-xs font-semibold transition"
                                    :class="store.selectedStatuses.includes(opt.value) ? opt.activeClass : 'bg-slate-800 text-slate-400 hover:text-slate-200'"
                                    @click="toggleStatus(opt.value)"
                                >{{ opt.label }}</button>
                            </div>
                        </div>
                    </div>

                    <button type="button" class="rounded-2xl border border-slate-700 bg-slate-800 px-4 py-3 text-sm font-semibold text-slate-200 transition hover:bg-slate-700" @click="store.fetchLogs()">
                        Zoeken
                    </button>
                </div>
            </div>

            <!-- Error -->
            <div v-if="store.error" class="rounded-3xl border border-rose-500/30 bg-rose-500/10 px-5 py-4 text-sm text-rose-200">{{ store.error }}</div>

            <!-- Tabel -->
            <div class="flex min-h-0 flex-1 flex-col rounded-3xl border border-slate-800 bg-slate-900 shadow-xl">
                <div class="min-h-0 flex-1 overflow-auto">
                    <table class="w-full min-w-[800px] text-sm">
                        <thead class="sticky top-0 bg-slate-900 text-slate-400">
                        <tr>
                            <th class="px-5 py-3 text-left font-medium">Ontvanger</th>
                            <th class="px-5 py-3 text-left font-medium">Onderwerp</th>
                            <th class="px-5 py-3 text-left font-medium">Type</th>
                            <th class="px-5 py-3 text-left font-medium">Verstuurd</th>
                            <th class="px-5 py-3 text-left font-medium">Status</th>
                        </tr>
                        </thead>
                        <tbody>
                        <tr v-if="store.loading">
                            <td colspan="5" class="px-5 py-10 text-center text-slate-500">Laden…</td>
                        </tr>
                        <tr v-else-if="!store.logs.length">
                            <td colspan="5" class="px-5 py-10 text-center text-slate-500">Geen mails gevonden.</td>
                        </tr>
                        <tr
                            v-for="log in store.logs"
                            :key="log.id"
                            class="cursor-pointer border-t border-slate-800 transition hover:bg-slate-800/30"
                            :class="[
                                log.id === store.selectedLogId ? 'bg-slate-800/60' : '',
                                log.has_issue ? 'border-l-2 border-l-rose-500' : '',
                            ]"
                            @click="store.selectLog(log.id)"
                        >
                            <td class="px-5 py-3 align-top">
                                <div class="font-medium text-white">{{ log.to_name || log.to_email }}</div>
                                <div v-if="log.to_name" class="mt-0.5 text-xs text-slate-500">{{ log.to_email }}</div>
                            </td>
                            <td class="px-5 py-3 align-top text-slate-300 max-w-[260px] truncate">{{ log.subject }}</td>
                            <td class="px-5 py-3 align-top">
                                <span class="inline-flex rounded-full bg-slate-700/60 px-2.5 py-1 text-xs text-slate-300">{{ log.mail_type_label }}</span>
                            </td>
                            <td class="px-5 py-3 align-top text-slate-400 text-xs">{{ log.sent_at_label || log.created_at_label }}</td>
                            <td class="px-5 py-3 align-top">
                                <span class="inline-flex rounded-full px-2.5 py-1 text-xs font-semibold" :class="statusClass(log.status)">
                                    {{ log.status_label }}
                                </span>
                            </td>
                        </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- Rechterkolom: detail -->
        <div class="w-[360px] shrink-0">
            <div class="rounded-3xl border border-slate-800 bg-slate-900 shadow-xl h-full overflow-hidden flex flex-col">
                <div class="border-b border-slate-800 px-5 py-4">
                    <h3 class="font-semibold text-white">Detail</h3>
                </div>

                <div v-if="!store.selectedLogId" class="flex flex-1 items-center justify-center text-sm text-slate-500 p-6 text-center">
                    Selecteer een mail om de details te zien.
                </div>

                <div v-else-if="store.loadingDetail" class="flex flex-1 items-center justify-center text-sm text-slate-500">
                    Laden…
                </div>

                <div v-else-if="store.selectedLogDetail" class="flex-1 overflow-y-auto p-5 space-y-4">
                    <!-- Header info -->
                    <div class="space-y-3">
                        <div class="rounded-2xl border border-slate-800 bg-slate-950/60 p-4 space-y-2.5">
                            <div>
                                <div class="text-xs text-slate-500 mb-0.5">Aan</div>
                                <div class="text-sm text-white">{{ store.selectedLogDetail.to_name || '—' }}</div>
                                <div class="text-xs text-slate-400">{{ store.selectedLogDetail.to_email }}</div>
                            </div>
                            <div>
                                <div class="text-xs text-slate-500 mb-0.5">Onderwerp</div>
                                <div class="text-sm text-slate-200">{{ store.selectedLogDetail.subject }}</div>
                            </div>
                            <div>
                                <div class="text-xs text-slate-500 mb-0.5">Type</div>
                                <div class="text-sm text-slate-200">{{ store.selectedLogDetail.mail_type_label }}</div>
                            </div>
                        </div>

                        <!-- Status + tijdlijn -->
                        <div class="rounded-2xl border border-slate-800 bg-slate-950/60 p-4">
                            <div class="mb-3 flex items-center justify-between">
                                <span class="text-xs text-slate-500">Status</span>
                                <span class="inline-flex rounded-full px-2.5 py-1 text-xs font-semibold" :class="statusClass(store.selectedLogDetail.status)">
                                    {{ store.selectedLogDetail.status_label }}
                                </span>
                            </div>

                            <div class="space-y-2">
                                <div v-for="event in timeline" :key="event.label" class="flex items-center gap-3">
                                    <div class="h-2 w-2 rounded-full shrink-0" :class="event.ts ? 'bg-emerald-400' : 'bg-slate-700'"></div>
                                    <span class="text-xs text-slate-400 w-24 shrink-0">{{ event.label }}</span>
                                    <span class="text-xs" :class="event.ts ? 'text-slate-300' : 'text-slate-600'">
                                        {{ event.ts ? formatTs(event.ts) : '—' }}
                                    </span>
                                </div>
                            </div>
                        </div>

                        <!-- Bounce info -->
                        <div v-if="store.selectedLogDetail.has_issue" class="rounded-2xl border border-rose-500/30 bg-rose-500/10 p-4 space-y-1.5">
                            <div class="text-xs font-semibold text-rose-300">
                                {{ store.selectedLogDetail.status === 'bounced' ? 'Bounce details' : 'Spam complaint' }}
                            </div>
                            <div v-if="store.selectedLogDetail.bounce_type" class="text-xs text-rose-200">
                                Type: {{ store.selectedLogDetail.bounce_type }}
                            </div>
                            <div v-if="store.selectedLogDetail.bounce_description" class="text-xs text-rose-200/80">
                                {{ store.selectedLogDetail.bounce_description }}
                            </div>
                        </div>

                        <!-- Resend ID -->
                        <div v-if="store.selectedLogDetail.resend_id" class="text-xs text-slate-600 break-all">
                            Resend ID: {{ store.selectedLogDetail.resend_id }}
                        </div>
                    </div>

                    <!-- Mail preview -->
                    <div v-if="store.selectedLogDetail.html_body">
                        <div class="mb-2 flex items-center justify-between">
                            <span class="text-xs font-medium text-slate-400">Inhoud</span>
                            <button type="button" class="text-xs text-blue-400 hover:text-blue-300" @click="showPreview = !showPreview">
                                {{ showPreview ? 'Verbergen' : 'Tonen' }}
                            </button>
                        </div>
                        <div v-if="showPreview" class="rounded-2xl border border-slate-700 bg-white overflow-hidden">
                            <iframe
                                :srcdoc="store.selectedLogDetail.html_body"
                                class="w-full"
                                style="height: 400px; border: none;"
                                sandbox="allow-same-origin"
                            ></iframe>
                        </div>
                    </div>
                </div>
            </div>
        </div>

    </div>
</template>

<script setup>
import { computed, onMounted, ref } from 'vue'
import { useMailLogsStore } from '../stores/useMailLogsStore'

const store = useMailLogsStore()
const showPreview = ref(false)

onMounted(() => store.fetchLogs())

const summaryCards = computed(() => [
    {
        label: 'Verstuurd',
        value: store.summary.total,
        wrapperClass: 'border-slate-800 bg-slate-900',
        labelClass: 'text-slate-400',
        valueClass: 'text-white',
    },
    {
        label: 'Ontvangen',
        value: store.summary.delivered,
        wrapperClass: 'border-emerald-500/20 bg-emerald-500/10',
        labelClass: 'text-emerald-300',
        valueClass: 'text-emerald-200',
    },
    {
        label: 'Geopend',
        value: store.summary.opened,
        wrapperClass: 'border-blue-500/20 bg-blue-500/10',
        labelClass: 'text-blue-300',
        valueClass: 'text-blue-200',
    },
    {
        label: 'Problemen',
        value: store.summary.issues,
        wrapperClass: store.summary.issues > 0 ? 'border-rose-500/20 bg-rose-500/10' : 'border-slate-800 bg-slate-900',
        labelClass: store.summary.issues > 0 ? 'text-rose-300' : 'text-slate-400',
        valueClass: store.summary.issues > 0 ? 'text-rose-200' : 'text-white',
    },
])

const statusOptions = [
    { value: 'queued',     label: 'In wachtrij',    activeClass: 'bg-slate-600 text-white' },
    { value: 'sent',       label: 'Verstuurd',       activeClass: 'bg-blue-600 text-white' },
    { value: 'delivered',  label: 'Ontvangen',       activeClass: 'bg-emerald-600 text-white' },
    { value: 'opened',     label: 'Geopend',         activeClass: 'bg-teal-600 text-white' },
    { value: 'clicked',    label: 'Geklikt',         activeClass: 'bg-cyan-600 text-white' },
    { value: 'bounced',    label: 'Gebounced',       activeClass: 'bg-rose-600 text-white' },
    { value: 'complained', label: 'Spam',            activeClass: 'bg-orange-600 text-white' },
    { value: 'failed',     label: 'Mislukt',         activeClass: 'bg-red-700 text-white' },
]

const timeline = computed(() => {
    const d = store.selectedLogDetail
    if (!d) return []
    return [
        { label: 'Verstuurd',   ts: d.sent_at },
        { label: 'Ontvangen',  ts: d.delivered_at },
        { label: 'Geopend',     ts: d.opened_at },
        { label: 'Geklikt',     ts: d.clicked_at },
        { label: 'Gebounced',   ts: d.bounced_at },
        { label: 'Spam',        ts: d.complained_at },
    ].filter(e => e.ts || ['Verstuurd', 'Ontvangen', 'Geopend'].includes(e.label))
})

function toggleStatus(value) {
    const next = store.selectedStatuses.includes(value)
        ? store.selectedStatuses.filter(s => s !== value)
        : [...store.selectedStatuses, value]
    store.selectedStatuses = next
}

function statusClass(status) {
    return {
        queued:     'bg-slate-500/20 text-slate-300',
        sent:       'bg-blue-500/15 text-blue-300',
        delivered:  'bg-emerald-500/15 text-emerald-300',
        opened:     'bg-teal-500/15 text-teal-300',
        clicked:    'bg-cyan-500/15 text-cyan-300',
        bounced:    'bg-rose-500/15 text-rose-300',
        complained: 'bg-orange-500/15 text-orange-300',
        failed:     'bg-red-500/15 text-red-300',
    }[status] ?? 'bg-slate-500/15 text-slate-300'
}

function formatTs(ts) {
    if (!ts) return '—'
    return new Date(ts).toLocaleString('nl-BE', { day: '2-digit', month: '2-digit', year: 'numeric', hour: '2-digit', minute: '2-digit' })
}
</script>
