<template>
    <div class="flex min-h-0 flex-1 flex-col overflow-hidden rounded-[2rem] border border-slate-800 bg-slate-900/90 shadow-2xl shadow-slate-950/50 backdrop-blur-xl">
        <div class="flex items-center justify-between border-b border-slate-800 px-8 py-6">
            <div>
                <div class="text-xs font-semibold uppercase tracking-[0.3em] text-cyan-300/80">Nieuw lid</div>
                <h2 class="mt-2 text-3xl font-semibold text-white">Registratie via display</h2>
                <p class="mt-2 text-base text-slate-400">Vul eerst de gegevens in en kies daarna het kaartdesign.</p>
            </div>

            <div class="inline-flex rounded-full border border-slate-700 bg-slate-950/70 p-1">
                <div class="rounded-full px-4 py-2 text-sm font-semibold" :class="step === 1 ? 'bg-cyan-500/20 text-cyan-200' : 'text-slate-500'">Gegevens</div>
                <div class="rounded-full px-4 py-2 text-sm font-semibold" :class="step === 2 ? 'bg-cyan-500/20 text-cyan-200' : 'text-slate-500'">Kaartdesign</div>
            </div>
        </div>

        <div class="min-h-0 flex-1 overflow-y-auto px-8 py-6">
            <div v-if="success" class="flex h-full min-h-[480px] flex-col items-center justify-center text-center">
                <div class="flex h-24 w-24 items-center justify-center rounded-full bg-emerald-500/15 text-emerald-300">
                    <svg class="h-12 w-12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" />
                    </svg>
                </div>
                <h3 class="mt-6 text-3xl font-semibold text-white">Lid succesvol aangemaakt</h3>
                <p class="mt-3 max-w-2xl text-lg text-slate-300">{{ successMessage }}</p>
            </div>

            <div v-else-if="step === 1" class="grid gap-8 xl:grid-cols-[minmax(0,1.4fr)_minmax(0,1fr)]">
                <section class="rounded-3xl border border-slate-800 bg-slate-950/70 p-6">
                    <div class="hidden" aria-hidden="true">
                        <input type="text" name="display_username_decoy" autocomplete="username">
                        <input type="password" name="display_password_decoy" autocomplete="new-password">
                    </div>

                    <h3 class="text-xl font-semibold text-white">Gegevens</h3>
                    <div class="mt-5 grid gap-5 md:grid-cols-2">
                        <label class="space-y-2 text-sm text-slate-300">
                            <span>Voornaam *</span>
                            <input :value="localForm.first_name" type="text" :class="fieldClass" @input="updateField('first_name', $event.target.value)">
                        </label>

                        <label class="space-y-2 text-sm text-slate-300">
                            <span>Naam *</span>
                            <input :value="localForm.last_name" type="text" :class="fieldClass" @input="updateField('last_name', $event.target.value)">
                        </label>

                        <label class="space-y-2 text-sm text-slate-300">
                            <span>E-mail *</span>
                            <input
                                :value="localForm.email"
                                type="text"
                                inputmode="email"
                                name="display_member_contact_email"
                                autocomplete="off"
                                autocapitalize="off"
                                autocorrect="off"
                                spellcheck="false"
                                data-form-type="other"
                                data-lpignore="true"
                                data-1p-ignore="true"
                                data-bwignore="true"
                                :class="fieldClass"
                                @input="updateField('email', $event.target.value)"
                            >
                        </label>

                        <label class="space-y-2 text-sm text-slate-300">
                            <span>Telefoon</span>
                            <input
                                :value="localForm.phone"
                                type="text"
                                inputmode="tel"
                                name="display_member_contact_phone"
                                autocomplete="off"
                                autocapitalize="off"
                                autocorrect="off"
                                spellcheck="false"
                                data-form-type="other"
                                data-lpignore="true"
                                data-1p-ignore="true"
                                data-bwignore="true"
                                :class="fieldClass"
                                @input="updateField('phone', $event.target.value)"
                            >
                        </label>

                        <label class="space-y-2 text-sm text-slate-300">
                            <span>Paswoord *</span>
                            <input
                                :value="localForm.password"
                                type="password"
                                name="display_member_secret_a"
                                autocomplete="new-password"
                                autocapitalize="off"
                                autocorrect="off"
                                spellcheck="false"
                                data-form-type="other"
                                data-lpignore="true"
                                data-1p-ignore="true"
                                data-bwignore="true"
                                :class="fieldClass"
                                @input="updateField('password', $event.target.value)"
                            >
                        </label>

                        <label class="space-y-2 text-sm text-slate-300">
                            <span>Herhaal paswoord *</span>
                            <input
                                :value="localForm.password_confirmation"
                                type="password"
                                name="display_member_secret_b"
                                autocomplete="new-password"
                                autocapitalize="off"
                                autocorrect="off"
                                spellcheck="false"
                                data-form-type="other"
                                data-lpignore="true"
                                data-1p-ignore="true"
                                data-bwignore="true"
                                :class="fieldClass"
                                @input="updateField('password_confirmation', $event.target.value)"
                            >
                        </label>

                        <label class="space-y-2 text-sm text-slate-300">
                            <span>Geboortedatum</span>
                            <input :value="localForm.birth_date" type="date" :class="fieldClass" @input="updateField('birth_date', $event.target.value)">
                        </label>

                        <label class="space-y-2 text-sm text-slate-300">
                            <span>Type</span>
                            <select :value="localForm.type" :class="fieldClass" @change="updateField('type', $event.target.value)">
                                <option value="adult">Volwassen</option>
                                <option value="student">Student</option>
                            </select>
                        </label>
                    </div>
                </section>

                <section class="rounded-3xl border border-slate-800 bg-slate-950/70 p-6">
                    <h3 class="text-xl font-semibold text-white">Adres</h3>
                    <div class="mt-5 grid gap-5 md:grid-cols-[minmax(0,1fr)_120px]">
                        <label class="space-y-2 text-sm text-slate-300">
                            <span>Straat</span>
                            <input :value="localForm.street" type="text" :class="fieldClass" @input="updateField('street', $event.target.value)">
                        </label>

                        <label class="space-y-2 text-sm text-slate-300">
                            <span>Nr</span>
                            <input :value="localForm.house_number" type="text" :class="fieldClass" @input="updateField('house_number', $event.target.value)">
                        </label>
                    </div>

                    <div class="mt-5 grid gap-5 md:grid-cols-[180px_minmax(0,1fr)]">
                        <label class="space-y-2 text-sm text-slate-300">
                            <span>Postcode</span>
                            <input :value="localForm.postal_code" type="text" :class="fieldClass" @input="updateField('postal_code', $event.target.value)">
                        </label>

                        <label class="space-y-2 text-sm text-slate-300">
                            <span>Gemeente</span>
                            <input :value="localForm.city" type="text" :class="fieldClass" @input="updateField('city', $event.target.value)">
                        </label>
                    </div>
                </section>
            </div>

            <div v-else class="space-y-6">
                <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
                    <button
                        v-for="template in displayTemplates"
                        :key="template.id"
                        type="button"
                        class="rounded-3xl border p-5 text-left transition"
                        :class="template.id === localForm.badge_template_id ? 'border-cyan-400 bg-cyan-500/10 text-white shadow-lg shadow-cyan-950/40' : 'border-slate-800 bg-slate-950/70 text-slate-300 hover:border-slate-700 hover:bg-slate-900'"
                        @click="updateField('badge_template_id', template.id)"
                    >
                        <div class="relative aspect-[1.58/1] overflow-hidden rounded-2xl border border-slate-700/80 p-4" :class="template.previewClass">
                            <div class="absolute inset-0 opacity-20" :class="template.patternClass"></div>

                            <div class="relative flex h-full flex-col justify-between">
                                <div class="flex items-start justify-between gap-3">
                                    <div>
                                        <div class="text-[10px] font-semibold uppercase tracking-[0.3em]" :class="template.accentClass">Game-Inn</div>
                                        <div class="mt-2 text-lg font-semibold text-white">{{ template.name }}</div>
                                    </div>

                                    <div class="rounded-full border border-white/15 bg-white/10 px-3 py-1 text-[10px] font-semibold uppercase tracking-[0.25em] text-white/80">
                                        Member
                                    </div>
                                </div>

                                <div>
                                    <div class="text-sm font-medium text-white/90">{{ previewMemberName }}</div>
                                    <div class="mt-1 text-xs text-white/60">{{ template.description }}</div>
                                </div>
                            </div>
                        </div>
                    </button>
                </div>

                <div class="rounded-3xl border border-slate-800 bg-slate-950/50 px-5 py-4 text-sm text-slate-400">
                    Kies een badge design om verder te gaan.
                </div>
            </div>
        </div>

        <div class="border-t border-slate-800 px-8 py-5">
            <div v-if="error" class="mb-4 rounded-2xl border border-rose-500/30 bg-rose-500/10 px-4 py-3 text-base text-rose-200">{{ error }}</div>

            <div class="flex items-center justify-between gap-4">
                <button
                    type="button"
                    class="rounded-2xl border border-slate-700 bg-slate-800 px-5 py-3 text-base font-semibold text-slate-200 transition hover:bg-slate-700 disabled:opacity-40"
                    :disabled="step === 1 || saving || success"
                    @click="$emit('previous')"
                >
                    Vorige
                </button>

                <div class="flex items-center gap-3">
                    <button
                        type="button"
                        class="rounded-2xl border border-slate-700 bg-slate-800 px-5 py-3 text-base font-semibold text-slate-200 transition hover:bg-slate-700 disabled:opacity-40"
                        :disabled="saving"
                        @click="$emit('cancel')"
                    >
                        Annuleren
                    </button>

                    <button
                        v-if="step === 1 && !success"
                        type="button"
                        class="rounded-2xl bg-cyan-600 px-5 py-3 text-base font-semibold text-white transition hover:bg-cyan-500"
                        @click="$emit('next')"
                    >
                        Volgende
                    </button>

                    <button
                        v-else-if="!success"
                        type="button"
                        class="rounded-2xl bg-emerald-600 px-5 py-3 text-base font-semibold text-white transition hover:bg-emerald-500 disabled:opacity-50"
                        :disabled="saving || !localForm.badge_template_id"
                        @click="$emit('save')"
                    >
                        {{ saving ? 'Bezig met opslaan...' : 'Opslaan' }}
                    </button>
                </div>
            </div>
        </div>
    </div>
</template>

<script setup>
import { computed, reactive, watch } from 'vue'

const props = defineProps({
    form: { type: Object, required: true },
    step: { type: Number, default: 1 },
    templates: { type: Array, default: () => [] },
    saving: { type: Boolean, default: false },
    error: { type: String, default: '' },
    success: { type: Boolean, default: false },
    successMessage: { type: String, default: '' },
})

const emit = defineEmits(['update', 'next', 'previous', 'cancel', 'save'])

const fieldClass = 'w-full rounded-2xl border border-slate-700 bg-slate-900 px-4 py-3 text-base text-white outline-none transition focus:border-cyan-400'
const localForm = reactive(createFormState(props.form))
const dirtyFields = new Set()

const fallbackTemplates = [
    {
        id: 'fallback-neon',
        name: 'Neon',
        description: 'Donkere kaart met subtiele cyan-accenten.',
        previewClass: 'bg-gradient-to-br from-slate-900 via-cyan-950 to-slate-950',
        patternClass: 'bg-[radial-gradient(circle_at_top_right,rgba(34,211,238,0.45),transparent_35%),radial-gradient(circle_at_bottom_left,rgba(59,130,246,0.25),transparent_30%)]',
        accentClass: 'text-cyan-300/90',
    },
    {
        id: 'fallback-arcade',
        name: 'Arcade',
        description: 'Warme retro look met arcade-sfeer.',
        previewClass: 'bg-gradient-to-br from-fuchsia-950 via-slate-900 to-amber-950',
        patternClass: 'bg-[linear-gradient(135deg,rgba(244,114,182,0.18),transparent_35%),linear-gradient(315deg,rgba(251,191,36,0.18),transparent_35%)]',
        accentClass: 'text-amber-300/90',
    },
    {
        id: 'fallback-clean',
        name: 'Minimal',
        description: 'Strakke badge met rustige premium uitstraling.',
        previewClass: 'bg-gradient-to-br from-slate-800 via-slate-900 to-black',
        patternClass: 'bg-[linear-gradient(90deg,rgba(255,255,255,0.06),transparent_30%,rgba(255,255,255,0.04))]',
        accentClass: 'text-emerald-300/90',
    },
]

const displayTemplates = computed(() => {
    if (Array.isArray(props.templates) && props.templates.length) {
        return props.templates.map((template, index) => ({
            id: template.id ?? `template-${index}`,
            name: template.name ?? template.title ?? `Design ${index + 1}`,
            description: template.description ?? 'Member badge design',
            previewClass: 'bg-gradient-to-br from-slate-800 via-slate-900 to-slate-950',
            patternClass: 'bg-[radial-gradient(circle_at_top_right,rgba(34,211,238,0.28),transparent_32%),linear-gradient(135deg,rgba(255,255,255,0.06),transparent_40%)]',
            accentClass: 'text-cyan-300/90',
            ...template,
        }))
    }

    return fallbackTemplates
})

const previewMemberName = computed(() => {
    const fullName = [localForm.first_name, localForm.last_name].filter(Boolean).join(' ').trim()
    return fullName || 'Nieuw lid'
})

watch(
    () => props.form,
    (nextForm) => {
        const nextState = createFormState(nextForm)

        Object.keys(nextState).forEach((key) => {
            if (!dirtyFields.has(key)) {
                localForm[key] = nextState[key]
            }
        })
    },
    { deep: true, immediate: true },
)

watch(
    () => props.success,
    (isSuccess) => {
        if (isSuccess) {
            dirtyFields.clear()
        }
    },
)

watch(
    displayTemplates,
    (templates) => {
        if (!localForm.badge_template_id && templates.length) {
            localForm.badge_template_id = templates[0].id
            emit('update', { field: 'badge_template_id', value: templates[0].id })
        }
    },
    { immediate: true },
)

function createFormState(source = {}) {
    return {
        first_name: source?.first_name ?? '',
        last_name: source?.last_name ?? '',
        email: source?.email ?? '',
        phone: source?.phone ?? '',
        password: source?.password ?? '',
        password_confirmation: source?.password_confirmation ?? '',
        birth_date: source?.birth_date ?? '',
        type: source?.type ?? 'adult',
        street: source?.street ?? '',
        house_number: source?.house_number ?? '',
        postal_code: source?.postal_code ?? '',
        city: source?.city ?? '',
        badge_template_id: source?.badge_template_id ?? null,
    }
}

function updateField(field, value) {
    localForm[field] = value
    dirtyFields.add(field)
    emit('update', { field, value })
}
</script>
