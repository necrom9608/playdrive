<template>
    <div
        v-if="open"
        class="fixed inset-0 z-[100] flex items-center justify-center bg-black/70 p-6 backdrop-blur-sm"
        @click.self="$emit('close')"
    >
        <div class="w-full max-w-2xl rounded-3xl border border-slate-800 bg-slate-900 shadow-2xl">
            <div class="flex items-center justify-between border-b border-slate-800 px-6 py-4">
                <div>
                    <h3 class="text-xl font-semibold text-white">Taak toevoegen</h3>
                    <p class="mt-1 text-sm text-slate-400">Snelle taak voor vandaag</p>
                </div>
                <button type="button" class="rounded-xl border border-slate-700 bg-slate-800 px-3 py-2 text-sm text-slate-200" @click="$emit('close')">
                    Sluiten
                </button>
            </div>

            <form class="space-y-5 p-6" @submit.prevent="$emit('submit')">
                <label class="block space-y-2 text-sm text-slate-300">
                    <span>Titel</span>
                    <input
                        :value="form.title"
                        type="text"
                        class="w-full rounded-2xl border border-slate-700 bg-slate-950 px-4 py-3 text-white outline-none focus:border-amber-500"
                        @input="updateField('title', $event.target.value)"
                    >
                </label>

                <label class="block space-y-2 text-sm text-slate-300">
                    <span>Omschrijving</span>
                    <textarea
                        :value="form.description"
                        rows="4"
                        class="w-full rounded-2xl border border-slate-700 bg-slate-950 px-4 py-3 text-white outline-none focus:border-amber-500"
                        @input="updateField('description', $event.target.value)"
                    ></textarea>
                </label>

                <div class="grid gap-4 md:grid-cols-2">
                    <label class="block space-y-2 text-sm text-slate-300">
                        <span>Dag</span>
                        <input
                            :value="form.due_date"
                            type="date"
                            class="w-full rounded-2xl border border-slate-700 bg-slate-950 px-4 py-3 text-white outline-none focus:border-amber-500"
                            @input="updateField('due_date', $event.target.value)"
                        >
                    </label>

                    <label class="block space-y-2 text-sm text-slate-300">
                        <span>Toegewezen aan</span>
                        <select
                            :value="form.assigned_user_id"
                            class="w-full rounded-2xl border border-slate-700 bg-slate-950 px-4 py-3 text-white outline-none focus:border-amber-500"
                            @change="updateAssignedUser($event.target.value)"
                        >
                            <option :value="null">Algemeen</option>
                            <option v-for="member in staff" :key="member.id" :value="member.id">{{ member.name }}</option>
                        </select>
                    </label>
                </div>

                <div v-if="error" class="rounded-2xl border border-rose-500/30 bg-rose-500/10 px-4 py-3 text-sm text-rose-200">
                    {{ error }}
                </div>

                <div class="flex justify-end gap-3">
                    <button type="button" class="rounded-2xl border border-slate-700 bg-slate-800 px-4 py-3 text-sm font-semibold text-slate-200" @click="$emit('close')">
                        Annuleren
                    </button>
                    <button type="submit" class="rounded-2xl bg-amber-500 px-5 py-3 text-sm font-semibold text-slate-950 hover:bg-amber-400" :disabled="processing">
                        {{ processing ? 'Opslaan...' : 'Taak opslaan' }}
                    </button>
                </div>
            </form>
        </div>
    </div>
</template>

<script setup>
const props = defineProps({
    open: {
        type: Boolean,
        required: true,
    },
    form: {
        type: Object,
        required: true,
    },
    staff: {
        type: Array,
        required: true,
    },
    processing: {
        type: Boolean,
        required: true,
    },
    error: {
        type: String,
        default: '',
    },
})

const emit = defineEmits(['close', 'submit', 'update:form'])

function updateField(field, value) {
    emit('update:form', {
        ...props.form,
        [field]: value,
    })
}

function updateAssignedUser(value) {
    emit('update:form', {
        ...props.form,
        assigned_user_id: value === '' || value === 'null' ? null : Number(value),
    })
}
</script>
