<template>
    <FormCard title="Statistieken" class="h-full">
        <div class="flex flex-col gap-3 text-sm">
            <ToggleCardField v-model="model.already_visited" label="Reeds geweest" emoji="🔁" />
            <ToggleCardField v-model="model.recommended_by_friend" label="Aanrader" emoji="👥" />
            <ToggleCardField v-model="model.internet" label="Internet" emoji="🌐" />
            <ToggleCardField v-model="model.social_media" label="Sociale media" emoji="📱" />
            <ToggleCardField v-model="model.facade" label="Gevel" emoji="🏢" />
            <ToggleCardField v-model="model.ai" label="AI" emoji="🤖" />
        </div>
    </FormCard>
</template>

<script setup>
import FormCard from '../fields/FormCard.vue'
import ToggleCardField from '../fields/ToggleCardField.vue'

const model = defineModel({ type: Object, required: true })
</script>
