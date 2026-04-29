<template>
    <div class="mx-auto max-w-3xl space-y-6">
        <div>
            <h1 class="text-3xl font-bold text-white">Voorzieningen</h1>
            <p class="mt-2 text-slate-400">Vink aan wat je venue te bieden heeft.</p>
        </div>

        <div v-if="loading" class="text-slate-400">Laden...</div>

        <template v-else>
            <Section title="Voorzieningen">
                <div class="space-y-3">
                    <div
                        v-for="amenity in amenities"
                        :key="amenity.key"
                        class="rounded-xl border border-slate-700 bg-slate-900/40 p-4"
                    >
                        <label class="flex cursor-pointer items-center gap-3">
                            <input
                                v-model="amenity.is_available"
                                type="checkbox"
                                class="h-5 w-5 rounded border-slate-600 bg-slate-800 text-cyan-500"
                            />
                            <span class="font-medium text-slate-200">{{ amenity.label }}</span>
                        </label>
                        <input
                            v-if="amenity.is_available"
                            v-model="amenity.value"
                            type="text"
                            maxlength="255"
                            placeholder="Optionele toelichting (bv. 'Gratis')"
                            class="input mt-3"
                        />
                    </div>
                </div>
            </Section>

            <div class="flex items-center gap-3">
                <button
                    type="button"
                    :disabled="saving"
                    class="rounded-xl bg-cyan-600 px-5 py-3 text-sm font-semibold text-white transition hover:bg-cyan-500 disabled:opacity-60"
                    @click="save"
                >{{ saving ? 'Bezig...' : 'Wijzigingen opslaan' }}</button>
                <span v-if="error" class="text-sm text-rose-400">{{ error }}</span>
                <span v-if="lastSaved" class="text-sm text-slate-400">Opgeslagen.</span>
            </div>
        </template>
    </div>
</template>

<script setup>
import { onMounted, ref } from 'vue'
import Section from '../components/Section.vue'
import { getAmenities, updateAmenities } from '../services/venueApi'

const loading = ref(true)
const saving = ref(false)
const error = ref('')
const lastSaved = ref(false)
const amenities = ref([])

onMounted(refresh)

async function refresh() {
    loading.value = true
    try {
        const data = await getAmenities()
        amenities.value = (data.amenities ?? []).map(a => ({
            ...a,
            value: a.value ?? '',
        }))
    } finally {
        loading.value = false
    }
}

async function save() {
    saving.value = true
    error.value = ''
    lastSaved.value = false

    try {
        const payload = amenities.value.map(a => ({
            key: a.key,
            is_available: a.is_available,
            value: a.value || null,
        }))
        const result = await updateAmenities(payload)
        amenities.value = (result.amenities ?? []).map(a => ({ ...a, value: a.value ?? '' }))
        lastSaved.value = true
        setTimeout(() => { lastSaved.value = false }, 3000)
    } catch (err) {
        error.value = err?.data?.message || 'Opslaan mislukt.'
    } finally {
        saving.value = false
    }
}
</script>

<style scoped>
.input {
    width: 100%;
    border-radius: 0.75rem;
    border: 1px solid rgb(51 65 85);
    background: rgb(2 6 23);
    padding: 0.5rem 0.75rem;
    color: white;
    font-size: 0.875rem;
    outline: none;
    transition: border-color 0.15s;
}
.input:focus { border-color: rgb(6 182 212); }
</style>
