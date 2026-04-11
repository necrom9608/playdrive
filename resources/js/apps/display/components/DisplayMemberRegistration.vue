<template>
    <div class="mx-auto flex h-full w-full max-w-[1120px] flex-col rounded-[2rem] border border-sky-500/20 bg-slate-950/80 p-6 shadow-2xl shadow-slate-950/60 backdrop-blur-xl">
        <div class="flex items-start justify-between gap-6 border-b border-slate-800 pb-5">
            <div>
                <div class="text-sm uppercase tracking-[0.45em] text-cyan-300/80">Nieuw lid</div>
                <h1 class="mt-2 text-4xl font-semibold text-white">Registreer een nieuw lid</h1>
                <p class="mt-3 max-w-3xl text-lg text-slate-300">
                    Vul de gegevens in en kies daarna het kaartdesign. Het lidmaatschap loopt automatisch van vandaag tot exact 1 jaar later.
                </p>
            </div>

            <div class="rounded-[1.5rem] border border-slate-700 bg-slate-900/70 px-5 py-4 text-right">
                <div class="text-xs uppercase tracking-[0.35em] text-slate-500">Stap</div>
                <div class="mt-1 text-4xl font-semibold text-white">{{ currentStep }}/2</div>
            </div>
        </div>

        <div v-if="error" class="mt-5 rounded-2xl border border-rose-500/40 bg-rose-500/10 px-5 py-4 text-base text-rose-100">
            {{ error }}
        </div>

        <div class="min-h-0 flex-1 overflow-y-auto py-6">
            <div v-if="step === 1" class="space-y-8">
                <section class="space-y-4">
                    <div>
                        <h2 class="text-xl font-semibold text-white">Gegevens</h2>
                        <div class="mt-1 text-sm text-slate-400">Alle basisgegevens van het nieuwe lid.</div>
                    </div>

                    <div class="grid gap-4 lg:grid-cols-2">
                        <label class="space-y-2 text-sm text-slate-300">
                            <span>Voornaam *</span>
                            <input v-model="form.first_name" type="text" :class="fieldClass">
                        </label>
                        <label class="space-y-2 text-sm text-slate-300">
                            <span>Naam *</span>
                            <input v-model="form.last_name" type="text" :class="fieldClass">
                        </label>
                        <label class="space-y-2 text-sm text-slate-300">
                            <span>E-mail *</span>
                            <input v-model="form.email" type="email" :class="fieldClass">
                        </label>
                        <label class="space-y-2 text-sm text-slate-300">
                            <span>Telefoon</span>
                            <input v-model="form.phone" type="text" :class="fieldClass">
                        </label>
                        <label class="space-y-2 text-sm text-slate-300">
                            <span>Paswoord *</span>
                            <input v-model="form.password" type="password" :class="fieldClass">
                        </label>
                        <label class="space-y-2 text-sm text-slate-300">
                            <span>Herhaal paswoord *</span>
                            <input v-model="form.password_confirmation" type="password" :class="fieldClass">
                        </label>
                        <label class="space-y-2 text-sm text-slate-300">
                            <span>Geboortedatum</span>
                            <input v-model="form.birth_date" type="date" :class="fieldClass">
                        </label>
                        <div class="space-y-2 text-sm text-slate-300">
                            <span>Type *</span>
                            <div class="grid grid-cols-2 gap-3">
                                <button type="button" class="rounded-2xl border px-4 py-4 text-left text-base font-semibold transition" :class="form.membership_type === 'adult' ? selectedToggleClass : toggleClass" @click="form.membership_type='adult'">Volwassen</button>
                                <button type="button" class="rounded-2xl border px-4 py-4 text-left text-base font-semibold transition" :class="form.membership_type === 'student' ? selectedToggleClass : toggleClass" @click="form.membership_type='student'">Student</button>
                            </div>
                        </div>
                    </div>
                </section>

                <section class="space-y-4">
                    <div>
                        <h2 class="text-xl font-semibold text-white">Adres</h2>
                        <div class="mt-1 text-sm text-slate-400">Adresgegevens van het lid.</div>
                    </div>

                    <div class="grid gap-4 lg:grid-cols-[minmax(0,1fr)_180px]">
                        <label class="space-y-2 text-sm text-slate-300">
                            <span>Straat</span>
                            <input v-model="form.street" type="text" :class="fieldClass">
                        </label>
                        <label class="space-y-2 text-sm text-slate-300">
                            <span>Nr</span>
                            <input v-model="form.house_number" type="text" :class="fieldClass">
                        </label>
                    </div>

                    <div class="grid gap-4 lg:grid-cols-[220px_minmax(0,1fr)]">
                        <label class="space-y-2 text-sm text-slate-300">
                            <span>Postcode</span>
                            <input v-model="form.postal_code" type="text" :class="fieldClass">
                        </label>
                        <label class="space-y-2 text-sm text-slate-300">
                            <span>Gemeente</span>
                            <input v-model="form.city" type="text" :class="fieldClass">
                        </label>
                    </div>
                </section>
            </div>

            <div v-else class="space-y-4">
                <div>
                    <h2 class="text-xl font-semibold text-white">Kaartdesign kiezen</h2>
                    <div class="mt-1 text-sm text-slate-400">Kies welk kaartdesign gebruikt wordt voor het nieuwe lid.</div>
                </div>

                <div v-if="templates.length" class="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
                    <button
                        v-for="template in templates"
                        :key="template.id"
                        type="button"
                        class="rounded-[1.75rem] border p-5 text-left transition"
                        :class="template.id === form.badge_template_id ? 'border-cyan-400 bg-cyan-500/10 text-white shadow-lg shadow-cyan-950/20' : 'border-slate-800 bg-slate-900/70 text-slate-300 hover:border-slate-600 hover:bg-slate-900'"
                        @click="form.badge_template_id = template.id"
                    >
                        <div class="text-lg font-semibold">{{ template.name }}</div>
                        <div class="mt-2 text-sm text-slate-400">{{ template.description || 'Geen beschrijving beschikbaar.' }}</div>
                    </button>
                </div>

                <div v-else class="rounded-2xl border border-dashed border-slate-700 bg-slate-900/50 px-5 py-6 text-slate-400">
                    Er zijn nog geen member kaartdesigns beschikbaar.
                </div>
            </div>
        </div>

        <div class="flex items-center justify-between gap-4 border-t border-slate-800 pt-5">
            <button type="button" class="rounded-2xl border border-slate-700 bg-slate-900 px-6 py-4 text-lg font-semibold text-slate-200 transition hover:bg-slate-800 disabled:cursor-not-allowed disabled:opacity-40" :disabled="step === 1 || saving" @click="step = 1">Vorige</button>

            <div class="flex items-center gap-3">
                <button type="button" class="rounded-2xl border border-rose-500/30 bg-rose-500/10 px-6 py-4 text-lg font-semibold text-rose-100 transition hover:bg-rose-500/20 disabled:cursor-not-allowed disabled:opacity-40" :disabled="saving" @click="$emit('cancel')">Annuleren</button>
                <button v-if="step === 1" type="button" class="rounded-2xl bg-cyan-500 px-8 py-4 text-lg font-semibold text-slate-950 transition hover:bg-cyan-400" @click="goNext">Volgende</button>
                <button v-else type="button" class="rounded-2xl bg-emerald-500 px-8 py-4 text-lg font-semibold text-slate-950 transition hover:bg-emerald-400 disabled:cursor-not-allowed disabled:opacity-60" :disabled="saving" @click="submit">{{ saving ? 'Opslaan...' : 'Opslaan' }}</button>
            </div>
        </div>
    </div>
</template>

<script setup>
import { reactive, ref, watch } from 'vue'

const props = defineProps({
    templates: { type: Array, default: () => [] },
    saving: { type: Boolean, default: false },
    error: { type: String, default: '' },
})

const emit = defineEmits(['submit', 'cancel'])

const step = ref(1)
const form = reactive(createDefaultForm(props.templates))

watch(() => props.templates, (templates) => {
    if (!form.badge_template_id) {
        form.badge_template_id = defaultTemplateId(templates)
    }
}, { deep: true })

function createDefaultForm(templates) {
    return {
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
        badge_template_id: defaultTemplateId(templates),
    }
}

function defaultTemplateId(templates) {
    return templates.find(template => template.is_default)?.id ?? templates[0]?.id ?? null
}

function goNext() {
    if (!form.first_name || !form.last_name || !form.email || !form.password || !form.password_confirmation || !form.membership_type) {
        return
    }

    step.value = 2
}

function submit() {
    emit('submit', { ...form })
}

const currentStep = step
const fieldClass = 'w-full rounded-2xl border border-slate-700 bg-slate-950 px-4 py-4 text-lg text-white outline-none transition focus:border-cyan-400'
const toggleClass = 'border-slate-700 bg-slate-950 text-slate-200 hover:border-slate-500 hover:bg-slate-900'
const selectedToggleClass = 'border-cyan-400 bg-cyan-500/10 text-white shadow-lg shadow-cyan-950/20'
</script>
