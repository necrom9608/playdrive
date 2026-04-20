<template>
    <div class="space-y-6">
        <div>
            <h1 class="text-3xl font-bold text-white">E-mailtemplates</h1>
            <p class="mt-2 text-slate-400">Beheer de standaard e-mails die PlayDrive verstuurt naar leden en gebruikers.</p>
        </div>

        <div v-if="error" class="rounded-2xl border border-red-800 bg-red-950/40 px-4 py-3 text-sm text-red-300">
            {{ error }}
        </div>

        <div v-if="successMessage" class="rounded-2xl border border-emerald-500/30 bg-emerald-500/10 px-4 py-3 text-sm text-emerald-200">
            {{ successMessage }}
        </div>

        <div v-if="loading" class="text-sm text-slate-400">Laden...</div>

        <div v-else class="space-y-3">
            <div
                v-for="template in templates"
                :key="template.key"
                class="flex flex-wrap items-center justify-between gap-4 rounded-2xl border border-slate-800 bg-slate-900 px-6 py-5 transition hover:border-slate-700"
            >
                <div class="min-w-0 flex-1">
                    <div class="flex flex-wrap items-center gap-2">
                        <h3 class="font-semibold text-white">{{ template.label }}</h3>
                        <span
                            v-if="template.is_customized"
                            class="rounded-full bg-cyan-500/15 px-2.5 py-0.5 text-xs font-medium text-cyan-300"
                        >
                            Aangepast
                        </span>
                        <span
                            v-else
                            class="rounded-full bg-slate-700 px-2.5 py-0.5 text-xs font-medium text-slate-400"
                        >
                            Standaard
                        </span>
                    </div>
                    <p class="mt-1 text-sm text-slate-400">{{ template.description }}</p>
                    <div v-if="template.subject" class="mt-2 text-xs text-slate-500">
                        Onderwerp: <span class="text-slate-300">{{ template.subject }}</span>
                    </div>
                </div>

                <button
                    type="button"
                    class="rounded-xl border border-slate-700 px-4 py-2.5 text-sm font-medium text-slate-300 transition hover:bg-slate-800"
                    @click="openEditModal(template)"
                >
                    Bewerken
                </button>
            </div>

            <div v-if="templates.length === 0 && !loading" class="rounded-2xl border border-slate-800 bg-slate-900 p-6 text-sm text-slate-400">
                Geen e-mailtemplates gevonden.
            </div>
        </div>

        <EmailTemplateEditModal
            :open="modalOpen"
            :template="editingTemplate"
            :saving="saving"
            :error="modalError"
            @close="closeModal"
            @submit="handleSubmit"
            @reset="handleReset"
        />
    </div>
</template>

<script setup>
import { onMounted, ref } from 'vue'
import EmailTemplateEditModal from '../components/EmailTemplateEditModal.vue'
import { fetchEmailTemplates, resetEmailTemplate, updateEmailTemplate } from '../services/emailTemplateApi'

const templates = ref([])
const loading = ref(false)
const saving = ref(false)
const error = ref('')
const successMessage = ref('')
const modalError = ref('')
const modalOpen = ref(false)
const editingTemplate = ref(null)

async function loadTemplates() {
    loading.value = true
    error.value = ''
    try {
        const response = await fetchEmailTemplates()
        templates.value = response.templates ?? response ?? []
    } catch {
        error.value = 'Kon e-mailtemplates niet laden.'
    } finally {
        loading.value = false
    }
}

function openEditModal(template) {
    editingTemplate.value = template
    modalError.value = ''
    modalOpen.value = true
}

function closeModal() {
    modalOpen.value = false
    editingTemplate.value = null
    modalError.value = ''
}

function showSuccess(msg) {
    successMessage.value = msg
    setTimeout(() => { successMessage.value = '' }, 3500)
}

async function handleSubmit(payload) {
    saving.value = true
    modalError.value = ''
    try {
        await updateEmailTemplate(editingTemplate.value.key, payload)
        closeModal()
        showSuccess('E-mailtemplate opgeslagen.')
        await loadTemplates()
    } catch (err) {
        modalError.value = err?.data?.message || 'Opslaan mislukt.'
    } finally {
        saving.value = false
    }
}

async function handleReset() {
    saving.value = true
    modalError.value = ''
    try {
        await resetEmailTemplate(editingTemplate.value.key)
        closeModal()
        showSuccess('E-mailtemplate teruggezet naar standaard.')
        await loadTemplates()
    } catch (err) {
        modalError.value = err?.data?.message || 'Terugzetten mislukt.'
    } finally {
        saving.value = false
    }
}

onMounted(loadTemplates)
</script>
