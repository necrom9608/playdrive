<template>
    <div class="relative overflow-hidden rounded-[20px] border border-slate-700 bg-slate-950/80" :style="wrapperStyle">
        <div class="absolute inset-0" :style="canvasStyle" />
        <div class="absolute inset-0">
            <div
                v-for="element in orderedElements"
                :key="element.id"
                class="absolute overflow-hidden"
                :style="elementStyle(element)"
            >
                <template v-if="element.type === 'shape'">
                    <div class="h-full w-full" :style="shapeStyle(element)" />
                </template>

                <template v-else-if="['photo', 'logo', 'image'].includes(element.type)">
                    <div class="relative h-full w-full" :style="mediaStyle(element)">
                        <img v-if="mediaSource(element)" :src="mediaSource(element)" alt="" class="h-full w-full" :style="imageStyle(element)" />
                        <div v-else class="flex h-full w-full items-center justify-center px-2 text-center text-[8px] font-semibold uppercase tracking-[0.18em] text-slate-200">
                            {{ mediaLabel(element) }}
                        </div>
                    </div>
                </template>

                <template v-else-if="element.type === 'qr'">
                    <div class="flex h-full w-full items-center justify-center" :style="mediaStyle(element)">
                        <div class="grid h-[72%] w-[72%] grid-cols-5 gap-[2px]">
                            <div
                                v-for="index in 25"
                                :key="index"
                                class="rounded-[1px]"
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
</template>

<script setup>
import { computed } from 'vue'
import { resolveImageUrl, sampleData } from '../utils/badgeEditor'

const props = defineProps({
    template: { type: Object, required: true },
    width: { type: Number, default: 280 },
})

const scale = computed(() => props.width / (props.template?.config_json?.width || 1016))
const config = computed(() => props.template?.config_json || props.template || { width: 1016, height: 638, elements: [] })
const ratioHeight = computed(() => Math.round((config.value.height || 638) * scale.value))
const previewData = computed(() => sampleData[props.template?.template_type] || sampleData.staff)

const wrapperStyle = computed(() => ({
    width: `${props.width}px`,
    height: `${ratioHeight.value}px`,
}))

const orderedElements = computed(() => [...(config.value.elements || [])].sort((a, b) => (a.zIndex ?? 1) - (b.zIndex ?? 1)))

const backgroundImageSource = computed(() => resolveImageUrl(config.value.backgroundImagePath, config.value.backgroundImageUrl))

const canvasStyle = computed(() => ({
    backgroundColor: config.value.backgroundColor || '#111827',
    backgroundImage: backgroundImageSource.value ? `url(${backgroundImageSource.value})` : undefined,
    backgroundSize: config.value.backgroundSize || 'cover',
    backgroundPosition: config.value.backgroundPosition || 'center',
    backgroundRepeat: 'no-repeat',
}))

function scaled(value) {
    return `${Math.round((Number(value) || 0) * scale.value)}px`
}

function elementStyle(element) {
    return {
        left: scaled(element.x),
        top: scaled(element.y),
        width: scaled(element.width),
        height: scaled(element.height),
        zIndex: element.zIndex ?? 1,
        borderRadius: scaled(element.borderRadius ?? 0),
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
        fontSize: scaled(element.fontSize || 32),
        fontWeight: element.fontWeight || 700,
        fontFamily: 'Arial, Helvetica, sans-serif',
        padding: `0 ${Math.max(4, Math.round(12 * scale.value))}px`,
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
        borderRadius: scaled(element.borderRadius ?? 0),
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
        borderRadius: scaled(element.borderRadius ?? 0),
        opacity: element.opacity ?? 1,
    }
}

function displayText(element) {
    if (element.type === 'text') return element.text || 'Tekst'
    if (element.type === 'field') return previewData.value[element.source] || `{{ ${element.source || 'veld'} }}`
    return element.label || ''
}

function mediaLabel(element) {
    if (element.type === 'photo') return 'Foto'
    if (element.type === 'logo') return 'Logo'
    return 'Afbeelding'
}
</script>
