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
                    <h3 class="text-xl font-semibold text-white">Gegevens</h3>
                    <div class="mt-5 grid gap-5 md:grid-cols-2">
                        <label class="space-y-2 text-sm text-slate-300"><span>Voornaam *</span><input :value="form.first_name" type="text" :class="fieldClass" @input="update('first_name', $event.target.value)"></label>
                        <label class="space-y-2 text-sm text-slate-300"><span>Naam *</span><input :value="form.last_name" type="text" :class="fieldClass" @input="update('last_name', $event.target.value)"></label>
                        <label class="space-y-2 text-sm text-slate-300"><span>E-mail *</span><input :value="form.email" type="email" :class="fieldClass" @input="update('email', $event.target.value)"></label>
                        <label class="space-y-2 text-sm text-slate-300"><span>Telefoon</span><input :value="form.phone" type="text" :class="fieldClass" @input="update('phone', $event.target.value)"></label>
                        <label class="space-y-2 text-sm text-slate-300"><span>Paswoord *</span><input :value="form.password" type="password" :class="fieldClass" @input="update('password', $event.target.value)"></label>
                        <label class="space-y-2 text-sm text-slate-300"><span>Herhaal paswoord *</span><input :value="form.password_confirmation" type="password" :class="fieldClass" @input="update('password_confirmation', $event.target.value)"></label>
                        <label class="space-y-2 text-sm text-slate-300"><span>Geboortedatum</span><input :value="form.birth_date" type="date" :class="fieldClass" @input="update('birth_date', $event.target.value)"></label>
                        <label class="space-y-2 text-sm text-slate-300">
                            <span>Type</span>
                            <select :value="form.type" :class="fieldClass" @change="update('type', $event.target.value)">
                                <option value="adult">Volwassen</option>
                                <option value="student">Student</option>
                            </select>
                        </label>
                    </div>
                </section>

                <section class="rounded-3xl border border-slate-800 bg-slate-950/70 p-6">
                    <h3 class="text-xl font-semibold text-white">Adres</h3>
                    <div class="mt-5 grid gap-5 md:grid-cols-[minmax(0,1fr)_120px]">
                        <label class="space-y-2 text-sm text-slate-300"><span>Straat</span><input :value="form.street" type="text" :class="fieldClass" @input="update('street', $event.target.value)"></label>
                        <label class="space-y-2 text-sm text-slate-300"><span>Nr</span><input :value="form.house_number" type="text" :class="fieldClass" @input="update('house_number', $event.target.value)"></label>
                    </div>
                    <div class="mt-5 grid gap-5 md:grid-cols-[180px_minmax(0,1fr)]">
                        <label class="space-y-2 text-sm text-slate-300"><span>Postcode</span><input :value="form.postal_code" type="text" :class="fieldClass" @input="update('postal_code', $event.target.value)"></label>
                        <label class="space-y-2 text-sm text-slate-300"><span>Gemeente</span><input :value="form.city" type="text" :class="fieldClass" @input="update('city', $event.target.value)"></label>
                    </div>
                </section>
            </div>

            <div v-else class="space-y-6">
                <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
                    <button
                        v-for="template in templates"
                        :key="template.id"
                        type="button"
                        class="rounded-3xl border p-6 text-left transition"
                        :class="template.id === form.badge_template_id ? 'border-cyan-400 bg-cyan-500/10 text-white shadow-lg shadow-cyan-950/40' : 'border-slate-800 bg-slate-950/70 text-slate-300 hover:border-slate-700 hover:bg-slate-900'"
                        @click="update('badge_template_id', template.id)"
                    >
                        <div class="aspect-[1.58/1] rounded-2xl border border-slate-700/80 bg-gradient-to-br from-slate-800 to-slate-950 p-4">
                            <div class="text-[11px] font-semibold uppercase tracking-[0.28em] text-cyan-300/80">Kaart design</div>
                            <div class="mt-6 text-xl font-semibold text-white">{{ template.name }}</div>
                            <div class="mt-3 text-sm text-slate-300">{{ template.description || 'Member badge design' }}</div>
                        </div>
                    </button>
                </div>

                <div v-if="!templates.length" class="rounded-3xl border border-dashed border-slate-700 bg-slate-950/70 px-6 py-8 text-center text-slate-400">
                    Er zijn nog geen kaartdesigns beschikbaar.
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
                        :disabled="saving || !form.badge_template_id"
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
defineProps({
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

function update(field, value) {
    emit('update', { field, value })
}
</script>
