<template>
    <div class="fixed inset-0 z-50 flex flex-col justify-end" @click.self="$emit('close')">
        <div class="absolute inset-0 bg-slate-950/70 backdrop-blur-sm" @click="$emit('close')" />
        <div class="relative glass-card rounded-t-3xl px-5 pt-4 pb-safe pb-8">
            <div class="w-10 h-1 rounded-full bg-slate-700 mx-auto mb-5" />

            <div class="space-y-2 mb-4">
                <button
                    v-for="v in venue.venues"
                    :key="v.slug"
                    class="w-full flex items-center gap-3 px-4 py-3 rounded-2xl transition-colors"
                    :class="v.slug === venue.activeSlug
                        ? 'bg-blue-500/15 border border-blue-500/30'
                        : 'bg-slate-800/50 border border-slate-700/40'"
                    @click="select(v.slug)"
                >
                    <span
                        class="w-2.5 h-2.5 rounded-full shrink-0"
                        :class="v.is_active ? 'bg-emerald-400' : 'bg-slate-500'"
                    />
                    <div class="flex-1 text-left">
                        <div class="text-sm font-medium text-slate-100">{{ v.name }}</div>
                        <div class="text-xs text-slate-400 capitalize">{{ v.membership_status === 'active' ? 'Actief lid' : v.membership_status === 'expired' ? 'Verlopen' : 'Bezoekersprofiel' }}</div>
                    </div>
                    <CheckIcon v-if="v.slug === venue.activeSlug" class="w-4 h-4 text-blue-400" />
                </button>
            </div>

            <button
                class="w-full flex items-center justify-center gap-2 py-3 rounded-2xl border border-dashed border-slate-700 text-slate-400 text-sm"
                @click="discover"
            >
                <PlusIcon class="w-4 h-4" />
                Nieuwe venue ontdekken
            </button>
        </div>
    </div>
</template>

<script setup>
import { CheckIcon, PlusIcon } from '@heroicons/vue/24/outline'
import { useVenueStore } from '../stores/useVenueStore'
import { useRouter } from 'vue-router'

const emit = defineEmits(['close'])
const venue = useVenueStore()
const router = useRouter()

async function select(slug) {
    await venue.switchVenue(slug)
    emit('close')
}

function discover() {
    emit('close')
    router.push('/ontdekken')
}
</script>
