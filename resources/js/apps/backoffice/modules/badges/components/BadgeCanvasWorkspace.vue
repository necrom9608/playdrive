<template>
    <section class="flex h-full min-h-0 flex-col rounded-3xl border border-slate-800 bg-slate-900/80 shadow-xl shadow-slate-950/20">
        <div class="border-b border-slate-800 px-5 py-4">
            <div class="flex flex-wrap items-start justify-between gap-4">
                <div>
                    <h2 class="text-xl font-semibold text-white">Werkveld</h2>
                    <p class="mt-2 text-sm text-slate-400">Bewerk je badge in het midden en gebruik zoom om comfortabel in of uit te zoomen.</p>
                </div>

                <div class="flex flex-wrap gap-2">
                    <button type="button" class="inline-flex items-center gap-2 rounded-2xl border border-slate-700 bg-slate-950 px-4 py-2.5 text-sm font-semibold text-slate-200 transition hover:border-slate-600 hover:bg-slate-800" @click="$emit('duplicate')">
                        <Squares2X2Icon class="h-4 w-4" />
                        Dupliceren
                    </button>
                    <button type="button" class="inline-flex items-center gap-2 rounded-2xl border border-slate-700 bg-slate-950 px-4 py-2.5 text-sm font-semibold text-slate-200 transition hover:border-slate-600 hover:bg-slate-800" @click="$emit('reset')">
                        <DocumentMinusIcon class="h-4 w-4" />
                        Leeg maken
                    </button>
                    <button
                        type="button"
                        class="inline-flex items-center gap-2 rounded-2xl border border-rose-500/30 bg-rose-500/10 px-4 py-2.5 text-sm font-semibold text-rose-200 transition hover:bg-rose-500/20 disabled:cursor-not-allowed disabled:opacity-50"
                        :disabled="!hasStoredTemplate"
                        @click="$emit('delete')"
                    >
                        <TrashIcon class="h-4 w-4" />
                        Verwijderen
                    </button>
                    <button type="button" class="inline-flex items-center gap-2 rounded-2xl bg-sky-500 px-4 py-2.5 text-sm font-semibold text-white transition hover:bg-sky-400 disabled:cursor-not-allowed disabled:opacity-50" :disabled="saving" @click="$emit('save')">
                        <CloudArrowUpIcon class="h-4 w-4" />
                        {{ saving ? 'Opslaan...' : 'Opslaan' }}
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
import {
    CloudArrowUpIcon,
    DocumentMinusIcon,
    Squares2X2Icon,
    TrashIcon,
} from '@heroicons/vue/24/outline'
import BadgeTemplateCanvas from './BadgeTemplateCanvas.vue'

defineProps({
    template: { type: Object, required: true },
    selectedElementId: { type: String, default: null },
    sampleData: { type: Object, default: () => ({}) },
    hasStoredTemplate: { type: Boolean, default: false },
    saving: { type: Boolean, default: false },
})

defineEmits(['duplicate', 'reset', 'save', 'delete', 'select-element', 'update-element-position'])
</script>
