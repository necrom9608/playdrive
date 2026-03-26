<template>
    <FormCard title="Deelnemers" class="h-full">
        <div class="space-y-5">
            <NumberStepperField
                v-model="model.kids_count"
                label="Kinderen / studenten"
            />

            <NumberStepperField
                v-model="model.adults_count"
                label="Volwassenen"
            />

            <NumberStepperField
                v-model="model.supervisors_count"
                label="Begeleiders"
            />

            <div class="rounded-xl border border-slate-700 bg-slate-800 px-4 py-3">
                <div class="text-xs font-medium text-slate-400">
                    Totaal
                </div>

                <div class="mt-1 text-2xl font-semibold text-slate-100">
                    {{ totalParticipants }}
                </div>
            </div>
        </div>
    </FormCard>
</template>

<script setup>
import FormCard from '../fields/FormCard.vue'
import NumberStepperField from '../fields/NumberStepperField.vue'

defineProps({
    totalParticipants: {
        type: Number,
        required: true,
    },
})

const model = defineModel({ type: Object, required: true })
</script>
