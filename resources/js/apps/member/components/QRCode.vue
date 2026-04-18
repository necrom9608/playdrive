<template>
    <canvas ref="canvas" :width="size" :height="size" />
</template>

<script setup>
import { ref, watch, onMounted } from 'vue'

const props = defineProps({
    value: { type: String, required: true },
    size: { type: Number, default: 180 },
})

const canvas = ref(null)

async function draw() {
    if (!canvas.value || !props.value) return
    // Gebruik de QRCode library via dynamic import
    const QRCodeLib = await import('qrcode')
    await QRCodeLib.toCanvas(canvas.value, props.value, {
        width: props.size,
        margin: 1,
        color: { dark: '#000000', light: '#ffffff' },
    })
}

onMounted(draw)
watch(() => props.value, draw)
</script>
