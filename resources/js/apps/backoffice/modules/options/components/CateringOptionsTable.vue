<template>
    <div class="space-y-6">
        <div class="flex gap-3">
            <button
                type="button"
                @click="emit('refresh')"
                :disabled="loading"
                class="rounded-xl border border-slate-700 px-4 py-3 text-sm font-medium text-slate-300 transition hover:bg-slate-800 disabled:opacity-60"
            >
                Vernieuwen
            </button>

            <button
                type="button"
                @click="emit('create')"
                class="rounded-xl bg-blue-600 px-4 py-3 text-sm font-semibold text-white transition hover:bg-blue-500"
            >
                Nieuwe cateringoptie
            </button>
        </div>

        <div
            v-if="error"
            class="rounded-2xl border border-red-800 bg-red-950/40 px-4 py-3 text-sm text-red-300"
        >
            {{ error }}
        </div>

        <div class="rounded-2xl border border-slate-800 bg-slate-900 shadow-sm">
            <div v-if="loading" class="p-6 text-sm text-slate-400">
                Laden...
            </div>

            <div v-else-if="options.length === 0" class="p-6 text-sm text-slate-400">
                Nog geen cateringopties gevonden.
            </div>

            <div v-else class="overflow-x-auto">
                <table class="min-w-full text-sm">
                    <thead class="bg-slate-950 text-slate-300">
                    <tr>
                        <th class="px-4 py-3 text-left font-semibold">Volgorde</th>
                        <th class="px-4 py-3 text-left font-semibold">Naam</th>
                        <th class="px-4 py-3 text-left font-semibold">Slug</th>
                        <th class="px-4 py-3 text-left font-semibold">Icoon</th>
                        <th class="px-4 py-3 text-left font-semibold">Status</th>
                        <th class="px-4 py-3 text-right font-semibold">Acties</th>
                    </tr>
                    </thead>

                    <tbody>
                    <tr
                        v-for="option in localOptions"
                        :key="option.id"
                        draggable="true"
                        class="border-t border-slate-800 bg-slate-900"
                        :class="draggingId === option.id ? 'opacity-40' : ''"
                        @dragstart="onDragStart(option.id)"
                        @dragover.prevent
                        @drop="onDrop(option.id)"
                        @dragend="onDragEnd"
                    >
                        <td class="px-4 py-3 text-slate-400">
                            ↕ {{ option.sort_order }}
                        </td>

                        <td class="px-4 py-3 text-white">
                            {{ option.name }}
                        </td>

                        <td class="px-4 py-3 text-slate-400">
                            {{ option.slug }}
                        </td>

                        <td class="px-4 py-3 text-slate-400">
                            {{ option.icon || '—' }}
                        </td>

                        <td class="px-4 py-3">
                                <span
                                    class="inline-flex rounded-full px-2.5 py-1 text-xs font-semibold"
                                    :class="option.is_active ? 'bg-emerald-500/15 text-emerald-300' : 'bg-slate-700 text-slate-300'"
                                >
                                    {{ option.is_active ? 'Actief' : 'Inactief' }}
                                </span>
                        </td>

                        <td class="px-4 py-3">
                            <div class="flex justify-end gap-2">
                                <button
                                    type="button"
                                    @click="emit('manage-products', option)"
                                    class="rounded-lg border border-slate-700 px-3 py-2 text-xs font-medium text-slate-300 transition hover:bg-slate-800"
                                >
                                    Producten
                                </button>

                                <button
                                    type="button"
                                    @click="emit('edit', option)"
                                    class="rounded-lg border border-slate-700 px-3 py-2 text-xs font-medium text-slate-300 transition hover:bg-slate-800"
                                >
                                    Bewerken
                                </button>

                                <button
                                    type="button"
                                    @click="emit('delete', option)"
                                    class="rounded-lg border border-red-800 px-3 py-2 text-xs font-medium text-red-300 transition hover:bg-red-950/40"
                                >
                                    Verwijderen
                                </button>
                            </div>
                        </td>
                    </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</template>

<script setup>
import { ref, watch } from 'vue'

const props = defineProps({
    options: {
        type: Array,
        default: () => [],
    },
    loading: {
        type: Boolean,
        default: false,
    },
    error: {
        type: String,
        default: '',
    },
})

const emit = defineEmits(['refresh', 'create', 'edit', 'delete', 'manage-products', 'reorder'])

const localOptions = ref([])
const draggingId = ref(null)

watch(
    () => props.options,
    (value) => {
        localOptions.value = Array.isArray(value) ? [...value] : []
    },
    { immediate: true, deep: true },
)

function onDragStart(id) {
    draggingId.value = id
}

function onDragEnd() {
    draggingId.value = null
}

function onDrop(targetId) {
    if (!draggingId.value || draggingId.value === targetId) {
        draggingId.value = null
        return
    }

    const fromIndex = localOptions.value.findIndex((item) => item.id === draggingId.value)
    const toIndex = localOptions.value.findIndex((item) => item.id === targetId)

    if (fromIndex === -1 || toIndex === -1) {
        draggingId.value = null
        return
    }

    const reordered = [...localOptions.value]
    const [movedItem] = reordered.splice(fromIndex, 1)
    reordered.splice(toIndex, 0, movedItem)

    localOptions.value = reordered.map((item, index) => ({
        ...item,
        sort_order: index + 1,
    }))

    draggingId.value = null
    emit('reorder', localOptions.value)
}
</script>
