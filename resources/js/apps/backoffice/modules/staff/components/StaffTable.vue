<template>
    <div class="space-y-6">
        <div class="flex gap-3">
            <button
                type="button"
                @click="$emit('refresh')"
                :disabled="loading"
                class="rounded-xl border border-slate-700 px-4 py-3 text-sm font-medium text-slate-300 transition hover:bg-slate-800 disabled:opacity-60"
            >
                Vernieuwen
            </button>

            <button
                type="button"
                @click="$emit('create')"
                class="rounded-xl bg-blue-600 px-4 py-3 text-sm font-semibold text-white transition hover:bg-blue-500"
            >
                Nieuwe medewerker
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

            <div v-else-if="staff.length === 0" class="p-6 text-sm text-slate-400">
                Nog geen medewerkers gevonden.
            </div>

            <div v-else class="overflow-x-auto">
                <table class="min-w-full text-sm">
                    <thead class="bg-slate-950 text-slate-300">
                    <tr>
                        <th class="px-4 py-3 text-left font-semibold">Volgorde</th>
                        <th class="px-4 py-3 text-left font-semibold">Naam</th>
                        <th class="px-4 py-3 text-left font-semibold">Login</th>
                        <th class="px-4 py-3 text-left font-semibold">E-mail</th>
                        <th class="px-4 py-3 text-left font-semibold">RFID</th>
                        <th class="px-4 py-3 text-left font-semibold">Adres</th>
                        <th class="px-4 py-3 text-left font-semibold">Rol</th>
                        <th class="px-4 py-3 text-left font-semibold">Status</th>
                        <th class="px-4 py-3 text-right font-semibold">Acties</th>
                    </tr>
                    </thead>

                    <tbody>
                    <tr
                        v-for="member in localStaff"
                        :key="member.id"
                        draggable="true"
                        class="border-t border-slate-800 bg-slate-900"
                        :class="draggingId === member.id ? 'opacity-40' : ''"
                        @dragstart="onDragStart(member.id)"
                        @dragover.prevent
                        @drop="onDrop(member.id)"
                        @dragend="onDragEnd"
                    >
                        <td class="px-4 py-3 text-slate-400">
                            ↕ {{ member.sort_order }}
                        </td>

                        <td class="px-4 py-3 text-white">
                            {{ member.name }}
                        </td>

                        <td class="px-4 py-3 text-slate-400">
                            {{ member.username }}
                        </td>

                        <td class="px-4 py-3 text-slate-400">
                            {{ member.email || '—' }}
                        </td>

                        <td class="px-4 py-3 text-slate-400">
                            {{ member.rfid_uid || '—' }}
                        </td>

                        <td class="px-4 py-3 text-slate-400">
                            {{ member.full_address || '—' }}
                        </td>

                        <td class="px-4 py-3">
                            <span class="inline-flex rounded-full px-2.5 py-1 text-xs font-semibold" :class="member.is_admin ? 'bg-cyan-500/15 text-cyan-300' : 'bg-slate-700 text-slate-300'">{{ member.is_admin ? 'Admin' : 'Medewerker' }}</span>
                        </td>

                        <td class="px-4 py-3">
                                <span
                                    class="inline-flex rounded-full px-2.5 py-1 text-xs font-semibold"
                                    :class="member.is_active ? 'bg-emerald-500/15 text-emerald-300' : 'bg-slate-700 text-slate-300'"
                                >
                                    {{ member.is_active ? 'Actief' : 'Inactief' }}
                                </span>
                        </td>

                        <td class="px-4 py-3">
                            <div class="flex justify-end gap-2">
                                <button
                                    type="button"
                                    @click="$emit('edit', member)"
                                    class="rounded-lg border border-slate-700 px-3 py-2 text-xs font-medium text-slate-300 transition hover:bg-slate-800"
                                >
                                    Bewerken
                                </button>

                                <button
                                    type="button"
                                    @click="$emit('delete', member)"
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
    staff: {
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

const emit = defineEmits(['refresh', 'create', 'edit', 'delete', 'reorder'])

const localStaff = ref([])
const draggingId = ref(null)

watch(
    () => props.staff,
    (value) => {
        localStaff.value = Array.isArray(value) ? [...value] : []
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

    const fromIndex = localStaff.value.findIndex((item) => item.id === draggingId.value)
    const toIndex = localStaff.value.findIndex((item) => item.id === targetId)

    if (fromIndex === -1 || toIndex === -1) {
        draggingId.value = null
        return
    }

    const reordered = [...localStaff.value]
    const [movedItem] = reordered.splice(fromIndex, 1)
    reordered.splice(toIndex, 0, movedItem)

    localStaff.value = reordered.map((item, index) => ({
        ...item,
        sort_order: index + 1,
    }))

    draggingId.value = null
    emit('reorder', localStaff.value)
}
</script>
