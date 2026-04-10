<template>
    <div class="flex flex-1 flex-col gap-4">
        <DisplayHeaderCard
            :tenant-name="tenantName"
            :tenant-logo-url="tenantLogoUrl"
            :reservation-name="reservation?.name || ''"
        />

        <DisplayStatsCard
            :total-persons-label="totalPersonsLabel"
            :played-time-label="playedTimeLabel"
            :start-time-label="startTimeLabel"
            :end-time-label="endTimeLabel"
        />

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
