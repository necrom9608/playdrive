<template>
    <div class="flex min-h-0 flex-1 flex-col">
        <div class="rounded-[2rem] border border-cyan-500/20 bg-slate-900/80 p-6 shadow-2xl shadow-slate-950/50 backdrop-blur-xl">
            <div class="flex items-start justify-between gap-4">
                <div>
                    <div class="text-sm uppercase tracking-[0.35em] text-cyan-300/70">Nieuw lid</div>
                    <h1 class="mt-2 text-4xl font-semibold text-white">Registreer een nieuw lid</h1>
                    <p class="mt-3 max-w-3xl text-lg text-slate-300">Vul de gegevens in en kies daarna het kaartdesign. Het lidmaatschap loopt automatisch van vandaag tot exact 1 jaar later.</p>
                </div>
                <div class="rounded-2xl border border-slate-700 bg-slate-950/70 px-4 py-3 text-right">
                    <div class="text-xs uppercase tracking-[0.25em] text-slate-500">Stap</div>
                    <div class="mt-2 text-3xl font-semibold text-white">{{ currentStep }}/2</div>
                </div>
            </div>
        </div>

        <div class="mt-6 min-h-0 flex-1 overflow-hidden rounded-[2rem] border border-slate-800 bg-slate-900/70 shadow-2xl shadow-slate-950/50">
            <div class="h-full overflow-y-auto p-6">
                <div v-if="isSuccess" class="flex h-full flex-col items-center justify-center text-center">
                    <div class="rounded-full bg-emerald-500/15 p-5 text-emerald-300">
                        <svg class="h-14 w-14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" />
                        </svg>
                    </div>
                    <h2 class="mt-6 text-4xl font-semibold text-white">Lid aangemaakt</h2>
                    <p class="mt-3 max-w-2xl text-xl text-slate-300">{{ successMember?.full_name || 'Nieuw lid' }} werd succesvol toegevoegd.</p>
                    <div class="mt-6 grid gap-4 md:grid-cols-3">
                        <div class="rounded-2xl border border-slate-700 bg-slate-950/70 px-5 py-4">
                            <div class="text-xs uppercase tracking-[0.25em] text-slate-500">Lidnummer</div>
                            <div class="mt-2 text-2xl font-semibold text-white">#{{ successMember?.id }}</div>
                        </div>
                        <div class="rounded-2xl border border-slate-700 bg-slate-950/70 px-5 py-4">
                            <div class="text-xs uppercase tracking-[0.25em] text-slate-500">Geldig van</div>
                            <div class="mt-2 text-2xl font-semibold text-white">{{ successMember?.membership_starts_at }}</div>
                        </div>
                        <div class="rounded-2xl border border-slate-700 bg-slate-950/70 px-5 py-4">
                            <div class="text-xs uppercase tracking-[0.25em] text-slate-500">Geldig tot</div>
                            <div class="mt-2 text-2xl font-semibold text-white">{{ successMember?.membership_ends_at }}</div>
                        </div>
                    </div>
                </div>

                <template v-else>
                    <div v-if="submitError" class="mb-6 rounded-2xl border border-rose-500/30 bg-rose-500/10 px-5 py-4 text-base text-rose-100">
                        {{ submitError }}
                    </div>

                    <template v-if="currentStep === 1">
                        <section>
                            <h2 class="text-xl font-semibold text-white">Gegevens</h2>
                            <div class="mt-4 grid gap-5 md:grid-cols-2">
                                <label class="space-y-2 text-sm text-slate-300">
                                    <span>Voornaam *</span>
                                    <input v-model="form.first_name" type="text" :class="inputClass">
                                </label>
                                <label class="space-y-2 text-sm text-slate-300">
                                    <span>Naam *</span>
                                    <input v-model="form.last_name" type="text" :class="inputClass">
                                </label>
                                <label class="space-y-2 text-sm text-slate-300">
                                    <span>E-mail *</span>
                                    <input v-model="form.email" type="email" :class="inputClass">
                                </label>
                                <label class="space-y-2 text-sm text-slate-300">
                                    <span>Telefoon</span>
                                    <input v-model="form.phone" type="text" :class="inputClass">
                                </label>
                                <label class="space-y-2 text-sm text-slate-300">
                                    <span>Paswoord *</span>
                                    <input v-model="form.password" type="password" :class="inputClass">
                                </label>
                                <label class="space-y-2 text-sm text-slate-300">
                                    <span>Herhaal paswoord *</span>
                                    <input v-model="form.password_confirmation" type="password" :class="inputClass">
                                </label>
                                <label class="space-y-2 text-sm text-slate-300">
                                    <span>Geboortedatum</span>
                                    <input v-model="form.birth_date" type="date" :class="inputClass">
                                </label>
                                <div class="space-y-2 text-sm text-slate-300">
                                    <span>Type</span>
                                    <div class="grid grid-cols-2 gap-3">
                                        <button type="button" class="rounded-2xl border px-4 py-4 text-base font-semibold transition" :class="form.membership_type === 'adult' ? 'border-cyan-400 bg-cyan-500/15 text-white' : 'border-slate-700 bg-slate-950 text-slate-300'" @click="form.membership_type = 'adult'">Volwassen</button>
                                        <button type="button" class="rounded-2xl border px-4 py-4 text-base font-semibold transition" :class="form.membership_type === 'student' ? 'border-cyan-400 bg-cyan-500/15 text-white' : 'border-slate-700 bg-slate-950 text-slate-300'" @click="form.membership_type = 'student'">Student</button>
                                    </div>
                                </div>
                            </div>
                        </section>

                        <section class="mt-8">
                            <h2 class="text-xl font-semibold text-white">Adres</h2>
                            <div class="mt-4 grid gap-5 md:grid-cols-[minmax(0,1fr)_220px]">
                                <label class="space-y-2 text-sm text-slate-300">
                                    <span>Straat</span>
                                    <input v-model="form.street" type="text" :class="inputClass">
                                </label>
                                <label class="space-y-2 text-sm text-slate-300">
                                    <span>Nr</span>
                                    <input v-model="form.house_number" type="text" :class="inputClass">
                                </label>
                                <label class="space-y-2 text-sm text-slate-300">
                                    <span>Postcode</span>
                                    <input v-model="form.postal_code" type="text" :class="inputClass">
                                </label>
                                <label class="space-y-2 text-sm text-slate-300">
                                    <span>Gemeente</span>
                                    <input v-model="form.city" type="text" :class="inputClass">
                                </label>
                            </div>
                        </section>
                    </template>

                    <template v-else>
                        <section>
                            <h2 class="text-xl font-semibold text-white">Kaartdesign</h2>
                            <div v-if="templates.length" class="mt-4 grid gap-4 md:grid-cols-2">
                                <button
                                    v-for="template in templates"
                                    :key="template.id"
                                    type="button"
                                    class="rounded-2xl border p-4 text-left transition"
                                    :class="form.badge_template_id === template.id ? 'border-cyan-400 bg-cyan-500/15 text-white' : 'border-slate-700 bg-slate-950/70 text-slate-300'"
                                    @click="form.badge_template_id = template.id"
                                >
                                    <div class="text-lg font-semibold">{{ template.name }}</div>
                                    <div class="mt-1 text-sm text-slate-400">{{ template.description || 'Geen beschrijving' }}</div>
                                </button>
                            </div>
                            <div v-else class="mt-4 rounded-2xl border border-dashed border-slate-700 bg-slate-950/70 px-4 py-5 text-base text-slate-400">Er zijn geen member kaartdesigns beschikbaar.</div>
                        </section>
                    </template>
                </template>
            </div>
        </div>

        <div v-if="!isSuccess" class="mt-6 flex items-center justify-between gap-4">
            <button type="button" class="rounded-2xl border border-slate-700 bg-slate-900 px-6 py-4 text-lg font-semibold text-slate-200 transition hover:bg-slate-800 disabled:opacity-40" :disabled="currentStep === 1 || submitting" @click="currentStep = 1">Vorige</button>

            <div class="flex items-center gap-4">
                <button type="button" class="rounded-2xl border border-slate-700 bg-slate-950 px-6 py-4 text-lg font-semibold text-slate-200 transition hover:bg-slate-800 disabled:opacity-40" :disabled="submitting" @click="cancel">Annuleren</button>
                <button v-if="currentStep === 1" type="button" class="rounded-2xl bg-cyan-500 px-8 py-4 text-lg font-semibold text-slate-950 transition hover:bg-cyan-400" @click="handleNext">Volgende</button>
                <button v-else type="button" class="rounded-2xl bg-emerald-500 px-8 py-4 text-lg font-semibold text-slate-950 transition hover:bg-emerald-400 disabled:opacity-50" :disabled="submitting" @click="submit">{{ submitting ? 'Opslaan...' : 'Opslaan' }}</button>
            </div>
        </div>
    </div>
</template>

<script setup>
import axios from 'axios'
import { computed, reactive, ref, watch } from 'vue'
import { getDisplayToken, getOrCreateDisplayUuid } from '../shared/device'

const props = defineProps({
    payload: { type: Object, default: () => ({}) },
})

const currentStep = ref(1)
const submitting = ref(false)
const submitError = ref('')
const inputClass = 'w-full rounded-2xl border border-slate-700 bg-slate-950 px-5 py-4 text-lg text-white outline-none transition focus:border-cyan-400'

const form = reactive({
    first_name: '',
    last_name: '',
    email: '',
    phone: '',
    password: '',
    password_confirmation: '',
    birth_date: '',
    membership_type: 'adult',
    street: '',
    house_number: '',
    postal_code: '',
    city: '',
    badge_template_id: null,
})

const templates = computed(() => Array.isArray(props.payload?.member_badge_templates) ? props.payload.member_badge_templates : [])
const isSuccess = computed(() => props.payload?.step === 'success')
const successMember = computed(() => props.payload?.member ?? null)

watch(templates, (value) => {
    if (!form.badge_template_id) {
        form.badge_template_id = props.payload?.default_badge_template_id ?? value.find(template => template.is_default)?.id ?? value[0]?.id ?? null
    }
}, { immediate: true })

watch(() => props.payload?.step, (value) => {
    if (value === 1 || value === 2) {
        currentStep.value = Number(value)
    }
})

function validateStep(step = currentStep.value) {
    submitError.value = ''

    if (step === 1) {
        if (!form.first_name.trim() || !form.last_name.trim()) {
            submitError.value = 'Voornaam en naam zijn verplicht.'
            return false
        }

        if (!form.email.trim()) {
            submitError.value = 'E-mail is verplicht.'
            return false
        }

        const emailPattern = /^[^\s@]+@[^\s@]+\.[^\s@]+$/
        if (!emailPattern.test(form.email.trim())) {
            submitError.value = 'Vul een geldig e-mailadres in.'
            return false
        }

        if (!form.password || form.password.length < 6) {
            submitError.value = 'Het paswoord moet minstens 6 tekens bevatten.'
            return false
        }

        if (form.password !== form.password_confirmation) {
            submitError.value = 'De paswoorden komen niet overeen.'
            return false
        }

        if ((form.postal_code && !form.city) || (!form.postal_code && form.city)) {
            submitError.value = 'Vul postcode en gemeente samen in.'
            return false
        }
    }

    return true
}

function handleNext() {
    if (!validateStep(1)) {
        return
    }

    currentStep.value = 2
}

async function cancel() {
    submitError.value = ''

    try {
        await axios.post('/api/display/bootstrap', {
            role: 'display',
            device_uuid: getOrCreateDisplayUuid(),
            device_token: getDisplayToken(),
            name: 'Customer Display',
        })
    } catch (error) {
        submitError.value = error?.response?.data?.message ?? 'Display resetten mislukt.'
    }
}

async function submit() {
    if (!validateStep(1)) {
        return
    }

    submitting.value = true
    submitError.value = ''

    try {
        await axios.post('/api/display/members', {
            device_uuid: getOrCreateDisplayUuid(),
            device_token: getDisplayToken(),
            ...form,
        })
    } catch (error) {
        submitError.value = error?.response?.data?.message ?? 'Lid aanmaken mislukt.'
    } finally {
        submitting.value = false
    }
}
</script>
