<template>
    <div
        v-if="open"
        class="fixed inset-0 z-[100] flex items-center justify-center bg-black/70 p-6 backdrop-blur-sm"
        @click.self="$emit('close')"
    >
        <div class="flex max-h-[90vh] w-full max-w-6xl flex-col overflow-hidden rounded-3xl border border-slate-800 bg-slate-900 shadow-2xl">
            <div class="flex items-center justify-between border-b border-slate-800 px-6 py-4">
                <div>
                    <h2 class="text-xl font-semibold text-slate-100">
                        {{ form.id ? 'Abonnee bewerken' : 'Nieuwe abonnee' }}
                    </h2>
                    <p class="mt-1 text-sm text-slate-400">
                        Beheer standaardgegevens, adres en kaartinstellingen in aparte onderdelen.
                    </p>
                </div>

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

            <form class="min-h-0 flex-1 overflow-hidden bg-slate-950" @submit.prevent="submitForm">
                <div class="border-b border-slate-800 px-6 pt-4">
                    <div class="flex flex-wrap gap-2">
                        <button
                            v-for="tab in tabs"
                            :key="tab.value"
                            type="button"
                            class="rounded-t-2xl border border-b-0 px-4 py-3 text-sm font-semibold transition"
                            :class="activeTab === tab.value ? 'border-slate-700 bg-slate-900 text-white' : 'border-transparent bg-slate-950 text-slate-400 hover:bg-slate-900/70 hover:text-slate-200'"
                            @click="activeTab = tab.value"
                        >
                            {{ tab.label }}
                        </button>
                    </div>
                </div>

                <div class="min-h-0 overflow-y-auto p-6">
                    <MemberStandardFields
                        v-if="activeTab === 'standard'"
                        :form="form"
                        :field-class="fieldClass"
                        @update="updateField"
                    />

                    <MemberAddressFields
                        v-else-if="activeTab === 'address'"
                        :form="form"
                        :field-class="fieldClass"
                        @update="updateField"
                    />

                    <MemberCardFields
                        v-else
                        :form="form"
                        :templates="badgeTemplates"
                        :field-class="fieldClass"
                        @update="updateField"
                    />
                </div>

                <div class="flex justify-end gap-3 border-t border-slate-800 px-6 py-5">
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
import MemberStandardFields from './sections/MemberStandardFields.vue'
import MemberAddressFields from './sections/MemberAddressFields.vue'
import MemberCardFields from './sections/MemberCardFields.vue'

const props = defineProps({
    open: {
        type: Boolean,
        default: false,
    },
    member: {
        type: Object,
        default: null,
    },
    badgeTemplates: {
        type: Array,
        default: () => [],
    },
})

const emit = defineEmits(['close', 'submit'])

const tabs = [
    { value: 'standard', label: 'Standaard gegevens' },
    { value: 'address', label: 'Adres gegevens' },
    { value: 'card', label: 'Kaart' },
]

const activeTab = ref('standard')

function defaultDates() {
    const now = new Date()
    const start = now.toISOString().slice(0, 10)
    const ends = new Date(now)
    ends.setFullYear(ends.getFullYear() + 1)

    return {
        membership_starts_at: start,
        membership_ends_at: ends.toISOString().slice(0, 10),
    }
}

function defaultBadgeTemplateId() {
    return props.badgeTemplates.find(template => template.is_default)?.id ?? props.badgeTemplates[0]?.id ?? null
}

const form = ref({})
const normalizedMember = computed(() => props.member ?? {})

watch(
    () => [props.open, normalizedMember.value, props.badgeTemplates],
    () => {
        const dates = defaultDates()
        activeTab.value = 'standard'

        form.value = {
            id: normalizedMember.value.id ?? null,
            first_name: normalizedMember.value.first_name ?? '',
            last_name: normalizedMember.value.last_name ?? '',
            email: normalizedMember.value.email ?? '',
            login: normalizedMember.value.login ?? '',
            password: '',
            street: normalizedMember.value.street ?? '',
            house_number: normalizedMember.value.house_number ?? '',
            box: normalizedMember.value.box ?? '',
            postal_code: normalizedMember.value.postal_code ?? '',
            city: normalizedMember.value.city ?? '',
            country: normalizedMember.value.country ?? 'BE',
            rfid_uid: normalizedMember.value.rfid_uid ?? '',
            comment: normalizedMember.value.comment ?? '',
            membership_starts_at: normalizedMember.value.membership_starts_at ?? dates.membership_starts_at,
            membership_ends_at: normalizedMember.value.membership_ends_at ?? dates.membership_ends_at,
            is_active: normalizedMember.value.is_active ?? true,
            badge_template_id: normalizedMember.value.member_badge_template_id ?? defaultBadgeTemplateId(),
            member_card_id: normalizedMember.value.member_card_id ?? null,
            member_card_label: normalizedMember.value.member_card_label ?? '',
            member_card_badge_template_name: normalizedMember.value.member_card_badge_template_name ?? '',
        }
    },
    { immediate: true }
)

function updateField({ field, value }) {
    form.value = {
        ...form.value,
        [field]: value,
    }
}

function submitForm() {
    emit('submit', { ...form.value })
}

const fieldClass = 'w-full rounded-2xl border border-slate-700 bg-slate-900 px-4 py-3 text-white outline-none transition focus:border-blue-500'
</script>
