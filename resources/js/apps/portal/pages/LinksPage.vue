<template>
    <div class="mx-auto max-w-3xl space-y-6">
        <div>
            <h1 class="text-3xl font-bold text-white">Externe links</h1>
            <p class="mt-2 text-slate-400">Social media en andere relevante links.</p>
        </div>

        <div v-if="loading" class="text-slate-400">Laden...</div>

        <template v-else>
            <Section title="Links">
                <div v-if="!links.length" class="rounded-xl border border-dashed border-slate-700 bg-slate-900/40 p-8 text-center text-sm text-slate-500">
                    Nog geen links toegevoegd.
                </div>

                <div v-else class="space-y-2">
                    <div
                        v-for="link in links"
                        :key="link.id"
                        class="flex items-center gap-4 rounded-xl border border-slate-700 bg-slate-900/60 p-4"
                    >
                        <div class="w-24 shrink-0 text-sm font-medium text-slate-300">{{ typeLabels[link.type] || link.type }}</div>
                        <div class="flex-1 truncate text-sm text-slate-400">{{ link.url }}</div>
                        <button type="button" class="text-sm text-cyan-400 hover:text-cyan-300" @click="openEdit(link)">Bewerken</button>
                        <button type="button" class="text-sm text-rose-400 hover:text-rose-300" @click="handleDelete(link)">Verwijder</button>
                    </div>
                </div>

                <div class="mt-3">
                    <button
                        type="button"
                        class="rounded-xl border border-slate-700 bg-slate-900/60 px-4 py-2.5 text-sm font-medium text-slate-200 transition hover:border-cyan-500 hover:text-cyan-300"
                        @click="openCreate"
                    >Link toevoegen</button>
                </div>
            </Section>

            <div v-if="error" class="rounded-xl border border-rose-500/30 bg-rose-500/10 px-4 py-3 text-sm text-rose-200">
                {{ error }}
            </div>
        </template>

        <!-- Modal -->
        <div
            v-if="modalOpen"
            class="fixed inset-0 z-50 flex items-center justify-center bg-slate-950/80 p-4"
            @click.self="closeModal"
        >
            <div class="w-full max-w-md rounded-2xl border border-slate-800 bg-slate-900 p-6">
                <h2 class="mb-4 text-lg font-semibold text-white">
                    {{ editing ? 'Link bewerken' : 'Link toevoegen' }}
                </h2>

                <div class="space-y-4">
                    <Field label="Type" required>
                        <select v-model="modal.type" class="input">
                            <option v-for="(label, key) in typeLabels" :key="key" :value="key">{{ label }}</option>
                        </select>
                    </Field>
                    <Field label="URL" required>
                        <input v-model="modal.url" type="url" placeholder="https://" class="input" required />
                    </Field>
                </div>

                <div class="mt-6 flex justify-end gap-2">
                    <button type="button" class="rounded-xl border border-slate-700 px-4 py-2.5 text-sm font-medium text-slate-300 transition hover:bg-slate-800" @click="closeModal">Annuleren</button>
                    <button type="button" :disabled="modalSaving" class="rounded-xl bg-cyan-600 px-5 py-2.5 text-sm font-semibold text-white transition hover:bg-cyan-500 disabled:opacity-60" @click="saveModal">{{ modalSaving ? 'Bezig...' : 'Opslaan' }}</button>
                </div>
            </div>
        </div>
    </div>
</template>

<script setup>
import { onMounted, ref } from 'vue'
import Section from '../components/Section.vue'
import Field from '../components/Field.vue'
import { getLinks, createLink, updateLink, deleteLink } from '../services/venueApi'

const typeLabels = {
    facebook: 'Facebook',
    instagram: 'Instagram',
    tiktok: 'TikTok',
    youtube: 'YouTube',
    twitter: 'X / Twitter',
    linkedin: 'LinkedIn',
    website: 'Eigen website',
    other: 'Andere',
}

const loading = ref(true)
const links = ref([])
const error = ref('')

const modalOpen = ref(false)
const editing = ref(null)
const modalSaving = ref(false)
const modal = ref({ type: 'facebook', url: '' })

onMounted(refresh)

async function refresh() {
    loading.value = true
    try {
        const data = await getLinks()
        links.value = data.links ?? []
    } finally {
        loading.value = false
    }
}

function openCreate() {
    editing.value = null
    modal.value = { type: 'facebook', url: '' }
    modalOpen.value = true
}

function openEdit(link) {
    editing.value = link
    modal.value = { type: link.type, url: link.url }
    modalOpen.value = true
}

function closeModal() {
    modalOpen.value = false
}

async function saveModal() {
    modalSaving.value = true
    try {
        if (editing.value) {
            const updated = await updateLink(editing.value.id, modal.value)
            const idx = links.value.findIndex(l => l.id === updated.id)
            if (idx !== -1) links.value[idx] = updated
        } else {
            const created = await createLink(modal.value)
            links.value.push(created)
        }
        modalOpen.value = false
    } catch (err) {
        error.value = err?.data?.message || 'Opslaan mislukt.'
    } finally {
        modalSaving.value = false
    }
}

async function handleDelete(link) {
    if (!confirm('Link verwijderen?')) return
    try {
        await deleteLink(link.id)
        links.value = links.value.filter(l => l.id !== link.id)
    } catch (err) {
        error.value = err?.data?.message || 'Verwijderen mislukt.'
    }
}
</script>

<style scoped>
.input {
    width: 100%;
    border-radius: 0.75rem;
    border: 1px solid rgb(51 65 85);
    background: rgb(2 6 23);
    padding: 0.6rem 0.9rem;
    color: white;
    outline: none;
    transition: border-color 0.15s;
}
.input:focus { border-color: rgb(6 182 212); }
</style>
