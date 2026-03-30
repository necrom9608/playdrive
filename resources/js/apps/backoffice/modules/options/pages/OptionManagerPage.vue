<template>
    <div class="space-y-6">
        <div class="flex flex-wrap items-start justify-between gap-4">
            <div>
                <h1 class="text-3xl font-bold text-white">{{ title }}</h1>
                <p class="mt-2 text-slate-400">{{ description }}</p>
            </div>
            <div class="flex gap-3">
                <button type="button" @click="loadItems" :disabled="loading" class="rounded-xl border border-slate-700 px-4 py-3 text-sm font-medium text-slate-300 transition hover:bg-slate-800 disabled:opacity-60">Vernieuwen</button>
                <button type="button" @click="openCreateModal" class="rounded-xl bg-blue-600 px-4 py-3 text-sm font-semibold text-white transition hover:bg-blue-500">Nieuw item</button>
            </div>
        </div>

        <div v-if="error" class="rounded-2xl border border-red-800 bg-red-950/40 px-4 py-3 text-sm text-red-300">{{ error }}</div>

        <div class="rounded-2xl border border-slate-800 bg-slate-900 shadow-sm">
            <div v-if="loading" class="p-6 text-sm text-slate-400">Laden...</div>
            <div v-else-if="items.length === 0" class="p-6 text-sm text-slate-400">Nog geen items gevonden.</div>
            <div v-else class="overflow-x-auto">
                <table class="min-w-full text-sm">
                    <thead class="bg-slate-950 text-slate-300">
                    <tr>
                        <th class="px-4 py-3 text-left font-semibold">Volgorde</th>
                        <th class="px-4 py-3 text-left font-semibold">Naam</th>
                        <th class="px-4 py-3 text-left font-semibold">Code</th>
                        <th v-if="hasEmoji" class="px-4 py-3 text-left font-semibold">Emoji</th>
                        <th v-if="hasDuration" class="px-4 py-3 text-left font-semibold">Duur</th>
                        <th class="px-4 py-3 text-left font-semibold">Status</th>
                        <th class="px-4 py-3 text-right font-semibold">Acties</th>
                    </tr>
                    </thead>
                    <tbody>
                    <tr
                        v-for="item in items"
                        :key="item.id"
                        draggable="true"
                        class="border-t border-slate-800 bg-slate-900"
                        :class="draggingId === item.id ? 'opacity-40' : ''"
                        @dragstart="onDragStart(item.id)"
                        @dragover.prevent
                        @drop="onDrop(item.id)"
                    >
                        <td class="px-4 py-3 text-slate-400">↕ {{ item.sort_order }}</td>
                        <td class="px-4 py-3 text-white">{{ item.name }}</td>
                        <td class="px-4 py-3 text-slate-400">{{ item.code }}</td>
                        <td v-if="hasEmoji" class="px-4 py-3 text-xl">{{ item.emoji || '—' }}</td>
                        <td v-if="hasDuration" class="px-4 py-3 text-slate-400">{{ item.duration_minutes ? `${item.duration_minutes} min` : '—' }}</td>
                        <td class="px-4 py-3"><span class="inline-flex rounded-full px-2.5 py-1 text-xs font-semibold" :class="item.is_active ? 'bg-emerald-500/15 text-emerald-300' : 'bg-slate-700 text-slate-300'">{{ item.is_active ? 'Actief' : 'Inactief' }}</span></td>
                        <td class="px-4 py-3">
                            <div class="flex justify-end gap-2">
                                <button type="button" @click="openEditModal(item)" class="rounded-lg border border-slate-700 px-3 py-2 text-xs font-medium text-slate-300 transition hover:bg-slate-800">Bewerken</button>
                                <button type="button" @click="removeItem(item)" class="rounded-lg border border-red-800 px-3 py-2 text-xs font-medium text-red-300 transition hover:bg-red-950/40">Verwijderen</button>
                            </div>
                        </td>
                    </tr>
                    </tbody>
                </table>
            </div>
        </div>

        <ModalDialog :open="modalOpen" :title="editingId ? 'Item bewerken' : 'Nieuw item'" :description="modalDescription" @close="closeModal">
            <form class="space-y-4" @submit.prevent="submit">
                <div>
                    <label class="mb-1 block text-sm font-medium text-slate-300">Naam</label>
                    <input v-model="form.name" type="text" class="w-full rounded-xl border border-slate-700 bg-slate-950 px-4 py-3 text-white outline-none transition focus:border-blue-500" />
                </div>
                <div>
                    <label class="mb-1 block text-sm font-medium text-slate-300">Code</label>
                    <input v-model="form.code" type="text" class="w-full rounded-xl border border-slate-700 bg-slate-950 px-4 py-3 text-white outline-none transition focus:border-blue-500" placeholder="Laat leeg om automatisch te genereren" />
                </div>
                <div v-if="hasEmoji">
                    <label class="mb-1 block text-sm font-medium text-slate-300">Emoji</label>
                    <input v-model="form.emoji" type="text" class="w-full rounded-xl border border-slate-700 bg-slate-950 px-4 py-3 text-white outline-none transition focus:border-blue-500" placeholder="Bijvoorbeeld: 🎮" />
                </div>
                <div v-if="hasDuration">
                    <label class="mb-1 block text-sm font-medium text-slate-300">Duur in minuten</label>
                    <input v-model.number="form.duration_minutes" type="number" min="0" class="w-full rounded-xl border border-slate-700 bg-slate-950 px-4 py-3 text-white outline-none transition focus:border-blue-500" />
                </div>
                <label class="flex items-center gap-3 text-sm text-slate-300">
                    <input v-model="form.is_active" type="checkbox" class="h-4 w-4 rounded border-slate-600 bg-slate-950" />
                    Actief
                </label>
                <div class="flex flex-wrap gap-3 pt-2">
                    <button type="submit" :disabled="saving" class="rounded-xl bg-blue-600 px-4 py-3 text-sm font-semibold text-white transition hover:bg-blue-500 disabled:opacity-60">{{ saving ? 'Opslaan...' : editingId ? 'Item opslaan' : 'Item toevoegen' }}</button>
                    <button type="button" @click="closeModal" class="rounded-xl border border-slate-700 px-4 py-3 text-sm font-medium text-slate-300 transition hover:bg-slate-800">Annuleren</button>
                </div>
            </form>
        </ModalDialog>
    </div>
</template>

<script setup>
import { computed, onMounted, reactive, ref } from 'vue'
import ModalDialog from '../../../components/ModalDialog.vue'
import { createOption, deleteOption, fetchOptions, reorderOptions, updateOption } from '../services/optionApi'

const props = defineProps({
    type: { type: String, required: true },
    title: { type: String, required: true },
    description: { type: String, required: true },
    hasEmoji: { type: Boolean, default: true },
    hasDuration: { type: Boolean, default: false },
})

const items = ref([])
const loading = ref(false)
const saving = ref(false)
const error = ref('')
const editingId = ref(null)
const modalOpen = ref(false)
const draggingId = ref(null)

const form = reactive({ name: '', code: '', emoji: '', duration_minutes: 0, is_active: true })
const modalDescription = computed(() => `Beheer ${props.title.toLowerCase()} via een modal.`)

function resetForm() {
    editingId.value = null
    form.name = ''
    form.code = ''
    form.emoji = ''
    form.duration_minutes = 0
    form.is_active = true
}

function openCreateModal() {
    resetForm()
    modalOpen.value = true
}

function openEditModal(item) {
    editingId.value = item.id
    form.name = item.name
    form.code = item.code
    form.emoji = item.emoji ?? ''
    form.duration_minutes = item.duration_minutes ?? 0
    form.is_active = item.is_active
    modalOpen.value = true
}

function closeModal() {
    modalOpen.value = false
    resetForm()
}

async function loadItems() {
    loading.value = true
    error.value = ''
    try {
        items.value = await fetchOptions(props.type)
    } catch (err) {
        error.value = err?.data?.message ?? 'Kon gegevens niet laden.'
        console.error(err)
    } finally {
        loading.value = false
    }
}

async function submit() {
    saving.value = true
    error.value = ''
    try {
        const payload = {
            name: form.name,
            code: form.code,
            emoji: props.hasEmoji ? form.emoji : null,
            duration_minutes: props.hasDuration ? form.duration_minutes : null,
            is_active: form.is_active,
        }
        if (editingId.value) {
            await updateOption(props.type, editingId.value, payload)
        } else {
            await createOption(props.type, payload)
        }
        closeModal()
        await loadItems()
    } catch (err) {
        error.value = err?.data?.message ?? 'Kon item niet opslaan.'
        console.error(err)
    } finally {
        saving.value = false
    }
}

async function removeItem(item) {
    if (!window.confirm(`Item "${item.name}" verwijderen?`)) return
    try {
        await deleteOption(props.type, item.id)
        await loadItems()
    } catch (err) {
        error.value = err?.data?.message ?? 'Kon item niet verwijderen.'
        console.error(err)
    }
}

function onDragStart(id) {
    draggingId.value = id
}

async function onDrop(targetId) {
    if (!draggingId.value || draggingId.value === targetId) return
    const fromIndex = items.value.findIndex((item) => item.id === draggingId.value)
    const toIndex = items.value.findIndex((item) => item.id === targetId)
    const reordered = [...items.value]
    const [movedItem] = reordered.splice(fromIndex, 1)
    reordered.splice(toIndex, 0, movedItem)
    items.value = reordered.map((item, index) => ({ ...item, sort_order: index + 1 }))
    draggingId.value = null
    try {
        await reorderOptions(props.type, items.value.map((item) => ({ id: item.id })))
        await loadItems()
    } catch (err) {
        error.value = err?.data?.message ?? 'Kon volgorde niet opslaan.'
        console.error(err)
    }
}

onMounted(loadItems)
</script>
