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

        <div ref="workspaceRef" class="relative flex min-h-0 flex-1 items-center justify-center overflow-auto rounded-3xl border border-slate-800 bg-slate-950/70 p-6" @wheel.prevent="handleWheelZoom">
            <div class="pointer-events-none absolute inset-0" :style="workspaceGridStyle" />

            <div class="relative flex items-center justify-center" :style="scaledStageBoundsStyle">
                <div
                    ref="viewportRef"
                    class="absolute left-1/2 top-1/2 overflow-visible rounded-[28px] border border-slate-700 bg-slate-900 shadow-[0_18px_40px_rgba(2,6,23,0.28)]"
                    :style="viewportStyle"
                    @pointermove="handlePointerMove"
                    @pointerup="stopInteraction"
                    @pointerleave="stopInteraction"
                >
                    <div class="absolute inset-0 overflow-hidden rounded-[28px]" :style="canvasStyle">
                        <div
                            v-for="element in orderedElements"
                            :key="element.id"
                            class="absolute overflow-visible border transition"
                            :class="selectedElementId === element.id ? 'border-sky-400 ring-2 ring-sky-500/40' : 'border-transparent hover:border-slate-400/50'"
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
                                <div class="flex h-full w-full flex-col items-center justify-center gap-2 overflow-hidden px-3 py-3" :style="mediaStyle(element)">
                                    <div class="grid h-[62%] w-[62%] grid-cols-5 gap-1">
                                        <div
                                            v-for="index in 25"
                                            :key="index"
                                            class="rounded-sm"
                                            :class="index % 2 === 0 || index % 5 === 0 ? 'bg-slate-950' : 'bg-white'"
                                        />
                                    </div>
                                    <div class="w-full truncate text-center text-[10px] font-semibold uppercase tracking-[0.18em] text-slate-700">
                                        {{ qrPreviewText(element) }}
                                    </div>
                                </div>
                            </template>

                            <template v-else>
                                <div :style="textStyle(element)">
                                    {{ displayText(element) }}
                                </div>
                            </template>

                            <template v-if="selectedElementId === element.id">
                                <button
                                    v-for="handle in resizeHandles"
                                    :key="handle.position"
                                    type="button"
                                    class="absolute h-3.5 w-3.5 rounded-sm border border-white bg-sky-500 shadow"
                                    :style="resizeHandleStyle(handle.position)"
                                    @pointerdown.stop.prevent="startResizing($event, element, handle.position)"
                                />
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

const MIN_ELEMENT_SIZE = 24

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

const emit = defineEmits(['select', 'update:position', 'update:bounds'])

const workspaceRef = ref(null)
const viewportRef = ref(null)
const interactionState = ref(null)
const zoom = ref(0.75)
const resizeHandles = [
    { position: 'nw' },
    { position: 'n' },
    { position: 'ne' },
    { position: 'e' },
    { position: 'se' },
    { position: 's' },
    { position: 'sw' },
    { position: 'w' },
]

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
    backgroundImage: 'linear-gradient(rgba(148,163,184,0.08) 1px, transparent 1px), linear-gradient(90deg, rgba(148,163,184,0.08) 1px, transparent 1px)',
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

    const availableWidth = Math.max(workspace.clientWidth - 56, 240)
    const availableHeight = Math.max(workspace.clientHeight - 56, 180)
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

function handleWheelZoom(event) {
    const delta = event.deltaY < 0 ? 0.05 : -0.05
    zoom.value = clamp(Math.round((zoom.value + delta) * 100) / 100, 0.25, 2)
}

function startDragging(event, element) {
    const bounds = viewportRef.value?.getBoundingClientRect()
    if (!bounds) return

    emit('select', element.id)

    interactionState.value = {
        mode: 'drag',
        id: element.id,
        startClientX: event.clientX,
        startClientY: event.clientY,
        startX: Number(element.x || 0),
        startY: Number(element.y || 0),
        startWidth: Number(element.width || 0),
        startHeight: Number(element.height || 0),
        scaleX: props.template.width / bounds.width,
        scaleY: props.template.height / bounds.height,
    }
}

function startResizing(event, element, handle) {
    const bounds = viewportRef.value?.getBoundingClientRect()
    if (!bounds) return

    emit('select', element.id)

    interactionState.value = {
        mode: 'resize',
        handle,
        id: element.id,
        startClientX: event.clientX,
        startClientY: event.clientY,
        startX: Number(element.x || 0),
        startY: Number(element.y || 0),
        startWidth: Number(element.width || 0),
        startHeight: Number(element.height || 0),
        scaleX: props.template.width / bounds.width,
        scaleY: props.template.height / bounds.height,
    }
}

function handlePointerMove(event) {
    if (!interactionState.value) return

    if (interactionState.value.mode === 'drag') {
        handleDragMove(event)
        return
    }

    handleResizeMove(event)
}

function handleDragMove(event) {
    const deltaX = (event.clientX - interactionState.value.startClientX) * interactionState.value.scaleX
    const deltaY = (event.clientY - interactionState.value.startClientY) * interactionState.value.scaleY

    emit('update:position', {
        id: interactionState.value.id,
        x: Math.max(0, Math.round(interactionState.value.startX + deltaX)),
        y: Math.max(0, Math.round(interactionState.value.startY + deltaY)),
    })
}

function handleResizeMove(event) {
    const state = interactionState.value
    const dx = (event.clientX - state.startClientX) * state.scaleX
    const dy = (event.clientY - state.startClientY) * state.scaleY

    let x = state.startX
    let y = state.startY
    let width = state.startWidth
    let height = state.startHeight

    if (state.handle.includes('e')) {
        width = state.startWidth + dx
    }
    if (state.handle.includes('s')) {
        height = state.startHeight + dy
    }
    if (state.handle.includes('w')) {
        width = state.startWidth - dx
        x = state.startX + dx
    }
    if (state.handle.includes('n')) {
        height = state.startHeight - dy
        y = state.startY + dy
    }

    if (width < MIN_ELEMENT_SIZE) {
        if (state.handle.includes('w')) x -= MIN_ELEMENT_SIZE - width
        width = MIN_ELEMENT_SIZE
    }
    if (height < MIN_ELEMENT_SIZE) {
        if (state.handle.includes('n')) y -= MIN_ELEMENT_SIZE - height
        height = MIN_ELEMENT_SIZE
    }

    x = clamp(Math.round(x), 0, props.template.width - width)
    y = clamp(Math.round(y), 0, props.template.height - height)
    width = clamp(Math.round(width), MIN_ELEMENT_SIZE, props.template.width - x)
    height = clamp(Math.round(height), MIN_ELEMENT_SIZE, props.template.height - y)

    emit('update:bounds', {
        id: state.id,
        x,
        y,
        width,
        height,
    })
}

function stopInteraction() {
    interactionState.value = null
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
        cursor: 'move',
    }
}

function resizeHandleStyle(position) {
    const common = { transform: 'translate(-50%, -50%)' }
    const map = {
        nw: { left: '0%', top: '0%', cursor: 'nwse-resize' },
        n: { left: '50%', top: '0%', cursor: 'ns-resize' },
        ne: { left: '100%', top: '0%', cursor: 'nesw-resize' },
        e: { left: '100%', top: '50%', cursor: 'ew-resize' },
        se: { left: '100%', top: '100%', cursor: 'nwse-resize' },
        s: { left: '50%', top: '100%', cursor: 'ns-resize' },
        sw: { left: '0%', top: '100%', cursor: 'nesw-resize' },
        w: { left: '0%', top: '50%', cursor: 'ew-resize' },
    }

    return { ...common, ...(map[position] || {}) }
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
        borderRadius: `${element.borderRadius ?? 0}px`,
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
    if (element.type === 'text') return element.text || 'Tekst'
    if (element.type === 'field') return props.sampleData[element.source] || `{{ ${element.source || 'veld'} }}`
    return element.label || ''
}

function qrPreviewText(element) {
    const source = element.source || 'badge_number'
    return props.sampleData[source] || `{{ ${source} }}`
}

function mediaLabel(element) {
    if (element.type === 'photo') return 'Foto'
    if (element.type === 'logo') return 'Logo'
    return 'Afbeelding'
}
</script>
