<template>
    <div class="h-full w-full rounded-b-2xl bg-slate-950 text-slate-100">
        <form class="flex h-full w-full flex-col" @submit.prevent="handleSubmit">
            <div class="flex h-full w-full flex-col rounded-b-2xl bg-slate-950 px-4 py-4">
                <RegistrationTabs v-model="activeTab" />

                <div class="mb-4 rounded-xl bg-slate-800 p-3 text-xs text-slate-300">
                    activeTab: {{ activeTab }} |
                    loading: {{ isLoadingOptions }} |
                    error: {{ optionsError }} |
                    eventTypes: {{ eventTypes.length }} |
                    stayOptions: {{ stayOptions.length }} |
                    cateringOptions: {{ cateringOptions.length }}
                </div>

                <div class="min-h-0 flex-1 overflow-y-auto pr-1">
                    <div
                        v-if="isLoadingOptions"
                        class="flex h-full items-center justify-center rounded-2xl border border-slate-800 bg-slate-900/60 p-8 text-sm text-slate-400"
                    >
                        Formulieropties laden...
                    </div>

                    <div
                        v-else-if="optionsError"
                        class="rounded-2xl border border-red-900 bg-red-950/30 p-4 text-sm text-red-300"
                    >
                        Kon formulieropties niet laden.
                    </div>

                    <template v-else-if="activeTab === 'quick'">
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
                                    :stay-options="stayOptions"
                                />
                            </div>

                            <div class="col-span-1">
                                <DetailsCard
                                    v-model="form"
                                    :event-types="eventTypes"
                                    :catering-options="cateringOptions"
                                />
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
import axios from 'axios'
import { computed, onMounted, ref, toRaw, watch } from 'vue'
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
const isLoadingOptions = ref(true)
const optionsError = ref(false)

const eventTypes = ref([])
const stayOptions = ref([])
const cateringOptions = ref([])

const selectedStayOption = computed(() => {
    if (!form?.stay_option_id) {
        return null
    }

    return stayOptions.value.find((option) => option.id === form.stay_option_id) ?? null
})

const {
    form,
    totalParticipants,
    plannedEndTime,
    fillForm,
} = useRegistrationForm(props.initialValues, selectedStayOption)

watch(
    () => props.initialValues,
    (value) => {
        fillForm(value ?? {})
    },
    { deep: true }
)

async function loadOptions() {
    isLoadingOptions.value = true
    optionsError.value = false

    try {
        const response = await axios.get('/api/frontdesk/form-options')

        eventTypes.value = response.data.event_types ?? []
        stayOptions.value = response.data.stay_options ?? []
        cateringOptions.value = response.data.catering_options ?? []

        if (!form.event_type_id && eventTypes.value.length > 0) {
            const freePlay = eventTypes.value.find((item) => item.code === 'free_play')
            form.event_type_id = freePlay?.id ?? eventTypes.value[0].id
        }

        if (!form.stay_option_id && stayOptions.value.length > 0) {
            const twoHours = stayOptions.value.find((item) => item.code === '2h')
            form.stay_option_id = twoHours?.id ?? stayOptions.value[0].id
        }

        if (!form.catering_option_id && cateringOptions.value.length > 0) {
            const noneOption = cateringOptions.value.find((item) => item.code === 'none')
            form.catering_option_id = noneOption?.id ?? null
        }
    } catch (error) {
        console.error('form-options failed', error)
        optionsError.value = true
    } finally {
        isLoadingOptions.value = false
    }
}

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
    const payload = JSON.parse(JSON.stringify(toRaw(form)))
    emit('submit', payload)
}

onMounted(() => {
    loadOptions()
})
</script>
