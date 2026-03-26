<template>
    <FormCard title="Reservatie" class="h-full">
        <div class="space-y-5">
            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label class="mb-2 block text-xs font-medium text-slate-400">
                        Datum
                    </label>

                    <input
                        v-model="model.event_date"
                        type="date"
                        class="w-full rounded-xl border border-slate-700 bg-slate-800 px-4 py-3 text-sm text-slate-100 shadow-sm transition focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-900"
                    >
                </div>

                <div>
                    <label class="mb-2 block text-xs font-medium text-slate-400">
                        Startuur
                    </label>

                    <input
                        v-model="model.event_time"
                        type="time"
                        class="w-full rounded-xl border border-slate-700 bg-slate-800 px-4 py-3 text-sm text-slate-100 shadow-sm transition focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-900"
                    >
                </div>
            </div>

            <div>
                <label class="mb-2 block text-xs font-medium text-slate-400">
                    Verblijfsduur
                </label>

                <select
                    v-model="model.stay_option_id"
                    class="w-full rounded-xl border border-slate-700 bg-slate-800 px-4 py-3 text-sm font-medium text-slate-100 shadow-sm transition focus:border-blue-500 focus:outline-none focus:ring-2 focus:ring-blue-900"
                >
                    <option
                        v-for="option in stayOptions"
                        :key="option.id"
                        :value="option.id"
                    >
                        {{ option.emoji ? `${option.emoji} ${option.name}` : option.name }}
                    </option>
                </select>
            </div>

            <div class="rounded-xl border border-slate-700 bg-slate-800 px-4 py-3">

                <div class="mt-1 text-lg font-semibold text-slate-100">
                    {{ plannedEndTime }}
                </div>
            </div>

            <label class="flex items-center justify-between rounded-xl border border-slate-700 bg-slate-800 px-4 py-3 transition hover:bg-slate-700">
                <div>
                    <div class="text-sm font-medium text-slate-100">
                        Buiten openingsuren
                    </div>

                    <div class="text-xs text-slate-400">
                        Gebruik dit enkel indien uitzonderlijk
                    </div>
                </div>

                <input
                    v-model="model.outside_opening_hours"
                    type="checkbox"
                    class="h-5 w-5 rounded border-slate-600 bg-slate-900 text-blue-500 focus:ring-2 focus:ring-blue-500"
                >
            </label>

            <p
                v-if="model.outside_opening_hours"
                class="text-xs text-amber-400"
            >
                Deze reservatie valt buiten de standaard openingsuren.
            </p>
        </div>
    </FormCard>
</template>

<script setup>
import FormCard from '../fields/FormCard.vue'

defineProps({
    plannedEndTime: {
        type: String,
        required: true,
    },
    stayOptions: {
        type: Array,
        default: () => [],
    },
})

const model = defineModel({ type: Object, required: true })
</script>
