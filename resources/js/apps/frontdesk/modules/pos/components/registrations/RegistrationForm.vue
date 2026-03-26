<template>
    <div class="h-full w-full rounded-b-2xl bg-slate-950 text-slate-100">
        <form class="flex h-full w-full flex-col" @submit.prevent="handleSubmit">
            <div class="flex h-full w-full flex-col rounded-b-2xl bg-slate-950 px-4 py-4">
                <RegistrationTabs v-model="activeTab" />

                <div class="min-h-0 flex-1 overflow-y-auto pr-1">
                    <template v-if="activeTab === 'quick'">
                        <div class="grid grid-cols-12 gap-4">
                            <div class="col-span-4">
                                <CustomerCard v-model="form" />
                            </div>

                            <div class="col-span-4">
                                <ParticipantsCard
                                    v-model="form"
                                    :total-participants="totalParticipants"
                                />
                            </div>

                            <div class="col-span-4">
                                <StatsCard v-model="form.stats" />
                            </div>
                        </div>
                    </template>

                    <template v-else-if="activeTab === 'details'">
                        <div class="grid grid-cols-3 gap-4">
                            <div class="col-span-2">
                                <ReservationCard
                                    v-model="form"
                                    :planned-end-time="plannedEndTime"
                                />
                            </div>

                            <div class="col-span-1">
                                <DetailsCard v-model="form" />
                            </div>
                        </div>
                    </template>

                    <template v-else-if="activeTab === 'invoice'">
                        <div>
                            <InvoiceCard v-model="form" />
                        </div>
                    </template>
                </div>

                <RegistrationFooter
                    :tab="activeTab"
                    @cancel="$emit('cancel')"
                    @previous="goPrevious"
                    @next="goNext"
                    @submit="handleSubmit"
                />
            </div>
        </form>
    </div>
</template>

<script setup>
import { ref } from 'vue'
import { useRegistrationForm } from '../../composables/useRegistrationForm'
import RegistrationTabs from './RegistrationTabs.vue'
import RegistrationFooter from './RegistrationFooter.vue'
import CustomerCard from './cards/CustomerCard.vue'
import ParticipantsCard from './cards/ParticipantsCard.vue'
import StatsCard from './cards/StatsCard.vue'
import ReservationCard from './cards/ReservationCard.vue'
import DetailsCard from './cards/DetailsCard.vue'
import InvoiceCard from './cards/InvoiceCard.vue'

const props = defineProps({
    initialValues: {
        type: Object,
        default: () => ({}),
    },
})

const emit = defineEmits(['cancel', 'submit'])

const activeTab = ref('quick')

const {
    form,
    totalParticipants,
    plannedEndTime,
} = useRegistrationForm(props.initialValues)

function goNext() {
    if (activeTab.value === 'quick') {
        activeTab.value = 'details'
        return
    }

    if (activeTab.value === 'details') {
        activeTab.value = 'invoice'
    }
}

function goPrevious() {
    if (activeTab.value === 'invoice') {
        activeTab.value = 'details'
        return
    }

    if (activeTab.value === 'details') {
        activeTab.value = 'quick'
    }
}

function handleSubmit() {
    emit('submit', structuredClone(form))
}
</script>
