<template>
    <div class="flex h-full min-h-0 flex-col gap-4">
        <div class="flex items-center justify-between gap-3">
            <div>
                <h3 class="text-lg font-semibold text-white">Live preview</h3>
                <p class="mt-1 text-sm text-slate-400">Sleep elementen op de badge en verfijn hun eigenschappen rechts.</p>
            </div>

            <div class="flex items-center gap-2 text-xs text-slate-400">
                <span class="rounded-full border border-slate-700 bg-slate-950/70 px-3 py-1.5">{{ template.width }} × {{ template.height }}</span>
                <span class="rounded-full border border-slate-700 bg-slate-950/70 px-3 py-1.5">safe zone zichtbaar</span>
            </div>
        </div>

        <div class="flex min-h-0 flex-1 items-center justify-center rounded-3xl border border-slate-800 bg-slate-950/70 p-4">
            <div ref="viewportRef" class="mx-auto w-full max-w-[860px]">
                <div
                    class="relative w-full overflow-hidden rounded-[28px] border border-slate-700 bg-slate-900 shadow-2xl"
                    :style="viewportStyle"
                    @pointermove="handlePointerMove"
                    @pointerup="stopDragging"
                    @pointerleave="stopDragging"
                >
                    <div class="absolute inset-0 opacity-40" :style="gridStyle" />

                    <div
                        class="pointer-events-none absolute border border-dashed border-slate-500/70"
                        :style="safeZoneStyle"
                    />

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
import { computed, ref } from 'vue'
import { resolveImageUrl } from '../utils/badgeEditor'

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

const viewportRef = ref(null)
const dragState = ref(null)

const orderedElements = computed(() => {
    return [...(props.template.elements ?? [])].sort((left, right) => (left.zIndex ?? 1) - (right.zIndex ?? 1))
})

const viewportStyle = computed(() => ({
    aspectRatio: `${props.template.width} / ${props.template.height}`,
}))


const backgroundImageSource = computed(() => resolveImageUrl(props.template.backgroundImagePath, props.template.backgroundImageUrl))

const canvasStyle = computed(() => ({
    backgroundColor: props.template.backgroundColor || '#111827',
    backgroundImage: backgroundImageSource.value ? `url(${backgroundImageSource.value})` : undefined,
    backgroundSize: props.template.backgroundSize || 'cover',
    backgroundPosition: props.template.backgroundPosition || 'center',
    backgroundRepeat: 'no-repeat',
}))

const safeZoneStyle = computed(() => ({
    top: '4.5%',
    left: '4.5%',
    right: '4.5%',
    bottom: '4.5%',
    borderRadius: '20px',
}))

const gridStyle = computed(() => ({
    backgroundImage: 'linear-gradient(rgba(148,163,184,0.08) 1px, transparent 1px), linear-gradient(90deg, rgba(148,163,184,0.08) 1px, transparent 1px)',
    backgroundSize: '24px 24px',
}))

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
