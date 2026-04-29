<template>
    <div class="mx-auto max-w-3xl space-y-6">
        <div>
            <h1 class="text-3xl font-bold text-white">Publicatie</h1>
            <p class="mt-2 text-slate-400">Beheer je publieke URL en zet je pagina live.</p>
        </div>

        <div v-if="loading" class="text-slate-400">Laden...</div>

        <template v-else>
            <!-- Status -->
            <Section title="Status">
                <div
                    class="rounded-xl border p-4"
                    :class="publication.public_status === 'live'
                        ? 'border-emerald-500/30 bg-emerald-500/5'
                        : 'border-amber-500/30 bg-amber-500/5'"
                >
                    <div class="flex items-center gap-3">
                        <div
                            class="h-2.5 w-2.5 rounded-full"
                            :class="publication.public_status === 'live' ? 'bg-emerald-400' : 'bg-amber-400'"
                        />
                        <div class="text-sm font-semibold uppercase tracking-wider"
                             :class="publication.public_status === 'live' ? 'text-emerald-300' : 'text-amber-300'"
                        >
                            {{ publication.public_status === 'live' ? 'Live' : 'Concept' }}
                        </div>
                    </div>
                    <div class="mt-3 text-sm text-slate-300">
                        {{ publication.public_status === 'live'
                            ? 'Je pagina is openbaar zichtbaar.'
                            : 'Je pagina is enkel zichtbaar voor jou. Bezoekers krijgen 404.' }}
                    </div>
                    <div v-if="publication.public_url" class="mt-2 text-sm">
                        <a :href="publication.public_url" target="_blank" class="text-cyan-400 hover:text-cyan-300">
                            {{ publication.public_url }}
                        </a>
                    </div>
                </div>
            </Section>

            <!-- Slug -->
            <Section title="Publieke URL" subtitle="Wijzig de slug die in de URL verschijnt.">
                <div class="space-y-2">
                    <div class="flex flex-wrap items-center gap-2">
                        <span class="text-sm text-slate-400">{{ baseUrl }}/venues/</span>
                        <input
                            v-model="slug"
                            type="text"
                            class="input flex-1 min-w-[200px]"
                            placeholder="game-inn"
                        />
                        <button
                            type="button"
                            :disabled="slugSaving || slug === publication.public_slug"
                            class="rounded-xl bg-cyan-600 px-4 py-2.5 text-sm font-semibold text-white transition hover:bg-cyan-500 disabled:opacity-40"
                            @click="saveSlug"
                        >Opslaan</button>
                    </div>
                    <p class="text-xs text-slate-500">Alleen kleine letters, cijfers en koppeltekens.</p>
                    <p v-if="slugError" class="text-xs text-rose-400">{{ slugError }}</p>
                </div>
            </Section>

            <!-- Requirements + actions -->
            <Section title="Vereisten" subtitle="Check voordat je publiceert.">
                <ul class="space-y-2 text-sm">
                    <li v-for="check in checkList" :key="check.key" class="flex items-center gap-2">
                        <span :class="check.ok ? 'text-emerald-400' : 'text-slate-500'">
                            {{ check.ok ? '✓' : '○' }}
                        </span>
                        <span :class="check.ok ? 'text-slate-300' : 'text-slate-500'">{{ check.label }}</span>
                    </li>
                </ul>

                <div class="mt-6 flex flex-wrap gap-2">
                    <button
                        v-if="publication.public_status !== 'live'"
                        type="button"
                        :disabled="!publication.requirements?.ready || actionSaving"
                        class="rounded-xl bg-emerald-600 px-5 py-3 text-sm font-semibold text-white transition hover:bg-emerald-500 disabled:opacity-40"
                        @click="handlePublish"
                    >
                        {{ actionSaving ? 'Bezig...' : 'Publiceer pagina' }}
                    </button>
                    <button
                        v-else
                        type="button"
                        :disabled="actionSaving"
                        class="rounded-xl border border-amber-500/40 bg-amber-500/10 px-5 py-3 text-sm font-semibold text-amber-200 transition hover:bg-amber-500/20 disabled:opacity-40"
                        @click="handleUnpublish"
                    >
                        {{ actionSaving ? 'Bezig...' : 'Pagina offline halen' }}
                    </button>
                    <a
                        v-if="publication.public_url"
                        :href="publication.public_url"
                        target="_blank"
                        class="rounded-xl border border-slate-700 bg-slate-900/60 px-5 py-3 text-sm font-medium text-slate-200 transition hover:border-cyan-500 hover:text-cyan-300"
                    >Voorbeeld bekijken</a>
                </div>

                <div v-if="actionError" class="mt-3 rounded-xl border border-rose-500/30 bg-rose-500/10 px-4 py-3 text-sm text-rose-200">
                    {{ actionError }}
                </div>
            </Section>
        </template>
    </div>
</template>

<script setup>
import { computed, onMounted, ref } from 'vue'
import Section from '../components/Section.vue'
import { getPublication, updateSlug, publish, unpublish } from '../services/venueApi'
import { usePortalAuthStore } from '../stores/authStore'

const auth = usePortalAuthStore()
const loading = ref(true)
const publication = ref({})
const slug = ref('')
const slugSaving = ref(false)
const slugError = ref('')
const actionSaving = ref(false)
const actionError = ref('')

const baseUrl = computed(() => window.location.origin)

const checkList = computed(() => {
    const checks = publication.value?.requirements?.checks ?? {}
    return [
        { key: 'has_name', ok: !!checks.has_name, label: 'Naam ingevuld' },
        { key: 'has_slug', ok: !!checks.has_slug, label: 'Publieke URL ingesteld' },
        { key: 'has_description', ok: !!checks.has_description, label: 'Beschrijving ingevuld' },
        { key: 'has_media', ok: !!checks.has_media, label: 'Minstens één foto, logo of hero' },
    ]
})

onMounted(refresh)

async function refresh() {
    loading.value = true
    try {
        publication.value = await getPublication()
        slug.value = publication.value.public_slug ?? ''
    } finally {
        loading.value = false
    }
}

async function saveSlug() {
    slugSaving.value = true
    slugError.value = ''

    try {
        publication.value = await updateSlug(slug.value)
        slug.value = publication.value.public_slug ?? ''
        auth.updateTenant({ public_slug: publication.value.public_slug })
    } catch (err) {
        slugError.value = err?.data?.errors?.public_slug?.[0]
            || err?.data?.message
            || 'Opslaan mislukt.'
    } finally {
        slugSaving.value = false
    }
}

async function handlePublish() {
    actionSaving.value = true
    actionError.value = ''

    try {
        publication.value = await publish()
        auth.updateTenant({
            public_status: publication.value.public_status,
            public_slug: publication.value.public_slug,
        })
    } catch (err) {
        actionError.value = err?.data?.message || 'Publiceren mislukt.'
    } finally {
        actionSaving.value = false
    }
}

async function handleUnpublish() {
    if (!confirm('Pagina offline halen? Bezoekers zien dan een 404.')) return

    actionSaving.value = true
    actionError.value = ''

    try {
        publication.value = await unpublish()
        auth.updateTenant({ public_status: publication.value.public_status })
    } catch (err) {
        actionError.value = err?.data?.message || 'Mislukt.'
    } finally {
        actionSaving.value = false
    }
}
</script>

<style scoped>
.input {
    border-radius: 0.75rem;
    border: 1px solid rgb(51 65 85);
    background: rgb(2 6 23);
    padding: 0.5rem 0.75rem;
    color: white;
    font-size: 0.875rem;
    outline: none;
    transition: border-color 0.15s;
}
.input:focus { border-color: rgb(6 182 212); }
</style>
