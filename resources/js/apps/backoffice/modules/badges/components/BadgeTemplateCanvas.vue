<template>
    <div class="flex h-full min-h-0 flex-col gap-4">
        <div class="flex items-center justify-end gap-2">
            <button type="button" class="inline-flex h-10 w-10 items-center justify-center rounded-2xl border border-slate-700 bg-slate-950/80 text-slate-200 transition hover:border-slate-600 hover:bg-slate-800" @click="zoomOut">
                <MinusIcon class="h-4 w-4" />
            </button>
            <button type="button" class="min-w-[5rem] rounded-2xl border border-slate-700 bg-slate-950/80 px-3 py-2 text-sm font-semibold text-slate-200 transition hover:border-slate-600 hover:bg-slate-800" @click="resetZoom">
                {{ Math.round(zoom * 100) }}%
            </button>
            <button type="button" class="inline-flex h-10 w-10 items-center justify-center rounded-2xl border border-slate-700 bg-slate-950/80 text-slate-200 transition hover:border-slate-600 hover:bg-slate-800" @click="zoomIn">
                <PlusIcon class="h-4 w-4" />
            </button>
            <button type="button" class="inline-flex h-10 items-center justify-center gap-2 rounded-2xl border border-slate-700 bg-slate-950/80 px-3 text-sm font-semibold text-slate-200 transition hover:border-slate-600 hover:bg-slate-800" @click="fitToWorkspace">
                <ArrowsPointingOutIcon class="h-4 w-4" />
                Passend
            </button>
        </div>

        <div ref="workspaceRef" class="relative flex min-h-0 flex-1 items-center justify-center overflow-auto rounded-3xl border border-slate-800 bg-slate-950/70 p-6">
            <div class="pointer-events-none absolute inset-0 opacity-100" :style="workspaceGridStyle" />

            <div class="relative flex items-center justify-center" :style="scaledStageBoundsStyle">
                <div
                    ref="viewportRef"
                    class="absolute left-1/2 top-1/2 overflow-hidden rounded-[28px] border border-slate-700 bg-slate-900 shadow-2xl"
                    :style="viewportStyle"
                    @pointermove="handlePointerMove"
                    @pointerup="stopDragging"
                    @pointerleave="stopDragging"
                >
                    <div class="absolute inset-0" :style="canvasStyle">
                        <div
                            v-for="element in orderedElements"
                            :key="element.id"
                            class="absolute cursor-move overflow-hidden border transition"
                            :class="selectedElementId === element.id
                                ? 'border-sky-400 ring-2 ring-sky-500/40'
                                : 'border-transparent hover:border-slate-400/50'"
                            :style="elementStyle(element)"
                            @pointerdown.stop.prevent="startDragging($event, element)"
                            @click.stop="$emit('select', element.id)"
                        >
                            <template v-if="element.type === 'shape'">
                                <div class="h-full w-full" :style="shapeStyle(element)" />
                            </template>

                            <template v-else-if="['photo', 'logo', 'image'].includes(element.type)">
                                <div class="relative h-full w-full" :style="mediaStyle(element)">
                                    <img v-if="mediaSource(element)" :src="mediaSource(element)" alt="" class="h-full w-full" :style="imageStyle(element)" />
                                    <div v-else class="flex h-full w-full items-center justify-center text-center text-xs font-semibold uppercase tracking-[0.18em] text-slate-200">
                                        {{ mediaLabel(element) }}
                                    </div>
                                </div>
                            </template>

                            <template v-else-if="element.type === 'qr'">
                                <div class="flex h-full w-full items-center justify-center" :style="mediaStyle(element)">
                                    <div class="grid h-[72%] w-[72%] grid-cols-5 gap-1">
                                        <div
                                            v-for="index in 25"
                                            :key="index"
                                            class="rounded-sm"
                                            :class="index % 2 === 0 || index % 5 === 0 ? 'bg-slate-950' : 'bg-white'"
                                        />
                                    </div>
                                </div>
                            </template>

                            <template v-else>
                                <div :style="textStyle(element)">
                                    {{ displayText(element) }}
                                </div>
                            </template>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</template>

<script setup>
import { ArrowsPointingOutIcon, MinusIcon, PlusIcon } from '@heroicons/vue/24/outline'
import { computed, nextTick, onMounted, ref, watch } from 'vue'
import { clamp, resolveImageUrl } from '../utils/badgeEditor'

const props = defineProps({
    template: {
        type: Object,
        required: true,
    },
    selectedElementId: {
        type: String,
        default: null,
    },
    sampleData: {
        type: Object,
        default: () => ({}),
    },
})

const emit = defineEmits(['select', 'update:position'])

const workspaceRef = ref(null)
const viewportRef = ref(null)
const dragState = ref(null)
const zoom = ref(0.75)

const orderedElements = computed(() => {
    return [...(props.template.elements ?? [])].sort((left, right) => (left.zIndex ?? 1) - (right.zIndex ?? 1))
})

const backgroundImageSource = computed(() => resolveImageUrl(props.template.backgroundImagePath, props.template.backgroundImageUrl))

const canvasStyle = computed(() => ({
    backgroundColor: props.template.backgroundColor || '#111827',
    backgroundImage: backgroundImageSource.value ? `url(${backgroundImageSource.value})` : undefined,
    backgroundSize: props.template.backgroundSize || 'cover',
    backgroundPosition: props.template.backgroundPosition || 'center',
    backgroundRepeat: 'no-repeat',
}))

const workspaceGridStyle = computed(() => ({
    backgroundImage: 'linear-gradient(rgba(148,163,184,0.14) 1px, transparent 1px), linear-gradient(90deg, rgba(148,163,184,0.14) 1px, transparent 1px)',
    backgroundSize: '28px 28px',
    backgroundPosition: 'center center',
}))

const scaledStageBoundsStyle = computed(() => ({
    width: `${Math.round(props.template.width * zoom.value)}px`,
    height: `${Math.round(props.template.height * zoom.value)}px`,
    minWidth: `${Math.round(props.template.width * zoom.value)}px`,
    minHeight: `${Math.round(props.template.height * zoom.value)}px`,
}))

const viewportStyle = computed(() => ({
    width: `${props.template.width}px`,
    height: `${props.template.height}px`,
    transform: `translate(-50%, -50%) scale(${zoom.value})`,
    transformOrigin: 'center center',
}))

watch(
    () => [props.template.width, props.template.height],
    async () => {
        await nextTick()
        fitToWorkspace()
    }
)

onMounted(async () => {
    await nextTick()
    fitToWorkspace()
})

function fitToWorkspace() {
    const workspace = workspaceRef.value
    if (!workspace) {
        return
    }

    const availableWidth = Math.max(workspace.clientWidth - 48, 240)
    const availableHeight = Math.max(workspace.clientHeight - 48, 180)
    const nextZoom = Math.min(1, availableWidth / props.template.width, availableHeight / props.template.height)
    zoom.value = clamp(Number.isFinite(nextZoom) ? nextZoom : 1, 0.25, 2)
}

function zoomIn() {
    zoom.value = clamp(Math.round((zoom.value + 0.1) * 100) / 100, 0.25, 2)
}

function zoomOut() {
    zoom.value = clamp(Math.round((zoom.value - 0.1) * 100) / 100, 0.25, 2)
}

function resetZoom() {
    zoom.value = 1
}

function startDragging(event, element) {
    const bounds = viewportRef.value?.getBoundingClientRect()
    if (!bounds) {
        return
    }

    emit('select', element.id)

    dragState.value = {
        id: element.id,
        startClientX: event.clientX,
        startClientY: event.clientY,
        startX: Number(element.x || 0),
        startY: Number(element.y || 0),
        scaleX: props.template.width / bounds.width,
        scaleY: props.template.height / bounds.height,
    }
}

function handlePointerMove(event) {
    if (!dragState.value) {
        return
    }

    const deltaX = (event.clientX - dragState.value.startClientX) * dragState.value.scaleX
    const deltaY = (event.clientY - dragState.value.startClientY) * dragState.value.scaleY

    emit('update:position', {
        id: dragState.value.id,
        x: Math.max(0, Math.round(dragState.value.startX + deltaX)),
        y: Math.max(0, Math.round(dragState.value.startY + deltaY)),
    })
}

function stopDragging() {
    dragState.value = null
}

function elementStyle(element) {
    return {
        left: `${element.x}px`,
        top: `${element.y}px`,
        width: `${element.width}px`,
        height: `${element.height}px`,
        zIndex: element.zIndex ?? 1,
        borderRadius: `${element.borderRadius ?? 0}px`,
        opacity: element.opacity ?? 1,
        boxSizing: 'border-box',
    }
}

function textStyle(element) {
    return {
        display: 'flex',
        alignItems: 'center',
        justifyContent: element.textAlign === 'center' ? 'center' : element.textAlign === 'right' ? 'flex-end' : 'flex-start',
        width: '100%',
        height: '100%',
        boxSizing: 'border-box',
        color: element.color || '#ffffff',
        background: element.backgroundColor || 'transparent',
        fontSize: `${element.fontSize || 32}px`,
        fontWeight: element.fontWeight || 700,
        fontFamily: 'Arial, Helvetica, sans-serif',
        padding: '0 12px',
        textAlign: element.textAlign || 'left',
        lineHeight: 1.08,
        whiteSpace: 'pre-wrap',
        overflow: 'hidden',
    }
}

function mediaSource(element) {
    return resolveImageUrl(element.imagePath, element.imageUrl)
}

function mediaStyle(element) {
    return {
        background: mediaSource(element) ? 'transparent' : element.backgroundColor || 'linear-gradient(135deg, #7c3aed, #0f172a)',
        borderRadius: `${element.borderRadius ?? 0}px`,
    }
}

function imageStyle(element) {
    return {
        objectFit: element.fit || (element.type === 'logo' ? 'contain' : 'cover'),
    }
}

function shapeStyle(element) {
    return {
        background: element.backgroundColor || '#7c3aed',
        borderRadius: `${element.borderRadius ?? 0}px`,
        opacity: element.opacity ?? 1,
    }
}

function displayText(element) {
    if (element.type === 'text') {
        return element.text || 'Tekst'
    }

    if (element.type === 'field') {
        return props.sampleData[element.source] || `{{ ${element.source || 'veld'} }}`
    }

    return element.label || ''
}

function mediaLabel(element) {
    if (element.type === 'photo') return 'Foto'
    if (element.type === 'logo') return 'Logo'
    return 'Afbeelding'
}
</script>
