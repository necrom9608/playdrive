<template>
    <ModalDialog :open="open" :title="setup ? `QR-code · ${setup.name}` : 'QR-code'" description="Klanten scannen deze code aan de speelplek." @close="$emit('close')">
        <div v-if="setup" class="flex flex-col items-center gap-5">
            <div class="rounded-3xl bg-white p-4">
                <img v-if="dataUrl" :src="dataUrl" alt="QR-code" class="h-64 w-64" />
                <div v-else class="flex h-64 w-64 items-center justify-center text-sm text-slate-500">QR maken...</div>
            </div>

            <div class="w-full rounded-2xl border border-slate-800 bg-slate-950/60 px-4 py-3 text-center">
                <a :href="setup.qr_url" target="_blank" rel="noopener" class="break-all font-mono text-sm text-sky-300 hover:text-sky-200">{{ setup.qr_url }}</a>
            </div>

            <div class="flex w-full flex-wrap justify-center gap-3">
                <button type="button" class="inline-flex items-center gap-2 rounded-xl bg-blue-600 px-4 py-3 text-sm font-semibold text-white transition hover:bg-blue-500" @click="printLabel">
                    <PrinterIcon class="h-5 w-5" /> Label printen
                </button>
                <button type="button" class="inline-flex items-center gap-2 rounded-xl border border-slate-700 px-4 py-3 text-sm font-medium text-slate-200 transition hover:bg-slate-800" @click="downloadPng">
                    <ArrowDownTrayIcon class="h-5 w-5" /> PNG downloaden
                </button>
                <button type="button" class="inline-flex items-center gap-2 rounded-xl border border-amber-500/30 bg-amber-500/10 px-4 py-3 text-sm font-medium text-amber-200 transition hover:bg-amber-500/20" @click="$emit('regenerate')">
                    <ArrowPathIcon class="h-5 w-5" /> Nieuwe code
                </button>
            </div>
            <p class="text-center text-xs text-slate-500">Een nieuwe code maakt een geprinte QR-code ongeldig.</p>
        </div>
    </ModalDialog>
</template>

<script setup>
import { ref, watch } from 'vue'
import QRCode from 'qrcode'
import { ArrowDownTrayIcon, ArrowPathIcon, PrinterIcon } from '@heroicons/vue/24/outline'
import ModalDialog from '../../../components/ModalDialog.vue'

const props = defineProps({
    open: { type: Boolean, default: false },
    setup: { type: Object, default: null },
    tenantName: { type: String, default: '' },
})

defineEmits(['close', 'regenerate'])

const dataUrl = ref('')

watch(() => [props.open, props.setup?.qr_url], async ([open, url]) => {
    dataUrl.value = ''
    if (!open || !url) return
    dataUrl.value = await QRCode.toDataURL(url, { width: 1024, margin: 1, errorCorrectionLevel: 'M' })
}, { immediate: true })

function fileName() {
    return `qr-${(props.setup?.name || 'speelplek').toLowerCase().replace(/[^a-z0-9]+/g, '-')}.png`
}

function downloadPng() {
    if (!dataUrl.value) return
    const a = document.createElement('a')
    a.href = dataUrl.value
    a.download = fileName()
    a.click()
}

function escapeHtml(value) {
    return String(value ?? '').replace(/[&<>"']/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]))
}

function printLabel() {
    if (!dataUrl.value || !props.setup) return
    const win = window.open('', '_blank', 'width=480,height=640')
    if (!win) return
    win.document.write(`<!DOCTYPE html><html><head><meta charset="utf-8"><title>${escapeHtml(props.setup.name)}</title>
<style>
  @page { size: 80mm 110mm; margin: 0; }
  body { margin: 0; font-family: system-ui, sans-serif; display: flex; justify-content: center; }
  .label { width: 80mm; height: 110mm; padding: 6mm; box-sizing: border-box; text-align: center; display: flex; flex-direction: column; align-items: center; justify-content: center; gap: 3mm; }
  .label img { width: 62mm; height: 62mm; }
  .title { font-size: 15pt; font-weight: 700; line-height: 1.15; }
  .cta { font-size: 11pt; }
  .venue { font-size: 8pt; color: #555; }
</style></head><body>
<div class="label">
  <div class="title">${escapeHtml(props.setup.name)}</div>
  <img src="${dataUrl.value}" alt="">
  <div class="cta">Scan voor highscores &amp; klassement</div>
  <div class="venue">${escapeHtml(props.tenantName)} · playdrive</div>
</div>
<script>window.onload = () => { window.print(); }<\/script>
</body></html>`)
    win.document.close()
}
</script>
