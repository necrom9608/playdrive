<template>
    <teleport to="body">
        <div v-if="open" class="fixed inset-0 z-50 flex items-center justify-center p-4">
            <div class="absolute inset-0 bg-slate-950/80" @click="$emit('close')" />

            <div class="relative z-10 max-h-[90vh] w-full max-w-2xl overflow-hidden rounded-3xl border border-slate-800 bg-slate-900 shadow-2xl">
                <div class="flex items-center justify-between border-b border-slate-800 px-6 py-4">
                    <div>
                        <h2 class="text-lg font-semibold text-white">{{ isEditing ? 'Tenant bewerken' : 'Nieuwe tenant' }}</h2>
                        <p class="mt-1 text-sm text-slate-400">Beheer de basisgegevens van deze tenant.</p>
                    </div>
                    <button
                        type="button"
                        class="inline-flex h-10 w-10 items-center justify-center rounded-xl border border-slate-700 text-slate-300 transition hover:bg-slate-800 hover:text-white"
                        @click="$emit('close')"
                    >
                        <svg xmlns="http://www.w3.org/2000/svg" class="h-5 w-5" viewBox="0 0 20 20" fill="currentColor">
                            <path fill-rule="evenodd" d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z" clip-rule="evenodd" />
                        </svg>
                    </button>
                </div>

                <div class="max-h-[calc(90vh-88px)] overflow-y-auto p-6">
                    <div
                        v-if="error"
                        class="mb-4 rounded-2xl border border-red-800 bg-red-950/40 px-4 py-3 text-sm text-red-300"
                    >
                        {{ error }}
                    </div>

                    <div class="space-y-4">
                        <div class="grid gap-4 md:grid-cols-2">
                            <div>
                                <label class="mb-1 block text-sm font-medium text-slate-300">Naam *</label>
                                <input v-model="form.name" type="text" class="w-full rounded-xl border border-slate-700 bg-slate-950 px-4 py-3 text-white outline-none transition focus:border-cyan-500" />
                            </div>
                            <div>
                                <label class="mb-1 block text-sm font-medium text-slate-300">Bedrijfsnaam</label>
                                <input v-model="form.company_name" type="text" class="w-full rounded-xl border border-slate-700 bg-slate-950 px-4 py-3 text-white outline-none transition focus:border-cyan-500" />
                            </div>
                        </div>

                        <div class="grid gap-4 md:grid-cols-2">
                            <div>
                                <label class="mb-1 block text-sm font-medium text-slate-300">Slug *</label>
                                <input v-model="form.slug" type="text" class="w-full rounded-xl border border-slate-700 bg-slate-950 px-4 py-3 text-white outline-none transition focus:border-cyan-500" />
                            </div>
                            <div>
                                <label class="mb-1 block text-sm font-medium text-slate-300">E-mail</label>
                                <input v-model="form.email" type="email" class="w-full rounded-xl border border-slate-700 bg-slate-950 px-4 py-3 text-white outline-none transition focus:border-cyan-500" />
                            </div>
                        </div>

                        <div class="grid gap-4 md:grid-cols-2">
                            <div>
                                <label class="mb-1 block text-sm font-medium text-slate-300">Telefoon</label>
                                <input v-model="form.phone" type="text" class="w-full rounded-xl border border-slate-700 bg-slate-950 px-4 py-3 text-white outline-none transition focus:border-cyan-500" />
                            </div>
                            <div>
                                <label class="mb-1 block text-sm font-medium text-slate-300">BTW-nummer</label>
                                <input v-model="form.vat_number" type="text" class="w-full rounded-xl border border-slate-700 bg-slate-950 px-4 py-3 text-white outline-none transition focus:border-cyan-500" />
                            </div>
                        </div>

                        <div class="grid gap-4 md:grid-cols-[1fr_140px]">
                            <div>
                                <label class="mb-1 block text-sm font-medium text-slate-300">Straat</label>
                                <input v-model="form.street" type="text" class="w-full rounded-xl border border-slate-700 bg-slate-950 px-4 py-3 text-white outline-none transition focus:border-cyan-500" />
                            </div>
                            <div>
                                <label class="mb-1 block text-sm font-medium text-slate-300">Nummer</label>
                                <input v-model="form.number" type="text" class="w-full rounded-xl border border-slate-700 bg-slate-950 px-4 py-3 text-white outline-none transition focus:border-cyan-500" />
                            </div>
                        </div>

                        <div class="grid gap-4 md:grid-cols-3">
                            <div>
                                <label class="mb-1 block text-sm font-medium text-slate-300">Postcode</label>
                                <input v-model="form.postal_code" type="text" class="w-full rounded-xl border border-slate-700 bg-slate-950 px-4 py-3 text-white outline-none transition focus:border-cyan-500" />
                            </div>
                            <div>
                                <label class="mb-1 block text-sm font-medium text-slate-300">Stad</label>
                                <input v-model="form.city" type="text" class="w-full rounded-xl border border-slate-700 bg-slate-950 px-4 py-3 text-white outline-none transition focus:border-cyan-500" />
                            </div>
                            <div>
                                <label class="mb-1 block text-sm font-medium text-slate-300">Land</label>
                                <input v-model="form.country" type="text" class="w-full rounded-xl border border-slate-700 bg-slate-950 px-4 py-3 text-white outline-none transition focus:border-cyan-500" />
                            </div>
                        </div>

                        <div>
                            <label class="mb-1 block text-sm font-medium text-slate-300">Receipt footer</label>
                            <textarea v-model="form.receipt_footer" rows="2" class="w-full rounded-xl border border-slate-700 bg-slate-950 px-4 py-3 text-white outline-none transition focus:border-cyan-500" />
                        </div>

                        <label class="flex items-center gap-3 text-sm text-slate-300">
                            <input v-model="form.is_active" type="checkbox" class="h-4 w-4 rounded border-slate-600 bg-slate-950" />
                            Tenant actief
                        </label>

                        <div class="flex flex-wrap gap-3 pt-2">
                            <button
                                type="button"
                                :disabled="saving"
                                class="rounded-xl bg-cyan-600 px-4 py-3 text-sm font-semibold text-white transition hover:bg-cyan-500 disabled:opacity-60"
                                @click="submitForm"
                            >
                                {{ saving ? 'Opslaan...' : isEditing ? 'Tenant opslaan' : 'Tenant toevoegen' }}
                            </button>
                            <button
                                type="button"
                                class="rounded-xl border border-slate-700 px-4 py-3 text-sm font-medium text-slate-300 transition hover:bg-slate-800"
                                @click="$emit('close')"
                            >
                                Annuleren
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </teleport>
</template>

<script setup>
import { computed, reactive, watch } from 'vue'

const props = defineProps({
    open: { type: Boolean, default: false },
    tenant: { type: Object, default: null },
    saving: { type: Boolean, default: false },
    error: { type: String, default: '' },
})

const emit = defineEmits(['close', 'submit'])

const form = reactive({
    name: '',
    company_name: '',
    slug: '',
    email: '',
    phone: '',
    vat_number: '',
    street: '',
    number: '',
    postal_code: '',
    city: '',
    country: '',
    receipt_footer: '',
    is_active: true,
})

const isEditing = computed(() => !!props.tenant?.id)

watch(
    () => [props.open, props.tenant],
    ([open]) => {
        if (open) {
            fillForm()
        } else {
            resetForm()
        }
    },
    { immediate: true, deep: true },
)

function fillForm() {
    const t = props.tenant
    form.name = t?.name ?? ''
    form.company_name = t?.company_name ?? ''
    form.slug = t?.slug ?? ''
    form.email = t?.email ?? ''
    form.phone = t?.phone ?? ''
    form.vat_number = t?.vat_number ?? ''
    form.street = t?.street ?? ''
    form.number = t?.number ?? ''
    form.postal_code = t?.postal_code ?? ''
    form.city = t?.city ?? ''
    form.country = t?.country ?? ''
    form.receipt_footer = t?.receipt_footer ?? ''
    form.is_active = t?.is_active ?? true
}

function resetForm() {
    form.name = ''
    form.company_name = ''
    form.slug = ''
    form.email = ''
    form.phone = ''
    form.vat_number = ''
    form.street = ''
    form.number = ''
    form.postal_code = ''
    form.city = ''
    form.country = ''
    form.receipt_footer = ''
    form.is_active = true
}

function submitForm() {
    emit('submit', { ...form })
}
</script>
