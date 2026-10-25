<template>
    <FormCard title="Deelnemers" class="h-full">
        <div class="space-y-5">
            <div>
                <NumberStepperField
                    v-model="model.participants_children"
                    label="Kinderen / studenten"
                    :min="subscribersChildren"
                />
                <div v-if="subscribersChildren" class="mt-1 text-[11px] text-emerald-300">
                    waarvan {{ subscribersChildren }} abonnee{{ subscribersChildren > 1 ? 's' : '' }} · {{ payingChildren }} betalend
                </div>
            </div>

            <div>
                <NumberStepperField
                    v-model="model.participants_adults"
                    label="Volwassenen"
                    :min="subscribersAdults"
                />
                <div v-if="subscribersAdults" class="mt-1 text-[11px] text-emerald-300">
                    waarvan {{ subscribersAdults }} abonnee{{ subscribersAdults > 1 ? 's' : '' }} · {{ payingAdults }} betalend
                </div>
            </div>

            <NumberStepperField
                v-model="model.participants_supervisors"
                label="Begeleiders"
            />

            <SubscribersBlock v-model="model" />

            <div class="rounded-xl border border-slate-700 bg-slate-800 px-4 py-3">
                <div class="text-xs font-medium text-slate-400">
                    Totaal
                </div>

                <div class="mt-1 text-2xl font-semibold text-slate-100">
                    {{ totalParticipants }}
                </div>

                <div v-if="subscriberCount" class="mt-1 text-xs text-slate-400">
                    Betalende speeltijd: <span class="font-semibold text-slate-200">{{ payingChildren + payingAdults }}</span>
                    · <span class="text-emerald-300">{{ subscriberCount }} gratis (abonnement)</span>
                </div>
            </div>
        </div>
    </FormCard>
</template>

<script setup>
import { computed } from 'vue'
import FormCard from '../fields/FormCard.vue'
import NumberStepperField from '../fields/NumberStepperField.vue'
import SubscribersBlock from './SubscribersBlock.vue'

defineProps({
    totalParticipants: {
        type: Number,
        required: true,
    },
})

const model = defineModel({ type: Object, required: true })

const subscribers = computed(() => model.value.subscribers ?? [])
const subscriberCount = computed(() => subscribers.value.length)
const subscribersChildren = computed(() => subscribers.value.filter(s => s.participant_scope === 'children').length)
const subscribersAdults = computed(() => subscribers.value.filter(s => s.participant_scope === 'adults').length)
const payingChildren = computed(() => Math.max(0, Number(model.value.participants_children || 0) - subscribersChildren.value))
const payingAdults = computed(() => Math.max(0, Number(model.value.participants_adults || 0) - subscribersAdults.value))
</script>
