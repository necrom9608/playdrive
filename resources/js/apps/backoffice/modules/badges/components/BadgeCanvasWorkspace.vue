<template>
    <section class="flex h-full min-h-0 flex-col rounded-3xl border border-slate-800 bg-slate-900/80 shadow-xl shadow-slate-950/20">
        <div class="border-b border-slate-800 px-5 py-4">
            <div class="flex flex-wrap items-start justify-between gap-4">
                <div>
                    <div class="flex flex-wrap items-center gap-2">
                        <h2 class="text-xl font-semibold text-white">{{ template.name || 'Nieuwe badge template' }}</h2>
                        <span class="rounded-full border border-slate-700 bg-slate-950 px-3 py-1 text-xs font-semibold uppercase tracking-[0.16em] text-slate-300">
                            {{ typeLabel(template.template_type) }}
                        </span>
                        <span v-if="isPreset" class="rounded-full border border-sky-500/30 bg-sky-500/10 px-3 py-1 text-xs font-semibold text-sky-200">
                            Nog niet opgeslagen
                        </span>
                    </div>
                    <p class="mt-2 text-sm text-slate-400">{{ template.description || 'Kies links een template en werk het ontwerp in het midden verder uit.' }}</p>
                </div>

                <div class="flex flex-wrap gap-2">
                    <button type="button" class="rounded-2xl border border-slate-700 bg-slate-950 px-4 py-2.5 text-sm font-semibold text-slate-200 transition hover:border-slate-600 hover:bg-slate-800" @click="$emit('duplicate')">Dupliceren</button>
                    <button type="button" class="rounded-2xl border border-slate-700 bg-slate-950 px-4 py-2.5 text-sm font-semibold text-slate-200 transition hover:border-slate-600 hover:bg-slate-800" @click="$emit('reset')">Leeg maken</button>
                    <button type="button" class="rounded-2xl bg-sky-500 px-4 py-2.5 text-sm font-semibold text-white transition hover:bg-sky-400 disabled:cursor-not-allowed disabled:opacity-50" :disabled="saving" @click="$emit('save')">
                        {{ saving ? 'Opslaan...' : hasStoredTemplate ? 'Template bijwerken' : 'Als nieuwe template opslaan' }}
                    </button>
                </div>
            </div>
        </div>

        <div class="flex min-h-0 flex-1 flex-col bg-slate-950/40 p-5">
            <BadgeTemplateCanvas
                class="min-h-0 flex-1"
                :template="template.config_json"
                :selected-element-id="selectedElementId"
                :sample-data="sampleData"
                @select="$emit('select-element', $event)"
                @update:position="$emit('update-element-position', $event)"
            />
        </div>
    </section>
</template>

<script setup>
import BadgeTemplateCanvas from './BadgeTemplateCanvas.vue'
import { typeLabel } from '../utils/badgeEditor'

defineProps({
    template: { type: Object, required: true },
    selectedElementId: { type: String, default: null },
    sampleData: { type: Object, default: () => ({}) },
    isPreset: { type: Boolean, default: false },
    hasStoredTemplate: { type: Boolean, default: false },
    saving: { type: Boolean, default: false },
})

defineEmits(['duplicate', 'reset', 'save', 'select-element', 'update-element-position'])
</script>
