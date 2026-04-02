<template>
    <ModalDialog
        :open="open"
        :title="isEditing ? 'Cateringoptie bewerken' : 'Nieuwe cateringoptie'"
        description="Maak een cateringoptie aan of pas een bestaande optie aan."
        @close="handleClose"
    >
        <form class="space-y-4" @submit.prevent="submitForm">
            <div
                v-if="error"
                class="rounded-2xl border border-red-800 bg-red-950/40 px-4 py-3 text-sm text-red-300"
            >
                {{ error }}
            </div>

            <div class="grid gap-4 md:grid-cols-2">
                <div>
                    <label class="mb-1 block text-sm font-medium text-slate-300">Naam</label>
                    <input
                        v-model="form.name"
                        type="text"
                        class="w-full rounded-xl border border-slate-700 bg-slate-950 px-4 py-3 text-white outline-none transition focus:border-blue-500"
                    />
                </div>

                <div>
                    <label class="mb-1 block text-sm font-medium text-slate-300">Code</label>
                    <input
                        v-model="form.code"
                        type="text"
                        class="w-full rounded-xl border border-slate-700 bg-slate-950 px-4 py-3 text-white outline-none transition focus:border-blue-500"
                    />
                </div>
            </div>

            <div class="grid gap-4 md:grid-cols-2">
                <div>
                    <label class="mb-1 block text-sm font-medium text-slate-300">Emoji</label>
                    <input
                        v-model="form.emoji"
                        type="text"
                        placeholder="Bijv. 🍕"
                        class="w-full rounded-xl border border-slate-700 bg-slate-950 px-4 py-3 text-white outline-none transition focus:border-blue-500"
                    />
                </div>

                <div class="flex items-end">
                    <label class="flex items-center gap-3 text-sm text-slate-300">
                        <input
                            v-model="form.is_active"
                            type="checkbox"
                            class="h-4 w-4 rounded border-slate-600 bg-slate-950"
                        />
                        Actief
                    </label>
                </div>
            </div>

            <div class="flex flex-wrap gap-3 pt-2">
                <button
                    type="submit"
                    :disabled="saving"
                    class="rounded-xl bg-blue-600 px-4 py-3 text-sm font-semibold text-white transition hover:bg-blue-500 disabled:opacity-60"
                >
                    {{ saving ? 'Opslaan...' : isEditing ? 'Cateringoptie opslaan' : 'Cateringoptie toevoegen' }}
                </button>

                <button
                    type="button"
                    @click="handleClose"
                    class="rounded-xl border border-slate-700 px-4 py-3 text-sm font-medium text-slate-300 transition hover:bg-slate-800"
                >
                    Annuleren
                </button>
            </div>
        </form>
    </ModalDialog>
</template>

<script setup>
import { computed, reactive, watch } from 'vue'
import ModalDialog from '../../../components/ModalDialog.vue'

const props = defineProps({
    open: {
        type: Boolean,
        default: false,
    },
    option: {
        type: Object,
        default: null,
    },
    saving: {
        type: Boolean,
        default: false,
    },
    error: {
        type: String,
        default: '',
    },
})

const emit = defineEmits(['close', 'submit'])

const form = reactive({
    name: '',
    code: '',
    emoji: '',
    is_active: true,
})

const isEditing = computed(() => !!props.option?.id)

watch(
    () => [props.open, props.option],
    ([open]) => {
        if (open) {
            fillFormFromProps()
        } else {
            resetForm()
        }
    },
    { immediate: true, deep: true },
)

function fillFormFromProps() {
    form.name = props.option?.name ?? ''
    form.code = props.option?.code ?? ''
    form.emoji = props.option?.emoji ?? ''
    form.is_active = props.option?.is_active ?? true
}

function resetForm() {
    form.name = ''
    form.code = ''
    form.emoji = ''
    form.is_active = true
}

function submitForm() {
    emit('submit', {
        name: form.name,
        code: form.code,
        emoji: form.emoji,
        is_active: form.is_active,
    })
}

function handleClose() {
    emit('close')
}
</script>
