<template>
    <aside class="flex h-full min-h-0 flex-col rounded-3xl border border-slate-800 bg-slate-900/80 shadow-xl shadow-slate-950/20">
        <div class="min-h-0 flex-1 overflow-y-auto px-5 py-4">
            <div class="space-y-4">
                <section class="rounded-3xl border border-slate-800 bg-slate-950/60 p-4">
                    <div class="mb-3 text-xs font-semibold uppercase tracking-[0.18em] text-slate-500">Template naam</div>
                    <input v-model="template.name" type="text" class="w-full rounded-2xl border border-slate-700 bg-slate-900 px-4 py-3 text-sm text-white focus:border-sky-500 focus:outline-none focus:ring-2 focus:ring-sky-500/30" />
                </section>

                <section class="rounded-3xl border border-slate-800 bg-slate-950/60 p-4">
                    <div class="mb-3 text-xs font-semibold uppercase tracking-[0.18em] text-slate-500">Elementen</div>
                    <div class="space-y-2">
                        <button
                            v-for="element in orderedElements"
                            :key="element.id"
                            type="button"
                            class="flex w-full items-center justify-between gap-3 rounded-2xl border px-3 py-3 text-left transition"
                            :class="selectedElement?.id === element.id ? 'border-sky-500 bg-sky-500/10' : 'border-slate-800 bg-slate-900/60 hover:border-slate-700 hover:bg-slate-900'"
                            @click="$emit('select-element', element.id)"
                        >
                            <div class="min-w-0">
                                <div class="truncate text-sm font-semibold text-white">{{ element.label || fallbackElementLabel(element) }}</div>
                                <div class="text-xs text-slate-500">{{ typeName(element.type) }}</div>
                            </div>
                            <div class="flex items-center gap-1">
                                <button type="button" class="rounded-xl border border-slate-700 bg-slate-950 p-2 text-slate-300 transition hover:bg-slate-800" @click.stop="$emit('move-element-up', element.id)">
                                    <ChevronUpIcon class="h-4 w-4" />
                                </button>
                                <button type="button" class="rounded-xl border border-slate-700 bg-slate-950 p-2 text-slate-300 transition hover:bg-slate-800" @click.stop="$emit('move-element-down', element.id)">
                                    <ChevronDownIcon class="h-4 w-4" />
                                </button>
                            </div>
                        </button>
                    </div>
                </section>

                <section v-if="selectedElement" class="rounded-3xl border border-slate-800 bg-slate-950/60 p-4">
                    <div class="mb-4 flex items-start justify-between gap-3">
                        <div>
                            <div class="text-xs font-semibold uppercase tracking-[0.18em] text-slate-500">Eigenschappen</div>
                            <div class="mt-1 text-base font-semibold text-white">{{ selectedElement.label || fallbackLabel }}</div>
                        </div>
                        <button type="button" class="inline-flex items-center gap-2 rounded-2xl border border-rose-500/30 bg-rose-500/10 px-3 py-2 text-xs font-semibold text-rose-200 transition hover:bg-rose-500/20" @click="$emit('remove-selected')">
                            <TrashIcon class="h-4 w-4" />
                            Verwijderen
                        </button>
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
                                <button v-if="elementPreviewUrl" type="button" class="text-xs font-semibold text-rose-300 hover:text-rose-200" @click="clearElementImage">Wissen</button>
                            </div>
                            <input :value="elementPreviewUrl" type="text" placeholder="/storage/..." class="w-full rounded-2xl border border-slate-700 bg-slate-900 px-4 py-3 text-sm text-white focus:border-sky-500 focus:outline-none focus:ring-2 focus:ring-sky-500/30" @input="onElementImageUrlInput" />
                            <div class="flex flex-wrap items-center gap-2">
                                <label class="inline-flex cursor-pointer items-center gap-2 rounded-2xl border border-slate-700 bg-slate-900 px-3 py-2 text-sm font-semibold text-slate-200 hover:bg-slate-800">
                                    <PhotoIcon class="h-4 w-4" />
                                    Uploaden
                                    <input type="file" accept="image/*" class="hidden" @change="onElementImagePicked" />
                                </label>
                                <span v-if="elementUploadBusy" class="text-xs text-slate-400">Uploaden...</span>
                            </div>
                        </div>

                        <div v-if="supportsImageSource" class="space-y-2">
                            <label class="text-xs font-semibold uppercase tracking-[0.14em] text-slate-500">Weergave</label>
                            <select v-model="selectedElement.fit" class="w-full rounded-2xl border border-slate-700 bg-slate-900 px-4 py-3 text-sm text-white focus:border-sky-500 focus:outline-none focus:ring-2 focus:ring-sky-500/30">
                                <option value="cover">Cover</option>
                                <option value="contain">Contain</option>
                                <option value="fill">Stretch</option>
                            </select>
                        </div>

                        <div class="grid gap-3 grid-cols-2">
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

                        <div v-if="supportsText" class="grid gap-3 grid-cols-2">
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
                                <button type="button" class="flex items-center justify-center rounded-2xl border px-3 py-2 transition" :class="selectedElement.textAlign === 'left' ? 'border-sky-500 bg-sky-500/10 text-sky-200' : 'border-slate-700 bg-slate-900 text-slate-300 hover:bg-slate-800'" @click="selectedElement.textAlign = 'left'">
                                    <Bars3BottomLeftIcon class="h-5 w-5" />
                                </button>
                                <button type="button" class="flex items-center justify-center rounded-2xl border px-3 py-2 transition" :class="selectedElement.textAlign === 'center' ? 'border-sky-500 bg-sky-500/10 text-sky-200' : 'border-slate-700 bg-slate-900 text-slate-300 hover:bg-slate-800'" @click="selectedElement.textAlign = 'center'">
                                    <Bars3Icon class="h-5 w-5" />
                                </button>
                                <button type="button" class="flex items-center justify-center rounded-2xl border px-3 py-2 transition" :class="selectedElement.textAlign === 'right' ? 'border-sky-500 bg-sky-500/10 text-sky-200' : 'border-slate-700 bg-slate-900 text-slate-300 hover:bg-slate-800'" @click="selectedElement.textAlign = 'right'">
                                    <Bars3BottomRightIcon class="h-5 w-5" />
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

                        <div class="grid gap-3 grid-cols-2">
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
                    Selecteer een element om de eigenschappen te wijzigen.
                </section>

                <section class="rounded-3xl border border-slate-800 bg-slate-950/60 p-4">
                    <div class="mb-3 text-xs font-semibold uppercase tracking-[0.18em] text-slate-500">Achtergrond</div>
                    <div class="space-y-3">
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
                                <button v-if="backgroundPreviewUrl" type="button" class="text-xs font-semibold text-rose-300 hover:text-rose-200" @click="clearTemplateBackground">Wissen</button>
                            </div>
                            <input :value="backgroundPreviewUrl" type="text" placeholder="https://..." class="w-full rounded-2xl border border-slate-700 bg-slate-900 px-4 py-3 text-sm text-white focus:border-sky-500 focus:outline-none focus:ring-2 focus:ring-sky-500/30" @input="onTemplateBackgroundUrlInput" />
                            <div class="flex flex-wrap items-center gap-2">
                                <label class="inline-flex cursor-pointer items-center gap-2 rounded-2xl border border-slate-700 bg-slate-900 px-3 py-2 text-sm font-semibold text-slate-200 hover:bg-slate-800">
                                    <PhotoIcon class="h-4 w-4" />
                                    Achtergrond uploaden
                                    <input type="file" accept="image/*" class="hidden" @change="onTemplateBackgroundPicked" />
                                </label>
                                <span v-if="templateUploadBusy" class="text-xs text-slate-400">Uploaden...</span>
                            </div>
                        </div>
                    </div>
                </section>
            </div>
        </div>
    </aside>
</template>

<script setup>
import { computed, ref } from 'vue'
import {
    Bars3BottomLeftIcon,
    Bars3BottomRightIcon,
    Bars3Icon,
    ChevronDownIcon,
    ChevronUpIcon,
    PhotoIcon,
    TrashIcon,
} from '@heroicons/vue/24/outline'
import { uploadBadgeTemplateImage } from '../services/badgeTemplateApi'
import { resolveImageUrl } from '../utils/badgeEditor'

const props = defineProps({
    template: { type: Object, required: true },
    selectedElement: { type: Object, default: null },
    availableFields: { type: Array, required: true },
    sampleData: { type: Object, default: () => ({}) },
})

const emit = defineEmits(['remove-selected', 'select-element', 'move-element-up', 'move-element-down'])

const templateUploadBusy = ref(false)
const elementUploadBusy = ref(false)

const supportsText = computed(() => ['text', 'field'].includes(props.selectedElement?.type))
const supportsImageSource = computed(() => ['photo', 'image', 'logo'].includes(props.selectedElement?.type))
const fallbackLabel = computed(() => {
    if (!props.selectedElement) return 'Element'
    return props.selectedElement.type === 'field' ? 'Dataveld' : props.selectedElement.type
})
const orderedElements = computed(() => [...(props.template.config_json.elements || [])].sort((a, b) => (b.zIndex ?? 1) - (a.zIndex ?? 1)))
const backgroundPreviewUrl = computed(() => resolveImageUrl(props.template.config_json.backgroundImagePath, props.template.config_json.backgroundImageUrl))
const elementPreviewUrl = computed(() => {
    if (!props.selectedElement) return ''
    return resolveImageUrl(props.selectedElement.imagePath, props.selectedElement.imageUrl)
})

function typeName(type) {
    return {
        field: 'Dataveld',
        text: 'Tekst',
        photo: 'Foto',
        image: 'Afbeelding',
        qr: 'QR-code',
        shape: 'Vorm',
    }[type] || type
}

function fallbackElementLabel(element) {
    return typeName(element.type)
}

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
