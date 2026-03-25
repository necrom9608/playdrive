<template>
    <div class="h-full min-h-0">
        <PosLayout />
    </div>
</template>

<script setup>
import { onMounted } from 'vue'
import PosLayout from '../components/PosLayout.vue'
import { usePosStore } from '../stores/usePosStore'

const store = usePosStore()

onMounted(() => {
    store.loadInitialData()
})
</script>
