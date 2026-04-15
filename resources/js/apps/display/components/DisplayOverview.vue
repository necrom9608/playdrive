<template>
    <div class="flex h-full min-h-0 flex-1 flex-col gap-4 lg:grid lg:grid-cols-[430px,minmax(0,1fr)]">
        <div class="flex flex-col gap-4 lg:min-h-0">
            <DisplayHeaderCard
                :tenant-name="tenantName"
                :tenant-logo-url="tenantLogoUrl"
                :reservation="reservation"
                :reservation-name="reservation?.name || ''"
                @logo-hold-start="emit('logo-hold-start')"
                @logo-hold-end="emit('logo-hold-end')"
            />

            <DisplayStatsCard
                :total-persons-label="totalPersonsLabel"
                :played-time-label="playedTimeLabel"
                :start-time-label="startTimeLabel"
                :end-time-label="endTimeLabel"
            />
        </div>

        <DisplayListCard
            :items="groupedOrderItems"
            :item-count="groupedOrderCount"
            :total="orderTotal"
        />
    </div>
</template>

<script setup>
import DisplayHeaderCard from './DisplayHeaderCard.vue'
import DisplayListCard from './DisplayListCard.vue'
import DisplayStatsCard from './DisplayStatsCard.vue'

const emit = defineEmits(['logo-hold-start', 'logo-hold-end'])

defineProps({
    tenantName: {
        type: String,
        default: 'PlayDrive',
    },
    tenantLogoUrl: {
        type: String,
        default: '',
    },
    reservation: {
        type: Object,
        default: () => ({}),
    },
    totalPersonsLabel: {
        type: String,
        default: '-',
    },
    playedTimeLabel: {
        type: String,
        default: '00:00',
    },
    startTimeLabel: {
        type: String,
        default: '-',
    },
    endTimeLabel: {
        type: String,
        default: '-',
    },
    groupedOrderItems: {
        type: Array,
        default: () => [],
    },
    groupedOrderCount: {
        type: Number,
        default: 0,
    },
    orderTotal: {
        type: Number,
        default: 0,
    },
})
</script>
