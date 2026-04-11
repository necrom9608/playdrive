<template>
    <div class="grid gap-5 lg:grid-cols-2">
        <label class="space-y-2 text-sm text-slate-300">
            <span>Voornaam *</span>
            <input :value="form.first_name" type="text" :class="fieldClass" @input="update('first_name', $event.target.value)">
        </label>

        <label class="space-y-2 text-sm text-slate-300">
            <span>Naam *</span>
            <input :value="form.last_name" type="text" :class="fieldClass" @input="update('last_name', $event.target.value)">
        </label>

        <label class="space-y-2 text-sm text-slate-300">
            <span>E-mail</span>
            <input :value="form.email" type="email" :class="fieldClass" @input="update('email', $event.target.value)">
        </label>

        <label class="space-y-2 text-sm text-slate-300">
            <span>Telefoon</span>
            <input :value="form.phone" type="text" :class="fieldClass" @input="update('phone', $event.target.value)">
        </label>

        <label class="space-y-2 text-sm text-slate-300">
            <span>{{ form.id ? 'Nieuw paswoord (optioneel)' : 'Paswoord' }}</span>
            <input :value="form.password" type="password" :class="fieldClass" @input="update('password', $event.target.value)">
        </label>

        <label class="space-y-2 text-sm text-slate-300">
            <span>Geboortedatum</span>
            <input :value="form.birth_date" type="date" :class="fieldClass" @input="update('birth_date', $event.target.value)">
        </label>

        <label class="space-y-2 text-sm text-slate-300">
            <span>Type</span>
            <select :value="form.membership_type" :class="fieldClass" @change="update('membership_type', $event.target.value)">
                <option value="adult">Volwassen</option>
                <option value="student">Student</option>
            </select>
        </label>

        <div class="rounded-2xl border border-slate-800 bg-slate-900/60 p-4 text-sm text-slate-300">
            <div class="text-xs uppercase tracking-[0.2em] text-slate-500">Abonnee</div>
            <div class="mt-2 text-lg font-semibold text-white">
                {{ form.id ? `#${form.id}` : 'Nieuw lid' }}
            </div>
            <div class="mt-1 text-slate-400">
                {{ form.first_name || 'Voornaam' }} {{ form.last_name || 'Naam' }}
            </div>
        </div>

        <label class="space-y-2 text-sm text-slate-300">
            <span>Geldig van</span>
            <input :value="form.membership_starts_at" type="date" :class="fieldClass" @input="update('membership_starts_at', $event.target.value)">
        </label>

        <label class="space-y-2 text-sm text-slate-300">
            <span>Geldig tot</span>
            <input :value="form.membership_ends_at" type="date" :class="fieldClass" @input="update('membership_ends_at', $event.target.value)">
        </label>

        <label class="space-y-2 text-sm text-slate-300 lg:col-span-2">
            <span>Login</span>
            <input :value="form.email" type="text" :class="fieldClass" disabled>
        </label>

        <label class="space-y-2 text-sm text-slate-300 lg:col-span-2">
            <span>Commentaar</span>
            <textarea :value="form.comment" rows="5" :class="fieldClass" @input="update('comment', $event.target.value)"></textarea>
        </label>

        <label class="inline-flex items-center gap-3 text-sm text-slate-300 lg:col-span-2">
            <input :checked="form.is_active" type="checkbox" class="h-4 w-4 rounded border-slate-700 bg-slate-900 text-blue-600" @change="update('is_active', $event.target.checked)">
            <span>Abonnement actief</span>
        </label>
    </div>
</template>

<script setup>
defineProps({
    form: { type: Object, required: true },
    fieldClass: { type: String, required: true },
})

const emit = defineEmits(['update'])

function update(field, value) {
    emit('update', { field, value })
}
</script>
