<template>
    <div class="px-4 pt-4 pb-6 space-y-4">
        <h1 class="text-lg font-semibold text-white">Ontdekken</h1>

        <!-- Laden -->
        <div v-if="loading" class="flex justify-center pt-16">
            <div class="flex gap-2">
                <span class="loader-dot" /><span class="loader-dot" /><span class="loader-dot" />
            </div>
        </div>

        <!-- Lijst -->
        <template v-else>
            <div
                v-for="v in venues"
                :key="v.slug"
                class="glass-card rounded-3xl p-5 relative overflow-hidden"
            >
                <div class="flex items-start gap-4">
                    <!-- Logo of initialen -->
                    <div class="w-12 h-12 rounded-2xl bg-blue-500/15 border border-blue-500/20 flex items-center justify-center shrink-0 overflow-hidden">
                        <img v-if="v.logo_url" :src="v.logo_url" :alt="v.name" class="w-full h-full object-contain p-1" />
                        <span v-else class="text-base font-bold text-blue-300">{{ v.name[0] }}</span>
                    </div>

                    <!-- Info -->
                    <div class="flex-1 min-w-0">
                        <div class="flex items-center gap-2 flex-wrap">
                            <p class="text-sm font-semibold text-white">{{ v.name }}</p>
                            <span v-if="isJoined(v.slug)" class="text-[10px] px-2 py-0.5 rounded-full bg-emerald-500/15 border border-emerald-500/25 text-emerald-300">
                                gekoppeld
                            </span>
                        </div>
                        <p v-if="v.city" class="text-xs text-slate-400 mt-0.5 flex items-center gap-1">
                            <MapPinIcon class="w-3 h-3" />
                            {{ v.city }}
                        </p>
                    </div>
                </div>

                <!-- Actie -->
                <div class="mt-4">
                    <button
                        v-if="!isJoined(v.slug)"
                        class="member-btn-primary w-full flex items-center justify-center gap-2 text-sm py-2.5"
                        :disabled="joining === v.slug"
                        @click="join(v.slug)"
                    >
                        <template v-if="joining === v.slug">
                            <span class="loader-dot" /><span class="loader-dot" /><span class="loader-dot" />
                        </template>
                        <template v-else>
                            <PlusIcon class="w-4 h-4" />
                            Toevoegen aan mijn venues
                        </template>
                    </button>
                    <button
                        v-else
                        class="w-full flex items-center justify-center gap-2 py-2.5 rounded-2xl bg-emerald-500/10 border border-emerald-500/25 text-emerald-300 text-sm"
                        @click="goToVenue(v.slug)"
                    >
                        <CheckIcon class="w-4 h-4" />
                        Bekijk mijn lidmaatschap
                    </button>
                </div>
            </div>

            <!-- Leeg -->
            <div v-if="venues.length === 0" class="glass-card rounded-3xl p-8 text-center">
                <p class="text-slate-400 text-sm">Geen venues gevonden.</p>
            </div>
        </template>

        <!-- Succes toast -->
        <Transition name="toast">
            <div
                v-if="toast"
                class="fixed bottom-24 left-1/2 -translate-x-1/2 px-5 py-3 rounded-2xl bg-emerald-500/20 border border-emerald-500/30 text-emerald-300 text-sm font-medium whitespace-nowrap backdrop-blur-xl"
            >
                {{ toast }}
            </div>
        </Transition>
    </div>
</template>

<script setup>
import { ref, onMounted } from 'vue'
import { useRouter } from 'vue-router'
import { MapPinIcon, PlusIcon, CheckIcon } from '@heroicons/vue/24/outline'
import { api } from '../../services/api'
import { useVenueStore } from '../../stores/useVenueStore'

const router = useRouter()
const venue = useVenueStore()

const venues = ref([])
const loading = ref(true)
const joining = ref(null)
const toast = ref('')

async function load() {
    loading.value = true
    try {
        const { data } = await api.get('/venues/discover')
        venues.value = data
    } finally {
        loading.value = false
    }
}

function isJoined(slug) {
    return venue.venues.some(v => v.slug === slug)
}

async function join(slug) {
    joining.value = slug
    try {
        await venue.joinVenue(slug)
        showToast('Venue toegevoegd!')
    } catch (e) {
        showToast(e?.message ?? 'Er is een fout opgetreden.')
    } finally {
        joining.value = null
    }
}

function goToVenue(slug) {
    venue.switchVenue(slug)
    router.push('/mijn/lidmaatschap')
}

function showToast(msg) {
    toast.value = msg
    setTimeout(() => toast.value = '', 2500)
}

onMounted(load)
</script>

<style scoped>
.toast-enter-active, .toast-leave-active { transition: opacity .2s, transform .2s; }
.toast-enter-from, .toast-leave-to { opacity: 0; transform: translate(-50%, 8px); }
</style>
