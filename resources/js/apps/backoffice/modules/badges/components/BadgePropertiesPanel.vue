<template>
    <aside class="flex h-full min-h-0 flex-col rounded-3xl border border-slate-800 bg-slate-900/80 shadow-xl shadow-slate-950/20">
        <div class="border-b border-slate-800 px-5 py-4">
            <h2 class="text-lg font-semibold text-white">Eigenschappen</h2>
            <p class="mt-1 text-sm text-slate-400">Pas de badge of het geselecteerde element aan.</p>
        </div>

        <div class="min-h-0 flex-1 overflow-y-auto px-5 py-4">
            <div class="space-y-4">
                <section class="rounded-3xl border border-slate-800 bg-slate-950/60 p-4">
                    <div class="mb-4">
                        <div class="text-xs font-semibold uppercase tracking-[0.18em] text-slate-500">Template</div>
                        <div class="mt-1 text-sm text-slate-400">Algemene badge-instellingen en achtergrond.</div>
                    </div>

                    <div class="space-y-3">
                        <div class="space-y-2">
                            <label class="text-xs font-semibold uppercase tracking-[0.14em] text-slate-500">Naam</label>
                            <input v-model="template.name" type="text" class="w-full rounded-2xl border border-slate-700 bg-slate-900 px-4 py-3 text-sm text-white focus:border-sky-500 focus:outline-none focus:ring-2 focus:ring-sky-500/30" />
                        </div>

                        <div class="space-y-2">
                            <label class="text-xs font-semibold uppercase tracking-[0.14em] text-slate-500">Beschrijving</label>
                            <textarea v-model="template.description" rows="3" class="w-full rounded-2xl border border-slate-700 bg-slate-900 px-4 py-3 text-sm text-white focus:border-sky-500 focus:outline-none focus:ring-2 focus:ring-sky-500/30" />
                        </div>

                        <div class="space-y-2">
                            <label class="text-xs font-semibold uppercase tracking-[0.14em] text-slate-500">Type</label>
                            <select v-model="template.template_type" class="w-full rounded-2xl border border-slate-700 bg-slate-900 px-4 py-3 text-sm text-white focus:border-sky-500 focus:outline-none focus:ring-2 focus:ring-sky-500/30">
                                <option value="staff">Staff</option>
                                <option value="member">Member</option>
                                <option value="voucher">Voucher</option>
                            </select>
                        </div>

                        <div class="space-y-2">
                            <label class="text-xs font-semibold uppercase tracking-[0.14em] text-slate-500">Achtergrondkleur</label>
                            <div class="flex gap-3">
                                <input v-model="template.config_json.backgroundColor" type="color" class="h-12 w-16 rounded-2xl border border-slate-700 bg-slate-900 p-1" />
                                <input v-model="template.config_json.backgroundColor" type="text" class="flex-1 rounded-2xl border border-slate-700 bg-slate-900 px-4 py-3 text-sm text-white focus:border-sky-500 focus:outline-none focus:ring-2 focus:ring-sky-500/30" />
                            </div>
                        </div>

                        <div class="space-y-2">
                            <div class="flex items-center justify-between gap-3">
                                <label class="text-xs font-semibold uppercase tracking-[0.14em] text-slate-500">Achtergrondafbeelding</label>
                                <button v-if="backgroundPreviewUrl" type="button" class="text-xs font-semibold text-rose-300 hover:text-rose-200" @click="clearTemplateBackground">Verwijderen</button>
                            </div>
                            <input :value="backgroundPreviewUrl" type="text" placeholder="/storage/..." class="w-full rounded-2xl border border-slate-700 bg-slate-900 px-4 py-3 text-sm text-white focus:border-sky-500 focus:outline-none focus:ring-2 focus:ring-sky-500/30" @input="onTemplateBackgroundUrlInput" />
                            <div class="flex flex-wrap items-center gap-2">
                                <label class="inline-flex cursor-pointer items-center gap-2 rounded-2xl border border-slate-700 bg-slate-900 px-3 py-2 text-sm font-semibold text-slate-200 hover:bg-slate-800">
                                    Bestand uploaden
                                    <input type="file" accept="image/*" class="hidden" @change="onTemplateBackgroundPicked" />
                                </label>
                                <span v-if="templateUploadBusy" class="text-xs text-slate-400">Uploaden...</span>
                            </div>
                        </div>

                        <div class="grid gap-3 sm:grid-cols-2 xl:grid-cols-1 2xl:grid-cols-2">
                            <div class="space-y-2">
                                <label class="text-xs font-semibold uppercase tracking-[0.14em] text-slate-500">Background size</label>
                                <select v-model="template.config_json.backgroundSize" class="w-full rounded-2xl border border-slate-700 bg-slate-900 px-4 py-3 text-sm text-white focus:border-sky-500 focus:outline-none focus:ring-2 focus:ring-sky-500/30">
                                    <option value="cover">Cover</option>
                                    <option value="contain">Contain</option>
                                    <option value="100% 100%">Stretch</option>
                                </select>
                            </div>
                            <div class="space-y-2">
                                <label class="text-xs font-semibold uppercase tracking-[0.14em] text-slate-500">Background position</label>
                                <select v-model="template.config_json.backgroundPosition" class="w-full rounded-2xl border border-slate-700 bg-slate-900 px-4 py-3 text-sm text-white focus:border-sky-500 focus:outline-none focus:ring-2 focus:ring-sky-500/30">
                                    <option value="center">Center</option>
                                    <option value="top">Top</option>
                                    <option value="bottom">Bottom</option>
                                    <option value="left">Left</option>
                                    <option value="right">Right</option>
                                </select>
                            </div>
                        </div>
                    </div>
                </section>

                <section v-if="selectedElement" class="rounded-3xl border border-slate-800 bg-slate-950/60 p-4">
                    <div class="mb-4 flex items-start justify-between gap-3">
                        <div>
                            <div class="text-xs font-semibold uppercase tracking-[0.18em] text-slate-500">Geselecteerd element</div>
                            <div class="mt-1 text-base font-semibold text-white">{{ selectedElement.label || fallbackLabel }}</div>
                            <div class="mt-1 text-[11px] uppercase tracking-[0.16em] text-slate-500">{{ selectedElement.type }}</div>
                        </div>
                        <button type="button" class="rounded-2xl border border-rose-500/30 bg-rose-500/10 px-3 py-2 text-xs font-semibold text-rose-200 transition hover:bg-rose-500/20" @click="$emit('remove-selected')">Verwijderen</button>
                    </div>

                    <div class="space-y-3">
                        <div class="space-y-2">
                            <label class="text-xs font-semibold uppercase tracking-[0.14em] text-slate-500">Label</label>
                            <input v-model="selectedElement.label" type="text" class="w-full rounded-2xl border border-slate-700 bg-slate-900 px-4 py-3 text-sm text-white focus:border-sky-500 focus:outline-none focus:ring-2 focus:ring-sky-500/30" />
                        </div>

                        <div v-if="selectedElement.type === 'text'" class="space-y-2">
                            <label class="text-xs font-semibold uppercase tracking-[0.14em] text-slate-500">Tekst</label>
                            <textarea v-model="selectedElement.text" rows="3" class="w-full rounded-2xl border border-slate-700 bg-slate-900 px-4 py-3 text-sm text-white focus:border-sky-500 focus:outline-none focus:ring-2 focus:ring-sky-500/30" />
                        </div>

                        <div v-if="selectedElement.type === 'field'" class="space-y-2">
                            <label class="text-xs font-semibold uppercase tracking-[0.14em] text-slate-500">Dataveld</label>
                            <select v-model="selectedElement.source" class="w-full rounded-2xl border border-slate-700 bg-slate-900 px-4 py-3 text-sm text-white focus:border-sky-500 focus:outline-none focus:ring-2 focus:ring-sky-500/30">
                                <option v-for="field in availableFields" :key="field.value" :value="field.value">{{ field.label }}</option>
                            </select>
                        </div>

                        <div v-if="supportsImageSource" class="space-y-2">
                            <div class="flex items-center justify-between gap-3">
                                <label class="text-xs font-semibold uppercase tracking-[0.14em] text-slate-500">Afbeelding</label>
                                <button v-if="elementPreviewUrl" type="button" class="text-xs font-semibold text-rose-300 hover:text-rose-200" @click="clearElementImage">Verwijderen</button>
                            </div>
                            <input :value="elementPreviewUrl" type="text" placeholder="/storage/..." class="w-full rounded-2xl border border-slate-700 bg-slate-900 px-4 py-3 text-sm text-white focus:border-sky-500 focus:outline-none focus:ring-2 focus:ring-sky-500/30" @input="onElementImageUrlInput" />
                            <div class="flex flex-wrap items-center gap-2">
                                <label class="inline-flex cursor-pointer items-center gap-2 rounded-2xl border border-slate-700 bg-slate-900 px-3 py-2 text-sm font-semibold text-slate-200 hover:bg-slate-800">
                                    Bestand uploaden
                                    <input type="file" accept="image/*" class="hidden" @change="onElementImagePicked" />
                                </label>
                                <span v-if="elementUploadBusy" class="text-xs text-slate-400">Uploaden...</span>
                            </div>
                        </div>

                        <div v-if="supportsImageSource" class="space-y-2">
                            <label class="text-xs font-semibold uppercase tracking-[0.14em] text-slate-500">Pasvorm</label>
                            <select v-model="selectedElement.fit" class="w-full rounded-2xl border border-slate-700 bg-slate-900 px-4 py-3 text-sm text-white focus:border-sky-500 focus:outline-none focus:ring-2 focus:ring-sky-500/30">
                                <option value="cover">Cover</option>
                                <option value="contain">Contain</option>
                            </select>
                        </div>

                        <div class="grid gap-3 sm:grid-cols-2 xl:grid-cols-1 2xl:grid-cols-2">
                            <div class="space-y-2">
                                <label class="text-xs font-semibold uppercase tracking-[0.14em] text-slate-500">X</label>
                                <input v-model.number="selectedElement.x" type="number" min="0" class="w-full rounded-2xl border border-slate-700 bg-slate-900 px-4 py-3 text-sm text-white focus:border-sky-500 focus:outline-none focus:ring-2 focus:ring-sky-500/30" />
                            </div>
                            <div class="space-y-2">
                                <label class="text-xs font-semibold uppercase tracking-[0.14em] text-slate-500">Y</label>
                                <input v-model.number="selectedElement.y" type="number" min="0" class="w-full rounded-2xl border border-slate-700 bg-slate-900 px-4 py-3 text-sm text-white focus:border-sky-500 focus:outline-none focus:ring-2 focus:ring-sky-500/30" />
                            </div>
                            <div class="space-y-2">
                                <label class="text-xs font-semibold uppercase tracking-[0.14em] text-slate-500">Breedte</label>
                                <input v-model.number="selectedElement.width" type="number" min="1" class="w-full rounded-2xl border border-slate-700 bg-slate-900 px-4 py-3 text-sm text-white focus:border-sky-500 focus:outline-none focus:ring-2 focus:ring-sky-500/30" />
                            </div>
                            <div class="space-y-2">
                                <label class="text-xs font-semibold uppercase tracking-[0.14em] text-slate-500">Hoogte</label>
                                <input v-model.number="selectedElement.height" type="number" min="1" class="w-full rounded-2xl border border-slate-700 bg-slate-900 px-4 py-3 text-sm text-white focus:border-sky-500 focus:outline-none focus:ring-2 focus:ring-sky-500/30" />
                            </div>
                        </div>

                        <div v-if="supportsText" class="grid gap-3 sm:grid-cols-2 xl:grid-cols-1 2xl:grid-cols-2">
                            <div class="space-y-2">
                                <label class="text-xs font-semibold uppercase tracking-[0.14em] text-slate-500">Font size</label>
                                <input v-model.number="selectedElement.fontSize" type="number" min="10" class="w-full rounded-2xl border border-slate-700 bg-slate-900 px-4 py-3 text-sm text-white focus:border-sky-500 focus:outline-none focus:ring-2 focus:ring-sky-500/30" />
                            </div>
                            <div class="space-y-2">
                                <label class="text-xs font-semibold uppercase tracking-[0.14em] text-slate-500">Font weight</label>
                                <input v-model.number="selectedElement.fontWeight" type="number" min="100" max="900" step="100" class="w-full rounded-2xl border border-slate-700 bg-slate-900 px-4 py-3 text-sm text-white focus:border-sky-500 focus:outline-none focus:ring-2 focus:ring-sky-500/30" />
                            </div>
                        </div>

                        <div v-if="supportsText" class="space-y-2">
                            <label class="text-xs font-semibold uppercase tracking-[0.14em] text-slate-500">Tekstuitlijning</label>
                            <div class="grid grid-cols-3 gap-2">
                                <button v-for="align in ['left', 'center', 'right']" :key="align" type="button" class="rounded-2xl border px-3 py-2 text-sm font-semibold transition" :class="selectedElement.textAlign === align ? 'border-sky-500 bg-sky-500/10 text-sky-200' : 'border-slate-700 bg-slate-900 text-slate-300 hover:bg-slate-800'" @click="selectedElement.textAlign = align">
                                    {{ align }}
                                </button>
                            </div>
                        </div>

                        <div class="space-y-2">
                            <label class="text-xs font-semibold uppercase tracking-[0.14em] text-slate-500">Achtergrondkleur</label>
                            <div class="flex gap-3">
                                <input v-model="selectedElement.backgroundColor" type="color" class="h-12 w-16 rounded-2xl border border-slate-700 bg-slate-900 p-1" />
                                <input v-model="selectedElement.backgroundColor" type="text" class="flex-1 rounded-2xl border border-slate-700 bg-slate-900 px-4 py-3 text-sm text-white focus:border-sky-500 focus:outline-none focus:ring-2 focus:ring-sky-500/30" />
                            </div>
                        </div>

                        <div v-if="supportsText" class="space-y-2">
                            <label class="text-xs font-semibold uppercase tracking-[0.14em] text-slate-500">Tekstkleur</label>
                            <div class="flex gap-3">
                                <input v-model="selectedElement.color" type="color" class="h-12 w-16 rounded-2xl border border-slate-700 bg-slate-900 p-1" />
                                <input v-model="selectedElement.color" type="text" class="flex-1 rounded-2xl border border-slate-700 bg-slate-900 px-4 py-3 text-sm text-white focus:border-sky-500 focus:outline-none focus:ring-2 focus:ring-sky-500/30" />
                            </div>
                        </div>

                        <div class="grid gap-3 sm:grid-cols-2 xl:grid-cols-1 2xl:grid-cols-2">
                            <div class="space-y-2">
                                <label class="text-xs font-semibold uppercase tracking-[0.14em] text-slate-500">Radius</label>
                                <input v-model.number="selectedElement.borderRadius" type="number" min="0" class="w-full rounded-2xl border border-slate-700 bg-slate-900 px-4 py-3 text-sm text-white focus:border-sky-500 focus:outline-none focus:ring-2 focus:ring-sky-500/30" />
                            </div>
                            <div class="space-y-2">
                                <label class="text-xs font-semibold uppercase tracking-[0.14em] text-slate-500">Opacity</label>
                                <input v-model.number="selectedElement.opacity" type="number" min="0" max="1" step="0.1" class="w-full rounded-2xl border border-slate-700 bg-slate-900 px-4 py-3 text-sm text-white focus:border-sky-500 focus:outline-none focus:ring-2 focus:ring-sky-500/30" />
                            </div>
                        </div>
                    </div>
                </section>

                <section v-else class="rounded-3xl border border-dashed border-slate-800 bg-slate-950/40 px-4 py-8 text-center text-sm text-slate-400">
                    Selecteer een element op de badge om de detailinstellingen te tonen.
                </section>

                <section class="rounded-3xl border border-slate-800 bg-slate-950/60 p-4">
                    <div class="text-xs font-semibold uppercase tracking-[0.18em] text-slate-500">Voorbeelddata</div>
                    <div class="mt-3 space-y-2">
                        <div v-for="field in availableFields" :key="field.value" class="flex items-start justify-between gap-3 rounded-2xl border border-slate-800 bg-slate-900/60 px-3 py-2.5">
                            <div class="text-sm font-medium text-slate-200">{{ field.label }}</div>
                            <div class="max-w-[55%] truncate text-right text-sm text-slate-400">{{ sampleData[field.value] || '—' }}</div>
                        </div>
                    </div>
                </section>
            </div>
        </div>
    </aside>
</template>

<script setup>
import { computed, ref } from 'vue'
import { uploadBadgeTemplateImage } from '../services/badgeTemplateApi'
import { resolveImageUrl } from '../utils/badgeEditor'

const props = defineProps({
    template: { type: Object, required: true },
    selectedElement: { type: Object, default: null },
    availableFields: { type: Array, required: true },
    sampleData: { type: Object, default: () => ({}) },
})

const emit = defineEmits(['remove-selected'])

const templateUploadBusy = ref(false)
const elementUploadBusy = ref(false)

const supportsText = computed(() => ['text', 'field'].includes(props.selectedElement?.type))
const supportsImageSource = computed(() => ['photo', 'logo', 'image'].includes(props.selectedElement?.type))
const fallbackLabel = computed(() => {
    if (!props.selectedElement) return 'Element'
    return props.selectedElement.type === 'field' ? 'Dataveld' : props.selectedElement.type
})
const backgroundPreviewUrl = computed(() => resolveImageUrl(props.template.config_json.backgroundImagePath, props.template.config_json.backgroundImageUrl))
const elementPreviewUrl = computed(() => {
    if (!props.selectedElement) return ''
    return resolveImageUrl(props.selectedElement.imagePath, props.selectedElement.imageUrl)
})

async function onTemplateBackgroundPicked(event) {
    const file = event.target.files?.[0]
    if (!file) return

    templateUploadBusy.value = true

    try {
        const media = await uploadBadgeTemplateImage(file, 'background')
        props.template.config_json.backgroundImagePath = media.path || ''
        props.template.config_json.backgroundImageUrl = media.url || ''
    } catch (error) {
        console.error(error)
    } finally {
        templateUploadBusy.value = false
        event.target.value = ''
    }
}

function onTemplateBackgroundUrlInput(event) {
    props.template.config_json.backgroundImageUrl = event.target.value
    props.template.config_json.backgroundImagePath = ''
}

function clearTemplateBackground() {
    props.template.config_json.backgroundImagePath = ''
    props.template.config_json.backgroundImageUrl = ''
}

async function onElementImagePicked(event) {
    const file = event.target.files?.[0]
    if (!file || !props.selectedElement) return

    elementUploadBusy.value = true

    try {
        const media = await uploadBadgeTemplateImage(file, 'element')
        props.selectedElement.imagePath = media.path || ''
        props.selectedElement.imageUrl = media.url || ''
    } catch (error) {
        console.error(error)
    } finally {
        elementUploadBusy.value = false
        event.target.value = ''
    }
}

function onElementImageUrlInput(event) {
    if (!props.selectedElement) return
    props.selectedElement.imageUrl = event.target.value
    props.selectedElement.imagePath = ''
}

function clearElementImage() {
    if (!props.selectedElement) return
    props.selectedElement.imagePath = ''
    props.selectedElement.imageUrl = ''
}
</script>
