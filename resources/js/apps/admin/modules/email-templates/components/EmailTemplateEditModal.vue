<template>
    <teleport to="body">
        <div v-if="open" class="fixed inset-0 z-50 flex items-center justify-center p-4">
            <div class="absolute inset-0 bg-slate-950/80" @click="$emit('close')" />

            <div class="relative z-10 flex max-h-[92vh] w-full max-w-3xl flex-col overflow-hidden rounded-3xl border border-slate-800 bg-slate-900 shadow-2xl">
                <!-- Header -->
                <div class="flex items-center justify-between border-b border-slate-800 px-6 py-4">
                    <div>
                        <h2 class="text-lg font-semibold text-white">{{ template?.label ?? 'E-mailtemplate bewerken' }}</h2>
                        <p class="mt-1 text-sm text-slate-400">Pas de onderwerpregel en de inhoud van deze e-mail aan.</p>
                    </div>
                    <button
                        type="button"
                        class="inline-flex h-10 w-10 items-center justify-center rounded-xl border border-slate-700 text-slate-300 transition hover:bg-slate-800 hover:text-white"
                        @click="$emit('close')"
                    >
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor">
                            <path fill-rule="evenodd" d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z" clip-rule="evenodd" />
                        </svg>
                    </button>
                </div>

                <!-- Tabs -->
                <div class="flex border-b border-slate-800 px-6">
                    <button
                        v-for="tab in tabs"
                        :key="tab.key"
                        type="button"
                        class="mr-6 py-3 text-sm font-medium transition border-b-2 -mb-px"
                        :class="activeTab === tab.key
                            ? 'border-cyan-500 text-white'
                            : 'border-transparent text-slate-400 hover:text-slate-200'"
                        @click="activeTab = tab.key"
                    >
                        {{ tab.label }}
                    </button>
                </div>

                <!-- Body -->
                <div class="flex-1 overflow-y-auto p-6">
                    <div v-if="error" class="mb-4 rounded-2xl border border-red-800 bg-red-950/40 px-4 py-3 text-sm text-red-300">
                        {{ error }}
                    </div>

                    <!-- Edit tab -->
                    <div v-if="activeTab === 'edit'" class="space-y-4">
                        <div>
                            <label class="mb-1 block text-sm font-medium text-slate-300">Onderwerp</label>
                            <input
                                v-model="form.subject"
                                type="text"
                                class="w-full rounded-xl border border-slate-700 bg-slate-950 px-4 py-3 text-white outline-none transition focus:border-cyan-500"
                                placeholder="E-mailonderwerp"
                            />
                        </div>

                        <div>
                            <label class="mb-1 block text-sm font-medium text-slate-300">Inhoud</label>
                            <p class="mb-2 text-xs text-slate-500">
                                Je kan variabelen gebruiken zoals
                                <code class="rounded bg-slate-800 px-1 py-0.5 text-cyan-400">&#123;&#123;naam&#125;&#125;</code>.
                                Beschikbare variabelen staan hieronder.
                            </p>
                            <textarea
                                v-model="form.body"
                                rows="12"
                                class="w-full rounded-xl border border-slate-700 bg-slate-950 px-4 py-3 font-mono text-sm text-white outline-none transition focus:border-cyan-500"
                                placeholder="Inhoud van de e-mail..."
                            />
                        </div>

                        <!-- Variables reference -->
                        <div v-if="template?.variables?.length" class="rounded-2xl border border-slate-800 bg-slate-950/50 p-4">
                            <div class="mb-2 text-xs font-semibold uppercase tracking-[0.15em] text-slate-400">Beschikbare variabelen</div>
                            <div class="flex flex-wrap gap-2">
                                <button
                                    v-for="variable in template.variables"
                                    :key="variable"
                                    type="button"
                                    class="rounded-lg bg-slate-800 px-2.5 py-1.5 font-mono text-xs text-cyan-300 transition hover:bg-slate-700"
                                    title="Klik om te kopiëren"
                                    @click="copyVariable(variable)"
                                >
                                    {{ formatVariable(variable) }}
                                </button>
                            </div>
                            <p class="mt-2 text-xs text-slate-500">Klik op een variabele om ze te kopiëren naar het klembord.</p>
                            <p v-if="copiedVar" class="mt-1 text-xs text-emerald-400">Gekopieerd: {{ copiedVar }}</p>
                        </div>
                    </div>

                    <!-- Preview tab -->
                    <div v-else-if="activeTab === 'preview'" class="space-y-4">
                        <div v-if="previewLoading" class="rounded-2xl border border-slate-800 bg-slate-950 p-6 text-center text-sm text-slate-400">
                            Preview laden...
                        </div>
                        <div v-else-if="previewHtml" class="overflow-hidden rounded-2xl border border-slate-800">
                            <iframe
                                :srcdoc="previewHtml"
                                class="h-[500px] w-full bg-white"
                                sandbox="allow-same-origin"
                            />
                        </div>
                        <div v-else class="rounded-2xl border border-slate-800 bg-slate-950 p-6 text-center text-sm text-slate-400">
                            <p>Nog geen preview geladen.</p>
                            <button
                                type="button"
                                class="mt-3 rounded-xl bg-slate-800 px-4 py-2 text-xs font-medium text-slate-200 transition hover:bg-slate-700"
                                @click="loadPreview"
                            >
                                Preview laden
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Footer -->
                <div class="flex flex-wrap items-center justify-between gap-3 border-t border-slate-800 px-6 py-4">
                    <button
                        type="button"
                        class="rounded-xl border border-amber-500/30 bg-amber-500/10 px-4 py-2.5 text-sm font-medium text-amber-200 transition hover:bg-amber-500/20"
                        @click="handleReset"
                    >
                        Terugzetten naar standaard
                    </button>
                    <div class="flex gap-3">
                        <button
                            type="button"
                            class="rounded-xl border border-slate-700 px-4 py-2.5 text-sm font-medium text-slate-300 transition hover:bg-slate-800"
                            @click="$emit('close')"
                        >
                            Annuleren
                        </button>
                        <button
                            type="button"
                            :disabled="saving"
                            class="rounded-xl bg-cyan-600 px-4 py-2.5 text-sm font-semibold text-white transition hover:bg-cyan-500 disabled:opacity-60"
                            @click="submitForm"
                        >
                            {{ saving ? 'Opslaan...' : 'Opslaan' }}
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </teleport>
</template>

<script setup>
import { reactive, ref, watch } from 'vue'
import { previewEmailTemplate } from '../services/emailTemplateApi'

const props = defineProps({
    open: { type: Boolean, default: false },
    template: { type: Object, default: null },
    saving: { type: Boolean, default: false },
    error: { type: String, default: '' },
})

const emit = defineEmits(['close', 'submit', 'reset'])

const tabs = [
    { key: 'edit', label: 'Bewerken' },
    { key: 'preview', label: 'Voorbeeld' },
]

const activeTab = ref('edit')
const previewHtml = ref('')
const previewLoading = ref(false)
const copiedVar = ref('')

const form = reactive({
    subject: '',
    body: '',
})

watch(
    () => [props.open, props.template],
    ([open]) => {
        if (open) {
            form.subject = props.template?.subject ?? ''
            form.body = props.template?.body ?? ''
            activeTab.value = 'edit'
            previewHtml.value = ''
            copiedVar.value = ''
        }
    },
    { immediate: true, deep: true },
)

watch(activeTab, (tab) => {
    if (tab === 'preview' && !previewHtml.value) {
        loadPreview()
    }
})

async function loadPreview() {
    if (!props.template?.key) return
    previewLoading.value = true
    try {
        const response = await previewEmailTemplate(props.template.key, {
            subject: form.subject,
            body: form.body,
        })
        previewHtml.value = response.html ?? ''
    } catch {
        previewHtml.value = ''
    } finally {
        previewLoading.value = false
    }
}

function formatVariable(variable) {
    return '{{' + variable + '}}'
}

function copyVariable(variable) {
    const text = '{{' + variable + '}}'
    navigator.clipboard?.writeText(text).catch(() => {})
    copiedVar.value = text
    setTimeout(() => { copiedVar.value = '' }, 2000)
}

function submitForm() {
    emit('submit', { subject: form.subject, body: form.body })
}

function handleReset() {
    if (!confirm('Deze template terugzetten naar de standaardinhoud? Je aanpassingen gaan verloren.')) return
    emit('reset')
}
</script>
