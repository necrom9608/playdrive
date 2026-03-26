<template>
    <FormCard title="Details" class="h-full">
        <div class="space-y-5">
            <div>
                <label class="mb-2 block text-xs font-medium text-slate-400">
                    Type event
                </label>

                <select
                    v-model="model.event_type_id"
                    class="w-full rounded-xl border border-slate-700 bg-slate-800 px-4 py-3 text-sm font-medium text-slate-100 shadow-sm transition focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-900"
                >
                    <option :value="null">Kies een type</option>

                    <option
                        v-for="option in eventTypes"
                        :key="option.id"
                        :value="option.id"
                    >
                        {{ option.name }}
                    </option>
                </select>
            </div>

            <div>
                <label class="mb-2 block text-xs font-medium text-slate-400">
                    Catering
                </label>

                <select
                    v-model="model.catering_option_id"
                    class="w-full rounded-xl border border-slate-700 bg-slate-800 px-4 py-3 text-sm font-medium text-slate-100 shadow-sm transition focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-900"
                >
                    <option :value="null">Geen / niet gekozen</option>

                    <option
                        v-for="option in cateringOptions"
                        :key="option.id"
                        :value="option.id"
                    >
                        {{ option.name }}
                    </option>
                </select>
            </div>

            <div>
                <label class="mb-2 block text-xs font-medium text-slate-400">
                    Status
                </label>

                <select
                    v-model="model.status"
                    class="w-full rounded-xl border border-slate-700 bg-slate-800 px-4 py-3 text-sm font-medium text-slate-100 shadow-sm transition focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-900"
                >
                    <option value="new">Nieuw</option>
                    <option value="confirmed">Gereserveerd</option>
                    <option value="checked_in">Ingecheckt</option>
                    <option value="checked_out">Uitgecheckt</option>
                    <option value="paid">Betaald</option>
                    <option value="cancelled">Geannuleerd</option>
                    <option value="no_show">No-show</option>
                </select>
            </div>

            <div>
                <label class="mb-2 block text-xs font-medium text-slate-400">
                    Commentaar
                </label>

                <textarea
                    v-model="model.comment"
                    rows="5"
                    placeholder="Extra info, opmerkingen of afspraken..."
                    class="w-full resize-none rounded-xl border border-slate-700 bg-slate-800 px-4 py-3 text-sm text-slate-100 placeholder:text-slate-500 shadow-sm transition focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-900"
                />
            </div>
        </div>
    </FormCard>
</template>

<script setup>
import FormCard from '../fields/FormCard.vue'

defineProps({
    eventTypes: {
        type: Array,
        default: () => [],
    },
    cateringOptions: {
        type: Array,
        default: () => [],
    },
})

const model = defineModel({ type: Object, required: true })
</script>
