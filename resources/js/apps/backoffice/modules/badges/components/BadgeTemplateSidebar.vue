<template>
    <aside class="flex h-full min-h-0 flex-col rounded-3xl border border-slate-800 bg-slate-900/80 shadow-xl shadow-slate-950/20">
        <div class="border-b border-slate-800 px-5 py-4">
            <div class="flex items-center justify-between gap-3">
                <h2 class="text-lg font-semibold text-white">Templates</h2>
                <button
                    type="button"
                    class="inline-flex items-center gap-2 rounded-2xl border border-slate-700 bg-slate-950 px-3 py-2 text-xs font-semibold text-slate-200 transition hover:bg-slate-800"
                    @click="$emit('refresh')"
                >
                    <ArrowPathIcon class="h-4 w-4" />
                    Vernieuwen
                </button>
            </div>

            <div class="mt-4 grid grid-cols-1 gap-2">
                <button
                    v-for="option in filters"
                    :key="option.value"
                    type="button"
                    class="flex items-center gap-3 rounded-2xl border px-3 py-3 text-sm font-semibold transition"
                    :class="activeFilter === option.value ? 'border-sky-500 bg-sky-500/15 text-white' : 'border-slate-800 bg-slate-950/70 text-slate-300 hover:border-slate-700 hover:bg-slate-900'"
                    @click="$emit('update:activeFilter', option.value)"
                >
                    <component :is="option.icon" class="h-5 w-5" />
                    <span>{{ option.label }}</span>
                </button>
            </div>

            <button
                type="button"
                class="mt-4 inline-flex w-full items-center justify-center gap-2 rounded-2xl border border-slate-700 bg-slate-950 px-4 py-3 text-sm font-semibold text-slate-100 transition hover:border-slate-600 hover:bg-slate-800"
                @click="$emit('create-template', activeFilter)"
            >
                <PlusIcon class="h-5 w-5" />
                Nieuw
            </button>
        </div>

        <div class="min-h-0 flex-1 overflow-y-auto px-5 py-4">
            <div class="space-y-3">
                <button
                    v-for="template in savedTemplates"
                    :key="template.id"
                    type="button"
                    class="w-full rounded-3xl border p-3 text-left transition"
                    :class="isCurrent(template) ? 'border-sky-500 bg-sky-500/10' : 'border-slate-800 bg-slate-950/60 hover:border-slate-700 hover:bg-slate-950'"
                    @click="$emit('load-saved-template', template)"
                >
                    <div class="flex justify-center overflow-hidden rounded-2xl bg-slate-950/80 p-2">
                        <BadgeTemplateThumbnail :template="template" :width="290" />
                    </div>
                    <div class="mt-3 flex items-center justify-between gap-3">
                        <div class="min-w-0">
                            <div class="truncate text-sm font-semibold text-white">{{ template.name }}</div>
                            <div class="mt-1 text-xs text-slate-500">{{ typeLabel(template.template_type) }}</div>
                        </div>
                        <span v-if="template.is_default" class="rounded-full border border-emerald-500/30 bg-emerald-500/10 px-2.5 py-1 text-[11px] font-semibold text-emerald-200">Standaard</span>
                    </div>
                </button>

                <div v-if="!savedTemplates.length && !loading" class="rounded-3xl border border-dashed border-slate-800 px-4 py-8 text-center text-sm text-slate-400">
                    Nog geen templates opgeslagen voor dit type.
                </div>
            </div>
        </div>
    </aside>
</template>

<script setup>
import { ArrowPathIcon, PlusIcon } from '@heroicons/vue/24/outline'
import BadgeTemplateThumbnail from './BadgeTemplateThumbnail.vue'
import { typeLabel } from '../utils/badgeEditor'

const props = defineProps({
    filters: { type: Array, required: true },
    activeFilter: { type: String, required: true },
    savedTemplates: { type: Array, required: true },
    loading: { type: Boolean, default: false },
    currentTemplateId: { type: [Number, String, null], default: null },
})

defineEmits(['refresh', 'update:activeFilter', 'create-template', 'load-saved-template'])

function isCurrent(template) {
    return props.currentTemplateId ? String(props.currentTemplateId) === String(template.id) : false
}
</script>
