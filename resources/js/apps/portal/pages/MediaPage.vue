<template>
    <div class="mx-auto max-w-3xl space-y-6">
        <div>
            <h1 class="text-3xl font-bold text-white">Foto's & video</h1>
            <p class="mt-2 text-slate-400">Logo, hero-afbeelding, galerij en optionele video.</p>
        </div>

        <div v-if="loading" class="text-slate-400">Laden...</div>

        <template v-else>
            <!-- Logo -->
            <Section title="Logo" subtitle="Klein vierkant beeld dat overal in de app verschijnt.">
                <div class="flex items-center gap-4">
                    <div class="h-20 w-20 shrink-0 overflow-hidden rounded-xl border border-slate-700 bg-slate-950">
                        <img v-if="media.logo_url" :src="media.logo_url" class="h-full w-full object-contain" alt="Logo">
                        <div v-else class="flex h-full w-full items-center justify-center text-xs text-slate-500">Geen</div>
                    </div>
                    <div class="flex flex-wrap gap-2">
                        <FileButton accept="image/*" @selected="handleLogoUpload">
                            {{ media.logo_url ? 'Vervang logo' : 'Upload logo' }}
                        </FileButton>
                        <button
                            v-if="media.logo_url"
                            type="button"
                            class="rounded-xl border border-rose-500/30 bg-rose-500/10 px-4 py-2.5 text-sm font-medium text-rose-200 transition hover:bg-rose-500/20"
                            @click="handleLogoDelete"
                        >Verwijder</button>
                    </div>
                </div>
            </Section>

            <!-- Hero -->
            <Section title="Hero-afbeelding" subtitle="Grote sfeerafbeelding bovenaan je pagina.">
                <div class="space-y-3">
                    <div class="aspect-[16/7] overflow-hidden rounded-xl border border-slate-700 bg-slate-950">
                        <img v-if="media.hero_image_url" :src="media.hero_image_url" class="h-full w-full object-cover" alt="Hero">
                        <div v-else class="flex h-full w-full items-center justify-center text-sm text-slate-500">Geen hero-afbeelding</div>
                    </div>
                    <div class="flex flex-wrap gap-2">
                        <FileButton accept="image/*" @selected="handleHeroUpload">
                            {{ media.hero_image_url ? 'Vervang hero' : 'Upload hero' }}
                        </FileButton>
                        <button
                            v-if="media.hero_image_url"
                            type="button"
                            class="rounded-xl border border-rose-500/30 bg-rose-500/10 px-4 py-2.5 text-sm font-medium text-rose-200 transition hover:bg-rose-500/20"
                            @click="handleHeroDelete"
                        >Verwijder</button>
                    </div>
                </div>
            </Section>

            <!-- Photos -->
            <Section title="Foto galerij" subtitle="Sleep om te herordenen.">
                <div v-if="!media.photos.length" class="rounded-xl border border-dashed border-slate-700 bg-slate-900/40 p-8 text-center text-sm text-slate-500">
                    Nog geen foto's toegevoegd.
                </div>
                <div v-else class="grid grid-cols-2 gap-3 md:grid-cols-3">
                    <div
                        v-for="(photo, index) in media.photos"
                        :key="photo.id"
                        :draggable="true"
                        @dragstart="onDragStart(index)"
                        @dragover.prevent
                        @drop.prevent="onDrop(index)"
                        class="group relative aspect-square overflow-hidden rounded-xl border border-slate-700 bg-slate-950 transition hover:border-cyan-500/40"
                    >
                        <img :src="photo.url" :alt="photo.alt_text || ''" class="h-full w-full object-cover" />
                        <button
                            type="button"
                            class="absolute right-2 top-2 rounded-lg bg-slate-950/80 px-2 py-1 text-xs font-medium text-rose-300 opacity-0 transition hover:bg-slate-950 group-hover:opacity-100"
                            @click="handlePhotoDelete(photo.id)"
                        >Verwijder</button>
                        <div class="absolute bottom-0 left-0 right-0 bg-slate-950/70 px-2 py-1 text-xs text-slate-300">
                            #{{ index + 1 }}
                        </div>
                    </div>
                </div>
                <div class="mt-3">
                    <FileButton accept="image/*" multiple @selected="handlePhotoUpload">
                        Foto's toevoegen
                    </FileButton>
                </div>
            </Section>

            <div v-if="error" class="rounded-xl border border-rose-500/30 bg-rose-500/10 px-4 py-3 text-sm text-rose-200">
                {{ error }}
            </div>
        </template>
    </div>
</template>

<script setup>
import { onMounted, ref } from 'vue'
import Section from '../components/Section.vue'
import FileButton from '../components/FileButton.vue'
import {
    getMedia,
    uploadLogo,
    deleteLogo,
    uploadHero,
    deleteHero,
    uploadPhoto,
    deletePhoto,
    reorderPhotos,
} from '../services/venueApi'
import { usePortalAuthStore } from '../stores/authStore'

const auth = usePortalAuthStore()
const loading = ref(true)
const error = ref('')
const media = ref({ logo_url: null, hero_image_url: null, video_url: null, photos: [] })

const dragSourceIndex = ref(null)

onMounted(refresh)

async function refresh() {
    loading.value = true
    try {
        media.value = await getMedia()
    } finally {
        loading.value = false
    }
}

async function handleLogoUpload(files) {
    error.value = ''
    try {
        const result = await uploadLogo(files[0])
        media.value.logo_url = result.logo_url
        auth.updateTenant({ logo_url: result.logo_url })
    } catch (err) {
        error.value = err?.data?.message || 'Upload mislukt.'
    }
}

async function handleLogoDelete() {
    if (!confirm('Logo verwijderen?')) return
    try {
        await deleteLogo()
        media.value.logo_url = null
        auth.updateTenant({ logo_url: null })
    } catch (err) {
        error.value = err?.data?.message || 'Verwijderen mislukt.'
    }
}

async function handleHeroUpload(files) {
    error.value = ''
    try {
        const result = await uploadHero(files[0])
        media.value.hero_image_url = result.hero_image_url
    } catch (err) {
        error.value = err?.data?.message || 'Upload mislukt.'
    }
}

async function handleHeroDelete() {
    if (!confirm('Hero-afbeelding verwijderen?')) return
    try {
        await deleteHero()
        media.value.hero_image_url = null
    } catch (err) {
        error.value = err?.data?.message || 'Verwijderen mislukt.'
    }
}

async function handlePhotoUpload(files) {
    error.value = ''
    try {
        for (const file of files) {
            const photo = await uploadPhoto(file)
            media.value.photos.push(photo)
        }
    } catch (err) {
        error.value = err?.data?.message || 'Upload mislukt.'
    }
}

async function handlePhotoDelete(id) {
    if (!confirm('Foto verwijderen?')) return
    try {
        await deletePhoto(id)
        media.value.photos = media.value.photos.filter(p => p.id !== id)
    } catch (err) {
        error.value = err?.data?.message || 'Verwijderen mislukt.'
    }
}

function onDragStart(index) {
    dragSourceIndex.value = index
}

async function onDrop(targetIndex) {
    const source = dragSourceIndex.value
    dragSourceIndex.value = null
    if (source === null || source === targetIndex) return

    const newOrder = [...media.value.photos]
    const [moved] = newOrder.splice(source, 1)
    newOrder.splice(targetIndex, 0, moved)
    media.value.photos = newOrder

    try {
        await reorderPhotos(newOrder.map(p => p.id))
    } catch (err) {
        error.value = err?.data?.message || 'Volgorde opslaan mislukt.'
    }
}
</script>
