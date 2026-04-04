<template>
    <aside class="flex h-full min-h-0 flex-col rounded-3xl border border-slate-800 bg-slate-900/80 shadow-xl shadow-slate-950/20">
        <div class="border-b border-slate-800 px-5 py-4">
            <div class="flex items-center justify-between gap-3">
                <div>
                    <h2 class="text-lg font-semibold text-white">Templates</h2>
                    <p class="mt-1 text-sm text-slate-400">Kies een starttemplate of laad een opgeslagen ontwerp.</p>
                </div>
                <button
                    type="button"
                    class="rounded-2xl border border-slate-700 bg-slate-950 px-3 py-2 text-xs font-semibold text-slate-200 hover:bg-slate-800"
                    @click="$emit('refresh')"
                >
                    Vernieuwen
                </button>
            </div>

            <div class="mt-4 flex gap-2 rounded-2xl border border-slate-800 bg-slate-950/70 p-1">
                <button
                    v-for="option in filters"
                    :key="option.value"
                    type="button"
                    class="flex-1 rounded-xl px-3 py-2 text-sm font-semibold transition"
                    :class="activeFilter === option.value ? 'bg-sky-500 text-white' : 'text-slate-300 hover:bg-slate-800'"
                    @click="$emit('update:activeFilter', option.value)"
                >
                    {{ option.label }}
                </button>
            </div>

            <div class="mt-4 grid grid-cols-3 gap-2">
                <button
                    type="button"
                    class="rounded-2xl border border-slate-700 bg-slate-950 px-3 py-2.5 text-xs font-semibold text-slate-200 transition hover:border-slate-600 hover:bg-slate-800"
                    @click="$emit('create-template', 'staff')"
                >
                    Nieuwe staff
                </button>
                <button
                    type="button"
                    class="rounded-2xl border border-slate-700 bg-slate-950 px-3 py-2.5 text-xs font-semibold text-slate-200 transition hover:border-slate-600 hover:bg-slate-800"
                    @click="$emit('create-template', 'member')"
                >
                    Nieuwe member
                </button>
                <button
                    type="button"
                    class="rounded-2xl border border-slate-700 bg-slate-950 px-3 py-2.5 text-xs font-semibold text-slate-200 transition hover:border-slate-600 hover:bg-slate-800"
                    @click="$emit('create-template', 'voucher')"
                >
                    Nieuwe voucher
                </button>
            </div>
        </div>

        <div class="min-h-0 flex-1 overflow-y-auto px-5 py-4">
            <div class="space-y-6">
                <div>
                    <div class="mb-3 text-xs font-semibold uppercase tracking-[0.18em] text-slate-500">Starttemplates</div>
                    <div class="space-y-3">
                        <button
                            v-for="preset in presets"
                            :key="preset.id"
                            type="button"
                            class="w-full rounded-3xl border px-4 py-4 text-left transition"
                            :class="isCurrent(preset) ? 'border-sky-500 bg-sky-500/10' : 'border-slate-800 bg-slate-950/60 hover:border-slate-700 hover:bg-slate-950'"
                            @click="$emit('load-preset', preset)"
                        >
                            <div class="flex items-start justify-between gap-3">
                                <div>
                                    <div class="text-sm font-semibold text-white">{{ preset.name }}</div>
                                    <div class="mt-1 text-xs uppercase tracking-[0.16em] text-slate-500">{{ typeLabel(preset.template_type) }}</div>
                                </div>
                                <span class="rounded-full border border-slate-700 bg-slate-900 px-2.5 py-1 text-[11px] font-semibold text-slate-300">Basis</span>
                            </div>
                            <p class="mt-3 text-sm text-slate-400">{{ preset.description }}</p>
                        </button>
                    </div>
                </div>

                <div>
                    <div class="mb-3 text-xs font-semibold uppercase tracking-[0.18em] text-slate-500">Opgeslagen templates</div>
                    <div class="space-y-3">
                        <button
                            v-for="template in savedTemplates"
                            :key="template.id"
                            type="button"
                            class="w-full rounded-3xl border px-4 py-4 text-left transition"
                            :class="isCurrent(template) ? 'border-sky-500 bg-sky-500/10' : 'border-slate-800 bg-slate-950/60 hover:border-slate-700 hover:bg-slate-950'"
                            @click="$emit('load-saved-template', template)"
                        >
                            <div class="flex items-start justify-between gap-3">
                                <div>
                                    <div class="flex items-center gap-2">
                                        <div class="text-sm font-semibold text-white">{{ template.name }}</div>
                                        <span v-if="template.is_default" class="rounded-full border border-emerald-500/30 bg-emerald-500/10 px-2.5 py-1 text-[11px] font-semibold text-emerald-200">Standaard</span>
                                    </div>
                                    <div class="mt-1 text-xs uppercase tracking-[0.16em] text-slate-500">{{ typeLabel(template.template_type) }}</div>
                                </div>
                                <span class="rounded-full border border-slate-700 bg-slate-900 px-2.5 py-1 text-[11px] font-semibold text-slate-300">Opgeslagen</span>
                            </div>
                            <p class="mt-3 text-sm text-slate-400">{{ template.description || 'Geen beschrijving ingevuld.' }}</p>
                            <p class="mt-2 text-xs text-slate-500">Laatst aangepast: {{ formatDate(template.updated_at) }}</p>
                        </button>

                        <div v-if="!savedTemplates.length && !loading" class="rounded-3xl border border-dashed border-slate-800 px-4 py-8 text-center text-sm text-slate-400">
                            Nog geen templates opgeslagen voor dit type.
                        </div>
                    </div>
                </div>

                <div>
                    <div class="mb-3 text-xs font-semibold uppercase tracking-[0.18em] text-slate-500">Elementen</div>
                    <div class="grid grid-cols-2 gap-2">
                        <button
                            v-for="tool in elementTools"
                            :key="tool.type"
                            type="button"
                            class="group flex items-center gap-3 rounded-2xl border border-slate-800 bg-slate-950/70 px-3 py-3 text-left transition hover:border-slate-700 hover:bg-slate-900"
                            @click="$emit('add-element', tool.type)"
                        >
                            <div class="flex h-10 w-10 items-center justify-center rounded-2xl border border-slate-700 bg-slate-900 text-slate-200 transition group-hover:border-sky-500/40 group-hover:text-sky-300">
                                <component :is="tool.icon" class="h-5 w-5" />
                            </div>
                            <div class="min-w-0">
                                <div class="truncate text-sm font-semibold text-white">{{ tool.label }}</div>
                                <div class="text-xs uppercase tracking-[0.16em] text-slate-500">{{ tool.group === 'dynamic' ? 'Dynamisch' : 'Statisch' }}</div>
                            </div>
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </aside>
</template>

<script setup>
import { formatDate, typeLabel } from '../utils/badgeEditor'

const props = defineProps({
    filters: { type: Array, required: true },
    activeFilter: { type: String, required: true },
    presets: { type: Array, required: true },
    savedTemplates: { type: Array, required: true },
    loading: { type: Boolean, default: false },
    currentTemplateId: { type: [Number, String, null], default: null },
    currentSourceId: { type: [Number, String, null], default: null },
    elementTools: { type: Array, required: true },
})

defineEmits([
    'refresh',
    'update:activeFilter',
    'create-template',
    'load-preset',
    'load-saved-template',
    'add-element',
])

function isCurrent(template) {
    if (props.currentTemplateId && template.id) {
        return String(props.currentTemplateId) === String(template.id)
    }

    return String(props.currentSourceId) === String(template.id)
}
</script>
