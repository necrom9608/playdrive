<template>
    <div class="flex flex-col gap-4">
        <section class="rounded-3xl border border-slate-800 bg-slate-900/90 p-5 shadow-xl">
            <h2 class="text-xl font-semibold text-white">Kennisbank</h2>
            <p class="mt-1 text-sm text-slate-400">
                Vaste antwoorden die niet in de andere modules staan (taart meebrengen, parking, minimumleeftijd VR, …).
                De assistent gebruikt ze letterlijk als bron.
            </p>

            <form class="mt-4 grid gap-3 lg:grid-cols-[1fr_1.4fr_auto] lg:items-end" @submit.prevent="add">
                <label class="flex flex-col gap-1.5 text-sm text-slate-300">
                    Vraag
                    <input v-model="newItem.question" type="text" class="field" placeholder="Mag ik zelf een taart meebrengen?" required>
                </label>
                <label class="flex flex-col gap-1.5 text-sm text-slate-300">
                    Antwoord
                    <textarea v-model="newItem.answer" rows="2" class="field resize-y" placeholder="Ja, taart meebrengen mag; borden en bestek zijn voorzien." required></textarea>
                </label>
                <button
                    type="submit"
                    class="rounded-2xl bg-[linear-gradient(180deg,#3483ff_0%,#255df4_100%)] px-5 py-3 text-sm font-semibold text-white shadow-[0_14px_28px_rgba(37,93,244,0.28)] disabled:opacity-50"
                    :disabled="busy || !newItem.question.trim() || !newItem.answer.trim()"
                >
                    Toevoegen
                </button>
            </form>
        </section>

        <div v-if="error" class="rounded-2xl border border-rose-500/30 bg-rose-500/10 px-5 py-3 text-sm text-rose-200">{{ error }}</div>

        <div v-if="loading" class="rounded-3xl border border-slate-800 bg-slate-900/90 p-10 text-center text-sm text-slate-400">Laden…</div>
        <div v-else-if="!items.length" class="rounded-3xl border border-slate-800 bg-slate-900/90 p-10 text-center text-sm text-slate-400">
            Nog geen vragen in de kennisbank.
        </div>

        <ul v-else class="flex flex-col gap-2">
            <li v-for="item in items" :key="item.id" class="rounded-3xl border border-slate-800 bg-slate-900/90 p-4">
                <template v-if="editingId === item.id">
                    <div class="grid gap-3 lg:grid-cols-[1fr_1.4fr]">
                        <input v-model="edit.question" type="text" class="field" aria-label="Vraag">
                        <textarea v-model="edit.answer" rows="3" class="field resize-y" aria-label="Antwoord"></textarea>
                    </div>
                    <div class="mt-3 flex flex-wrap gap-2">
                        <button type="button" class="rounded-2xl border border-blue-500 bg-blue-600 px-4 py-2.5 text-sm font-semibold text-white disabled:opacity-50" :disabled="busy" @click="saveEdit(item)">
                            Bewaren
                        </button>
                        <button type="button" class="rounded-2xl px-4 py-2.5 text-sm font-semibold text-slate-300 hover:text-white" @click="editingId = null">Annuleer</button>
                    </div>
                </template>

                <div v-else class="flex flex-wrap items-start gap-4">
                    <div class="min-w-0 flex-1" :class="item.is_active ? '' : 'opacity-50'">
                        <p class="font-semibold text-white">{{ item.question }}</p>
                        <p class="mt-1 whitespace-pre-line text-sm text-slate-300">{{ item.answer }}</p>
                    </div>
                    <div class="flex items-center gap-2">
                        <ToggleSwitch :model-value="item.is_active" :aria-label="`${item.question} actief`" @update:model-value="toggle(item, $event)" />
                        <button type="button" class="rounded-xl p-2 text-slate-400 hover:bg-slate-800 hover:text-white" aria-label="Bewerken" @click="startEdit(item)">
                            <PencilSquareIcon class="h-5 w-5" />
                        </button>
                        <button type="button" class="rounded-xl p-2 text-slate-400 hover:bg-rose-500/15 hover:text-rose-300" aria-label="Verwijderen" @click="remove(item)">
                            <TrashIcon class="h-5 w-5" />
                        </button>
                    </div>
                </div>
            </li>
        </ul>
    </div>
</template>

<script setup>
import { onMounted, reactive, ref } from 'vue'
import { PencilSquareIcon, TrashIcon } from '@heroicons/vue/24/outline'
import ToggleSwitch from '../../../components/ToggleSwitch.vue'
import { createFaq, deleteFaq, errorMessage, fetchFaqs, updateFaq } from '../services/mailAssistantApi'

const items = ref([])
const loading = ref(true)
const busy = ref(false)
const error = ref('')
const editingId = ref(null)
const newItem = reactive({ question: '', answer: '' })
const edit = reactive({ question: '', answer: '' })

async function run(fn, fallback) {
    busy.value = true
    error.value = ''
    try {
        const res = await fn()
        items.value = res.data
        return true
    } catch (e) {
        error.value = errorMessage(e, fallback)
        return false
    } finally {
        busy.value = false
    }
}

async function load() {
    loading.value = true
    try {
        items.value = await fetchFaqs()
    } catch (e) {
        error.value = errorMessage(e, 'Kennisbank laden mislukt.')
    } finally {
        loading.value = false
    }
}

async function add() {
    const ok = await run(() => createFaq({ question: newItem.question, answer: newItem.answer, is_active: true }), 'Toevoegen mislukt.')
    if (ok) {
        newItem.question = ''
        newItem.answer = ''
    }
}

function startEdit(item) {
    editingId.value = item.id
    edit.question = item.question
    edit.answer = item.answer
}

async function saveEdit(item) {
    const ok = await run(() => updateFaq(item.id, { question: edit.question, answer: edit.answer, is_active: item.is_active }), 'Bewaren mislukt.')
    if (ok) editingId.value = null
}

function toggle(item, value) {
    run(() => updateFaq(item.id, { question: item.question, answer: item.answer, is_active: value }), 'Wijzigen mislukt.')
}

function remove(item) {
    if (!window.confirm(`"${item.question}" verwijderen?`)) return
    run(() => deleteFaq(item.id), 'Verwijderen mislukt.')
}

onMounted(load)
</script>

<style scoped>
.field {
    width: 100%;
    border-radius: 1rem;
    border: 1px solid rgba(255, 255, 255, 0.1);
    background: rgba(2, 6, 23, 0.55);
    padding: 0.75rem 1rem;
    color: #fff;
    outline: none;
    transition: border-color 0.2s ease, background-color 0.2s ease;
}

.field::placeholder {
    color: rgba(148, 163, 184, 0.7);
}

.field:focus {
    border-color: rgba(96, 165, 250, 0.65);
    background: rgba(2, 6, 23, 0.72);
}
</style>
