<template>
    <div class="mx-auto max-w-3xl space-y-6">
        <div>
            <h1 class="text-3xl font-bold text-white">Activiteiten</h1>
            <p class="mt-2 text-slate-400">Wat kunnen bezoekers bij jou doen? Sleep om te herordenen.</p>
        </div>

        <div v-if="loading" class="text-slate-400">Laden...</div>

        <template v-else>
            <Section title="Activiteiten">
                <div v-if="!activities.length" class="rounded-xl border border-dashed border-slate-700 bg-slate-900/40 p-8 text-center text-sm text-slate-500">
                    Nog geen activiteiten toegevoegd.
                </div>

                <div v-else class="space-y-2">
                    <div
                        v-for="(activity, index) in activities"
                        :key="activity.id"
                        :draggable="true"
                        @dragstart="onDragStart(index)"
                        @dragover.prevent
                        @drop.prevent="onDrop(index)"
                        class="flex items-center gap-4 rounded-xl border border-slate-700 bg-slate-900/60 p-4"
                    >
                        <div class="cursor-move text-slate-500">⠿</div>
                        <div class="flex-1">
                            <div class="font-medium text-white">{{ activity.name }}</div>
                            <div v-if="activity.description" class="mt-0.5 text-sm text-slate-400">
                                {{ activity.description }}
                            </div>
                        </div>
                        <span
                            v-if="!activity.is_visible"
                            class="rounded-full bg-amber-500/10 px-2 py-0.5 text-xs text-amber-300"
                        >Verborgen</span>
                        <button
                            type="button"
                            class="text-sm text-cyan-400 hover:text-cyan-300"
                            @click="openEdit(activity)"
                        >Bewerken</button>
                        <button
                            type="button"
                            class="text-sm text-rose-400 hover:text-rose-300"
                            @click="handleDelete(activity)"
                        >Verwijder</button>
                    </div>
                </div>

                <div class="mt-3">
                    <button
                        type="button"
                        class="rounded-xl border border-slate-700 bg-slate-900/60 px-4 py-2.5 text-sm font-medium text-slate-200 transition hover:border-cyan-500 hover:text-cyan-300"
                        @click="openCreate"
                    >Activiteit toevoegen</button>
                </div>
            </Section>

            <div v-if="error" class="rounded-xl border border-rose-500/30 bg-rose-500/10 px-4 py-3 text-sm text-rose-200">
                {{ error }}
            </div>
        </template>

        <!-- Modal -->
        <div
            v-if="modalOpen"
            class="fixed inset-0 z-50 flex items-center justify-center bg-slate-950/80 p-4"
            @click.self="closeModal"
        >
            <div class="w-full max-w-md rounded-2xl border border-slate-800 bg-slate-900 p-6">
                <h2 class="mb-4 text-lg font-semibold text-white">
                    {{ editing ? 'Activiteit bewerken' : 'Activiteit toevoegen' }}
                </h2>

                <div class="space-y-4">
                    <Field label="Naam" required>
                        <input v-model="modal.name" type="text" maxlength="120" class="input" required />
                    </Field>
                    <Field label="Beschrijving" hint="Optioneel, max 500 tekens.">
                        <textarea v-model="modal.description" rows="3" maxlength="500" class="input" />
                    </Field>
                    <Field label="Icoon-key" hint="Optioneel — laat leeg, we voegen iconen later toe.">
                        <input v-model="modal.icon_key" type="text" placeholder="bv. lucide:gamepad-2" class="input" />
                    </Field>
                    <label class="flex items-center gap-2 text-sm text-slate-300">
                        <input v-model="modal.is_visible" type="checkbox" class="h-4 w-4 rounded border-slate-600 bg-slate-800 text-cyan-500" />
                        Tonen op publieke pagina
                    </label>
                </div>

                <div class="mt-6 flex justify-end gap-2">
                    <button
                        type="button"
                        class="rounded-xl border border-slate-700 px-4 py-2.5 text-sm font-medium text-slate-300 transition hover:bg-slate-800"
                        @click="closeModal"
                    >Annuleren</button>
                    <button
                        type="button"
                        :disabled="modalSaving"
                        class="rounded-xl bg-cyan-600 px-5 py-2.5 text-sm font-semibold text-white transition hover:bg-cyan-500 disabled:opacity-60"
                        @click="saveModal"
                    >{{ modalSaving ? 'Bezig...' : 'Opslaan' }}</button>
                </div>
            </div>
        </div>
    </div>
</template>

<script setup>
import { onMounted, ref } from 'vue'
import Section from '../components/Section.vue'
import Field from '../components/Field.vue'
import {
    getActivities,
    createActivity,
    updateActivity,
    deleteActivity,
    reorderActivities,
} from '../services/venueApi'

const loading = ref(true)
const activities = ref([])
const error = ref('')
const dragSourceIndex = ref(null)

const modalOpen = ref(false)
const editing = ref(null)
const modalSaving = ref(false)
const modal = ref({
    name: '',
    description: '',
    icon_key: '',
    is_visible: true,
})

onMounted(refresh)

async function refresh() {
    loading.value = true
    try {
        const data = await getActivities()
        activities.value = data.activities ?? []
    } finally {
        loading.value = false
    }
}

function openCreate() {
    editing.value = null
    modal.value = { name: '', description: '', icon_key: '', is_visible: true }
    modalOpen.value = true
}

function openEdit(activity) {
    editing.value = activity
    modal.value = {
        name: activity.name,
        description: activity.description ?? '',
        icon_key: activity.icon_key ?? '',
        is_visible: activity.is_visible,
    }
    modalOpen.value = true
}

function closeModal() {
    modalOpen.value = false
}

async function saveModal() {
    modalSaving.value = true
    try {
        const payload = {
            name: modal.value.name,
            description: modal.value.description || null,
            icon_key: modal.value.icon_key || null,
            is_visible: modal.value.is_visible,
        }

        if (editing.value) {
            const updated = await updateActivity(editing.value.id, payload)
            const idx = activities.value.findIndex(a => a.id === updated.id)
            if (idx !== -1) activities.value[idx] = updated
        } else {
            const created = await createActivity(payload)
            activities.value.push(created)
        }

        modalOpen.value = false
    } catch (err) {
        error.value = err?.data?.message || 'Opslaan mislukt.'
    } finally {
        modalSaving.value = false
    }
}

async function handleDelete(activity) {
    if (!confirm(`Activiteit "${activity.name}" verwijderen?`)) return
    try {
        await deleteActivity(activity.id)
        activities.value = activities.value.filter(a => a.id !== activity.id)
    } catch (err) {
        error.value = err?.data?.message || 'Verwijderen mislukt.'
    }
}

function onDragStart(index) {
    dragSourceIndex.value = index
}

async function onDrop(targetIndex) {
    const source = dragSourceIndex.value
    dragSourceIndex.value = null
    if (source === null || source === targetIndex) return

    const newOrder = [...activities.value]
    const [moved] = newOrder.splice(source, 1)
    newOrder.splice(targetIndex, 0, moved)
    activities.value = newOrder

    try {
        await reorderActivities(newOrder.map(a => a.id))
    } catch (err) {
        error.value = err?.data?.message || 'Volgorde opslaan mislukt.'
    }
}
</script>

<style scoped>
.input {
    width: 100%;
    border-radius: 0.75rem;
    border: 1px solid rgb(51 65 85);
    background: rgb(2 6 23);
    padding: 0.6rem 0.9rem;
    color: white;
    outline: none;
    transition: border-color 0.15s;
}
.input:focus { border-color: rgb(6 182 212); }
</style>
