<template>
    <div
        v-if="open"
        class="fixed inset-0 z-[100] flex items-center justify-center bg-black/70 p-6 backdrop-blur-sm"
        @click.self="$emit('close')"
    >
        <div class="flex max-h-[90vh] w-full max-w-5xl flex-col overflow-hidden rounded-3xl border border-slate-800 bg-slate-900 shadow-2xl">
            <div class="flex items-center justify-between border-b border-slate-800 px-6 py-4">
                <h2 class="text-xl font-semibold text-slate-100">
                    {{ form.id ? 'Abonnee bewerken' : 'Nieuwe abonnee' }}
                </h2>

                <button
                    type="button"
                    class="inline-flex h-10 w-10 items-center justify-center rounded-xl text-slate-400 transition hover:bg-slate-800 hover:text-white"
                    @click="$emit('close')"
                >
                    <svg class="h-5 w-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12" />
                    </svg>
                </button>
            </div>

            <form class="min-h-0 flex-1 overflow-y-auto bg-slate-950 p-6" @submit.prevent="submitForm">
                <div class="grid gap-5 lg:grid-cols-2">
                    <label class="space-y-2 text-sm text-slate-300">
                        <span>Voornaam *</span>
                        <input v-model="form.first_name" type="text" :class="fieldClass">
                    </label>

                    <label class="space-y-2 text-sm text-slate-300">
                        <span>Naam *</span>
                        <input v-model="form.last_name" type="text" :class="fieldClass">
                    </label>

                    <label class="space-y-2 text-sm text-slate-300">
                        <span>E-mail</span>
                        <input v-model="form.email" type="email" :class="fieldClass">
                    </label>

                    <label class="space-y-2 text-sm text-slate-300">
                        <span>Login</span>
                        <input v-model="form.username" type="text" :class="fieldClass">
                    </label>

                    <label class="space-y-2 text-sm text-slate-300">
                        <span>{{ form.id ? 'Nieuw paswoord (optioneel)' : 'Paswoord' }}</span>
                        <input v-model="form.password" type="password" :class="fieldClass">
                    </label>

                    <label class="space-y-2 text-sm text-slate-300">
                        <span>RFID kaart</span>
                        <div class="flex gap-3">
                            <input v-model="form.rfid_uid" type="text" :class="[fieldClass, 'flex-1']" ref="rfidInput">
                            <button
                                type="button"
                                class="rounded-2xl border border-slate-700 bg-slate-800 px-4 py-3 text-sm font-semibold text-slate-200 transition hover:bg-slate-700"
                                @click="focusRfid"
                            >
                                Koppelen
                            </button>
                        </div>
                    </label>

                    <label class="space-y-2 text-sm text-slate-300 lg:col-span-2">
                        <span>Straat</span>
                        <input v-model="form.street" type="text" :class="fieldClass">
                    </label>

                    <label class="space-y-2 text-sm text-slate-300">
                        <span>Huisnummer</span>
                        <input v-model="form.house_number" type="text" :class="fieldClass">
                    </label>

                    <label class="space-y-2 text-sm text-slate-300">
                        <span>Bus</span>
                        <input v-model="form.bus" type="text" :class="fieldClass">
                    </label>

                    <label class="space-y-2 text-sm text-slate-300">
                        <span>Postcode</span>
                        <input v-model="form.postal_code" type="text" :class="fieldClass">
                    </label>

                    <label class="space-y-2 text-sm text-slate-300">
                        <span>Gemeente</span>
                        <input v-model="form.city" type="text" :class="fieldClass">
                    </label>

                    <label class="space-y-2 text-sm text-slate-300">
                        <span>Geldig van</span>
                        <input v-model="form.membership_started_at" type="date" :class="fieldClass">
                    </label>

                    <label class="space-y-2 text-sm text-slate-300">
                        <span>Geldig tot</span>
                        <input v-model="form.membership_expires_at" type="date" :class="fieldClass">
                    </label>

                    <label class="space-y-2 text-sm text-slate-300 lg:col-span-2">
                        <span>Commentaar</span>
                        <textarea v-model="form.comment" rows="4" :class="fieldClass"></textarea>
                    </label>

                    <label class="inline-flex items-center gap-3 text-sm text-slate-300 lg:col-span-2">
                        <input v-model="form.is_active" type="checkbox" class="h-4 w-4 rounded border-slate-700 bg-slate-900 text-blue-600">
                        <span>Abonnement actief</span>
                    </label>
                </div>

                <div class="mt-6 flex justify-end gap-3 border-t border-slate-800 pt-6">
                    <button
                        type="button"
                        class="rounded-2xl border border-slate-700 bg-slate-800 px-4 py-3 text-sm font-semibold text-slate-200 transition hover:bg-slate-700"
                        @click="$emit('close')"
                    >
                        Annuleren
                    </button>
                    <button
                        type="submit"
                        class="rounded-2xl bg-blue-600 px-5 py-3 text-sm font-semibold text-white transition hover:bg-blue-500"
                    >
                        Opslaan
                    </button>
                </div>
            </form>
        </div>
    </div>
</template>

<script setup>
import { computed, ref, watch } from 'vue'

const props = defineProps({
    open: {
        type: Boolean,
        default: false,
    },
    member: {
        type: Object,
        default: null,
    },
})

const emit = defineEmits(['close', 'submit'])
const rfidInput = ref(null)

function defaultDates() {
    const now = new Date()
    const start = now.toISOString().slice(0, 10)
    const expires = new Date(now)
    expires.setFullYear(expires.getFullYear() + 1)
    return {
        membership_started_at: start,
        membership_expires_at: expires.toISOString().slice(0, 10),
    }
}

const form = ref({
    id: null,
    first_name: '',
    last_name: '',
    email: '',
    username: '',
    password: '',
    street: '',
    house_number: '',
    bus: '',
    postal_code: '',
    city: '',
    rfid_uid: '',
    comment: '',
    membership_started_at: defaultDates().membership_started_at,
    membership_expires_at: defaultDates().membership_expires_at,
    is_active: true,
})

const normalizedMember = computed(() => props.member ?? {})

watch(
    () => [props.open, normalizedMember.value],
    () => {
        const dates = defaultDates()
        form.value = {
            id: normalizedMember.value.id ?? null,
            first_name: normalizedMember.value.first_name ?? '',
            last_name: normalizedMember.value.last_name ?? '',
            email: normalizedMember.value.email ?? '',
            username: normalizedMember.value.username ?? '',
            password: '',
            street: normalizedMember.value.street ?? '',
            house_number: normalizedMember.value.house_number ?? '',
            bus: normalizedMember.value.bus ?? '',
            postal_code: normalizedMember.value.postal_code ?? '',
            city: normalizedMember.value.city ?? '',
            rfid_uid: normalizedMember.value.rfid_uid ?? '',
            comment: normalizedMember.value.comment ?? '',
            membership_started_at: normalizedMember.value.membership_started_at ?? dates.membership_started_at,
            membership_expires_at: normalizedMember.value.membership_expires_at ?? dates.membership_expires_at,
            is_active: normalizedMember.value.is_active ?? true,
        }
    },
    { immediate: true }
)

function focusRfid() {
    rfidInput.value?.focus()
}

function submitForm() {
    emit('submit', { ...form.value })
}


const fieldClass = 'w-full rounded-2xl border border-slate-700 bg-slate-900 px-4 py-3 text-white outline-none transition focus:border-blue-500'
</script>
